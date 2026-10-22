<?php
/**
 * * Involve Class. Involve Asia Publisher API: approved offers and product deeplinks.
 *
 * - Offers: once a day the approved offers are fetched and turned into a "store domain => offer"
 *   map, so links to any approved advertiser (Shopee, Lazada, Shein, ...) are tracked without code
 *   changes. The hardcoded map in Affiliate stays as the fallback.
 * - Deeplinks: the plain invol.co/aff_m?url= format tracks, but Shopee/Lazada drop the product and
 *   open a generic page (also in their apps). /deeplink/generate returns a link to the exact
 *   product. Generating is limited (~1,000 unique links / 30 days, 60 calls / minute), so each
 *   product URL is generated once in the background, stored, and reused; until then (or when a
 *   store refuses the product) the plain tracked link is used.
 *
 * Config (wp-config.php):
 *   define( 'INVOLVE_API_KEY', '...' );
 *   define( 'INVOLVE_API_SECRET', '...' );
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Involve
 */
class Involve {
    const API          = 'https://api.involve.asia/api/';
    const OFFERS_OPT   = 'mm_involve_offers';    // ['time' => int, 'map' => [domain => offer]]
    const LINKS_OPT    = 'mm_involve_deeplinks'; // [md5(url) => ['l' => link] | ['e' => error], 't' => time]
    const QUEUE_OPT    = 'mm_involve_queue';     // [md5(url) => ['url' => ..., 'offer' => api offer id]]
    const SYNC_HOOK    = 'mm_involve_sync_offers';
    const WORK_HOOK    = 'mm_involve_deeplinks';
    const MONTH_LIMIT  = 900;   // stay under Involve Asia's ~1,000 unique links per rolling 30 days
    const PER_RUN      = 20;    // deeplinks generated per background run (API allows 60 / minute)
    const RETRY_FAILED = 2592000; // a product the store refused is retried after 30 days

    protected static $hooked = false;
    protected static $links  = null;
    protected static $queued = array();

