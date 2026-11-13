<?php
/**
 * WishlistPlus: the Member+ wishlist extras, shown wherever the member's group wishlist is (member popup,
 * their giver's See Match): a photo per wish, a pinned "Top wish" shown first, and a short profile
 * (sizes, likes, dislikes, allergies).
 *
 * Photos and the pin are keyed by the wish text (not its position), so reordering or deleting other wishes
 * in the wishlist editor doesn't move them; editing a wish's text detaches its photo (cleaned up later).
 * Everything shows only while the member has Member+. Managed in the Member+ panel's "Wishlist+" tab.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class WishlistPlus {
    const PHOTOS     = '_mm_wish_photos'; // wish key => attachment id
    const PIN        = '_mm_wish_pin';    // wish key
    const PROFILE    = '_mm_profile';     // sizes, likes, dislikes, allergies
    const MAX_PHOTOS = 10;
    const MAX_BYTES  = 3145728;           // 3 MB

    public static function init() {
        add_filter( 'mm_wishlist_view', array( __CLASS__, 'order' ), 10, 2 );
        add_filter( 'mm_wish_extra', array( __CLASS__, 'extra' ), 10, 3 );
        add_filter( 'mm_wishlist_profile', array( __CLASS__, 'profileHtml' ), 10, 2 );
        add_filter( 'mm_mplus_tabs', array( __CLASS__, 'tab' ), 10, 2 );
        foreach ( array( 'mm_wplus_photo' => 'ajaxPhoto', 'mm_wplus_pin' => 'ajaxPin', 'mm_wplus_profile' => 'ajaxProfile' ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
        }
    }

    public static function fields() {
        return array( 'sizes' => array( 'Sizes', 'e.g. shirt M, shoes 8, ring 6' ), 'likes' => array( 'Likes', 'e.g. coffee, K-dramas, plants' ), 'dislikes' => array( 'Not for me', 'e.g. scented candles, mugs' ), 'allergies' => array( 'Allergies', 'e.g. peanuts, wool' ) );
    }

    protected static function on( $uid ) {
        return class_exists( 'MemberPlus' ) && MemberPlus::has( $uid );
    }

    /** Stable key for a wish: its text, ignoring case, spacing and tags. */
    public static function key( $text ) {
        return substr( md5( mb_strtolower( preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $text ) ) ) ) ), 0, 12 );
    }

    protected static function photos( $uid ) {
        return array_filter( (array) get_post_meta( (int) $uid, self::PHOTOS, true ) );
    }

    /* ------------------------------------------------------------------ Display */

    /** Pinned wish first (with '_mm_pin'), each wish gets '_mm_key'. */
    public static function order( $wishlist, $uid ) {
        if ( ! $wishlist || ! is_array( $wishlist ) || ! self::on( $uid ) ) return $wishlist;
        $pin = (string) get_post_meta( $uid, self::PIN, true );
        $top = array();
        $rest = array();
        foreach ( $wishlist as $w ) {
            $w['_mm_key'] = self::key( $w['wishlist_description'] ?? '' );
            if ( $pin !== '' && $w['_mm_key'] === $pin && ! $top ) { $w['_mm_pin'] = true; $top[] = $w; }
            else $rest[] = $w;
        }

        return array_merge( $top, $rest );
    }

    public static function extra( $html, $w, $uid ) {
        if ( empty( $w['_mm_key'] ) ) return $html; // order() only marks wishes of Member+ cards
        if ( ! empty( $w['_mm_pin'] ) ) $html .= '<span class="mm-wplus-pin"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Top wish</span>';
        $photos = self::photos( $uid );
        $att = (int) ( $photos[ $w['_mm_key'] ] ?? 0 );
        if ( $att ) {
            $src = wp_get_attachment_image_url( $att, 'medium_large' );
            $full = wp_get_attachment_image_url( $att, 'full' );
            if ( $src ) $html .= '<a class="mm-wplus-photo" href="' . esc_url( $full ?: $src ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $src ) . '" alt="" loading="lazy"></a>';
        }

        return $html;
    }

    public static function profileHtml( $html, $uid ) {
        if ( ! self::on( $uid ) ) return $html;
        $p = (array) get_post_meta( $uid, self::PROFILE, true );
        $rows = '';
        foreach ( self::fields() as $k => $f ) {
            if ( trim( (string) ( $p[ $k ] ?? '' ) ) !== '' ) $rows .= '<div><dt>' . esc_html( $f[0] ) . '</dt><dd>' . esc_html( $p[ $k ] ) . '</dd></div>';
        }

        return $html . ( $rows ? '<dl class="mm-wplus-profile">' . $rows . '</dl>' : '' );
    }

    /* ------------------------------------------------------------------ Panel tab */

    public static function tab( $tabs, $uid ) {
        $wishlist = (array) get_field( 'my_wishlists', $uid );
        $photos = self::photos( $uid );
        $pin = (string) get_post_meta( $uid, self::PIN, true );
        $keys = array();
        $items = '';
        foreach ( array_filter( $wishlist ) as $w ) {
            $text = trim( wp_strip_all_tags( (string) ( $w['wishlist_description'] ?? '' ) ) );
            if ( $text === '' ) continue;
            $k = self::key( $text );
            $keys[] = $k;
            $att = (int) ( $photos[ $k ] ?? 0 );
            $thumb = $att ? wp_get_attachment_image_url( $att, 'thumbnail' ) : '';
            $items .= '<li class="mp-wish" data-key="' . esc_attr( $k ) . '">'
                . '<span class="mp-wish__thumb">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<i class="fa-regular fa-image" aria-hidden="true"></i>' ) . '</span>'
                . '<span class="mp-wish__text">' . esc_html( mb_strimwidth( $text, 0, 90, '…' ) ) . '</span>'
                . '<span class="mp-wish__acts">'
                . '<label class="mp-btn mp-btn--ghost mp-btn--sm"><input type="file" accept="image/jpeg,image/png,image/webp" data-wplus-photo hidden>' . ( $att ? 'Change photo' : 'Add photo' ) . '</label>'
                . ( $att ? '<button type="button" class="mp-btn mp-btn--ghost mp-btn--sm" data-wplus-photo-remove>Remove photo</button>' : '' )
                . '<button type="button" class="mp-btn mp-btn--ghost mp-btn--sm' . ( $pin === $k ? ' is-on' : '' ) . '" data-wplus-pin>' . ( $pin === $k ? '<i class="fa-solid fa-thumbtack"></i> Top wish' : 'Pin as top wish' ) . '</button>'
                . '</span></li>';
        }
        self::cleanup( $uid, $keys );

        $p = (array) get_post_meta( $uid, self::PROFILE, true );
        $form = '';
        foreach ( self::fields() as $k => $f ) {
            $form .= '<label class="mp-field"><span>' . esc_html( $f[0] ) . '</span><input type="text" name="' . esc_attr( $k ) . '" maxlength="120" placeholder="' . esc_attr( $f[1] ) . '" value="' . esc_attr( (string) ( $p[ $k ] ?? '' ) ) . '"></label>';
        }

        $html = '<div class="mp-wplus" data-wplus>'
            . '<section class="mp-card-sec"><header class="mp-sec-head"><i class="fa-solid fa-images" aria-hidden="true"></i><div><h4>Your wishes</h4><p>Add a photo so your Secret Santa knows exactly what you mean, and pin the one you want most.</p></div></header>'
            . ( $items
                ? '<ul class="mp-wishes">' . $items . '</ul><p class="mp-fine-left">Photos: JPG, PNG or WebP, up to 3 MB each.</p>'
                : '<div class="mp-empty"><i class="fa-solid fa-gift" aria-hidden="true"></i><p><strong>No wishes yet</strong><span>Add a few wishes first, then come back here to add photos and pin your top wish.</span></p><button type="button" class="mp-btn mp-btn--ghost mp-btn--sm" data-mp-editwish>Open my wishlist</button></div>' )
            . '</section>'
            . '<section class="mp-card-sec"><header class="mp-sec-head"><i class="fa-solid fa-user-tag" aria-hidden="true"></i><div><h4>About you</h4><p>Shown at the top of your wishlist, for everyone in the group.</p></div></header>'
            . '<form class="mp-form" data-wplus-profile><div class="mp-grid2">' . $form . '</div><div class="mp-form__foot"><button type="submit" class="mp-btn">Save</button><p class="mp-msg" data-mp-msg hidden></p></div></form>'
            . '</section></div>';
        $tabs['wishlist'] = array( 'Wishlist+', $html );

        return $tabs;
    }

    /** Photos of wishes that no longer exist (text edited or wish removed) are deleted. */
    protected static function cleanup( $uid, array $keys ) {
        $photos = self::photos( $uid );
        $keep = array();
        foreach ( $photos as $k => $att ) {
            if ( in_array( $k, $keys, true ) ) $keep[ $k ] = $att;
            else wp_delete_attachment( (int) $att, true );
        }
        if ( count( $keep ) !== count( $photos ) ) update_post_meta( $uid, self::PHOTOS, $keep );
    }

    /* ------------------------------------------------------------------ AJAX */

    protected static function guard() {
        if ( ! check_ajax_referer( 'mm_mplus', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'This page has expired. Please refresh.' ) );
        $uid = absint( $_POST['uid'] ?? 0 );
        if ( ! class_exists( 'MemberPlus' ) || ! MemberPlus::verified( $uid ) ) wp_send_json_error( array( 'message' => 'Please open your card with your password again.', 'reauth' => 1 ) );
        if ( ! MemberPlus::has( $uid ) ) wp_send_json_error( array( 'message' => 'Get Member+ first.' ) );

        return $uid;
    }

    /** The key must belong to one of the member's current wishes. */
    protected static function wishKey( $uid ) {
        $k = preg_replace( '/[^a-f0-9]/', '', (string) ( $_POST['key'] ?? '' ) );
        foreach ( (array) get_field( 'my_wishlists', $uid ) as $w ) {
            if ( $k !== '' && self::key( $w['wishlist_description'] ?? '' ) === $k ) return $k;
        }
        wp_send_json_error( array( 'message' => 'That wish changed. Please reopen Member+.' ) );
    }

    public static function ajaxPhoto() {
        $uid = self::guard();
        $key = self::wishKey( $uid );
        $photos = self::photos( $uid );

        if ( ( $_POST['op'] ?? '' ) === 'remove' ) {
            if ( ! empty( $photos[ $key ] ) ) wp_delete_attachment( (int) $photos[ $key ], true );
            unset( $photos[ $key ] );
            update_post_meta( $uid, self::PHOTOS, $photos );
            wp_send_json_success( array( 'thumb' => '' ) );
        }

        $f = $_FILES['photo'] ?? null;
        if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) wp_send_json_error( array( 'message' => 'Could not read that photo. Please try another.' ) );
        if ( (int) $f['size'] > self::MAX_BYTES ) wp_send_json_error( array( 'message' => 'That photo is over 3 MB. Please pick a smaller one.' ) );
        $type = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) );
        if ( empty( $type['type'] ) || ! @getimagesize( $f['tmp_name'] ) ) wp_send_json_error( array( 'message' => 'Please use a JPG, PNG or WebP photo.' ) );
        if ( empty( $photos[ $key ] ) && count( $photos ) >= self::MAX_PHOTOS ) wp_send_json_error( array( 'message' => 'You can add up to ' . self::MAX_PHOTOS . ' photos.' ) );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $_FILES['photo']['name'] = 'wish-' . $uid . '-' . $key . '.' . ( $type['ext'] ?: 'jpg' ); // no personal file names
        $att = media_handle_upload( 'photo', $uid, array( 'post_title' => 'Wishlist photo' ), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
        if ( is_wp_error( $att ) ) wp_send_json_error( array( 'message' => 'Upload failed. Please try again.' ) );

        if ( ! empty( $photos[ $key ] ) ) wp_delete_attachment( (int) $photos[ $key ], true );
        $photos[ $key ] = (int) $att;
        update_post_meta( $uid, self::PHOTOS, $photos );
        if ( class_exists( 'Insights' ) ) Insights::log( 'mp_use', (int) get_field( 'groups', $uid, false ), $uid, 0, 'wish_photo' );

        wp_send_json_success( array( 'thumb' => (string) wp_get_attachment_image_url( $att, 'thumbnail' ) ) );
    }

    public static function ajaxPin() {
        $uid = self::guard();
        $key = self::wishKey( $uid );
        $off = (string) get_post_meta( $uid, self::PIN, true ) === $key;
        update_post_meta( $uid, self::PIN, $off ? '' : $key );
        if ( ! $off && class_exists( 'Insights' ) ) Insights::log( 'mp_use', (int) get_field( 'groups', $uid, false ), $uid, 0, 'wish_pin' );

        wp_send_json_success( array( 'pin' => $off ? '' : $key ) );
    }

    public static function ajaxProfile() {
        $uid = self::guard();
        $p = array();
        foreach ( array_keys( self::fields() ) as $k ) {
            $p[ $k ] = mb_substr( sanitize_text_field( wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ), 0, 120 );
        }
        update_post_meta( $uid, self::PROFILE, $p );
        if ( class_exists( 'Insights' ) ) Insights::log( 'mp_use', (int) get_field( 'groups', $uid, false ), $uid, 0, 'profile' );

        wp_send_json_success( array( 'message' => 'Saved. It shows at the top of your wishlist.' ) );
    }
}

WishlistPlus::init();
