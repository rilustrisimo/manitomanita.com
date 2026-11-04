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
            add_action( 'admin_post_mm_ad_manage', array( __CLASS__, 'manage' ) );
            add_filter( 'views_edit-' . self::CPT, array( __CLASS__, 'statusViews' ) );
            add_action( 'pre_get_posts', array( __CLASS__, 'filterList' ) );
            add_action( 'admin_post_nopriv_mm_ad_review', array( __CLASS__, 'reviewLogin' ) );
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

    /** Recorded payments (rows only; an unset meta is '' and must not become a blank row). */
    public static function payments( $id ) {
        $p = get_post_meta( (int) $id, '_mm_ad_payments', true );

        return is_array( $p ) ? array_values( array_filter( $p, 'is_array' ) ) : array();
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

        return $card !== '' ? $card : self::fallback( $slot );
    }

    /** Unsold slot: an "Advertise here" house card about 1 in 4 times (while ad pages exist), else AdSense. */
    public static function fallback( $slot ) {
        if ( get_option( 'mm_ads_pages' ) && isset( self::placements()[ $slot ] ) && self::season( $slot ) !== 'closed' && mt_rand( 1, 4 ) === 1 ) return self::houseCard( $slot );

        return self::adsense( $slot );
    }

    public static function houseCard( $slot ) {
        $price = self::price( $slot, 'weekly' );
        $url = home_url( '/advertise/' );

        return '<aside class="mm-spon mm-spon--house"><span class="mm-spon__label">Advertise here</span><div class="mm-spon__body"><div class="mm-spon__text">'
            . '<span class="mm-spon__brand">For shops and brands</span>'
            . '<strong class="mm-spon__head">This spot is open for your shop</strong>'
            . '<span class="mm-spon__copy">Reach people right when they&rsquo;re shopping for their Secret Santa gift.' . ( $price ? ' From &#8369;' . number_format( $price ) . ' a week, cancel anytime.' : '' ) . '</span>'
            . '</div></div><div class="mm-spon__actions"><a class="mm-spon__btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" data-mm-track="advertise_cta" data-mm-slot="' . esc_attr( 'house-' . $slot ) . '">See placements</a></div></aside>';
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

    /** $preview: no tracking and no click links (advertiser's own previews, sample cards). */
    public static function cardHtml( array $ad, $placement, $gid = 0, $preview = false ) {
        $c = self::variant( $ad['c'], $ad['v'] );
        $id = (int) $ad['id'];
        $href = function ( $link ) use ( $id, $placement, $ad, $gid, $preview, $c ) {
            if ( $preview ) return esc_url( (string) ( $c['links'][ $link ][1] ?? '#' ) );
            return esc_url( self::clickUrl( $id, $placement, $ad['v'], $link, $gid ) );
        };

        $h  = $preview ? '<aside class="mm-spon">' : '<aside class="mm-spon" data-mm-spon="' . $id . '" data-v="' . esc_attr( $ad['v'] ) . '" data-s="' . esc_attr( $placement ) . '">';
        $h .= '<span class="mm-spon__label">Sponsored</span>';
        $h .= '<div class="mm-spon__body">';
        if ( $c['logo'] ) $h .= '<img class="mm-spon__logo" src="' . esc_url( $c['logo'] ) . '" alt="' . esc_attr( $c['brand'] ) . '" width="56" height="56" loading="lazy">';
        $h .= '<div class="mm-spon__text"><span class="mm-spon__brand">' . esc_html( $c['brand'] ) . '</span>';
        $h .= '<strong class="mm-spon__head">' . esc_html( $c['headline'] ) . '</strong>';
        if ( $c['text'] ) $h .= '<span class="mm-spon__copy">' . esc_html( $c['text'] ) . '</span>';
        if ( $c['promo'] ) $h .= '<span class="mm-spon__promo">Use code <b>' . esc_html( $c['promo'] ) . '</b></span>';
        $h .= '</div></div>';
        $h .= '<div class="mm-spon__actions"><a class="mm-spon__btn" href="' . $href( 0 ) . '" target="_blank" rel="sponsored noopener">' . esc_html( $c['button'] ?: 'Learn more' ) . '</a>';
        foreach ( array_slice( (array) $c['links'], 1, 2 ) as $i => $l ) {
            if ( empty( $l[1] ) ) continue;
            $h .= '<a class="mm-spon__link" href="' . $href( $i + 1 ) . '" target="_blank" rel="sponsored noopener">' . esc_html( $l[0] ?: 'More' ) . '</a>';
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
        foreach ( $ended as $id ) {
            update_post_meta( $id, '_mm_ad_status', 'ended' );
            if ( class_exists( 'AdsPortal' ) && (int) self::meta( $id, 'adv', 0 ) ) AdsPortal::mailAdvertiser( $id, 'ended' );
        }
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
        return array( 'checkout' => 'Awaiting payment', 'review' => 'Needs review', 'live' => 'Live', 'paused' => 'Paused', 'ended' => 'Ended', 'removed' => 'Removed', 'rejected' => 'Rejected' );
    }

    public static function metaBoxes() {
        add_meta_box( 'mm_ad_manage', 'Status & actions', array( __CLASS__, 'manageBox' ), self::CPT, 'normal', 'high' );
        add_meta_box( 'mm_ad_box', 'Ad details', array( __CLASS__, 'adBox' ), self::CPT, 'normal', 'default' );
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
            echo '<h4>Preview</h4><div style="max-width:560px">' . self::cardHtml( array( 'id' => $post->ID, 'v' => 'a', 'c' => $c ), $f( 'slot' ), 0, true ) . '</div>';
        }
    }

    /** The sponsor card's rules from mm-dashboard.css, so previews in WP Admin look like the site. */
    protected static function cardCss() {
        $css = (string) @file_get_contents( get_template_directory() . '/assets/csslib/mm-dashboard.css' );
        preg_match_all( '/[^{}]*\.mm-spon[^{}]*\{[^{}]*\}/', $css, $m );

        return wp_strip_all_tags( implode( "\n", $m[0] ) );
    }

    /** The control panel: who, what, money, results, and the actions that fit the status. */
    public static function manageBox( $post ) {
        $id = (int) $post->ID;
        if ( get_post_status( $id ) === 'auto-draft' ) { echo '<p>Save the ad first.</p>'; return; }
        $st = self::meta( $id, 'status', 'review' );
        $adv = (int) self::meta( $id, 'adv', 0 );
        $pl = self::placements()[ self::meta( $id, 'slot' ) ] ?? array( 'label' => '' );
        $t = self::totals( $id );
        $imp = $t['view'] + $t['open'];
        $end = (int) self::meta( $id, 'end', 0 );
        $start = (int) self::meta( $id, 'start', 0 );
        if ( class_exists( 'AdsPortal' ) ) AdsPortal::syncPayments( $id );
        $pays = self::payments( $id );
        $chip = array( 'live' => array( '#1f8a5b', '#e6f4ed' ), 'review' => array( '#8a6a10', '#fbf3dc' ), 'rejected' => array( '#b32d2e', '#fbeaea' ), 'removed' => array( '#b32d2e', '#fbeaea' ) )[ $st ] ?? array( '#50575e', '#f0f0f1' );
        $reason = (string) get_post_meta( $id, '_mm_ad_reason', true );
        $pending = is_array( get_post_meta( $id, '_mm_ad_pending', true ) );
        $unused = class_exists( 'AdsPortal' ) ? AdsPortal::unusedAmount( $id ) : 0;
        $open = class_exists( 'AdsPortal' ) ? AdsPortal::openPayments( $id ) : array();
        $last = $open ? end( $open ) : null;
        $act = admin_url( 'admin-post.php' );
        $nonce = wp_create_nonce( 'mm_ad_manage_' . $id );

        echo '<style>
            .mmam{display:grid;gap:16px}.mmam-top{display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center}
            .mmam-chip{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:700;font-size:13px}
            .mmam-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
            .mmam-cell{padding:12px 14px;border:1px solid #e3e5e8;border-radius:10px;background:#fff}
            .mmam-cell span{display:block;color:#646970;font-size:12px}.mmam-cell b{font-size:15px}
            .mmam-act{display:grid;gap:12px;padding:14px;border-radius:10px;background:#f6f7f7}
            .mmam-act h4{margin:0}.mmam-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
            .mmam-act textarea{width:100%;min-height:60px}.mmam-opts label{display:block;margin:4px 0}
            .mmam .button-danger{background:#b32d2e;border-color:#b32d2e;color:#fff}.mmam .button-danger:hover{background:#8a2424;border-color:#8a2424;color:#fff}
            .mmam table{width:100%}.mmam-note{color:#646970;margin:0}
            .mmam-act > label{display:grid;gap:6px;max-width:640px}
            .mmam-ad{display:grid;grid-template-columns:minmax(280px,420px) 1fr;gap:16px;align-items:start}
            .mmam-ad h4{margin:0 0 8px}.mmam-prev{display:grid;gap:10px}.mmam-prev small{color:#646970}
            .mmam-kv{width:100%;border-collapse:collapse}.mmam-kv td{padding:6px 8px;border-top:1px solid #eef0f2;vertical-align:top}
            .mmam-kv td:first-child{width:150px;color:#646970}.mmam-kv a{word-break:break-all}
            @media (max-width:900px){.mmam-ad{grid-template-columns:1fr}}
        </style><div class="mmam">';

        echo '<div class="mmam-top"><span class="mmam-chip" style="color:' . $chip[0] . ';background:' . $chip[1] . '">' . esc_html( self::statuses()[ $st ] ?? $st ) . '</span>';
        if ( $pending ) echo '<span class="mmam-chip" style="color:#8a6a10;background:#fbf3dc">Edit waiting for review</span>';
        if ( self::meta( $id, 'mode' ) === 'sandbox' ) echo '<span class="mmam-chip" style="color:#8a6a10;background:#fbf3dc">Sandbox test</span>';
        if ( get_post_meta( $id, '_mm_ad_refund_failed', true ) ) echo '<span class="mmam-chip" style="color:#b32d2e;background:#fbeaea">Refund failed: refund in PayPal</span>';
        if ( class_exists( 'AdsReport' ) && $st !== 'checkout' ) echo '<a class="button" href="' . esc_url( AdsReport::url( $id ) ) . '" target="_blank" rel="noopener">Open full results &#8599;</a>';
        echo '</div>';
        if ( $reason !== '' && in_array( $st, array( 'rejected', 'removed' ), true ) ) echo '<p class="mmam-note"><b>Reason given:</b> ' . esc_html( $reason ) . '</p>';

        echo '<div class="mmam-grid">';
        echo '<div class="mmam-cell"><span>Advertiser</span><b>' . esc_html( $adv ? get_the_title( $adv ) : '&ndash;' ) . '</b><br>' . esc_html( (string) get_post_meta( $adv, '_mm_adv_email', true ) ) . ( get_post_meta( $adv, '_mm_adv_contact', true ) ? '<br>' . esc_html( get_post_meta( $adv, '_mm_adv_contact', true ) ) : '' ) . '</div>';
        echo '<div class="mmam-cell"><span>Placement</span><b>' . esc_html( $pl['label'] ) . '</b>' . ( self::meta( $id, 'target' ) ? '<br>' . esc_html( self::meta( $id, 'target' ) ) : '' ) . '</div>';
        echo '<div class="mmam-cell"><span>Billing</span><b>' . esc_html( ucfirst( self::meta( $id, 'cadence', 'weekly' ) ) ) . ' &middot; &#8369;' . number_format( (float) self::meta( $id, 'price', 0 ) ) . '</b><br>' . ( self::meta( $id, 'sub' ) ? ( self::meta( $id, 'cancelled' ) ? 'Subscription cancelled' : 'Renews automatically' ) : 'No subscription' ) . '</div>';
        echo '<div class="mmam-cell"><span>Period</span><b>' . ( $start ? esc_html( wp_date( 'M j', $start ) ) : '&ndash;' ) . ' &rarr; ' . ( $end ? esc_html( wp_date( 'M j, g:i A', $end ) ) : '&ndash;' ) . '</b></div>';
        echo '<div class="mmam-cell"><span>Results so far</span><b>' . number_format( $t['view'] ) . ' views &middot; ' . number_format( $t['sent'] ) . ' emails &middot; ' . number_format( $t['click'] ) . ' clicks</b><br>CTR ' . ( $imp ? round( $t['click'] / $imp * 100, 2 ) . '%' : '&ndash;' ) . '</div>';
        echo '<div class="mmam-cell"><span>Paid in total</span><b>&#8369;' . number_format( (float) self::meta( $id, 'paid', 0 ), 2 ) . '</b></div>';
        echo '</div>';

        // The ad itself: preview(s) and every detail the reviewer needs, plus the PayPal side.
        $c = self::creative( $id );
        $slot = self::meta( $id, 'slot' );
        $pend = get_post_meta( $id, '_mm_ad_pending', true );
        echo '<style>' . self::cardCss() . '</style>';
        echo '<div class="mmam-ad"><div class="mmam-prev"><h4>' . ( is_array( $pend ) ? 'Live version' : 'Preview' ) . '</h4>';
        echo $c['headline'] !== '' ? self::cardHtml( array( 'id' => $id, 'v' => 'a', 'c' => $c ), $slot, 0, true ) : '<p class="mmam-note">No card yet.</p>';
        if ( ! empty( $c['b']['headline'] ) ) echo '<small>Version B</small>' . self::cardHtml( array( 'id' => $id, 'v' => 'b', 'c' => $c ), $slot, 0, true );
        if ( is_array( $pend ) && ! empty( $pend['headline'] ) ) echo '<h4 style="margin-top:8px;color:#8a6a10">Edit waiting for review</h4>' . self::cardHtml( array( 'id' => $id, 'v' => 'a', 'c' => $pend ), $slot, 0, true );
        echo '</div><div>';

        $kv = function ( $rows ) {
            echo '<table class="mmam-kv"><tbody>';
            foreach ( $rows as $k => $v ) if ( $v !== '' && $v !== null ) echo '<tr><td>' . $k . '</td><td>' . $v . '</td></tr>';
            echo '</tbody></table>';
        };
        $show = is_array( $pend ) ? $pend : $c;
        $links = '';
        foreach ( (array) ( $show['links'] ?? array() ) as $i => $l ) {
            $links .= '<div><b>' . esc_html( $l[0] !== '' ? $l[0] : ( $i ? 'Link ' . ( $i + 1 ) : 'Main button' ) ) . ':</b> <a href="' . esc_url( $l[1] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $l[1] ) . '</a></div>';
        }
        echo '<h4>Ad details' . ( is_array( $pend ) ? ' (the edit)' : '' ) . '</h4>';
        $kv( array(
            'Brand'      => esc_html( $show['brand'] ?? '' ),
            'Headline'   => esc_html( $show['headline'] ?? '' ),
            'Text'       => esc_html( $show['text'] ?? '' ),
            'Button'     => esc_html( $show['button'] ?? '' ),
            'Links'      => $links !== '' ? $links . '<small class="mmam-note">Clicks add UTM tags (utm_source=manitomanita).</small>' : '<span style="color:#b32d2e">No link</span>',
            'Promo code' => esc_html( $show['promo'] ?? '' ),
            'Logo'       => ! empty( $show['logo'] ) ? '<a href="' . esc_url( $show['logo'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_basename( $show['logo'] ) ) . '</a>' : '',
            'Target'     => esc_html( (string) self::meta( $id, 'target' ) ),
            'Booked'     => esc_html( get_the_date( 'M j, Y g:i A', $id ) ),
        ) );

        $sub = (string) self::meta( $id, 'sub' );
        if ( $sub !== '' && class_exists( 'AdsPayPal' ) ) {
            $mode = self::meta( $id, 'mode', 'live' );
            $ps = AdsPayPal::subscriptionCached( $mode, $sub );
            $host = $mode === 'sandbox' ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
            $who = trim( ( $ps['subscriber']['name']['given_name'] ?? '' ) . ' ' . ( $ps['subscriber']['name']['surname'] ?? '' ) );
            $next = strtotime( (string) ( $ps['billing_info']['next_billing_time'] ?? '' ) );
            $lastPay = $ps['billing_info']['last_payment'] ?? array();
            echo '<h4 style="margin-top:14px">PayPal</h4>';
            $kv( array(
                'Subscription' => '<a href="' . esc_url( $host . '/billing/subscriptions/' . rawurlencode( $sub ) ) . '" target="_blank" rel="noopener">' . esc_html( $sub ) . '</a>' . ( $mode === 'sandbox' ? ' <small>(sandbox)</small>' : '' ),
                'Status'       => $ps ? esc_html( ucfirst( strtolower( (string) ( $ps['status'] ?? '' ) ) ) ) : '<span class="mmam-note">Couldn&rsquo;t reach PayPal just now</span>',
                'Payer'        => esc_html( implode( ' / ', array_filter( array( $who, (string) ( $ps['subscriber']['email_address'] ?? '' ) ) ) ) ),
                'Payer ID'     => esc_html( (string) ( $ps['subscriber']['payer_id'] ?? '' ) ),
                'Plan'         => esc_html( (string) ( $ps['plan_id'] ?? self::meta( $id, 'plan' ) ) ),
                'Last payment' => ! empty( $lastPay['amount']['value'] ) ? '&#8369;' . number_format( (float) $lastPay['amount']['value'], 2 ) . ' &middot; ' . esc_html( wp_date( 'M j, Y g:i A', strtotime( (string) $lastPay['time'] ) ) ) : '',
                'Next charge'  => $next ? esc_html( wp_date( 'M j, Y g:i A', $next ) ) : '',
                'Failed'       => ! empty( $ps['billing_info']['failed_payments_count'] ) ? (int) $ps['billing_info']['failed_payments_count'] . ' payment(s)' : '',
            ) );
        }
        echo '</div></div>';

        $form = function ( $title, $inner, $note = '' ) use ( $act, $nonce, $id ) {
            echo '<form class="mmam-act" method="post" action="' . esc_url( $act ) . '"><input type="hidden" name="action" value="mm_ad_manage"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '"><h4>' . $title . '</h4>' . ( $note ? '<p class="mmam-note">' . $note . '</p>' : '' ) . $inner . '</form>';
        };
        $reasonField = '<label>Reason for the advertiser (included in their email)<textarea name="reason" placeholder="e.g. The link goes to a different product than the ad describes."></textarea></label>';

        if ( $st === 'review' || $pending ) {
            $form( $pending ? 'Review the edit' : 'Review this ad', $reasonField . '<div class="mmam-row"><button class="button button-primary" name="do" value="approve">' . ( $pending ? 'Approve the edit' : 'Approve and go live' ) . '</button><button class="button button-danger" name="do" value="reject">' . ( $pending ? 'Reject the edit' : 'Reject and refund' . ( $open ? ' &#8369;' . number_format( array_sum( array_column( $open, 'amount' ) ), 2 ) : '' ) ) . '</button></div>', $pending ? 'The current card keeps running until you approve the edit.' : 'Check the card, link and promo code against the ad guidelines. Rejecting cancels the subscription and refunds every payment in full.' );
        }
        if ( in_array( $st, array( 'live', 'paused' ), true ) ) {
            $btn = $st === 'live' ? '<button class="button" name="do" value="pause">Pause (stop showing, keep the subscription)</button>' : '<button class="button button-primary" name="do" value="approve">Resume</button>';
            $form( $st === 'live' ? 'Pause' : 'Resume', '<div class="mmam-row">' . $btn . '</div>', $st === 'live' ? 'Pausing hides the ad but doesn&rsquo;t refund or cancel. The advertiser is emailed.' : 'Resuming shows the ad again until its paid-through date.' );
            $opts = '<div class="mmam-opts">'
                . '<label><input type="radio" name="refund" value="unused" checked> Refund the unused days' . ( $last ? ': <b>&#8369;' . number_format( $unused, 2 ) . '</b> of the last &#8369;' . number_format( (float) $last['amount'], 2 ) . ' payment' : '' ) . '</label>'
                . '<label><input type="radio" name="refund" value="full"> Refund the last payment in full' . ( $last ? ': <b>&#8369;' . number_format( (float) $last['amount'], 2 ) . '</b>' : '' ) . '</label>'
                . '<label><input type="radio" name="refund" value="none"> No refund</label></div>';
            $form( 'Remove this ad', $opts . $reasonField . '<div class="mmam-row"><button class="button button-danger" name="do" value="remove" onclick="return this.form.querySelector(\'[name=sure]\').checked || (alert(\'Tick the confirmation box first.\'), false);">Remove ad now</button><label><input type="checkbox" name="sure" value="1"> I understand the ad stops now and the subscription is cancelled</label></div>', 'Use this when an approved ad breaks the guidelines or the advertiser asks to stop. The advertiser is emailed the reason and any refund.' );
        }

        echo '<div><h4 style="margin:4px 0 8px">Payments</h4>';
        if ( ! $pays ) {
            echo '<p class="mmam-note">No payments recorded yet. PayPal reports each payment a minute or two after it&rsquo;s made.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Amount</th><th>PayPal transaction</th><th>Status</th><th></th></tr></thead><tbody>';
            $pp = self::meta( $id, 'mode' ) === 'sandbox' ? 'https://www.sandbox.paypal.com/activity/payment/' : 'https://www.paypal.com/activity/payment/';
            foreach ( array_reverse( $pays ) as $p ) {
                $done = ! empty( $p['refunded'] );
                echo '<tr><td>' . esc_html( wp_date( 'M j, Y g:i A', (int) $p['t'] ) ) . '</td><td>&#8369;' . number_format( (float) $p['amount'], 2 ) . '</td><td><a href="' . esc_url( $pp . rawurlencode( (string) $p['sale'] ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) $p['sale'] ) . '</a></td>'
                    . '<td>' . ( $done ? 'Refunded' . ( isset( $p['refund_amount'] ) && (float) $p['refund_amount'] < (float) $p['amount'] ? ' &#8369;' . number_format( (float) $p['refund_amount'], 2 ) : '' ) : 'Paid' ) . '</td><td>';
                if ( ! $done && ! empty( $p['sale'] ) ) echo '<form method="post" action="' . esc_url( $act ) . '" style="margin:0"><input type="hidden" name="action" value="mm_ad_manage"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '"><input type="hidden" name="sale" value="' . esc_attr( $p['sale'] ) . '"><button class="button button-small" name="do" value="refund" onclick="return confirm(\'Refund this payment in full?\')">Refund</button></form>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
    }

    /** Actions from the Status & actions panel. */
    public static function manage() {
        $id = absint( $_POST['id'] ?? 0 );
        $do = sanitize_key( (string) ( $_POST['do'] ?? '' ) );
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'mm_ad_manage_' . $id ) || get_post_type( $id ) !== self::CPT || ! class_exists( 'AdsPortal' ) ) wp_die( 'Not allowed.' );
        $reason = wp_unslash( (string) ( $_POST['reason'] ?? '' ) );
        $msg = '';
        switch ( $do ) {
            case 'approve':
                AdsPortal::approve( $id );
                $msg = 'approved';
                break;
            case 'reject':
                AdsPortal::reject( $id, $reason );
                $msg = 'rejected';
                break;
            case 'pause':
                update_post_meta( $id, '_mm_ad_status', 'paused' );
                self::flush();
                if ( (int) self::meta( $id, 'adv', 0 ) ) AdsPortal::mailAdvertiser( $id, 'paused' );
                $msg = 'paused';
                break;
            case 'remove':
                if ( empty( $_POST['sure'] ) ) { $msg = 'confirm'; break; }
                $amt = AdsPortal::remove( $id, sanitize_key( (string) ( $_POST['refund'] ?? 'none' ) ), $reason );
                $msg = $amt > 0 ? 'removed-refund' : 'removed';
                break;
            case 'refund':
                $msg = AdsPortal::refundOne( $id, sanitize_text_field( (string) ( $_POST['sale'] ?? '' ) ) ) ? 'refunded' : 'refund-failed';
                break;
        }
        wp_safe_redirect( add_query_arg( 'mm_ad_done', $msg, get_edit_post_link( $id, 'url' ) ) );
        exit;
    }

    /** "Needs review (2) | Live (3) | ..." above the list. */
    public static function statusViews( $views ) {
        global $wpdb;
        $counts = array();
        foreach ( $wpdb->get_results( "SELECT meta_value s, COUNT(*) n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = '" . self::CPT . "' AND p.post_status <> 'trash' WHERE m.meta_key = '_mm_ad_status' GROUP BY meta_value" ) as $r ) $counts[ $r->s ] = (int) $r->n;
        $cur = sanitize_key( (string) ( $_GET['mm_status'] ?? '' ) );
        $out = array( 'all' => $views['all'] ?? '' );
        foreach ( array( 'review', 'live', 'paused', 'checkout', 'ended', 'removed', 'rejected' ) as $s ) {
            if ( empty( $counts[ $s ] ) ) continue;
            $url = add_query_arg( array( 'post_type' => self::CPT, 'mm_status' => $s ), admin_url( 'edit.php' ) );
            $label = self::statuses()[ $s ];
            $out[ 'mm_' . $s ] = '<a href="' . esc_url( $url ) . '"' . ( $cur === $s ? ' class="current"' : '' ) . ( $s === 'review' ? ' style="font-weight:700;color:#8a6a10"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . $counts[ $s ] . ')</span></a>';
        }
        if ( isset( $views['trash'] ) ) $out['trash'] = $views['trash'];

        return $out;
    }

    public static function filterList( $q ) {
        if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== self::CPT || empty( $_GET['mm_status'] ) ) return;
        $q->set( 'meta_query', array( array( 'key' => '_mm_ad_status', 'value' => sanitize_key( (string) $_GET['mm_status'] ) ) ) );
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
        return array( 'cb' => $cols['cb'], 'title' => 'Ad', 'mm_adv' => 'Advertiser', 'mm_slot' => 'Placement', 'mm_status' => 'Status', 'mm_end' => 'Paid through', 'mm_views' => 'Views / emails', 'mm_clicks' => 'Clicks', 'mm_ctr' => 'CTR', 'mm_paid' => 'Paid' );
    }

    public static function column( $col, $id ) {
        static $cache = array();
        if ( ! isset( $cache[ $id ] ) ) $cache[ $id ] = self::totals( $id );
        $t = $cache[ $id ];
        $base = $t['view'] + $t['open'];
        switch ( $col ) {
            case 'mm_adv':
                $a = (int) self::meta( $id, 'adv', 0 );
                echo $a ? esc_html( get_the_title( $a ) ) . '<br><small>' . esc_html( (string) get_post_meta( $a, '_mm_adv_email', true ) ) . '</small>' : '&ndash;';
                break;
            case 'mm_paid':
                echo '&#8369;' . number_format( (float) self::meta( $id, 'paid', 0 ), 2 );
                break;
            case 'mm_slot':
                $p = self::placements()[ self::meta( $id, 'slot' ) ] ?? null;
                echo esc_html( $p ? $p['label'] : '' ) . ( self::meta( $id, 'target' ) ? ' <small>(' . esc_html( self::meta( $id, 'target' ) ) . ')</small>' : '' );
                break;
            case 'mm_status':
                $s = self::meta( $id, 'status', 'review' );
                $color = array( 'live' => '#1f8a5b', 'review' => '#b8901f', 'rejected' => '#b32d2e' )[ $s ] ?? '#646970';
                echo '<b style="color:' . $color . '">' . esc_html( self::statuses()[ $s ] ?? $s ) . '</b>';
                if ( is_array( get_post_meta( $id, '_mm_ad_pending', true ) ) ) echo '<br><small style="color:#b8901f">Edit to review</small>';
                if ( self::meta( $id, 'cancelled' ) ) echo '<br><small>Cancelled, runs to end</small>';
                if ( get_post_meta( $id, '_mm_ad_refund_failed', true ) ) echo '<br><small style="color:#b32d2e;font-weight:700">Refund failed: refund in PayPal</small>';
                if ( self::meta( $id, 'mode' ) === 'sandbox' ) echo '<br><small>Sandbox test</small>';
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
        $pending = is_array( get_post_meta( $post->ID, '_mm_ad_pending', true ) );
        if ( in_array( $s, array( 'review', 'paused' ), true ) || ( $s === 'rejected' && self::meta( $post->ID, 'sub' ) === '' ) ) $actions['mm_approve'] = '<a href="' . esc_url( $url( 'approve' ) ) . '" style="color:#1f8a5b;font-weight:600">Approve</a>';
        if ( $pending ) $actions['mm_approve'] = '<a href="' . esc_url( $url( 'approve' ) ) . '" style="color:#1f8a5b;font-weight:600">Approve edit</a>';
        if ( $s === 'review' || $pending ) $actions['mm_reject'] = '<a href="' . esc_url( $url( 'reject' ) ) . '" style="color:#b32d2e">' . ( $pending ? 'Reject edit' : 'Reject' . ( self::meta( $post->ID, 'sub' ) ? ' &amp; refund' : '' ) ) . '</a>';
        if ( $s === 'live' ) $actions['mm_pause'] = '<a href="' . esc_url( $url( 'pause' ) ) . '">Pause</a>';

        return $actions;
    }

    /** Approve (goes live now; a period is set from the cadence when none is), reject or pause. */
    public static function review() {
        $id = absint( $_GET['id'] ?? 0 );
        $do = sanitize_key( (string) ( $_GET['do'] ?? '' ) );
        $signed = class_exists( 'AdsPortal' ) && AdsPortal::validReviewKey( $id, $do, $_GET['k'] ?? '' ); // link from the review email
        if ( ! current_user_can( 'manage_options' ) || ( ! $signed && ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'mm_ad_review_' . $id ) ) || get_post_type( $id ) !== self::CPT ) wp_die( 'Not allowed.' );

        if ( class_exists( 'AdsPortal' ) && in_array( $do, array( 'approve', 'reject' ), true ) ) {
            if ( $do === 'approve' ) AdsPortal::approve( $id ); else AdsPortal::reject( $id );
        } elseif ( $do === 'approve' ) {
            if ( ! (int) self::meta( $id, 'start', 0 ) ) update_post_meta( $id, '_mm_ad_start', time() );
            if ( (int) self::meta( $id, 'end', 0 ) <= time() ) update_post_meta( $id, '_mm_ad_end', time() + ( self::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? 30 : 7 ) * DAY_IN_SECONDS );
            update_post_meta( $id, '_mm_ad_status', 'live' );
            if ( get_post_status( $id ) !== 'publish' ) wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        } elseif ( $do === 'reject' ) {
            update_post_meta( $id, '_mm_ad_status', 'rejected' );
        } elseif ( $do === 'pause' ) {
            update_post_meta( $id, '_mm_ad_status', 'paused' );
            if ( class_exists( 'AdsPortal' ) && (int) self::meta( $id, 'adv', 0 ) ) AdsPortal::mailAdvertiser( $id, 'paused' );
        }
        self::flush();

        wp_safe_redirect( add_query_arg( 'mm_ad_done', $do, admin_url( 'edit.php?post_type=' . self::CPT ) ) );
        exit;
    }

    /** Review link opened while signed out: sign in to WordPress, then come back to it. */
    public static function reviewLogin() {
        wp_safe_redirect( wp_login_url( add_query_arg( array_map( 'rawurlencode', wp_unslash( $_GET ) ), admin_url( 'admin-post.php' ) ) ) );
        exit;
    }

    public static function notices() {
        $do = sanitize_key( (string) ( $_GET['mm_ad_done'] ?? '' ) );
        $msg = array( 'approve' => 'Ad approved and live.', 'reject' => 'Ad rejected.', 'pause' => 'Ad paused.', 'prices' => 'Prices saved.',
            'approved' => 'Approved. The advertiser has been emailed.', 'rejected' => 'Rejected: the subscription is cancelled, the payment is being refunded and the advertiser has been emailed.', 'paused' => 'Paused. The advertiser has been emailed.',
            'removed' => 'Ad removed and subscription cancelled (no refund). The advertiser has been emailed.', 'removed-refund' => 'Ad removed, subscription cancelled and refund sent. The advertiser has been emailed.',
            'refunded' => 'Refund sent to PayPal.', 'refund-failed' => 'PayPal didn&rsquo;t accept the refund. Check your email for the details and refund it in PayPal.', 'confirm' => 'Nothing changed: tick the confirmation box to remove the ad.' )[ $do ] ?? '';
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
