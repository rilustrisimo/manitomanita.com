<?php
/**
 * InsightsReport: the business scorecard, the site-side PRO funnel, email results, other logged activity
 * and a dated change log, for Manito Insights (Overview / Revenue tabs) and its Export for AI.
 *
 * Kept out of class-insights.php so that file doesn't keep growing. Everything is read-only queries on
 * the event log ({prefix}mm_events), the email stats table and options, cached like the rest of Insights.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class InsightsReport {
    const CHANGELOG = 'mm_changelog';

    public static function init() {
        add_action( 'admin_post_mm_changelog_save', array( __CLASS__, 'saveChangelog' ) );
    }

    protected static function cached( $key, $to, $fn ) {
        $k = 'mm_insr_' . md5( $key );
        $v = get_transient( $k );
        if ( $v !== false ) return $v;
        $v = $fn();
        set_transient( $k, $v, $to >= wp_date( 'Y-m-d' ) ? 60 : 12 * HOUR_IN_SECONDS );

        return $v;
    }

    /** Count and distinct groups of one event type in a range, optionally with a meta prefix. */
    protected static function events( $type, $from, $to, $metaPrefix = '' ) {
        global $wpdb;
        $sql = 'SELECT COUNT(*) n, COUNT(DISTINCT gid) g FROM ' . Insights::table() . ' WHERE type = %s AND t BETWEEN %s AND %s';
        $args = array( $type, $from . ' 00:00:00', $to . ' 23:59:59' );
        if ( $metaPrefix !== '' ) {
            $sql .= ' AND meta LIKE %s';
            $args[] = $wpdb->esc_like( $metaPrefix ) . '%';
        }
        $r = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );

        return array( 'count' => (int) ( $r->n ?? 0 ), 'groups' => (int) ( $r->g ?? 0 ) );
    }

    /** meta => count for one event type, only metas starting with $prefix (shown without it). */
    protected static function byMeta( $type, $from, $to, $prefix = '' ) {
        global $wpdb;
        $out = array();
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT meta, COUNT(*) n FROM ' . Insights::table() . ' WHERE type = %s AND t BETWEEN %s AND %s AND meta LIKE %s GROUP BY meta', $type, $from . ' 00:00:00', $to . ' 23:59:59', $wpdb->esc_like( $prefix ) . '%' ) ) as $r ) {
            $k = $prefix !== '' ? substr( (string) $r->meta, strlen( $prefix ) ) : (string) $r->meta;
            $k = $k !== '' ? $k : 'other';
            $out[ $k ] = ( $out[ $k ] ?? 0 ) + (int) $r->n;
        }
        arsort( $out );

        return $out;
    }

    /** First time an event type was logged (Y-m-d) or ''. */
    public static function since( $type ) {
        global $wpdb;
        $t = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(t) FROM ' . Insights::table() . ' WHERE type = %s', $type ) );

        return $t ? substr( $t, 0, 10 ) : '';
    }

    /* ------------------------------------------------------------------ PRO funnel (site log) */

    /**
     * PRO sheet opened -> card/PayPal tapped -> order created -> paid, from our own log (not blocked by
     * ad blockers). Steps logged since 2026-11-05; purchases since 2026-11-03.
     */
    public static function proFunnel( $from, $to ) {
        return self::cached( 'funnel|' . $from . '|' . $to, $to, function () use ( $from, $to ) {
            $steps = array(
                array( 'key' => 'pro_sheet',    'step' => 'Opened the PRO sheet' ) + self::events( 'pro_sheet', $from, $to, 'pro|' ),
                array( 'key' => 'pro_checkout', 'step' => 'Tapped card or PayPal' ) + self::events( 'pro_checkout', $from, $to, 'pro|' ),
                array( 'key' => 'pro_order',    'step' => 'Submitted payment details (order created)' ) + self::events( 'pro_order', $from, $to, 'pro' ),
                array( 'key' => 'purchase',     'step' => 'Paid' ) + self::events( 'purchase', $from, $to, 'pro' ),
            );

            return array(
                'steps'   => $steps,
                'reasons' => self::byMeta( 'pro_sheet', $from, $to, 'pro|' ),
                'methods' => self::byMeta( 'pro_checkout', $from, $to, 'pro|' ),
                'credits' => self::events( 'purchase', $from, $to, 'credits' ),
                'since'   => self::since( 'pro_sheet' ),
            );
        } );
    }

    /* ------------------------------------------------------------------ Scorecard */

    /** The five engines for one range. */
    protected static function engines( $from, $to ) {
        $s = Insights::series( $from, $to );
        $sum = function ( $m ) use ( $s ) { return array_sum( $s[ $m ] ?? array() ); };
        $sponsor = class_exists( 'AdsReport' ) ? (float) ( AdsReport::adminSummary( $from, $to )['revenue'] ?? 0 ) : 0;
        $members = $sum( 'members_confirmed' );
        $pro = (float) $sum( 'revenue' );

        return array(
            'groups'       => $sum( 'groups' ),
            'members'      => $members,
            'shuffles'     => $sum( 'shuffle' ),
            'pro_sales'    => $sum( 'purchase' ),
            'pro_revenue'  => $pro,
            'aff_clicks'   => $sum( 'affiliate_click' ),
            'lists'        => $sum( 'wishlists_mine' ),
            'reservations' => $sum( 'wishlist_reserve' ),
            'sponsor'      => $sponsor,
            'rev_per_1k'   => $members ? round( ( $pro + $sponsor ) / $members * 1000, 1 ) : 0,
        );
    }

    public static function labels() {
        return array(
            'groups'       => array( 'Groups created', 'Core product' ),
            'members'      => array( 'Members confirmed', 'Core product' ),
            'shuffles'     => array( 'Shuffles', 'Core product' ),
            'pro_sales'    => array( 'PRO sales (incl. unshuffle credits)', 'Premium' ),
            'pro_revenue'  => array( 'PRO revenue (PHP)', 'Premium' ),
            'aff_clicks'   => array( 'Affiliate clicks', 'Affiliate' ),
            'lists'        => array( 'My Wishlist lists created (personal and registries; see Registries for occasion lists)', 'Registries' ),
            'reservations' => array( 'Registry gifts reserved', 'Registries' ),
            'sponsor'      => array( 'Sponsor revenue (PHP)', 'Sponsorship' ),
            'rev_per_1k'   => array( 'Revenue per 1,000 confirmed members (PHP)', 'All' ),
        );
    }

    /** Scorecard for a period vs the previous one: key => array( label, engine, value, prev, change ). */
    public static function scorecard( $p ) {
        list( $from, $to, $pf, $pt ) = $p;

        return self::cached( 'score|' . $from . '|' . $to, $to, function () use ( $from, $to, $pf, $pt ) {
            $now = self::engines( $from, $to );
            $was = self::engines( $pf, $pt );
            $out = array();
            foreach ( self::labels() as $k => $l ) {
                $out[ $k ] = array( 'label' => $l[0], 'engine' => $l[1], 'value' => $now[ $k ], 'prev' => $was[ $k ], 'change' => Insights::change( $now[ $k ], $was[ $k ] ) );
            }

            return $out;
        } );
    }

    /* ------------------------------------------------------------------ Emails */

    /** Email stats (sent, failed, suppressed by kind; delivered, bounces, complaints) and campaigns. */
    public static function emails( $from, $to ) {
        return self::cached( 'emails|' . $from . '|' . $to, $to, function () use ( $from, $to ) {
            global $wpdb;
            $kinds = array();
            $totals = array();
            if ( class_exists( 'EmailDeliver' ) ) {
                foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT kind, metric, SUM(n) n FROM ' . EmailDeliver::statsTable() . ' WHERE d BETWEEN %s AND %s GROUP BY kind, metric', $from, $to ) ) as $r ) {
                    $kinds[ $r->kind ][ $r->metric ] = (int) $r->n;
                    $totals[ $r->metric ] = ( $totals[ $r->metric ] ?? 0 ) + (int) $r->n;
                }
            }
            $campaigns = array();
            if ( class_exists( 'EmailCenter' ) ) {
                $ids = get_posts( array( 'post_type' => EmailCenter::CPT, 'post_status' => 'any', 'posts_per_page' => 30, 'fields' => 'ids', 'date_query' => array( array( 'after' => wp_date( 'Y-m-d', strtotime( $from . ' -60 days' ) ) ) ) ) );
                foreach ( $ids as $id ) {
                    $c = EmailCenter::counts( $id );
                    $campaigns[] = array( 'name' => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ), 'status' => EmailCenter::status( $id ), 'created' => get_the_date( 'Y-m-d', $id ) ) + $c;
                }
            }
            $jobs = array();
            if ( class_exists( 'EmailCenterAdmin' ) && method_exists( 'EmailCenterAdmin', 'jobs' ) ) {
                foreach ( EmailCenterAdmin::jobs() as $j ) {
                    $jobs[] = array( 'name' => html_entity_decode( $j['label'], ENT_QUOTES, 'UTF-8' ), 'last_sent' => $j['last_sent'] ? wp_date( 'Y-m-d H:i', $j['last_sent'] ) : '-', 'sent' => (int) $j['sent'], 'next' => $j['next'] ? wp_date( 'Y-m-d H:i', $j['next'] ) : '-' );
                }
            }
            $sup = 0;
            if ( class_exists( 'EmailDeliver' ) ) $sup = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . EmailDeliver::suppressTable() );

            return array( 'kinds' => $kinds, 'totals' => $totals, 'campaigns' => $campaigns, 'jobs' => $jobs, 'suppressed_all' => $sup );
        } );
    }

    /* ------------------------------------------------------------------ Other activity */

    public static function activityTypes() {
        return array(
            'comment_removed'  => 'Comments removed by PRO organizers',
            'unshuffle'        => 'Groups unshuffled (PRO)',
            'shuffle_reminder' => 'Shuffle reminders sent to organizers',
            'sale_email'       => 'Sale-day email batches',
            'season_email'     => 'Season emails sent',
            'support_handoff'  => 'Help chat: talk to a person',
            'sponsor_click'    => 'Sponsor ad clicks',
            'ad_payment'       => 'Ad payments',
        );
    }

    public static function activity( $from, $to ) {
        return self::cached( 'act|' . $from . '|' . $to, $to, function () use ( $from, $to ) {
            $out = array();
            foreach ( self::activityTypes() as $type => $label ) $out[ $type ] = self::events( $type, $from, $to )['count'];

            return $out;
        } );
    }

    /* ------------------------------------------------------------------ Change log */

    /** Dated list of site changes, newest first: array( array( 'd' => Y-m-d, 't' => text ) ). */
    public static function changelog() {
        $c = get_option( self::CHANGELOG, null );
        if ( ! is_array( $c ) ) {
            $c = self::seed();
            update_option( self::CHANGELOG, $c, false );
        }
        usort( $c, function ( $a, $b ) { return strcmp( $b['d'], $a['d'] ); } );

        return $c;
    }

    protected static function seed() {
        return array(
            array( 'd' => '2026-11-02', 't' => 'My Wishlist launched; start a new group from an old one; Season email to last season\'s organizers; PRO: anonymous Q&A, co-organizer, group colors.' ),
            array( 'd' => '2026-11-03', 't' => 'Event log started (PRO sales, affiliate clicks, shuffles). Manito Insights + Export for AI. Help chat (Ask Manito). PRO sheet redesigned (price and checkout first). Self-serve sponsor ads launched. /online-bunutan/ landing page.' ),
            array( 'd' => '2026-11-04', 't' => '8 registry guide pages + registry extras (QR cards, owner digest, guest reminders). One confirmed spot per email per group. Sale-day emails (11.11/12.12). Manito Emails dashboard with SES bounce/complaint feedback. Footer rebalanced.' ),
            array( 'd' => '2026-11-05', 't' => 'PRO: organizers can remove comments; free groups see a locked "Remove - PRO" button.' ),
            array( 'd' => '2026-11-05', 't' => 'PRO funnel tracking fixed: GA4 begin_checkout now = tapped card/PayPal, add_payment_info = submitted details (before this the two were reversed). Site-side funnel log started (pro_sheet, pro_checkout, pro_order). PRO sheet copy leads with exclusions and "anyone in the group can pay"; PRO buttons say "Get PRO, P199". Match screen: Lazada/Shopee buttons within budget when the match has no wishlist (placement match-empty-shop).' ),
        );
    }

    public static function changelogForm() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $lines = array();
        foreach ( self::changelog() as $e ) $lines[] = $e['d'] . ' ' . $e['t'];
        echo '<details class="mmi-panel mmi-changelog"><summary><strong>Change log</strong> <span class="mmi-muted">(goes into Export for AI, so each period\'s numbers can be read against what changed)</span></summary>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'mm_changelog' );
        echo '<input type="hidden" name="action" value="mm_changelog_save">';
        echo '<p class="description">One change per line: <code>YYYY-MM-DD what changed</code>.</p>';
        echo '<textarea name="log" rows="8" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
        echo '<p><button class="button button-primary">Save change log</button></p></form></details>';
    }

    public static function saveChangelog() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mm_changelog' ) ) wp_die( 'Not allowed.' );
        $out = array();
        foreach ( preg_split( '/\r\n|\r|\n/', wp_unslash( (string) ( $_POST['log'] ?? '' ) ) ) as $line ) {
            if ( preg_match( '/^\s*(\d{4}-\d{2}-\d{2})\s*[-:]?\s*(.+)$/', $line, $m ) ) $out[] = array( 'd' => $m[1], 't' => sanitize_text_field( $m[2] ) );
        }
        update_option( self::CHANGELOG, $out, false );
        wp_safe_redirect( admin_url( 'admin.php?page=' . Insights::PAGE . '&changelog=saved' ) );
        exit;
    }

    /* ------------------------------------------------------------------ Export for AI */

    protected static function table( array $head, array $rows ) {
        if ( ! $rows ) return "_No data._\n";
        $cell = function ( $v ) { return str_replace( array( '|', "\r", "\n" ), array( '/', ' ', ' ' ), (string) $v ); };
        $o = '| ' . implode( ' | ', $head ) . " |\n|" . str_repeat( ' --- |', count( $head ) ) . "\n";
        foreach ( $rows as $r ) $o .= '| ' . implode( ' | ', array_map( $cell, $r ) ) . " |\n";

        return $o;
    }

    protected static function n( $v ) {
        return is_numeric( $v ) ? ( floor( (float) $v ) == (float) $v ? number_format( (float) $v ) : number_format( (float) $v, 1 ) ) : (string) $v;
    }

    protected static function chg( $c ) {
        return $c === null ? 'new' : ( $c > 0 ? '+' : '' ) . $c . '%';
    }

    /** Extra data notes (when each source started). */
    public static function dataNotes() {
        $md = array();
        $s = self::since( 'pro_sheet' );
        $md[] = '- Site-side PRO funnel steps (sheet opened, card/PayPal tapped, order created) are logged since **' . ( $s ?: '2026-11-05' ) . '**; they count even when GA4 is blocked.';
        $md[] = '- GA4 checkout events changed meaning on 2026-11-05: before it, add_payment_info fired before begin_checkout. GA4 "view_promotion" counts PRO banner impressions as well as sheet opens, so use the site-side funnel for sheet opens.';
        $s = self::since( 'wishlist_reserve' );
        $md[] = '- Registry reservations are logged since ' . ( $s ?: '2026-11-04' ) . '; email stats (sent/bounces by kind) since Manito Emails launched on 2026-11-04; comment removals since 2026-11-05.';
        $md[] = '- Affiliate commissions are not in this report: every store link carries aff_sub = placement and aff_sub2 = group id, so commissions per placement are in the Involve Asia report.';

        return implode( "\n", $md );
    }

    /** Scorecard section (near the top of the export). */
    public static function scorecardMarkdown( $p ) {
        $rows = array();
        foreach ( self::scorecard( $p ) as $k => $x ) {
            $rows[] = array( $x['engine'], $x['label'], self::n( $x['value'] ), self::n( $x['prev'] ), self::chg( $x['change'] ) );
        }

        return "## Business scorecard (five engines)\n" . self::table( array( 'Engine', 'Metric', 'This period', 'Previous period', 'Change' ), $rows );
    }

    /** Sections after the existing ones: site PRO funnel, emails, other activity, change log. */
    public static function sectionsMarkdown( $p ) {
        list( $from, $to ) = $p;
        $md = array();

        $f = self::proFunnel( $from, $to );
        $md[] = '## PRO funnel (site log, not blocked by ad blockers)';
        $rows = array();
        $prev = null;
        foreach ( $f['steps'] as $s ) {
            $rows[] = array( $s['step'], self::n( $s['count'] ), self::n( $s['groups'] ), $prev ? round( $s['count'] / $prev * 100, 1 ) . '%' : '-' );
            $prev = $s['count'];
        }
        $md[] = self::table( array( 'Step', 'Times', 'Groups', '% of previous step' ), $rows );
        $md[] = '- Unshuffle credit purchases in this period: ' . self::n( $f['credits']['count'] );
        $md[] = '';
        $md[] = '### What opened the PRO sheet (reason)';
        $md[] = $f['reasons'] ? self::table( array( 'Reason', 'Opens' ), array_map( null, array_keys( $f['reasons'] ), array_values( $f['reasons'] ) ) ) : "_None in this period._\n";
        $md[] = '### Payment method tapped';
        $md[] = $f['methods'] ? self::table( array( 'Method', 'Taps' ), array_map( null, array_keys( $f['methods'] ), array_values( $f['methods'] ) ) ) : "_None in this period._\n";

        $e = self::emails( $from, $to );
        $md[] = '## Emails (Manito Emails and automatic emails)';
        $t = $e['totals'];
        $sent = (int) ( $t['sent'] ?? 0 );
        $md[] = self::table( array( 'Metric', 'Value' ), array(
            array( 'Emails sent', self::n( $sent ) ),
            array( 'Failed to send', self::n( $t['failed'] ?? 0 ) ),
            array( 'Skipped (suppressed inbox)', self::n( $t['suppressed'] ?? 0 ) ),
            array( 'Delivered (SES)', 'not tracked (SES only reports bounces and complaints to us)' ),
            array( 'Hard bounces', self::n( $t['bounce'] ?? 0 ) . ( $sent ? ' (' . round( ( $t['bounce'] ?? 0 ) / $sent * 100, 2 ) . '%)' : '' ) ),
            array( 'Soft bounces', self::n( $t['soft_bounce'] ?? 0 ) ),
            array( 'Refused by SES (already on its suppression list; not counted by SES)', self::n( $t['ses_listed'] ?? 0 ) ),
            array( 'Complaints', self::n( $t['complaint'] ?? 0 ) . ( $sent ? ' (' . round( ( $t['complaint'] ?? 0 ) / $sent * 100, 3 ) . '%)' : '' ) ),
            array( 'Suppressed inboxes (all time)', self::n( $e['suppressed_all'] ) ),
        ) );
        $md[] = '### Sent by email type';
        $rows = array();
        foreach ( $e['kinds'] as $kind => $m ) {
            if ( $kind === 'any' ) continue;
            $rows[] = array( $kind, self::n( $m['sent'] ?? 0 ), self::n( $m['failed'] ?? 0 ), self::n( $m['suppressed'] ?? 0 ) );
        }
        $md[] = self::table( array( 'Type', 'Sent', 'Failed', 'Skipped (suppressed)' ), $rows );
        $md[] = '### Campaigns';
        $md[] = self::table( array( 'Campaign', 'Created', 'Status', 'Sent', 'Skipped', 'Failed', 'Bounced', 'Opened (est.)', 'Clicked' ), array_map( function ( $c ) {
            return array( $c['name'], $c['created'], $c['status'], $c['sent'], $c['skipped'], $c['failed'], $c['bounced'], $c['opened'], $c['clicked'] );
        }, $e['campaigns'] ) );
        $md[] = '### Automatic emails';
        $md[] = self::table( array( 'Job', 'Last batch', 'Sent in last batch', 'Next run' ), array_map( function ( $j ) { return array( $j['name'], $j['last_sent'], $j['sent'], $j['next'] ); }, $e['jobs'] ) );

        $a = self::activity( $from, $to );
        $md[] = '## Other activity (site log)';
        $rows = array();
        foreach ( self::activityTypes() as $type => $label ) $rows[] = array( $label, self::n( $a[ $type ] ?? 0 ) );
        $md[] = self::table( array( 'Event', 'Count' ), $rows );

        $md[] = '## Change log (what changed on the site, newest first)';
        $md[] = 'Use this to explain changes in the numbers. Changes inside the period are marked.';
        $md[] = '';
        foreach ( array_slice( self::changelog(), 0, 30 ) as $c ) {
            $in = $c['d'] >= $from && $c['d'] <= $to;
            $md[] = '- ' . $c['d'] . ( $in ? ' **(in period)**' : '' ) . ': ' . $c['t'];
        }
        $md[] = '';

        return implode( "\n", $md );
    }
}

InsightsReport::init();
