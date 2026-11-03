<?php
/**
 * Ads Class. Sponsor placements (self-serve ads) and the AdSense fallback.
 *
 * - Placements and their seasonal prices (WP Admin -> Ads -> Prices).
 * - `mm_ad` posts: one booked placement each (creative, period, status). `mm_advertiser`: who pays.
 * - Picking: up to SEATS live ads per placement rotate evenly; one ad per page or email; never for PRO groups.
 * - Cards: on group pages (server-rendered), gift guides (fetched, since those pages are cached) and emails.
 * - First-party stats: viewable impressions (beacon), email sends/opens (pixel), clicks (redirect with bot
 *   filtering, UTM tags), per day / variant / placement / link / device, plus click segments for audience
 *   insights. Daily counters in {prefix}mm_ad_stats; unique clicks/opens/viewers in {prefix}mm_ad_uniq.
 * - Where no sponsor is live, group-page slots fall back to an AdSense placeholder that Theme.js loads
 *   only when it scrolls near the screen.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Ads
 */
class Ads {
    const CPT            = 'mm_ad';
    const ADV            = 'mm_advertiser';
    const SEATS          = 3;
    const DB_VERSION     = 1;
    const PRICES_OPT     = 'mm_ad_prices';
    const CRON           = 'mm_ads_hourly';
    const ADSENSE_CLIENT = 'ca-pub-8648985139343614';

    /** AdSense fallback per slot: ad unit id, fixed size (w, h) or 0 for responsive. */
    protected static $adsense = array(
        'match-ideas'      => array( '3964563495', 300, 250 ),
        'dashboard-bottom' => array( '3637831254', 0, 0 ),
    );

