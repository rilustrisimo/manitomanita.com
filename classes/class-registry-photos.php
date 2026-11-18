<?php
/**
 * Pictures for My Wishlist / registry gifts.
 *
 * 1. Shop link pictures: the list shows <img src="admin-ajax.php?action=mm_wl_thumb&id=..&k=.."> with
 *    loading="lazy". That endpoint reads the product page's og:image once and 302-redirects to the shop's
 *    own image CDN. Only the image URL is cached (transient), never the image. Saving a list warms the
 *    cache in the background so guests rarely wait for a scrape.
 * 2. Owner photos: uploaded per gift (the browser shrinks them first), stored as two small WebP files
 *    (uploads/mm-wishlist/<list id>/<token>-s.webp ~240px for the list, <token>.webp ~800px to enlarge).
 *    Files no longer used by any gift are deleted on save; the folder goes when the list is deleted.
 * 3. No picture: a CSS tile with the occasion's icon (no image request).
 *
 * Item meta: 'img' => '<12 chars>.webp' (or .jpg where the server can't write WebP).
 * JS: assets/js/mm-registry-photos.js. Styles: mm-wishlist.css (.mm-wl-thumb, .mm-item__photo).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class RegistryPhotos {
    const DIR       = 'mm-wishlist';
    const RE        = '/^[a-z0-9]{12}\.(webp|jpg)$/';
    const MAX_UP    = 8388608; // 8 MB (the browser usually sends ~300 KB)
    const LARGE     = 800;
    const SMALL     = 240;
    const UA        = 'WhatsApp/2.23'; // shops serve link-preview tags (og:image) to chat apps
    const HIT_TTL   = 14 * DAY_IN_SECONDS;
    const MISS_TTL  = DAY_IN_SECONDS;
    const WARM_HOOK = 'mm_wl_thumbs_warm';

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'wp_ajax_mm_wl_thumb', array( __CLASS__, 'ajaxThumb' ) );
        add_action( 'wp_ajax_nopriv_mm_wl_thumb', array( __CLASS__, 'ajaxThumb' ) );
        add_action( 'wp_ajax_mm_wl_photo', array( __CLASS__, 'ajaxPhoto' ) );
        add_action( 'wp_ajax_nopriv_mm_wl_photo', array( __CLASS__, 'ajaxPhoto' ) );
        add_action( self::WARM_HOOK, array( __CLASS__, 'warm' ) );
        add_action( 'before_delete_post', array( __CLASS__, 'onDelete' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    public static function assets() {
        if ( ! class_exists( 'MyWishlist' ) || ! MyWishlist::current() ) return;
        $js = '/assets/js/mm-registry-photos.js';
        wp_enqueue_script( 'mm-registry-photos', PARENT_URL . $js, array( 'jquery' ), (string) @filemtime( PARENT_DIR . $js ), true );
    }

    /* ------------------------------------------------------------------ Owner photos: files */

    protected static function dir( $id ) {
        $u = wp_upload_dir();

        return array( trailingslashit( $u['basedir'] ) . self::DIR . '/' . (int) $id, trailingslashit( $u['baseurl'] ) . self::DIR . '/' . (int) $id );
    }

    public static function valid( $img ) {
        return is_string( $img ) && preg_match( self::RE, $img );
    }

    /** URL of an owner photo: 'small' (list) or 'large' (enlarged). '' if missing. */
    public static function url( $id, $img, $size = 'small' ) {
        if ( ! self::valid( $img ) ) return '';
        list( $path, $url ) = self::dir( $id );
        $name = $size === 'small' ? preg_replace( '/\.(\w+)$/', '-s.$1', $img ) : $img;

        return file_exists( $path . '/' . $name ) ? $url . '/' . $name : '';
    }

    /**
     * After a save: keep 'img' only where the file exists, delete files no gift uses any more,
     * and look up shop pictures for new links in the background.
     */
    public static function sync( $id, array $rows ) {
        $keep = array();
        foreach ( $rows as $i => $r ) {
            if ( empty( $r['img'] ) ) continue;
            if ( self::url( $id, $r['img'], 'large' ) === '' ) { unset( $rows[ $i ]['img'] ); continue; }
            $keep[ $r['img'] ] = true;
            $keep[ preg_replace( '/\.(\w+)$/', '-s.$1', $r['img'] ) ] = true;
        }
        list( $path ) = self::dir( $id );
        foreach ( (array) glob( $path . '/*.{webp,jpg}', GLOB_BRACE ) as $f ) {
            if ( $f && ! isset( $keep[ basename( $f ) ] ) ) @unlink( $f );
        }
        if ( ! wp_next_scheduled( self::WARM_HOOK, array( (int) $id ) ) ) wp_schedule_single_event( time() + 5, self::WARM_HOOK, array( (int) $id ) );

        return $rows;
    }

    public static function onDelete( $postId ) {
        if ( ! class_exists( 'MyWishlist' ) || get_post_type( $postId ) !== MyWishlist::CPT ) return;
        list( $path ) = self::dir( $postId );
        foreach ( (array) glob( $path . '/*' ) as $f ) if ( $f && is_file( $f ) ) @unlink( $f );
        @rmdir( $path );
    }

    /** AJAX (owner): one photo for one gift. Returns the token to save with the item and its URLs. */
    public static function ajaxPhoto() {
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( get_post_type( $id ) !== MyWishlist::CPT || ! MyWishlist::isOwner( $id ) ) wp_send_json_error( 'Please log in to your list again.' );
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_wl_' . $id ) ) wp_send_json_error( 'This page has expired. Please refresh and try again.' );

        $rk = 'mm_wl_photo_n_' . $id;
        $n = (int) get_transient( $rk );
        if ( $n >= 80 ) wp_send_json_error( 'That&rsquo;s a lot of photos for one hour. Please try again later.' );
        set_transient( $rk, $n + 1, HOUR_IN_SECONDS );

        $file = $_FILES['photo'] ?? null;
        if ( ! $file || (int) ( $file['error'] ?? 1 ) !== 0 || ! is_uploaded_file( $file['tmp_name'] ) ) wp_send_json_error( 'The photo didn&rsquo;t upload. Please try again.' );
        if ( (int) $file['size'] > self::MAX_UP ) wp_send_json_error( 'Please use a photo under 8 MB.' );
        $info = @getimagesize( $file['tmp_name'] );
        if ( ! $info || ! in_array( $info['mime'] ?? '', array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) wp_send_json_error( 'Please use a JPG, PNG or WebP photo.' );

        list( $path, $url ) = self::dir( $id );
        if ( ! wp_mkdir_p( $path ) ) wp_send_json_error( 'The photo couldn&rsquo;t be saved. Please try again.' );
        if ( count( (array) glob( $path . '/*-s.*' ) ) >= MyWishlist::MAX_ITEMS * 2 ) wp_send_json_error( 'Please save your list first, then add more photos.' );

        $ext = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'webp' : 'jpg';
        $mime = $ext === 'webp' ? 'image/webp' : 'image/jpeg';
        $token = strtolower( wp_generate_password( 12, false ) );
        // A named copy: some image libraries pick the format from the extension (the upload temp file has none).
        $src = $path . '/' . $token . '-orig.' . array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $info['mime'] ];
        if ( ! @move_uploaded_file( $file['tmp_name'], $src ) ) wp_send_json_error( 'The photo couldn&rsquo;t be saved. Please try again.' );
        $fail = function ( $msg ) use ( $src ) { @unlink( $src ); wp_send_json_error( $msg ); };
        foreach ( array( '' => self::LARGE, '-s' => self::SMALL ) as $suffix => $max ) {
            $ed = wp_get_image_editor( $src );
            if ( is_wp_error( $ed ) ) $fail( 'This photo couldn&rsquo;t be read. Please try another one.' );
            if ( method_exists( $ed, 'maybe_exif_rotate' ) ) $ed->maybe_exif_rotate();
            $ed->resize( $max, $max, false );
            $ed->set_quality( $suffix === '' ? 78 : 72 );
            $saved = $ed->save( $path . '/' . $token . $suffix . '.' . $ext, $mime );
            if ( is_wp_error( $saved ) ) $fail( 'The photo couldn&rsquo;t be saved. Please try again.' );
        }
        @unlink( $src );

        $img = $token . '.' . $ext;
        wp_send_json_success( array( 'img' => $img, 'small' => $url . '/' . $token . '-s.' . $ext, 'large' => $url . '/' . $img ) );
    }

    /* ------------------------------------------------------------------ Shop link pictures */

    /** Lazy <img> source for a gift's shop picture ('' when the gift has no link). */
    public static function linkSrc( $id, array $item ) {
        if ( empty( $item['reference_links'] ) ) return '';

        return add_query_arg( array( 'action' => 'mm_wl_thumb', 'id' => (int) $id, 'k' => self::key( $item ) ), admin_url( 'admin-ajax.php' ) );
    }

    /** Same key MyWishlist uses for an item's reservations. */
    public static function key( array $item ) {
        return substr( md5( strtolower( trim( (string) ( $item['wishlist_description'] ?? '' ) ) ) ), 0, 12 );
    }

    protected static function cacheKey( $url ) {
        return 'mm_wl_og_' . md5( $url );
    }

    /** Cached picture URL for a shop link: string URL, '' (looked, none found) or null (not looked up yet). */
    protected static function cached( $url ) {
        $v = get_transient( self::cacheKey( $url ) );

        return $v === false ? null : (string) $v;
    }

    /**
     * Picture of a gift from its shop links (first link that has one). $fetch = false only reads the cache.
     * Returns a URL, '' (none) or null (not known yet).
     */
    public static function pictureFor( array $item, $fetch = true ) {
        $unknown = false;
        foreach ( array_slice( (array) ( $item['reference_links'] ?? array() ), 0, 3 ) as $l ) {
            $u = (string) ( $l['link_url'] ?? '' );
            if ( $u === '' ) continue;
            $c = self::cached( $u );
            if ( $c === null && $fetch ) $c = self::lookup( $u );
            if ( $c === null ) { $unknown = true; continue; }
            if ( $c !== '' ) return $c;
        }

        return $unknown ? null : '';
    }

    /** Reads og:image (or similar) from a shop page and caches the result. null = busy, try later. */
    protected static function lookup( $url ) {
        // At most 20 page reads a minute site-wide, so a big list can't tie up the server.
        $slot = 'mm_wl_og_rate_' . (int) floor( time() / 60 );
        $n = (int) get_transient( $slot );
        if ( $n >= 20 ) return null;
        set_transient( $slot, $n + 1, 120 );

        $img = '';
        $r = wp_safe_remote_get( $url, array( 'timeout' => 6, 'redirection' => 5, 'user-agent' => self::UA, 'limit_response_size' => 600000, 'headers' => array( 'Accept' => 'text/html' ) ) );
        if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) {
            $img = self::fromHtml( (string) wp_remote_retrieve_body( $r ), $url );
        }
        set_transient( self::cacheKey( $url ), $img, $img !== '' ? self::HIT_TTL : self::MISS_TTL );

        return $img;
    }

    /** The product picture named in a page's <head>, made absolute and https, sized down where the CDN allows. */
    public static function fromHtml( $html, $pageUrl ) {
        $found = '';
        if ( preg_match_all( '/<meta\b[^>]*>/i', $html, $tags ) ) {
            $want = array( 'og:image:secure_url' => 1, 'og:image' => 2, 'twitter:image' => 3, 'twitter:image:src' => 3, 'image' => 4 );
            $best = 99;
            foreach ( $tags[0] as $t ) {
                if ( ! preg_match( '/\b(?:property|name|itemprop)\s*=\s*["\']([^"\']+)["\']/i', $t, $k ) ) continue;
                $rank = $want[ strtolower( trim( $k[1] ) ) ] ?? 0;
                if ( ! $rank || $rank >= $best || ! preg_match( '/\bcontent\s*=\s*["\']([^"\']+)["\']/i', $t, $c ) ) continue;
                $found = html_entity_decode( trim( $c[1] ), ENT_QUOTES, 'UTF-8' );
                $best = $rank;
            }
        }
        if ( $found === '' && preg_match( '/<link\b[^>]*rel\s*=\s*["\']image_src["\'][^>]*href\s*=\s*["\']([^"\']+)/i', $html, $m ) ) $found = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
        if ( $found === '' ) return '';

        if ( strpos( $found, '//' ) === 0 ) $found = 'https:' . $found;
        elseif ( ! preg_match( '~^https?://~i', $found ) ) {
            $p = wp_parse_url( $pageUrl );
            if ( empty( $p['host'] ) ) return '';
            $found = 'https://' . $p['host'] . '/' . ltrim( $found, '/' );
        }
        $found = preg_replace( '~^http://~i', 'https://', $found );
        if ( ! filter_var( $found, FILTER_VALIDATE_URL ) ) return '';

        // Shopee's CDN has a ready thumbnail (~30 KB instead of ~150 KB).
        if ( preg_match( '~^https://[^/]+\.susercontent\.com/file/[A-Za-z0-9-]+$~', $found ) ) $found .= '_tn';

        return esc_url_raw( $found );
    }

    /** AJAX (anyone, GET, used as <img src>): redirect to the shop's picture, or 404 so the tile stays. */
    public static function ajaxThumb() {
        $id = (int) ( $_GET['id'] ?? 0 );
        $k = preg_replace( '/[^a-f0-9]/', '', (string) ( $_GET['k'] ?? '' ) );
        $pic = '';
        if ( get_post_type( $id ) === MyWishlist::CPT && get_post_status( $id ) === 'publish' && $k !== '' ) {
            foreach ( MyWishlist::items( $id ) as $it ) {
                if ( self::key( $it ) !== $k ) continue;
                $pic = self::pictureFor( $it );
                break;
            }
        }
        if ( $pic === null ) { // busy: let the browser ask again next time (admin-ajax already sent no-cache)
            status_header( 404 );
            exit;
        }
        header_remove( 'Expires' );
        header_remove( 'Pragma' );
        if ( $pic === '' ) {
            header( 'Cache-Control: public, max-age=3600' );
            status_header( 404 );
            exit;
        }
        header( 'Cache-Control: public, max-age=86400' );
        header( 'Location: ' . $pic, true, 302 );
        exit;
    }

    /** Cron, a few seconds after a save: look up pictures for the list's links so guests get them instantly. */
    public static function warm( $id ) {
        if ( get_post_type( $id ) !== MyWishlist::CPT ) return;
        foreach ( MyWishlist::items( $id ) as $it ) {
            if ( self::pictureFor( $it ) === null ) { // rate limit hit: finish in a minute
                wp_schedule_single_event( time() + 70, self::WARM_HOOK, array( (int) $id ) );
                return;
            }
        }
    }

    /* ------------------------------------------------------------------ HTML */

    /** Occasion icon class for the "no picture" tile. */
    protected static function icon( $id ) {
        return class_exists( 'Registries' ) ? Registries::get( Registries::occasionOf( $id ) )['icon'] : 'fa-solid fa-gift';
    }

    /**
     * Guest list picture: owner photo (tap to enlarge), else the shop picture (tap opens the shop), else
     * the occasion tile. $num is the gift's number badge.
     */
    public static function guestHtml( $id, array $item, $num ) {
        $badge = '<span class="mm-wish__num">' . (int) $num . '</span>';
        $tile = '<i class="' . esc_attr( self::icon( $id ) ) . ' mm-wl-thumb__icon" aria-hidden="true"></i>';
        $alt = esc_attr( wp_trim_words( wp_strip_all_tags( (string) ( $item['wishlist_description'] ?? '' ) ), 8, '…' ) );

        $small = ! empty( $item['img'] ) ? self::url( $id, $item['img'], 'small' ) : '';
        if ( $small !== '' ) {
            $large = self::url( $id, $item['img'], 'large' );

            return '<figure class="mm-wl-thumb is-photo">' . $badge . $tile
                . '<a href="' . esc_url( $large ) . '" data-mm-wl-zoom aria-label="Enlarge photo"><img src="' . esc_url( $small ) . '" alt="' . $alt . '" loading="lazy" decoding="async" width="' . self::SMALL . '" height="' . self::SMALL . '"></a></figure>';
        }

        $src = self::linkSrc( $id, $item );
        if ( $src !== '' ) {
            $first = (string) ( $item['reference_links'][0]['link_url'] ?? '' );
            $href = class_exists( 'Affiliate' ) ? Affiliate::link( $first, 'my-wishlist-thumb' ) : $first;

            return '<figure class="mm-wl-thumb is-shop">' . $badge . $tile
                . '<a href="' . esc_url( $href ) . '" target="_blank" rel="nofollow sponsored noopener" tabindex="-1" aria-hidden="true">'
                . '<img src="' . esc_url( $src ) . '" alt="" loading="lazy" decoding="async" width="' . self::SMALL . '" height="' . self::SMALL . '" onerror="this.parentNode.remove()"></a></figure>';
        }

        return '<figure class="mm-wl-thumb">' . $badge . $tile . '</figure>';
    }

    /** Owner editor: the photo control for one gift (registry lists only). $id = 0 for the new-item template. */
    public static function editorHtml( $id, $item = null ) {
        $item = is_array( $item ) ? $item : array();
        $img = ! empty( $item['img'] ) && $id ? (string) $item['img'] : '';
        $small = $img !== '' ? self::url( $id, $img, 'small' ) : '';
        if ( $small === '' ) $img = '';
        $shop = $id && $img === '' ? self::linkSrc( $id, $item ) : '';

        $pic = $small !== '' ? '<img src="' . esc_url( $small ) . '" alt="" data-mm-photo-img>'
            : ( $shop !== '' ? '<img src="' . esc_url( $shop ) . '" alt="" loading="lazy" data-mm-photo-shop onerror="this.remove()">' : '' );

        return '<div class="mm-item__photo' . ( $img !== '' ? ' has-photo' : '' ) . '" data-mm-photo>'
            . '<div class="mm-item__photo-pic" data-mm-photo-pic><i class="' . esc_attr( $id ? self::icon( $id ) : 'fa-solid fa-gift' ) . '" aria-hidden="true"></i>' . $pic . '</div>'
            . '<div class="mm-item__photo-acts">'
            . '<button type="button" class="mm-btn-text" data-mm-photo-add><i class="fa-solid fa-camera"></i> <span data-mm-photo-label>' . ( $img !== '' ? 'Change photo' : 'Add your own photo' ) . '</span></button>'
            . '<button type="button" class="mm-btn-text mm-item__photo-rm" data-mm-photo-rm' . ( $img === '' ? ' hidden' : '' ) . '>Remove</button>'
            . '<small class="mm-item__photo-hint" data-mm-photo-hint>' . ( $img !== '' ? 'Guests see your photo.' : ( $shop !== '' ? 'Guests see the shop&rsquo;s picture.' : 'Optional. Shop links show their own picture.' ) ) . '</small>'
            . '</div>'
            . '<input type="hidden" value="' . esc_attr( $img ) . '" data-mm-img>'
            . '<input type="file" accept="image/jpeg,image/png,image/webp" hidden data-mm-photo-file>'
            . '</div>';
    }
}

RegistryPhotos::init();
