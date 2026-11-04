<?php
/**
 * EmailDeliver Class. Keeps email deliverability healthy (Amazon SES):
 *  - Suppression list ({prefix}mm_email_suppress, one row per inbox, hashed): hard bounces and spam complaints
 *    (from SES via SNS) and manual blocks stop every non-essential email; promo opt-outs stop promos only.
 *  - Emails::send asks EmailDeliver::blocked() first; login codes, confirm links and password resets
 *    ('essential' => true) always go out.
 *  - Daily counters ({prefix}mm_email_stats): sent, failed, suppressed, bounce, complaint, per email kind.
 *  - /wp-json/mm/v1/ses-events: SNS endpoint (signature verified, subscription confirmed automatically).
 *  - Promo unsubscribe: /?mm_promo_unsub=<email b64>&s=<sig> (page with a button, or RFC 8058 one-click POST).
 *
 * AWS setup: SNS topic > HTTPS subscription to the endpoint above > SES identity "Feedback notifications":
 * Bounce and Complaint to that topic. The first confirmed topic is remembered (option mm_ses_topic) and
 * messages from any other topic are refused.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailDeliver {
    const DB_VERSION = 1;

    public static function init() {
        add_action( 'init', array( __CLASS__, 'install' ), 0 );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_action( 'wp_mail_failed', array( __CLASS__, 'mailFailed' ) );
        add_action( 'template_redirect', array( __CLASS__, 'promoUnsubscribe' ), 1 );
        add_action( 'wp_loaded', array( __CLASS__, 'policyOnce' ) );
    }

    /**
     * Once: adds the promo email lines to the Privacy Policy page (offers + unsubscribe, opens/clicks,
     * 90-day send logs, coded bounce list) and bumps its "last updated" date. Only inserts at known spots;
     * a part whose spot was edited away is skipped (and reported), the rest of the page is untouched.
     */
    public static function policyOnce() {
        if ( get_option( 'mm_policy_promo_v1' ) ) return;
        update_option( 'mm_policy_promo_v1', time(), false );
        $page = get_page_by_path( 'privacy-policy' );
        if ( ! $page ) return;
        $c = (string) $page->post_content;
        if ( strpos( $c, 'offers and news' ) !== false ) return; // already there
        $done = 0;

        $c = preg_replace( '~(<li>\s*To show ads to free groups\.?\s*</li>)~i', '$1' . "\n" . '<li>To send occasional offers and news about Manito Manita, at most one every few days. Each has an <strong>unsubscribe</strong> link that stops only these emails, not your group&rsquo;s emails like your match and reminders. We record whether an offer email was opened or its links were clicked, to see what&rsquo;s useful and to stop emailing addresses that no longer work.</li>', $c, 1, $n );
        $done += $n;
        $c = preg_replace( '~to send confirmation, match and reminder emails\.~i', 'to send confirmation, match, reminder and occasional offer emails, and to tell us which addresses bounce or report an email as spam.', $c, 1, $n );
        $done += $n;
        $c = preg_replace( '~(<h2[^>]*>\s*8\.)~i', '<p>Email send logs (which address got an offer email, and whether it was opened or clicked) keep the address for 90 days; after that only totals remain. If an address bounces or reports an email as spam, we keep a coded (hashed) copy of it so we never email it again. An email you leave for a registry gift reminder is deleted once the reminder is sent.</p>' . "\n" . '$1', $c, 1, $n );
        $done += $n;

        if ( $done ) {
            wp_update_post( array( 'ID' => $page->ID, 'post_content' => $c ) );
            if ( function_exists( 'update_field' ) ) update_field( 'mm_updated', wp_date( 'Ymd' ), $page->ID );
            do_action( 'litespeed_purge_post', $page->ID );
        }
        if ( $done < 3 ) do_action( 'mm_alert', 'policy', 'Privacy Policy: ' . ( 3 - $done ) . ' of 3 email lines could not be placed (the page was edited). Add them by hand.' );
    }

    public static function suppressTable() { global $wpdb; return $wpdb->prefix . 'mm_email_suppress'; }
    public static function statsTable()    { global $wpdb; return $wpdb->prefix . 'mm_email_stats'; }

    public static function install() {
        if ( (int) get_option( 'mm_email_deliver_db' ) >= self::DB_VERSION ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta( 'CREATE TABLE ' . self::suppressTable() . " (
            inbox char(64) NOT NULL,
            reason varchar(20) NOT NULL,
            source varchar(60) NOT NULL DEFAULT '',
            masked varchar(120) NOT NULL DEFAULT '',
            t datetime NOT NULL,
            n int unsigned NOT NULL DEFAULT 1,
            PRIMARY KEY  (inbox),
            KEY reason (reason)
        ) $c;" );
        dbDelta( 'CREATE TABLE ' . self::statsTable() . " (
            d date NOT NULL,
            kind varchar(40) NOT NULL,
            metric varchar(20) NOT NULL,
            n int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (d,kind,metric)
        ) $c;" );
        update_option( 'mm_email_deliver_db', self::DB_VERSION, false );
    }

    /* ------------------------------------------------------------------ Inboxes */

    /** Stable private key for an inbox (Gmail dots / +tags folded, like Season::inboxKey). */
    public static function hash( $email ) {
        $key = class_exists( 'Season' ) ? Season::inboxKey( $email ) : strtolower( trim( (string) $email ) );

        return hash_hmac( 'sha256', $key, wp_salt( 'nonce' ) );
    }

    public static function mask( $email ) {
        $email = strtolower( trim( (string) $email ) );
        $at = strpos( $email, '@' );

        return $at === false ? '' : substr( $email, 0, min( 2, $at ) ) . '***' . substr( $email, $at );
    }

    /** Suppression reason for an inbox, or ''. */
    protected static $cache = array();

    public static function reason( $email ) {
        global $wpdb;
        $h = self::hash( $email );
        if ( ! array_key_exists( $h, self::$cache ) ) self::$cache[ $h ] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT reason FROM ' . self::suppressTable() . ' WHERE inbox = %s', $h ) );

        return self::$cache[ $h ];
    }

    /** True when this email must not be sent ($promo: promo opt-outs count too). Soft bounces alone don't block. */
    public static function blocked( $email, $promo = false ) {
        $r = self::reason( $email );
        if ( $r === '' || $r === 'soft' ) return false;

        return $r !== 'promo_unsub' || $promo;
    }

    /** Add (or strengthen) a suppression. A bounce/complaint outranks a promo opt-out. */
    public static function suppress( $email, $reason, $source = '' ) {
        global $wpdb;
        $h = self::hash( $email );
        $now = current_time( 'mysql' );
        $cur = self::reason( $email );
        if ( $cur !== '' && ! in_array( $cur, array( 'promo_unsub', 'soft' ), true ) && $reason === 'promo_unsub' ) $reason = $cur; // keep the stronger reason
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::suppressTable() . ' (inbox, reason, source, masked, t, n) VALUES (%s, %s, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE reason = VALUES(reason), source = VALUES(source), t = VALUES(t), n = n + 1',
            $h, substr( $reason, 0, 20 ), substr( (string) $source, 0, 60 ), self::mask( $email ), $now
        ) );
        self::$cache[ $h ] = $reason;
        do_action( 'mm_email_suppressed', $email, $reason, $h );
    }

    /**
     * Temporary bounce (mailbox full, server busy): counted per inbox; the 3rd within 30 days makes it a hard
     * bounce. Soft rows never block on their own; stronger reasons (unsubscribe, bounce) are left alone.
     */
    public static function softBounce( $email, $why = '' ) {
        global $wpdb;
        $h = self::hash( $email );
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT reason, t, n FROM ' . self::suppressTable() . ' WHERE inbox = %s', $h ) );
        if ( $row && $row->reason !== 'soft' ) return;
        $fresh = $row && strtotime( $row->t ) > time() - 30 * DAY_IN_SECONDS;
        $n = $fresh ? (int) $row->n + 1 : 1;
        if ( $n >= 3 ) { self::suppress( $email, 'bounce', 'ses: 3 soft bounces in 30 days' ); return; }
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::suppressTable() . ' (inbox, reason, source, masked, t, n) VALUES (%s, %s, %s, %s, %s, %d) ON DUPLICATE KEY UPDATE n = VALUES(n), t = CASE WHEN VALUES(n) = 1 THEN VALUES(t) ELSE t END, source = VALUES(source)',
            $h, 'soft', substr( 'ses soft: ' . $why, 0, 60 ), self::mask( $email ), current_time( 'mysql' ), $n
        ) );
        self::$cache[ $h ] = 'soft';
    }

    public static function unsuppress( $hash ) {
        global $wpdb;

        self::$cache = array();

        return (bool) $wpdb->delete( self::suppressTable(), array( 'inbox' => (string) $hash ) );
    }

    /* ------------------------------------------------------------------ Stats */

    public static function count( $metric, $kind = 'other', $n = 1 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::statsTable() . ' (d, kind, metric, n) VALUES (%s, %s, %s, %d) ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
            wp_date( 'Y-m-d' ), substr( sanitize_key( (string) $kind ) ?: 'other', 0, 40 ), substr( $metric, 0, 20 ), (int) $n
        ) );
    }

    /** Totals since $days ago: metric => n (optionally for one kind). */
    public static function totals( $days, $kind = '' ) {
        global $wpdb;
        $sql = 'SELECT metric, SUM(n) n FROM ' . self::statsTable() . ' WHERE d >= %s' . ( $kind !== '' ? ' AND kind = %s' : '' ) . ' GROUP BY metric';
        $args = array( wp_date( 'Y-m-d', time() - max( 0, (int) $days - 1 ) * DAY_IN_SECONDS ) );
        if ( $kind !== '' ) $args[] = $kind;
        $out = array();
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) as $r ) $out[ $r->metric ] = (int) $r->n;

        return $out;
    }

    public static function mailFailed( $error ) {
        self::count( 'failed', 'any' );
        $msg = is_wp_error( $error ) ? $error->get_error_message() : 'unknown error';
        do_action( 'mm_alert', 'email', 'An email could not be sent: ' . $msg );
    }

    /* ------------------------------------------------------------------ SES feedback (SNS) */

    public static function routes() {
        register_rest_route( 'mm/v1', '/ses-events', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'sesEvent' ),
            'permission_callback' => '__return_true',
        ) );
    }

    protected static function snsHost( $url ) {
        $p = wp_parse_url( (string) $url );

        return ( $p['scheme'] ?? '' ) === 'https' && preg_match( '/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', (string) ( $p['host'] ?? '' ) );
    }

    /** True when the SNS message is signed by AWS (SignatureVersion 1 = SHA1, 2 = SHA256). */
    public static function verifySns( array $m ) {
        $cert = (string) ( $m['SigningCertURL'] ?? $m['SigningCertUrl'] ?? '' );
        if ( ! self::snsHost( $cert ) || substr( wp_parse_url( $cert, PHP_URL_PATH ), -4 ) !== '.pem' || empty( $m['Signature'] ) ) return false;

        $fields = ( $m['Type'] ?? '' ) === 'Notification'
            ? array( 'Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type' )
            : array( 'Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type' );
        $str = '';
        foreach ( $fields as $f ) {
            if ( ! isset( $m[ $f ] ) ) continue; // Subject is optional
            $str .= $f . "\n" . $m[ $f ] . "\n";
        }

        $pem = get_transient( 'mm_sns_cert_' . md5( $cert ) );
        if ( ! $pem ) {
            $r = wp_remote_get( $cert, array( 'timeout' => 10 ) );
            $pem = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
            if ( strpos( $pem, 'BEGIN CERTIFICATE' ) === false ) return false;
            set_transient( 'mm_sns_cert_' . md5( $cert ), $pem, DAY_IN_SECONDS );
        }
        $key = openssl_pkey_get_public( $pem );
        if ( ! $key ) return false;

        return openssl_verify( $str, base64_decode( (string) $m['Signature'] ), $key, ( $m['SignatureVersion'] ?? '1' ) === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1 ) === 1;
    }

    public static function sesEvent( WP_REST_Request $req ) {
        $m = json_decode( (string) $req->get_body(), true );
        if ( ! is_array( $m ) || empty( $m['Type'] ) || ! self::verifySns( $m ) ) return new WP_REST_Response( array( 'ok' => false ), 403 );

        $topic = (string) get_option( 'mm_ses_topic', '' );
        if ( $topic !== '' && ( $m['TopicArn'] ?? '' ) !== $topic ) return new WP_REST_Response( array( 'ok' => false ), 403 );

        if ( $m['Type'] === 'SubscriptionConfirmation' ) {
            if ( ! self::snsHost( $m['SubscribeURL'] ?? '' ) ) return new WP_REST_Response( array( 'ok' => false ), 400 );
            $r = wp_remote_get( (string) $m['SubscribeURL'], array( 'timeout' => 10 ) );
            if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) update_option( 'mm_ses_topic', (string) $m['TopicArn'], false );

            return new WP_REST_Response( array( 'ok' => true ), 200 );
        }
        if ( $m['Type'] !== 'Notification' ) return new WP_REST_Response( array( 'ok' => true ), 200 );

        $seen = 'mm_sns_' . md5( (string) ( $m['MessageId'] ?? '' ) );
        if ( get_transient( $seen ) ) return new WP_REST_Response( array( 'ok' => true ), 200 );
        set_transient( $seen, 1, 2 * DAY_IN_SECONDS );

        self::handleSes( json_decode( (string) ( $m['Message'] ?? '' ), true ) );

        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /** SES notification (identity feedback "notificationType" or configuration-set "eventType"). */
    public static function handleSes( $n ) {
        if ( ! is_array( $n ) ) return;
        $type = (string) ( $n['notificationType'] ?? $n['eventType'] ?? '' );

        if ( $type === 'Bounce' ) {
            $hard = ( $n['bounce']['bounceType'] ?? '' ) === 'Permanent';
            foreach ( (array) ( $n['bounce']['bouncedRecipients'] ?? array() ) as $r ) {
                $email = (string) ( $r['emailAddress'] ?? '' );
                if ( $email === '' ) continue;
                self::count( $hard ? 'bounce' : 'soft_bounce', 'any' );
                if ( $hard ) self::suppress( $email, 'bounce', 'ses: ' . (string) ( $n['bounce']['bounceSubType'] ?? '' ) );
                else self::softBounce( $email, (string) ( $n['bounce']['bounceSubType'] ?? '' ) );
            }
        } elseif ( $type === 'Complaint' ) {
            foreach ( (array) ( $n['complaint']['complainedRecipients'] ?? array() ) as $r ) {
                $email = (string) ( $r['emailAddress'] ?? '' );
                if ( $email === '' ) continue;
                self::count( 'complaint', 'any' );
                self::suppress( $email, 'complaint', 'ses: ' . (string) ( $n['complaint']['complaintFeedbackType'] ?? 'abuse' ) );
            }
        } elseif ( $type === 'Delivery' ) {
            self::count( 'delivered', 'any', count( (array) ( $n['delivery']['recipients'] ?? array( 1 ) ) ) );
        }
    }

    /* ------------------------------------------------------------------ Promo unsubscribe */

    protected static function b64( $s ) { return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); }
    protected static function unb64( $s ) { return (string) base64_decode( strtr( (string) $s, '-_', '+/' ) ); }

    public static function promoSig( $email ) {
        return substr( hash_hmac( 'sha256', 'promo|' . self::hash( $email ), wp_salt( 'auth' ) ), 0, 24 );
    }

    public static function promoUnsubUrl( $email ) {
        return add_query_arg( array( 'mm_promo_unsub' => self::b64( strtolower( trim( (string) $email ) ) ), 's' => self::promoSig( $email ) ), home_url( '/' ) );
    }

    public static function promoUnsubscribe() {
        if ( ! isset( $_GET['mm_promo_unsub'] ) ) return;
        $email = self::unb64( wp_unslash( $_GET['mm_promo_unsub'] ) );
        $valid = is_email( $email ) && hash_equals( self::promoSig( $email ), (string) ( $_GET['s'] ?? '' ) );
        $post = ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST';
        nocache_headers();

        if ( $post && ( $_POST['List-Unsubscribe'] ?? '' ) === 'One-Click' ) { // inbox button (RFC 8058)
            if ( $valid ) self::suppress( $email, 'promo_unsub', 'one-click' );
            status_header( $valid ? 200 : 400 );
            exit;
        }
        if ( ! $valid ) wp_die( 'This unsubscribe link isn&rsquo;t valid.', 'Link not valid', array( 'response' => 400 ) );

        // A page with a button, so link scanners that open every link don't unsubscribe people.
        if ( $post && ! empty( $_POST['confirm'] ) ) {
            self::suppress( $email, 'promo_unsub', 'link' );
            wp_die( '<h1 style="font-size:22px;">You&rsquo;re unsubscribed</h1><p>You won&rsquo;t get offers and news from Manito Manita at ' . esc_html( self::mask( $email ) ) . ' anymore. You&rsquo;ll still get your group&rsquo;s emails, like your match and exchange reminders.</p>', 'Unsubscribed', array( 'response' => 200, 'link_url' => home_url( '/' ), 'link_text' => 'Manito Manita' ) );
        }
        wp_die( '<h1 style="font-size:22px;">Unsubscribe from offers and news?</h1><p>We&rsquo;ll stop sending promotional emails to ' . esc_html( self::mask( $email ) ) . '. Your group&rsquo;s emails (your match, reminders) keep coming.</p><form method="post"><input type="hidden" name="confirm" value="1"><button type="submit" style="padding:10px 18px;border:0;border-radius:8px;background:#1b1b1b;color:#fff;font-size:15px;cursor:pointer;">Unsubscribe</button></form>', 'Unsubscribe', array( 'response' => 200 ) );
    }
}

EmailDeliver::init();
