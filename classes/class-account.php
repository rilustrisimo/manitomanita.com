<?php
/**
 * Account helpers for members and organizers (both use one password per group, no WP accounts):
 *
 * - Password reset links: personal, signed, 1-hour, single use (the signature includes the current
 *   password hash, so the link stops working once the password changes). Replaces the old links that
 *   used the shared group password.
 * - "Find my groups": email someone the links to every group they're in (lost link). Same reply
 *   whether or not the email exists, so addresses can't be probed.
 * - "Remember this device": after a correct password, a signed cookie (30 days, HttpOnly) keeps the
 *   member / organizer verified on this browser. It stops working when the password changes.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class Account {
    const RESET_PAGE  = 1389;
    const RESET_TTL   = HOUR_IN_SECONDS;
    const COOKIE      = 'mm_rd';
    const REMEMBER    = 30; // days
    const MAX_DEVICES = 25; // remembered members/groups per browser

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'wp_ajax_mm_find_groups', array( __CLASS__, 'ajaxFindGroups' ) );
        add_action( 'wp_ajax_nopriv_mm_find_groups', array( __CLASS__, 'ajaxFindGroups' ) );
        add_action( 'wp_ajax_mm_forget_device', array( __CLASS__, 'ajaxForget' ) );
        add_action( 'wp_ajax_nopriv_mm_forget_device', array( __CLASS__, 'ajaxForget' ) );
    }

    protected static function hashOf( $id, $role = '' ) {
        if ( $role === 'co' && class_exists( 'GroupExtras' ) ) {
            $c = GroupExtras::coOrganizer( $id );
            return $c ? (string) $c['hash'] : '';
        }

        return (string) get_field( 'password', $id );
    }

    protected static function sign( $what ) {
        return substr( hash_hmac( 'sha256', $what, wp_salt( 'auth' ) ), 0, 32 );
    }

    protected static function rate( $key, $max ) {
        $k = 'mm_acc_rl_' . md5( $key );
        $n = (int) get_transient( $k );
        if ( $n >= $max ) return false;
        set_transient( $k, $n + 1, HOUR_IN_SECONDS );

        return true;
    }

    /* ------------------------------------------------------------------ Password reset */

    public static function resetUrl( $id ) {
        $id = (int) $id;
        $exp = time() + self::RESET_TTL;

        return add_query_arg( array( 'uid' => $id, 'exp' => $exp, 't' => self::sign( 'reset|' . $id . '|' . $exp . '|' . self::hashOf( $id ) ) ), get_permalink( self::RESET_PAGE ) );
    }

    public static function validReset( $id, $exp, $t ) {
        $id = (int) $id;
        if ( ! in_array( get_post_type( $id ), array( 'users', 'groups' ), true ) || (int) $exp < time() || ! $t ) return false;

        return hash_equals( self::sign( 'reset|' . $id . '|' . (int) $exp . '|' . self::hashOf( $id ) ), (string) $t );
    }

    public static function canSendReset( $id ) {
        return self::rate( 'reset-uid' . (int) $id, 3 ) && self::rate( 'reset-ip' . ( $_SERVER['REMOTE_ADDR'] ?? '' ), 10 );
    }

    public static function groupOf( $id ) {
        return get_post_type( $id ) === 'groups' ? (int) $id : (int) get_field( 'groups', $id, false );
    }

    /**
     * Handles the reset form (template-resetpw.php). Returns array( state, message, extra ).
     * States: form | done | invalid.
     */
    public static function handleReset() {
        $id = (int) ( $_REQUEST['uid'] ?? 0 );
        $exp = (int) ( $_REQUEST['exp'] ?? 0 );
        $t = (string) ( $_REQUEST['t'] ?? '' );

        if ( ! self::validReset( $id, $exp, $t ) ) return array( 'invalid', '', array() );
        if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) return array( 'form', '', array( 'id' => $id ) );

        if ( ! wp_verify_nonce( (string) ( $_POST['_mmnonce'] ?? '' ), 'mm_reset_' . $id ) ) return array( 'form', 'This form expired. Please try again.', array( 'id' => $id ) );
        $pass = (string) wp_unslash( $_POST['new-pass'] ?? '' );
        $again = (string) wp_unslash( $_POST['new-pass2'] ?? '' );
        if ( strlen( $pass ) < 6 ) return array( 'form', 'Please use at least 6 characters.', array( 'id' => $id ) );
        if ( $pass !== $again ) return array( 'form', 'The two passwords don&rsquo;t match.', array( 'id' => $id ) );

        update_field( 'password', wp_hash_password( $pass ), $id );

        // Signed in right away on this browser (and remembered, as with a normal password check).
        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) session_start();
        if ( get_post_type( $id ) === 'groups' ) {
            Groups::markModerator( $id );
            if ( class_exists( 'GroupExtras' ) ) GroupExtras::markOrganizer( $id );
        } else {
            $_SESSION['mm_verified'][ $id ] = time();
        }
        self::remember( $id );

        $gid = self::groupOf( $id );
        $isGroup = get_post_type( $id ) === 'groups';
        $email = (string) get_field( $isGroup ? 'your_email' : 'email', $id );
        $name = (string) get_field( $isGroup ? 'your_name' : 'screen_name', $id );
        Emails::send( $email, 'Your Manito Manita password was changed', array(
            'preheader' => 'Your password for ' . get_field( 'group_name', $gid ) . ' was just changed.',
            'eyebrow'   => 'Password changed',
            'title'     => 'Your password was changed',
            'body'      => array( '<p>Hi ' . esc_html( $name !== '' ? $name : 'there' ) . ', your ' . ( $isGroup ? 'organizer' : 'member' ) . ' password for <strong>' . esc_html( (string) get_field( 'group_name', $gid ) ) . '</strong> was just changed.</p>', '<p>If this wasn&rsquo;t you, reset it again right away from the group page (&ldquo;Forgot password?&rdquo;) or reply to this email.</p>' ),
            'button'    => array( 'Open the group', Emails::groupLink( $gid ) ),
            'footer'    => 'You&rsquo;re getting this because the password for this group was changed on manitomanita.com.',
        ) );

        return array( 'done', '', array( 'id' => $id, 'gid' => $gid, 'link' => Emails::groupLink( $gid ), 'group' => (string) get_field( 'group_name', $gid ), 'organizer' => $isGroup ) );
    }

    /* ------------------------------------------------------------------ Find my groups (lost link) */

    /** Groups an email belongs to (as member or organizer), newest first, at most 20. */
    public static function groupsFor( $email ) {
        $email = strtolower( trim( (string) $email ) );
        $out = array();

        $add = function ( $gid, $role ) use ( &$out ) {
            $gid = (int) $gid;
            if ( ! $gid || get_post_type( $gid ) !== 'groups' || get_post_status( $gid ) !== 'publish' || isset( $out[ $gid ] ) ) return;
            $d = DateTime::createFromFormat( 'm/d/Y', (string) get_field( 'gift_exchange_date', $gid ) );
            $out[ $gid ] = array( 'name' => (string) get_field( 'group_name', $gid ), 'date' => $d ? $d->format( 'M j, Y' ) : '', 'sort' => $d ? $d->format( 'Ymd' ) : '0', 'role' => $role, 'link' => Emails::groupLink( $gid ) );
        };

        foreach ( get_posts( array( 'post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'email', 'value' => $email ) ) ) ) as $uid ) {
            $add( get_field( 'groups', $uid, false ), 'member' );
        }
        foreach ( get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'your_email', 'value' => $email ) ) ) ) as $gid ) {
            $add( $gid, 'organizer' );
        }

        uasort( $out, function ( $a, $b ) { return strcmp( $b['sort'], $a['sort'] ); } );

        return array_slice( $out, 0, 20, true );
    }

    public static function ajaxFindGroups() {
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        if ( ! is_email( $email ) ) wp_send_json_error( 'Please enter a valid email address.' );
        if ( ! self::rate( 'find-ip' . ( $_SERVER['REMOTE_ADDR'] ?? '' ), 6 ) || ! self::rate( 'find-email' . $email, 3 ) ) {
            wp_send_json_error( 'You&rsquo;ve asked a few times already. Please check your inbox (and spam folder), or try again in an hour.' );
        }

        $groups = self::groupsFor( $email );
        if ( $groups ) {
            $rows = '';
            foreach ( $groups as $g ) {
                $rows .= '<p style="margin:0 0 10px;padding:12px 14px;border:1px solid #e6e6e6;border-radius:10px;"><a href="' . esc_url( $g['link'] ) . '" style="color:#1b1b1b;font-weight:700;font-size:16px;text-decoration:none;">' . esc_html( $g['name'] ) . ' &rarr;</a><br><span style="color:#6d6d6d;font-size:13px;">' . ( $g['role'] === 'organizer' ? 'You organize this group' : 'You&rsquo;re a member' ) . ( $g['date'] ? ' &middot; exchange ' . esc_html( $g['date'] ) : '' ) . '</span></p>';
            }
            Emails::send( $email, 'Your Manito Manita group links', array(
                'preheader' => 'Here are the links to your ' . count( $groups ) . ' ' . ( count( $groups ) === 1 ? 'group' : 'groups' ) . '.',
                'eyebrow'   => 'Your groups',
                'title'     => 'Here are your group links',
                'body'      => array( '<p>Tap a group to open it. Save the link (or bookmark the page) so it&rsquo;s easy to find next time.</p>', $rows ),
                'note'      => 'Forgot your password too? Open the group, tap your name, then &ldquo;Forgot Password?&rdquo;.',
                'footer'    => 'You&rsquo;re getting this because someone asked for the group links for this email on manitomanita.com.',
            ) );
        }

        wp_send_json_success( 'If that email is in any groups, we just sent you the links. Check your inbox (and spam folder).' );
    }

    /* ------------------------------------------------------------------ Remember this device */

    protected static function cookieMap() {
        $raw = isset( $_COOKIE[ self::COOKIE ] ) ? json_decode( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ), true ) : null;

        return is_array( $raw ) ? $raw : array();
    }

    protected static function writeCookie( array $map ) {
        if ( headers_sent() ) return;
        $map = array_slice( $map, -self::MAX_DEVICES, null, true );
        $value = $map ? wp_json_encode( $map ) : '';
        setcookie( self::COOKIE, $value, array(
            'expires'  => $map ? time() + self::REMEMBER * DAY_IN_SECONDS : time() - 3600,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
        $_COOKIE[ self::COOKIE ] = $value;
    }

    /** Remember this member / organizer (role 'co' = co-organizer password) on this browser. */
    public static function remember( $id, $role = '' ) {
        $id = (int) $id;
        $exp = time() + self::REMEMBER * DAY_IN_SECONDS;
        $map = self::cookieMap();
        unset( $map[ $id ] );
        $map[ $id ] = $exp . '.' . $role . '.' . self::sign( 'rd|' . $id . '|' . $exp . '|' . $role . '|' . self::hashOf( $id, $role ) );
        self::writeCookie( $map );
    }

    /** Members / groups remembered on this browser, most recent first. */
    public static function rememberedIds() {
        $out = array();
        foreach ( array_reverse( array_keys( self::cookieMap() ) ) as $id ) {
            if ( self::remembered( $id ) !== null ) $out[] = (int) $id;
        }

        return $out;
    }

    /** Role ('' or 'co') if this browser is remembered for the member/group, else null. */
    public static function remembered( $id ) {
        $id = (int) $id;
        $map = self::cookieMap();
        if ( empty( $map[ $id ] ) ) return null;

        $parts = explode( '.', (string) $map[ $id ] );
        if ( count( $parts ) !== 3 ) return null;
        list( $exp, $role, $sig ) = $parts;
        if ( (int) $exp < time() || ! in_array( $role, array( '', 'co' ), true ) ) return null;
        if ( $role === 'co' && ! get_field( 'pro', $id ) ) return null;

        $hash = self::hashOf( $id, $role );
        if ( $hash === '' || ! hash_equals( self::sign( 'rd|' . $id . '|' . (int) $exp . '|' . $role . '|' . $hash ), $sig ) ) return null;

        return $role;
    }

    /**
     * Restores a verified session from the cookie. Used by Users::isVerifiedMember, Groups::isModerator, QA.
     */
    public static function restore( $id ) {
        $role = self::remembered( $id );
        if ( $role === null ) return false;

        if ( get_post_type( $id ) === 'groups' ) {
            $_SESSION['mm_mod'][ (int) $id ] = time();
            if ( $role === 'co' ) $_SESSION['mm_co'][ (int) $id ] = time();
            else unset( $_SESSION['mm_co'][ (int) $id ] );
        } else {
            $_SESSION['mm_verified'][ (int) $id ] = time();
        }

        return true;
    }

    /** Members and the group signed in on this browser (fresh password this session, or remembered). */
    public static function verifiedIds( $gid ) {
        $gid = (int) $gid;
        $ids = array();
        if ( Groups::isModerator( $gid ) ) $ids[] = $gid;
        foreach ( Groups::memberIds( $gid ) as $uid ) {
            $t = isset( $_SESSION['mm_verified'][ $uid ] ) ? (int) $_SESSION['mm_verified'][ $uid ] : 0;
            if ( $t > time() - 2 * HOUR_IN_SECONDS || self::restore( $uid ) ) $ids[] = (int) $uid;
        }

        return $ids;
    }

    /** "Forget this device" for one member/group (or all). */
    public static function ajaxForget() {
        $id = (int) ( $_POST['id'] ?? 0 );
        $map = self::cookieMap();
        if ( $id ) unset( $map[ $id ] ); else $map = array();
        self::writeCookie( $map );
        if ( $id ) { unset( $_SESSION['mm_verified'][ $id ], $_SESSION['mm_mod'][ $id ], $_SESSION['mm_co'][ $id ] ); }
        else { unset( $_SESSION['mm_verified'], $_SESSION['mm_mod'], $_SESSION['mm_co'] ); }
        wp_send_json_success();
    }
}

Account::init();
