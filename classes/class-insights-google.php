<?php
/**
 * Google data for Manito Insights: GA4 Data API (reports + realtime) and Search Console, using a
 * service account (no libraries: JWT signed with openssl, token cached 50 min).
 *
 * wp-config.php (never in the repo):
 *   define( 'MM_GOOGLE_SA_JSON', '/home/.../manito-insights.json' ); // path to the key file, or the JSON itself
 *   define( 'GA4_PROPERTY_ID', '123456789' );                          // numeric GA4 property id
 *   define( 'MM_GSC_SITE', 'sc-domain:manitomanita.com' );             // Search Console property
 *
 * Calls happen only from the admin dashboard / weekly email, never for visitors. Caches: realtime 60s,
 * GA4 reports 1h, Search Console 6h. Any failure returns array( 'error' => ... ) so the page still works.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class InsightsGoogle {
    const SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';

    public static function configured() {
        return defined( 'MM_GOOGLE_SA_JSON' ) && MM_GOOGLE_SA_JSON && defined( 'GA4_PROPERTY_ID' ) && GA4_PROPERTY_ID;
    }

    protected static function key() {
        $raw = (string) MM_GOOGLE_SA_JSON;
        if ( strpos( ltrim( $raw ), '{' ) !== 0 && is_readable( $raw ) ) $raw = (string) file_get_contents( $raw );
        $k = json_decode( $raw, true );

        return ( is_array( $k ) && ! empty( $k['client_email'] ) && ! empty( $k['private_key'] ) ) ? $k : null;
    }

    protected static function b64( $s ) {
        return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
    }

    /** Signed JWT for the token request (exposed for tests). */
    public static function jwt( array $key, $now ) {
        $head = self::b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
        $claim = self::b64( wp_json_encode( array( 'iss' => $key['client_email'], 'scope' => self::SCOPES, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600 ) ) );
        $sig = '';
        if ( ! openssl_sign( $head . '.' . $claim, $sig, $key['private_key'], 'sha256WithRSAEncryption' ) ) return '';

        return $head . '.' . $claim . '.' . self::b64( $sig );
    }

    protected static function token() {
        $t = get_transient( 'mm_google_token' );
        if ( $t ) return $t;

        $key = self::key();
        if ( ! $key ) return new WP_Error( 'mm_google', 'The service account key could not be read.' );
        $jwt = self::jwt( $key, time() );
        if ( $jwt === '' ) return new WP_Error( 'mm_google', 'Could not sign the request with the key.' );

        $r = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ) ) );
        if ( is_wp_error( $r ) ) return $r;
        $b = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( empty( $b['access_token'] ) ) return new WP_Error( 'mm_google', 'Google sign-in failed: ' . ( $b['error_description'] ?? $b['error'] ?? 'unknown error' ) );

        set_transient( 'mm_google_token', $b['access_token'], 50 * MINUTE_IN_SECONDS );

        return $b['access_token'];
    }

    protected static function call( $url, array $body, $ttl ) {
        $ck = 'mm_g_' . md5( $url . wp_json_encode( $body ) );
        $hit = get_transient( $ck );
        if ( $hit !== false ) return $hit;

        $token = self::token();
        if ( is_wp_error( $token ) ) return array( 'error' => $token->get_error_message() );

        $r = wp_remote_post( $url, array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
        if ( is_wp_error( $r ) ) return array( 'error' => $r->get_error_message() );
        $code = (int) wp_remote_retrieve_response_code( $r );
        $b = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( $code === 401 ) delete_transient( 'mm_google_token' );
        if ( $code !== 200 ) return array( 'error' => (string) ( $b['error']['message'] ?? ( 'HTTP ' . $code ) ) );

        set_transient( $ck, $b, $ttl );

        return $b;
    }

    /* ------------------------------------------------------------------ GA4 */

    protected static function ga( array $body, $realtime = false ) {
        $u = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( (string) GA4_PROPERTY_ID ) . ( $realtime ? ':runRealtimeReport' : ':runReport' );

        return self::call( $u, $body, $realtime ? 60 : HOUR_IN_SECONDS );
    }

    /** GA4 rows as arrays: dimension values + metric values. */
    protected static function rows( $res ) {
        if ( ! empty( $res['error'] ) ) return array();
        $out = array();
        foreach ( (array) ( $res['rows'] ?? array() ) as $r ) {
            $out[] = array(
                'd' => array_map( function ( $x ) { return $x['value'] ?? ''; }, (array) ( $r['dimensionValues'] ?? array() ) ),
                'm' => array_map( function ( $x ) { return (float) ( $x['value'] ?? 0 ); }, (array) ( $r['metricValues'] ?? array() ) ),
            );
        }

        return $out;
    }

    protected static function ranges( $p ) {
        return array( array( 'startDate' => $p[0], 'endDate' => $p[1] ) );
    }

    protected static function top( $p, $dim, $metric = 'sessions', $limit = 8, $extra = array() ) {
        $res = self::ga( array_merge( array( 'dateRanges' => self::ranges( $p ), 'dimensions' => array( array( 'name' => $dim ) ), 'metrics' => array( array( 'name' => $metric ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => $metric ), 'desc' => true ) ), 'limit' => $limit ), $extra ) );
        if ( ! empty( $res['error'] ) ) return array( 'error' => $res['error'] );

        return array_map( function ( $r ) { return array( 'name' => $r['d'][0] !== '' ? $r['d'][0] : '(not set)', 'value' => $r['m'][0] ); }, self::rows( $res ) );
    }

    protected static function totals( $p ) {
        $res = self::ga( array( 'dateRanges' => array( array( 'startDate' => $p[0], 'endDate' => $p[1] ), array( 'startDate' => $p[2], 'endDate' => $p[3] ) ), 'metrics' => array( array( 'name' => 'sessions' ), array( 'name' => 'totalUsers' ), array( 'name' => 'newUsers' ), array( 'name' => 'engagementRate' ), array( 'name' => 'averageSessionDuration' ), array( 'name' => 'screenPageViews' ) ) ) );
        if ( ! empty( $res['error'] ) ) return array( 'error' => $res['error'] );
        $now = $was = array();
        foreach ( self::rows( $res ) as $r ) {
            // with two date ranges GA4 adds a dateRange dimension: date_range_0 / date_range_1
            if ( ( $r['d'][0] ?? 'date_range_0' ) === 'date_range_1' ) $was = $r['m']; else $now = $r['m'];
        }
        $names = array( 'sessions', 'users', 'new_users', 'engagement', 'avg_duration', 'views' );
        $o = array();
        foreach ( $names as $i => $n ) $o[ $n ] = array( 'value' => $now[ $i ] ?? 0, 'prev' => $was[ $i ] ?? 0, 'change' => Insights::change( $now[ $i ] ?? 0, $was[ $i ] ?? 0 ) );
        $o['sessions_value'] = $o['sessions']['value'];

        return $o;
    }

    public static function overview( $p ) {
        if ( ! self::configured() ) return null;
        $t = self::totals( $p );

        return array( 'totals' => empty( $t['error'] ) ? array( 'sessions' => $t['sessions']['value'], 'users' => $t['users']['value'] ) : array(), 'kpis' => $t, 'sources' => self::top( $p, 'sessionSource', 'sessions', 5 ) );
    }

    public static function traffic( $p ) {
        if ( ! self::configured() ) return null;
        $trend = self::ga( array( 'dateRanges' => self::ranges( $p ), 'dimensions' => array( array( 'name' => 'date' ) ), 'metrics' => array( array( 'name' => 'sessions' ), array( 'name' => 'totalUsers' ) ), 'orderBys' => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ), 'limit' => 400 ) );
        $series = array( 'labels' => array(), 'sessions' => array(), 'users' => array() );
        foreach ( self::rows( $trend ) as $r ) {
            $series['labels'][] = substr( $r['d'][0], 0, 4 ) . '-' . substr( $r['d'][0], 4, 2 ) . '-' . substr( $r['d'][0], 6, 2 );
            $series['sessions'][] = $r['m'][0];
            $series['users'][] = $r['m'][1];
        }
        $pages = self::ga( array( 'dateRanges' => self::ranges( $p ), 'dimensions' => array( array( 'name' => 'pagePath' ) ), 'metrics' => array( array( 'name' => 'screenPageViews' ), array( 'name' => 'activeUsers' ), array( 'name' => 'userEngagementDuration' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'screenPageViews' ), 'desc' => true ) ), 'limit' => 15 ) );
        $pageRows = array_map( function ( $r ) { return array( 'path' => $r['d'][0], 'views' => $r['m'][0], 'users' => $r['m'][1], 'time' => $r['m'][1] ? round( $r['m'][2] / $r['m'][1] ) : 0 ); }, self::rows( $pages ) );

        return array(
            'kpis'     => self::totals( $p ),
            'trend'    => $series,
            'channels' => self::top( $p, 'sessionDefaultChannelGroup' ),
            'sources'  => self::top( $p, 'sessionSourceMedium', 'sessions', 10 ),
            'devices'  => self::top( $p, 'deviceCategory' ),
            'countries' => self::top( $p, 'country', 'sessions', 8 ),
            'pages'    => $pageRows,
            'error'    => $trend['error'] ?? null,
        );
    }

    /** Event counts (for features / funnel), optionally broken down by a custom dimension. */
    public static function events( $p, $names = null ) {
        if ( ! self::configured() ) return null;
        $res = self::ga( array( 'dateRanges' => self::ranges( $p ), 'dimensions' => array( array( 'name' => 'eventName' ) ), 'metrics' => array( array( 'name' => 'eventCount' ), array( 'name' => 'totalUsers' ) ), 'limit' => 100 ) );
        if ( ! empty( $res['error'] ) ) return array( 'error' => $res['error'] );
        $out = array();
        foreach ( self::rows( $res ) as $r ) {
            if ( $names === null || in_array( $r['d'][0], $names, true ) ) $out[ $r['d'][0] ] = array( 'count' => $r['m'][0], 'users' => $r['m'][1] );
        }

        return $out;
    }

    public static function funnel( $p ) {
        if ( ! self::configured() ) return null;
        $steps = array( 'view_promotion' => 'Saw a PRO offer (banner or sheet)', 'select_promotion' => 'Tapped a PRO button', 'begin_checkout' => 'Tapped card or PayPal', 'add_payment_info' => 'Submitted payment details', 'purchase' => 'Paid' );
        $ev = self::events( $p, array_keys( $steps ) );
        if ( ! empty( $ev['error'] ) ) return array( 'error' => $ev['error'] );
        $out = array();
        foreach ( $steps as $k => $label ) $out[] = array( 'step' => $label, 'users' => (int) ( $ev[ $k ]['users'] ?? 0 ), 'count' => (int) ( $ev[ $k ]['count'] ?? 0 ) );

        $slots = self::top( $p, 'customEvent:creative_slot', 'eventCount', 10, array( 'dimensionFilter' => array( 'filter' => array( 'fieldName' => 'eventName', 'stringFilter' => array( 'value' => 'view_promotion' ) ) ) ) );

        return array( 'steps' => $out, 'slots' => empty( $slots['error'] ) ? $slots : array() );
    }

    public static function realtime() {
        if ( ! self::configured() ) return null;
        $tot = self::ga( array( 'metrics' => array( array( 'name' => 'activeUsers' ) ) ), true );
        if ( ! empty( $tot['error'] ) ) return array( 'error' => $tot['error'] );
        $active = (int) ( self::rows( $tot )[0]['m'][0] ?? 0 );
        $by = function ( $dim ) {
            $res = self::ga( array( 'dimensions' => array( array( 'name' => $dim ) ), 'metrics' => array( array( 'name' => 'activeUsers' ) ), 'limit' => 6 ), true );
            return array_map( function ( $r ) { return array( 'name' => $r['d'][0], 'value' => $r['m'][0] ); }, self::rows( $res ) );
        };

        return array( 'active' => $active, 'pages' => $by( 'unifiedScreenName' ), 'devices' => $by( 'deviceCategory' ), 'countries' => $by( 'country' ) );
    }

    /* ------------------------------------------------------------------ Search Console */

    protected static function gsc( array $body ) {
        $site = defined( 'MM_GSC_SITE' ) && MM_GSC_SITE ? MM_GSC_SITE : 'sc-domain:manitomanita.com';

        return self::call( 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query', $body, 6 * HOUR_IN_SECONDS );
    }

    public static function seo( $p ) {
        if ( ! self::configured() ) return null;
        // Search Console data ends ~2-3 days ago: shift the range so it isn't empty at the end.
        $end = min( $p[1], wp_date( 'Y-m-d', strtotime( '-3 days' ) ) );
        $start = min( $p[0], $end );
        $prevEnd = wp_date( 'Y-m-d', strtotime( $start . ' -1 day' ) );
        $prevStart = wp_date( 'Y-m-d', strtotime( $prevEnd . ' -' . max( 0, $p[4] - 1 ) . ' days' ) );

        $tot = function ( $a, $b ) {
            $r = self::gsc( array( 'startDate' => $a, 'endDate' => $b, 'dataState' => 'all' ) );
            $row = $r['rows'][0] ?? array();
            return empty( $r['error'] ) ? array( 'clicks' => (float) ( $row['clicks'] ?? 0 ), 'impressions' => (float) ( $row['impressions'] ?? 0 ), 'ctr' => round( (float) ( $row['ctr'] ?? 0 ) * 100, 1 ), 'position' => round( (float) ( $row['position'] ?? 0 ), 1 ) ) : array( 'error' => $r['error'] );
        };
        $now = $tot( $start, $end );
        if ( ! empty( $now['error'] ) ) return array( 'error' => $now['error'] );
        $was = $tot( $prevStart, $prevEnd );

        $list = function ( $dim, $limit ) use ( $start, $end ) {
            $r = self::gsc( array( 'startDate' => $start, 'endDate' => $end, 'dimensions' => array( $dim ), 'rowLimit' => $limit, 'dataState' => 'all' ) );
            return array_map( function ( $x ) { return array( 'key' => $x['keys'][0], 'clicks' => (int) $x['clicks'], 'impressions' => (int) $x['impressions'], 'ctr' => round( $x['ctr'] * 100, 1 ), 'position' => round( $x['position'], 1 ) ); }, (array) ( $r['rows'] ?? array() ) );
        };
        $trendRes = self::gsc( array( 'startDate' => $start, 'endDate' => $end, 'dimensions' => array( 'date' ), 'rowLimit' => 400, 'dataState' => 'all' ) );
        $trend = array( 'labels' => array(), 'clicks' => array(), 'impressions' => array() );
        foreach ( (array) ( $trendRes['rows'] ?? array() ) as $x ) { $trend['labels'][] = $x['keys'][0]; $trend['clicks'][] = (int) $x['clicks']; $trend['impressions'][] = (int) $x['impressions']; }

        $kpis = array();
        foreach ( array( 'clicks', 'impressions', 'ctr', 'position' ) as $m ) $kpis[ $m ] = array( 'value' => $now[ $m ], 'prev' => $was[ $m ] ?? 0, 'change' => Insights::change( $now[ $m ], $was[ $m ] ?? 0 ) );

        return array( 'range' => array( $start, $end ), 'kpis' => $kpis, 'trend' => $trend, 'queries' => $list( 'query', 25 ), 'pages' => $list( 'page', 20 ), 'countries' => $list( 'country', 8 ), 'devices' => $list( 'device', 3 ) );
    }
}
