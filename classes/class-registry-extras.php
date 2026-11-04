<?php
/**
 * RegistryExtras Class. Add-ons for My Wishlist lists / registries, kept out of MyWishlist:
 *  - Share QR + printable invitation cards (/wishlist/<slug>/?card=1, owner only).
 *  - Daily reservation summary to the owner (on by default; one email a day at most, only when new
 *    gifts were reserved; names only when the owner sees who reserved what).
 *  - Thank-you checklist: the owner ticks givers as thanked.
 *  - Guest reminder: a guest who reserved can leave an email to be reminded 3 days before the date.
 *
 * Meta on mm_wishlist: _mm_wl_alerts ('0' = off), _mm_wl_digest (queued reservations),
 * _mm_wl_thanked (reservation hash => 1), _mm_wl_remind (guest reminders, deleted once sent).
 * JS: assets/js/mm-registry.js. Styles: mm-wishlist.css.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class RegistryExtras {
    const CRON = 'mm_wl_daily';
    const REMIND_DAYS = 3;

    public static function init() {
        add_action( 'mm_wl_reserved', array( __CLASS__, 'queue' ), 10, 3 );
        add_action( 'wp_ajax_mm_wl_alerts', array( __CLASS__, 'ajaxAlerts' ) );
        add_action( 'wp_ajax_nopriv_mm_wl_alerts', array( __CLASS__, 'ajaxAlerts' ) );
        add_action( 'wp_ajax_mm_wl_thanked', array( __CLASS__, 'ajaxThanked' ) );
        add_action( 'wp_ajax_nopriv_mm_wl_thanked', array( __CLASS__, 'ajaxThanked' ) );
        add_action( 'wp_ajax_mm_wl_remind', array( __CLASS__, 'ajaxRemind' ) );
        add_action( 'wp_ajax_nopriv_mm_wl_remind', array( __CLASS__, 'ajaxRemind' ) );
        add_action( 'template_redirect', array( __CLASS__, 'routes' ), 5 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
        add_action( self::CRON, array( __CLASS__, 'daily' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
    }

    /** Daily at 7 pm Manila: owner summaries and guest reminders. */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( strtotime( 'today 19:00:00 ' . wp_timezone_string() ) + ( time() > strtotime( 'today 19:00:00 ' . wp_timezone_string() ) ? DAY_IN_SECONDS : 0 ), 'daily', self::CRON );
    }

    public static function assets() {
        $id = class_exists( 'MyWishlist' ) ? MyWishlist::current() : 0;
        if ( ! $id ) return;
        if ( MyWishlist::isOwner( $id ) ) wp_enqueue_script( 'qrcodejs', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), null, true );
        $js = '/assets/js/mm-registry.js';
        wp_enqueue_script( 'mm-registry', PARENT_URL . $js, array( 'jquery' ), (string) @filemtime( PARENT_DIR . $js ), true );
    }

    protected static function sig( $what ) {
        return substr( hash_hmac( 'sha256', $what, wp_salt( 'auth' ) ), 0, 24 );
    }

    protected static function ownerOrFail( $id ) {
        if ( get_post_type( $id ) !== MyWishlist::CPT || ! MyWishlist::isOwner( $id ) ) wp_send_json_error( 'Please log in to your list again.' );
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_wl_' . $id ) ) wp_send_json_error( 'This page has expired. Please refresh and try again.' );
    }

    /** Reservation entry (by, t, h) for a hash, with its item key, or null. */
    protected static function entry( $id, $h ) {
        $res = MyWishlist::reservations( $id );
        foreach ( array_keys( $res ) as $key ) {
            foreach ( MyWishlist::resList( $res, (string) $key ) as $r ) {
                if ( hash_equals( (string) ( $r['h'] ?? '' ), (string) $h ) ) return array( 'key' => $key, 'by' => (string) $r['by'], 'h' => (string) $r['h'] );
            }
        }

        return null;
    }

    /** Item (text, first link) for a reservation key. */
    protected static function item( $id, $key ) {
        foreach ( MyWishlist::items( $id ) as $w ) {
            if ( substr( md5( strtolower( trim( (string) $w['wishlist_description'] ) ) ), 0, 12 ) === $key ) {
                $link = '';
                foreach ( (array) ( $w['reference_links'] ?? array() ) as $l ) if ( ! empty( $l['link_url'] ) ) { $link = $l['link_url']; break; }

                return array( 'text' => (string) $w['wishlist_description'], 'link' => $link );
            }
        }

        return null;
    }

    public static function alertsOn( $id ) {
        return get_post_meta( (int) $id, '_mm_wl_alerts', true ) !== '0';
    }

    public static function thanked( $id ) {
        $t = get_post_meta( (int) $id, '_mm_wl_thanked', true );

        return is_array( $t ) ? $t : array();
    }

    /* ------------------------------------------------------------------ Hooks and AJAX */

    /** A guest reserved: queue it for the owner's daily summary. */
    public static function queue( $id, $key, $entry ) {
        if ( ! self::alertsOn( $id ) ) return;
        $q = get_post_meta( $id, '_mm_wl_digest', true );
        $q = is_array( $q ) ? $q : array();
        if ( count( $q ) >= 300 ) return;
        $q[] = array( 'h' => (string) $entry['h'], 't' => time() );
        update_post_meta( $id, '_mm_wl_digest', $q );
    }

    public static function ajaxAlerts() {
        $id = (int) ( $_POST['id'] ?? 0 );
        self::ownerOrFail( $id );
        $on = ! empty( $_POST['on'] ) && $_POST['on'] !== '0';
        update_post_meta( $id, '_mm_wl_alerts', $on ? '1' : '0' );
        if ( ! $on ) delete_post_meta( $id, '_mm_wl_digest' );
        wp_send_json_success( array( 'message' => $on ? 'We&rsquo;ll email you a daily summary of new reservations.' : 'Reservation emails are off.' ) );
    }

    public static function ajaxThanked() {
        $id = (int) ( $_POST['id'] ?? 0 );
        self::ownerOrFail( $id );
        $h = preg_replace( '/[^A-Za-z0-9.\/$]/', '', (string) ( $_POST['h'] ?? '' ) );
        if ( $h === '' || ! self::entry( $id, $h ) ) wp_send_json_error( 'That reservation was removed.' );
        $t = self::thanked( $id );
        if ( ! empty( $_POST['on'] ) && $_POST['on'] !== '0' ) $t[ $h ] = 1; else unset( $t[ $h ] );
        update_post_meta( $id, '_mm_wl_thanked', $t );
        wp_send_json_success( array( 'n' => count( $t ) ) );
    }

    /** Guest: remind me 3 days before (needs the token from their reservation). */
    public static function ajaxRemind() {
        $id = (int) ( $_POST['id'] ?? 0 );
        $email = strtolower( sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) ) );
        if ( get_post_type( $id ) !== MyWishlist::CPT ) wp_send_json_error( 'This list is no longer available.' );
        if ( ! is_email( $email ) ) wp_send_json_error( 'Please enter a valid email.' );
        $days = Registries::days( $id );
        if ( $days === null || $days <= self::REMIND_DAYS ) wp_send_json_error( 'The day is too close for a reminder.' );

        $ip = 'mm_wl_rm_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $n = (int) get_transient( $ip );
        if ( $n >= 10 ) wp_send_json_error( 'Too many tries. Please try again later.' );
        set_transient( $ip, $n + 1, HOUR_IN_SECONDS );

        $e = self::entry( $id, wp_hash( (string) ( $_POST['token'] ?? '' ) ) );
        if ( ! $e ) wp_send_json_error( 'We couldn&rsquo;t find your reservation. Please reserve again.' );

        $list = get_post_meta( $id, '_mm_wl_remind', true );
        $list = is_array( $list ) ? array_values( array_filter( $list, function ( $r ) use ( $e ) { return $r['h'] !== $e['h']; } ) ) : array();
        if ( count( $list ) >= 300 ) wp_send_json_error( 'Reminders are full for this list.' );
        $list[] = array( 'h' => $e['h'], 'e' => $email );
        update_post_meta( $id, '_mm_wl_remind', $list );

        wp_send_json_success( array( 'message' => 'Done! We&rsquo;ll email you ' . self::REMIND_DAYS . ' days before, on ' . wp_date( 'M j', Registries::date( $id ) - self::REMIND_DAYS * DAY_IN_SECONDS ) . '.' ) );
    }

    /* ------------------------------------------------------------------ Daily emails */

    public static function daily() {
        foreach ( get_posts( array( 'post_type' => MyWishlist::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_key' => '_mm_wl_digest' ) ) as $id ) {
            self::sendDigest( (int) $id );
        }
        foreach ( get_posts( array( 'post_type' => MyWishlist::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_key' => '_mm_wl_remind' ) ) as $id ) {
            self::sendReminders( (int) $id );
        }
    }

    public static function sendDigest( $id ) {
        $q = get_post_meta( $id, '_mm_wl_digest', true );
        delete_post_meta( $id, '_mm_wl_digest' );
        if ( ! is_array( $q ) || ! self::alertsOn( $id ) ) return;

        $new = array();
        foreach ( $q as $r ) {
            $e = self::entry( $id, $r['h'] );
            if ( $e ) $new[] = $e; // still reserved (not undone)
        }
        if ( ! $new ) return;

        $title = Registries::title( $id );
        $see = Registries::seeRes( $id );
        $items = MyWishlist::items( $id );
        $res = MyWishlist::reservations( $id );
        $covered = 0;
        foreach ( $items as $w ) {
            $k = substr( md5( strtolower( trim( (string) $w['wishlist_description'] ) ) ), 0, 12 );
            if ( count( MyWishlist::resList( $res, $k ) ) >= MyWishlist::qty( $w ) ) $covered++;
        }
        $n = count( $new );
        $body = array();
        if ( $see ) {
            $rows = '';
            foreach ( $new as $e ) {
                $it = self::item( $id, $e['key'] );
                $rows .= '<li><strong>' . esc_html( wp_trim_words( $it ? $it['text'] : 'An item', 12 ) ) . '</strong> &middot; ' . esc_html( $e['by'] !== '' ? $e['by'] : 'Someone (no name given)' ) . '</li>';
            }
            $body[] = '<p>Here&rsquo;s what guests reserved since yesterday:</p><ul>' . $rows . '</ul>';
        } else {
            $body[] = '<p>Guests reserved <strong>' . $n . ' ' . ( $n === 1 ? 'gift' : 'gifts' ) . '</strong> since yesterday. It&rsquo;s a surprise, so we won&rsquo;t say which.</p>';
        }
        $body[] = '<p><strong>' . $covered . ' of ' . count( $items ) . '</strong> items are now fully covered.</p>';

        Emails::send( (string) get_post_meta( $id, '_mm_wl_email', true ), $n . ' new ' . ( $n === 1 ? 'reservation' : 'reservations' ) . ' on ' . html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ), array(
            'preheader' => $see ? 'See who reserved what.' : 'Your list is filling up.',
            'eyebrow'   => 'My Wishlist',
            'title'     => $n . ' new ' . ( $n === 1 ? 'reservation' : 'reservations' ),
            'body'      => $body,
            'button'    => array( 'Open my list', MyWishlist::url( $id ) ),
            'note'      => 'Don&rsquo;t want these? Turn them off: <a href="' . esc_url( $off = add_query_arg( array( 'mm_wl_alerts_off' => $id, 's' => self::sig( 'off' . $id ) ), home_url( '/' ) ) ) . '" style="color:#1f8580;">' . esc_html( $off ) . '</a>',
            'footer'    => 'You get at most one of these a day, only when something new is reserved on your list.',
        ) );
    }

    public static function sendReminders( $id ) {
        $days = Registries::days( $id );
        if ( $days === null || $days < 0 ) { delete_post_meta( $id, '_mm_wl_remind' ); return; }
        if ( $days > self::REMIND_DAYS ) return;

        $list = get_post_meta( $id, '_mm_wl_remind', true );
        delete_post_meta( $id, '_mm_wl_remind' ); // one reminder each, then the emails are gone
        if ( ! is_array( $list ) ) return;

        $title = html_entity_decode( wp_strip_all_tags( Registries::title( $id ) ), ENT_QUOTES, 'UTF-8' );
        $note = (string) get_post_meta( $id, '_mm_wl_note', true );
        $when = $days === 0 ? 'today' : ( $days === 1 ? 'tomorrow' : 'in ' . $days . ' days' );
        foreach ( $list as $r ) {
            $e = self::entry( $id, $r['h'] );
            if ( ! $e || ! is_email( $r['e'] ) ) continue; // undone since
            $it = self::item( $id, $e['key'] );
            Emails::send( $r['e'], 'Reminder: ' . $title . ' is ' . $when, array(
                'preheader' => 'The gift you reserved: ' . ( $it ? wp_trim_words( $it['text'], 10 ) : 'see the list' ),
                'eyebrow'   => 'Gift reminder',
                'title'     => 'The day is ' . $when,
                'body'      => array_filter( array(
                    '<p>You reserved a gift on <strong>' . esc_html( $title ) . '</strong>:</p><p style="font-size:17px;"><strong>' . esc_html( $it ? $it['text'] : 'See the list' ) . '</strong></p>',
                    $note !== '' ? '<p>' . esc_html( $note ) . '</p>' : '',
                    $it && $it['link'] !== '' ? '<p><a href="' . esc_url( $it['link'] ) . '">Open the shop link</a></p>' : '',
                ) ),
                'button'    => array( 'Open the list', MyWishlist::url( $id ) ),
                'footer'    => 'You asked for this reminder when you reserved a gift on manitomanita.com. It&rsquo;s the only one we&rsquo;ll send about this list.',
            ) );
        }
    }

    /* ------------------------------------------------------------------ Pages */

    public static function routes() {
        if ( isset( $_GET['mm_wl_alerts_off'] ) ) {
            $id = absint( $_GET['mm_wl_alerts_off'] );
            if ( $id && get_post_type( $id ) === MyWishlist::CPT && hash_equals( self::sig( 'off' . $id ), (string) ( $_GET['s'] ?? '' ) ) ) {
                update_post_meta( $id, '_mm_wl_alerts', '0' );
                delete_post_meta( $id, '_mm_wl_digest' );
                wp_die( 'Reservation summary emails are off for this list. You can turn them on again from your list.', 'Emails turned off', array( 'response' => 200, 'link_url' => MyWishlist::url( $id ), 'link_text' => 'Open my list' ) );
            }
            wp_die( 'This link isn&rsquo;t valid.', 'Link not valid', array( 'response' => 400 ) );
        }

        $id = class_exists( 'MyWishlist' ) ? MyWishlist::current() : 0;
        if ( $id && isset( $_GET['card'] ) ) {
            if ( ! MyWishlist::isOwner( $id ) ) { wp_safe_redirect( MyWishlist::url( $id ) ); exit; }
            nocache_headers();
            self::card( $id );
            exit;
        }
    }

    /** Printable invitation inserts: 4 per A4 page, each with the title, date, note and the list's QR. */
    protected static function card( $id ) {
        $o = Registries::get( Registries::occasionOf( $id ) );
        $title = Registries::title( $id );
        $t = Registries::date( $id );
        $note = (string) get_post_meta( $id, '_mm_wl_note', true );
        $url = MyWishlist::url( $id );
        $one = '<div class="c"><p class="o">' . esc_html( html_entity_decode( $o['label'], ENT_QUOTES, 'UTF-8' ) ) . '</p><h1>' . $title . '</h1>'
            . ( $t ? '<p class="d">' . esc_html( wp_date( 'l, F j, Y', $t ) ) . '</p>' : '' )
            . ( $note !== '' ? '<p class="n">' . esc_html( $note ) . '</p>' : '' )
            . '<div class="q" data-qr></div><p class="s">Scan to see our gift list</p><p class="u">' . esc_html( preg_replace( '~^https?://~', '', untrailingslashit( $url ) ) ) . '</p></div>';
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Invitation cards &middot; <?php echo esc_html( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ) ); ?></title>
<style>
:root{--a:<?php echo esc_attr( $o['accent'] ); ?>}
*{box-sizing:border-box}body{margin:0;background:#f2f2f2;color:#1b1b1b;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
.bar{display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap;padding:16px;text-align:center}
.bar button,.bar a{padding:10px 18px;border:0;border-radius:999px;background:#1b1b1b;color:#fff;font:600 15px inherit;text-decoration:none;cursor:pointer}.bar a{background:#fff;color:#1b1b1b;border:1px solid #ddd}
.bar p{width:100%;margin:0;color:#666;font-size:14px}
.sheet{display:grid;grid-template-columns:1fr 1fr;gap:0;width:210mm;max-width:100%;margin:0 auto 24px;background:#fff}
.c{display:flex;flex-direction:column;align-items:center;justify-content:center;height:148mm;padding:12mm 10mm;border:1px dashed #ccc;border-top:4mm solid var(--a);text-align:center}
.o{margin:0 0 4mm;color:var(--a);font-size:11pt;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
h1{margin:0 0 3mm;font-size:17pt;line-height:1.2}.d{margin:0 0 3mm;font-size:11pt}.n{margin:0 0 4mm;color:#555;font-size:10pt}
.q{width:40mm;height:40mm;margin:2mm 0 3mm}.q img,.q canvas{width:100%!important;height:100%!important}
.s{margin:0;font-size:11pt;font-weight:700}.u{margin:1mm 0 0;color:#777;font-size:8.5pt;word-break:break-all}
@media (max-width:700px){.sheet{grid-template-columns:1fr}.c{height:auto;min-height:120mm}}
@media print{@page{size:A4;margin:0}body{background:#fff}.bar{display:none}.sheet{margin:0;width:210mm}.c{height:148.5mm}}
</style></head><body>
<div class="bar"><button type="button" onclick="window.print()">Print cards</button><a href="<?php echo esc_url( $url ); ?>">Back to my list</a><p>4 cards per A4 page. Cut along the dashed lines and slip one into each invitation.</p></div>
<div class="sheet"><?php echo str_repeat( $one, 4 ); ?></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>document.querySelectorAll('[data-qr]').forEach(function(el){new QRCode(el,{text:<?php echo wp_json_encode( $url ); ?>,width:300,height:300,correctLevel:QRCode.CorrectLevel.M});});</script>
</body></html><?php
    }
}

RegistryExtras::init();
