<?php
/**
 * WP Admin for My Wishlists / registries: Owner, Occasion and Gifts columns, buttons to open the
 * list as guests see it or as the owner sees it (signs this browser in to the list), and a
 * "Gifts and reservations" box on the edit screen (who reserved what, and when).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MyWishlistAdmin {
    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked || ! is_admin() || ! class_exists( 'MyWishlist' ) ) return;
        self::$hooked = true;

        $t = MyWishlist::CPT;
        add_filter( 'manage_' . $t . '_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_' . $t . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
        add_filter( 'post_row_actions', array( __CLASS__, 'rowActions' ), 10, 2 );
        add_action( 'add_meta_boxes_' . $t, array( __CLASS__, 'metaBoxes' ) );
        add_action( 'admin_post_mm_wl_admin_open', array( __CLASS__, 'openAsOwner' ) );
        add_action( 'admin_head', array( __CLASS__, 'css' ) );
    }

    /* ------------------------------------------------------------------ Links */

    /** What a guest sees. "?preview=1" also shows the guest view if this browser is signed in as the owner. */
    public static function guestUrl( $id ) {
        return add_query_arg( 'preview', '1', MyWishlist::url( $id ) );
    }

    public static function ownerUrl( $id ) {
        return wp_nonce_url( admin_url( 'admin-post.php?action=mm_wl_admin_open&id=' . (int) $id ), 'mm_wl_admin_open_' . (int) $id );
    }

    protected static function buttons( $id ) {
        return '<a class="button button-small" href="' . esc_url( self::guestUrl( $id ) ) . '" target="_blank" rel="noopener">Guest view &#8599;</a> '
            . '<a class="button button-small" href="' . esc_url( self::ownerUrl( $id ) ) . '" target="_blank" rel="noopener" '
            . 'onclick="return confirm(\'Open this list as its owner? Your browser gets signed in to it, so anything you change is saved to their list.\');">Owner view &#8599;</a>';
    }

    /** Signs this browser in to the list (same as the owner's password) and opens it. Admins only. */
    public static function openAsOwner() {
        $id = (int) ( $_GET['id'] ?? 0 );
        check_admin_referer( 'mm_wl_admin_open_' . $id );
        if ( get_post_type( $id ) !== MyWishlist::CPT || ! current_user_can( 'edit_post', $id ) ) wp_die( 'You can’t open this list.' );

        if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) session_start();
        $_SESSION['mm_wl'][ $id ] = time();
        wp_safe_redirect( MyWishlist::url( $id ) );
        exit;
    }

    public static function rowActions( $actions, $post ) {
        if ( $post->post_type !== MyWishlist::CPT || $post->post_status !== 'publish' ) return $actions;
        $actions['mm_wl_guest'] = '<a href="' . esc_url( self::guestUrl( $post->ID ) ) . '" target="_blank" rel="noopener">Guest view &#8599;</a>';

        return $actions;
    }

    /* ------------------------------------------------------------------ List columns */

    public static function columns( $cols ) {
        $out = array();
        foreach ( $cols as $k => $v ) {
            $out[ $k ] = $v;
            if ( $k === 'title' ) {
                $out['mm_wl_owner'] = 'Owner';
                $out['mm_wl_occ'] = 'Occasion';
                $out['mm_wl_gifts'] = 'Gifts';
                $out['mm_wl_open'] = '';
            }
        }

        return $out;
    }

    /** Reserved count and wanted count (quantities included). */
    protected static function counts( $id ) {
        $items = MyWishlist::items( $id );
        $res = MyWishlist::reservations( $id );
        $want = 0;
        $got = 0;
        foreach ( $items as $it ) {
            $q = MyWishlist::qty( $it );
            $want += $q;
            $got += min( $q, count( MyWishlist::resList( $res, self::key( $it ) ) ) );
        }

        return array( count( $items ), $want, $got );
    }

    /** Same key MyWishlist uses for an item's reservations. */
    protected static function key( array $item ) {
        return substr( md5( strtolower( trim( (string) ( $item['wishlist_description'] ?? '' ) ) ) ), 0, 12 );
    }

    public static function column( $col, $id ) {
        if ( $col === 'mm_wl_owner' ) {
            $e = (string) get_post_meta( $id, '_mm_wl_email', true );
            echo esc_html( MyWishlist::name( $id ) ) . ( $e !== '' ? '<br><a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '' );
        }
        if ( $col === 'mm_wl_occ' && class_exists( 'Registries' ) ) {
            $o = Registries::get( Registries::occasionOf( $id ) );
            $d = Registries::date( $id );
            echo esc_html( html_entity_decode( $o['label'], ENT_QUOTES, 'UTF-8' ) ) . ( $d ? '<br><span class="mm-wl-ad-muted">' . esc_html( wp_date( 'M j, Y', $d ) ) . '</span>' : '' );
        }
        if ( $col === 'mm_wl_gifts' ) {
            list( $n, $want, $got ) = self::counts( $id );
            echo (int) $n . ' ' . ( $n === 1 ? 'gift' : 'gifts' ) . '<br><span class="mm-wl-ad-muted">' . (int) $got . ' of ' . (int) $want . ' reserved</span>';
        }
        if ( $col === 'mm_wl_open' && get_post_status( $id ) === 'publish' ) echo self::buttons( $id );
    }

    /* ------------------------------------------------------------------ Edit screen */

    public static function metaBoxes( $post ) {
        add_meta_box( 'mm_wl_admin_view', 'Gifts and reservations', array( __CLASS__, 'box' ), MyWishlist::CPT, 'normal', 'high' );
    }

    public static function box( $post ) {
        $id = (int) $post->ID;
        $items = MyWishlist::items( $id );
        $res = MyWishlist::reservations( $id );
        list( $n, $want, $got ) = self::counts( $id );
        $seeRes = class_exists( 'Registries' ) ? Registries::seeRes( $id ) : true;

        echo '<p>' . ( get_post_status( $id ) === 'publish' ? self::buttons( $id ) . ' ' : '' )
            . '<code>' . esc_html( MyWishlist::url( $id ) ) . '</code></p>';
        echo '<p><strong>' . (int) $n . '</strong> gifts &middot; <strong>' . (int) $got . ' of ' . (int) $want . '</strong> reserved &middot; the owner '
            . ( $seeRes ? '<strong>sees</strong> who reserved what' : '<strong>doesn’t see</strong> who reserved (surprise)' ) . '</p>';

        $note = (string) get_post_meta( $id, '_mm_wl_note', true );
        if ( $note !== '' ) echo '<p><span class="mm-wl-ad-muted">Note for guests:</span> ' . esc_html( $note ) . '</p>';
        $cash = (string) get_post_meta( $id, '_mm_wl_cash', true );
        if ( $cash !== '' || get_post_meta( $id, '_mm_wl_qr', true ) ) {
            echo '<p><span class="mm-wl-ad-muted">Cash gift:</span> ' . ( get_post_meta( $id, '_mm_wl_qr', true ) ? 'QR uploaded. ' : '' ) . esc_html( $cash ) . '</p>';
        }

        if ( ! $items ) { echo '<p><em>No gifts added yet.</em></p>'; return; }

        echo '<table class="widefat striped mm-wl-ad-table"><thead><tr><th>Gift</th><th>Wanted</th><th>Reserved by</th><th>Links</th></tr></thead><tbody>';
        foreach ( $items as $it ) {
            $list = MyWishlist::resList( $res, self::key( $it ) );
            $who = array();
            foreach ( $list as $r ) {
                $by = trim( (string) ( $r['by'] ?? '' ) );
                $who[] = esc_html( $by !== '' ? $by : '(no name)' ) . ( ! empty( $r['t'] ) ? ' <span class="mm-wl-ad-muted">' . esc_html( wp_date( 'M j, g:i A', (int) $r['t'] ) ) . '</span>' : '' );
            }
            $links = array();
            foreach ( (array) ( $it['reference_links'] ?? array() ) as $l ) {
                $u = (string) ( $l['link_url'] ?? '' );
                if ( $u !== '' ) $links[] = '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener nofollow">' . esc_html( (string) wp_parse_url( $u, PHP_URL_HOST ) ) . '</a>';
            }
            echo '<tr><td>' . ( ! empty( $it['top'] ) ? '&#9733; ' : '' ) . nl2br( esc_html( (string) ( $it['wishlist_description'] ?? '' ) ) ) . '</td>'
                . '<td>' . (int) MyWishlist::qty( $it ) . '</td>'
                . '<td>' . ( $who ? implode( '<br>', $who ) : '<span class="mm-wl-ad-muted">&mdash;</span>' ) . '</td>'
                . '<td>' . ( $links ? implode( '<br>', $links ) : '&mdash;' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function css() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== MyWishlist::CPT ) return;
        echo '<style>.column-mm_wl_open{width:200px;text-align:right}.column-mm_wl_gifts{width:120px}.column-mm_wl_occ{width:120px}'
            . '.mm-wl-ad-muted{color:#8c8f94;font-size:12px}.mm-wl-ad-table td{vertical-align:top}.mm-wl-ad-table td:nth-child(2){width:60px}</style>';
    }
}

MyWishlistAdmin::init();
