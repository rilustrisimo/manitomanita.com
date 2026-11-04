<?php
/**
 * AdsPayPal Class. PayPal subscriptions for ad placements (weekly / monthly), live or sandbox.
 *
 * Live uses PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET / PAYPAL_WEBHOOK_ID (the PRO checkout's app).
 * Sandbox (admins' test checkout only) uses PAYPAL_SANDBOX_CLIENT_ID / _SECRET / _WEBHOOK_ID,
 * so ad checkout can be tested without touching live PRO payments.
 *
 * Webhook: /wp-json/mm/v1/paypal-webhook (signature verified with PayPal before anything changes).
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
 * Class AdsPayPal
 */
class AdsPayPal {
    const PLANS_OPT   = 'mm_ads_paypal_plans';
    const PRODUCT_OPT = 'mm_ads_paypal_product';

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    /* ------------------------------------------------------------------ Modes and API */

    public static function enabled( $mode = 'live' ) {
        return self::clientId( $mode ) !== '' && self::secret( $mode ) !== '';
    }

    public static function clientId( $mode ) {
        $c = $mode === 'sandbox' ? 'PAYPAL_SANDBOX_CLIENT_ID' : 'PAYPAL_CLIENT_ID';
        return defined( $c ) ? (string) constant( $c ) : '';
    }

    protected static function secret( $mode ) {
        $c = $mode === 'sandbox' ? 'PAYPAL_SANDBOX_CLIENT_SECRET' : 'PAYPAL_CLIENT_SECRET';
        return defined( $c ) ? (string) constant( $c ) : '';
    }

    protected static function webhookId( $mode ) {
        $c = $mode === 'sandbox' ? 'PAYPAL_SANDBOX_WEBHOOK_ID' : 'PAYPAL_WEBHOOK_ID';
        return defined( $c ) ? (string) constant( $c ) : '';
    }

    protected static function base( $mode ) {
        // Live ad checkout follows the site's PAYPAL_MODE, so a sandbox site never charges real cards.
        $sandbox = $mode === 'sandbox' || ( defined( 'PAYPAL_MODE' ) && PAYPAL_MODE === 'sandbox' );
        return $sandbox ? 'https://api-m.sandbox.paypal.com/' : 'https://api-m.paypal.com/';
    }

    protected static function token( $mode ) {
        $key = 'mm_ads_pp_token_' . $mode;
        $t = get_transient( $key );
        if ( $t ) return $t;

        $r = wp_remote_post( self::base( $mode ) . 'v1/oauth2/token', array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Basic ' . base64_encode( self::clientId( $mode ) . ':' . self::secret( $mode ) ) ),
            'body'    => array( 'grant_type' => 'client_credentials' ),
        ) );
        $b = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
        if ( empty( $b['access_token'] ) ) {
            error_log( 'AdsPayPal: authentication failed (' . $mode . ')' );
            return '';
        }
        set_transient( $key, $b['access_token'], max( 60, (int) ( $b['expires_in'] ?? 3600 ) - 300 ) );

