<?php
/**
 * * EmailCheck Class. Makes sure group creators and members use a working email address.
 *
 * - Everyone: disposable domains are rejected (Theme::validateEmail); a typo hint runs in the browser.
 * - Group creator: a 6-digit code is emailed and must be entered before the group is created.
 * - Members: a "Confirm your email" button in the welcome email they already receive.
 *
 * Members who joined before EmailCheck existed count as confirmed (VERIFY_SINCE).
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
 * Class EmailCheck
 */
class EmailCheck {
    const GROUP_EMAIL_FIELD = 'field_5f55bf2fde4cc'; // "Your email" on the Create Group form
    const CODE_TTL          = 600;   // a code is valid for 10 minutes
    const PASS_TTL          = 1800;  // a verified email stays verified for the form's two validations
    const MAX_TRIES         = 5;
    const PER_EMAIL_HOUR    = 3;
    const PER_IP_HOUR       = 10;
    const VERIFIED_META     = '_mm_email_verified'; // the address that was confirmed
    const VERIFY_SINCE      = '2026-11-02 00:00:00'; // members created earlier count as confirmed

    protected static $hooked = false;

    protected static $disposable = array(
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamailblock.com', 'sharklasers.com',
        'grr.la', '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io',
        'tempmailo.com', 'tempail.com', 'tempr.email', 'yopmail.com', 'yopmail.net', 'trashmail.com',
        'trashmail.de', 'getnada.com', 'nada.email', 'dispostable.com', 'maildrop.cc', 'mailnesia.com',
        'fakeinbox.com', 'throwawaymail.com', 'emailondeck.com', 'mohmal.com', 'moakt.com', 'mintemail.com',
        'mailcatch.com', 'spamgourmet.com', 'spambox.us', 'getairmail.com', 'discard.email', 'burnermail.io',
        'mailpoof.com', 'emailfake.com', 'fakemail.net', 'mytemp.email', 'tmpmail.org', 'tmpmail.net',
        'mail.tm', 'mail.gw', 'inboxkitten.com', 'mailsac.com', 'luxusmail.org', 'harakirimail.com',
        'spam4.me', 'cock.li', 'linshiyouxiang.net', 'dropmail.me', 'emltmp.com', 'tempinbox.com',
        'mailforspam.com', 'jetable.org', 'trbvm.com', 'byom.de', 'anonbox.net', 'eyepaste.com',
    );

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'wp_ajax_mm_send_otp', array( __CLASS__, 'ajaxSendCode' ) );
        add_action( 'wp_ajax_nopriv_mm_send_otp', array( __CLASS__, 'ajaxSendCode' ) );
        add_action( 'wp_ajax_mm_check_otp', array( __CLASS__, 'ajaxCheckCode' ) );
        add_action( 'wp_ajax_nopriv_mm_check_otp', array( __CLASS__, 'ajaxCheckCode' ) );
        add_action( 'acf/validate_save_post', array( __CLASS__, 'validateGroupForm' ), 6 );
        add_action( 'acf/save_post', array( __CLASS__, 'markNewGroup' ), 20 );
        add_action( 'init', array( __CLASS__, 'confirmLink' ), 2 );
    }

    /* ------------------------------------------------------------ Helpers */

    public static function isDisposable( $email ) {
        $domain = strtolower( (string) substr( strrchr( (string) $email, '@' ), 1 ) );

        foreach ( self::$disposable as $d ) {
            if ( $domain === $d || substr( $domain, -strlen( '.' . $d ) ) === '.' . $d ) return true;
        }

        return false;
    }

    protected static function key( $email ) {
        return md5( strtolower( trim( (string) $email ) ) );
    }

    protected static function hashCode( $email, $code ) {
        return hash_hmac( 'sha256', self::key( $email ) . '|' . $code, wp_salt( 'auth' ) );
    }

    /**
     * Member counts as confirmed when the confirmed address is the current one (or they joined before).
     */
    public static function isConfirmed( $uid ) {
        $uid = (int) $uid;
        $email = strtolower( trim( (string) get_field( 'email', $uid ) ) );
        $verified = strtolower( (string) get_post_meta( $uid, self::VERIFIED_META, true ) );

        if ( $verified !== '' && $verified === $email ) return true;

        return get_post_field( 'post_date', $uid ) < self::VERIFY_SINCE;
    }

    /* ------------------------------------------------------ Creator code */

    /**
     * Code box placed under "Your email" on the Create Group form (moved there by Theme.initEmailChecks).
     */
    public static function codeField() {
        return '<div class="mm-otp" data-mm-otp hidden>'
            . '<button type="button" class="mm-otp__send" data-mm-otp-send>Send verification code</button>'
            . '<div class="mm-otp__code" data-mm-otp-box hidden>'
            . '<label for="mm-otp-input">Enter the 6-digit code we emailed you</label>'
            . '<div class="mm-otp__row"><input id="mm-otp-input" type="text" name="mm_otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456">'
            . '<button type="button" class="mm-otp__resend" data-mm-otp-resend disabled>Resend</button></div>'
            . '</div>'
            . '<p class="mm-otp__msg" data-mm-otp-msg aria-live="polite"></p>'
            . '<input type="hidden" name="mm_otp_nonce" value="' . esc_attr( wp_create_nonce( 'mm_otp' ) ) . '">'
            . '</div>';
    }

    public static function ajaxSendCode() {
        check_ajax_referer( 'mm_otp', 'nonce' );

        $email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );

        if ( ! is_email( $email ) ) wp_send_json_error( 'Please enter a valid email address first.' );
        if ( self::isDisposable( $email ) ) wp_send_json_error( 'Please use your regular email address, not a temporary one.' );

        $domain = substr( strrchr( $email, '@' ), 1 );
        if ( ! checkdnsrr( $domain, 'MX' ) ) wp_send_json_error( 'That email domain can&rsquo;t receive emails. Please check the spelling.' );

        // Rate limits (also protect the SES sending quota).
        $eKey = 'mm_otp_rl_e_' . self::key( $email );
        $iKey = 'mm_otp_rl_i_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $eCount = (int) get_transient( $eKey );
        $iCount = (int) get_transient( $iKey );

        if ( $eCount >= self::PER_EMAIL_HOUR || $iCount >= self::PER_IP_HOUR ) {
            wp_send_json_error( 'Too many codes requested. Please wait a while and try again.' );
        }

        set_transient( $eKey, $eCount + 1, HOUR_IN_SECONDS );
        set_transient( $iKey, $iCount + 1, HOUR_IN_SECONDS );

        $code = (string) random_int( 100000, 999999 );
        set_transient( 'mm_otp_' . self::key( $email ), array( 'h' => self::hashCode( $email, $code ), 'tries' => 0 ), self::CODE_TTL );

        $what = ( ( $_POST['purpose'] ?? '' ) === 'wishlist' ) ? 'wishlist' : 'group';
        $sent = Emails::send( $email, $code . ' is your Manito Manita code', array(
            'preheader' => 'Enter this code to verify your email and create your ' . $what . '.',
            'eyebrow'   => 'Verify your email',
            'title'     => 'Your verification code',
            'body'      => array( '<p>Enter this code ' . ( $what === 'group' ? 'on the Create Group form' : 'to create your wishlist' ) . ' to verify your email:</p>' ),
            'code'      => $code,
            'note'      => 'The code expires in 10 minutes. If you didn&rsquo;t try to create a ' . $what . ', you can ignore this email.',
            'footer'    => 'You&rsquo;re getting this because this address was entered on manitomanita.com.',
        ) );

        if ( ! $sent ) wp_send_json_error( 'We couldn&rsquo;t send the code. Please try again.' );

        wp_send_json_success( 'Code sent to ' . $email . '. Check your inbox (and spam folder).' );
    }

    /**
     * Checks the code as soon as it is typed, so the form can unlock "Create Group" (the server check on
     * submit stays: a pass is remembered for the form's validation).
     */
    public static function ajaxCheckCode() {
        check_ajax_referer( 'mm_otp', 'nonce' );

        $email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
        $code  = preg_replace( '/\D/', '', (string) ( $_POST['code'] ?? '' ) );

        if ( ! is_email( $email ) || strlen( $code ) !== 6 ) wp_send_json_error( 'Enter the 6-digit code from the email.' );

        $error = self::checkCode( $email, $code );

        if ( $error ) wp_send_json_error( $error );

        wp_send_json_success( 'Email verified.' );
    }

    /**
     * Create Group (new group post): the email must have a valid code. A pass is remembered because
     * ACF validates twice (AJAX, then on submit).
     */
    public static function validateGroupForm() {
        if ( is_admin() && ! wp_doing_ajax() ) return;
        if ( ( $_POST['_acf_post_id'] ?? '' ) !== 'new_post' ) return;

        $email = isset( $_POST['acf'][ self::GROUP_EMAIL_FIELD ] ) ? sanitize_email( wp_unslash( (string) $_POST['acf'][ self::GROUP_EMAIL_FIELD ] ) ) : '';
        if ( $email === '' ) return; // not the Create Group form

        $error = self::checkCode( $email, preg_replace( '/\D/', '', (string) ( $_POST['mm_otp'] ?? '' ) ) );

        if ( $error ) acf_add_validation_error( '', $error );
    }

    /** Public check for other forms (My Wishlist). Empty when verified, otherwise a message. */
    public static function verifyCode( $email, $code ) {
        return self::checkCode( $email, $code );
    }

    /**
     * @return string Empty when verified, otherwise a message.
     */
    protected static function checkCode( $email, $code ) {
        $k = self::key( $email );

        if ( get_transient( 'mm_otp_ok_' . $k ) ) return '';
        if ( $code === '' ) return 'Please verify your email: tap &ldquo;Send verification code&rdquo; and enter the code we email you.';

        $entry = get_transient( 'mm_otp_' . $k );
        if ( ! is_array( $entry ) ) return 'That code has expired. Please request a new one.';

        if ( ! hash_equals( $entry['h'], self::hashCode( $email, $code ) ) ) {
            $entry['tries']++;

            if ( $entry['tries'] >= self::MAX_TRIES ) {
                delete_transient( 'mm_otp_' . $k );
                return 'Too many wrong attempts. Please request a new code.';
            }

            set_transient( 'mm_otp_' . $k, $entry, self::CODE_TTL );
            return 'That code isn&rsquo;t right. Please check the email and try again.';
        }

        delete_transient( 'mm_otp_' . $k );
        set_transient( 'mm_otp_ok_' . $k, 1, self::PASS_TTL );

        return '';
    }

    /**
     * After a group is created: remember its email was verified.
     */
    public static function markNewGroup( $postId ) {
        if ( ! is_numeric( $postId ) || get_post_type( $postId ) !== 'groups' ) return;

        $email = (string) get_field( 'your_email', $postId );
        $k = self::key( $email );

        if ( $email !== '' && get_transient( 'mm_otp_ok_' . $k ) ) {
            update_post_meta( $postId, self::VERIFIED_META, strtolower( trim( $email ) ) );
            delete_transient( 'mm_otp_ok_' . $k );
        }
    }

    /* ------------------------------------------------------ Member confirm */

    public static function confirmUrl( $uid ) {
        $email = strtolower( trim( (string) get_field( 'email', $uid ) ) );

        return add_query_arg( array(
            'mm_confirm' => (int) $uid,
            't'          => substr( hash_hmac( 'sha256', (int) $uid . '|' . $email, wp_salt( 'auth' ) ), 0, 32 ),
        ), home_url( '/' ) );
    }

    /**
     * Join email: confirm to be added to the group.
     */
    public static function sendJoinConfirm( $uid ) {
        $gid = (int) get_field( 'groups', $uid, false );
        $group = (string) get_field( 'group_name', $gid );
        $name = trim( (string) get_field( 'screen_name', $uid ) );

        return Emails::send( (string) get_field( 'email', $uid ), 'Confirm your email to join ' . $group, array(
            'preheader' => 'One tap and you&rsquo;re in. You&rsquo;ll be added to ' . $group . ' once you confirm.',
            'eyebrow'   => 'Almost there',
            'title'     => 'Confirm your email to join ' . $group,
            'body'      => array(
                '<p>Hi ' . esc_html( $name !== '' ? $name : 'there' ) . ', thanks for joining! Tap the button below to confirm this is your email.</p>',
                '<p><strong>You&rsquo;ll be added to the group once you confirm.</strong> That way your match and reminders reach you.</p>',
            ),
            'card'      => Emails::groupCard( $gid ),
            'button'    => array( 'Confirm and join the group', self::confirmUrl( $uid ) ),
            'note'      => 'Didn&rsquo;t try to join? Just ignore this email. You won&rsquo;t be added.',
            'footer'    => 'You&rsquo;re getting this because someone used this address to join ' . esc_html( $group ) . ' on Manito Manita.',
        ) );
    }

    /**
     * After confirming: welcome + next steps.
     */
    public static function sendWelcome( $uid ) {
        $gid = (int) get_field( 'groups', $uid, false );
        $group = (string) get_field( 'group_name', $gid );
        $name = trim( (string) get_field( 'screen_name', $uid ) );

        return Emails::send( (string) get_field( 'email', $uid ), 'You&rsquo;re in! Welcome to ' . $group, array(
            'preheader' => 'Add your wishlist so your Secret Santa knows what to get you.',
            'eyebrow'   => 'You&rsquo;re in',
            'title'     => 'Welcome to ' . $group . ', ' . ( $name !== '' ? $name : 'friend' ) . '!',
            'body'      => array(
                '<p>Your email is confirmed and you&rsquo;re now part of the group. Here&rsquo;s what happens next:</p>',
                '<ol><li><strong>Add your wishlist</strong> so your Secret Santa knows what to get you.</li><li>Wait for the organizer to <strong>shuffle the group</strong>. We&rsquo;ll email you.</li><li>Open the group and tap <strong>See Match</strong> to see who you&rsquo;re buying for.</li></ol>',
            ),
            'card'      => Emails::groupCard( $gid ),
            'button'    => array( 'Open the group and add my wishlist', Emails::groupLink( $gid ) ),
            'note'      => 'Keep this email: the button always takes you back to your group.',
            'footer'    => 'You&rsquo;re getting this because you joined ' . esc_html( $group ) . ' on Manito Manita.',
        ) );
    }

    /**
     * /?mm_confirm=<uid>&t=<token>: marks the member's email confirmed, then opens their group.
     */
    public static function confirmLink() {
        if ( empty( $_GET['mm_confirm'] ) || empty( $_GET['t'] ) ) return;

        $uid = absint( $_GET['mm_confirm'] );
        $token = (string) $_GET['t'];
        $ok = false;

        if ( get_post_type( $uid ) === 'users' ) {
            $email = strtolower( trim( (string) get_field( 'email', $uid ) ) );
            $expect = substr( hash_hmac( 'sha256', $uid . '|' . $email, wp_salt( 'auth' ) ), 0, 32 );

            if ( $email !== '' && hash_equals( $expect, $token ) ) {
                update_post_meta( $uid, self::VERIFIED_META, $email );
                $ok = true;
            }
        }

        $gid = $ok ? (int) get_field( 'groups', $uid, false ) : 0;
        $state = '1';

        // Pending join: add them to the group now (unless names were already drawn without them).
        if ( $gid && get_post_status( $uid ) === 'pending' ) {
            if ( get_field( 'matched', $gid ) ) {
                $state = 'late';
            } else {
                wp_update_post( array( 'ID' => $uid, 'post_status' => 'publish' ) );
                self::sendWelcome( $uid );
                if ( class_exists( 'Insights' ) ) Insights::log( 'member_confirmed', $gid, $uid );
            }
        }

        $target = $gid
            ? add_query_arg( array( 'gid' => $gid, 'pw' => (string) get_field( 'group_password', $gid ), 'mm_confirmed' => $state ), get_permalink( 23 ) )
            : home_url( '/' );

        nocache_headers();
        wp_safe_redirect( $target );
        exit;
    }
}

EmailCheck::init();
