<?php
/**
 * AdsPortal Class. Self-serve advertising: /advertise/ (public), /advertiser/ (account), /ad-guidelines/.
 *
 * Flow: sign in with an email code -> build the card (live preview) -> pick placement + weekly/monthly ->
 * PayPal subscription -> "Needs review" (admin gets Approve / Reject links) -> live, renews by itself.
 * Seats: Ads::SEATS per placement (live + in review + 30-minute checkout holds + 48-hour waitlist holds).
 * Christmas placements sell Sep 1 - Dec 20; their subscriptions are cancelled before they would bill past Dec 20.
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
 * Class AdsPortal
 */
class AdsPortal {
    const COOKIE     = 'mm_adv';
    const REMEMBER   = 30;               // days signed in on a device
    const HOLD       = 30 * MINUTE_IN_SECONDS;
    const WAIT_HOLD  = 2 * DAY_IN_SECONDS;
    const WAIT_OPT   = 'mm_ads_waitlist';
    const PAGES_OPT  = 'mm_ads_pages';
    const MAX_LOGO   = 1048576;          // 1 MB

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'wp_loaded', array( __CLASS__, 'pages' ) );
        add_action( Ads::CRON, array( __CLASS__, 'hourly' ) );
        add_filter( 'wpseo_robots', array( __CLASS__, 'robots' ), 30 );
        add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'sitemapExclude' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );

        foreach ( array( 'login', 'logout', 'save', 'checkout', 'activate', 'cancel', 'switch', 'wait', 'profile' ) as $a ) {
            add_action( 'wp_ajax_mm_ads_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
            add_action( 'wp_ajax_nopriv_mm_ads_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
        }
    }

    /* ------------------------------------------------------------------ Pages */

    public static function pageUrl( $which ) {
        return home_url( '/' . ( $which === 'account' ? 'advertiser' : ( $which === 'rules' ? 'ad-guidelines' : 'advertise' ) ) . '/' );
    }

    /** Creates /advertise/, /advertiser/ and /ad-guidelines/ once (edits in WP Admin are kept). */
    public static function pages() {
        if ( get_option( self::PAGES_OPT ) ) {
            self::rulesLayout();
            self::seoMeta();
            return;
        }
        if ( get_transient( 'mm_ads_pages_lock' ) ) return;
        set_transient( 'mm_ads_pages_lock', 1, 120 );

        $defs = array(
            'advertise'     => array( 'Advertise on Manito Manita', 'template-advertise.php', '', 'Advertise to Gift Buyers in the Philippines | Manito Manita', 'Reach office teams, barkadas and families the moment they shop for their Secret Santa gift. Book a sponsored spot in minutes, weekly or monthly.' ),
            'advertiser'    => array( 'Advertiser account', 'template-advertiser.php', '', 'Advertiser account | Manito Manita', '' ),
            'ad-guidelines' => array( 'Ad guidelines', '', self::rulesHtml(), 'Ad Guidelines | Manito Manita', 'What sponsors can and can&rsquo;t advertise on Manito Manita, and how reviews and refunds work.' ),
        );
        $ids = array();
        foreach ( $defs as $slug => $d ) {
            $existing = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
            if ( $existing ) {
                $ids[ $slug ] = (int) $existing[0];
                continue;
            }
            $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $d[0], 'post_content' => $d[2] ), true );
            if ( is_wp_error( $id ) || ! $id ) continue;
            if ( $d[1] ) update_post_meta( $id, '_wp_page_template', $d[1] );
            update_post_meta( $id, '_yoast_wpseo_title', html_entity_decode( $d[3], ENT_QUOTES, 'UTF-8' ) );
            if ( $d[4] ) update_post_meta( $id, '_yoast_wpseo_metadesc', html_entity_decode( $d[4], ENT_QUOTES, 'UTF-8' ) );
            $ids[ $slug ] = (int) $id;
        }
        if ( count( $ids ) === count( $defs ) ) update_option( self::PAGES_OPT, $ids, true );
        delete_transient( 'mm_ads_pages_lock' );
    }

    /** Search titles, descriptions and focus keyphrases for the ad pages (applied once per version). */
    protected static function seoMeta() {
        if ( (int) get_option( 'mm_ads_seo' ) >= 1 ) return;
        $ids = (array) get_option( self::PAGES_OPT, array() );
        $meta = array(
            'advertise'     => array( 'Advertise in the Philippines: Reach Christmas Gift Buyers | Manito Manita', 'Advertise your shop to thousands of Filipino Secret Santa and Monito Monita gift buyers. Sponsored spots from PHP 300 a week, weekly or monthly, with live stats.', 'advertise in the Philippines' ),
            'ad-guidelines' => array( 'Advertising Guidelines | Manito Manita', 'What shops and brands can advertise on Manito Manita, how ads are reviewed, and how payments and refunds work.', 'advertising guidelines' ),
        );
        foreach ( $meta as $slug => $m ) {
            if ( empty( $ids[ $slug ] ) ) continue;
            update_post_meta( $ids[ $slug ], '_yoast_wpseo_title', $m[0] );
            update_post_meta( $ids[ $slug ], '_yoast_wpseo_metadesc', $m[1] );
            update_post_meta( $ids[ $slug ], '_yoast_wpseo_focuskw', $m[2] );
        }
        if ( ! empty( $ids['advertise'] ) ) wp_update_post( array( 'ID' => $ids['advertise'], 'post_title' => 'Advertise with us' ) );
        update_option( 'mm_ads_seo', 1, true );
    }

    /** /ad-guidelines/ uses the Landing "legal" layout (same as the Privacy Policy). Applied once. */
    protected static function rulesLayout() {
        if ( get_option( 'mm_ads_rules_layout' ) || ! function_exists( 'update_field' ) ) return;
        $ids = (array) get_option( self::PAGES_OPT, array() );
        $id = (int) ( $ids['ad-guidelines'] ?? 0 );
        if ( ! $id || get_post_type( $id ) !== 'page' ) return;
        update_option( 'mm_ads_rules_layout', 1, true );
        wp_update_post( array( 'ID' => $id, 'post_content' => self::rulesHtml() ) );
        update_post_meta( $id, '_wp_page_template', 'template-landing.php' );
        update_field( 'field_mm_land_type', 'legal', $id );
        update_field( 'field_mm_land_h1', 'Ad guidelines', $id );
        update_field( 'field_mm_land_lead', 'Ads appear next to Secret Santa groups, gift lists and gift guides used by families, schools and offices. Every ad is reviewed before it goes live.', $id );
        update_field( 'field_mm_land_updated', wp_date( 'Ymd' ), $id );
    }

    public static function rulesHtml() {
        return '<h2>What you can advertise</h2><ul><li>Products and services people buy as gifts, or use for parties and celebrations: shops, gift boxes, food, flowers, venues, events, personalised items and the like.</li><li>Your own Shopee, Lazada, website or social media pages.</li></ul>'
            . '<h2>What we don&rsquo;t accept</h2><ul><li>Gambling, betting, raffles, lotteries and lucky draws that people pay to join.</li><li>Adult content, dating, alcohol aimed at minors, tobacco and vapes.</li><li>Investment schemes, crypto, loans, MLM and &ldquo;get rich&rdquo; offers.</li><li>Political or religious campaigning, weapons, and anything illegal in the Philippines.</li><li>Misleading claims, fake discounts, or links that don&rsquo;t match the ad.</li></ul>'
            . '<h2>Your card</h2><ul><li>Use your real brand name and a logo you own.</li><li>Links must use https and lead to the page the ad describes.</li><li>Promo codes must work for everyone who sees the ad.</li></ul>'
            . '<h2>Reviews, payments and refunds</h2><ul><li>You pay when you book. If we reject the ad, the first payment is refunded in full and the subscription is cancelled.</li><li>Subscriptions renew every week or month until you cancel. When you cancel, your ad keeps running until the end of the period you paid for; that period isn&rsquo;t refunded.</li><li>Christmas placements run Sep 1 to Dec 20. Subscriptions for them stop before billing a period after Dec 20.</li><li>We may pause or remove an ad that breaks these rules. Edits to a live ad are reviewed before they replace the current version.</li></ul>'
            . '<h2>Privacy</h2><p>Advertisers get totals only (views, clicks, emails and group budget or size ranges). We never share names, emails or group details.</p>';
    }

    public static function isPage( $slug ) {
        $ids = (array) get_option( self::PAGES_OPT, array() );
        return ! empty( $ids[ $slug ] ) && is_page( $ids[ $slug ] );
    }

    public static function robots( $robots ) {
        return self::isPage( 'advertiser' ) ? 'noindex, nofollow' : $robots;
    }

    public static function sitemapExclude( $ids ) {
        $p = (array) get_option( self::PAGES_OPT, array() );
        return ! empty( $p['advertiser'] ) ? array_merge( (array) $ids, array( (int) $p['advertiser'] ) ) : $ids;
    }

    public static function assets() {
        if ( ! self::isPage( 'advertise' ) && ! self::isPage( 'advertiser' ) ) return;
        foreach ( array( 'mm-home', 'mm-ads' ) as $n ) {
            $file = '/assets/csslib/' . $n . '.css';
            wp_enqueue_style( $n, PARENT_URL . $file, array(), (string) @filemtime( PARENT_DIR . $file ) );
        }
        if ( self::isPage( 'advertiser' ) ) {
            if ( ! empty( $_GET['report'] ) ) wp_enqueue_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', array(), null, true );
            $js = '/assets/js/mm-ads.js';
            wp_enqueue_script( 'mm-ads', PARENT_URL . $js, array( 'jquery' ), (string) @filemtime( PARENT_DIR . $js ), true );
            wp_localize_script( 'mm-ads', 'mmAds', array(
                'ajax'     => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'mm_ads' ),
                'otpNonce' => wp_create_nonce( 'mm_otp' ),
                'test'     => self::testMode() ? 1 : 0,
                'csv'      => admin_url( 'admin-ajax.php?action=mm_ads_csv' ),
            ) );
        }
    }

    /** Admins can book with the PayPal sandbox (?test=1 on /advertiser/) to test end to end. */
    public static function testMode() {
        return current_user_can( 'manage_options' ) && ! empty( $_REQUEST['test'] ) && AdsPayPal::enabled( 'sandbox' );
    }

    /* ------------------------------------------------------------------ Advertiser session */

    protected static function sign( $what ) {
        return substr( hash_hmac( 'sha256', $what, wp_salt( 'auth' ) ), 0, 32 );
    }

    public static function current() {
        static $id = null;
        if ( $id !== null ) return $id;
        $id = 0;
        $raw = (string) ( $_COOKIE[ self::COOKIE ] ?? '' );
        $p = explode( '.', $raw );
        if ( count( $p ) !== 3 ) return $id;
        list( $aid, $exp, $sig ) = $p;
        if ( (int) $exp < time() || get_post_type( (int) $aid ) !== Ads::ADV ) return $id;
        $email = (string) get_post_meta( (int) $aid, '_mm_adv_email', true );
        if ( $email !== '' && hash_equals( self::sign( 'adv|' . (int) $aid . '|' . (int) $exp . '|' . $email ), $sig ) ) $id = (int) $aid;

        return $id;
    }

    protected static function signIn( $aid ) {
        $exp = time() + self::REMEMBER * DAY_IN_SECONDS;
        $v = (int) $aid . '.' . $exp . '.' . self::sign( 'adv|' . (int) $aid . '|' . $exp . '|' . get_post_meta( $aid, '_mm_adv_email', true ) );
        if ( ! headers_sent() ) setcookie( self::COOKIE, $v, array( 'expires' => $exp, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
        $_COOKIE[ self::COOKIE ] = $v;
    }

    public static function byEmail( $email ) {
        $p = get_posts( array( 'post_type' => Ads::ADV, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_adv_email', 'value' => strtolower( $email ) ) ) ) );
        return $p ? (int) $p[0] : 0;
    }

    public static function adsOf( $aid ) {
        return array_map( 'intval', get_posts( array( 'post_type' => Ads::CPT, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 100, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array( array( 'key' => '_mm_ad_adv', 'value' => (int) $aid ) ) ) ) );
    }

    protected static function guard( $needAccount = true ) {
        if ( ! check_ajax_referer( 'mm_ads', 'nonce', false ) ) wp_send_json_error( 'This page has expired. Please refresh and try again.' );
        if ( $needAccount && ! self::current() ) wp_send_json_error( 'Please sign in again.' );
    }

    protected static function ownAd( $id ) {
        $id = (int) $id;
        if ( get_post_type( $id ) !== Ads::CPT || (int) Ads::meta( $id, 'adv', 0 ) !== self::current() ) wp_send_json_error( 'That ad isn&rsquo;t in your account.' );

        return $id;
    }

    public static function ajaxLogin() {
        self::guard( false );
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        if ( ! is_email( $email ) ) wp_send_json_error( 'Please enter a valid email address.' );
        $err = EmailCheck::verifyCode( $email, preg_replace( '/\D/', '', (string) ( $_POST['code'] ?? '' ) ) );
        if ( $err ) wp_send_json_error( $err );

        $aid = self::byEmail( $email );
        if ( ! $aid ) {
            $aid = wp_insert_post( array( 'post_type' => Ads::ADV, 'post_status' => 'publish', 'post_title' => $email ) );
            if ( ! $aid || is_wp_error( $aid ) ) wp_send_json_error( 'Something went wrong. Please try again.' );
            update_post_meta( $aid, '_mm_adv_email', $email );
        }
        self::signIn( $aid );
        wp_send_json_success( array( 'reload' => true ) );
    }

    public static function ajaxLogout() {
        if ( ! headers_sent() ) setcookie( self::COOKIE, '', array( 'expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
        wp_send_json_success( array( 'reload' => true ) );
    }

    public static function ajaxProfile() {
        self::guard();
        $aid = self::current();
        $biz = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['business'] ?? '' ) ) ), 0, 60 );
        if ( $biz === '' ) wp_send_json_error( 'Please enter your business name.' );
        wp_update_post( array( 'ID' => $aid, 'post_title' => $biz ) );
        update_post_meta( $aid, '_mm_adv_name', mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ), 0, 60 ) );
        update_post_meta( $aid, '_mm_adv_contact', mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['contact'] ?? '' ) ) ), 0, 120 ) );
        wp_send_json_success( array( 'message' => 'Saved' ) );
    }

    /* ------------------------------------------------------------------ Seats, holds, waitlist */

    protected static function seatKey( $placement, $target ) {
        return $placement . '|' . $target;
    }

    /** Seats used by other advertisers: live, in review, checkout holds, waitlist holds. */
    public static function seatsTaken( $placement, $target = '', $exceptAdv = 0 ) {
        $mq = array(
            array( 'key' => '_mm_ad_slot', 'value' => $placement ),
            array( 'relation' => 'OR',
                array( 'key' => '_mm_ad_status', 'value' => array( 'live', 'review' ), 'compare' => 'IN' ),
                array( 'relation' => 'AND', array( 'key' => '_mm_ad_status', 'value' => 'checkout' ), array( 'key' => '_mm_ad_hold', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ) ),
            ),
        );
        if ( $target !== '' ) $mq[] = array( 'key' => '_mm_ad_target', 'value' => $target );
        $n = 0;
        foreach ( get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => $mq ) ) as $id ) {
            if ( Ads::meta( $id, 'status' ) === 'live' && (int) Ads::meta( $id, 'end', 0 ) <= time() ) continue;
            if ( $exceptAdv && (int) Ads::meta( $id, 'adv', 0 ) === (int) $exceptAdv ) continue;
            $n++;
        }
        foreach ( self::waitlist( $placement, $target ) as $w ) {
            if ( ! empty( $w['hold'] ) && $w['hold'] > time() && (int) $w['adv'] !== (int) $exceptAdv ) $n++;
        }

        return $n;
    }

    public static function seatsLeft( $placement, $target = '', $forAdv = 0 ) {
        return max( 0, Ads::SEATS - self::seatsTaken( $placement, $target, $forAdv ) );
    }

    public static function waitlist( $placement, $target = '' ) {
        $all = (array) get_option( self::WAIT_OPT, array() );
        return (array) ( $all[ self::seatKey( $placement, $target ) ] ?? array() );
    }

    protected static function saveWaitlist( $placement, $target, array $list ) {
        $all = (array) get_option( self::WAIT_OPT, array() );
        $all[ self::seatKey( $placement, $target ) ] = array_values( $list );
        update_option( self::WAIT_OPT, $all, false );
    }

    public static function ajaxWait() {
        self::guard();
        $placement = sanitize_key( (string) ( $_POST['placement'] ?? '' ) );
        $target = sanitize_title( (string) ( $_POST['target'] ?? '' ) );
        if ( ! isset( Ads::placements()[ $placement ] ) ) wp_send_json_error( 'Pick a placement.' );
        if ( empty( Ads::placements()[ $placement ]['target'] ) ) $target = '';
        elseif ( ! isset( self::targets( $placement )[ $target ] ) ) wp_send_json_error( 'Choose where your ad should appear first.' );
        $list = self::waitlist( $placement, $target );
        foreach ( $list as $w ) if ( (int) $w['adv'] === self::current() ) wp_send_json_success( array( 'message' => 'You&rsquo;re already on the waitlist. We&rsquo;ll email you when a seat opens.' ) );
        $list[] = array( 'adv' => self::current(), 't' => time(), 'hold' => 0 );
        self::saveWaitlist( $placement, $target, $list );
        self::mailWaitlist( self::current(), $placement, 'joined', count( $list ) );
        wp_send_json_success( array( 'message' => 'You&rsquo;re on the waitlist (#' . count( $list ) . '). We&rsquo;ll email you when a seat opens and hold it for you for 48 hours.' ) );
    }

    /* ------------------------------------------------------------------ Build, checkout, activate */

    /** Logo upload: PNG/JPG/WebP up to 1 MB, resized to 160x160. Returns url or error string. */
    protected static function upload( array $file ) {
        if ( (int) ( $file['error'] ?? 1 ) !== 0 ) return new WP_Error( 'upload', 'The logo didn&rsquo;t upload. Please try again.' );
        if ( (int) $file['size'] > self::MAX_LOGO ) return new WP_Error( 'upload', 'Please use a logo under 1 MB.' );
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $mimes = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
        if ( empty( $check['type'] ) ) return new WP_Error( 'upload', 'Please use a PNG, JPG or WebP image.' );
        $up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
        if ( ! empty( $up['error'] ) ) return new WP_Error( 'upload', 'The logo didn&rsquo;t upload. Please try again.' );
        $ed = wp_get_image_editor( $up['file'] );
        if ( ! is_wp_error( $ed ) ) {
            $ed->resize( 160, 160, true );
            $saved = $ed->save( $up['file'] );
            if ( ! is_wp_error( $saved ) && $saved['path'] !== $up['file'] ) {
                @unlink( $up['file'] );
                $up['url'] = str_replace( wp_basename( $up['file'] ), wp_basename( $saved['path'] ), $up['url'] );
            }
        }

        return $up['url'];
    }

    /** Create or edit an ad's card. Edits to a live ad wait for review; the live version keeps running. */
    public static function ajaxSave() {
        self::guard();
        $aid = self::current();
        $id = absint( $_POST['ad'] ?? 0 );
        if ( $id ) self::ownAd( $id );

        $c = Ads::cleanCreative( (array) wp_unslash( $_POST['c'] ?? array() ) );
        if ( ! empty( $_FILES['logo']['name'] ) ) {
            $url = self::upload( $_FILES['logo'] );
            if ( is_wp_error( $url ) ) wp_send_json_error( $url->get_error_message() );
            $c['logo'] = $url;
        } elseif ( $id && empty( $c['logo'] ) ) {
            $c['logo'] = Ads::creative( $id )['logo'];
        }
        if ( $c['brand'] === '' || $c['headline'] === '' ) wp_send_json_error( 'Add your brand name and a headline.' );
        if ( empty( $c['links'] ) ) wp_send_json_error( 'Add the link people should open (it must start with https://).' );

        if ( ! $id ) {
            $placement = sanitize_key( (string) ( $_POST['placement'] ?? '' ) );
            if ( ! isset( Ads::placements()[ $placement ] ) || ! self::sellable( $placement ) ) wp_send_json_error( 'Pick a placement.' );
            $target = sanitize_title( (string) ( $_POST['target'] ?? '' ) );
            if ( empty( Ads::placements()[ $placement ]['target'] ) ) $target = '';
            elseif ( ! isset( self::targets( $placement )[ $target ] ) ) wp_send_json_error( Ads::placements()[ $placement ]['target'] === 'occasion' ? 'Pick which occasion&rsquo;s registries your ad should appear on.' : 'Pick which page your ad should appear on.' );
            $id = wp_insert_post( array( 'post_type' => Ads::CPT, 'post_status' => 'draft', 'post_title' => $c['brand'] . ' - ' . Ads::placements()[ $placement ]['label'] ) );
            if ( ! $id || is_wp_error( $id ) ) wp_send_json_error( 'Something went wrong. Please try again.' );
            update_post_meta( $id, '_mm_ad_adv', $aid );
            update_post_meta( $id, '_mm_ad_slot', $placement );
            update_post_meta( $id, '_mm_ad_target', $target );
            update_post_meta( $id, '_mm_ad_status', 'checkout' );
            update_post_meta( $id, '_mm_ad_creative', $c );
            wp_send_json_success( array( 'ad' => $id, 'message' => 'Saved. Choose weekly or monthly and pay to send it for review.' ) );
        }

        $status = Ads::meta( $id, 'status' );
        if ( $status === 'live' || $status === 'paused' ) {
            update_post_meta( $id, '_mm_ad_pending', $c );
            self::notifyAdmin( $id, 'edit' );
            self::mailAdvertiser( $id, 'edit-received' );
            wp_send_json_success( array( 'ad' => $id, 'message' => 'Saved. Your current card keeps running until we approve the edit (usually within a day).' ) );
        }
        update_post_meta( $id, '_mm_ad_creative', $c );
        if ( $status === 'rejected' || $status === 'removed' ) {
            update_post_meta( $id, '_mm_ad_status', 'checkout' ); // edited: may be booked (and reviewed) again
            Ads::flush();
            wp_send_json_success( array( 'ad' => $id, 'message' => 'Saved. You can book it again now; we&rsquo;ll review the new version.' ) );
        }
        if ( $status === 'review' ) {
            self::notifyAdmin( $id, 'edit' );
            self::mailAdvertiser( $id, 'edit-received' );
        }
        Ads::flush();
        wp_send_json_success( array( 'ad' => $id, 'message' => 'Saved.' ) );
    }

    /**
     * Targets a placement can be booked for, slug => label: gift guide pages (not legal pages or registry
     * guides, which carry their occasion's registry ads) or occasions (wedding/debut for registry-major).
     */
    public static function targets( $placement ) {
        $pl = Ads::placements()[ $placement ] ?? array();
        $out = array();
        if ( ( $pl['target'] ?? '' ) === 'page' ) {
            foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 50, 'meta_key' => '_wp_page_template', 'meta_value' => 'template-landing.php', 'orderby' => 'title', 'order' => 'ASC' ) ) as $gp ) {
                if ( function_exists( 'get_field' ) && in_array( get_field( 'mm_type', $gp->ID ), array( 'legal', 'registry' ), true ) ) continue;
                $out[ $gp->post_name ] = get_the_title( $gp );
            }
        } elseif ( ( $pl['target'] ?? '' ) === 'occasion' && class_exists( 'Registries' ) ) {
            foreach ( Registries::occasions() as $k => $o ) {
                if ( $k === 'any' || Registries::placement( $k ) !== $placement ) continue;
                $out[ $k ] = html_entity_decode( $o['label'], ENT_QUOTES, 'UTF-8' ) . ' registries';
            }
        }

        return $out;
    }

    /** Can this placement be booked now (registries once Registries::adsOpen(); Christmas placements Sep 1 - Dec 20)? */
    public static function sellable( $placement ) {
        if ( strpos( $placement, 'registry' ) === 0 && ! ( class_exists( 'Registries' ) && Registries::adsOpen() ) ) return false;

        return true;
    }

    /** Cadences on offer: Christmas placements only offer monthly while a full month still fits before Dec 21. */
    public static function cadences( $placement ) {
        $out = array( 'weekly' );
        $p = Ads::placements()[ $placement ] ?? array();
        $fits = ( $p['season'] ?? '' ) !== 'xmas' || Ads::season( $placement, time() + 30 * DAY_IN_SECONDS ) !== 'closed';
        if ( $fits ) $out[] = 'monthly';

        return $out;
    }

    public static function ajaxCheckout() {
        self::guard();
        $id = self::ownAd( absint( $_POST['ad'] ?? 0 ) );
        $cadence = ( $_POST['cadence'] ?? '' ) === 'monthly' ? 'monthly' : 'weekly';
        $placement = Ads::meta( $id, 'slot' );
        $target = Ads::meta( $id, 'target' );
        $mode = self::testMode() ? 'sandbox' : 'live';

        $st = Ads::meta( $id, 'status' );
        if ( in_array( $st, array( 'rejected', 'removed' ), true ) ) wp_send_json_error( 'Please edit the card first, then book it again.' );
        if ( ! in_array( $st, array( 'checkout', 'ended' ), true ) ) wp_send_json_error( 'This ad is already booked.' );
        if ( Ads::season( $placement ) === 'closed' ) wp_send_json_error( 'This placement opens again on Sep 1. Join the waitlist and we&rsquo;ll email you.' );
        if ( ! in_array( $cadence, self::cadences( $placement ), true ) ) wp_send_json_error( 'Monthly isn&rsquo;t available this close to Dec 20. Choose weekly.' );
        if ( self::seatsLeft( $placement, $target, self::current() ) < 1 && ! self::holdsWaitSeat( $placement, $target ) ) wp_send_json_error( 'All seats for this placement are taken. Join the waitlist and we&rsquo;ll email you when one opens.' );
        if ( ! AdsPayPal::enabled( $mode ) ) wp_send_json_error( 'Payments aren&rsquo;t available right now. Please try again later.' );

        $price = Ads::price( $placement, $cadence );
        $plan = AdsPayPal::plan( $mode, $placement, $cadence, $price );
        if ( ! $plan ) wp_send_json_error( 'We couldn&rsquo;t start the payment. Please try again in a minute.' );

        update_post_meta( $id, '_mm_ad_status', 'checkout' );
        update_post_meta( $id, '_mm_ad_hold', time() + self::HOLD );
        update_post_meta( $id, '_mm_ad_cadence', $cadence );
        update_post_meta( $id, '_mm_ad_price', $price );
        update_post_meta( $id, '_mm_ad_plan', $plan );
        update_post_meta( $id, '_mm_ad_mode', $mode );

        wp_send_json_success( array( 'plan' => $plan, 'clientId' => AdsPayPal::clientId( $mode ), 'custom' => (string) $id, 'price' => $price, 'mode' => $mode ) );
    }

    protected static function holdsWaitSeat( $placement, $target ) {
        foreach ( self::waitlist( $placement, $target ) as $w ) {
            if ( (int) $w['adv'] === self::current() && ! empty( $w['hold'] ) && $w['hold'] > time() ) return true;
        }

        return false;
    }

    /** After PayPal approval: check the subscription is real and for this ad, then send the ad for review. */
    public static function ajaxActivate() {
        self::guard();
        $id = self::ownAd( absint( $_POST['ad'] ?? 0 ) );
        $sub = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $_POST['sub'] ?? '' ) );
        $err = self::link( $id, $sub, Ads::meta( $id, 'mode', 'live' ) );
        if ( $err ) wp_send_json_error( $err );

        wp_send_json_success( array( 'message' => 'Payment received. We&rsquo;re reviewing your ad now and will email you when it&rsquo;s live (usually within a day).', 'reload' => true ) );
    }

    /** Link a PayPal subscription to an ad after verifying it with PayPal. '' on success, else a message. */
    public static function link( $id, $sub, $mode ) {
        if ( $sub === '' ) return 'The payment didn&rsquo;t go through. Please try again.';
        if ( in_array( Ads::meta( $id, 'status' ), array( 'review', 'live' ), true ) && Ads::meta( $id, 'sub' ) === $sub ) return '';

        $s = AdsPayPal::subscription( $mode, $sub );
        if ( ! $s ) return 'We couldn&rsquo;t confirm the payment with PayPal. If you were charged, email us and we&rsquo;ll sort it out.';
        if ( ( $s['plan_id'] ?? '' ) !== Ads::meta( $id, 'plan' ) || (string) ( $s['custom_id'] ?? '' ) !== (string) $id ) return 'That payment doesn&rsquo;t match this ad.';
        if ( ! in_array( $s['status'] ?? '', array( 'ACTIVE', 'APPROVED' ), true ) ) return 'PayPal hasn&rsquo;t activated the subscription yet. Please refresh in a minute.';
        $other = get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'post__not_in' => array( $id ), 'meta_query' => array( array( 'key' => '_mm_ad_sub', 'value' => $sub ) ) ) );
        if ( $other ) return 'That payment is already linked to another ad.';

        // Re-booking (after it ended, or after a fix): settle the previous subscription first.
        $old = (string) Ads::meta( $id, 'sub' );
        if ( $old !== '' && $old !== $sub ) {
            if ( get_post_meta( $id, '_mm_ad_refund_due', true ) ) self::syncPayments( $id, true ); // refunds a late payment of the rejected booking
            $pays = Ads::payments( $id );
            foreach ( $pays as &$p ) if ( empty( $p['sub'] ) ) $p['sub'] = $old;
            unset( $p );
            update_post_meta( $id, '_mm_ad_payments', $pays );
        }
        delete_post_meta( $id, '_mm_ad_refund_due' );
        update_post_meta( $id, '_mm_ad_payfail', 0 );
        delete_post_meta( $id, '_mm_ad_switch' );

        update_post_meta( $id, '_mm_ad_sub', $sub );
        update_post_meta( $id, '_mm_ad_status', 'review' );
        update_post_meta( $id, '_mm_ad_cancelled', 0 );
        delete_post_meta( $id, '_mm_ad_hold' );
        self::clearWait( Ads::meta( $id, 'slot' ), Ads::meta( $id, 'target' ), (int) Ads::meta( $id, 'adv', 0 ) );
        if ( get_post_status( $id ) !== 'publish' ) wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        Ads::flush();

        self::notifyAdmin( $id, 'new' );
        self::mailAdvertiser( $id, 'received' );

        return '';
    }

    protected static function clearWait( $placement, $target, $aid ) {
        $list = array_filter( self::waitlist( $placement, $target ), function ( $w ) use ( $aid ) { return (int) $w['adv'] !== (int) $aid; } );
        self::saveWaitlist( $placement, $target, $list );
    }

    /* ------------------------------------------------------------------ Cancel / switch */

    public static function ajaxCancel() {
        self::guard();
        $id = self::ownAd( absint( $_POST['ad'] ?? 0 ) );
        $sub = Ads::meta( $id, 'sub' );
        if ( $sub === '' || Ads::meta( $id, 'cancelled' ) ) wp_send_json_error( 'This ad has no active subscription.' );
        if ( ! AdsPayPal::cancel( Ads::meta( $id, 'mode', 'live' ), $sub, 'Cancelled by the advertiser' ) ) wp_send_json_error( 'PayPal didn&rsquo;t confirm the cancellation. Please try again.' );
        update_post_meta( $id, '_mm_ad_cancelled', 1 );
        self::mailAdvertiser( $id, 'cancelled' );
        $end = (int) Ads::meta( $id, 'end', 0 );

        wp_send_json_success( array( 'message' => 'Cancelled. No more charges.' . ( $end > time() ? ' Your ad keeps running until ' . wp_date( 'M j, g:i A', $end ) . '.' : '' ), 'reload' => true ) );
    }

    public static function ajaxSwitch() {
        self::guard();
        $id = self::ownAd( absint( $_POST['ad'] ?? 0 ) );
        $sub = Ads::meta( $id, 'sub' );
        $mode = Ads::meta( $id, 'mode', 'live' );
        $to = Ads::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? 'weekly' : 'monthly';
        $placement = Ads::meta( $id, 'slot' );
        if ( $sub === '' || Ads::meta( $id, 'cancelled' ) ) wp_send_json_error( 'This ad has no active subscription.' );
        if ( ! in_array( $to, self::cadences( $placement ), true ) ) wp_send_json_error( 'Monthly isn&rsquo;t available this close to Dec 20.' );

        $price = Ads::price( $placement, $to );
        $plan = AdsPayPal::plan( $mode, $placement, $to, $price );
        $url = $plan ? AdsPayPal::revise( $mode, $sub, $plan, add_query_arg( array( 'switched' => $id ), self::pageUrl( 'account' ) ) ) : '';
        if ( $url === '' ) wp_send_json_error( 'PayPal couldn&rsquo;t start the change. Please try again.' );
        update_post_meta( $id, '_mm_ad_switch', array( 'plan' => $plan, 'cadence' => $to, 'price' => $price ) );

        wp_send_json_success( array( 'redirect' => $url ) );
    }

    /** Back from PayPal after a weekly/monthly switch: apply it when PayPal shows the new plan. */
    public static function confirmSwitch( $id ) {
        $id = (int) $id;
        if ( get_post_type( $id ) !== Ads::CPT || (int) Ads::meta( $id, 'adv', 0 ) !== self::current() ) return '';
        $sw = get_post_meta( $id, '_mm_ad_switch', true );
        if ( ! is_array( $sw ) ) return '';
        $s = AdsPayPal::subscription( Ads::meta( $id, 'mode', 'live' ), Ads::meta( $id, 'sub' ) );
        if ( ( $s['plan_id'] ?? '' ) !== $sw['plan'] ) return 'PayPal hasn&rsquo;t applied the change yet. It may take a minute; refresh to check.';
        update_post_meta( $id, '_mm_ad_cadence', $sw['cadence'] );
        update_post_meta( $id, '_mm_ad_price', $sw['price'] );
        update_post_meta( $id, '_mm_ad_plan', $sw['plan'] );
        delete_post_meta( $id, '_mm_ad_switch' );
        self::mailAdvertiser( $id, 'switched' );

        return 'Switched to ' . $sw['cadence'] . ' billing (&#8369;' . number_format( $sw['price'] ) . ') from your next charge.';
    }

    /* ------------------------------------------------------------------ Review (admin) */

    /** Signed link for the review email (admin must still be signed in to WordPress). */
    public static function reviewUrl( $id, $do ) {
        return add_query_arg( array( 'action' => 'mm_ad_review', 'do' => $do, 'id' => (int) $id, 'k' => self::sign( 'review|' . (int) $id . '|' . $do ) ), admin_url( 'admin-post.php' ) );
    }

    public static function validReviewKey( $id, $do, $k ) {
        return is_string( $k ) && $k !== '' && hash_equals( self::sign( 'review|' . (int) $id . '|' . $do ), $k );
    }

    /** Approve: a new ad goes live for its paid period; a live ad's pending edit replaces its card. */
    public static function approve( $id ) {
        $pending = get_post_meta( $id, '_mm_ad_pending', true );
        if ( is_array( $pending ) && $pending ) {
            update_post_meta( $id, '_mm_ad_creative', $pending );
            delete_post_meta( $id, '_mm_ad_pending' );
            if ( in_array( Ads::meta( $id, 'status' ), array( 'live', 'paused' ), true ) ) { // a paused ad stays paused
                Ads::flush();
                self::mailAdvertiser( $id, 'edit-approved' );
                return;
            }
        }
        $sub = Ads::meta( $id, 'sub' );
        $end = 0;
        if ( $sub !== '' ) {
            $s = AdsPayPal::subscription( Ads::meta( $id, 'mode', 'live' ), $sub );
            if ( $s ) $end = AdsPayPal::periodEnd( $s, Ads::meta( $id, 'cadence', 'weekly' ) );
        }
        if ( ! $end ) $end = max( (int) Ads::meta( $id, 'end', 0 ), time() + ( Ads::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? 30 : 7 ) * DAY_IN_SECONDS );
        update_post_meta( $id, '_mm_ad_start', time() );
        update_post_meta( $id, '_mm_ad_end', $end );
        update_post_meta( $id, '_mm_ad_status', 'live' );
        if ( get_post_status( $id ) !== 'publish' ) wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        Ads::flush();
        if ( (int) Ads::meta( $id, 'adv', 0 ) ) self::mailAdvertiser( $id, 'approved' );
    }

    /** Reject: a pending edit is dropped; a new booking is cancelled and its first payment refunded. */
    public static function reject( $id, $reason = '' ) {
        $reason = mb_substr( sanitize_textarea_field( (string) $reason ), 0, 400 );
        if ( in_array( Ads::meta( $id, 'status' ), array( 'live', 'paused' ), true ) && get_post_meta( $id, '_mm_ad_pending', true ) ) {
            delete_post_meta( $id, '_mm_ad_pending' );
            update_post_meta( $id, '_mm_ad_reason', $reason );
            self::mailAdvertiser( $id, 'edit-rejected' );
            return;
        }
        update_post_meta( $id, '_mm_ad_status', 'rejected' );
        update_post_meta( $id, '_mm_ad_reason', $reason );
        $mode = Ads::meta( $id, 'mode', 'live' );
        $sub = Ads::meta( $id, 'sub' );
        if ( $sub !== '' ) {
            AdsPayPal::cancel( $mode, $sub, 'Ad not approved' );
            update_post_meta( $id, '_mm_ad_cancelled', 1 );
            $tried = false;
            foreach ( Ads::payments( $id ) as $p ) {
                if ( empty( $p['sale'] ) || ! empty( $p['refunded'] ) ) continue;
                if ( ! empty( $p['sub'] ) && $p['sub'] !== $sub ) continue; // an earlier booking that ran
                $tried = true;
                self::refundOne( $id, $p['sale'] );
            }
            if ( ! $tried ) update_post_meta( $id, '_mm_ad_refund_due', 1 ); // the payment webhook refunds it when it arrives
        }
        Ads::flush();
        if ( (int) Ads::meta( $id, 'adv', 0 ) ) self::mailAdvertiser( $id, 'rejected' );
    }

    /** Payments not yet refunded, newest last. */
    public static function openPayments( $id ) {
        return array_values( array_filter( Ads::payments( $id ), function ( $p ) { return ! empty( $p['sale'] ) && empty( $p['refunded'] ); } ) );
    }

    /**
     * Pulls the subscription's completed payments from PayPal and records any the webhook hasn't
     * (sandbox webhooks are often not set up; live ones can arrive late). At most every 5 minutes per ad.
     */
    public static function syncPayments( $id, $force = false ) {
        $id = (int) $id;
        $sub = (string) Ads::meta( $id, 'sub' );
        if ( $sub === '' || ! class_exists( 'AdsPayPal' ) ) return;
        $key = 'mm_ad_txsync_' . $id;
        if ( ! $force && get_transient( $key ) ) return;
        set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );

        $mode = Ads::meta( $id, 'mode', 'live' );
        $pays = Ads::payments( $id );
        $have = array();
        foreach ( $pays as $p ) $have[] = (string) ( $p['sale'] ?? '' );
        $new = array();
        foreach ( AdsPayPal::transactions( $mode, $sub, (int) get_post_time( 'U', true, $id ) - DAY_IN_SECONDS ) as $x ) {
            $sale = (string) ( $x['id'] ?? '' );
            if ( $sale === '' || strtoupper( (string) ( $x['status'] ?? '' ) ) !== 'COMPLETED' || in_array( $sale, $have, true ) ) continue;
            $amount = (float) ( $x['amount_with_breakdown']['gross_amount']['value'] ?? 0 );
            $pays[] = array( 't' => strtotime( (string) ( $x['time'] ?? '' ) ) ?: time(), 'amount' => $amount, 'sale' => $sale, 'sub' => $sub, 'synced' => 1 );
            $have[] = $sale;
            $new[] = $sale;
            update_post_meta( $id, '_mm_ad_paid', (float) Ads::meta( $id, 'paid', 0 ) + $amount );
            if ( class_exists( 'Insights' ) && $mode === 'live' ) Insights::log( 'ad_payment', 0, 0, $amount, (string) $id );
        }
        if ( ! $new ) return;
        usort( $pays, function ( $a, $b ) { return (int) $a['t'] - (int) $b['t']; } );
        update_post_meta( $id, '_mm_ad_payments', $pays );

        // A rejected ad whose payment hadn't arrived yet: refund it now.
        if ( Ads::meta( $id, 'status' ) === 'rejected' || get_post_meta( $id, '_mm_ad_refund_due', true ) ) {
            $ok = true;
            foreach ( $new as $sale ) $ok = self::refundOne( $id, $sale ) && $ok;
            if ( $ok ) delete_post_meta( $id, '_mm_ad_refund_due' );
        }
    }

    /** Unused share of the current paid period (PHP) for the latest payment. */
    public static function unusedAmount( $id ) {
        $pays = self::openPayments( $id );
        if ( ! $pays ) return 0;
        $last = end( $pays );
        $len = ( Ads::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? 30 : 7 ) * DAY_IN_SECONDS;
        $left = max( 0, (int) Ads::meta( $id, 'end', 0 ) - time() );

        return round( (float) $last['amount'] * min( 1, $left / $len ), 2 );
    }

    /** Refund one payment (full, or $amount). Records it; reports failures. Returns bool. */
    public static function refundOne( $id, $sale, $amount = null ) {
        $mode = Ads::meta( $id, 'mode', 'live' );
        $pays = Ads::payments( $id );
        $row = null;
        foreach ( $pays as $p ) if ( ( $p['sale'] ?? '' ) === $sale ) $row = $p;
        if ( ! $row || ! empty( $row['refunded'] ) ) return false;
        $amount = $amount === null ? null : min( (float) $row['amount'], max( 0, (float) $amount ) );
        if ( $amount !== null && $amount <= 0 ) return false;
        if ( ! AdsPayPal::refundSale( $mode, $sale, $amount !== null && $amount < (float) $row['amount'] ? $amount : null ) ) {
            self::refundFailed( $id, $sale, $amount === null ? (float) $row['amount'] : $amount, $mode );
            return false;
        }
        foreach ( $pays as &$p ) if ( ( $p['sale'] ?? '' ) === $sale ) { $p['refunded'] = 1; $p['refund_amount'] = $amount === null ? (float) $row['amount'] : $amount; }
        unset( $p );
        update_post_meta( $id, '_mm_ad_payments', $pays );

        return true;
    }

    /**
     * Remove a live (or paused) ad after approval: cancel the subscription, stop it now, and refund
     * 'full' (latest payment), 'unused' (prorated rest of the period) or 'none'. Emails the advertiser.
     */
    public static function remove( $id, $refund, $reason = '' ) {
        $reason = mb_substr( sanitize_textarea_field( (string) $reason ), 0, 400 );
        $sub = Ads::meta( $id, 'sub' );
        if ( $sub !== '' && ! Ads::meta( $id, 'cancelled' ) ) AdsPayPal::cancel( Ads::meta( $id, 'mode', 'live' ), $sub, 'Ad removed' );
        update_post_meta( $id, '_mm_ad_cancelled', 1 );
        $amount = 0;
        $pays = self::openPayments( $id );
        if ( $pays && in_array( $refund, array( 'full', 'unused' ), true ) ) {
            $last = end( $pays );
            $amount = $refund === 'full' ? (float) $last['amount'] : self::unusedAmount( $id );
            if ( $amount > 0 && ! self::refundOne( $id, $last['sale'], $refund === 'full' ? null : $amount ) ) $amount = 0;
        }
        update_post_meta( $id, '_mm_ad_status', 'removed' );
        update_post_meta( $id, '_mm_ad_end', time() );
        update_post_meta( $id, '_mm_ad_reason', $reason );
        Ads::flush();
        if ( (int) Ads::meta( $id, 'adv', 0 ) ) self::mailAdvertiser( $id, 'removed', array( 'amount' => $amount ) );

        return $amount;
    }

    /**
     * PayPal refused a refund: flag the ad and email the admin everything needed to refund it by hand.
     */
    public static function refundFailed( $id, $sale, $amount, $mode ) {
        $fails = array_filter( (array) get_post_meta( $id, '_mm_ad_refund_failed', true ) );
        if ( in_array( $sale, $fails, true ) ) return; // already reported
        $fails[] = $sale;
        update_post_meta( $id, '_mm_ad_refund_failed', $fails );
        error_log( 'AdsPortal: refund failed for ad ' . (int) $id . ' sale ' . $sale );

        $c = Ads::creative( $id );
        $p = Ads::placements()[ Ads::meta( $id, 'slot' ) ] ?? array( 'label' => '' );
        $adv = (int) Ads::meta( $id, 'adv', 0 );
        $who = get_the_title( $adv ) . ' &lt;' . esc_html( (string) get_post_meta( $adv, '_mm_adv_email', true ) ) . '&gt;';
        $pp = $mode === 'sandbox' ? 'https://www.sandbox.paypal.com/activity/payment/' : 'https://www.paypal.com/activity/payment/';

        Emails::send( get_option( 'admin_email' ), 'Action needed: refund failed for ' . html_entity_decode( $c['brand'], ENT_QUOTES, 'UTF-8' ), array(
            'preheader' => 'PayPal didn&rsquo;t accept the automatic refund. Please refund it by hand.',
            'eyebrow'   => 'Refund failed' . ( $mode === 'sandbox' ? ' &middot; sandbox' : '' ),
            'title'     => 'Please refund this payment in PayPal',
            'body'      => array(
                '<p>You rejected the <strong>' . esc_html( $c['brand'] ) . '</strong> ad (' . esc_html( $p['label'] ) . '), but PayPal didn&rsquo;t accept the automatic refund. The subscription is already cancelled, so there are no more charges.</p>',
                '<p><strong>Advertiser:</strong> ' . $who . '<br><strong>Amount:</strong> &#8369;' . number_format( $amount, 2 ) . '<br><strong>PayPal transaction ID:</strong> ' . esc_html( $sale ) . '</p>',
                '<p>To refund it: open the payment in PayPal, click <strong>Refund</strong>, and refund the full amount. The advertiser gets an automatic &ldquo;Refund issued&rdquo; email once PayPal confirms it. Common reasons: the payment is still pending, or your PayPal balance can&rsquo;t cover the refund.</p>',
            ),
            'button'    => array( 'Open the payment in PayPal', $pp . rawurlencode( $sale ) ),
            'footer'    => 'You&rsquo;re getting this because you manage ads on Manito Manita.',
        ) );
    }

    /* ------------------------------------------------------------------ PayPal events */

    protected static function adBySub( $sub ) {
        $p = get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_ad_sub', 'value' => (string) $sub ) ) ) );
        return $p ? (int) $p[0] : 0;
    }

    public static function onEvent( $mode, $type, array $r ) {
        switch ( $type ) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                // The advertiser closed the tab before we heard back: link it from the webhook instead.
                $id = (int) ( $r['custom_id'] ?? 0 );
                if ( $id && get_post_type( $id ) === Ads::CPT && Ads::meta( $id, 'status' ) === 'checkout' ) self::link( $id, (string) ( $r['id'] ?? '' ), $mode );
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $id = self::adBySub( (string) ( $r['billing_agreement_id'] ?? '' ) );
                if ( ! $id ) break;
                $amount = (float) ( $r['amount']['total'] ?? 0 );
                $sale = (string) ( $r['id'] ?? '' );
                $pays = Ads::payments( $id );
                $known = null;
                foreach ( $pays as $p ) if ( ( $p['sale'] ?? '' ) === $sale ) $known = $p;
                if ( $known && ! empty( $known['refunded'] ) ) break; // already synced and refunded
                if ( ! $known ) {
                    $pays[] = array( 't' => time(), 'amount' => $amount, 'sale' => $sale, 'sub' => (string) ( $r['billing_agreement_id'] ?? '' ) );
                    update_post_meta( $id, '_mm_ad_payments', $pays );
                    update_post_meta( $id, '_mm_ad_paid', (float) Ads::meta( $id, 'paid', 0 ) + $amount );
                    if ( class_exists( 'Insights' ) && $mode === 'live' ) Insights::log( 'ad_payment', 0, 0, $amount, (string) $id );
                }

                if ( Ads::meta( $id, 'status' ) === 'rejected' || get_post_meta( $id, '_mm_ad_refund_due', true ) ) {
                    if ( self::refundOne( $id, $sale ) ) delete_post_meta( $id, '_mm_ad_refund_due' );
                } elseif ( Ads::meta( $id, 'status' ) === 'live' ) {
                    $s = AdsPayPal::subscription( $mode, Ads::meta( $id, 'sub' ) );
                    if ( $s ) update_post_meta( $id, '_mm_ad_end', max( (int) Ads::meta( $id, 'end', 0 ), AdsPayPal::periodEnd( $s, Ads::meta( $id, 'cadence', 'weekly' ) ) ) );
                    update_post_meta( $id, '_mm_ad_payfail', 0 );
                    Ads::flush();
                    self::mailAdvertiser( $id, 'renewed', array( 'amount' => $amount ) ); // the first payment is covered by the "received" email
                }
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                $id = self::adBySub( (string) ( $r['id'] ?? '' ) );
                if ( $id && ! Ads::meta( $id, 'cancelled' ) ) { // cancelled in PayPal, not from our account page
                    update_post_meta( $id, '_mm_ad_cancelled', 1 );
                    self::mailAdvertiser( $id, 'cancelled-pp' );
                }
                break;

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                $id = self::adBySub( (string) ( $r['id'] ?? '' ) );
                if ( $id && ! Ads::meta( $id, 'payfail' ) ) {
                    update_post_meta( $id, '_mm_ad_payfail', 1 );
                    self::mailAdvertiser( $id, 'payfail' );
                }
                break;

            case 'PAYMENT.SALE.REFUNDED':
                $sale = (string) ( $r['sale_id'] ?? '' );
                $id = self::adBySub( (string) ( $r['billing_agreement_id'] ?? '' ) );
                if ( $id && $sale ) {
                    self::mailAdvertiser( $id, 'refunded', array( 'amount' => (float) ( $r['amount']['total'] ?? 0 ) ) );
                    $fails = array_values( array_diff( array_filter( (array) get_post_meta( $id, '_mm_ad_refund_failed', true ) ), array( $sale ) ) );
                    if ( $fails ) update_post_meta( $id, '_mm_ad_refund_failed', $fails ); else delete_post_meta( $id, '_mm_ad_refund_failed' );
                    $pays = Ads::payments( $id );
                    foreach ( $pays as &$p ) if ( ( $p['sale'] ?? '' ) === $sale ) $p['refunded'] = 1;
                    unset( $p );
                    update_post_meta( $id, '_mm_ad_payments', $pays );
                }
                break;
        }
    }

    /* ------------------------------------------------------------------ Hourly */

    public static function hourly() {
        // Christmas placements: stop subscriptions before they would bill a period after Dec 20.
        $md = (int) wp_date( 'md' );
        if ( $md >= 1201 && $md <= 1231 ) {
            foreach ( get_posts( array( 'post_type' => Ads::CPT, 'posts_per_page' => 100, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_ad_status', 'value' => array( 'live', 'review' ), 'compare' => 'IN' ), array( 'key' => '_mm_ad_cancelled', 'value' => '1', 'compare' => '!=' ) ) ) ) as $id ) {
                $p = Ads::placements()[ Ads::meta( $id, 'slot' ) ] ?? array();
                if ( ( $p['season'] ?? '' ) !== 'xmas' || Ads::meta( $id, 'sub' ) === '' ) continue;
                $dec20 = strtotime( wp_date( 'Y' ) . '-12-20 23:59:59 ' . wp_timezone_string() );
                if ( (int) Ads::meta( $id, 'end', 0 ) < $dec20 - 6 * DAY_IN_SECONDS ) continue; // its next renewal still runs before Dec 20
                if ( AdsPayPal::cancel( Ads::meta( $id, 'mode', 'live' ), Ads::meta( $id, 'sub' ), 'Christmas placements end Dec 20' ) ) {
                    update_post_meta( $id, '_mm_ad_cancelled', 1 );
                    self::mailAdvertiser( $id, 'season-end' );
                }
            }
        }

        // Waitlist: when a seat frees up, hold it 48 hours for the next advertiser in line.
        $all = (array) get_option( self::WAIT_OPT, array() );
        $changed = false;
        foreach ( $all as $key => $list ) {
            list( $placement, $target ) = array_pad( explode( '|', $key, 2 ), 2, '' );
            $keep = array();
            foreach ( (array) $list as $w ) {
                if ( ! empty( $w['hold'] ) && $w['hold'] <= time() ) self::mailWaitlist( (int) $w['adv'], $placement, 'expired' ); // hold ran out: they leave the line
                else $keep[] = $w;
            }
            $list = $keep;
            if ( Ads::season( $placement ) !== 'closed' ) {
                foreach ( $list as &$w ) {
                    if ( ! empty( $w['hold'] ) ) continue;
                    if ( self::seatsLeft( $placement, $target, (int) $w['adv'] ) < 1 ) break;
                    $w['hold'] = time() + self::WAIT_HOLD;
                    self::mailWaitlist( (int) $w['adv'], $placement );
                    break; // one seat per run keeps the order fair
                }
                unset( $w );
            }
            $all[ $key ] = $list;
            $changed = true;
        }
        if ( $changed ) update_option( self::WAIT_OPT, $all, false );
    }

    /* ------------------------------------------------------------------ Emails */

    protected static function advEmail( $id ) {
        return (string) get_post_meta( (int) Ads::meta( $id, 'adv', 0 ), '_mm_adv_email', true );
    }

    public static function notifyAdmin( $id, $why ) {
        $c = Ads::creative( $id );
        $edit = $why === 'edit';
        if ( $edit && is_array( get_post_meta( $id, '_mm_ad_pending', true ) ) ) $c = get_post_meta( $id, '_mm_ad_pending', true ) + $c;
        $p = Ads::placements()[ Ads::meta( $id, 'slot' ) ] ?? array( 'label' => '' );
        $links = array_map( function ( $l ) { return esc_html( $l[0] ) . ': <a href="' . esc_url( $l[1] ) . '">' . esc_html( $l[1] ) . '</a>'; }, (array) $c['links'] );

        Emails::send( get_option( 'admin_email' ), ( $edit ? 'Ad edit to review: ' : 'New ad to review: ' ) . $c['brand'], array(
            'preheader' => $c['brand'] . ' booked ' . $p['label'] . '. Approve or reject in one tap.',
            'eyebrow'   => $edit ? 'Ad edit' : 'New ad &middot; paid',
            'title'     => esc_html( $c['brand'] ) . ' &middot; ' . esc_html( $p['label'] ),
            'body'      => array(
                '<p><strong>' . esc_html( $c['headline'] ) . '</strong><br>' . esc_html( $c['text'] ) . '</p>',
                '<p>' . implode( '<br>', $links ) . ( $c['promo'] ? '<br>Promo code: ' . esc_html( $c['promo'] ) : '' ) . '</p>',
                '<p>Billing: ' . esc_html( Ads::meta( $id, 'cadence', 'weekly' ) ) . ', &#8369;' . number_format( (float) Ads::meta( $id, 'price', 0 ) ) . ( Ads::meta( $id, 'mode' ) === 'sandbox' ? ' (sandbox test)' : '' ) . '. Check the links and the <a href="' . esc_url( self::pageUrl( 'rules' ) ) . '">ad guidelines</a>.</p>',
                '<p><a href="' . esc_url( self::reviewUrl( $id, 'reject' ) ) . '" style="color:#b32d2e;">Reject' . ( $edit ? ' the edit' : ' and refund' ) . '</a> &middot; <a href="' . esc_url( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) ) . '" style="color:#1f8580;">Open in WP Admin</a></p>',
            ),
            'button'    => array( $edit ? 'Approve the edit' : 'Approve and go live', self::reviewUrl( $id, 'approve' ) ),
            'footer'    => 'You&rsquo;re getting this because you manage ads on Manito Manita.',
        ) );
    }

    public static function mailAdvertiser( $id, $what, array $x = array() ) {
        $to = self::advEmail( $id );
        if ( ! is_email( $to ) ) return;
        $c = Ads::creative( $id );
        $t = Ads::totals( $id );
        $results = '<p>Results so far: <strong>' . number_format( $t['view'] ) . '</strong> views, <strong>' . number_format( $t['sent'] ) . '</strong> emails, <strong>' . number_format( $t['click'] ) . '</strong> clicks.</p>';
        $amount = isset( $x['amount'] ) ? '&#8369;' . number_format( (float) $x['amount'], 2 ) : '';
        $why = (string) get_post_meta( $id, '_mm_ad_reason', true );
        $whyHtml = $why !== '' ? '<p><strong>Reason:</strong> ' . esc_html( $why ) . '</p>' : '';
        $p = Ads::placements()[ Ads::meta( $id, 'slot' ) ] ?? array( 'label' => 'your placement' );
        $end = (int) Ads::meta( $id, 'end', 0 );
        $endTxt = $end ? wp_date( 'F j, g:i A', $end ) : '';
        $acct = self::pageUrl( 'account' );
        $m = array(
            'received'      => array( 'We got your ad: ' . $c['brand'], 'We&rsquo;re reviewing your ad', array( '<p>Thanks! Your payment went through and your ' . esc_html( $p['label'] ) . ' ad is in review. We check every ad against our <a href="' . esc_url( self::pageUrl( 'rules' ) ) . '">ad guidelines</a>, usually within a day.</p>', '<p>If it isn&rsquo;t approved, your payment is refunded in full.</p>' ) ),
            'approved'      => array( 'Your ad is live: ' . $c['brand'], 'Your ad is live', array( '<p>Your ' . esc_html( $p['label'] ) . ' ad is now showing' . ( $endTxt ? ', paid through ' . esc_html( $endTxt ) : '' ) . '. It renews automatically until you cancel.</p>', '<p>Views, clicks and results by day are in your advertiser account.</p>' ) ),
            'rejected'      => array( 'Your ad wasn&rsquo;t approved', 'Your ad wasn&rsquo;t approved', array( '<p>We couldn&rsquo;t approve your ' . esc_html( $p['label'] ) . ' ad because it doesn&rsquo;t meet our <a href="' . esc_url( self::pageUrl( 'rules' ) ) . '">ad guidelines</a>. Your subscription is cancelled and your payment is being refunded to your PayPal account or card (it can take a few days to show).</p>', $whyHtml, '<p>To try again, open your account, tap <strong>Edit and resubmit</strong>, fix the card, then book it again.</p>' ) ),
            'removed'       => array( 'Your ad has been removed', 'Your ad has been removed', array( '<p>We&rsquo;ve stopped your ' . esc_html( $p['label'] ) . ' ad and cancelled its subscription, so there will be no more charges.</p>', $whyHtml, '<p>' . ( isset( $x['amount'] ) && (float) $x['amount'] > 0 ? 'We&rsquo;re refunding <strong>' . $amount . '</strong> to your PayPal account or card (it can take a few days to show).' : 'This doesn&rsquo;t include a refund.' ) . ' Questions? Just reply to this email.</p>', $results ) ),
            'edit-approved' => array( 'Your ad edit is live', 'Your updated card is live', array( '<p>Your edited ' . esc_html( $p['label'] ) . ' card has replaced the previous one.</p>' ) ),
            'edit-rejected' => array( 'Your ad edit wasn&rsquo;t approved', 'Your edit wasn&rsquo;t approved', array( '<p>Your current card keeps running. The edit didn&rsquo;t meet our <a href="' . esc_url( self::pageUrl( 'rules' ) ) . '">ad guidelines</a>; you can change it and save again.</p>', $whyHtml ) ),
            'cancelled'     => array( 'Your ad subscription is cancelled', 'Subscription cancelled', array( '<p>There will be no more charges for your ' . esc_html( $p['label'] ) . ' ad.' . ( $end > time() ? ' It keeps running until ' . esc_html( $endTxt ) . '.' : '' ) . '</p>' ) ),
            'payfail'       => array( 'Payment failed for your ad', 'Your renewal payment failed', array( '<p>PayPal couldn&rsquo;t charge the renewal for your ' . esc_html( $p['label'] ) . ' ad.' . ( $end > time() ? ' It keeps running until ' . esc_html( $endTxt ) . ', then stops.' : '' ) . ' Update your payment method in PayPal, or book again from your account.</p>' ) ),
            'edit-received' => array( 'We got your ad edit', 'Your edit is in review', array( '<p>Thanks! We&rsquo;re reviewing the changes to your ' . esc_html( $p['label'] ) . ' card, usually within a day. Your current card keeps running until the edit is approved.</p>' ) ),
            'paused'        => array( 'Your ad is paused', 'Your ad is paused', array( '<p>We&rsquo;ve paused your ' . esc_html( $p['label'] ) . ' ad, so it isn&rsquo;t showing right now. Reply to this email or contact us and we&rsquo;ll explain and help you get it running again.</p>', $results ) ),
            'renewed'       => array( 'Payment received: ' . html_entity_decode( $amount, ENT_QUOTES, 'UTF-8' ), 'Thanks, your ad renewed', array( '<p>We received ' . $amount . ' for your ' . esc_html( $p['label'] ) . ' ad' . ( $endTxt ? '. It&rsquo;s paid through <strong>' . esc_html( $endTxt ) . '</strong>' : '' ) . '. PayPal sends your official receipt separately.</p>', $results ) ),
            'refunded'      => array( 'Refund issued: ' . html_entity_decode( $amount, ENT_QUOTES, 'UTF-8' ), 'Your refund is on its way', array( '<p>We refunded ' . $amount . ' for your ' . esc_html( $p['label'] ) . ' ad. It goes back to your PayPal account or card and can take a few days to show.</p>' ) ),
            'switched'      => array( 'Billing changed to ' . Ads::meta( $id, 'cadence', 'weekly' ), 'Billing updated', array( '<p>Your ' . esc_html( $p['label'] ) . ' ad now bills <strong>' . esc_html( Ads::meta( $id, 'cadence', 'weekly' ) ) . '</strong> at &#8369;' . number_format( (float) Ads::meta( $id, 'price', 0 ) ) . ', starting from your next charge.</p>' ) ),
            'cancelled-pp'  => array( 'Your ad subscription was cancelled', 'Subscription cancelled', array( '<p>Your PayPal subscription for the ' . esc_html( $p['label'] ) . ' ad was cancelled, so there will be no more charges.' . ( $end > time() ? ' Your ad keeps running until ' . esc_html( $endTxt ) . '.' : '' ) . ' You can book again anytime from your account.</p>' ) ),
            'ended'         => array( 'Your ad has ended: ' . $c['brand'], 'Your ad has ended', array( '<p>Your ' . esc_html( $p['label'] ) . ' ad finished its paid period and is no longer showing.</p>', $results, '<p>Want to keep going? Book again from your account in a minute; your card is saved.</p>' ) ),
            'season-end'    => array( 'Your Christmas ad ends Dec 20', 'Christmas season wrap-up', array( '<p>Christmas placements close after Dec 20, so we&rsquo;ve stopped your subscription before it bills again. Your ad keeps running until ' . esc_html( $endTxt ) . '. Thank you for sponsoring this season!</p>', '<p>Gift guides and registries run all year if you&rsquo;d like to keep going.</p>' ) ),
        )[ $what ] ?? null;
        if ( ! $m ) return;

        Emails::send( $to, html_entity_decode( $m[0], ENT_QUOTES, 'UTF-8' ), array(
            'preheader' => wp_strip_all_tags( $m[2][0] ),
            'eyebrow'   => 'Manito Manita ads',
            'title'     => $m[1],
            'body'      => $m[2],
            'button'    => in_array( $what, array( 'ended', 'approved', 'renewed', 'paused' ), true ) && class_exists( 'AdsReport' ) ? array( 'See your results', AdsReport::url( $id ) ) : array( 'Open my advertiser account', $acct ),
            'footer'    => 'You&rsquo;re getting this because you advertise on Manito Manita.',
        ) );
    }

    public static function mailWaitlist( $aid, $placement, $what = 'open', $pos = 0 ) {
        $to = (string) get_post_meta( $aid, '_mm_adv_email', true );
        if ( ! is_email( $to ) ) return;
        $p = Ads::placements()[ $placement ] ?? array( 'label' => 'A placement' );
        if ( $what !== 'open' ) {
            $joined = $what === 'joined';
            Emails::send( $to, $joined ? 'You&rsquo;re on the waitlist' : 'Your held seat was released', array(
                'preheader' => $joined ? 'We&rsquo;ll email you when a seat opens.' : 'The 48-hour hold ended.',
                'eyebrow'   => 'Waitlist',
                'title'     => $joined ? 'You&rsquo;re #' . (int) $pos . ' on the waitlist' : 'Your held seat was released',
                'body'      => array( $joined
                    ? '<p>You joined the waitlist for <strong>' . esc_html( $p['label'] ) . '</strong>. When a seat opens we&rsquo;ll email you and hold it for you for 48 hours.</p>'
                    : '<p>The 48-hour hold on your <strong>' . esc_html( $p['label'] ) . '</strong> seat ended without a booking, so it went to the next advertiser. You can join the waitlist again anytime.</p>' ),
                'button'    => array( 'Open my advertiser account', self::pageUrl( 'account' ) ),
                'footer'    => 'You&rsquo;re getting this because you joined the waitlist on Manito Manita.',
            ) );
            return;
        }
        Emails::send( $to, 'A seat opened: ' . html_entity_decode( $p['label'], ENT_QUOTES, 'UTF-8' ), array(
            'preheader' => 'We&rsquo;re holding it for you for 48 hours.',
            'eyebrow'   => 'Waitlist',
            'title'     => 'Your seat is ready',
            'body'      => array( '<p>A seat opened on <strong>' . esc_html( $p['label'] ) . '</strong> and we&rsquo;re holding it for you for <strong>48 hours</strong>. Book it from your account before the hold ends.</p>' ),
            'button'    => array( 'Book my seat', self::pageUrl( 'account' ) ),
            'footer'    => 'You&rsquo;re getting this because you joined the waitlist on Manito Manita.',
        ) );
    }

    /* ------------------------------------------------------------------ Audience numbers for /advertise/ */

    /** Cached 12 hours. */
    public static function audience() {
        $a = get_transient( 'mm_ads_audience' );
        if ( is_array( $a ) ) return $a;
        $a = array( 'groups' => 0, 'members' => 0, 'people30' => 0 );
        if ( class_exists( 'Insights' ) ) {
            $k = Insights::kpis( Insights::period( 'season' ) );
            $a['groups'] = (int) $k['groups']['value'];
            $a['members'] = (int) $k['members_joined']['value'];
        }
        if ( class_exists( 'InsightsGoogle' ) && InsightsGoogle::configured() ) {
            $o = InsightsGoogle::overview( Insights::period( '30d' ) );
            $a['people30'] = (int) ( $o['totals']['users'] ?? 0 );
        }
        set_transient( 'mm_ads_audience', $a, 12 * HOUR_IN_SECONDS );

        return $a;
    }
}

AdsPortal::init();
