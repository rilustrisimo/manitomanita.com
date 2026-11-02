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
 *   define( 'GA4_MEASUREMENT_ID', 'G-...' ); // optional: server-side GA4 "purchase" events
 *   define( 'GA4_API_SECRET', '...' );
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

    protected static $hooked  = false;
    protected static $tracked = false; // GA4 purchase sent from the server for this request

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
        var item = <?php echo wp_json_encode( array( 'item_id' => $product, 'item_name' => self::$products[ $product ]['label'], 'price' => (float) self::$products[ $product ]['amount'], 'quantity' => 1 ) ); ?>;

        function track(name, params){
            if (window.Theme && Theme.track) Theme.track(name, params);
        }

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
                track('begin_checkout', { currency: 'PHP', value: item.price, items: [item] });
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
                        // Sent from the browser only when the server couldn't (no GA4 API secret configured).
                        if(!resp.data || !resp.data.tracked) track('purchase', { transaction_id: data.orderID, currency: 'PHP', value: item.price, items: [item] });
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

        wp_send_json_success( array( 'tracked' => self::$tracked ) );
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

        self::$tracked = self::ga4Purchase( $orderId, $product, (float) $amount['value'] );
        if ( $product === 'pro' ) self::emailModerator( $gid );

        return true;
    }

    /**
     * GA4 Measurement Protocol "purchase" (counted even when the browser blocks analytics).
     */
    protected static function ga4Purchase( $orderId, $product, $value ) {
        if ( ! defined( 'GA4_MEASUREMENT_ID' ) || ! defined( 'GA4_API_SECRET' ) || ! GA4_MEASUREMENT_ID || ! GA4_API_SECRET ) return false;

        // The visitor's GA client id from the _ga cookie ("GA1.1.123.456" -> "123.456").
        $clientId = preg_match( '/^GA\d\.\d\.(\d+\.\d+)$/', (string) ( $_COOKIE['_ga'] ?? '' ), $m ) ? $m[1] : wp_rand() . '.' . time();

        $r = wp_remote_post( 'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode( GA4_MEASUREMENT_ID ) . '&api_secret=' . rawurlencode( GA4_API_SECRET ), array(
            'timeout' => 5,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'client_id' => $clientId,
                'events'    => array( array(
                    'name'   => 'purchase',
                    'params' => array(
                        'transaction_id' => $orderId,
                        'currency'       => self::CURRENCY,
                        'value'          => $value,
                        'items'          => array( array( 'item_id' => $product, 'item_name' => self::$products[ $product ]['label'], 'price' => $value, 'quantity' => 1 ) ),
                    ),
                ) ),
            ) ),
        ) );

        return ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) < 300;
    }

    /**
     * Tells the moderator their group is now PRO (it may have been a gift from a member).
     */
    protected static function emailModerator( $gid ) {
        $to = (string) get_field( 'your_email', $gid );
        if ( ! is_email( $to ) ) return;

        $name = (string) get_field( 'group_name', $gid );
        $link = get_permalink( 23 ) . '?gid=' . $gid . '&pw=' . rawurlencode( (string) get_field( 'group_password', $gid ) );

        Emails::send( $to, $name . ' is now PRO', array(
            'preheader' => 'Ads are off for everyone, and your organizer tools are unlocked.',
            'eyebrow'   => 'Manito Manita PRO',
            'title'     => $name . ' is now PRO',
            'body'      => array(
                '<p>Your group has been upgraded to <strong>Manito Manita PRO</strong>. Ads are removed for everyone, and as the organizer you can now:</p>',
                '<ul><li>Add <strong>exclusions</strong> so couples or family don&rsquo;t get each other</li><li>Run <strong>Weekly Drops</strong>: a themed mini-gift each week, with a reveal at the end</li><li><strong>Unshuffle</strong> to let late joiners in (3 unshuffles included)</li><li>Send reminders, schedule an auto-shuffle and track gift status</li><li>Remove or edit members, see matches and export the group</li></ul>',
                '<p>Open your group, tap <strong>PRO Actions</strong> and enter your organizer password.</p>',
            ),
            'card'      => Emails::groupCard( $gid ),
            'button'    => array( 'Open PRO Actions', $link ),
            'footer'    => 'You&rsquo;re getting this because ' . esc_html( $name ) . ' was upgraded on Manito Manita.',
        ) );
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
