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
        'plus'    => array( 'amount' => '99.00',  'label' => 'Manito Manita Member+' ), // one member's own card (MemberPlus)
    );

    protected static $hooked  = false;
    protected static $tracked = false; // GA4 purchase sent from the server for this request

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        foreach ( array( 'mm_paypal_create' => 'ajaxCreate', 'mm_paypal_capture' => 'ajaxCapture', 'mm_pay_step' => 'ajaxStep' ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
        }
        add_action( 'wp_head', array( __CLASS__, 'preconnect' ), 2 );
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

        // Card first (most Filipino buyers don't have PayPal), then PayPal. Both are PayPal checkouts.
        echo '<div id="paypal-card-container" class="mm-pay__card"></div>';
        echo '<div class="mm-pay__or"><span>or</span></div>';
        echo '<div id="paypal-button-container" class="mm-pay__paypal"></div>';
        // en_PH: Philippine defaults in the card form (country, mobile prefix); unused funding options off = less to load.
        echo '<script src="https://www.paypal.com/sdk/js?client-id=' . esc_attr( PAYPAL_CLIENT_ID ) . '&currency=' . self::CURRENCY . '&locale=en_PH&disable-funding=paylater,venmo"></script>';
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

        // Site-side funnel log (Insights), so the steps still count when GA4 is blocked. Once per step per page.
        var stepsSent = {};
        function step(name, detail){
            if (stepsSent[name + detail]) return;
            stepsSent[name + detail] = 1;
            var f = new FormData();
            f.append('action', 'mm_pay_step'); f.append('nonce', nonce); f.append('gid', gid); f.append('product', product); f.append('step', name); f.append('detail', detail || '');
            if (navigator.sendBeacon) navigator.sendBeacon(ajaxUrl, f); else fetch(ajaxUrl, { method: 'POST', body: f, credentials: 'same-origin' });
        }
        // The PRO sheet (#make-pro) holds these buttons: log each time it opens, with the reason that opened it.
        var sheet = document.getElementById('make-pro');
        if (sheet && product === 'pro' && window.MutationObserver) {
            var open = false;
            new MutationObserver(function(){
                var now = sheet.style.display === 'flex';
                if (now && !open) { stepsSent = {}; step('sheet', (window.jQuery && jQuery(sheet).data('mmReason')) || 'header'); }
                open = now;
            }).observe(sheet, { attributes: true, attributeFilter: ['style'] });
        }

        function fail(message){
            document.getElementById('loader-overlay-paypal').style.display = 'none';
            alert(message || 'There was an issue processing your payment. Please try again.');
        }

        // Instant feedback while PayPal loads its secure card form (it's PayPal's own page, embedded).
        function cardLoading(){
            var box = document.getElementById('paypal-card-container');
            if (!box || document.getElementById('mm-card-wait')) return;
            var start = box.offsetHeight;
            var note = document.createElement('p');
            note.id = 'mm-card-wait';
            note.className = 'mm-pay__wait';
            note.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening secure card form\u2026';
            box.parentNode.insertBefore(note, box.nextSibling);
            var done = function(){ if (note.parentNode) note.parentNode.removeChild(note); };
            if (window.ResizeObserver) {
                var ro = new ResizeObserver(function(){ if (box.offsetHeight > start + 60) { done(); ro.disconnect(); } });
                ro.observe(box);
            }
            setTimeout(done, 6000);
            setTimeout(function(){ box.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
        }

        var cfg = {
            // Funnel order: begin_checkout when card/PayPal is tapped, add_payment_info when the details are
            // submitted (PayPal asks for the order only then), purchase when captured.
            onClick: function(data){
                var src = data ? data.fundingSource : '';
                if (src === 'card') cardLoading();
                track('begin_checkout', { currency: 'PHP', value: item.price, payment_type: src, items: [item] });
                step('checkout', src);
            },
            createOrder: function(data){
                track('add_payment_info', { currency: 'PHP', value: item.price, payment_type: data && data.paymentSource ? data.paymentSource : '', items: [item] });
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
        };

        function buttons(source, style, el){
            var b = paypal.Buttons(Object.assign({ fundingSource: source, style: style }, cfg));
            if (b.isEligible()) { b.render(el); return true; }
            return false;
        }

        var card = buttons(paypal.FUNDING.CARD, { layout: 'vertical', shape: 'rect', height: 48 }, '#paypal-card-container');
        buttons(paypal.FUNDING.PAYPAL, { layout: 'vertical', shape: 'rect', color: 'gold', label: 'paypal', height: 44 }, '#paypal-button-container');
        if (!card) { var or = document.querySelector('.mm-pay__or'); if (or) or.style.display = 'none'; }
    })();
</script>
        <?php
    }

    /** Open the connection to PayPal early on group dashboards, so the buttons and card form load faster. */
    public static function preconnect() {
        if ( ! is_page( 23 ) || ! self::enabled() ) return;
        foreach ( array( 'https://www.paypal.com', 'https://www.paypalobjects.com', 'https://c.paypal.com' ) as $h ) {
            echo '<link rel="preconnect" href="' . esc_url( $h ) . '" crossorigin>' . "\n";
        }
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

        // Member+: for one member's card; the order carries the member id (custom_id "gid:plus:uid").
        $uid = 0;
        if ( $product === 'plus' ) {
            $uid = absint( $_POST['uid'] ?? 0 );
            $why = class_exists( 'MemberPlus' ) ? MemberPlus::cantBuy( $uid, $gid ) : 'Member+ is not available right now.';
            if ( $why !== '' ) wp_send_json_error( $why );
        }

        $p = self::$products[ $product ];
        $order = self::request( 'POST', 'v2/checkout/orders', array(
            'intent'         => 'CAPTURE',
            'purchase_units' => array( array(
                'custom_id'   => $gid . ':' . $product . ( $uid ? ':' . $uid : '' ),
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
        if ( class_exists( 'Insights' ) ) Insights::log( 'pro_order', $gid, $uid, (float) $p['amount'], $product );

        wp_send_json_success( array( 'id' => $order['id'] ) );
    }

    /**
     * Funnel beacon from the buttons: 'sheet' (PRO sheet opened, detail = reason) or 'checkout' (card or
     * PayPal tapped, detail = funding source). Logged as pro_sheet / pro_checkout; meta "product|detail".
     */
    public static function ajaxStep() {
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_paypal' ) ) wp_die( '', '', array( 'response' => 403 ) );
        $step    = sanitize_key( (string) ( $_POST['step'] ?? '' ) );
        $product = sanitize_key( (string) ( $_POST['product'] ?? '' ) );
        $gid     = absint( $_POST['gid'] ?? 0 );
        if ( in_array( $step, array( 'sheet', 'checkout' ), true ) && isset( self::$products[ $product ] ) && get_post_type( $gid ) === 'groups' && class_exists( 'Insights' ) ) {
            Insights::log( 'pro_' . $step, $gid, 0, 0, $product . '|' . substr( sanitize_key( (string) ( $_POST['detail'] ?? '' ) ), 0, 40 ) );
        }
        wp_die( '', '', array( 'response' => 204 ) );
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
            do_action( 'mm_alert', 'payment', 'PRO payment not applied: ' . $result, array( 'order' => $orderId, 'group' => (int) ( $_POST['gid'] ?? 0 ) ) );
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

        list( $gid, $product, $uid ) = array_pad( explode( ':', $custom, 3 ), 3, '' );
        $gid = absint( $gid );
        $uid = absint( $uid );

        if ( ! isset( self::$products[ $product ] ) || get_post_type( $gid ) !== 'groups' ) return 'unknown item ' . $custom;

        $amount = $capture['amount'] ?? array();
        if ( ( $amount['currency_code'] ?? '' ) !== self::CURRENCY || (float) ( $amount['value'] ?? 0 ) < (float) self::$products[ $product ]['amount'] ) return 'amount mismatch';

        if ( $product === 'plus' ) {
            if ( ! class_exists( 'MemberPlus' ) ) return 'Member+ unavailable';
            $ok = MemberPlus::unlock( $uid, $gid, $orderId );
            if ( $ok !== true ) return $ok;
            self::$tracked = self::ga4Purchase( $orderId, $product, (float) $amount['value'] );
            if ( class_exists( 'Insights' ) ) Insights::log( 'purchase', $gid, $uid, (float) $amount['value'], $product );

            return true;
        }

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
        if ( class_exists( 'Insights' ) ) Insights::log( 'purchase', $gid, 0, (float) $amount['value'], $product );
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
                        'items'          => array( array( 'item_id' => $product === 'plus' ? 'member_plus' : $product, 'item_name' => self::$products[ $product ]['label'], 'price' => $value, 'quantity' => 1 ) ),
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
                '<ul><li>Add <strong>exclusions</strong> so couples or family don&rsquo;t get each other</li><li>Run <strong>Weekly Drops</strong>: a themed mini-gift each week, with a reveal at the end</li><li>Everyone can ask their match questions <strong>anonymously</strong> (sizes, likes, allergies)</li><li>Add a <strong>co-organizer</strong> and pick <strong>colors</strong> for your group page</li><li><strong>Unshuffle</strong> to let late joiners in (3 unshuffles included)</li><li>Send reminders, schedule an auto-shuffle and track gift status</li><li>Remove or edit members, see matches and export the group</li></ul>',
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
            do_action( 'mm_alert', 'paypal', 'PayPal sign-in failed for PRO payments (check the client ID and secret in wp-config.php)' );
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
