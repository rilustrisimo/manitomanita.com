<?php
/**
 * * Payments Class. PayPal checkout for PRO and unshuffle credits, verified on the server.
 *
 * The order is created here (amount and group fixed by the server) and captured here; the group
 * is only upgraded after PayPal confirms the capture is COMPLETED for the right amount. Each
 * order is applied once.
 *
 * Config (wp-config.php):
 *   define( 'PAYPAL_CLIENT_ID', '...' );
 *   define( 'PAYPAL_CLIENT_SECRET', '...' );
 *   define( 'PAYPAL_MODE', 'live' ); // or 'sandbox'
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
 * Class Payments
 */
class Payments {
    const CURRENCY  = 'PHP';
    const PAID_META = '_mm_paid_orders'; // order ids already applied to a group

    protected static $products = array(
        'pro'     => array( 'amount' => '199.00', 'label' => 'Manito Manita PRO' ),
        'credits' => array( 'amount' => '99.00',  'label' => 'Manito Manita PRO - 3 unshuffle credits' ),
    );

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        foreach ( array( 'mm_paypal_create' => 'ajaxCreate', 'mm_paypal_capture' => 'ajaxCapture' ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
        }
    }

    public static function enabled() {
        return defined( 'PAYPAL_CLIENT_ID' ) && PAYPAL_CLIENT_ID !== '' && defined( 'PAYPAL_CLIENT_SECRET' ) && PAYPAL_CLIENT_SECRET !== '';
    }

    protected static function api() {
        return ( defined( 'PAYPAL_MODE' ) && PAYPAL_MODE === 'sandbox' ) ? 'https://api-m.sandbox.paypal.com/' : 'https://api-m.paypal.com/';
    }

    /* ------------------------------------------------------------ Buttons */

    /**
     * PayPal buttons for a product ('pro' or 'credits') on the current group.
     */
    public static function buttons( $product ) {
        if ( ! self::enabled() ) {
            echo '<p class="mm-pay-off">Payments are temporarily unavailable. Please try again later.</p>';
            return;
        }

        $group = new Groups();
        $gid = (int) $group->getGroupId();

        echo '<div id="paypal-button-container"></div>';
        echo '<script src="https://www.paypal.com/sdk/js?client-id=' . esc_attr( PAYPAL_CLIENT_ID ) . '&currency=' . self::CURRENCY . '"></script>';
        ?>
<script>
    (function(){
        var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
        var gid = <?php echo wp_json_encode( (string) $gid ); ?>;
        var product = <?php echo wp_json_encode( $product ); ?>;
        var nonce = <?php echo wp_json_encode( wp_create_nonce( 'mm_paypal' ) ); ?>;

        function post(data){
            data.nonce = nonce;
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data).toString()
            }).then(function(r){ return r.json(); });
        }

        function fail(message){
            document.getElementById('loader-overlay-paypal').style.display = 'none';
            alert(message || 'There was an issue processing your payment. Please try again.');
        }

        paypal.Buttons({
            locale: 'en_PH',
            createOrder: function(){
                return post({ action: 'mm_paypal_create', gid: gid, product: product }).then(function(resp){
                    if(!resp.success) throw new Error(resp.data || 'Could not start the payment.');
                    return resp.data.id;
                });
            },
            onApprove: function(data){
                document.getElementById('loader-overlay-paypal').style.display = 'flex';
                jQuery('.pop-container').hide();

                return post({ action: 'mm_paypal_capture', order: data.orderID }).then(function(resp){
                    if(resp.success){
                        jQuery('#reload').submit(); // reload the dashboard with PRO / the new credits
                    }else{
                        fail(resp.data);
                    }
                }).catch(function(){
                    fail('Network error. If you were charged, please contact us with your PayPal receipt.');
                });
            },
            onError: function(err){
                fail(err && err.message ? err.message : '');
            }
        }).render('#paypal-button-container');
    })();
