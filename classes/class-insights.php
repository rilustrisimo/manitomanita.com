<?php
/**
 * Manito Insights: our own analytics in WP Admin (replaces ExactMetrics' reports).
 *
 * Site data comes from the database (groups, members) and a small first-party event log
 * ({prefix}mm_events: affiliate clicks, purchases, confirmations, shuffles...). Traffic and SEO come
 * from GA4 and Search Console (InsightsGoogle). Admin-only.
 *
 * Efficiency: nothing runs for visitors except one small INSERT per logged event. Dashboard queries
 * run only when an admin opens the page; they are indexed GROUP BY queries, cached 60s when the range
 * includes today and 12h for past ranges. The page polls one small "live" endpoint every 60s, only
 * while the tab is visible.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class Insights {
    const DB_VERSION = 1;
    const PAGE       = 'mm-insights';
    const KEEP_DAYS  = 400; // raw events kept ~13 months
    const CRON       = 'mm_insights_daily';
    const MAIL_CRON  = 'mm_insights_weekly';

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'init', array( __CLASS__, 'install' ), 0 ); // before Affiliate::go (init 1) logs its first click
        add_action( 'wp_head', array( __CLASS__, 'gtag' ), 1 );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
        add_action( 'wp_ajax_mm_insights', array( __CLASS__, 'ajax' ) );
        add_action( self::CRON, array( __CLASS__, 'daily' ) );
        add_action( self::MAIL_CRON, array( __CLASS__, 'weeklyEmail' ) );
    }

    public static function table() {
        global $wpdb;

        return $wpdb->prefix . 'mm_events';
    }

    /** Creates/updates the event table once, and schedules the daily clean-up and the Monday email. */
    public static function install() {
        if ( (int) get_option( 'mm_insights_db' ) < self::DB_VERSION ) {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( 'CREATE TABLE ' . self::table() . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                t datetime NOT NULL,
                type varchar(32) NOT NULL,
                gid bigint(20) unsigned NOT NULL DEFAULT 0,
                uid bigint(20) unsigned NOT NULL DEFAULT 0,
                amount decimal(10,2) NOT NULL DEFAULT 0,
                meta varchar(191) NOT NULL DEFAULT '',
                PRIMARY KEY  (id),
                KEY type_t (type,t),
                KEY t (t),
                KEY gid (gid)
            ) " . $wpdb->get_charset_collate() . ';' );
            update_option( 'mm_insights_db', self::DB_VERSION, false );
        }

        if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( strtotime( 'tomorrow 01:10:00 ' . wp_timezone_string() ), 'daily', self::CRON );
        if ( ! wp_next_scheduled( self::MAIL_CRON ) ) wp_schedule_event( strtotime( 'next monday 08:00:00 ' . wp_timezone_string() ), 'weekly', self::MAIL_CRON );
    }

    /**
     * Our own GA4 tag (replaces ExactMetrics'): printed only when ExactMetrics is not active, so visits
     * are never counted twice. Lighter than the plugin (no extra frontend-gtag script).
     */
    public static function gtag() {
        if ( defined( 'EXACTMETRICS_VERSION' ) || is_admin() ) return;
        $id = defined( 'GA4_MEASUREMENT_ID' ) && GA4_MEASUREMENT_ID ? GA4_MEASUREMENT_ID : 'G-1QRP0XF3M8';
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $id ) . '"></script>' . "\n";
        echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(\'js\',new Date());gtag(\'config\',' . wp_json_encode( $id ) . ');</script>' . "\n";
    }

    /**
     * One small insert per meaningful event (no personal data). Never throws.
     */
    public static function log( $type, $gid = 0, $uid = 0, $amount = 0, $meta = '' ) {
        global $wpdb;
        $wpdb->insert( self::table(), array(
            't'      => current_time( 'mysql' ), // site time (Asia/Manila), same as post_date
            'type'   => substr( sanitize_key( $type ), 0, 32 ),
            'gid'    => (int) $gid,
            'uid'    => (int) $uid,
            'amount' => (float) $amount,
            'meta'   => substr( (string) $meta, 0, 191 ),
        ), array( '%s', '%s', '%d', '%d', '%f', '%s' ) );
    }

    public static function daily() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE t < %s LIMIT 5000', wp_date( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) );
    }

    /* ------------------------------------------------------------------ Dates & cache */

    /** Period from the request: array( from Y-m-d, to Y-m-d, prevFrom, prevTo, days ). */
    public static function period( $key, $from = '', $to = '' ) {
        $today = wp_date( 'Y-m-d' );
        $map = array( 'today' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '365d' => 365 );

        if ( $key === 'season' && class_exists( 'Season' ) ) {
            $s = Season::seasonStart();
            $from = substr( $s, 0, 4 ) . '-' . substr( $s, 4, 2 ) . '-' . substr( $s, 6, 2 );
            $to = $today;
        } elseif ( $key === 'custom' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && $from <= $to ) {
            // as given
        } else {
            $n = $map[ $key ] ?? 30;
            $to = $today;
            $from = wp_date( 'Y-m-d', strtotime( $today . ' -' . ( $n - 1 ) . ' days' ) );
        }

        $days = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
        $prevTo = wp_date( 'Y-m-d', strtotime( $from . ' -1 day' ) );
        $prevFrom = wp_date( 'Y-m-d', strtotime( $prevTo . ' -' . ( $days - 1 ) . ' days' ) );

        return array( $from, $to, $prevFrom, $prevTo, $days );
    }

    /** Cached computation: 60s when the range reaches today, 12h for the past. */
    protected static function cached( $key, $to, $fn ) {
        $k = 'mm_ins_' . md5( $key );
        $v = get_transient( $k );
        if ( $v !== false ) return $v;
        $v = $fn();
        set_transient( $k, $v, $to >= wp_date( 'Y-m-d' ) ? 60 : 12 * HOUR_IN_SECONDS );

        return $v;
    }

    /* ------------------------------------------------------------------ Site data */

    /**
     * Daily series between two dates: array( metric => array( 'Y-m-d' => value ) ).
     */
    public static function series( $from, $to ) {
        return self::cached( 'series|' . $from . '|' . $to, $to, function () use ( $from, $to ) {
            global $wpdb;
            $a = $from . ' 00:00:00';
            $b = $to . ' 23:59:59';
            $out = array();

            $posts = array(
                'groups'            => "post_type = 'groups' AND post_status = 'publish'",
                'members_joined'    => "post_type = 'users' AND post_status IN ('publish','pending')",
                'members_confirmed' => "post_type = 'users' AND post_status = 'publish'",
                'wishlists_mine'    => "post_type = 'mm_wishlist' AND post_status = 'publish'",
                'support_chats'     => "post_type = 'mm_support_chat'",
            );
            foreach ( $posts as $m => $where ) {
                $out[ $m ] = array();
                foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT DATE(post_date) d, COUNT(*) n FROM {$wpdb->posts} WHERE {$where} AND post_date BETWEEN %s AND %s GROUP BY d", $a, $b ) ) as $r ) {
                    $out[ $m ][ $r->d ] = (int) $r->n;
                }
            }

            foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(t) d, type, COUNT(*) n, SUM(amount) s FROM ' . self::table() . ' WHERE t BETWEEN %s AND %s GROUP BY d, type', $a, $b ) ) as $r ) {
                $out[ $r->type ][ $r->d ] = (int) $r->n;
                if ( $r->type === 'purchase' ) $out['revenue'][ $r->d ] = (float) $r->s;
            }

            return $out;
        } );
    }

    protected static function total( $series, $metric ) {
        return array_sum( $series[ $metric ] ?? array() );
    }

    public static function change( $now, $before ) {
        if ( ! $before ) return $now ? null : 0; // null = new (no previous data)

        return round( ( $now - $before ) / $before * 100, 1 );
    }

    /** KPI cards for a period with the previous period's values and % change. */
    public static function kpis( $p ) {
        list( $from, $to, $pf, $pt ) = $p;
        $now = self::series( $from, $to );
        $was = self::series( $pf, $pt );

        $defs = array(
            'groups'            => 'Groups created',
            'members_joined'    => 'Members joined',
            'members_confirmed' => 'Members confirmed',
            'purchase'          => 'PRO sales',
            'revenue'           => 'Revenue (&#8369;)',
            'affiliate_click'   => 'Affiliate clicks',
            'shuffle'           => 'Shuffles',
            'repeat_group'      => 'Groups started from an old one',
        );
        $out = array();
        foreach ( $defs as $m => $label ) {
            $a = self::total( $now, $m );
            $b = self::total( $was, $m );
            $out[ $m ] = array( 'label' => $label, 'value' => $a, 'prev' => $b, 'change' => self::change( $a, $b ) );
        }
        $j = $out['members_joined']['value'];
        $out['confirm_rate'] = array( 'label' => 'Confirmation rate', 'value' => $j ? round( $out['members_confirmed']['value'] / $j * 100, 1 ) : 0, 'suffix' => '%', 'prev' => null, 'change' => null );

        return $out;
    }

    /** Fills every day of the range so charts have no gaps. */
    public static function daysOf( $from, $to, $series, $metrics ) {
        $labels = array();
        $data = array_fill_keys( $metrics, array() );
        for ( $d = $from; $d <= $to; $d = wp_date( 'Y-m-d', strtotime( $d . ' +1 day' ) ) ) {
            $labels[] = $d;
            foreach ( $metrics as $m ) $data[ $m ][] = (float) ( $series[ $m ][ $d ] ?? 0 );
        }

        return array( 'labels' => $labels, 'data' => $data );
    }

    /** Rankings and lists for the Growth tab (cached 10 min). */
    public static function groups( $from, $to ) {
        return self::cached( 'groups|' . $from . '|' . $to . '|' . floor( time() / 600 ), $to, function () use ( $from, $to ) {
            global $wpdb;
            $a = $from . ' 00:00:00';
            $b = $to . ' 23:59:59';
            $name = function ( $gid ) { return html_entity_decode( (string) get_post_meta( $gid, 'group_name', true ), ENT_QUOTES, 'UTF-8' ); };

            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT g.ID gid, COUNT(u.ID) n FROM {$wpdb->posts} g
                 JOIN {$wpdb->postmeta} m ON m.meta_key = 'groups' AND m.meta_value = g.ID
                 JOIN {$wpdb->posts} u ON u.ID = m.post_id AND u.post_type = 'users' AND u.post_status = 'publish'
                 WHERE g.post_type = 'groups' AND g.post_status = 'publish' AND g.post_date BETWEEN %s AND %s
                 GROUP BY g.ID ORDER BY n DESC LIMIT 15", $a, $b ) );
            $biggest = array();
            foreach ( $rows as $r ) $biggest[] = array( 'gid' => (int) $r->gid, 'name' => $name( $r->gid ), 'members' => (int) $r->n, 'pro' => (bool) get_post_meta( $r->gid, 'pro', true ), 'link' => Emails::groupLink( $r->gid ) );

            $clicks = array();
            foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT gid, COUNT(*) n FROM ' . self::table() . " WHERE type = 'affiliate_click' AND gid > 0 AND t BETWEEN %s AND %s GROUP BY gid ORDER BY n DESC LIMIT 10", $a, $b ) ) as $r ) {
                if ( get_post_type( $r->gid ) === 'groups' ) $clicks[] = array( 'gid' => (int) $r->gid, 'name' => $name( $r->gid ), 'clicks' => (int) $r->n, 'link' => Emails::groupLink( $r->gid ) );
            }

            $today = wp_date( 'Ymd' );
            $soon = wp_date( 'Ymd', strtotime( '+14 days' ) );
            $upcoming = array();
            foreach ( $wpdb->get_results( $wpdb->prepare(
                "SELECT m.post_id gid, m.meta_value d FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} g ON g.ID = m.post_id AND g.post_type = 'groups' AND g.post_status = 'publish'
                 WHERE m.meta_key = 'gift_exchange_date' AND m.meta_value BETWEEN %s AND %s ORDER BY m.meta_value ASC LIMIT 40", $today, $soon ) ) as $r ) {
                $upcoming[] = array( 'gid' => (int) $r->gid, 'name' => $name( $r->gid ), 'date' => wp_date( 'M j', strtotime( $r->d ) ), 'shuffled' => (bool) get_post_meta( $r->gid, 'matched', true ), 'pro' => (bool) get_post_meta( $r->gid, 'pro', true ), 'link' => Emails::groupLink( $r->gid ) );
            }

            $newest = array();
            foreach ( get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 10, 'fields' => 'ids' ) ) as $gid ) {
                $newest[] = array( 'gid' => $gid, 'name' => $name( $gid ), 'created' => get_the_date( 'M j, g:i A', $gid ), 'members' => count( Groups::memberIds( $gid ) ), 'link' => Emails::groupLink( $gid ) );
            }

            $avg = $wpdb->get_var( $wpdb->prepare(
                "SELECT AVG(n) FROM (SELECT COUNT(u.ID) n FROM {$wpdb->posts} g
                 JOIN {$wpdb->postmeta} m ON m.meta_key = 'groups' AND m.meta_value = g.ID
                 JOIN {$wpdb->posts} u ON u.ID = m.post_id AND u.post_type = 'users' AND u.post_status = 'publish'
                 WHERE g.post_type = 'groups' AND g.post_status = 'publish' AND g.post_date BETWEEN %s AND %s GROUP BY g.ID) x", $a, $b ) );

            return array( 'biggest' => $biggest, 'clicks' => $clicks, 'upcoming' => $upcoming, 'newest' => $newest, 'avg_size' => round( (float) $avg, 1 ) );
        } );
    }

    /** All-time counts of PRO and feature adoption (cached 10 min). */
    public static function features() {
        return self::cached( 'features|' . floor( time() / 600 ), wp_date( 'Y-m-d' ), function () {
            global $wpdb;
            $meta = function ( $key, $value = null ) use ( $wpdb ) {
                return (int) ( $value === null
                    ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' AND meta_value <> 'a:0:{}'", $key ) )
                    : $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $key, $value ) ) );
            };
            $count = function ( $type, $status = 'publish' ) use ( $wpdb ) {
                return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $type, $status ) );
            };
            $chats = array();
            if ( class_exists( 'SupportBot' ) ) {
                foreach ( array_keys( SupportBot::statuses() ) as $s ) {
                    $chats[ $s ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mm_sc_status' WHERE p.post_type = %s AND m.meta_value = %s", SupportBot::CPT, $s ) );
                }
            }

            return array(
                'groups'        => $count( 'groups' ),
                'members'       => $count( 'users' ),
                'pro_groups'    => $meta( 'pro', '1' ),
                'exclusions'    => $meta( 'exclusions' ),
                'weekly_drops'  => $meta( '_mm_themes' ),
                'qa'            => $meta( '_mm_qa' ),
                'co_organizer'  => $meta( '_mm_comod' ),
                'group_theme'   => $meta( '_mm_gtheme' ),
                'revealed'      => $meta( '_mm_revealed' ),
                'my_wishlists'  => $count( 'mm_wishlist' ),
                'reservations'  => $meta( '_mm_wl_res' ),
                'chats'         => $chats,
            );
        } );
    }

    /** Affiliate clicks by store and placement for a period. */
    public static function affiliate( $from, $to ) {
        return self::cached( 'aff|' . $from . '|' . $to, $to, function () use ( $from, $to ) {
            global $wpdb;
            $by = array( 'store' => array(), 'placement' => array() );
            foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT meta, COUNT(*) n FROM ' . self::table() . " WHERE type = 'affiliate_click' AND t BETWEEN %s AND %s GROUP BY meta ORDER BY n DESC", $from . ' 00:00:00', $to . ' 23:59:59' ) ) as $r ) {
                list( $store, $place ) = array_pad( explode( '|', $r->meta, 2 ), 2, '' );
                $by['store'][ $store ?: 'other' ] = ( $by['store'][ $store ?: 'other' ] ?? 0 ) + (int) $r->n;
                $by['placement'][ $place ?: 'other' ] = ( $by['placement'][ $place ?: 'other' ] ?? 0 ) + (int) $r->n;
            }
            arsort( $by['store'] );
            arsort( $by['placement'] );

            return $by;
        } );
    }

    /** Latest events for the live feed. */
    public static function feed( $limit = 15 ) {
        global $wpdb;
        $labels = array( 'group_created' => 'Group created', 'repeat_group' => 'Group started from an old one', 'member_confirmed' => 'Member confirmed', 'purchase' => 'PRO purchase', 'affiliate_click' => 'Affiliate click', 'shuffle' => 'Group shuffled', 'unshuffle' => 'Group unshuffled', 'season_email' => 'Season email sent', 'support_handoff' => 'Support: talk to a person' );
        $out = array();
        foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT t, type, gid, amount, meta FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ) ) as $r ) {
            $out[] = array(
                'when'   => human_time_diff( strtotime( $r->t ), current_time( 'timestamp' ) ) . ' ago',
                'label'  => $labels[ $r->type ] ?? $r->type,
                'type'   => $r->type,
                'group'  => $r->gid ? html_entity_decode( (string) get_post_meta( $r->gid, 'group_name', true ), ENT_QUOTES, 'UTF-8' ) : '',
                'amount' => (float) $r->amount,
                'meta'   => $r->type === 'affiliate_click' ? $r->meta : '',
            );
        }

        return $out;
    }

    /** Simple rule-based hints from the KPIs. */
    public static function hints( $k ) {
        $h = array();
        foreach ( array( 'groups', 'members_joined', 'affiliate_click', 'revenue' ) as $m ) {
            $c = $k[ $m ]['change'];
            if ( $c !== null && abs( $c ) >= 15 && $k[ $m ]['prev'] >= 5 ) {
                $h[] = $k[ $m ]['label'] . ' ' . ( $c > 0 ? 'up' : 'down' ) . ' ' . abs( $c ) . '% vs the previous period.';
            }
        }
        if ( $k['confirm_rate']['value'] && $k['confirm_rate']['value'] < 70 ) $h[] = 'Only ' . $k['confirm_rate']['value'] . '% of new members confirmed their email. Remind organizers to check the member list.';
        if ( $k['groups']['value'] >= 20 && ! $k['purchase']['value'] ) $h[] = $k['groups']['value'] . ' groups created but no PRO sales in this period. Check the PRO funnel in Revenue.';

        return $h;
    }

    /* ------------------------------------------------------------------ AJAX */

    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'mm_insights', 'nonce', false ) ) wp_send_json_error( 'Not allowed.', 403 );

        $view = sanitize_key( (string) ( $_POST['view'] ?? 'overview' ) );
        $p = self::period( sanitize_key( (string) ( $_POST['period'] ?? '30d' ) ), (string) ( $_POST['from'] ?? '' ), (string) ( $_POST['to'] ?? '' ) );
        list( $from, $to ) = $p;
        $g = class_exists( 'InsightsGoogle' ) ? 'InsightsGoogle' : null;

        switch ( $view ) {
            case 'live':
                $today = self::series( wp_date( 'Y-m-d' ), wp_date( 'Y-m-d' ) );
                $out = array(
                    'today'  => array( 'groups' => self::total( $today, 'groups' ), 'members' => self::total( $today, 'members_joined' ), 'revenue' => self::total( $today, 'revenue' ), 'clicks' => self::total( $today, 'affiliate_click' ) ),
                    'feed'   => self::feed(),
                    'google' => $g ? $g::realtime() : null,
                    'at'     => wp_date( 'g:i:s A' ),
                );
                break;
            case 'overview':
                $k = self::kpis( $p );
                $out = array( 'kpis' => $k, 'hints' => self::hints( $k ), 'chart' => self::daysOf( $from, $to, self::series( $from, $to ), array( 'groups', 'members_joined', 'affiliate_click', 'purchase' ) ), 'google' => $g ? $g::overview( $p ) : null );
                break;
            case 'growth':
                $s = self::series( $from, $to );
                $out = array( 'kpis' => self::kpis( $p ), 'chart' => self::daysOf( $from, $to, $s, array( 'groups', 'members_joined', 'members_confirmed', 'shuffle', 'repeat_group' ) ), 'groups' => self::groups( $from, $to ) );
                break;
            case 'revenue':
                $s = self::series( $from, $to );
                $out = array( 'kpis' => self::kpis( $p ), 'chart' => self::daysOf( $from, $to, $s, array( 'revenue', 'purchase' ) ), 'features' => self::features(), 'funnel' => $g ? $g::funnel( $p ) : null );
                break;
            case 'traffic':
                $out = array( 'google' => $g ? $g::traffic( $p ) : null, 'seo' => $g ? $g::seo( $p ) : null, 'configured' => $g ? $g::configured() : false );
                break;
            case 'affiliate':
                $s = self::series( $from, $to );
                $out = array( 'chart' => self::daysOf( $from, $to, $s, array( 'affiliate_click' ) ), 'by' => self::affiliate( $from, $to ), 'groups' => self::groups( $from, $to )['clicks'], 'features' => self::features(), 'events' => $g ? $g::events( $p ) : null );
                break;
            case 'export':
                $out = array( 'markdown' => self::export( $p ), 'filename' => 'manito-insights-' . $from . '-to-' . $to . '.md' );
                break;
            default:
                wp_send_json_error( 'Unknown view.' );
        }

        $out['period'] = array( 'from' => $from, 'to' => $to, 'prevFrom' => $p[2], 'prevTo' => $p[3], 'days' => $p[4] );
        wp_send_json_success( $out );
    }

    /* ------------------------------------------------------------------ Admin screens */

    /**
     * Everything for the period as one Markdown report, written for an AI to analyze.
     * No emails, passwords or group links (links carry the group password).
     */
    public static function export( $p ) {
        global $wpdb;
        list( $from, $to, $pf, $pt, $days ) = $p;
        $g = class_exists( 'InsightsGoogle' ) && InsightsGoogle::configured() ? 'InsightsGoogle' : null;
        $dec = function ( $s ) { return html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ); };
        $n = function ( $v ) { return is_numeric( $v ) ? ( floor( (float) $v ) == (float) $v ? number_format( (float) $v ) : number_format( (float) $v, 1 ) ) : (string) $v; };
        $chg = function ( $c ) { return $c === null ? 'new' : ( $c > 0 ? '+' : '' ) . $c . '%'; };
        $cell = function ( $v ) { return str_replace( array( '|', "\r", "\n" ), array( '/', ' ', ' ' ), (string) $v ); };
        $table = function ( array $head, array $rows ) use ( $cell ) {
            if ( ! $rows ) return "_No data._\n";
            $o = '| ' . implode( ' | ', $head ) . " |\n|" . str_repeat( ' --- |', count( $head ) ) . "\n";
            foreach ( $rows as $r ) $o .= '| ' . implode( ' | ', array_map( $cell, $r ) ) . " |\n";
            return $o;
        };
        $err = function ( $x ) { return is_array( $x ) && ! empty( $x['error'] ) ? '_Unavailable: ' . $x['error'] . "_\n" : ''; };
        $named = function ( $list ) use ( $table, $n, $err ) {
            if ( $e = $err( $list ) ) return $e;
            return $table( array( 'Name', 'Value' ), array_map( function ( $r ) use ( $n ) { return array( $r['name'], $n( $r['value'] ) ); }, (array) $list ) );
        };
        $since = (string) $wpdb->get_var( 'SELECT MIN(t) FROM ' . self::table() );
        $since = $since ? substr( $since, 0, 10 ) : 'no events yet';

        $md = array();
        $md[] = '# Manito Manita analytics export';
        $md[] = '- Period: **' . $from . ' to ' . $to . '** (' . $days . ' days)';
        $md[] = '- Compared with: ' . $pf . ' to ' . $pt;
        $md[] = '- Generated: ' . wp_date( 'Y-m-d H:i' ) . ' (' . wp_timezone_string() . ')';
        $md[] = '';
        $md[] = '## About the site';
        $md[] = 'manitomanita.com is a free Secret Santa (Manito Manita / Kris Kringle) organizer, mostly used in the Philippines. An organizer creates a group (name, exchange date, budget, password) and shares the group link; members join with their email and confirm it; each member adds a wishlist; the organizer shuffles (draws) and everyone sees who they give to. PRO is a paid one-time upgrade per group (PayPal or card, in PHP) that unlocks extras: exclusions, Weekly Drops gift themes, anonymous Q&A, co-organizer, group themes. Gift idea links go to stores (Lazada and others) as affiliate links. "My Wishlist" is a standalone shareable wishlist. Peak season is October to December.';
        $md[] = '';
        $md[] = '## Data notes';
        $md[] = '- Groups, members and confirmations come from the database and are complete for any period.';
        $md[] = '- PRO sales, revenue, affiliate clicks, shuffles and repeat groups come from an event log that started on **' . $since . '**. Zeros before that date mean "not recorded", not "none happened".';
        $md[] = '- GA4 (traffic, events, funnel) can lag a few hours. Search Console lags 2-3 days, so its range is shifted (see that section).';
        $md[] = '- Most traffic is "Direct" because members open group links shared in chats and work tools.';
        $md[] = '';

        // Site KPIs
        $k = self::kpis( $p );
        $rows = array();
        foreach ( $k as $m => $x ) $rows[] = array( $dec( $x['label'] ), $n( $x['value'] ) . ( $x['suffix'] ?? '' ), $x['prev'] === null ? '-' : $n( $x['prev'] ), $x['prev'] === null ? '-' : $chg( $x['change'] ) );
        $md[] = '## 1. Key numbers (site data)';
        $md[] = $table( array( 'Metric', 'This period', 'Previous period', 'Change' ), $rows );

        // Daily site activity
        $metrics = array( 'groups' => 'Groups', 'members_joined' => 'Members joined', 'members_confirmed' => 'Confirmed', 'shuffle' => 'Shuffles', 'repeat_group' => 'Repeat groups', 'affiliate_click' => 'Affiliate clicks', 'purchase' => 'PRO sales', 'revenue' => 'Revenue' );
        $d = self::daysOf( $from, $to, self::series( $from, $to ), array_keys( $metrics ) );
        $rows = array();
        foreach ( $d['labels'] as $i => $day ) {
            $r = array( $day . ' ' . wp_date( 'D', strtotime( $day ) ) );
            foreach ( array_keys( $metrics ) as $m ) $r[] = $n( $d['data'][ $m ][ $i ] );
            $rows[] = $r;
        }
        $md[] = '## 2. Daily site activity';
        $md[] = $table( array_merge( array( 'Date' ), array_values( $metrics ) ), $rows );

        // Groups
        $gr = self::groups( $from, $to );
        $md[] = '## 3. Groups';
        $md[] = '- Average size of groups created in this period: ' . $gr['avg_size'] . ' members';
        $md[] = '';
        $md[] = '### Biggest groups created in this period';
        $md[] = $table( array( 'Group', 'Members', 'PRO' ), array_map( function ( $r ) { return array( $r['name'], $r['members'], $r['pro'] ? 'yes' : 'no' ); }, $gr['biggest'] ) );
        $md[] = '### Groups with the most affiliate clicks';
        $md[] = $table( array( 'Group', 'Clicks' ), array_map( function ( $r ) { return array( $r['name'], $r['clicks'] ); }, $gr['clicks'] ) );
        $md[] = '### Exchanges in the next 14 days';
        $md[] = $table( array( 'Group', 'Date', 'Shuffled', 'PRO' ), array_map( function ( $r ) { return array( $r['name'], $r['date'], $r['shuffled'] ? 'yes' : 'not yet', $r['pro'] ? 'yes' : 'no' ); }, $gr['upcoming'] ) );
        $md[] = '### Newest groups';
        $md[] = $table( array( 'Group', 'Created', 'Members' ), array_map( function ( $r ) { return array( $r['name'], $r['created'], $r['members'] ); }, $gr['newest'] ) );

        // Revenue / funnel
        $md[] = '## 4. PRO funnel (GA4, people per step)';
        if ( $g ) {
            $f = $g::funnel( $p );
            if ( $e = $err( $f ) ) {
                $md[] = $e;
            } else {
                $rows = array();
                $prev = null;
                foreach ( $f['steps'] as $s ) {
                    $rows[] = array( $s['step'], $n( $s['users'] ), $n( $s['count'] ), $prev ? round( $s['users'] / $prev * 100, 1 ) . '%' : '-' );
                    $prev = $s['users'];
                }
                $md[] = $table( array( 'Step', 'People', 'Events', '% of previous step' ), $rows );
                $md[] = '### Where people saw the PRO offer (creative slot)';
                $md[] = $named( $f['slots'] );
            }
        } else {
            $md[] = "_Google not connected._\n";
        }

        // Features
        $fe = self::features();
        $md[] = '## 5. Feature adoption (all time, site data)';
        $labels = array( 'groups' => 'Groups (all time)', 'members' => 'Confirmed members (all time)', 'pro_groups' => 'PRO groups', 'exclusions' => 'Groups using exclusions', 'weekly_drops' => 'Groups using Weekly Drops', 'qa' => 'Groups using anonymous Q&A', 'co_organizer' => 'Groups with a co-organizer', 'group_theme' => 'Groups with a custom theme', 'revealed' => 'Groups that revealed', 'my_wishlists' => 'My Wishlists created', 'reservations' => 'My Wishlists with reserved gifts' );
        $rows = array();
        foreach ( $labels as $key => $l ) $rows[] = array( $l, $n( $fe[ $key ] ?? 0 ) );
        foreach ( (array) $fe['chats'] as $s => $c ) $rows[] = array( 'Support chats: ' . $s, $n( $c ) );
        $md[] = $table( array( 'Feature', 'Count' ), $rows );

        // Affiliate
        $af = self::affiliate( $from, $to );
        $md[] = '## 6. Affiliate clicks (since ' . $since . ')';
        $md[] = '### By store';
        $md[] = $table( array( 'Store', 'Clicks' ), array_map( null, array_keys( $af['store'] ), array_values( $af['store'] ) ) );
        $md[] = '### By placement';
        $md[] = $table( array( 'Placement', 'Clicks' ), array_map( null, array_keys( $af['placement'] ), array_values( $af['placement'] ) ) );

        // Traffic
        $md[] = '## 7. Traffic (GA4)';
        if ( $g ) {
            $t = $g::traffic( $p );
            if ( ! empty( $t['error'] ) ) {
                $md[] = '_Unavailable: ' . $t['error'] . "_\n";
            } else {
                $kl = array( 'sessions' => 'Visits (sessions)', 'users' => 'People', 'new_users' => 'New people', 'views' => 'Page views', 'engagement' => 'Engagement rate', 'avg_duration' => 'Avg. visit (seconds)' );
                $rows = array();
                foreach ( $kl as $key => $l ) {
                    $x = $t['kpis'][ $key ] ?? null;
                    if ( ! $x ) continue;
                    $fmt = $key === 'engagement' ? function ( $v ) { return round( $v * 100, 1 ) . '%'; } : $n;
                    $rows[] = array( $l, $fmt( $x['value'] ), $fmt( $x['prev'] ), $chg( $x['change'] ) );
                }
                $md[] = $table( array( 'Metric', 'This period', 'Previous period', 'Change' ), $rows );
                $md[] = '### Daily visits';
                $md[] = $table( array( 'Date', 'Visits', 'People' ), array_map( null, $t['trend']['labels'], $t['trend']['sessions'], $t['trend']['users'] ) );
                $md[] = '### Channels';
                $md[] = $named( $t['channels'] );
                $md[] = '### Source / medium';
                $md[] = $named( $t['sources'] );
                $md[] = '### Devices';
                $md[] = $named( $t['devices'] );
                $md[] = '### Countries';
                $md[] = $named( $t['countries'] );
                $md[] = '### Top pages';
                $md[] = $table( array( 'Page', 'Views', 'People', 'Avg. engaged seconds per person' ), array_map( function ( $r ) use ( $n ) { return array( preg_replace( '/([?&]pw=)[^&]*/', '$1***', $r['path'] ), $n( $r['views'] ), $n( $r['users'] ), $r['time'] ); }, $t['pages'] ) );
            }
            $ev = $g::events( $p );
            $md[] = '### All GA4 events';
            if ( $e = $err( $ev ) ) {
                $md[] = $e;
            } else {
                uasort( $ev, function ( $a, $b ) { return $b['count'] <=> $a['count']; } );
                $md[] = $table( array( 'Event', 'Count', 'People' ), array_map( function ( $name, $x ) use ( $n ) { return array( $name, $n( $x['count'] ), $n( $x['users'] ) ); }, array_keys( $ev ), array_values( $ev ) ) );
            }
        } else {
            $md[] = "_Google not connected._\n";
        }

        // Search Console
        $md[] = '## 8. Google Search (Search Console)';
        if ( $g ) {
            $s = $g::seo( $p );
            if ( $e = $err( $s ) ) {
                $md[] = $e;
            } else {
                $md[] = '- Range used: ' . $s['range'][0] . ' to ' . $s['range'][1] . ' (shifted for the 2-3 day delay), compared with the same number of days before it.';
                $md[] = '';
                $rows = array();
                foreach ( array( 'clicks' => 'Clicks', 'impressions' => 'Impressions', 'ctr' => 'CTR %', 'position' => 'Avg. position (lower is better)' ) as $key => $l ) {
                    $x = $s['kpis'][ $key ];
                    $rows[] = array( $l, $n( $x['value'] ), $n( $x['prev'] ), $chg( $x['change'] ) );
                }
                $md[] = $table( array( 'Metric', 'This period', 'Previous period', 'Change' ), $rows );
                $q = function ( $list ) use ( $table, $n ) { return $table( array( 'Item', 'Clicks', 'Impressions', 'CTR %', 'Position' ), array_map( function ( $r ) use ( $n ) { return array( $r['key'], $n( $r['clicks'] ), $n( $r['impressions'] ), $r['ctr'], $r['position'] ); }, $list ) ); };
                $md[] = '### Daily';
                $md[] = $table( array( 'Date', 'Clicks', 'Impressions' ), array_map( null, $s['trend']['labels'], $s['trend']['clicks'], $s['trend']['impressions'] ) );
                $md[] = '### Top queries';
                $md[] = $q( $s['queries'] );
                $md[] = '### Top pages';
                $md[] = $q( $s['pages'] );
                $md[] = '### Countries';
                $md[] = $q( $s['countries'] );
                $md[] = '### Devices';
                $md[] = $q( $s['devices'] );
            }
        } else {
            $md[] = "_Google not connected._\n";
        }

        // Right now
        if ( $g ) {
            $rt = $g::realtime();
            $md[] = '## 9. Right now (GA4 realtime, last 30 minutes)';
            if ( $e = $err( $rt ) ) {
                $md[] = $e;
            } else {
                $md[] = '- Active people: ' . $rt['active'];
                $md[] = '';
                $md[] = '### Pages';
                $md[] = $named( $rt['pages'] );
            }
        }

        $md[] = '## 10. Automatic hints';
        $h = self::hints( $k );
        $md[] = $h ? '- ' . implode( "\n- ", $h ) . "\n" : "_None._\n";

        $md[] = '## What I want from you';
        $md[] = 'Analyze this period for Manito Manita. 1) Summarize what is happening and how it compares with the previous period. 2) Point out anything unusual (spikes, drops, weak funnel steps, pages or queries with many impressions but low CTR). 3) Explain likely causes, using the season and day-of-week patterns. 4) Give the 5 most valuable, concrete actions for the next 7 days to grow groups, PRO sales and affiliate clicks, each with the metric in this report that would show it worked.';

        return implode( "\n", $md ) . "\n";
    }

    public static function menu() {
        add_menu_page( 'Manito Insights', 'Manito Insights', 'manage_options', self::PAGE, array( __CLASS__, 'page' ), 'dashicons-chart-area', 2 );
    }

    public static function assets( $hook ) {
        if ( $hook !== 'toplevel_page_' . self::PAGE && $hook !== 'index.php' ) return;
        $dir = get_template_directory();
        $uri = get_template_directory_uri();
        wp_enqueue_style( 'mm-insights', $uri . '/assets/csslib/mm-insights.css', array(), (string) @filemtime( $dir . '/assets/csslib/mm-insights.css' ) );
        if ( $hook === 'toplevel_page_' . self::PAGE ) {
            wp_enqueue_script( 'chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', array(), null, true );
            wp_enqueue_script( 'mm-insights', $uri . '/assets/js/mm-insights.js', array( 'jquery', 'chartjs' ), (string) @filemtime( $dir . '/assets/js/mm-insights.js' ), true );
        } else {
            wp_enqueue_script( 'mm-insights', $uri . '/assets/js/mm-insights.js', array( 'jquery' ), (string) @filemtime( $dir . '/assets/js/mm-insights.js' ), true );
        }
        wp_localize_script( 'mm-insights', 'mmInsights', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'mm_insights' ), 'page' => admin_url( 'admin.php?page=' . self::PAGE ) ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        echo '<div class="wrap mmi" data-mmi>';
        echo '<div class="mmi-top"><div><h1>Manito Insights</h1><p class="mmi-sub">Live site data, traffic and search, in one place.</p></div>';
        echo '<div class="mmi-controls"><span class="mmi-live" data-mmi-live><i></i> Live: <b data-mmi-active>&ndash;</b> on the site now</span>';
        echo '<select data-mmi-period aria-label="Period"><option value="today">Today</option><option value="7d">Last 7 days</option><option value="30d" selected>Last 30 days</option><option value="90d">Last 90 days</option><option value="season">This season (since Sep 1)</option><option value="365d">Last 12 months</option><option value="custom">Custom&hellip;</option></select>';
        echo '<span class="mmi-custom" data-mmi-custom hidden><input type="date" data-mmi-from> &ndash; <input type="date" data-mmi-to> <button type="button" class="button" data-mmi-apply>Apply</button></span>';
        echo '<button type="button" class="button" data-mmi-export title="One Markdown file with every section for this period, ready to paste into an AI">Export for AI</button>';
        echo '</div></div>';
        echo '<nav class="mmi-tabs" role="tablist">';
        foreach ( array( 'overview' => 'Overview', 'growth' => 'Growth &amp; groups', 'revenue' => 'Revenue &amp; PRO', 'traffic' => 'Traffic &amp; SEO', 'affiliate' => 'Affiliate &amp; features' ) as $k => $l ) {
            echo '<button type="button" role="tab" data-mmi-tab="' . $k . '"' . ( $k === 'overview' ? ' class="is-on" aria-selected="true"' : '' ) . '>' . $l . '</button>';
        }
        echo '</nav><div class="mmi-body" data-mmi-body><p class="mmi-loading">Loading&hellip;</p></div>';
        echo '<p class="mmi-foot">Site data is live (refreshes every minute while this tab is open). GA4 trends can lag a few hours; Search Console data is 2&ndash;3 days behind.</p>';
        echo '</div>';
    }

    public static function widget() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        wp_add_dashboard_widget( 'mm_insights_widget', 'Manito Insights &middot; today', function () {
            echo '<div class="mmi-widget" data-mmi-widget><p class="mmi-loading">Loading&hellip;</p></div>';
        } );
        // First in the left column
        global $wp_meta_boxes;
        $normal = $wp_meta_boxes['dashboard']['normal']['core'] ?? array();
        if ( isset( $normal['mm_insights_widget'] ) ) {
            $mine = array( 'mm_insights_widget' => $normal['mm_insights_widget'] );
            $wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $mine, $normal );
        }
    }

    /* ------------------------------------------------------------------ Weekly email */

    public static function weeklyEmail( $to = '' ) {
        $p = self::period( '7d' );
        $k = self::kpis( $p );
        $rows = '';
        foreach ( array( 'groups', 'members_joined', 'members_confirmed', 'purchase', 'revenue', 'affiliate_click', 'shuffle' ) as $m ) {
            $c = $k[ $m ]['change'];
            $arrow = $c === null ? 'new' : ( $c > 0 ? '&#9650; ' . $c . '%' : ( $c < 0 ? '&#9660; ' . abs( $c ) . '%' : '&ndash;' ) );
            $color = $c === null || $c >= 0 ? '#1f8a5b' : '#b42318';
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">' . $k[ $m ]['label'] . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:700;">' . number_format( $k[ $m ]['value'], $m === 'revenue' ? 2 : 0 ) . '</td><td style="padding:8px 0 8px 12px;border-bottom:1px solid #eee;text-align:right;color:' . $color . ';font-size:13px;">' . $arrow . '</td></tr>';
        }
        $body = array( '<p>Here&rsquo;s how Manito Manita did this week (' . esc_html( wp_date( 'M j', strtotime( $p[0] ) ) ) . ' &ndash; ' . esc_html( wp_date( 'M j', strtotime( $p[1] ) ) ) . '), compared with the week before.</p>', '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">' . $rows . '</table>' );

        if ( class_exists( 'InsightsGoogle' ) && InsightsGoogle::configured() ) {
            $t = InsightsGoogle::overview( $p );
            if ( ! empty( $t['totals'] ) ) $body[] = '<p><strong>Traffic:</strong> ' . number_format( $t['totals']['sessions'] ) . ' visits, ' . number_format( $t['totals']['users'] ) . ' people.' . ( ! empty( $t['sources'][0] ) ? ' Top source: ' . esc_html( $t['sources'][0]['name'] ) . '.' : '' ) . '</p>';
            $s = InsightsGoogle::seo( $p );
            if ( ! empty( $s['queries'] ) ) {
                $q = array_slice( array_column( $s['queries'], 'key' ), 0, 5 );
                $body[] = '<p><strong>Top Google searches:</strong> ' . esc_html( implode( ', ', $q ) ) . '.</p>';
            }
        }
        $g = self::groups( $p[0], $p[1] );
        if ( ! empty( $g['biggest'][0] ) ) $body[] = '<p><strong>Biggest new group:</strong> ' . esc_html( $g['biggest'][0]['name'] ) . ' (' . (int) $g['biggest'][0]['members'] . ' members).</p>';
        foreach ( self::hints( $k ) as $h ) $body[] = '<p>&#128161; ' . $h . '</p>';

        return Emails::send( $to ?: get_option( 'admin_email' ), 'Manito Manita weekly: ' . number_format( $k['groups']['value'] ) . ' groups, &#8369;' . number_format( $k['revenue']['value'] ), array(
            'preheader' => number_format( $k['members_joined']['value'] ) . ' members joined, ' . number_format( $k['affiliate_click']['value'] ) . ' affiliate clicks this week.',
            'eyebrow'   => 'Weekly report',
            'title'     => 'Your week at a glance',
            'body'      => $body,
            'button'    => array( 'Open Manito Insights', admin_url( 'admin.php?page=' . self::PAGE ) ),
            'footer'    => 'Sent every Monday to the site admin email.',
        ) );
    }
}

Insights::init();