        return $b['access_token'];
    }

    /** API call. Returns [code, body]. */
    public static function request( $mode, $method, $path, $body = null ) {
        $token = self::token( $mode );
        if ( ! $token ) return array( 0, array() );

        $args = array( 'method' => $method, 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Prefer' => 'return=representation' ) );
        if ( $body !== null ) $args['body'] = wp_json_encode( $body );
        $r = wp_remote_request( self::base( $mode ) . $path, $args );
        if ( is_wp_error( $r ) ) {
            error_log( 'AdsPayPal: ' . $path . ' ' . $r->get_error_message() );
            return array( 0, array() );
        }
        $code = (int) wp_remote_retrieve_response_code( $r );
        if ( $code === 401 ) delete_transient( 'mm_ads_pp_token_' . $mode );

        return array( $code, (array) json_decode( wp_remote_retrieve_body( $r ), true ) );
    }

    /* ------------------------------------------------------------------ Catalog */

    protected static function product( $mode ) {
        $all = (array) get_option( self::PRODUCT_OPT, array() );
        if ( ! empty( $all[ $mode ] ) ) return $all[ $mode ];

        list( $code, $b ) = self::request( $mode, 'POST', 'v1/catalogs/products', array(
            'name'        => 'Manito Manita sponsorship',
            'description' => 'Sponsored placement on manitomanita.com',
            'type'        => 'SERVICE',
            'category'    => 'ADVERTISING',
            'home_url'    => home_url( '/' ),
        ) );
        if ( empty( $b['id'] ) ) {
            error_log( 'AdsPayPal: product create failed ' . $code );
            return '';
        }
        $all[ $mode ] = $b['id'];
        update_option( self::PRODUCT_OPT, $all, false );

        return $b['id'];
    }

    /**
     * Plan id for placement x cadence x price (created once, cached). A price change makes a new plan,
     * so existing subscribers keep the price they signed up at.
     */
    public static function plan( $mode, $placement, $cadence, $price ) {
        $price = (int) $price;
        if ( $price <= 0 ) return '';
        $key = $mode . '|' . $placement . '|' . $cadence . '|' . $price;
        $plans = (array) get_option( self::PLANS_OPT, array() );
        if ( ! empty( $plans[ $key ] ) ) return $plans[ $key ];

        $product = self::product( $mode );
        if ( ! $product ) return '';
        $label = Ads::placements()[ $placement ]['label'] ?? $placement;

        list( $code, $b ) = self::request( $mode, 'POST', 'v1/billing/plans', array(
            'product_id'          => $product,
            'name'                => $label . ' (' . $cadence . ', PHP ' . $price . ')',
            'status'              => 'ACTIVE',
            'billing_cycles'      => array( array(
                'frequency'      => array( 'interval_unit' => $cadence === 'monthly' ? 'MONTH' : 'WEEK', 'interval_count' => 1 ),
                'tenure_type'    => 'REGULAR',
                'sequence'       => 1,
                'total_cycles'   => 0,
                'pricing_scheme' => array( 'fixed_price' => array( 'value' => number_format( $price, 2, '.', '' ), 'currency_code' => 'PHP' ) ),
            ) ),
            'payment_preferences' => array( 'auto_bill_outstanding' => false, 'payment_failure_threshold' => 1 ),
        ) );
        if ( empty( $b['id'] ) ) {
            error_log( 'AdsPayPal: plan create failed ' . $code . ' ' . wp_json_encode( $b ) );
            return '';
        }
        $plans[ $key ] = $b['id'];
        update_option( self::PLANS_OPT, $plans, false );

        return $b['id'];
    }

    /* ------------------------------------------------------------------ Subscriptions */

    public static function subscription( $mode, $id ) {
        list( $code, $b ) = self::request( $mode, 'GET', 'v1/billing/subscriptions/' . rawurlencode( $id ) );
        return $code === 200 ? $b : array();
    }

    /** End of the paid period: the next billing time, else start + one cycle. */
    public static function periodEnd( array $sub, $cadence ) {
        $next = strtotime( (string) ( $sub['billing_info']['next_billing_time'] ?? '' ) );
        if ( $next && $next > time() ) return $next;

        return time() + ( $cadence === 'monthly' ? 30 : 7 ) * DAY_IN_SECONDS;
    }

    /** Completed / refunded payments of a subscription since $since (unix). */
    public static function transactions( $mode, $id, $since ) {
        $q = '?start_time=' . rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z', max( 0, (int) $since ) ) ) . '&end_time=' . rawurlencode( gmdate( 'Y-m-d\TH:i:s\Z', time() + HOUR_IN_SECONDS ) );
        list( $code, $b ) = self::request( $mode, 'GET', 'v1/billing/subscriptions/' . rawurlencode( $id ) . '/transactions' . $q );

        return $code === 200 ? (array) ( $b['transactions'] ?? array() ) : array();
    }

    /** Subscription details for the admin panel, cached 5 minutes. */
    public static function subscriptionCached( $mode, $id ) {
        $key = 'mm_ads_pp_sub_' . md5( $mode . $id );
        $s = get_transient( $key );
        if ( is_array( $s ) ) return $s;
        $s = self::subscription( $mode, $id );
        set_transient( $key, $s, 5 * MINUTE_IN_SECONDS );

        return $s;
    }

    public static function cancel( $mode, $id, $reason ) {
        list( $code ) = self::request( $mode, 'POST', 'v1/billing/subscriptions/' . rawurlencode( $id ) . '/cancel', array( 'reason' => mb_substr( $reason, 0, 120 ) ) );
        return $code === 204 || $code === 422; // 422: already cancelled
    }

    /** Switch plan (weekly <-> monthly). Returns the approval link the subscriber must open, or ''. */
    public static function revise( $mode, $id, $planId, $returnUrl ) {
        list( $code, $b ) = self::request( $mode, 'POST', 'v1/billing/subscriptions/' . rawurlencode( $id ) . '/revise', array(
            'plan_id'             => $planId,
            'application_context' => array( 'return_url' => $returnUrl, 'cancel_url' => $returnUrl, 'brand_name' => 'Manito Manita', 'user_action' => 'CONTINUE' ),
        ) );
        foreach ( (array) ( $b['links'] ?? array() ) as $l ) if ( ( $l['rel'] ?? '' ) === 'approve' ) return (string) $l['href'];

        return '';
    }

    /** Full refund, or a partial one when $amount (PHP) is given. */
    public static function refundSale( $mode, $saleId, $amount = null ) {
        $body = $amount === null ? new stdClass() : array( 'amount' => array( 'total' => number_format( (float) $amount, 2, '.', '' ), 'currency' => 'PHP' ) );
        list( $code ) = self::request( $mode, 'POST', 'v1/payments/sale/' . rawurlencode( $saleId ) . '/refund', $body );
        return $code >= 200 && $code < 300;
    }

    /* ------------------------------------------------------------------ Webhook */

    public static function routes() {
        register_rest_route( 'mm/v1', '/paypal-webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'webhook' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /** Which mode signed this event (live, then sandbox), or '' when neither verifies. */
    protected static function verify( WP_REST_Request $req, array $event ) {
        foreach ( array( 'live', 'sandbox' ) as $mode ) {
            if ( ! self::enabled( $mode ) || self::webhookId( $mode ) === '' ) continue;
            list( $code, $b ) = self::request( $mode, 'POST', 'v1/notifications/verify-webhook-signature', array(
                'auth_algo'         => (string) $req->get_header( 'paypal-auth-algo' ),
                'cert_url'          => (string) $req->get_header( 'paypal-cert-url' ),
                'transmission_id'   => (string) $req->get_header( 'paypal-transmission-id' ),
                'transmission_sig'  => (string) $req->get_header( 'paypal-transmission-sig' ),
                'transmission_time' => (string) $req->get_header( 'paypal-transmission-time' ),
                'webhook_id'        => self::webhookId( $mode ),
                'webhook_event'     => $event,
            ) );
            if ( $code === 200 && ( $b['verification_status'] ?? '' ) === 'SUCCESS' ) return $mode;
        }

        return '';
    }

    public static function webhook( WP_REST_Request $req ) {
        $event = json_decode( (string) $req->get_body(), true );
        if ( ! is_array( $event ) || empty( $event['event_type'] ) ) return new WP_REST_Response( array( 'ok' => false ), 400 );

        $mode = self::verify( $req, $event );
        if ( $mode === '' ) {
            // Only calls that look like PayPal's (signed headers) are worth an alert; random probes are ignored.
            if ( $req->get_header( 'paypal-transmission-id' ) ) do_action( 'mm_alert', 'webhook', 'PayPal webhook could not be verified (check PAYPAL_WEBHOOK_ID)', array( 'event' => (string) $event['event_type'] ) );
            return new WP_REST_Response( array( 'ok' => false ), 401 );
        }

        // PayPal retries; handle each event once.
        $seen = 'mm_ads_pp_evt_' . md5( (string) ( $event['id'] ?? '' ) );
        if ( get_transient( $seen ) ) return new WP_REST_Response( array( 'ok' => true ), 200 );
        set_transient( $seen, 1, 3 * DAY_IN_SECONDS );

        if ( class_exists( 'AdsPortal' ) ) AdsPortal::onEvent( $mode, (string) $event['event_type'], (array) ( $event['resource'] ?? array() ) );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }
}

AdsPayPal::init();