</script>
        <?php
    }

    /* --------------------------------------------------------------- AJAX */

    public static function ajaxCreate() {
        check_ajax_referer( 'mm_paypal', 'nonce' );

        $gid     = absint( $_POST['gid'] ?? 0 );
        $product = sanitize_key( (string) ( $_POST['product'] ?? '' ) );

        if ( ! self::enabled() || ! isset( self::$products[ $product ] ) || get_post_type( $gid ) !== 'groups' ) wp_send_json_error( 'Could not start the payment.' );

        $pro = (bool) get_field( 'pro', $gid );
        if ( $product === 'pro' && $pro ) wp_send_json_error( 'This group is already PRO.' );
        if ( $product === 'credits' && ! $pro ) wp_send_json_error( 'Credits are for PRO groups.' );

        $p = self::$products[ $product ];
        $order = self::request( 'POST', 'v2/checkout/orders', array(
            'intent'         => 'CAPTURE',
            'purchase_units' => array( array(
                'custom_id'   => $gid . ':' . $product,
                'description' => $p['label'],
                'amount'      => array( 'currency_code' => self::CURRENCY, 'value' => $p['amount'] ),
            ) ),
            'application_context' => array(
                'brand_name'          => 'Manito Manita',
                'locale'              => 'en-PH',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
            ),
        ) );

        if ( empty( $order['id'] ) ) wp_send_json_error( 'Could not start the payment. Please try again.' );

        wp_send_json_success( array( 'id' => $order['id'] ) );
    }

    public static function ajaxCapture() {
        check_ajax_referer( 'mm_paypal', 'nonce' );

        $orderId = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) ( $_POST['order'] ?? '' ) ) );

        if ( ! self::enabled() || $orderId === '' ) wp_send_json_error( 'Missing payment details.' );

        $order = self::request( 'POST', 'v2/checkout/orders/' . $orderId . '/capture', new stdClass() );

        // Already captured (e.g. a retried request): read the order instead.
        if ( empty( $order['status'] ) || $order['status'] !== 'COMPLETED' ) {
            $order = self::request( 'GET', 'v2/checkout/orders/' . $orderId );
        }

        $result = self::apply( $orderId, $order );

        if ( $result !== true ) {
            error_log( 'Payments: order ' . $orderId . ' not applied (' . $result . ')' );
            wp_send_json_error( 'We could not confirm your payment. If you were charged, please contact us with your PayPal receipt.' );
        }

        wp_send_json_success();
    }

    /**
     * Upgrades the group for a COMPLETED order. Returns true, or a reason string.
     */
    protected static function apply( $orderId, $order ) {
        if ( ( $order['status'] ?? '' ) !== 'COMPLETED' ) return 'status ' . ( $order['status'] ?? 'unknown' );

        $unit    = $order['purchase_units'][0] ?? array();
        $capture = $unit['payments']['captures'][0] ?? array();
        $custom  = (string) ( $capture['custom_id'] ?? ( $unit['custom_id'] ?? '' ) );

        if ( ( $capture['status'] ?? '' ) !== 'COMPLETED' ) return 'capture ' . ( $capture['status'] ?? 'missing' );

        list( $gid, $product ) = array_pad( explode( ':', $custom, 2 ), 2, '' );
        $gid = absint( $gid );

        if ( ! isset( self::$products[ $product ] ) || get_post_type( $gid ) !== 'groups' ) return 'unknown item ' . $custom;

        $amount = $capture['amount'] ?? array();
        if ( ( $amount['currency_code'] ?? '' ) !== self::CURRENCY || (float) ( $amount['value'] ?? 0 ) < (float) self::$products[ $product ]['amount'] ) return 'amount mismatch';

        $paid = (array) get_post_meta( $gid, self::PAID_META, true );
        if ( in_array( $orderId, $paid, true ) ) return true; // already applied

        if ( $product === 'pro' ) {
            update_field( 'pro', true, $gid );
            if ( get_post_meta( $gid, 'unshuffle_credits', true ) === '' ) update_field( 'unshuffle_credits', 3, $gid );
        } else {
            update_field( 'unshuffle_credits', (int) get_field( 'unshuffle_credits', $gid ) + 3, $gid );
        }

        $paid[] = $orderId;
        update_post_meta( $gid, self::PAID_META, array_values( array_filter( $paid ) ) );

        $current = (string) get_field( 'order_id', $gid );
        update_field( 'order_id', $current !== '' ? $current . ',' . $orderId : $orderId, $gid );

        return true;
    }

    /* ------------------------------------------------------------ PayPal */

    protected static function token() {
        $token = get_transient( 'mm_paypal_token' );
        if ( $token ) return $token;

        $r = wp_remote_post( self::api() . 'v1/oauth2/token', array(
            'timeout' => 20,
            'headers' => array( 'Authorization' => 'Basic ' . base64_encode( PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET ) ),
            'body'    => array( 'grant_type' => 'client_credentials' ),
        ) );

        $body = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );

        if ( empty( $body['access_token'] ) ) {
            error_log( 'Payments: PayPal authentication failed' );
            return '';
        }

        set_transient( 'mm_paypal_token', $body['access_token'], max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 ) );

        return $body['access_token'];
    }

    protected static function request( $method, $path, $body = null ) {
        $token = self::token();
        if ( ! $token ) return array();

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
        );

        if ( $body !== null ) $args['body'] = wp_json_encode( $body );

        $r = wp_remote_request( self::api() . $path, $args );

        if ( is_wp_error( $r ) ) {
            error_log( 'Payments: ' . $path . ' ' . $r->get_error_message() );
            return array();
        }

        if ( (int) wp_remote_retrieve_response_code( $r ) === 401 ) delete_transient( 'mm_paypal_token' );

        return (array) json_decode( wp_remote_retrieve_body( $r ), true );
    }
}

Payments::init();
