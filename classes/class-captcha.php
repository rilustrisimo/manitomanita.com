<?php
/**
 * * Captcha Class. Cloudflare Turnstile + honeypot for Manito Manita forms.
 *
 * Turns on only when both keys are defined in wp-config.php; until then the
 * existing reCAPTCHA v2 setup keeps working unchanged.
 *   define( 'TURNSTILE_SITE_KEY', '...' );
 *   define( 'TURNSTILE_SECRET_KEY', '...' );
 *
 * Used by: Create Group / Join Group (Theme::createAcfForm) and the member comment form.
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
 * Class Captcha
 */
class Captcha {
    const TOKEN_FIELD    = 'cf-turnstile-response';
    const HONEYPOT_FIELD = 'mm_website';
    const PASS_TTL       = 600; // a verified token stays valid for ACF's validate + save requests

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked || ! self::enabled() ) return;
        self::$hooked = true;

        // Hide the old ACF reCAPTCHA fields (their validation only runs for rendered fields).
        add_filter( 'acf/prepare_field/type=recaptcha', '__return_false' );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_print_scripts', array( __CLASS__, 'dropRecaptcha' ), 0 );
        add_action( 'acf/validate_save_post', array( __CLASS__, 'validateAcf' ), 5 );
    }

    /**
     * The ACF reCAPTCHA plugin loads Google's 350 KB script on every page even though its fields are hidden.
     * (Dashboard comments load their own reCAPTCHA in header-dashboard.php.)
     */
    public static function dropRecaptcha() {
        if ( is_admin() ) return;
        wp_dequeue_script( 'acf-recaptcha-grecaptcha-api' );
        wp_dequeue_script( 'acf-recaptcha-input' );
    }

    public static function enabled() {
        return defined( 'TURNSTILE_SITE_KEY' ) && TURNSTILE_SITE_KEY !== ''
            && defined( 'TURNSTILE_SECRET_KEY' ) && TURNSTILE_SECRET_KEY !== '';
    }

    public static function enqueue() {
        // Explicit rendering: the forms live in popups that Fancybox moves around the page, which breaks a
        // widget rendered on page load. Theme.initTurnstile renders it when the popup opens.
        wp_enqueue_script( 'cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true );
    }

    /**
     * Widget + honeypot markup for a form. Invisible for most visitors ("interaction-only").
     */
    public static function widget() {
        if ( ! self::enabled() ) return '';

        $html  = '<div class="mm-captcha">';
        $html .= '<div class="mm-turnstile" data-sitekey="' . esc_attr( TURNSTILE_SITE_KEY ) . '"></div>';
        $html .= '</div>';
        $html .= '<div class="mm-hp" aria-hidden="true"><label>Leave this empty <input type="text" name="' . self::HONEYPOT_FIELD . '" value="" tabindex="-1" autocomplete="off"></label></div>';

        return $html;
    }

    /**
     * ACF front-end forms: only new posts (Create Group / Join Group) are checked.
     */
    public static function validateAcf() {
        if ( is_admin() && ! wp_doing_ajax() ) return;
        if ( ( $_POST['_acf_post_id'] ?? '' ) !== 'new_post' ) return;

        $error = self::check();

        if ( $error ) acf_add_validation_error( '', $error );
    }

    /**
     * @return string Empty when the request passes, otherwise a message for the visitor.
     */
    public static function check() {
        if ( ! empty( $_POST[ self::HONEYPOT_FIELD ] ) ) return 'Something went wrong. Please refresh the page and try again.';

        $token = isset( $_POST[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_FIELD ] ) ) : '';

        if ( $token === '' ) return 'Please wait a moment for the security check to finish, then try again.';

        return self::verify( $token ) ? '' : 'The security check failed or expired. Please try again.';
    }

    /**
     * Server-side check with Cloudflare. A token can only be redeemed once, so a pass is cached
     * briefly: ACF validates over AJAX first and then validates again when the form is submitted.
     */
    public static function verify( $token ) {
        $key = 'mm_ts_' . md5( $token );

        if ( get_transient( $key ) ) return true;

        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'timeout' => 10,
            'body'    => array(
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Turnstile: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['success'] ) ) return false;

        set_transient( $key, 1, self::PASS_TTL );

        return true;
    }
}

Captcha::init();
