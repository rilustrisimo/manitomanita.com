<?php
/**
 * ErrorAlerts Class. Emails the site admin when something breaks, so problems surface before users report them:
 *  - PHP fatal errors (caught at shutdown, with file, line and URL).
 *  - do_action( 'mm_alert', $kind, $message, $context ) from code paths that must not fail quietly:
 *    PRO payment not applied, PayPal sign-in failed, ad refund failed, PayPal webhook not verified.
 * Each distinct alert is emailed at most once an hour (repeats are counted in the next email).
 * The last 30 alerts are kept in the option mm_alerts_log. No passwords, tokens or card data are included.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class ErrorAlerts {
    const THROTTLE = HOUR_IN_SECONDS;
    const LOG = 'mm_alerts_log';

    public static function init() {
        register_shutdown_function( array( __CLASS__, 'shutdown' ) );
        add_action( 'mm_alert', array( __CLASS__, 'alert' ), 10, 3 );
    }

    public static function shutdown() {
        $e = error_get_last();
        if ( ! $e || ! in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) return;
        $file = str_replace( ABSPATH, '', (string) $e['file'] );
        $msg = strtok( (string) $e['message'], "\n" ); // first line only (no stack trace with arguments)
        self::alert( 'fatal', $msg . ' in ' . $file . ':' . (int) $e['line'] );
    }

    public static function alert( $kind, $message, $context = array() ) {
        try {
            $kind = sanitize_key( (string) $kind );
            $message = mb_substr( wp_strip_all_tags( (string) $message ), 0, 500 );
            $key = 'mm_alert_' . md5( $kind . '|' . $message );
            $url = ( isset( $_SERVER['HTTP_HOST'] ) ? 'https://' . $_SERVER['HTTP_HOST'] : '' ) . preg_replace( '/([?&](pw|password|token|t|key|code)=)[^&]*/i', '$1***', (string) ( $_SERVER['REQUEST_URI'] ?? '' ) );

            $log = get_option( self::LOG, array() );
            $log = is_array( $log ) ? $log : array();
            array_unshift( $log, array( 't' => time(), 'kind' => $kind, 'msg' => $message, 'url' => $url ) );
            update_option( self::LOG, array_slice( $log, 0, 30 ), false );

            $seen = get_transient( $key );
            if ( $seen ) { set_transient( $key, (int) $seen + 1, self::THROTTLE ); return; } // already emailed this hour
            set_transient( $key, 1, self::THROTTLE );
            if ( ! function_exists( 'wp_mail' ) ) return;

            $lines = array(
                'What: ' . $message,
                'Kind: ' . $kind,
                'When: ' . wp_date( 'M j, Y g:i:s a' ) . ' (Manila)',
                'Page: ' . ( $url !== '' ? $url : '(background task)' ),
            );
            foreach ( (array) $context as $k => $v ) $lines[] = ucfirst( (string) $k ) . ': ' . mb_substr( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ), 0, 300 );
            $lines[] = '';
            $lines[] = 'You get one email per problem per hour; repeats within the hour are only logged (WP option ' . self::LOG . ').';

            wp_mail( get_option( 'admin_email' ), '[Manito alert] ' . $kind . ': ' . mb_substr( $message, 0, 90 ), implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
        } catch ( \Throwable $t ) {
            // never let an alert break the page
        }
    }
}

ErrorAlerts::init();
