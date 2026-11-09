<?php
/**
 * PRO group extras: a colour theme for the group page, and a co-organizer.
 *
 * Theme: one preset per group (post meta _mm_gtheme), applied on the group dashboard as a body
 * class (mm-gtheme mm-gtheme-{key}); styles in mm-dashboard.css "Group themes".
 *
 * Co-organizer: a second person who can open PRO Actions with their own password (post meta
 * _mm_comod: name, email, hashed password). They can do everything the organizer can, except add or
 * remove a co-organizer. Users::verify_password() calls checkCoOrganizer() when the main password
 * doesn't match.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class GroupExtras {
    const THEME_META = '_mm_gtheme';
    const COMOD_META = '_mm_comod';

    protected static $hooked = false;

    /**
     * "Share to Teams" link for the group link (Microsoft Teams is the top referral: office groups). Opens
     * Teams' share page with the link and a short message; tracked as share_teams with the slot.
     */
    public static function teamsShare( $gid, $slot, $class = 'mm-teams-link' ) {
        $gid = (int) $gid;
        if ( ! $gid || get_post_type( $gid ) !== 'groups' ) return '';
        $name = html_entity_decode( (string) get_field( 'group_name', $gid ), ENT_QUOTES, 'UTF-8' );
        $url = add_query_arg( array(
            'href'    => rawurlencode( Emails::groupLink( $gid ) ),
            'msgText' => rawurlencode( 'Join our gift exchange' . ( $name !== '' ? ': ' . $name : '' ) . '. Open the link, tap Join Group and add your wishlist.' ),
            'preview' => 'true',
        ), 'https://teams.microsoft.com/share' );

        return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" data-mm-track="share_teams" data-mm-slot="' . esc_attr( $slot ) . '"><i class="fa-brands fa-microsoft" aria-hidden="true"></i> Share to Teams</a>';
    }

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_filter( 'body_class', array( __CLASS__, 'bodyClass' ), 20 );
    }

    /** key => array(label, accent, card colour) — the swatches in the picker use the same colours. */
    public static function themes() {
        return array(
            ''          => array( 'Classic gold', '#d4af37', '#1b1b1b' ),
            'noche'     => array( 'Noche Buena', '#ff5a5f', '#3a0f14' ),
            'evergreen' => array( 'Evergreen', '#34c38f', '#0d2a20' ),
            'parol'     => array( 'Parol', '#ffb020', '#2e1c06' ),
            'midnight'  => array( 'Midnight', '#7aa2ff', '#111a38' ),
            'candy'     => array( 'Candy cane', '#ff7ac0', '#3a1030' ),
            'teal'      => array( 'Manito teal', '#3ec7c2', '#10302f' ),
        );
    }

    public static function theme( $gid ) {
        $key = (string) get_post_meta( (int) $gid, self::THEME_META, true );

        return array_key_exists( $key, self::themes() ) ? $key : '';
    }

    public static function bodyClass( $classes ) {
        if ( ! is_page( 23 ) || empty( $_GET['gid'] ) ) return $classes;

        $gid = (int) $_GET['gid'];
        if ( get_post_type( $gid ) !== 'groups' || ! get_field( 'pro', $gid ) ) return $classes;

        $key = self::theme( $gid );
        if ( $key !== '' ) {
            $classes[] = 'mm-gtheme';
            $classes[] = 'mm-gtheme-' . $key;
        }

        return $classes;
    }

    /* ------------------------------------------------------------------ Co-organizer */

    public static function coOrganizer( $gid ) {
        $c = get_post_meta( (int) $gid, self::COMOD_META, true );

        return ( is_array( $c ) && ! empty( $c['hash'] ) ) ? $c : null;
    }

    /** Password check for the co-organizer (PRO groups only). Marks the session as a co-organizer. */
    public static function checkCoOrganizer( $gid, $password ) {
        $gid = (int) $gid;
        $c = self::coOrganizer( $gid );
        if ( ! $c || ! get_field( 'pro', $gid ) || $password === '' || ! wp_check_password( (string) $password, $c['hash'] ) ) return false;

        $_SESSION['mm_co'][ $gid ] = time();

        return true;
    }

    /** True when this session opened PRO Actions with the co-organizer password. */
    public static function isCo( $gid ) {
        return ! empty( $_SESSION['mm_co'][ (int) $gid ] );
    }

    /** The main organizer logged in: drop a co-organizer mark from an earlier login in this browser. */
    public static function markOrganizer( $gid ) {
        unset( $_SESSION['mm_co'][ (int) $gid ] );
    }

    /* ------------------------------------------------------------------ REST */

    public static function routes() {
        register_rest_route( 'custom-webhook/v1', '/group-theme', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restTheme' ),
            'permission_callback' => array( __CLASS__, 'canUsePro' ),
        ) );
        register_rest_route( 'custom-webhook/v1', '/co-organizer', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restCoOrganizer' ),
            'permission_callback' => array( __CLASS__, 'canUsePro' ),
        ) );
    }

    public static function canUsePro( $request ) {
        $gid = (int) $request->get_param( 'group_id' );

        if ( ! Groups::isModerator( $gid ) ) return new WP_Error( 'mm_forbidden', 'Please enter the moderator password again.', array( 'status' => 403 ) );
        if ( ! get_field( 'pro', $gid ) ) return new WP_Error( 'mm_not_pro', 'This is a PRO feature.', array( 'status' => 403 ) );

        return true;
    }

    public static function restTheme( $request ) {
        $gid = (int) $request->get_param( 'group_id' );
        $themes = self::themes();

        if ( $request->get_param( 'op' ) === 'set' ) {
            $key = (string) $request->get_param( 'theme' );
            if ( ! array_key_exists( $key, $themes ) ) return new WP_Error( 'mm_bad_theme', 'Unknown theme.', array( 'status' => 400 ) );

            if ( $key === '' ) delete_post_meta( $gid, self::THEME_META );
            else update_post_meta( $gid, self::THEME_META, $key );
        }

        $list = array();
        foreach ( $themes as $k => $t ) $list[] = array( 'key' => $k, 'label' => $t[0], 'accent' => $t[1], 'card' => $t[2] );

        return array( 'success' => true, 'current' => self::theme( $gid ), 'themes' => $list );
    }

    public static function restCoOrganizer( $request ) {
        $gid = (int) $request->get_param( 'group_id' );
        $op = (string) $request->get_param( 'op' );
        $c = self::coOrganizer( $gid );

        if ( in_array( $op, array( 'add', 'remove', 'reset' ), true ) && self::isCo( $gid ) ) {
            return new WP_Error( 'mm_main_only', 'Only the main organizer can change the co-organizer.', array( 'status' => 403 ) );
        }

        if ( $op === 'add' ) {
            $name = sanitize_text_field( (string) $request->get_param( 'name' ) );
            $email = sanitize_email( (string) $request->get_param( 'email' ) );

            if ( $name === '' ) return new WP_Error( 'mm_name', 'Please enter their name.', array( 'status' => 400 ) );
            if ( ! is_email( $email ) ) return new WP_Error( 'mm_email', 'Please enter a valid email address.', array( 'status' => 400 ) );
            if ( strtolower( $email ) === strtolower( (string) get_field( 'your_email', $gid ) ) ) return new WP_Error( 'mm_email', 'That&rsquo;s your own email. Add the other person&rsquo;s email.', array( 'status' => 400 ) );

            $c = array( 'name' => $name, 'email' => $email, 'added' => time() );
            $sent = self::sendPassword( $gid, $c );
            if ( ! $sent ) return new WP_Error( 'mm_mail', 'We couldn&rsquo;t send the email. Please check the address and try again.', array( 'status' => 500 ) );
        } elseif ( $op === 'reset' ) {
            if ( ! $c ) return new WP_Error( 'mm_none', 'There&rsquo;s no co-organizer yet.', array( 'status' => 400 ) );
            if ( ! self::sendPassword( $gid, $c ) ) return new WP_Error( 'mm_mail', 'We couldn&rsquo;t send the email. Please try again.', array( 'status' => 500 ) );
        } elseif ( $op === 'remove' ) {
            delete_post_meta( $gid, self::COMOD_META );
            $c = null;
        }

        $c = self::coOrganizer( $gid );

        return array(
            'success' => true,
            'isCo'    => self::isCo( $gid ),
            'co'      => $c ? array( 'name' => $c['name'], 'email' => $c['email'], 'added' => wp_date( 'M j, Y', (int) $c['added'] ) ) : null,
        );
    }

    /**
     * New random password for the co-organizer: saved (hashed) and emailed to them with the group link.
     */
    protected static function sendPassword( $gid, array $c ) {
        $password = wp_generate_password( 10, false );
        $group = (string) get_field( 'group_name', $gid );
        $organizer = (string) get_field( 'your_name', $gid );

        $sent = Emails::send( $c['email'], 'You&rsquo;re now a co-organizer of ' . $group, array(
            'preheader' => $organizer . ' added you as a co-organizer. Here&rsquo;s your password for PRO Actions.',
            'eyebrow'   => 'Co-organizer',
            'title'     => 'You can now help run ' . $group,
            'body'      => array(
                '<p>Hi ' . esc_html( $c['name'] ) . ', <strong>' . esc_html( $organizer ) . '</strong> added you as a co-organizer. You can shuffle names, manage members, send reminders and use every PRO tool.</p>',
                '<p>Open the group, tap <strong>PRO Actions</strong> and enter this password:</p>',
            ),
            'code'      => $password,
            'button'    => array( 'Open the group', Emails::groupLink( $gid ) ),
            'note'      => 'Keep this password to yourself. If you didn&rsquo;t expect this email, you can ignore it.',
            'footer'    => 'You&rsquo;re getting this because ' . esc_html( $organizer ) . ' added you to ' . esc_html( $group ) . ' on Manito Manita.',
        ) );

        if ( ! $sent ) return false;

        $c['hash'] = wp_hash_password( $password );
        update_post_meta( (int) $gid, self::COMOD_META, $c );

        return true;
    }
}

GroupExtras::init();
