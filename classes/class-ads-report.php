<?php
/**
 * AdsReport Class. Advertiser results: /advertiser/?report=<ad> (owner, admins, or a share link).
 *
 * Built from the first-party counters in {prefix}mm_ad_stats (see Ads::bump): totals with change vs the
 * previous period, per-day series, placement / link / version / device splits, audience segments
 * (group budget and size, weekday x hour; hidden below MIN_SEG events so nobody can be singled out),
 * a placement benchmark (3+ ads), cost per click and per 1,000 views from payments in the period.
 * Also: CSV export, share links, "keep the winning version", the Monday summary email, and the data for
 * Insights -> Sponsors.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class AdsReport
 */
class AdsReport {
    const MIN_SEG    = 10;
    const MAIL_CRON  = 'mm_ads_weekly';

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        foreach ( array( 'report', 'share', 'variant' ) as $a ) {
            add_action( 'wp_ajax_mm_ads_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
            add_action( 'wp_ajax_nopriv_mm_ads_' . $a, array( __CLASS__, 'ajax' . ucfirst( $a ) ) );
        }
        add_action( 'wp_ajax_mm_ads_csv', array( __CLASS__, 'csv' ) );
        add_action( 'wp_ajax_nopriv_mm_ads_csv', array( __CLASS__, 'csv' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::MAIL_CRON, array( __CLASS__, 'weeklyEmails' ) );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::MAIL_CRON ) ) wp_schedule_event( strtotime( 'next monday 09:00:00 ' . wp_timezone_string() ), 'weekly', self::MAIL_CRON );
    }

    /* ------------------------------------------------------------------ Access */

    public static function shareKey( $id ) {
        return (string) get_post_meta( (int) $id, '_mm_ad_share', true );
    }

    public static function url( $id, $share = false ) {
        $args = array( 'report' => (int) $id );
        if ( $share && self::shareKey( $id ) !== '' ) $args['k'] = self::shareKey( $id );

        return add_query_arg( $args, AdsPortal::pageUrl( 'account' ) );
    }

    /** 'owner', 'admin', 'share' or '' (no access). */
    public static function access( $id, $k = '' ) {
        $id = (int) $id;
        if ( get_post_type( $id ) !== Ads::CPT ) return '';
        if ( AdsPortal::current() && (int) Ads::meta( $id, 'adv', 0 ) === AdsPortal::current() ) return 'owner';
        if ( current_user_can( 'manage_options' ) ) return 'admin';
        $key = self::shareKey( $id );
        if ( $key !== '' && is_string( $k ) && hash_equals( $key, $k ) ) return 'share';

        return '';
    }

    /* ------------------------------------------------------------------ Periods */

    /** [from, to, prevFrom, prevTo, days] for '7d' | '30d' | 'all' | 'custom'. */
    public static function period( $id, $range, $from = '', $to = '' ) {
        $today = wp_date( 'Y-m-d' );
        $start = (int) Ads::meta( $id, 'start', 0 );
        $startDay = $start ? wp_date( 'Y-m-d', $start ) : wp_date( 'Y-m-d', strtotime( get_post_field( 'post_date', $id ) ) );
        if ( $range === 'custom' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && $from <= $to ) {
            $f = $from;
            $t = min( $to, $today );
        } elseif ( $range === 'all' ) {
            $f = min( $startDay, $today );
            $t = $today;
        } else {
            $n = $range === '30d' ? 30 : 7;
            $t = $today;
            $f = wp_date( 'Y-m-d', strtotime( $today . ' -' . ( $n - 1 ) . ' days' ) );
        }
        $days = max( 1, min( 400, (int) round( ( strtotime( $t ) - strtotime( $f ) ) / DAY_IN_SECONDS ) + 1 ) );
        $f = wp_date( 'Y-m-d', strtotime( $t . ' -' . ( $days - 1 ) . ' days' ) );
        $pt = wp_date( 'Y-m-d', strtotime( $f . ' -1 day' ) );
        $pf = wp_date( 'Y-m-d', strtotime( $pt . ' -' . ( $days - 1 ) . ' days' ) );

        return array( $f, $t, $pf, $pt, $days );
    }

    protected static function change( $now, $before ) {
        if ( ! $before ) return $now ? null : 0;

        return round( ( $now - $before ) / $before * 100, 1 );
    }

    /** PHP paid in a date range (from payments), else 0. */
    public static function paidIn( $id, $from, $to ) {
        $a = strtotime( $from . ' 00:00:00 ' . wp_timezone_string() );
        $b = strtotime( $to . ' 23:59:59 ' . wp_timezone_string() );
        $sum = 0;
        foreach ( (array) get_post_meta( (int) $id, '_mm_ad_payments', true ) as $p ) {
            if ( empty( $p['t'] ) || ! empty( $p['refunded'] ) ) continue;
            if ( $p['t'] >= $a && $p['t'] <= $b ) $sum += (float) $p['amount'];
        }

        return $sum;
    }

    /* ------------------------------------------------------------------ Report */

    public static function report( $id, $range = '7d', $from = '', $to = '' ) {
        list( $f, $t, $pf, $pt, $days ) = self::period( $id, $range, $from, $to );
        $live = $t === wp_date( 'Y-m-d' );
        $ck = 'mm_adrep_' . (int) $id . '_' . md5( $f . $t );
        $cached = get_transient( $ck );
        if ( is_array( $cached ) ) return $cached;

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT d, variant v, slot, link, metric, device, seg, SUM(n) n FROM ' . Ads::statsTable() . ' WHERE ad_id = %d AND d BETWEEN %s AND %s GROUP BY d, variant, slot, link, metric, device, seg',
            (int) $id, $f, $t
        ) );

        $metrics = array( 'view', 'uview', 'sent', 'open', 'click', 'uclick' );
        $tot = array_fill_keys( $metrics, 0 );
        $daily = array();
        for ( $d = $f; $d <= $t; $d = wp_date( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ) $daily[ $d ] = array_fill_keys( $metrics, 0 );
        $slots = $links = $vars = $dev = array();
        $budget = $size = array();
        $heat = array();

        foreach ( $rows as $r ) {
            $n = (int) $r->n;
            if ( $r->metric === 'seg' ) {
                $k = substr( $r->seg, 2 );
                if ( strpos( $r->seg, 'b:' ) === 0 ) $budget[ $k ] = ( $budget[ $k ] ?? 0 ) + $n;
                if ( strpos( $r->seg, 'z:' ) === 0 ) $size[ $k ] = ( $size[ $k ] ?? 0 ) + $n;
                if ( strpos( $r->seg, 'h:' ) === 0 ) $heat[ $k ] = ( $heat[ $k ] ?? 0 ) + $n;
                continue;
            }
            if ( ! isset( $tot[ $r->metric ] ) ) continue;
            $tot[ $r->metric ] += $n;
            if ( isset( $daily[ $r->d ] ) ) $daily[ $r->d ][ $r->metric ] += $n;
            $slots[ $r->slot ][ $r->metric ] = ( $slots[ $r->slot ][ $r->metric ] ?? 0 ) + $n;
            if ( $r->metric === 'click' ) $links[ (int) $r->link ] = ( $links[ (int) $r->link ] ?? 0 ) + $n;
            if ( in_array( $r->metric, array( 'view', 'open' ), true ) ) $vars[ $r->v ]['imp'] = ( $vars[ $r->v ]['imp'] ?? 0 ) + $n;
            if ( $r->metric === 'click' ) $vars[ $r->v ]['click'] = ( $vars[ $r->v ]['click'] ?? 0 ) + $n;
            if ( in_array( $r->metric, array( 'view', 'click' ), true ) && $r->device !== '' ) $dev[ $r->device ][ $r->metric ] = ( $dev[ $r->device ][ $r->metric ] ?? 0 ) + $n;
        }

        $prev = Ads::totals( $id, $pf, $pt );
        $paid = self::paidIn( $id, $f, $t );
        $paidPrev = self::paidIn( $id, $pf, $pt );
        $paidAll = (float) Ads::meta( $id, 'paid', 0 );
        if ( $range === 'all' ) $paid = $paidAll;

        $imp = $tot['view'] + $tot['open'];
        $impPrev = $prev['view'] + $prev['open'];
        $derived = function ( $t, $imp, $paid ) {
            return array(
                'ctr' => $imp ? round( $t['click'] / $imp * 100, 2 ) : null,
                'cpc' => ( $paid && $t['click'] ) ? round( $paid / $t['click'], 2 ) : null,
                'cpm' => ( $paid && ( $t['view'] + $t['sent'] ) ) ? round( $paid / ( $t['view'] + $t['sent'] ) * 1000, 2 ) : null,
            );
        };
        $now = $derived( $tot, $imp, $paid );
        $was = $derived( $prev, $impPrev, $paidPrev );

        $cards = array();
        foreach ( $metrics as $m ) $cards[ $m ] = array( 'value' => $tot[ $m ], 'change' => self::change( $tot[ $m ], $prev[ $m ] ) );
        foreach ( array( 'ctr', 'cpc', 'cpm' ) as $m ) $cards[ $m ] = array( 'value' => $now[ $m ], 'change' => ( $now[ $m ] !== null && $was[ $m ] ) ? self::change( $now[ $m ], $was[ $m ] ) : null );
        $cards['paid'] = array( 'value' => $paid, 'change' => null );

        // Placement split.
        $pl = Ads::placements();
        $slotRows = array();
        foreach ( $slots as $s => $x ) {
            $si = ( $x['view'] ?? 0 ) + ( $x['open'] ?? 0 );
            $slotRows[] = array( 'name' => $pl[ $s ]['label'] ?? $s, 'views' => (int) ( $x['view'] ?? 0 ), 'sent' => (int) ( $x['sent'] ?? 0 ), 'opens' => (int) ( $x['open'] ?? 0 ), 'clicks' => (int) ( $x['click'] ?? 0 ), 'ctr' => $si ? round( ( $x['click'] ?? 0 ) / $si * 100, 2 ) : null );
        }

        // Links.
        $c = Ads::creative( $id );
        $linkRows = array();
        foreach ( $links as $i => $n ) {
            $label = $i === 0 ? ( $c['button'] ?: 'Main button' ) : ( (string) ( $c['links'][ $i ][0] ?? '' ) ?: 'Link ' . ( $i + 1 ) );
            $linkRows[] = array( 'name' => $label, 'url' => (string) ( $c['links'][ $i ][1] ?? '' ), 'clicks' => $n, 'share' => $tot['click'] ? round( $n / $tot['click'] * 100 ) : 0 );
        }
        usort( $linkRows, function ( $a, $b ) { return $b['clicks'] <=> $a['clicks']; } );

        // Versions A / B.
        $varRows = array();
        foreach ( array( 'a', 'b' ) as $v ) {
            if ( empty( $vars[ $v ] ) ) continue;
            $vi = (int) ( $vars[ $v ]['imp'] ?? 0 );
            $vc = (int) ( $vars[ $v ]['click'] ?? 0 );
            $varRows[ $v ] = array( 'headline' => $v === 'b' ? (string) ( $c['b']['headline'] ?? '' ) : $c['headline'], 'imp' => $vi, 'clicks' => $vc, 'ctr' => $vi ? round( $vc / $vi * 100, 2 ) : 0 );
        }
        $winner = '';
        if ( count( $varRows ) === 2 && $varRows['a']['imp'] >= 300 && $varRows['b']['imp'] >= 300 && $varRows['a']['clicks'] + $varRows['b']['clicks'] >= 10 ) {
            $hi = $varRows['a']['ctr'] >= $varRows['b']['ctr'] ? 'a' : 'b';
            $lo = $hi === 'a' ? 'b' : 'a';
            if ( $varRows[ $lo ]['ctr'] == 0 || $varRows[ $hi ]['ctr'] / max( 0.01, $varRows[ $lo ]['ctr'] ) >= 1.2 ) $winner = $hi;
        }

        // Audience (aggregate only; hidden under MIN_SEG).
        $seg = function ( array $m, array $order, array $labels ) {
            $sum = array_sum( $m );
            if ( $sum < self::MIN_SEG ) return array( 'hidden' => true, 'n' => $sum );
            $out = array();
            foreach ( $order as $k ) $out[] = array( 'name' => $labels[ $k ], 'value' => (int) ( $m[ $k ] ?? 0 ), 'share' => round( ( $m[ $k ] ?? 0 ) / $sum * 100 ) );
            return array( 'hidden' => false, 'rows' => $out, 'n' => $sum );
        };
        $heatSum = array_sum( $heat );
        $heatGrid = null;
        if ( $heatSum >= self::MIN_SEG ) {
            $heatGrid = array();
            for ( $w = 1; $w <= 7; $w++ ) for ( $h = 0; $h < 24; $h++ ) $heatGrid[ $w - 1 ][ $h ] = (int) ( $heat[ $w . sprintf( '%02d', $h ) ] ?? 0 );
        }

        $out = array(
            'ad'        => array( 'id' => (int) $id, 'brand' => $c['brand'], 'headline' => $c['headline'], 'placement' => $pl[ Ads::meta( $id, 'slot' ) ]['label'] ?? '', 'status' => Ads::meta( $id, 'status' ), 'hasB' => ! empty( $c['b']['headline'] ) ),
            'period'    => array( 'from' => $f, 'to' => $t, 'prevFrom' => $pf, 'prevTo' => $pt, 'days' => $days, 'live' => $live ),
            'cards'     => $cards,
            'daily'     => array( 'labels' => array_keys( $daily ), 'view' => array_column( array_values( $daily ), 'view' ), 'open' => array_column( array_values( $daily ), 'open' ), 'click' => array_column( array_values( $daily ), 'click' ), 'sent' => array_column( array_values( $daily ), 'sent' ), 'uview' => array_column( array_values( $daily ), 'uview' ), 'uclick' => array_column( array_values( $daily ), 'uclick' ) ),
            'slots'     => $slotRows,
            'links'     => $linkRows,
            'versions'  => array_values( array_map( function ( $v, $r ) { return $r + array( 'v' => strtoupper( $v ) ); }, array_keys( $varRows ), $varRows ) ),
            'winner'    => strtoupper( $winner ),
            'devices'   => array( 'phone' => $dev['m'] ?? array(), 'computer' => $dev['d'] ?? array() ),
            'budget'    => $seg( $budget, array( '300', '500', '1000+' ), array( '300' => html_entity_decode( '&#8369;' ) . '300 and below', '500' => html_entity_decode( '&#8369;' ) . '301 - ' . html_entity_decode( '&#8369;' ) . '999', '1000+' => html_entity_decode( '&#8369;' ) . '1,000 and up' ) ),
            'size'      => $seg( $size, array( '1-10', '11-30', '31+' ), array( '1-10' => 'Up to 10 members', '11-30' => '11 - 30 members', '31+' => '31 and up' ) ),
            'heat'      => $heatGrid,
            'heatN'     => $heatSum,
            'benchmark' => self::benchmark( $id, $f, $t ),
            'paidAll'   => $paidAll,
            'minSeg'    => self::MIN_SEG,
        );
        set_transient( $ck, $out, $live ? MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS );

        return $out;
    }

    /** Average CTR of the other ads in the same placement (needs 3+ ads with data), else null. */
    protected static function benchmark( $id, $f, $t ) {
        $slot = Ads::meta( $id, 'slot' );
        $ids = get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_ad_slot', 'value' => $slot ) ) ) );
        if ( count( $ids ) < 3 ) return null;
        global $wpdb;
        $in = implode( ',', array_map( 'intval', $ids ) );
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ad_id, metric, SUM(n) n FROM ' . Ads::statsTable() . " WHERE ad_id IN ($in) AND d BETWEEN %s AND %s AND metric IN ('view','open','click') GROUP BY ad_id, metric", $f, $t ) );
        $per = array();
        foreach ( $rows as $r ) $per[ (int) $r->ad_id ][ $r->metric ] = (int) $r->n;
        if ( count( $per ) < 3 ) return null;
        $imp = $clk = 0;
        foreach ( $per as $aid => $m ) {
            if ( $aid === (int) $id ) continue;
            $imp += ( $m['view'] ?? 0 ) + ( $m['open'] ?? 0 );
            $clk += $m['click'] ?? 0;
        }

        return $imp ? array( 'ctr' => round( $clk / $imp * 100, 2 ), 'ads' => count( $per ) - 1 ) : null;
    }

    /* ------------------------------------------------------------------ AJAX */

    protected static function input() {
        return array(
            absint( $_REQUEST['ad'] ?? 0 ),
            sanitize_key( (string) ( $_REQUEST['range'] ?? '7d' ) ),
            sanitize_text_field( (string) ( $_REQUEST['from'] ?? '' ) ),
            sanitize_text_field( (string) ( $_REQUEST['to'] ?? '' ) ),
            preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_REQUEST['k'] ?? '' ) ),
        );
    }

    public static function ajaxReport() {
        if ( ! check_ajax_referer( 'mm_ads', 'nonce', false ) ) wp_send_json_error( 'This page has expired. Please refresh.' );
        list( $id, $range, $from, $to, $k ) = self::input();
        $who = self::access( $id, $k );
        if ( $who === '' ) wp_send_json_error( 'You don&rsquo;t have access to these results.' );

        $r = self::report( $id, $range, $from, $to );
        $r['role'] = $who;
        $r['share'] = $who === 'owner' && self::shareKey( $id ) !== '' ? self::url( $id, true ) : '';
        wp_send_json_success( $r );
    }

    /** Daily rows as CSV (same access as the report). */
    public static function csv() {
        if ( ! wp_verify_nonce( (string) ( $_GET['nonce'] ?? '' ), 'mm_ads' ) ) wp_die( 'This link has expired. Please refresh the page and try again.' );
        list( $id, $range, $from, $to, $k ) = self::input();
        if ( self::access( $id, $k ) === '' ) wp_die( 'You don&rsquo;t have access to these results.' );
        $r = self::report( $id, $range, $from, $to );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="manito-ad-' . (int) $id . '-' . $r['period']['from'] . '-to-' . $r['period']['to'] . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'Date', 'Views', 'Unique viewers', 'Emails sent', 'Opens (estimated)', 'Clicks', 'Unique clicks', 'CTR %' ) );
        foreach ( $r['daily']['labels'] as $i => $d ) {
            $imp = $r['daily']['view'][ $i ] + $r['daily']['open'][ $i ];
            fputcsv( $out, array( $d, $r['daily']['view'][ $i ], $r['daily']['uview'][ $i ], $r['daily']['sent'][ $i ], $r['daily']['open'][ $i ], $r['daily']['click'][ $i ], $r['daily']['uclick'][ $i ], $imp ? round( $r['daily']['click'][ $i ] / $imp * 100, 2 ) : '' ) );
        }
        $t = $r['cards'];
        fputcsv( $out, array( 'Total', $t['view']['value'], $t['uview']['value'], $t['sent']['value'], $t['open']['value'], $t['click']['value'], $t['uclick']['value'], $t['ctr']['value'] ) );
        fclose( $out );
        exit;
    }

    /** Owner turns the read-only share link on (new key) or off. */
    public static function ajaxShare() {
        if ( ! check_ajax_referer( 'mm_ads', 'nonce', false ) ) wp_send_json_error( 'This page has expired. Please refresh.' );
        $id = absint( $_POST['ad'] ?? 0 );
        if ( self::access( $id ) !== 'owner' ) wp_send_json_error( 'Only the advertiser can share these results.' );
        if ( ( $_POST['do'] ?? '' ) === 'off' ) {
            delete_post_meta( $id, '_mm_ad_share' );
            wp_send_json_success( array( 'url' => '', 'message' => 'Share link turned off. The old link no longer works.' ) );
        }
        update_post_meta( $id, '_mm_ad_share', wp_generate_password( 24, false ) );
        wp_send_json_success( array( 'url' => self::url( $id, true ), 'message' => 'Anyone with this link can see these results (read only).' ) );
    }

    /** Keep the winning version: B replaces A (already approved text), or B is dropped. */
    public static function ajaxVariant() {
        if ( ! check_ajax_referer( 'mm_ads', 'nonce', false ) ) wp_send_json_error( 'This page has expired. Please refresh.' );
        $id = absint( $_POST['ad'] ?? 0 );
        if ( self::access( $id ) !== 'owner' ) wp_send_json_error( 'Only the advertiser can change versions.' );
        $c = Ads::creative( $id );
        if ( empty( $c['b']['headline'] ) ) wp_send_json_error( 'This ad has only one version.' );
        if ( ( $_POST['keep'] ?? '' ) === 'b' ) {
            foreach ( array( 'headline', 'text', 'button' ) as $f ) if ( ! empty( $c['b'][ $f ] ) ) $c[ $f ] = $c['b'][ $f ];
        }
        $c['b'] = array();
        update_post_meta( $id, '_mm_ad_creative', $c );
        Ads::flush();
        wp_send_json_success( array( 'message' => 'Done. Only the version you kept is showing now.' ) );
    }

    /* ------------------------------------------------------------------ Monday email */

    public static function weeklyEmails() {
        $ids = get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_ad_status', 'value' => array( 'live', 'ended' ), 'compare' => 'IN' ) ) ) );
        foreach ( $ids as $id ) {
            if ( Ads::meta( $id, 'status' ) === 'ended' && (int) Ads::meta( $id, 'end', 0 ) < time() - 7 * DAY_IN_SECONDS ) continue;
            $to = (string) get_post_meta( (int) Ads::meta( $id, 'adv', 0 ), '_mm_adv_email', true );
            if ( ! is_email( $to ) ) continue;
            $r = self::report( $id, '7d' );
            $c = $r['cards'];
            if ( ! $c['view']['value'] && ! $c['sent']['value'] ) continue;

            $chg = function ( $x ) { return $x === null ? '' : ' <span style="color:' . ( $x >= 0 ? '#1f8a5b' : '#b32d2e' ) . ';">' . ( $x >= 0 ? '&#9650; ' : '&#9660; ' ) . abs( $x ) . '%</span>'; };
            $row = function ( $l, $v, $x = null ) use ( $chg ) { return '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;color:#6d6d6d;">' . $l . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:bold;">' . $v . $chg( $x ) . '</td></tr>'; };
            $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">'
                . $row( 'Views on the site', number_format( $c['view']['value'] ), $c['view']['change'] )
                . $row( 'Emails sent', number_format( $c['sent']['value'] ), $c['sent']['change'] )
                . $row( 'Clicks', number_format( $c['click']['value'] ), $c['click']['change'] )
                . $row( 'Click-through rate', $c['ctr']['value'] !== null ? $c['ctr']['value'] . '%' : '&ndash;' )
                . ( $c['cpc']['value'] !== null ? $row( 'Cost per click', '&#8369;' . number_format( $c['cpc']['value'], 2 ) ) : '' )
                . '</table>';
            $top = $r['links'] ? '<p>Most clicked: <strong>' . esc_html( $r['links'][0]['name'] ) . '</strong> (' . (int) $r['links'][0]['clicks'] . ' clicks).</p>' : '';
            $win = $r['winner'] ? '<p>Version ' . esc_html( $r['winner'] ) . ' is getting more clicks. You can keep just that one from your results page.</p>' : '';

            Emails::send( $to, 'Your ad last week: ' . number_format( $c['view']['value'] + $c['sent']['value'] ) . ' people reached, ' . number_format( $c['click']['value'] ) . ' clicks', array(
                'preheader' => 'Weekly results for ' . $r['ad']['brand'] . ' on Manito Manita.',
                'eyebrow'   => 'Weekly results &middot; ' . esc_html( wp_date( 'M j', strtotime( $r['period']['from'] ) ) . ' - ' . wp_date( 'M j', strtotime( $r['period']['to'] ) ) ),
                'title'     => esc_html( $r['ad']['brand'] ) . ' &middot; ' . esc_html( $r['ad']['placement'] ),
                'body'      => array( $table, $top . $win ),
                'button'    => array( 'See full results', self::url( $id ) ),
                'footer'    => 'You&rsquo;re getting this weekly summary because you advertise on Manito Manita.',
            ) );
        }
    }

    /* ------------------------------------------------------------------ Admin (Insights -> Sponsors) */

    public static function adminSummary( $from, $to ) {
        $ids = get_posts( array( 'post_type' => Ads::CPT, 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mm_ad_status', 'value' => array( 'live', 'review', 'ended', 'paused', 'removed', 'rejected' ), 'compare' => 'IN' ) ) ) );
        $rows = array();
        $revenue = 0;
        $mrr = 0;
        $live = 0;
        foreach ( $ids as $id ) {
            $t = Ads::totals( $id, $from, $to );
            $paid = self::paidIn( $id, $from, $to );
            if ( Ads::meta( $id, 'mode' ) !== 'sandbox' ) $revenue += $paid;
            $st = Ads::meta( $id, 'status' );
            if ( $st === 'live' ) {
                $live++;
                if ( ! Ads::meta( $id, 'cancelled' ) && Ads::meta( $id, 'mode' ) !== 'sandbox' ) {
                    $price = (float) Ads::meta( $id, 'price', 0 );
                    $mrr += Ads::meta( $id, 'cadence', 'weekly' ) === 'monthly' ? $price : $price * 52 / 12;
                }
            }
            $imp = $t['view'] + $t['open'];
            $rows[] = array(
                'id'      => (int) $id,
                'brand'   => Ads::creative( $id )['brand'],
                'adv'     => get_the_title( (int) Ads::meta( $id, 'adv', 0 ) ),
                'place'   => Ads::placements()[ Ads::meta( $id, 'slot' ) ]['label'] ?? '',
                'status'  => $st . ( Ads::meta( $id, 'mode' ) === 'sandbox' ? ' (test)' : '' ),
                'views'   => $t['view'],
                'sent'    => $t['sent'],
                'clicks'  => $t['click'],
                'ctr'     => $imp ? round( $t['click'] / $imp * 100, 2 ) : null,
                'paid'    => $paid,
                'report'  => self::url( $id ),
            );
        }
        usort( $rows, function ( $a, $b ) { return $b['paid'] <=> $a['paid'] ?: $b['views'] <=> $a['views']; } );

        return array( 'rows' => $rows, 'revenue' => $revenue, 'mrr' => round( $mrr ), 'live' => $live, 'seats' => array_map( function ( $k ) { return array( 'name' => Ads::placements()[ $k ]['label'], 'live' => count( Ads::liveAds( $k ) ) ); }, array_keys( array_filter( Ads::placements(), function ( $p ) { return empty( $p['target'] ); } ) ) ) );
    }
}

AdsReport::init();
