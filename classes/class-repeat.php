<?php
/**
 * "Start a new group from this one": an organizer repeats last year's exchange.
 *
 * The new group is a normal, separate, FREE group made with the regular Create Group form: the
 * organizer enters a new name, date, budget and moderator password; only their email is carried over
 * (and verified again with the 6-digit code, so we know it still works). Nothing PRO is copied
 * (exclusions, Weekly Drops, co-organizer, theme, credits). Last year's members are only invited by
 * email; they show up after they join and confirm like anyone else.
 *
 * Flow: dashboard / PRO Actions (moderator password) -> ajax mm_repeat_start returns a one-time link
 * /?mm_repeat=<token> -> the homepage opens Create Group pre-filled (ajax mm_repeat_prep) -> on save
 * (Groups::my_save_post fires mm_group_created) the ticked members get an invite.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class Repeat {
    const TOKEN_TTL   = 2 * HOUR_IN_SECONDS;
    const FROM_META   = '_mm_from_group';   // on the new group: the group it was started from
    const NEXT_META   = '_mm_next_group';   // on the old group: the newest group started from it
    const MAX_INVITES = 300;

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'wp_ajax_mm_repeat_start', array( __CLASS__, 'ajaxStart' ) );
        add_action( 'wp_ajax_nopriv_mm_repeat_start', array( __CLASS__, 'ajaxStart' ) );
        add_action( 'wp_ajax_mm_repeat_prep', array( __CLASS__, 'ajaxPrep' ) );
        add_action( 'wp_ajax_nopriv_mm_repeat_prep', array( __CLASS__, 'ajaxPrep' ) );
        add_action( 'mm_group_created', array( __CLASS__, 'afterCreate' ) );
    }

    /** Members of the old group who can be invited: published, confirmed email, not unsubscribed. */
    protected static function invitable( $gid ) {
        $out = array();
        foreach ( Groups::memberIds( $gid ) as $uid ) {
            if ( ! EmailCheck::isConfirmed( $uid ) || Emails::optedOut( $uid ) ) continue;
            if ( ! is_email( (string) get_field( 'email', $uid ) ) ) continue;
            $out[ (int) $uid ] = trim( (string) get_field( 'name', $uid ) ) ?: (string) get_field( 'screen_name', $uid );
        }

        return $out;
    }

    public static function exchangeDone( $gid ) {
        $d = DateTime::createFromFormat( 'm/d/Y', (string) get_field( 'gift_exchange_date', $gid ) );

        return $d && $d->format( 'Y-m-d' ) < wp_date( 'Y-m-d' );
    }

    /** Dashboard card once the exchange date has passed (any visitor sees it; the button asks for the moderator password). */
    public static function dashboardCard( $gid ) {
        if ( ! self::exchangeDone( $gid ) ) return '';

        return '<div class="mm-repeat-card">'
            . '<span class="mm-repeat-card__icon"><i class="fas fa-rotate-right"></i></span>'
            . '<div class="mm-repeat-card__txt"><strong>Doing it again next time?</strong><span>Organizers can start a new group from this one and invite everyone back in a few taps.</span></div>'
            . '<a href="javascript:;" class="user-action repeat-btn mm-repeat-card__btn" group-data="' . (int) $gid . '" data-action="repeat-group">Start a new group</a>'
            . '</div>';
    }

    /* ------------------------------------------------------------------ AJAX */

    public static function ajaxStart() {
        $gid = (int) ( $_POST['gid'] ?? 0 );
        if ( get_post_type( $gid ) !== 'groups' || ! Groups::isModerator( $gid ) ) wp_send_json_error( 'Please enter the moderator password again.' );

        $token = wp_generate_password( 24, false );
        set_transient( 'mm_repeat_' . $token, $gid, self::TOKEN_TTL );

        wp_send_json_success( array( 'url' => add_query_arg( 'mm_repeat', $token, home_url( '/' ) ) ) );
    }

    protected static function fromToken( $token ) {
        $token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
        $gid = $token !== '' ? (int) get_transient( 'mm_repeat_' . $token ) : 0;

        return ( $gid && get_post_type( $gid ) === 'groups' ) ? $gid : 0;
    }

    public static function ajaxPrep() {
        $gid = self::fromToken( $_POST['token'] ?? '' );
        if ( ! $gid ) wp_send_json_error( 'This link has expired. Open your old group and tap &ldquo;Start a new group&rdquo; again.' );

        $members = array();
        foreach ( self::invitable( $gid ) as $uid => $name ) $members[] = array( 'id' => $uid, 'name' => $name );
        $total = count( Groups::memberIds( $gid ) );

        wp_send_json_success( array(
            'from'    => (string) get_field( 'group_name', $gid ),
            'name'    => (string) get_field( 'your_name', $gid ),
            'email'   => (string) get_field( 'your_email', $gid ),
            'members' => $members,
            'skipped' => max( 0, $total - count( $members ) ),
        ) );
    }

    /* ------------------------------------------------------------------ After the new group is saved */

    public static function afterCreate( $newGid ) {
        $from = self::fromToken( $_POST['mm_repeat'] ?? '' );
        if ( ! $from || (int) $from === (int) $newGid ) return;

        delete_transient( 'mm_repeat_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $_POST['mm_repeat'] ) );

        // Only the same organizer: the email was carried over and verified with the code.
        if ( strtolower( trim( (string) get_field( 'your_email', $newGid ) ) ) !== strtolower( trim( (string) get_field( 'your_email', $from ) ) ) ) return;

        update_post_meta( $newGid, self::FROM_META, (int) $from );
        update_post_meta( $from, self::NEXT_META, (int) $newGid );

        $pick = array_map( 'intval', (array) ( $_POST['mm_invite'] ?? array() ) );
        $allowed = self::invitable( $from );
        $sent = 0;

        foreach ( array_unique( $pick ) as $uid ) {
            if ( ! isset( $allowed[ $uid ] ) || $sent >= self::MAX_INVITES ) continue;
            if ( self::invite( $uid, $from, $newGid ) ) $sent++;
        }

        update_post_meta( $newGid, '_mm_repeat_invited', $sent );
    }

    protected static function invite( $uid, $from, $newGid ) {
        $organizer = (string) get_field( 'your_name', $newGid );
        $group = (string) get_field( 'group_name', $newGid );
        $old = (string) get_field( 'group_name', $from );
        $first = trim( (string) get_field( 'screen_name', $uid ) );
        $card = Emails::groupCard( $newGid );

        return Emails::send( (string) get_field( 'email', $uid ), $organizer . ' invited you to ' . $group, array(
            'preheader'   => 'You were in ' . $old . '. Join the new group to add your wishlist.',
            'eyebrow'     => 'You&rsquo;re invited',
            'title'       => 'Join ' . $group,
            'body'        => array(
                '<p>Hi ' . esc_html( $first !== '' ? $first : 'there' ) . ', you were in <strong>' . esc_html( $old ) . '</strong>. ' . esc_html( $organizer ) . ' started a new group and would love to have you again.</p>',
                '<p>Open the link, tap <strong>Join Group</strong> and add your wishlist. Names are shuffled once everyone is in.</p>',
            ),
            'card'        => $card,
            'button'      => array( 'Join the group', Emails::groupLink( $newGid ) ),
            'unsubscribe' => (int) $uid,
            'footer'      => 'You&rsquo;re getting this because you were a member of ' . esc_html( $old ) . ' on Manito Manita. You won&rsquo;t be added unless you join.',
        ) );
    }
}

Repeat::init();
