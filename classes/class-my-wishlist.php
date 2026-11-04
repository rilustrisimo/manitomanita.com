<?php
/**
 * My Wishlist: a personal wishlist that isn't tied to a group, with its own share link
 * (/wishlist/<slug>/) for birthdays, debuts, weddings or any day. Friends can tap "I'll get this" so
 * nobody buys the same thing; the owner never sees what's reserved (it stays a surprise).
 *
 * Storage: CPT mm_wishlist (not public). Meta: _mm_wl_name, _mm_wl_email (verified with the 6-digit
 * code), _mm_wl_hash (password), _mm_wl_items (same rows as a group wishlist), _mm_wl_note,
 * _mm_wl_res (reservations keyed by item text hash: a list of { by, t, h }; an old single entry still reads).
 * Occasion fields (registries): see class-registries.php. One email can own several lists (same password).
 * Pages: /my-wishlist/ (real WP page, template-my-wishlist.php, created once) and /wishlist/<slug>/
 * (rewrite to the same page with mm_wl=<slug>; noindex). Product links earn via Affiliate.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MyWishlist {
    const CPT       = 'mm_wishlist';
    const PAGE_SLUG = 'my-wishlist';
    const MAX_ITEMS = 30;
    const MAX_NOTE  = 160;
    const MAX_QTY   = 12;
    const MAX_QR    = 2097152; // 2 MB
    const LOGIN_TTL = HOUR_IN_SECONDS;

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'wp_loaded', array( __CLASS__, 'setupOnce' ) );
        add_action( 'wp_loaded', array( __CLASS__, 'seoOnce' ) );
        add_action( 'template_redirect', array( __CLASS__, 'loginLink' ), 1 );

        foreach ( array( 'create', 'login', 'magic', 'save', 'reserve', 'unreserve', 'delete', 'logout', 'cash' ) as $a ) {
            add_action( 'wp_ajax_mm_wl_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
            add_action( 'wp_ajax_nopriv_mm_wl_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
        }

        // SEO for a shared wishlist: its own title, never indexed (it's personal).
        add_filter( 'wpseo_title', array( __CLASS__, 'seoTitle' ), 20 );
        add_filter( 'pre_get_document_title', array( __CLASS__, 'seoTitle' ), 20 );
        add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'seoTitle' ), 20 );
        add_filter( 'wpseo_metadesc', array( __CLASS__, 'seoDesc' ), 20 );
        add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'seoDesc' ), 20 );
        add_filter( 'wpseo_robots', array( __CLASS__, 'seoRobots' ), 20 );
        add_filter( 'wpseo_canonical', array( __CLASS__, 'seoCanonical' ), 20 );
        add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'seoCanonical' ), 20 );
    }

    public static function register() {
        register_post_type( self::CPT, array(
            'label'           => 'My Wishlists',
            'labels'          => array( 'name' => 'My Wishlists', 'singular_name' => 'My Wishlist' ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-heart',
            'supports'        => array( 'title' ),
            'capability_type' => 'post',
        ) );

        add_rewrite_tag( '%mm_wl%', '([a-z0-9-]+)' );
        add_rewrite_rule( '^wishlist/([a-z0-9-]+)/?$', 'index.php?pagename=' . self::PAGE_SLUG . '&mm_wl=$matches[1]', 'top' );
    }

    /** Creates the /my-wishlist/ page and refreshes rewrite rules, once. */
    public static function setupOnce() {
        if ( get_option( 'mm_wl_setup_v1' ) ) return;
        update_option( 'mm_wl_setup_v1', time(), false );

        if ( ! get_page_by_path( self::PAGE_SLUG ) ) {
            $id = wp_insert_post( array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'My Wishlist',
                'post_name'    => self::PAGE_SLUG,
                'post_content' => '',
            ) );
            if ( $id && ! is_wp_error( $id ) ) {
                update_post_meta( $id, '_wp_page_template', 'template-my-wishlist.php' );
                update_post_meta( $id, '_yoast_wpseo_title', 'Free Online Wishlist You Can Share (Birthday, Christmas, Any Day) | Manito Manita' );
                update_post_meta( $id, '_yoast_wpseo_metadesc', 'Make a free wishlist with links to Lazada, Shopee or any shop, and share one link with family and friends. They can reserve a gift so nobody buys the same thing.' );
            }
        }

        flush_rewrite_rules( false );
    }

    /** Once: the /my-wishlist/ Yoast title and description cover registries (only if still the first seeded text). */
    public static function seoOnce() {
        if ( get_option( 'mm_wl_seo_v2' ) ) return;
        update_option( 'mm_wl_seo_v2', time(), true );
        $page = get_page_by_path( self::PAGE_SLUG );
        if ( ! $page ) return;

        if ( (string) get_post_meta( $page->ID, '_yoast_wpseo_title', true ) === 'Free Online Wishlist You Can Share (Birthday, Christmas, Any Day) | Manito Manita' ) {
            update_post_meta( $page->ID, '_yoast_wpseo_title', 'Free Gift Registry & Wishlist Maker: Wedding, Debut, Baptism, Birthday | Manito Manita' );
        }
        if ( strpos( (string) get_post_meta( $page->ID, '_yoast_wpseo_metadesc', true ), 'Make a free wishlist with links to Lazada' ) === 0 ) {
            update_post_meta( $page->ID, '_yoast_wpseo_metadesc', 'Make a free gift registry or wishlist for a wedding, debut, baptism, birthday or any day. Add Lazada and Shopee links and a GCash QR, share one link, and guests reserve so nothing is doubled.' );
        }
    }

    /* ------------------------------------------------------------------ Data */

    public static function bySlug( $slug ) {
        $slug = sanitize_title( (string) $slug );
        if ( $slug === '' ) return 0;
        $p = get_posts( array( 'post_type' => self::CPT, 'name' => $slug, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );

        return $p ? (int) $p[0] : 0;
    }

    /** Every list owned by this email, oldest first. */
    public static function listsByEmail( $email ) {
        $email = strtolower( trim( (string) $email ) );
        if ( $email === '' ) return array();

        return array_map( 'intval', get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC',
            'meta_query' => array( array( 'key' => '_mm_wl_email', 'value' => $email ) ) ) ) );
    }

    public static function byEmail( $email ) {
        $l = self::listsByEmail( $email );

        return $l ? $l[0] : 0;
    }

    /** Lists signed in on this browser session, newest first. */
    public static function mine() {
        $out = array();
        foreach ( array_keys( (array) ( $_SESSION['mm_wl'] ?? array() ) ) as $wid ) {
            if ( get_post_type( $wid ) === self::CPT && get_post_status( $wid ) === 'publish' ) $out[] = (int) $wid;
        }
        rsort( $out );

        return $out;
    }

    /** How many of an item are wanted (1-12). */
    public static function qty( array $item ) {
        return max( 1, min( self::MAX_QTY, (int) ( $item['qty'] ?? 1 ) ) );
    }

    /** Reservations of one item as a list (reads the old single-entry format too). */
    public static function resList( array $res, $key ) {
        if ( empty( $res[ $key ] ) ) return array();
        $r = $res[ $key ];

        return isset( $r['h'] ) ? array( $r ) : array_values( (array) $r );
    }

    public static function url( $id ) {
        return home_url( '/wishlist/' . get_post_field( 'post_name', $id ) . '/' );
    }

    public static function items( $id ) {
        $i = get_post_meta( (int) $id, '_mm_wl_items', true );

        return is_array( $i ) ? $i : array();
    }

    public static function name( $id ) {
        return (string) get_post_meta( (int) $id, '_mm_wl_name', true );
    }

    protected static function resKey( $text ) {
        return substr( md5( strtolower( trim( (string) $text ) ) ), 0, 12 );
    }

    public static function reservations( $id ) {
        $r = get_post_meta( (int) $id, '_mm_wl_res', true );

        return is_array( $r ) ? $r : array();
    }

    public static function isOwner( $id ) {
        return ! empty( $_SESSION['mm_wl'][ (int) $id ] );
    }

    protected static function signIn( $id ) {
        $_SESSION['mm_wl'][ (int) $id ] = time();
    }

    /** The wishlist currently shown (from /wishlist/<slug>/), or 0. */
    public static function current() {
        static $id = null;
        if ( $id === null ) $id = self::bySlug( get_query_var( 'mm_wl' ) );

        return $id;
    }

    /* ------------------------------------------------------------------ SEO */

    public static function seoTitle( $title ) {
        $id = self::current();

        return $id ? Registries::title( $id ) . ' | Manito Manita' : $title;
    }

    public static function seoDesc( $desc ) {
        $id = self::current();
        if ( ! $id ) return $desc;
        $n = count( self::items( $id ) );

        return esc_html( Registries::forName( $id ) ) . ' shared a ' . strtolower( Registries::get( Registries::occasionOf( $id ) )['noun'] ) . ' with ' . $n . ' ' . ( $n === 1 ? 'idea' : 'ideas' ) . '. Tap "I&rsquo;ll get this" so nobody buys the same gift.';
    }

    public static function seoRobots( $robots ) {
        return self::current() ? 'noindex, follow' : $robots;
    }

    public static function seoCanonical( $url ) {
        $id = self::current();

        return $id ? self::url( $id ) : $url;
    }

    /* ------------------------------------------------------------------ AJAX helpers */

    protected static function rate( $key, $max ) {
        $k = 'mm_wl_rl_' . md5( $key );
        $n = (int) get_transient( $k );
        if ( $n >= $max ) wp_send_json_error( 'Too many tries. Please wait a while and try again.' );
        set_transient( $k, $n + 1, HOUR_IN_SECONDS );
    }

    protected static function ownerOrFail( $id ) {
        // { message } so the shared wishlist editor (Theme.initWishlistEditor) shows it.
        if ( get_post_type( $id ) !== self::CPT || ! self::isOwner( $id ) ) wp_send_json_error( array( 'message' => 'Please log in to your wishlist again.' ) );
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_wl_' . $id ) ) wp_send_json_error( array( 'message' => 'This page has expired. Please refresh and try again.' ) );
    }

    protected static function cleanItems( $raw ) {
        $rows = array();
        foreach ( (array) $raw as $item ) {
            $text = mb_substr( trim( sanitize_textarea_field( (string) ( $item['text'] ?? '' ) ) ), 0, 500 );
            $links = array();
            foreach ( (array) ( $item['links'] ?? array() ) as $u ) {
                $u = trim( (string) $u );
                if ( $u === '' ) continue;
                if ( ! preg_match( '~^https?://~i', $u ) ) $u = 'https://' . ltrim( $u, '/' );
                $u = esc_url_raw( $u, array( 'http', 'https' ) );
                if ( $u && filter_var( $u, FILTER_VALIDATE_URL ) ) $links[] = array( 'link_url' => $u );
                if ( count( $links ) >= 5 ) break;
            }
            if ( $text === '' && ! $links ) continue;
            $row = array( 'wishlist_description' => $text !== '' ? $text : 'See link', 'reference_links' => $links );
            $qty = self::qty( $item );
            if ( $qty > 1 ) $row['qty'] = $qty;
            if ( ! empty( $item['top'] ) && $item['top'] !== 'false' ) $row['top'] = 1;
            $rows[] = $row;
            if ( count( $rows ) >= self::MAX_ITEMS ) break;
        }

        return $rows;
    }

    /** Saves occasion, celebrant, date and reservation visibility from $_POST. */
    protected static function saveOccasion( $id ) {
        if ( isset( $_POST['occasion'] ) ) update_post_meta( $id, '_mm_wl_occasion', Registries::valid( sanitize_key( wp_unslash( (string) $_POST['occasion'] ) ) ) );
        if ( isset( $_POST['for'] ) ) update_post_meta( $id, '_mm_wl_for', mb_substr( sanitize_text_field( wp_unslash( (string) $_POST['for'] ) ), 0, 60 ) );
        if ( isset( $_POST['date'] ) ) {
            $d = DateTime::createFromFormat( '!Y-m-d', sanitize_text_field( wp_unslash( (string) $_POST['date'] ) ) );
            update_post_meta( $id, '_mm_wl_date', $d ? $d->format( 'Y-m-d' ) : '' );
        }
        if ( isset( $_POST['seeres'] ) ) update_post_meta( $id, '_mm_wl_seeres', in_array( (string) $_POST['seeres'], array( '1', 'true' ), true ) ? '1' : '0' );
    }

    protected static function newList( $name, $email, $hash ) {
        $slug = sanitize_title( $name ) ?: 'wishlist';
        $slug = substr( $slug, 0, 40 ) . '-' . strtolower( wp_generate_password( 4, false ) );

        $id = wp_insert_post( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'post_title' => $name . ' (' . $email . ')', 'post_name' => $slug ) );
        if ( ! $id || is_wp_error( $id ) ) return 0;

        update_post_meta( $id, '_mm_wl_name', $name );
        update_post_meta( $id, '_mm_wl_email', $email );
        update_post_meta( $id, '_mm_wl_hash', $hash );
        update_post_meta( $id, '_mm_wl_items', array() );
        self::saveOccasion( $id );
        if ( class_exists( 'Insights' ) ) Insights::log( 'wishlist_create', 0, 0, 0, Registries::occasionOf( $id ) );

        return (int) $id;
    }

    /* ------------------------------------------------------------------ AJAX */

    public static function ajaxCreate() {
        // "Create another list" while signed in: same email and password, no new code.
        $from = (int) ( $_POST['from'] ?? 0 );
        if ( $from ) {
            if ( get_post_type( $from ) !== self::CPT || ! self::isOwner( $from ) ) wp_send_json_error( 'Please log in to your wishlist again.' );
            if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_wl_' . $from ) ) wp_send_json_error( 'This page has expired. Please refresh and try again.' );
            $email = (string) get_post_meta( $from, '_mm_wl_email', true );
            if ( count( self::listsByEmail( $email ) ) >= 20 ) wp_send_json_error( 'You have the most lists we allow (20). Delete an old one first.' );
            self::rate( 'another' . $email, 10 );
            $name = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ), 0, 60 ) ?: self::name( $from );
            $id = self::newList( $name, $email, (string) get_post_meta( $from, '_mm_wl_hash', true ) );
            if ( ! $id ) wp_send_json_error( 'Something went wrong. Please try again.' );
            self::signIn( $id );
            wp_send_json_success( array( 'url' => self::url( $id ) ) );
        }

        $name = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ), 0, 60 );
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        $pass = (string) wp_unslash( $_POST['password'] ?? '' );

        if ( $name === '' ) wp_send_json_error( 'Please enter your name.' );
        if ( ! is_email( $email ) ) wp_send_json_error( 'Please enter a valid email address.' );
        if ( strlen( $pass ) < 6 ) wp_send_json_error( 'Please use a password with at least 6 characters.' );

        $err = EmailCheck::verifyCode( $email, preg_replace( '/\D/', '', (string) ( $_POST['code'] ?? '' ) ) );
        if ( $err ) wp_send_json_error( $err );

        if ( self::byEmail( $email ) ) wp_send_json_error( 'You already have a list with this email. Tap &ldquo;I have one&rdquo; to log in, then &ldquo;Create another list&rdquo;.' );

        $id = self::newList( $name, $email, wp_hash_password( $pass ) );
        if ( ! $id ) wp_send_json_error( 'Something went wrong. Please try again.' );

        self::signIn( $id );
        self::sendWelcome( $id );

        wp_send_json_success( array( 'url' => self::url( $id ) ) );
    }

    public static function ajaxLogin() {
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        self::rate( 'login' . $email . ( $_SERVER['REMOTE_ADDR'] ?? '' ), 10 );

        $pass = (string) wp_unslash( $_POST['password'] ?? '' );
        $ok = array();
        foreach ( self::listsByEmail( $email ) as $id ) {
            if ( wp_check_password( $pass, (string) get_post_meta( $id, '_mm_wl_hash', true ) ) ) $ok[] = $id;
        }
        if ( ! $ok ) wp_send_json_error( 'That email and password don&rsquo;t match. Try again or email yourself a login link.' );

        foreach ( $ok as $id ) self::signIn( $id );
        // Several lists: open the "My lists" picker.
        wp_send_json_success( array( 'url' => count( $ok ) > 1 ? home_url( '/' . self::PAGE_SLUG . '/' ) : self::url( $ok[0] ) ) );
    }

    /** Forgot password: email a one-hour login link (same reply either way, so emails can't be probed). */
    public static function ajaxMagic() {
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        self::rate( 'magic' . ( $_SERVER['REMOTE_ADDR'] ?? '' ), 5 );

        $id = is_email( $email ) ? self::byEmail( $email ) : 0;
        if ( $id ) {
            $exp = time() + self::LOGIN_TTL;
            $sig = hash_hmac( 'sha256', $id . '|' . $exp . '|' . get_post_meta( $id, '_mm_wl_hash', true ), wp_salt( 'auth' ) );
            $link = add_query_arg( array( 'wl_login' => $id . '.' . $exp . '.' . substr( $sig, 0, 32 ) ), self::url( $id ) );

            Emails::send( $email, 'Your My Wishlist login link', array(
                'preheader' => 'Tap to open and edit your wishlist. The link works for one hour.',
                'eyebrow'   => 'My Wishlist',
                'title'     => 'Log in to your wishlist',
                'body'      => array( '<p>Tap the button to open your wishlist. You can set a new password in the editor if you forgot yours.</p>' ),
                'button'    => array( 'Open my wishlist', $link ),
                'note'      => 'The link works for one hour. If you didn&rsquo;t ask for it, you can ignore this email.',
                'footer'    => 'You&rsquo;re getting this because this address has a wishlist on manitomanita.com.',
            ) );
        }

        wp_send_json_success( 'If that email has a wishlist, we sent a login link. Check your inbox (and spam folder).' );
    }

    public static function loginLink() {
        if ( empty( $_GET['wl_login'] ) ) return;

        $parts = explode( '.', (string) $_GET['wl_login'] );
        if ( count( $parts ) === 3 ) {
            list( $id, $exp, $sig ) = $parts;
            $id = (int) $id;
            $want = substr( hash_hmac( 'sha256', $id . '|' . (int) $exp . '|' . get_post_meta( $id, '_mm_wl_hash', true ), wp_salt( 'auth' ) ), 0, 32 );
            if ( get_post_type( $id ) === self::CPT && (int) $exp >= time() && hash_equals( $want, (string) $sig ) ) {
                if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) session_start();
                // The link proves the email, so it opens every list of that email.
                foreach ( self::listsByEmail( (string) get_post_meta( $id, '_mm_wl_email', true ) ) as $wid ) self::signIn( $wid );
            }
        }

        wp_safe_redirect( remove_query_arg( 'wl_login' ) );
        exit;
    }

    public static function ajaxSave() {
        $id = (int) ( $_POST['id'] ?? 0 );
        self::ownerOrFail( $id );

        $rows = self::cleanItems( wp_unslash( $_POST['items'] ?? array() ) );
        update_post_meta( $id, '_mm_wl_items', $rows );
        update_post_meta( $id, '_mm_wl_note', mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['note'] ?? '' ) ) ), 0, self::MAX_NOTE ) );

        $name = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['name'] ?? '' ) ) ), 0, 60 );
        if ( $name !== '' ) update_post_meta( $id, '_mm_wl_name', $name );
        self::saveOccasion( $id );

        $pass = (string) wp_unslash( $_POST['password'] ?? '' );
        if ( $pass !== '' ) {
            if ( strlen( $pass ) < 6 ) wp_send_json_error( array( 'message' => 'The new password needs at least 6 characters.' ) );
            // One password for all lists of this email.
            $hash = wp_hash_password( $pass );
            foreach ( self::listsByEmail( (string) get_post_meta( $id, '_mm_wl_email', true ) ) as $wid ) update_post_meta( $wid, '_mm_wl_hash', $hash );
        }

        // Keep reservations only for items that are still on the list.
        $keep = array();
        foreach ( $rows as $r ) $keep[ self::resKey( $r['wishlist_description'] ) ] = true;
        update_post_meta( $id, '_mm_wl_res', array_intersect_key( self::reservations( $id ), $keep ) );

        wp_send_json_success( array( 'message' => 'Wishlist saved!', 'count' => count( $rows ) ) );
    }

    public static function ajaxReserve() {
        $id = (int) ( $_POST['id'] ?? 0 );
        $key = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['key'] ?? '' ) );
        if ( get_post_type( $id ) !== self::CPT || $key === '' ) wp_send_json_error( 'This item is no longer on the list.' );
        if ( self::isOwner( $id ) ) wp_send_json_error( 'This is your own wishlist. Friends use this button.' );
        self::rate( 'res' . ( $_SERVER['REMOTE_ADDR'] ?? '' ), 30 );

        $item = null;
        foreach ( self::items( $id ) as $r ) if ( self::resKey( $r['wishlist_description'] ) === $key ) $item = $r;
        if ( ! $item ) wp_send_json_error( 'This item is no longer on the list.' );
        if ( Registries::phase( $id ) === 'closed' ) wp_send_json_error( 'This list has closed.' );

        $res = self::reservations( $id );
        $list = self::resList( $res, $key );
        $qty = self::qty( $item );
        if ( count( $list ) >= $qty ) wp_send_json_error( $qty > 1 ? 'All ' . $qty . ' are already reserved.' : 'Someone already reserved this one.' );

        $token = wp_generate_password( 20, false );
        $list[] = array( 'by' => mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST['by'] ?? '' ) ) ), 0, 40 ), 't' => time(), 'h' => wp_hash( $token ) );
        $res[ $key ] = $list;
        update_post_meta( $id, '_mm_wl_res', $res );

        wp_send_json_success( array( 'token' => $token, 'n' => count( $list ), 'qty' => $qty ) );
    }

    public static function ajaxUnreserve() {
        $id = (int) ( $_POST['id'] ?? 0 );
        $key = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['key'] ?? '' ) );
        $res = self::reservations( $id );
        $list = self::resList( $res, $key );
        $h = wp_hash( (string) ( $_POST['token'] ?? '' ) );
        $left = array_values( array_filter( $list, function ( $r ) use ( $h ) { return ! hash_equals( (string) ( $r['h'] ?? '' ), $h ); } ) );
        if ( count( $left ) === count( $list ) ) wp_send_json_error( 'Only the person who reserved it can undo this.' );

        if ( $left ) $res[ $key ] = $left; else unset( $res[ $key ] );
        update_post_meta( $id, '_mm_wl_res', $res );
        wp_send_json_success( array( 'n' => count( $left ) ) );
    }

    public static function ajaxDelete() {
        $id = (int) ( $_POST['id'] ?? 0 );
        self::ownerOrFail( $id );
        $qr = (string) get_post_meta( $id, '_mm_wl_qr_file', true );
        if ( $qr !== '' && strpos( $qr, wp_upload_dir()['basedir'] ) === 0 ) @unlink( $qr );
        wp_delete_post( $id, true );
        unset( $_SESSION['mm_wl'][ $id ] );
        wp_send_json_success( array( 'url' => home_url( '/' . self::PAGE_SLUG . '/' ) ) );
    }

    /** Cash gift: a note and the owner's own GCash / Maya / bank QR image (guests pay in their own app). */
    public static function ajaxCash() {
        $id = (int) ( $_POST['id'] ?? 0 );
        self::ownerOrFail( $id );
        update_post_meta( $id, '_mm_wl_cash', mb_substr( sanitize_textarea_field( wp_unslash( (string) ( $_POST['cash'] ?? '' ) ) ), 0, 300 ) );

        $old = (string) get_post_meta( $id, '_mm_wl_qr_file', true );
        $drop = function () use ( $id, $old ) {
            if ( $old !== '' && strpos( $old, wp_upload_dir()['basedir'] ) === 0 ) @unlink( $old );
            delete_post_meta( $id, '_mm_wl_qr' );
            delete_post_meta( $id, '_mm_wl_qr_file' );
        };
        if ( ! empty( $_POST['remove_qr'] ) ) $drop();

        if ( ! empty( $_FILES['qr']['name'] ) ) {
            $file = $_FILES['qr'];
            if ( (int) ( $file['error'] ?? 1 ) !== 0 ) wp_send_json_error( array( 'message' => 'The QR image didn&rsquo;t upload. Please try again.' ) );
            if ( (int) $file['size'] > self::MAX_QR ) wp_send_json_error( array( 'message' => 'Please use an image under 2 MB.' ) );
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $mimes = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
            $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
            if ( empty( $check['type'] ) ) wp_send_json_error( array( 'message' => 'Please use a PNG, JPG or WebP image.' ) );
            $file['name'] = 'qr-' . strtolower( wp_generate_password( 16, false ) ) . '.' . $check['ext'];
            $up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
            if ( ! empty( $up['error'] ) ) wp_send_json_error( array( 'message' => 'The QR image didn&rsquo;t upload. Please try again.' ) );
            $ed = wp_get_image_editor( $up['file'] );
            if ( ! is_wp_error( $ed ) ) {
                $ed->resize( 800, 800, false );
                $ed->save( $up['file'] );
            }
            $drop();
            update_post_meta( $id, '_mm_wl_qr', $up['url'] );
            update_post_meta( $id, '_mm_wl_qr_file', $up['file'] );
        }

        wp_send_json_success( array( 'message' => 'Cash gift details saved!' ) );
    }

    public static function ajaxLogout() {
        unset( $_SESSION['mm_wl'] );
        wp_send_json_success( array( 'url' => home_url( '/' . self::PAGE_SLUG . '/' ) ) );
    }

    protected static function sendWelcome( $id ) {
        $title = html_entity_decode( Registries::title( $id ), ENT_QUOTES, 'UTF-8' );
        Emails::send( (string) get_post_meta( $id, '_mm_wl_email', true ), $title . ' is ready to share', array(
            'preheader' => 'Add what you&rsquo;d love to receive and share one link with family and friends.',
            'eyebrow'   => 'My Wishlist',
            'title'     => 'Your wishlist is ready',
            'body'      => array(
                '<p>Hi ' . esc_html( self::name( $id ) ) . '! Add the things you&rsquo;d love to receive, with links to Lazada, Shopee or any shop. Then share your link before a birthday, Christmas or any day.</p>',
                '<p>Friends can tap <strong>I&rsquo;ll get this</strong> so nobody buys the same gift. You won&rsquo;t see what&rsquo;s reserved, so it stays a surprise.</p>',
            ),
            'button'    => array( 'Open my wishlist', self::url( $id ) ),
            'note'      => 'Your link: ' . self::url( $id ),
            'footer'    => 'You&rsquo;re getting this because you made a wishlist on manitomanita.com.',
        ) );
    }
}

MyWishlist::init();
