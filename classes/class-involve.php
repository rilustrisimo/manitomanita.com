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
 *   product is generated once in the background, stored, and reused; until then (or when a
 *   store refuses the product) the plain tracked link is used.
 * - One entry per product: URLs are reduced to a product fingerprint (tracking parameters removed,
 *   e.g. shopee:{shop}:{item}, lazada:{item}, shein:{id}) and short links (shp.ee, s.lazada...) are
 *   resolved to the product page first, so the same product shared differently uses one deeplink.
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
    const LINKS_OPT    = 'mm_involve_deeplinks'; // [md5(fingerprint) => ['l' => link] | ['e' => error] | ['a' => alias key], 't' => time]
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

        $url = self::cleanUrl( $url );
        $key = self::key( $url );

        if ( self::$links === null ) self::$links = (array) get_option( self::LINKS_OPT, array() );

        $entry = self::$links[ $key ] ?? null;

        if ( isset( $entry['a'] ) ) $entry = self::$links[ $entry['a'] ] ?? null; // short link => product

        if ( $entry ) {
            if ( ! empty( $entry['l'] ) ) return $entry['l'];
            if ( ( $entry['t'] ?? 0 ) > time() - self::RETRY_FAILED ) return '';
        }

        self::$queued[ $key ] = array( 'url' => $url, 'offer' => (int) $apiOfferId );

        return '';
    }

    /* ------------------------------------------------------- Product fingerprints */

    const TRACKING_PARAMS = '/^(utm_|spm$|scm$|sp_atk$|xptdk$|smtt$|mmp_pid$|uls_trackid$|af_|gads_t_sig$|fbclid$|gclid$|ttclid$|clickTrackInfo$|from$|share|laz_|trafficFrom$|exlaid$|sub_|aff_|deep_and_deferred$|dsource$|search$|ref$|src_|url_from$|onelink)/i';

    /**
     * https, lowercase host, no fragment. Store product pages lose their whole query string;
     * other URLs only lose known tracking parameters.
     */
    public static function cleanUrl( $url ) {
        $url = trim( (string) $url );
        if ( ! preg_match( '~^https?://~i', $url ) ) $url = 'https://' . ltrim( $url, '/' );

        $p = parse_url( $url );
        if ( empty( $p['host'] ) ) return $url;

        $host  = strtolower( $p['host'] );
        $path  = $p['path'] ?? '/';
        $query = '';

        if ( ! self::productId( $host, $path ) && ! empty( $p['query'] ) ) {
            parse_str( $p['query'], $args );
            foreach ( array_keys( $args ) as $k ) {
                if ( preg_match( self::TRACKING_PARAMS, (string) $k ) ) unset( $args[ $k ] );
            }
            $query = $args ? '?' . http_build_query( $args ) : '';
        }

        return 'https://' . $host . $path . $query;
    }

    /**
     * Store-specific product id ("shopee:123:456", "lazada:789", "shein:111"), or ''.
     */
    public static function productId( $host, $path ) {
        $domain = self::registrableDomain( $host );

        if ( $domain === 'shopee.ph' ) {
            if ( preg_match( '~-i\.(\d+)\.(\d+)~', $path, $m ) || preg_match( '~/product/(\d+)/(\d+)~', $path, $m ) ) return 'shopee:' . $m[1] . ':' . $m[2];
        } elseif ( $domain === 'lazada.com.ph' ) {
            if ( preg_match( '~/products/.*?-i(\d+)(?:-s\d+)?\.html~', $path, $m ) || preg_match( '~/products/i(\d+)~', $path, $m ) ) return 'lazada:' . $m[1];
        } elseif ( $domain === 'shein.com' ) {
            if ( preg_match( '~-p-(\d+)~', $path, $m ) ) return 'shein:' . $m[1];
        }

        return '';
    }

    protected static function key( $cleanUrl ) {
        $p = parse_url( $cleanUrl );
        $id = self::productId( $p['host'] ?? '', $p['path'] ?? '' );

        return md5( $id !== '' ? $id : $cleanUrl );
    }

    public static function isShortLink( $url ) {
        $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

        return in_array( $host, array( 'shp.ee', 'shope.ee', 's.shopee.ph', 's.lazada.com.ph', 'c.lazada.com.ph', 'shein.top', 'onelink.shein.com' ), true );
    }

    /**
     * Follows a short link's redirects (desktop browser UA, which gets a plain 301) to the page.
     */
    public static function resolve( $url ) {
        for ( $hop = 0; $hop < 6; $hop++ ) {
            $r = wp_remote_get( $url, array(
                'timeout'     => 10,
                'redirection' => 0,
                'user-agent'  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
            ) );

            if ( is_wp_error( $r ) ) return '';

            $location = wp_remote_retrieve_header( $r, 'location' );
            if ( ! $location ) return self::isShortLink( $url ) ? '' : $url;

            if ( strpos( $location, '//' ) === 0 ) $location = 'https:' . $location;
            elseif ( strpos( $location, '/' ) === 0 ) $location = 'https://' . parse_url( $url, PHP_URL_HOST ) . $location;

            $url = $location;
            if ( ! self::isShortLink( $url ) ) return $url;
        }

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

            if ( ! empty( $links[ $key ]['l'] ) || isset( $links[ $key ]['a'] ) ) continue;

            $url = $job['url'];
            $saveKey = $key;

            // Short link: find the product page first; reuse its deeplink if we already have one.
            if ( self::isShortLink( $url ) ) {
                $resolved = self::resolve( $url );

                if ( ! $resolved ) {
                    $links[ $key ] = array( 'e' => 'short link could not be resolved', 't' => time() );
                    continue;
                }

                $url = self::cleanUrl( $resolved );
                $saveKey = self::key( $url );
                $links[ $key ] = array( 'a' => $saveKey, 't' => time() );

                if ( ! empty( $links[ $saveKey ]['l'] ) ) continue;
                if ( isset( $links[ $saveKey ]['e'] ) && ( $links[ $saveKey ]['t'] ?? 0 ) > time() - self::RETRY_FAILED ) continue;
            }

            if ( $made >= self::MONTH_LIMIT ) {
                error_log( 'Involve: monthly deeplink budget reached; using standard tracking links.' );
                $queue = array(); // try again after older links age out of the 30-day window
                break;
            }

            $r = self::post( 'deeplink/generate', array(
                'offer_id' => $job['offer'],
                'url'      => $url,
                'aff_sub'  => 'deeplink',
            ), $token );

            $link = ( $r && ( $r['status'] ?? '' ) === 'success' ) ? (string) ( $r['data']['tracking_link'] ?? '' ) : '';

            if ( $link && preg_match( '~^https://~', $link ) ) {
                $links[ $saveKey ] = array( 'l' => esc_url_raw( $link ), 't' => time() );
                $made++;
            } else {
                $links[ $saveKey ] = array( 'e' => substr( (string) ( $r['message'] ?? 'failed' ), 0, 120 ), 't' => time() );
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
