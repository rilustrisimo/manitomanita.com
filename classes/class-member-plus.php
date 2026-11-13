<?php
/**
 * MemberPlus: Member+, a one-time PHP 99 upgrade a member buys for their own card in one group.
 *
 * - Perks: edit their own name, screen name and email (a new email must be confirmed and can't already be
 *   used in the group), a card theme, a badge and a sparkle frame on their card in the members list.
 * - Looks show only on the screen-name card, never in Who Joined (real names), so they can't link the two.
 * - Paid through Payments (product 'plus', PayPal custom_id "gid:plus:uid"); Payments::apply calls unlock().
 * - Every endpoint needs the member's own password this session (or "remember this device").
 *
 * Front end: assets/js/mm-member-plus.js (panel, placements, checkout, GA4). Styles: mm-dashboard.css "Member+".
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MemberPlus {
    const PRICE     = 99;
    const META      = '_mm_plus';           // unlock time (0/absent = no Member+)
    const SOURCE    = '_mm_plus_by';        // 'paid' or 'admin:<login>' (given for free in WP Admin)
    const ORDERS    = '_mm_paid_orders';    // PayPal orders applied to this member
    const THEME     = '_mm_card_theme';
    const BADGE     = '_mm_card_badge';
    const FRAME     = '_mm_card_frame';
    const NEW_EMAIL = '_mm_email_new';      // pending email change (lowercase address)
    const NEW_T     = '_mm_email_new_t';    // when it was requested
    const EMAIL_REQ = '_mm_email_reqs';     // request times, for the daily limit
    const LINK_DAYS = 7;

    public static function init() {
        foreach ( array( 'mm_mplus_panel' => 'ajaxPanel', 'mm_mplus_look' => 'ajaxLook', 'mm_mplus_details' => 'ajaxDetails', 'mm_mplus_email' => 'ajaxEmail' ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
        }
        add_action( 'template_redirect', array( __CLASS__, 'confirmEmail' ), 1 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 31 );
        add_action( 'wp_footer', array( __CLASS__, 'popup' ) );
    }

    /* ------------------------------------------------------------------ Looks */

    /** key => array( label, accent, deep ) */
    public static function themes() {
        return array(
            'gold'      => array( 'Gold', '#d4af37', '#5a4300' ),
            'noche'     => array( 'Noche Buena', '#ff5a5f', '#6b1a1f' ),
            'evergreen' => array( 'Evergreen', '#34c38f', '#0d4a33' ),
            'parol'     => array( 'Parol', '#ffb020', '#6a4100' ),
            'midnight'  => array( 'Midnight', '#7aa2ff', '#1d2c66' ),
            'candy'     => array( 'Candy cane', '#ff7ac0', '#6d1846' ),
            'teal'      => array( 'Manito teal', '#3ec7c2', '#10514e' ),
        );
    }

    public static function badges() {
        return array(
            'tree' => '🎄', 'star' => '⭐', 'gift' => '🎁', 'snow' => '❄️', 'deer' => '🦌', 'bell' => '🔔',
            'cookie' => '🍪', 'snowman' => '☃️', 'heart' => '💝', 'santa' => '🎅', 'sparkle' => '✨', 'sock' => '🧦',
        );
    }

    public static function has( $uid ) {
        return (int) get_post_meta( (int) $uid, self::META, true ) > 0;
    }

    /** array( theme, badge, frame ) for a Member+ card, else empty values. */
    public static function look( $uid ) {
        if ( ! self::has( $uid ) ) return array( 'theme' => '', 'badge' => '', 'frame' => false );
        $t = (string) get_post_meta( $uid, self::THEME, true );
        $b = (string) get_post_meta( $uid, self::BADGE, true );

        return array(
            'theme' => isset( self::themes()[ $t ] ) ? $t : '',
            'badge' => isset( self::badges()[ $b ] ) ? $b : '',
            'frame' => (bool) get_post_meta( $uid, self::FRAME, true ),
        );
    }

    /** Extra classes for the member's card in the members list. */
    public static function cardClasses( $uid ) {
        $l = self::look( $uid );
        if ( ! self::has( $uid ) ) return '';

        return ' is-plus' . ( $l['theme'] ? ' mm-ct mm-ct-' . $l['theme'] : '' ) . ( $l['frame'] ? ' mm-cf' : '' );
    }

    public static function badgeHtml( $uid ) {
        $l = self::look( $uid );

        return $l['badge'] ? '<span class="mm-badge" aria-hidden="true">' . self::badges()[ $l['badge'] ] . '</span>' : '';
    }

    /**
     * Diamond-like glints scattered over a sparkle-frame card (shown only with .mm-cf). Positions, sizes and
     * timing come from the member id, so each card has its own pattern and it doesn't jump on reload.
     */
    public static function glints( $uid, $n = 12 ) {
        $h = '<span class="mm-glints" aria-hidden="true">';
        for ( $i = 0; $i < $n; $i++ ) {
            $r = crc32( 'g|' . (int) $uid . '|' . $i );
            $x = 4 + ( $r % 92 );                       // 4-95 %
            $y = 8 + ( ( $r >> 7 ) % 84 );              // 8-91 %
            $s = 14 + ( ( $r >> 13 ) % 13 );            // 14-26 px
            $d = round( ( ( $r >> 17 ) % 40 ) / 10, 1 ); // 0-3.9 s delay
            $t = round( 2.4 + ( ( $r >> 21 ) % 16 ) / 10, 1 ); // 2.4-3.9 s
            $h .= '<i style="left:' . $x . '%;top:' . $y . '%;--gs:' . $s . 'px;--gd:' . $d . 's;--gt:' . $t . 's"></i>';
        }

        return $h . '</span>';
    }

    /** Hidden trigger on every card: the popup's Member+ button (get it, or its settings) clicks it, so the card's password flow runs as usual. */
    public static function chip( $uid ) {
        return '<a href="#" class="user-action mm-plus-chip" user-data="' . (int) $uid . '" data-action="member-plus" aria-hidden="true" tabindex="-1">Member+</a>';
    }

    /* ------------------------------------------------------------------ Access */

    /** This member entered their own password this session (or this device is remembered for them). */
    public static function verified( $uid ) {
        $uid = (int) $uid;
        if ( get_post_type( $uid ) !== 'users' ) return false;
        $t = isset( $_SESSION['mm_verified'][ $uid ] ) ? (int) $_SESSION['mm_verified'][ $uid ] : 0;

        return $t > time() - 2 * HOUR_IN_SECONDS || ( class_exists( 'Account' ) && Account::restore( $uid ) );
    }

    protected static function groupOf( $uid ) {
        return (int) get_field( 'groups', (int) $uid, false );
    }

    /** '' when this member may buy Member+ for their card in this group, else the reason. */
    public static function cantBuy( $uid, $gid ) {
        $uid = (int) $uid;
        if ( get_post_type( $uid ) !== 'users' || get_post_status( $uid ) !== 'publish' || self::groupOf( $uid ) !== (int) $gid ) return 'This card is not in this group.';
        if ( ! self::verified( $uid ) ) return 'Please open your card with your password again.';
        if ( self::has( $uid ) ) return 'Your card already has Member+.';

        return '';
    }

    /** Called by Payments::apply for a COMPLETED order. true, or a reason string. */
    public static function unlock( $uid, $gid, $orderId ) {
        $uid = (int) $uid;
        if ( get_post_type( $uid ) !== 'users' || self::groupOf( $uid ) !== (int) $gid ) return 'unknown member ' . $uid;

        $paid = array_filter( (array) get_post_meta( $uid, self::ORDERS, true ) );
        if ( in_array( $orderId, $paid, true ) ) return true; // already applied
        if ( self::has( $uid ) ) do_action( 'mm_alert', 'payment', 'Member+ paid twice for the same card: refund one', array( 'member' => $uid, 'order' => $orderId ) );

        $paid[] = $orderId;
        update_post_meta( $uid, self::ORDERS, array_values( $paid ) );
        self::grant( $uid, 'paid' );
        self::mailUnlocked( $uid, $gid );

        return true;
    }

    /** Turns Member+ on (paid, or given in WP Admin). Keeps a look chosen earlier; new cards start gold with the frame. */
    public static function grant( $uid, $by ) {
        $uid = (int) $uid;
        if ( get_post_type( $uid ) !== 'users' ) return false;
        $was = self::has( $uid );
        if ( ! $was ) update_post_meta( $uid, self::META, time() );
        update_post_meta( $uid, self::SOURCE, $by === 'paid' || ! $was ? $by : get_post_meta( $uid, self::SOURCE, true ) );
        if ( get_post_meta( $uid, self::THEME, true ) === '' ) {
            update_post_meta( $uid, self::THEME, 'gold' );
            update_post_meta( $uid, self::FRAME, 1 );
        }
        if ( ! $was && $by !== 'paid' && class_exists( 'Insights' ) ) Insights::log( 'mp_grant', self::groupOf( $uid ), $uid, 0, $by );

        return true;
    }

    /** Turns Member+ off (WP Admin). The look is kept, so giving it back restores it. Never refunds. */
    public static function revoke( $uid, $by ) {
        $uid = (int) $uid;
        if ( ! self::has( $uid ) ) return false;
        delete_post_meta( $uid, self::META );
        delete_post_meta( $uid, self::NEW_EMAIL );
        delete_post_meta( $uid, self::NEW_T );
        if ( class_exists( 'Insights' ) ) Insights::log( 'mp_revoke', self::groupOf( $uid ), $uid, 0, $by );

        return true;
    }

    /* ------------------------------------------------------------------ Checks */

    /** Other members of the group (joined or pending) whose screen name matches, ignoring case. */
    protected static function screenTaken( $gid, $screen, $uid ) {
        $want = mb_strtolower( trim( $screen ) );
        foreach ( self::groupMembers( $gid ) as $id ) {
            if ( $id !== (int) $uid && mb_strtolower( trim( (string) get_field( 'screen_name', $id ) ) ) === $want ) return true;
        }

        return false;
    }

    protected static function groupMembers( $gid ) {
        return array_map( 'intval', get_posts( array( 'post_type' => 'users', 'post_status' => array( 'publish', 'pending' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => 'groups', 'value' => (int) $gid ) ) ) ) );
    }

    /** The email is already used in the group: by a member (joined or still confirming) or as someone's pending change. */
    public static function emailTaken( $gid, $email, $uid ) {
        $email = strtolower( trim( (string) $email ) );
        if ( class_exists( 'MemberRules' ) ) {
            $f = MemberRules::find( $gid, $email, $uid );
            if ( $f['publish'] || $f['pending'] ) return true;
        } else {
            foreach ( self::groupMembers( $gid ) as $id ) {
                if ( $id !== (int) $uid && strtolower( trim( (string) get_post_meta( $id, 'email', true ) ) ) === $email ) return true;
            }
        }
        foreach ( self::groupMembers( $gid ) as $id ) {
            if ( $id !== (int) $uid && (string) get_post_meta( $id, self::NEW_EMAIL, true ) === $email && self::pendingFresh( $id ) ) return true;
        }

        return false;
    }

    protected static function pendingFresh( $uid ) {
        return (int) get_post_meta( $uid, self::NEW_T, true ) > time() - self::LINK_DAYS * DAY_IN_SECONDS;
    }

    protected static function token( $uid, $email, $t ) {
        return substr( hash_hmac( 'sha256', 'mpe|' . (int) $uid . '|' . $email . '|' . (int) $t, wp_salt( 'auth' ) ), 0, 32 );
    }

    public static function confirmUrl( $uid ) {
        $email = (string) get_post_meta( $uid, self::NEW_EMAIL, true );
        $t = (int) get_post_meta( $uid, self::NEW_T, true );

        return add_query_arg( array( 'mm_mpe' => (int) $uid, 'e' => $t, 't' => self::token( $uid, $email, $t ) ), home_url( '/' ) );
    }

    /* ------------------------------------------------------------------ AJAX */

    /** Common checks: nonce, the member's own password, and (for perks) Member+. Returns the uid. */
    protected static function guard( $needPlus = true ) {
        if ( ! check_ajax_referer( 'mm_mplus', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'This page has expired. Please refresh.' ) );
        $uid = absint( $_POST['uid'] ?? 0 );
        if ( ! self::verified( $uid ) ) wp_send_json_error( array( 'message' => 'Please open your card with your password again.', 'reauth' => 1 ) );
        if ( $needPlus && ! self::has( $uid ) ) wp_send_json_error( array( 'message' => 'Get Member+ first.' ) );

        return $uid;
    }

    public static function ajaxPanel() {
        $uid = self::guard( false );
        wp_send_json_success( array( 'html' => self::panel( $uid ), 'plus' => self::has( $uid ), 'look' => self::look( $uid ) ) );
    }

    public static function ajaxLook() {
        $uid = self::guard();
        $theme = sanitize_key( (string) ( $_POST['theme'] ?? '' ) );
        $badge = sanitize_key( (string) ( $_POST['badge'] ?? '' ) );
        $frame = ! empty( $_POST['frame'] ) && $_POST['frame'] !== '0';
        if ( $theme !== '' && ! isset( self::themes()[ $theme ] ) ) wp_send_json_error( array( 'message' => 'Unknown theme.' ) );
        if ( $badge !== '' && ! isset( self::badges()[ $badge ] ) ) wp_send_json_error( array( 'message' => 'Unknown badge.' ) );

        $before = self::look( $uid );
        update_post_meta( $uid, self::THEME, $theme );
        update_post_meta( $uid, self::BADGE, $badge );
        update_post_meta( $uid, self::FRAME, $frame ? 1 : 0 );
        foreach ( array( 'theme' => $theme, 'badge' => $badge, 'frame' => $frame ) as $k => $v ) {
            if ( $before[ $k ] !== $v ) self::used( $uid, $k );
        }

        wp_send_json_success( array( 'look' => self::look( $uid ), 'classes' => trim( self::cardClasses( $uid ) ), 'badge' => self::badgeHtml( $uid ) ) );
    }

    public static function ajaxDetails() {
        $uid = self::guard();
        $gid = self::groupOf( $uid );
        $name = sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) );
        $screen = sanitize_text_field( wp_unslash( (string) ( $_POST['screen'] ?? '' ) ) );

        if ( $name === '' || mb_strlen( $name ) > 60 ) wp_send_json_error( array( 'message' => 'Enter your name (up to 60 characters).' ) );
        if ( $screen === '' || mb_strlen( $screen ) > 40 ) wp_send_json_error( array( 'message' => 'Enter a screen name (up to 40 characters).' ) );
        if ( self::screenTaken( $gid, $screen, $uid ) ) wp_send_json_error( array( 'message' => 'Someone in this group already uses that screen name.' ) );

        if ( $name !== (string) get_field( 'name', $uid ) ) { update_field( 'name', $name, $uid ); self::used( $uid, 'name' ); }
        if ( $screen !== (string) get_field( 'screen_name', $uid ) ) { update_field( 'screen_name', $screen, $uid ); self::used( $uid, 'screen_name' ); }

        wp_send_json_success( array( 'message' => 'Saved.', 'screen' => $screen ) );
    }

    public static function ajaxEmail() {
        $uid = self::guard();
        $gid = self::groupOf( $uid );
        $op = sanitize_key( (string) ( $_POST['op'] ?? 'request' ) );

        if ( $op === 'cancel' ) {
            delete_post_meta( $uid, self::NEW_EMAIL );
            delete_post_meta( $uid, self::NEW_T );
            wp_send_json_success( array( 'message' => 'Email change cancelled.', 'html' => self::emailBox( $uid ) ) );
        }

        $email = $op === 'resend' ? (string) get_post_meta( $uid, self::NEW_EMAIL, true ) : strtolower( trim( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) ) );
        if ( $email === '' || ! is_email( $email ) ) wp_send_json_error( array( 'message' => 'Enter a valid email address.' ) );
        if ( $email === strtolower( trim( (string) get_field( 'email', $uid ) ) ) ) wp_send_json_error( array( 'message' => 'That&rsquo;s already your email.' ) );
        if ( class_exists( 'EmailCheck' ) && EmailCheck::isDisposable( $email ) ) wp_send_json_error( array( 'message' => 'Please use a permanent email address.' ) );
        if ( self::emailTaken( $gid, $email, $uid ) ) wp_send_json_error( array( 'message' => 'This email is already used in this group. Each person needs their own email.' ) );

        $reqs = array_filter( (array) get_post_meta( $uid, self::EMAIL_REQ, true ), function ( $t ) { return (int) $t > time() - DAY_IN_SECONDS; } );
        if ( count( $reqs ) >= 3 ) wp_send_json_error( array( 'message' => 'You&rsquo;ve asked for 3 email changes today. Please try again tomorrow.' ) );
        $reqs[] = time();
        update_post_meta( $uid, self::EMAIL_REQ, array_values( $reqs ) );

        if ( $op !== 'resend' ) {
            update_post_meta( $uid, self::NEW_EMAIL, $email );
            update_post_meta( $uid, self::NEW_T, time() );
        }
        self::mailConfirm( $uid, $gid );
        if ( $op !== 'resend' ) self::mailOldNotice( $uid, $gid );

        wp_send_json_success( array( 'message' => 'Check ' . esc_html( $email ) . ' and tap the link to confirm.', 'html' => self::emailBox( $uid ) ) );
    }

    /** ?mm_mpe=uid&e=time&t=token from the confirm email: checks everything again, then switches the email. */
    public static function confirmEmail() {
        if ( empty( $_GET['mm_mpe'] ) ) return;
        $uid = absint( $_GET['mm_mpe'] );
        $gid = self::groupOf( $uid );
        $email = (string) get_post_meta( $uid, self::NEW_EMAIL, true );
        $t = (int) get_post_meta( $uid, self::NEW_T, true );
        $state = 'expired';

        if ( get_post_type( $uid ) === 'users' && $email !== '' && (int) ( $_GET['e'] ?? 0 ) === $t && hash_equals( self::token( $uid, $email, $t ), (string) ( $_GET['t'] ?? '' ) ) && self::pendingFresh( $uid ) ) {
            if ( self::emailTaken( $gid, $email, $uid ) ) {
                $state = 'taken';
            } else {
                update_field( 'email', $email, $uid );
                if ( class_exists( 'EmailCheck' ) ) update_post_meta( $uid, EmailCheck::VERIFIED_META, $email );
                self::used( $uid, 'email' );
                $state = 'ok';
            }
            delete_post_meta( $uid, self::NEW_EMAIL );
            delete_post_meta( $uid, self::NEW_T );
        }

        $to = $gid && class_exists( 'Emails' ) ? Emails::groupLink( $gid ) : home_url( '/' );
        wp_safe_redirect( add_query_arg( 'mp_email', $state, $to ) );
        exit;
    }

    /** Site-side usage log (Insights), next to GA4's mp_use. */
    protected static function used( $uid, $feature ) {
        if ( class_exists( 'Insights' ) ) Insights::log( 'mp_use', self::groupOf( $uid ), (int) $uid, 0, $feature );
    }

    /* ------------------------------------------------------------------ Panel */

    public static function panel( $uid ) {
        $screen = trim( (string) get_field( 'screen_name', $uid ) );
        $initial = mb_strtoupper( mb_substr( $screen, 0, 1 ) );
        $look = self::look( $uid );
        $plus = self::has( $uid );
        $preview = '<div class="mp-preview" data-mp-preview><div class="mp-card mm-ct' . ( $look['theme'] ? ' mm-ct-' . $look['theme'] : ' mm-ct-gold' ) . ( $look['frame'] || ! $plus ? ' mm-cf' : '' ) . '" data-mp-card>'
            . '<span class="mm-avatar" aria-hidden="true">' . esc_html( $initial ) . '</span>'
            . '<span class="mp-card__text"><span class="mp-card__name">' . esc_html( $screen ) . '</span><span class="mp-card__badge" data-mp-badge>' . ( $look['badge'] ? self::badges()[ $look['badge'] ] : ( $plus ? '' : '🎄' ) ) . '</span></span>'
            . '<span class="mp-card__tag">Member+</span>' . self::glints( $uid, 7 ) . '</div></div>';

        $swatches = '';
        foreach ( self::themes() as $k => $t ) {
            $on = $plus ? $look['theme'] === $k : $k === 'gold';
            $swatches .= '<button type="button" class="mp-swatch' . ( $on ? ' is-on' : '' ) . '" data-mp-theme="' . esc_attr( $k ) . '" style="--sw:' . esc_attr( $t[1] ) . '" aria-label="' . esc_attr( $t[0] ) . '" title="' . esc_attr( $t[0] ) . '"></button>';
        }
        $badges = '<button type="button" class="mp-badge-opt' . ( $plus && ! $look['badge'] ? ' is-on' : '' ) . '" data-mp-badge-key="" aria-label="No badge">&times;</button>';
        foreach ( self::badges() as $k => $e ) {
            $on = $plus ? $look['badge'] === $k : $k === 'tree';
            $badges .= '<button type="button" class="mp-badge-opt' . ( $on ? ' is-on' : '' ) . '" data-mp-badge-key="' . esc_attr( $k ) . '" data-emoji="' . esc_attr( $e ) . '">' . $e . '</button>';
        }
        $frameOn = $plus ? $look['frame'] : true;
        $lookTools = '<div class="mp-tools">'
            . '<div class="mp-row"><span class="mp-label">Color</span><div class="mp-swatches">' . $swatches . '</div></div>'
            . '<div class="mp-row"><span class="mp-label">Badge</span><div class="mp-badges">' . $badges . '</div></div>'
            . '<label class="mp-row mp-switch"><span class="mp-label">Sparkle frame</span><input type="checkbox" data-mp-frame' . ( $frameOn ? ' checked' : '' ) . '><span class="mp-switch__ui" aria-hidden="true"></span></label>'
            . '</div>';

        $h = '<div class="mp" data-mp data-uid="' . (int) $uid . '" data-plus="' . ( $plus ? 1 : 0 ) . '">';
        if ( ! $plus ) {
            $h .= '<div class="mp-hero"><span class="mp-kicker">✨ Member+</span><h3>Make your card stand out</h3><p>Try a look on your card, then keep it for <strong>&#8369;' . self::PRICE . '</strong>, one time.</p></div>'
                . $preview . $lookTools
                . '<ul class="mp-perks">'
                . '<li><i class="fa-solid fa-palette" aria-hidden="true"></i><span><b>Your own color, badge and sparkle frame</b>, seen by everyone in the group</span></li>'
                . '<li><i class="fa-solid fa-pen" aria-hidden="true"></i><span><b>Change your name, screen name or email</b> anytime, even after the shuffle</span></li>'
                . '<li><i class="fa-solid fa-image" aria-hidden="true"></i><span><b>Wishlist photos, a pinned top wish</b> and your sizes and likes, so your Secret Santa gets it right</span></li>'
                . '<li><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i><span><b>A thank-you card</b> for your Secret Santa after the exchange</span></li>'
                . '<li><i class="fa-solid fa-user-secret" aria-hidden="true"></i><span>Only your card changes. Your real name and match stay secret.</span></li>'
                . '</ul>'
                . '<div class="mp-pay" data-mp-pay>'
                . '<div class="mp-pay__head"><div class="mp-pay__what"><strong>Member+ for your card</strong><span>One time &middot; only your card in this group</span></div><div class="mp-pay__amount"><small>&#8369;</small>' . self::PRICE . '</div></div>'
                . '<div class="mp-pay__btns"><div id="mp-card-container"></div><div class="mp-or"><span>or</span></div><div id="mp-paypal-container"></div></div>'
                . '<p class="mp-fine"><i class="fa-solid fa-lock" aria-hidden="true"></i> Secure checkout by PayPal &middot; any debit or credit card, no PayPal account needed</p></div>';
        } else {
            $h .= '<div class="mp-hero is-plus"><span class="mp-kicker">✨ Member+</span><h3>Your card</h3><p>Changes show right away for everyone in the group.</p></div>'
                . self::tabs( array_merge(
                    array( 'look' => array( 'Look', $preview . self::section( 'fa-solid fa-palette', 'Card look', 'Tap to change. Everyone in the group sees it right away.', $lookTools ) ) ),
                    (array) apply_filters( 'mm_mplus_tabs', array(), $uid ), // Wishlist+, Thank you
                    array( 'details' => array( 'Details', self::detailsForm( $uid ) ), 'email' => array( 'Email', self::emailBox( $uid ) ) )
                ) );
        }

        return $h . '</div>';
    }

    /** array( key => array( label, html ) ) as tabs; the first one is open. */
    protected static function tabs( array $tabs ) {
        $nav = '';
        $panes = '';
        $first = true;
        foreach ( $tabs as $k => $t ) {
            $nav .= '<button type="button"' . ( $first ? ' class="is-on"' : '' ) . ' data-mp-tab="' . esc_attr( $k ) . '">' . esc_html( $t[0] ) . '</button>';
            $panes .= '<section data-mp-pane="' . esc_attr( $k ) . '"' . ( $first ? '' : ' hidden' ) . '>' . $t[1] . '</section>';
            $first = false;
        }

        return '<div class="mp-tabs" role="tablist">' . $nav . '</div>' . $panes;
    }

    /** A panel section card: icon, title, one-line explainer, then the body. */
    public static function section( $icon, $title, $note, $body, $attrs = '' ) {
        return '<section class="mp-card-sec"' . $attrs . '><header class="mp-sec-head"><i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i><div><h4>' . $title . '</h4>' . ( $note !== '' ? '<p>' . $note . '</p>' : '' ) . '</div></header>' . $body . '</section>';
    }

    protected static function detailsForm( $uid ) {
        $gid = self::groupOf( $uid );
        $after = get_field( 'matched', $gid ) ? '<p class="mp-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> The group is shuffled: your Secret Santa will see your new screen name.</p>' : '';

        return self::section( 'fa-solid fa-id-card', 'Your details', 'Your real name shows only in Who Joined, never next to your screen name.',
            '<form class="mp-form" data-mp-details><div class="mp-grid2">'
            . '<label class="mp-field"><span>Your name</span><input type="text" name="name" maxlength="60" required value="' . esc_attr( (string) get_field( 'name', $uid ) ) . '"></label>'
            . '<label class="mp-field"><span>Screen name</span><input type="text" name="screen" maxlength="40" required value="' . esc_attr( (string) get_field( 'screen_name', $uid ) ) . '"></label>'
            . '</div>' . $after . '<div class="mp-form__foot"><button type="submit" class="mp-btn">Save</button><p class="mp-msg" data-mp-msg hidden></p></div></form>' );
    }

    public static function emailBox( $uid ) {
        $current = (string) get_field( 'email', $uid );
        $pending = self::pendingFresh( $uid ) ? (string) get_post_meta( $uid, self::NEW_EMAIL, true ) : '';
        if ( $pending !== '' ) {
            $body = '<div class="mp-pending"><i class="fa-regular fa-envelope" aria-hidden="true"></i><div><strong>Waiting for confirmation</strong><span>We sent a link to ' . esc_html( $pending ) . '. Your current email stays active until you tap it.</span></div></div>'
                . '<div class="mp-form__foot"><button type="button" class="mp-btn mp-btn--ghost" data-mp-email-op="resend">Resend link</button><button type="button" class="mp-btn mp-btn--ghost" data-mp-email-op="cancel">Cancel change</button></div>';
        } else {
            $body = '<form class="mp-form" data-mp-email><label class="mp-field"><span>New email</span><input type="email" name="email" required autocomplete="email" placeholder="name@example.com"></label>'
                . '<div class="mp-form__foot"><button type="submit" class="mp-btn">Send confirmation link</button></div></form>';
        }

        return self::section( 'fa-solid fa-envelope', 'Your email', 'Now: <strong>' . esc_html( $current ) . '</strong>. A new one must be confirmed, and can&rsquo;t be one someone in this group already uses.',
            $body . '<p class="mp-msg" data-mp-msg hidden></p>', ' data-mp-emailbox' );
    }

    /* ------------------------------------------------------------------ Page */

    public static function assets() {
        if ( ! is_page( 23 ) ) return;
        $f = '/assets/js/mm-member-plus.js';
        wp_enqueue_script( 'mm-member-plus', get_template_directory_uri() . $f, array( 'jquery' ), (string) @filemtime( get_template_directory() . $f ), true );
        $pp = class_exists( 'Payments' ) && Payments::enabled();
        wp_localize_script( 'mm-member-plus', 'mmPlus', array(
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mm_mplus' ),
            'price'   => self::PRICE,
            'pp'      => $pp ? array( 'client' => PAYPAL_CLIENT_ID, 'nonce' => wp_create_nonce( 'mm_paypal' ) ) : null,
        ) );
    }

    public static function popup() {
        if ( ! is_page( 23 ) ) return;
        echo '<div id="mp-popup" class="popup" role="dialog" aria-label="Member+"><div class="popup__title">MEMBER<span>+</span></div><div class="popup__divider"></div><div class="popup__inner" data-mp-inner></div></div>';
    }

    /* ------------------------------------------------------------------ Emails */

    protected static function mailUnlocked( $uid, $gid ) {
        if ( ! class_exists( 'Emails' ) ) return;
        $group = (string) get_field( 'group_name', $gid );
        Emails::send( (string) get_field( 'email', $uid ), 'Member+ is on for your card in ' . $group, array( 'essential' => true,
            'preheader' => 'Your card now stands out. Change its look or your details anytime.',
            'eyebrow'   => 'Thank you!',
            'title'     => 'Your card has Member+',
            'body'      => array(
                '<p>Your card in <strong>' . esc_html( $group ) . '</strong> now has a gold look and a sparkle frame. Open your card and tap <strong>Member+</strong> to pick another color or badge, or to change your name, screen name or email.</p>',
                '<p>Payment: &#8369;' . self::PRICE . ' (one time, PayPal). Only your card changes; your real name and match stay secret.</p>',
            ),
            'card'      => Emails::groupCard( $gid ),
            'button'    => array( 'Open the group', Emails::groupLink( $gid ) ),
            'footer'    => 'You&rsquo;re getting this because you bought Member+ on Manito Manita.',
        ) );
    }

    protected static function mailConfirm( $uid, $gid ) {
        if ( ! class_exists( 'Emails' ) ) return;
        $group = (string) get_field( 'group_name', $gid );
        Emails::send( (string) get_post_meta( $uid, self::NEW_EMAIL, true ), 'Confirm your new email for ' . $group, array( 'essential' => true,
            'preheader' => 'One tap to use this email in ' . $group . '.',
            'eyebrow'   => 'Email change',
            'title'     => 'Confirm your new email',
            'body'      => array( '<p>Tap the button to use this address for your card in <strong>' . esc_html( $group ) . '</strong>. Your match and reminders will come here after that.</p>' ),
            'button'    => array( 'Confirm this email', self::confirmUrl( $uid ) ),
            'note'      => 'The link works for ' . self::LINK_DAYS . ' days. Didn&rsquo;t ask for this? Just ignore this email; nothing changes.',
            'footer'    => 'You&rsquo;re getting this because someone asked to use this address on Manito Manita.',
        ) );
    }

    protected static function mailOldNotice( $uid, $gid ) {
        if ( ! class_exists( 'Emails' ) ) return;
        $group = (string) get_field( 'group_name', $gid );
        Emails::send( (string) get_field( 'email', $uid ), 'Your email change for ' . $group, array( 'essential' => true,
            'preheader' => 'Waiting for the new address to be confirmed.',
            'eyebrow'   => 'Email change',
            'title'     => 'Your email change is waiting for confirmation',
            'body'      => array( '<p>Someone with your card&rsquo;s password asked to change your email in <strong>' . esc_html( $group ) . '</strong>. Nothing changes until the new address is confirmed; until then everything still comes here.</p>' ),
            'note'      => 'Didn&rsquo;t ask for this? Reply to this email and we&rsquo;ll help, and change your card&rsquo;s password with &ldquo;Forgot Password?&rdquo;.',
            'footer'    => 'You&rsquo;re getting this because your email is on a card in ' . esc_html( $group ) . ' on Manito Manita.',
        ) );
    }
}

MemberPlus::init();