    public static function init() {
        if ( self::$hooked || ! self::enabled() ) return;
        self::$hooked = true;

        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::SYNC_HOOK, array( __CLASS__, 'syncOffers' ) );
        add_action( self::WORK_HOOK, array( __CLASS__, 'processQueue' ) );
        add_action( 'shutdown', array( __CLASS__, 'saveQueue' ) );
    }

    public static function enabled() {
        return defined( 'INVOLVE_API_KEY' ) && INVOLVE_API_KEY !== '' && defined( 'INVOLVE_API_SECRET' ) && INVOLVE_API_SECRET !== '';
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) wp_schedule_event( time() + 60, 'daily', self::SYNC_HOOK );

        $offers = get_option( self::OFFERS_OPT );
        if ( ! $offers && ! get_transient( 'mm_involve_sync_lock' ) ) {
            set_transient( 'mm_involve_sync_lock', 1, 600 );
            wp_schedule_single_event( time(), self::SYNC_HOOK );
        }
    }

    /* ---------------------------------------------------------------- Offers */

    /**
     * Approved offer for a URL's host: ['api_id', 'link_id', 'name'] or false.
     */
    public static function offerFor( $host ) {
        $offers = get_option( self::OFFERS_OPT );

        if ( empty( $offers['map'] ) ) return false;

        $host = strtolower( $host );

        foreach ( $offers['map'] as $domain => $offer ) {
            if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) return $offer;
        }

        return false;
    }

    /**
     * Daily: approved, active offers that cover the Philippines (or are international) => domain map.
     */
    public static function syncOffers() {
        $token = self::token();
        if ( ! $token ) return;

        $map  = array();
        $page = 1;

        do {
            $r = self::post( 'offers/all', array(
                'page'                          => $page,
                'limit'                         => 100,
                'filters[application_status]'   => 'Approved',
                'filters[offer_status]'         => 'Active',
            ), $token );

            if ( ! $r || ( $r['status'] ?? '' ) !== 'success' ) break;

            foreach ( (array) ( $r['data']['data'] ?? array() ) as $o ) {
                $domain = self::registrableDomain( parse_url( (string) ( $o['preview_url'] ?? '' ), PHP_URL_HOST ) );
                $countries = is_array( $o['countries'] ?? null ) ? implode( ',', $o['countries'] ) : (string) ( $o['countries'] ?? '' );
                preg_match( '/offer_id=(\d+)/', (string) ( $o['tracking_link'] ?? '' ), $m );

                if ( ! $domain || empty( $m[1] ) ) continue;
                if ( stripos( $countries, 'Philippines' ) === false && stripos( $countries, 'International' ) === false ) continue;
                if ( stripos( (string) $o['offer_name'], 'non-deeplinkable' ) !== false ) continue;
                if ( preg_match( '/\bB2B\b/i', (string) $o['offer_name'] ) ) continue; // advertiser sign-ups (e.g. TikTok B2B), not shopping
                if ( isset( $map[ $domain ] ) ) continue; // first (most relevant) offer per store wins

                $map[ $domain ] = array(
                    'api_id'  => (int) $o['offer_id'],
                    'link_id' => $m[1],
                    'name'    => self::storeName( $domain, (string) $o['offer_name'] ),
                );
            }

            $page = $r['data']['nextPage'] ?? null;
        } while ( $page && $page < 20 );

        if ( $map ) update_option( self::OFFERS_OPT, array( 'time' => time(), 'map' => $map ), false );
    }

    /* ------------------------------------------------------------- Deeplinks */

    /**
     * Stored product deeplink for a URL, or '' (queues generation the first time).
     */
    public static function deeplink( $url, $apiOfferId ) {
        if ( ! $apiOfferId ) return '';

        $key = md5( $url );

        if ( self::$links === null ) self::$links = (array) get_option( self::LINKS_OPT, array() );

        if ( isset( self::$links[ $key ] ) ) {
            $entry = self::$links[ $key ];

            if ( ! empty( $entry['l'] ) ) return $entry['l'];
            if ( ( $entry['t'] ?? 0 ) > time() - self::RETRY_FAILED ) return '';
        }

        self::$queued[ $key ] = array( 'url' => $url, 'offer' => (int) $apiOfferId );

        return '';
    }

    /**
     * Saves URLs seen during this request to the queue (one option write per request).
     */
    public static function saveQueue() {
        if ( ! self::$queued ) return;

        $queue = (array) get_option( self::QUEUE_OPT, array() );
        $before = count( $queue );
        $queue = array_slice( $queue + self::$queued, 0, 500, true );

        if ( count( $queue ) !== $before ) update_option( self::QUEUE_OPT, $queue, false );
        if ( ! wp_next_scheduled( self::WORK_HOOK ) ) wp_schedule_single_event( time() + 10, self::WORK_HOOK );

        self::$queued = array();
    }

    /**
     * Background: generate up to PER_RUN queued deeplinks, ~1 per second, within the monthly budget.
     */
    public static function processQueue() {
        $queue = (array) get_option( self::QUEUE_OPT, array() );
        if ( ! $queue ) return;

        $links = (array) get_option( self::LINKS_OPT, array() );
        $made  = 0;

        foreach ( $links as $e ) {
            if ( ! empty( $e['l'] ) && ( $e['t'] ?? 0 ) > time() - 30 * DAY_IN_SECONDS ) $made++;
        }

        $token = self::token();
        if ( ! $token ) return;

        $done = 0;

        foreach ( $queue as $key => $job ) {
            if ( $done >= self::PER_RUN ) break;

            unset( $queue[ $key ] );

            if ( ! empty( $links[ $key ]['l'] ) ) continue;

            if ( $made >= self::MONTH_LIMIT ) {
                error_log( 'Involve: monthly deeplink budget reached; using standard tracking links.' );
                $queue = array(); // try again after older links age out of the 30-day window
                break;
            }

            $r = self::post( 'deeplink/generate', array(
                'offer_id' => $job['offer'],
                'url'      => $job['url'],
                'aff_sub'  => 'deeplink',
            ), $token );

            $link = ( $r && ( $r['status'] ?? '' ) === 'success' ) ? (string) ( $r['data']['tracking_link'] ?? '' ) : '';

            if ( $link && preg_match( '~^https://~', $link ) ) {
                $links[ $key ] = array( 'l' => esc_url_raw( $link ), 't' => time() );
                $made++;
            } else {
                $links[ $key ] = array( 'e' => substr( (string) ( $r['message'] ?? 'failed' ), 0, 120 ), 't' => time() );
            }

            $done++;
            usleep( 1100000 );
        }

        update_option( self::LINKS_OPT, $links, false );
        update_option( self::QUEUE_OPT, $queue, false );
        self::$links = $links;

        if ( $queue ) wp_schedule_single_event( time() + 60, self::WORK_HOOK );
    }

    /* --------------------------------------------------------------- Helpers */

    protected static function token() {
        $token = get_transient( 'mm_involve_token' );
        if ( $token ) return $token;

        $r = self::post( 'authenticate', array( 'key' => INVOLVE_API_KEY, 'secret' => INVOLVE_API_SECRET ) );
        $token = (string) ( $r['data']['token'] ?? '' );

        if ( $token ) set_transient( 'mm_involve_token', $token, 100 * MINUTE_IN_SECONDS ); // tokens last 2 hours
        else error_log( 'Involve: authentication failed (' . ( $r['message'] ?? 'no response' ) . ')' );

        return $token;
    }

    protected static function post( $path, $fields, $token = '' ) {
        $response = wp_remote_post( self::API . $path, array(
            'timeout' => 20,
            'headers' => $token ? array( 'Authorization' => 'Bearer ' . $token ) : array(),
            'body'    => $fields,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Involve: ' . $path . ' ' . $response->get_error_message() );
            return false;
        }

        if ( (int) wp_remote_retrieve_response_code( $response ) === 401 ) delete_transient( 'mm_involve_token' );

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * "pages.lazada.com.ph" => "lazada.com.ph", "www.shein.com" => "shein.com".
     */
    public static function registrableDomain( $host ) {
        $host = strtolower( trim( (string) $host ) );
        if ( $host === '' ) return '';

        $parts = explode( '.', $host );
        $n = count( $parts );
        if ( $n <= 2 ) return $host;

        $sld = $parts[ $n - 2 ];
        $keep = ( strlen( $parts[ $n - 1 ] ) === 2 && in_array( $sld, array( 'com', 'co', 'net', 'org', 'gov', 'edu' ), true ) ) ? 3 : 2;

        return implode( '.', array_slice( $parts, -$keep ) );
    }

    protected static function storeName( $domain, $offerName ) {
        $known = array( 'lazada.com.ph' => 'Lazada', 'shopee.ph' => 'Shopee', 'shein.com' => 'SHEIN', 'aliexpress.com' => 'AliExpress', 'alibaba.com' => 'Alibaba', 'zalora.com.ph' => 'Zalora', 'tiktok.com' => 'TikTok Shop' );

        if ( isset( $known[ $domain ] ) ) return $known[ $domain ];

        return trim( preg_replace( '/\s*[\-(].*$/', '', $offerName ) );
    }
}

Involve::init();