    protected static $hooked = false;
    protected static $shown = array(); // one sponsor per page: slots already filled in this request

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'init', array( __CLASS__, 'install' ), 0 );
        add_action( 'init', array( __CLASS__, 'redirect' ), 1 ); // click + open pixel, before page rendering
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_action( self::CRON, array( __CLASS__, 'hourly' ) );

        if ( is_admin() ) {
            add_action( 'add_meta_boxes', array( __CLASS__, 'metaBoxes' ) );
            add_action( 'save_post_' . self::CPT, array( __CLASS__, 'saveAd' ), 10, 2 );
            add_action( 'save_post_' . self::ADV, array( __CLASS__, 'saveAdvertiser' ), 10, 2 );
            add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
            add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
            add_filter( 'post_row_actions', array( __CLASS__, 'rowActions' ), 10, 2 );
            add_action( 'admin_post_mm_ad_review', array( __CLASS__, 'review' ) );
            add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
            add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
        }
    }

    /* ------------------------------------------------------------------ Setup */

    public static function statsTable() {
        global $wpdb;
        return $wpdb->prefix . 'mm_ad_stats';
    }

    public static function uniqTable() {
        global $wpdb;
        return $wpdb->prefix . 'mm_ad_uniq';
    }

    public static function install() {
        if ( (int) get_option( 'mm_ads_db' ) < self::DB_VERSION ) {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $c = $wpdb->get_charset_collate();
            dbDelta( 'CREATE TABLE ' . self::statsTable() . " (
                ad_id bigint(20) unsigned NOT NULL,
                variant char(1) NOT NULL DEFAULT 'a',
                d date NOT NULL,
                slot varchar(40) NOT NULL DEFAULT '',
                link tinyint(3) unsigned NOT NULL DEFAULT 0,
                metric varchar(10) NOT NULL,
                device char(1) NOT NULL DEFAULT '',
                seg varchar(16) NOT NULL DEFAULT '',
                n int(10) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY  (ad_id,variant,d,slot,link,metric,device,seg),
                KEY d (d)
            ) $c;" );
            dbDelta( 'CREATE TABLE ' . self::uniqTable() . " (
                ad_id bigint(20) unsigned NOT NULL,
                d date NOT NULL,
                kind char(2) NOT NULL,
                h char(16) NOT NULL,
                PRIMARY KEY  (ad_id,d,kind,h),
                KEY d (d)
            ) $c;" );
            update_option( 'mm_ads_db', self::DB_VERSION, false );
        }

        if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( time() + 300, 'hourly', self::CRON );
    }

    public static function register() {
        $common = array(
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'capabilities'        => array( 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'read_private_posts' => 'manage_options' ),
            'map_meta_cap'        => true,
        );
        register_post_type( self::CPT, $common + array(
            'labels'        => array( 'name' => 'Ads', 'singular_name' => 'Ad', 'add_new_item' => 'Add ad', 'edit_item' => 'Edit ad', 'menu_name' => 'Ads' ),
            'show_in_menu'  => true,
            'menu_icon'     => 'dashicons-megaphone',
            'menu_position' => 27,
        ) );
        register_post_type( self::ADV, $common + array(
            'labels'       => array( 'name' => 'Advertisers', 'singular_name' => 'Advertiser', 'add_new_item' => 'Add advertiser', 'edit_item' => 'Edit advertiser' ),
            'show_in_menu' => 'edit.php?post_type=' . self::CPT,
        ) );
    }

    /* ------------------------------------------------------------------ Placements, seasons, prices */

    /**
     * Placement key => label, where, kind (email|web), season (xmas: Sep 1-Dec 20 only; year: always),
     * price group. `guide` and `registry*` take a target (page slug / occasion).
     */
    public static function placements() {
        return array(
            'email-match'      => array( 'label' => 'Shuffle email', 'where' => 'The email everyone gets when their group is shuffled ("your match is ready")', 'kind' => 'email', 'season' => 'xmas' ),
            'email-reminder'   => array( 'label' => '3-day reminder email', 'where' => 'The email 3 days before the exchange', 'kind' => 'email', 'season' => 'xmas' ),
            'match-ideas'      => array( 'label' => 'Match screen', 'where' => 'Above the gift ideas, where members see who they are buying for', 'kind' => 'web', 'season' => 'xmas' ),
            'dashboard-bottom' => array( 'label' => 'Group page', 'where' => 'Bottom of every group page', 'kind' => 'web', 'season' => 'xmas' ),
            'guide'            => array( 'label' => 'Gift guide page', 'where' => 'A gift guide or landing page, after its intro', 'kind' => 'web', 'season' => 'year', 'target' => 'page' ),
            'registry-major'   => array( 'label' => 'Registry: wedding / debut', 'where' => 'Wedding and debut registries', 'kind' => 'web', 'season' => 'year', 'target' => 'occasion' ),
            'registry'         => array( 'label' => 'Registry: other occasions', 'where' => 'Registries of one occasion (baptism, baby shower, birthday...)', 'kind' => 'web', 'season' => 'year', 'target' => 'occasion' ),
        );
    }

    /** Starting prices (PHP per seat): placement => peak [weekly, monthly], shoulder [weekly, monthly]. */
    public static function defaultPrices() {
        return array(
            'email-match'      => array( 'peak' => array( 1500, 4900 ), 'shoulder' => array( 500, 1500 ) ),
            'email-reminder'   => array( 'peak' => array( 1000, 3500 ), 'shoulder' => array( 300, 900 ) ),
            'match-ideas'      => array( 'peak' => array( 800, 2500 ), 'shoulder' => array( 300, 900 ) ),
            'dashboard-bottom' => array( 'peak' => array( 800, 2500 ), 'shoulder' => array( 300, 900 ) ),
            'guide'            => array( 'peak' => array( 500, 1500 ), 'shoulder' => array( 300, 900 ) ),
            'registry-major'   => array( 'peak' => array( 500, 1500 ), 'shoulder' => array( 500, 1500 ) ),
            'registry'         => array( 'peak' => array( 300, 900 ), 'shoulder' => array( 300, 900 ) ),
        );
    }

    public static function prices() {
        $p = get_option( self::PRICES_OPT );
        return is_array( $p ) ? array_replace_recursive( self::defaultPrices(), $p ) : self::defaultPrices();
    }

    /** 'peak' (Nov 1-Dec 20), 'shoulder' (rest of the selling window) or 'closed' (xmas placements Dec 21-Aug 31). */
    public static function season( $placement, $ts = null ) {
        $md = (int) wp_date( 'md', $ts === null ? time() : $ts );
        $peak = $md >= 1101 && $md <= 1220;
        $p = self::placements()[ $placement ] ?? null;
        if ( $p && $p['season'] === 'xmas' && ! $peak && ! ( $md >= 901 && $md <= 1031 ) ) return 'closed';

        return $peak ? 'peak' : 'shoulder';
    }

    /** Price (PHP) of one seat for a cadence ('weekly'|'monthly') right now; 0 when closed. */
    public static function price( $placement, $cadence, $ts = null ) {
        $season = self::season( $placement, $ts );
        if ( $season === 'closed' ) return 0;
        $row = self::prices()[ $placement ][ $season ] ?? array( 0, 0 );

        return (int) ( $cadence === 'monthly' ? $row[1] : $row[0] );
    }

    /* ------------------------------------------------------------------ Ad data */

    public static function meta( $id, $key, $default = '' ) {
        $v = get_post_meta( (int) $id, '_mm_ad_' . $key, true );
        return ( $v === '' || $v === null ) ? $default : $v;
    }

    /** Creative: brand, logo (url), headline, text, button, links [[label, url] x3], promo, b [headline, text, button]. */
    public static function creative( $id ) {
        $c = get_post_meta( (int) $id, '_mm_ad_creative', true );
        $c = is_array( $c ) ? $c : array();

        return $c + array( 'brand' => '', 'logo' => '', 'headline' => '', 'text' => '', 'button' => 'Shop now', 'links' => array(), 'promo' => '', 'b' => array() );
    }

    public static function isLive( $id ) {
        return get_post_type( $id ) === self::CPT && self::meta( $id, 'status' ) === 'live' && (int) self::meta( $id, 'end', 0 ) > time();
    }

    /** Live ads for a placement (+ target), newest booking first, at most SEATS. Cached 10 minutes. */
    public static function liveAds( $placement, $target = '' ) {
        $key = 'mm_ads_live_' . md5( $placement . '|' . $target );
        $ids = get_transient( $key );
        if ( ! is_array( $ids ) ) {
            $mq = array(
                array( 'key' => '_mm_ad_status', 'value' => 'live' ),
                array( 'key' => '_mm_ad_slot', 'value' => $placement ),
                array( 'key' => '_mm_ad_end', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
            );
            if ( $target !== '' ) $mq[] = array( 'key' => '_mm_ad_target', 'value' => $target );
            $ids = array_map( 'intval', get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => self::SEATS, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC', 'meta_query' => $mq ) ) );
            set_transient( $key, $ids, 10 * MINUTE_IN_SECONDS );
        }

        return array_values( array_filter( $ids, array( __CLASS__, 'isLive' ) ) );
    }

    public static function flush() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_mm\\_ads\\_live\\_%' OR option_name LIKE '\\_transient\\_timeout\\_mm\\_ads\\_live\\_%'" );
        do_action( 'litespeed_purge_all' ); // cached gift guides pick up or drop their ad slot
    }

    /** One ad for a slot, evenly rotated (random pick among the live seats), with its variant. Null when none. */
    public static function pick( $placement, $target = '' ) {
        $ids = self::liveAds( $placement, $target );
        if ( ! $ids ) return null;
        $id = $ids[ array_rand( $ids ) ];
        $c = self::creative( $id );
        $v = ( ! empty( $c['b']['headline'] ) && mt_rand( 0, 1 ) ) ? 'b' : 'a';

        return array( 'id' => $id, 'v' => $v, 'c' => $c );
    }

    /** Creative fields for a variant (variant B overrides headline, text and button). */
    protected static function variant( array $c, $v ) {
        if ( $v === 'b' && ! empty( $c['b'] ) ) {
            foreach ( array( 'headline', 'text', 'button' ) as $k ) if ( ! empty( $c['b'][ $k ] ) ) $c[ $k ] = $c['b'][ $k ];
        }

        return $c;
    }

    /* ------------------------------------------------------------------ Rendering */

    /**
     * The ad for a group-page slot: '' for PRO groups, a sponsor card when one is live, else the
     * AdSense placeholder (slots that have one).
     */
    public static function slot( $slot, $gid = 0 ) {
        if ( self::isProGroup( $gid ) ) return '';
        $card = self::sponsorCard( $slot, $gid );

        return $card !== '' ? $card : self::adsense( $slot );
    }

    public static function isProGroup( $gid ) {
        return $gid && function_exists( 'get_field' ) && get_field( 'pro', $gid );
    }

    /** Sponsor card for a web placement ('' when none, PRO, or a sponsor already shown on this page). */
    public static function sponsorCard( $placement, $gid = 0, $target = '' ) {
        if ( self::isProGroup( $gid ) || ! empty( self::$shown['web'] ) ) return '';
        $ad = self::pick( $placement, $target );
        if ( ! $ad ) return '';
        self::$shown['web'] = true;

        return self::cardHtml( $ad, $placement, $gid );
    }

    public static function clickUrl( $id, $placement, $v, $link = 0, $gid = 0, $token = '' ) {
        $args = array( 'mm_sp' => (int) $id, 's' => $placement, 'v' => $v );
        if ( $link ) $args['l'] = (int) $link;
        if ( $gid ) $args['g'] = (int) $gid;
        if ( $token !== '' ) $args['t'] = $token;

        return add_query_arg( $args, home_url( '/' ) );
    }

    public static function cardHtml( array $ad, $placement, $gid = 0 ) {
        $c = self::variant( $ad['c'], $ad['v'] );
        $id = (int) $ad['id'];

        $h  = '<aside class="mm-spon" data-mm-spon="' . $id . '" data-v="' . esc_attr( $ad['v'] ) . '" data-s="' . esc_attr( $placement ) . '">';
        $h .= '<span class="mm-spon__label">Sponsored</span>';
        $h .= '<div class="mm-spon__body">';
        if ( $c['logo'] ) $h .= '<img class="mm-spon__logo" src="' . esc_url( $c['logo'] ) . '" alt="' . esc_attr( $c['brand'] ) . '" width="56" height="56" loading="lazy">';
        $h .= '<div class="mm-spon__text"><span class="mm-spon__brand">' . esc_html( $c['brand'] ) . '</span>';
        $h .= '<strong class="mm-spon__head">' . esc_html( $c['headline'] ) . '</strong>';
        if ( $c['text'] ) $h .= '<span class="mm-spon__copy">' . esc_html( $c['text'] ) . '</span>';
        if ( $c['promo'] ) $h .= '<span class="mm-spon__promo">Use code <b>' . esc_html( $c['promo'] ) . '</b></span>';
        $h .= '</div></div>';
        $h .= '<div class="mm-spon__actions"><a class="mm-spon__btn" href="' . esc_url( self::clickUrl( $id, $placement, $ad['v'], 0, $gid ) ) . '" target="_blank" rel="sponsored noopener">' . esc_html( $c['button'] ?: 'Learn more' ) . '</a>';
        foreach ( array_slice( (array) $c['links'], 1, 2 ) as $i => $l ) {
            if ( empty( $l[1] ) ) continue;
            $h .= '<a class="mm-spon__link" href="' . esc_url( self::clickUrl( $id, $placement, $ad['v'], $i + 1, $gid ) ) . '" target="_blank" rel="sponsored noopener">' . esc_html( $l[0] ?: 'More' ) . '</a>';
        }
        $h .= '</div></aside>';

        return $h;
    }

    /** Per-recipient email token: send time (base 36) + random part. Used for unique opens/clicks and scanner filtering. */
    public static function token() {
        return base_convert( (string) time(), 10, 36 ) . '-' . strtolower( wp_generate_password( 8, false ) );
    }

    /**
     * Sponsor block for an email ('' for PRO groups or when no sponsor is live). Counts the send.
     * Goes below the main message and gift ideas.
     */
    public static function emailBlock( $placement, $gid ) {
        if ( self::isProGroup( $gid ) ) return '';
        $ad = self::pick( $placement );
        if ( ! $ad ) return '';
        $c = self::variant( $ad['c'], $ad['v'] );
        $id = (int) $ad['id'];
        $t = self::token();
        self::bump( $id, 'sent', array( 'v' => $ad['v'], 'slot' => $placement ) );

        $pixel = add_query_arg( array( 'mm_spo' => $id, 's' => $placement, 'v' => $ad['v'], 't' => $t ), home_url( '/' ) );
        $btn = 'display:inline-block;padding:10px 18px;border-radius:999px;background:#1b1b1b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;';

        $h  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 0;font-family:Arial,Helvetica,sans-serif;border:1px solid #e6e6e6;border-radius:12px;">';
        $h .= '<tr><td style="padding:14px 18px 0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#8c8f94;">Sponsored</td></tr>';
        $h .= '<tr><td style="padding:8px 18px 16px;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>';
        if ( $c['logo'] ) $h .= '<td width="64" valign="top" style="padding-right:12px;"><img src="' . esc_url( $c['logo'] ) . '" width="56" height="56" alt="' . esc_attr( $c['brand'] ) . '" style="display:block;border-radius:10px;"></td>';
        $h .= '<td valign="top"><div style="font-size:12px;color:#6d6d6d;">' . esc_html( $c['brand'] ) . '</div>';
        $h .= '<div style="margin-top:2px;font-size:16px;font-weight:bold;color:#1b1b1b;">' . esc_html( $c['headline'] ) . '</div>';
        if ( $c['text'] ) $h .= '<div style="margin-top:4px;font-size:14px;color:#3a3a3a;">' . esc_html( $c['text'] ) . '</div>';
        if ( $c['promo'] ) $h .= '<div style="margin-top:6px;font-size:13px;color:#1f8580;">Use code <b>' . esc_html( $c['promo'] ) . '</b></div>';
        $h .= '<div style="margin-top:12px;"><a href="' . esc_url( self::clickUrl( $id, $placement, $ad['v'], 0, $gid, $t ) ) . '" target="_blank" rel="sponsored noopener" style="' . $btn . '">' . esc_html( $c['button'] ?: 'Learn more' ) . '</a></div>';
        $h .= '</td></tr></table></td></tr></table>';
        $h .= '<img src="' . esc_url( $pixel ) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">';

        return $h;
    }

    /** AdSense placeholder; Theme.initAdSense turns it into a live ad near the viewport. */
    public static function adsense( $slot ) {
        if ( empty( self::$adsense[ $slot ] ) ) return '';
        list( $unit, $w, $h ) = self::$adsense[ $slot ];

        $ins = $w
            ? '<ins class="adsbygoogle" style="display:inline-block;width:' . (int) $w . 'px;height:' . (int) $h . 'px" data-ad-client="' . self::ADSENSE_CLIENT . '" data-ad-slot="' . esc_attr( $unit ) . '"></ins>'
            : '<ins class="adsbygoogle" style="display:block" data-ad-client="' . self::ADSENSE_CLIENT . '" data-ad-slot="' . esc_attr( $unit ) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>';

        // The <ins> waits in a template so nothing loads until the slot is near the screen.
        return '<div class="mm-adslot' . ( $w ? ' mm-adslot--fixed' : '' ) . '" data-mm-adsense><template>' . $ins . '</template></div>';
    }

    /** Placeholder for cached pages (gift guides): Theme.js fetches the card, so rotation and end dates stay right. */
    public static function lazySlot( $placement, $target = '' ) {
        // Only when this page has a booked ad, so other visits make no extra request. Ad changes purge the page cache.
        if ( ! self::liveAds( $placement, $target ) ) return '';

        return '<div class="mm-spon-slot" data-mm-spon-slot="' . esc_attr( $placement ) . '" data-target="' . esc_attr( $target ) . '"></div>';
    }

    /* ------------------------------------------------------------------ Stats */

    /** One counter: +$n for ad / metric with dims (v, slot, link, device, seg). */
    public static function bump( $id, $metric, array $dims = array(), $n = 1 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::statsTable() . ' (ad_id, variant, d, slot, link, metric, device, seg, n) VALUES (%d, %s, %s, %s, %d, %s, %s, %s, %d) ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
            (int) $id, substr( (string) ( $dims['v'] ?? 'a' ), 0, 1 ), wp_date( 'Y-m-d' ), substr( (string) ( $dims['slot'] ?? '' ), 0, 40 ), (int) ( $dims['link'] ?? 0 ),
            $metric, substr( (string) ( $dims['device'] ?? '' ), 0, 1 ), substr( (string) ( $dims['seg'] ?? '' ), 0, 16 ), (int) $n
        ) );
    }

    /** True the first time this (ad, kind, hash) is seen today. */
    public static function firstToday( $id, $kind, $raw ) {
        global $wpdb;
        $h = substr( hash( 'sha256', $raw . '|' . wp_salt( 'nonce' ) ), 0, 16 );

        return (bool) $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::uniqTable() . ' (ad_id, d, kind, h) VALUES (%d, %s, %s, %s)', (int) $id, wp_date( 'Y-m-d' ), $kind, $h ) );
    }

    public static function device() {
        return wp_is_mobile() ? 'm' : 'd';
    }

    public static function isBot() {
        $ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );

        return $ua === '' || (bool) preg_match( '~bot|crawl|spider|slurp|preview|facebookexternalhit|mediapartners|curl|wget|python|scan|safelinks|proofpoint|mimecast|barracuda|headless|monitor|feedfetcher|google-read-aloud|bingpreview~i', $ua );
    }

    protected static function visitor() {
        return ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    }

    /** Audience segments of a click (aggregate only): budget band, group size band, weekday+hour. */
    protected static function segments( $gid ) {
        $s = array( 'h:' . wp_date( 'N' ) . wp_date( 'H' ) );
        if ( $gid && get_post_type( $gid ) === 'groups' ) {
            $b = class_exists( 'Affiliate' ) ? Affiliate::budget( $gid ) : 0;
            if ( $b ) $s[] = 'b:' . ( $b <= 300 ? '300' : ( $b < 1000 ? '500' : '1000+' ) );
            $n = class_exists( 'Groups' ) ? count( Groups::memberIds( $gid ) ) : 0;
            if ( $n ) $s[] = 'z:' . ( $n <= 10 ? '1-10' : ( $n <= 30 ? '11-30' : '31+' ) );
        }

        return $s;
    }

    /** Destination with UTM tags appended (existing query and fragment kept; tags the advertiser set win). */
    public static function withUtm( $url, $placement, $v, $id ) {
        $kind = ( self::placements()[ $placement ]['kind'] ?? 'web' ) === 'email' ? 'email' : 'web';
        $frag = '';
        if ( ( $p = strpos( $url, '#' ) ) !== false ) {
            $frag = substr( $url, $p );
            $url = substr( $url, 0, $p );
        }
        $q = array();
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
        $add = array();
        foreach ( array( 'utm_source' => 'manitomanita', 'utm_medium' => $kind, 'utm_campaign' => 'ad-' . (int) $id, 'utm_content' => $placement . '-' . $v ) as $k => $val ) {
            if ( ! isset( $q[ $k ] ) ) $add[] = $k . '=' . rawurlencode( $val );
        }

        return $add ? $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . implode( '&', $add ) . $frag : $url . $frag;
    }

    /** The ad's link for an index (0 = main button), https only. */
    public static function linkUrl( $id, $i ) {
        $c = self::creative( $id );
        $u = (string) ( $c['links'][ (int) $i ][1] ?? ( $c['links'][0][1] ?? '' ) );

        return preg_match( '~^https://~i', $u ) ? $u : '';
    }

    /**
     * /?mm_sp=<ad>&s=<placement>&v=<a|b>&l=<link>&g=<group>&t=<token>: count the click, then send the visitor
     * to the advertiser (with UTM tags). /?mm_spo=...: email open pixel.
     */
    public static function redirect() {
        if ( ! empty( $_GET['mm_spo'] ) ) self::openPixel();
        if ( empty( $_GET['mm_sp'] ) ) return;

        $id = absint( $_GET['mm_sp'] );
        $placement = sanitize_key( (string) ( $_GET['s'] ?? '' ) );
        $v = ( $_GET['v'] ?? 'a' ) === 'b' ? 'b' : 'a';
        $link = min( 2, absint( $_GET['l'] ?? 0 ) );
        $gid = absint( $_GET['g'] ?? 0 );
        $t = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $_GET['t'] ?? '' ) ) );
        $dest = get_post_type( $id ) === self::CPT ? self::linkUrl( $id, $link ) : '';

        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        if ( $dest === '' ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        if ( self::countable( $id, $t ) ) {
            // The same visitor clicking again within 30 seconds counts once.
            $k = 'mm_spc_' . md5( self::visitor() . '|' . $id . '|' . $link );
            if ( ! get_transient( $k ) ) {
                set_transient( $k, 1, 30 );
                $dims = array( 'v' => $v, 'slot' => $placement, 'link' => $link, 'device' => self::device() );
                self::bump( $id, 'click', $dims );
                if ( self::firstToday( $id, 'uc', $t !== '' ? 't|' . $t : 'v|' . self::visitor() ) ) self::bump( $id, 'uclick', $dims );
                foreach ( self::segments( $gid ) as $seg ) self::bump( $id, 'seg', array( 'v' => $v, 'slot' => $placement, 'seg' => $seg ) );
            }
        }

        wp_redirect( self::withUtm( $dest, $placement, $v, $id ), 302 );
        exit;
    }

    /** Real person on a live ad: not a bot, and not an email scanner opening links right after sending. */
    protected static function countable( $id, $t ) {
        if ( ! self::isLive( $id ) || self::isBot() ) return false;
        if ( $t !== '' ) {
            $sent = (int) base_convert( strtok( $t, '-' ), 36, 10 );
            if ( $sent && time() - $sent < 2 ) return false;
            if ( $sent && $sent < time() - 120 * DAY_IN_SECONDS ) return false;
        }

        return true;
    }

    protected static function openPixel() {
        $id = absint( $_GET['mm_spo'] );
        $t = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $_GET['t'] ?? '' ) ) );
        $placement = sanitize_key( (string) ( $_GET['s'] ?? '' ) );
        $v = ( $_GET['v'] ?? 'a' ) === 'b' ? 'b' : 'a';

        if ( $t !== '' && get_post_type( $id ) === self::CPT && self::countable( $id, $t ) && self::firstToday( $id, 'op', 't|' . $t ) ) {
            self::bump( $id, 'open', array( 'v' => $v, 'slot' => $placement ) );
        }

        nocache_headers();
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    public static function routes() {
        register_rest_route( 'mm/v1', '/ad-view', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restView' ),
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( 'mm/v1', '/ad-slot', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'restSlot' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /** Viewable impression beacon: ad, v, s, u (1 = first view of this ad in this browser today). */
    public static function restView( $req ) {
        $id = absint( $req->get_param( 'ad' ) );
        $ok = $id && self::isLive( $id ) && ! self::isBot();

        // At most 120 views per visitor per 10 minutes.
        $rk = 'mm_spv_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $rate = (int) get_transient( $rk );
        if ( $ok && $rate < 120 ) {
            set_transient( $rk, $rate + 1, 10 * MINUTE_IN_SECONDS );
            $dims = array( 'v' => $req->get_param( 'v' ) === 'b' ? 'b' : 'a', 'slot' => sanitize_key( (string) $req->get_param( 's' ) ), 'device' => self::device() );
            self::bump( $id, 'view', $dims );
            if ( $req->get_param( 'u' ) ) self::bump( $id, 'uview', $dims );
        }

        $r = new WP_REST_Response( null, 204 );
        $r->header( 'Cache-Control', 'no-store' );

        return $r;
    }

    /** Card HTML for a cached page's slot ('' when none is live). */
    public static function restSlot( $req ) {
        $placement = sanitize_key( (string) $req->get_param( 's' ) );
        $target = sanitize_title( (string) $req->get_param( 't' ) );
        $ad = isset( self::placements()[ $placement ] ) ? self::pick( $placement, $target ) : null;

        $r = new WP_REST_Response( array( 'html' => $ad ? self::cardHtml( $ad, $placement ) : '' ), 200 );
        $r->header( 'Cache-Control', 'no-store' );

        return $r;
    }

    /** Hourly: live ads past their end date end; unique-hash rows older than 90 days go. */
    public static function hourly() {
        global $wpdb;
        $ended = get_posts( array( 'post_type' => self::CPT, 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array(
            array( 'key' => '_mm_ad_status', 'value' => 'live' ),
            array( 'key' => '_mm_ad_end', 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC' ),
        ) ) );
        foreach ( $ended as $id ) update_post_meta( $id, '_mm_ad_status', 'ended' );
        if ( $ended ) self::flush();

        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::uniqTable() . ' WHERE d < %s LIMIT 5000', wp_date( 'Y-m-d', strtotime( '-90 days' ) ) ) );
    }

    /** Totals per metric for an ad (optionally a date range), plus seats live per placement. */
    public static function totals( $id, $from = '', $to = '' ) {
        global $wpdb;
        $where = $wpdb->prepare( 'ad_id = %d', (int) $id );
        if ( $from && $to ) $where .= $wpdb->prepare( ' AND d BETWEEN %s AND %s', $from, $to );
        $out = array_fill_keys( array( 'view', 'uview', 'sent', 'open', 'click', 'uclick' ), 0 );
        foreach ( $wpdb->get_results( 'SELECT metric, SUM(n) n FROM ' . self::statsTable() . " WHERE $where AND metric <> 'seg' GROUP BY metric" ) as $r ) {
            $out[ $r->metric ] = (int) $r->n;
        }

        return $out;
    }

    /* ------------------------------------------------------------------ Admin */

    public static function statuses() {
        return array( 'checkout' => 'Awaiting payment', 'review' => 'Needs review', 'live' => 'Live', 'paused' => 'Paused', 'ended' => 'Ended', 'rejected' => 'Rejected' );
    }

    public static function metaBoxes() {
        add_meta_box( 'mm_ad_box', 'Ad', array( __CLASS__, 'adBox' ), self::CPT, 'normal', 'high' );
        add_meta_box( 'mm_ad_stats', 'Results', array( __CLASS__, 'statsBox' ), self::CPT, 'side' );
        add_meta_box( 'mm_adv_box', 'Advertiser', array( __CLASS__, 'advBox' ), self::ADV, 'normal', 'high' );
    }

    public static function adBox( $post ) {
        $c = self::creative( $post->ID );
        $f = function ( $k, $d = '' ) use ( $post ) { return self::meta( $post->ID, $k, $d ); };
        $links = array_pad( (array) $c['links'], 3, array( '', '' ) );
        wp_nonce_field( 'mm_ad_save', 'mm_ad_nonce' );
        $date = function ( $ts ) { return $ts ? wp_date( 'Y-m-d\TH:i', (int) $ts ) : ''; };
        $in = function ( $name, $val, $attrs = '' ) { return '<input type="text" class="widefat" name="' . $name . '" value="' . esc_attr( $val ) . '" ' . $attrs . '>'; };

        echo '<style>.mm-adf{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 16px}.mm-adf label{display:grid;gap:4px;font-weight:600}.mm-adf h4{grid-column:1/-1;margin:10px 0 0}.mm-adf small{font-weight:400;color:#646970}</style><div class="mm-adf">';
        echo '<h4>Booking</h4>';
        echo '<label>Placement<select name="mm_ad[slot]">';
        foreach ( self::placements() as $k => $p ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $f( 'slot' ), $k, false ) . '>' . esc_html( $p['label'] ) . '</option>';
        echo '</select></label>';
        echo '<label>Target <small>Gift guide: page slug. Registry: occasion.</small>' . $in( 'mm_ad[target]', $f( 'target' ) ) . '</label>';
        echo '<label>Status<select name="mm_ad[status]">';
        foreach ( self::statuses() as $k => $l ) echo '<option value="' . esc_attr( $k ) . '"' . selected( $f( 'status', 'review' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
        echo '</select></label>';
        echo '<label>Billing<select name="mm_ad[cadence]"><option value="weekly"' . selected( $f( 'cadence', 'weekly' ), 'weekly', false ) . '>Weekly</option><option value="monthly"' . selected( $f( 'cadence' ), 'monthly', false ) . '>Monthly</option></select></label>';
        echo '<label>Starts<input type="datetime-local" name="mm_ad[start]" value="' . esc_attr( $date( $f( 'start', 0 ) ) ) . '"></label>';
        echo '<label>Paid through <small>The ad stops after this.</small><input type="datetime-local" name="mm_ad[end]" value="' . esc_attr( $date( $f( 'end', 0 ) ) ) . '"></label>';
        echo '<label>Amount paid so far (&#8369;) <small>For cost per click.</small><input type="number" min="0" step="1" name="mm_ad[paid]" value="' . esc_attr( $f( 'paid', 0 ) ) . '"></label>';
        echo '<label>Advertiser<select name="mm_ad[adv]"><option value="0">&mdash;</option>';
        foreach ( get_posts( array( 'post_type' => self::ADV, 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $a ) echo '<option value="' . (int) $a->ID . '"' . selected( (int) $f( 'adv', 0 ), $a->ID, false ) . '>' . esc_html( $a->post_title ) . '</option>';
        echo '</select></label>';

        echo '<h4>Card</h4>';
        echo '<label>Brand name' . $in( 'mm_ad[c][brand]', $c['brand'], 'maxlength="40"' ) . '</label>';
        echo '<label>Logo URL <small>Square image, https.</small>' . $in( 'mm_ad[c][logo]', $c['logo'] ) . '</label>';
        echo '<label>Headline <small>Up to 50 characters.</small>' . $in( 'mm_ad[c][headline]', $c['headline'], 'maxlength="50"' ) . '</label>';
        echo '<label>Text <small>Up to 120 characters.</small>' . $in( 'mm_ad[c][text]', $c['text'], 'maxlength="120"' ) . '</label>';
        echo '<label>Button label' . $in( 'mm_ad[c][button]', $c['button'], 'maxlength="24"' ) . '</label>';
        echo '<label>Promo code <small>Optional.</small>' . $in( 'mm_ad[c][promo]', $c['promo'], 'maxlength="24"' ) . '</label>';
        foreach ( $links as $i => $l ) {
            echo '<label>Link ' . ( $i + 1 ) . ( $i ? ' label' : ' label <small>Main button.</small>' ) . $in( 'mm_ad[c][links][' . $i . '][0]', $l[0], 'maxlength="24"' ) . '</label>';
            echo '<label>Link ' . ( $i + 1 ) . ' URL <small>https only.</small>' . $in( 'mm_ad[c][links][' . $i . '][1]', $l[1] ) . '</label>';
        }

        echo '<h4>Version B <small>Optional. Rotates evenly with version A; leave empty for one version.</small></h4>';
        echo '<label>Headline B' . $in( 'mm_ad[c][b][headline]', $c['b']['headline'] ?? '', 'maxlength="50"' ) . '</label>';
        echo '<label>Text B' . $in( 'mm_ad[c][b][text]', $c['b']['text'] ?? '', 'maxlength="120"' ) . '</label>';
        echo '<label>Button B' . $in( 'mm_ad[c][b][button]', $c['b']['button'] ?? '', 'maxlength="24"' ) . '</label>';
        echo '</div>';

        if ( $c['headline'] ) {
            echo '<h4>Preview</h4><div style="max-width:560px">' . self::cardHtml( array( 'id' => $post->ID, 'v' => 'a', 'c' => $c ), $f( 'slot' ) ) . '</div>';
        }
    }

    public static function statsBox( $post ) {
        $t = self::totals( $post->ID );
        $paid = (float) self::meta( $post->ID, 'paid', 0 );
        $ctrBase = $t['view'] + $t['open'];
        $rows = array(
            'Views (on site)'   => number_format( $t['view'] ),
            'Unique viewers'    => number_format( $t['uview'] ),
            'Emails sent'       => number_format( $t['sent'] ),
            'Opens (estimated)' => number_format( $t['open'] ),
            'Clicks'            => number_format( $t['click'] ),
            'Unique clicks'     => number_format( $t['uclick'] ),
            'CTR'               => $ctrBase ? round( $t['click'] / $ctrBase * 100, 2 ) . '%' : '&ndash;',
            '&#8369; per click' => ( $paid && $t['click'] ) ? '&#8369;' . number_format( $paid / $t['click'], 2 ) : '&ndash;',
        );
        echo '<table class="widefat striped"><tbody>';
        foreach ( $rows as $k => $v ) echo '<tr><td>' . $k . '</td><td style="text-align:right"><b>' . $v . '</b></td></tr>';
        echo '</tbody></table><p class="description">CTR = clicks / (site views + email opens).</p>';
    }

    public static function advBox( $post ) {
        wp_nonce_field( 'mm_adv_save', 'mm_adv_nonce' );
        foreach ( array( 'email' => 'Email', 'name' => 'Contact name', 'contact' => 'Phone or social link' ) as $k => $l ) {
            echo '<p><label><b>' . $l . '</b><br><input type="text" class="widefat" name="mm_adv[' . $k . ']" value="' . esc_attr( get_post_meta( $post->ID, '_mm_adv_' . $k, true ) ) . '"></label></p>';
        }
    }

    public static function saveAdvertiser( $id, $post ) {
        if ( ! isset( $_POST['mm_adv_nonce'] ) || ! wp_verify_nonce( $_POST['mm_adv_nonce'], 'mm_adv_save' ) || ! current_user_can( 'manage_options' ) ) return;
        $in = (array) wp_unslash( $_POST['mm_adv'] ?? array() );
        update_post_meta( $id, '_mm_adv_email', sanitize_email( $in['email'] ?? '' ) );
        update_post_meta( $id, '_mm_adv_name', sanitize_text_field( $in['name'] ?? '' ) );
        update_post_meta( $id, '_mm_adv_contact', sanitize_text_field( $in['contact'] ?? '' ) );
    }

    /** Clean a creative from form input (lengths, https links). */
    public static function cleanCreative( array $c ) {
        $t = function ( $v, $max ) { return mb_substr( sanitize_text_field( (string) $v ), 0, $max ); };
        $u = function ( $v ) { $v = esc_url_raw( trim( (string) $v ), array( 'https' ) ); return preg_match( '~^https://~i', $v ) ? $v : ''; };
        $links = array();
        foreach ( array_slice( (array) ( $c['links'] ?? array() ), 0, 3 ) as $l ) {
            $url = $u( $l[1] ?? '' );
            if ( $url !== '' ) $links[] = array( $t( $l[0] ?? '', 24 ), $url );
        }
        $b = array( 'headline' => $t( $c['b']['headline'] ?? '', 50 ), 'text' => $t( $c['b']['text'] ?? '', 120 ), 'button' => $t( $c['b']['button'] ?? '', 24 ) );

        return array(
            'brand'    => $t( $c['brand'] ?? '', 40 ),
            'logo'     => $u( $c['logo'] ?? '' ),
            'headline' => $t( $c['headline'] ?? '', 50 ),
            'text'     => $t( $c['text'] ?? '', 120 ),
            'button'   => $t( $c['button'] ?? '', 24 ),
            'links'    => $links,
            'promo'    => $t( $c['promo'] ?? '', 24 ),
            'b'        => $b['headline'] !== '' ? $b : array(),
        );
    }

    public static function saveAd( $id, $post ) {
        if ( ! isset( $_POST['mm_ad_nonce'] ) || ! wp_verify_nonce( $_POST['mm_ad_nonce'], 'mm_ad_save' ) || ! current_user_can( 'manage_options' ) ) return;
        $in = (array) wp_unslash( $_POST['mm_ad'] ?? array() );
        $slot = isset( self::placements()[ $in['slot'] ?? '' ] ) ? $in['slot'] : 'match-ideas';
        $ts = function ( $v ) { $v = trim( (string) $v ); if ( $v === '' ) return 0; $d = date_create( $v, wp_timezone() ); return $d ? $d->getTimestamp() : 0; };

        update_post_meta( $id, '_mm_ad_slot', $slot );
        update_post_meta( $id, '_mm_ad_target', sanitize_title( (string) ( $in['target'] ?? '' ) ) );
        update_post_meta( $id, '_mm_ad_status', isset( self::statuses()[ $in['status'] ?? '' ] ) ? $in['status'] : 'review' );
        update_post_meta( $id, '_mm_ad_cadence', ( $in['cadence'] ?? '' ) === 'monthly' ? 'monthly' : 'weekly' );
        update_post_meta( $id, '_mm_ad_start', $ts( $in['start'] ?? '' ) );
        update_post_meta( $id, '_mm_ad_end', $ts( $in['end'] ?? '' ) );
        update_post_meta( $id, '_mm_ad_paid', max( 0, (float) ( $in['paid'] ?? 0 ) ) );
        update_post_meta( $id, '_mm_ad_adv', absint( $in['adv'] ?? 0 ) );
        update_post_meta( $id, '_mm_ad_creative', self::cleanCreative( (array) ( $in['c'] ?? array() ) ) );
        self::flush();
    }

    public static function columns( $cols ) {
        return array( 'cb' => $cols['cb'], 'title' => 'Ad', 'mm_slot' => 'Placement', 'mm_status' => 'Status', 'mm_end' => 'Paid through', 'mm_views' => 'Views / emails', 'mm_clicks' => 'Clicks', 'mm_ctr' => 'CTR' );
    }

    public static function column( $col, $id ) {
        static $cache = array();
        if ( ! isset( $cache[ $id ] ) ) $cache[ $id ] = self::totals( $id );
        $t = $cache[ $id ];
        $base = $t['view'] + $t['open'];
        switch ( $col ) {
            case 'mm_slot':
                $p = self::placements()[ self::meta( $id, 'slot' ) ] ?? null;
                echo esc_html( $p ? $p['label'] : '' ) . ( self::meta( $id, 'target' ) ? ' <small>(' . esc_html( self::meta( $id, 'target' ) ) . ')</small>' : '' );
                break;
            case 'mm_status':
                $s = self::meta( $id, 'status', 'review' );
                $color = array( 'live' => '#1f8a5b', 'review' => '#b8901f', 'rejected' => '#b32d2e' )[ $s ] ?? '#646970';
                echo '<b style="color:' . $color . '">' . esc_html( self::statuses()[ $s ] ?? $s ) . '</b>';
                break;
            case 'mm_end':
                $e = (int) self::meta( $id, 'end', 0 );
                echo $e ? esc_html( wp_date( 'M j, g:i A', $e ) ) : '&ndash;';
                break;
            case 'mm_views':
                echo number_format( $t['view'] ) . ' / ' . number_format( $t['sent'] );
                break;
            case 'mm_clicks':
                echo number_format( $t['click'] );
                break;
            case 'mm_ctr':
                echo $base ? round( $t['click'] / $base * 100, 2 ) . '%' : '&ndash;';
                break;
        }
    }

    public static function rowActions( $actions, $post ) {
        if ( $post->post_type !== self::CPT || ! current_user_can( 'manage_options' ) ) return $actions;
        $s = self::meta( $post->ID, 'status', 'review' );
        $url = function ( $do ) use ( $post ) { return wp_nonce_url( admin_url( 'admin-post.php?action=mm_ad_review&do=' . $do . '&id=' . $post->ID ), 'mm_ad_review_' . $post->ID ); };
        if ( in_array( $s, array( 'review', 'paused', 'rejected' ), true ) ) $actions['mm_approve'] = '<a href="' . esc_url( $url( 'approve' ) ) . '" style="color:#1f8a5b;font-weight:600">Approve</a>';
        if ( $s === 'review' ) $actions['mm_reject'] = '<a href="' . esc_url( $url( 'reject' ) ) . '" style="color:#b32d2e">Reject</a>';
        if ( $s === 'live' ) $actions['mm_pause'] = '<a href="' . esc_url( $url( 'pause' ) ) . '">Pause</a>';

        return $actions;
    }

    /** Approve (goes live now; a period is set from the cadence when none is), reject or pause. */
    public static function review() {
        $id = absint( $_GET['id'] ?? 0 );
        $do = sanitize_key( (string) ( $_GET['do'] ?? '' ) );
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'mm_ad_review_' . $id ) || get_post_type( $id ) !== self::CPT ) wp_die( 'Not allowed.' );

        if ( $do === 'approve' ) {
            if ( ! (int) self::meta( $id, 'start', 0 ) ) update_post_meta( $id, '_mm_ad_start', time() );
            if ( (int) self::meta( $id, 'end', 0 ) <= time() ) update_post_meta( $id, '_mm_ad_end', time() + ( self::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? 30 : 7 ) * DAY_IN_SECONDS );
            update_post_meta( $id, '_mm_ad_status', 'live' );
            if ( get_post_status( $id ) !== 'publish' ) wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        } elseif ( $do === 'reject' ) {
            update_post_meta( $id, '_mm_ad_status', 'rejected' );
        } elseif ( $do === 'pause' ) {
            update_post_meta( $id, '_mm_ad_status', 'paused' );
        }
        self::flush();

        wp_safe_redirect( add_query_arg( 'mm_ad_done', $do, admin_url( 'edit.php?post_type=' . self::CPT ) ) );
        exit;
    }

    public static function notices() {
        $do = sanitize_key( (string) ( $_GET['mm_ad_done'] ?? '' ) );
        $msg = array( 'approve' => 'Ad approved and live.', 'reject' => 'Ad rejected.', 'pause' => 'Ad paused.', 'prices' => 'Prices saved.' )[ $do ] ?? '';
        if ( $msg ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
    }

    public static function menu() {
        add_submenu_page( 'edit.php?post_type=' . self::CPT, 'Ad prices', 'Prices', 'manage_options', 'mm-ad-prices', array( __CLASS__, 'pricesPage' ) );
    }

    public static function pricesPage() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( isset( $_POST['mm_prices'] ) && check_admin_referer( 'mm_ad_prices' ) ) {
            $in = (array) wp_unslash( $_POST['mm_prices'] );
            $out = array();
            foreach ( self::placements() as $k => $p ) {
                foreach ( array( 'peak', 'shoulder' ) as $s ) {
                    $out[ $k ][ $s ] = array( max( 0, (int) ( $in[ $k ][ $s ][0] ?? 0 ) ), max( 0, (int) ( $in[ $k ][ $s ][1] ?? 0 ) ) );
                }
            }
            update_option( self::PRICES_OPT, $out, false );
            echo '<div class="notice notice-success"><p>Prices saved. Existing bookings keep the price they signed up at.</p></div>';
        }

        $prices = self::prices();
        echo '<div class="wrap"><h1>Ad prices</h1><p>Per seat (3 seats per placement rotate). Peak: Nov 1 &ndash; Dec 20. Shoulder: the rest of the selling window. Christmas placements are closed Dec 21 &ndash; Aug 31.</p>';
        echo '<form method="post">';
        wp_nonce_field( 'mm_ad_prices' );
        echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>Placement</th><th>Season</th><th>Peak weekly</th><th>Peak monthly</th><th>Shoulder weekly</th><th>Shoulder monthly</th><th>Seats live</th></tr></thead><tbody>';
        foreach ( self::placements() as $k => $p ) {
            $num = function ( $s, $i ) use ( $k, $prices ) { return '&#8369; <input type="number" min="0" step="50" style="width:90px" name="mm_prices[' . esc_attr( $k ) . '][' . $s . '][' . $i . ']" value="' . (int) $prices[ $k ][ $s ][ $i ] . '">'; };
            $live = $p['season'] === 'xmas' || empty( $p['target'] ) ? count( self::liveAds( $k ) ) . ' / ' . self::SEATS : 'per ' . $p['target'];
            echo '<tr><td><b>' . esc_html( $p['label'] ) . '</b><br><small>' . esc_html( $p['where'] ) . '</small></td><td>' . ( $p['season'] === 'xmas' ? 'Sep 1 &ndash; Dec 20' : 'Year-round' ) . '<br><small>Now: ' . esc_html( self::season( $k ) ) . '</small></td>';
            echo '<td>' . $num( 'peak', 0 ) . '</td><td>' . $num( 'peak', 1 ) . '</td><td>' . $num( 'shoulder', 0 ) . '</td><td>' . $num( 'shoulder', 1 ) . '</td><td>' . esc_html( $live ) . '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button( 'Save prices' );
        echo '</form></div>';
    }
}

Ads::init();
