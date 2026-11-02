<?php
/**
 * Season email: "Ready for this year's exchange?" to people who organized a group last season.
 * One email per organizer (by email address), linking to their last group with the "Start a new group"
 * flow ready (Repeat class). Sent only when the site owner presses Send in Tools > Season email.
 *
 * Audience: published groups whose exchange date is in the previous season (Sep 1 - Aug 31), or any
 * earlier season if ticked; skipped when that organizer already made a group this season, unsubscribed
 * (any of their groups), or already got this season's email. Sending runs in WP-Cron batches so the
 * SES rate limit is respected; progress is shown on the screen and can be stopped.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class Season {
    const HOOK   = 'mm_season_batch';
    const QUEUE  = 'mm_season_queue';   // option: array( 'ids' => [...], 'sent' => n, 'failed' => n, 'started' => t )
    const BATCH  = 40;                  // per minute
    const PAGE   = 'mm-season-email';
    const SENT   = 'mm_season_sent_';   // option + season year: inbox keys already emailed (backup to the group meta)

    /** Why addresses were left out on the last audience() run: reason => count. */
    public static $skipped = array();

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_mm_season', array( __CLASS__, 'handle' ) );
        add_action( self::HOOK, array( __CLASS__, 'runBatch' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
    }

    public static function schedules( $s ) {
        $s['mm_minute'] = array( 'interval' => 60, 'display' => 'Every minute (Manito Manita)' );

        return $s;
    }

    /** The current season starts Sep 1 (Nov 2026 -> 2026-09-01). */
    public static function seasonStart() {
        $y = (int) wp_date( 'Y' );
        if ( (int) wp_date( 'n' ) < 9 ) $y--;

        return $y . '0901';
    }

    public static function sentMeta() {
        return '_mm_season_sent_' . substr( self::seasonStart(), 0, 4 );
    }

    /**
     * One key per inbox: lowercase, "+tag" removed, and Gmail dots removed (a.b@gmail.com = ab@gmail.com).
     */
    public static function inboxKey( $email ) {
        $email = strtolower( trim( (string) $email ) );
        if ( strpos( $email, '@' ) === false ) return $email;
        list( $user, $domain ) = explode( '@', $email, 2 );
        $user = preg_replace( '/\+.*$/', '', $user );
        if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) { $user = str_replace( '.', '', $user ); $domain = 'gmail.com'; }

        return $user . '@' . $domain;
    }

    /** Domains that receive mail (MX or A record); cached for a week per domain. */
    protected static function domainOk( $domain ) {
        static $seen = array();
        if ( isset( $seen[ $domain ] ) ) return $seen[ $domain ];

        $cached = get_transient( 'mm_mx_' . md5( $domain ) );
        if ( $cached !== false ) return $seen[ $domain ] = ( $cached === 'y' );

        $ok = function_exists( 'checkdnsrr' ) ? ( checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' ) ) : true;
        set_transient( 'mm_mx_' . md5( $domain ), $ok ? 'y' : 'n', WEEK_IN_SECONDS );

        return $seen[ $domain ] = $ok;
    }

    /**
     * Strict check before a bulk send. Returns '' when deliverable, otherwise the reason.
     */
    public static function badEmail( $email ) {
        $email = strtolower( trim( (string) $email ) );
        if ( ! is_email( $email ) || ! preg_match( '/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $email ) ) return 'invalid';

        list( $user, $domain ) = explode( '@', $email, 2 );
        $typos = array( 'gmial.com', 'gmai.com', 'gamil.com', 'gnail.com', 'gmail.co', 'gmail.con', 'gmail.cm', 'gmaill.com', 'gmal.com', 'gmali.com',
            'yaho.com', 'yahooo.com', 'yahoo.con', 'yahoo.co', 'yhoo.com', 'hotmial.com', 'hotmai.com', 'hotmail.co', 'hotmail.con', 'hotnail.com',
            'outlok.com', 'outlook.co', 'outloo.com', 'iclod.com', 'icloud.co', 'ymail.co' );
        if ( in_array( $domain, $typos, true ) ) return 'typo';
        if ( preg_match( '/(^|\.)(example|test|invalid|localhost|mailinator)\.(com|net|org)$|\.(test|invalid|local|example)$/', $domain ) ) return 'test';
        if ( preg_match( '/^(no-?reply|do-?not-?reply|test|asdf|qwerty|sample|dummy|abc|xxx+)$/', $user ) ) return 'placeholder';
        if ( class_exists( 'EmailCheck' ) && EmailCheck::isDisposable( $email ) ) return 'disposable';
        if ( ! self::domainOk( $domain ) ) return 'no-mail-server';

        return '';
    }

    /**
     * Eligible organizers: array( email => array( 'gid' => latest past group, 'name' => organizer, 'group' => group name, 'date' => Ymd ) ).
     */
    public static function audience( $includeOlder = false ) {
        global $wpdb;

        $start = self::seasonStart();
        $prevStart = ( (int) substr( $start, 0, 4 ) - 1 ) . '0901';
        $sent = self::sentMeta();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_date,
                MAX(CASE WHEN m.meta_key = 'gift_exchange_date' THEN m.meta_value END) AS d,
                MAX(CASE WHEN m.meta_key = 'your_email' THEN m.meta_value END) AS email,
                MAX(CASE WHEN m.meta_key = 'your_name' THEN m.meta_value END) AS name,
                MAX(CASE WHEN m.meta_key = 'group_name' THEN m.meta_value END) AS gname,
                MAX(CASE WHEN m.meta_key = %s THEN 1 END) AS optout,
                MAX(CASE WHEN m.meta_key = %s THEN 1 END) AS sent
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('gift_exchange_date', 'your_email', 'your_name', 'group_name', %s, %s)
             WHERE p.post_type = 'groups' AND p.post_status = 'publish'
             GROUP BY p.ID",
            Emails::OPTOUT_META, $sent, Emails::OPTOUT_META, $sent
        ) );

        $active = array();   // organized something this season already (by inbox)
        $blocked = (array) get_option( self::SENT . substr( $start, 0, 4 ), array() ); // inboxes already emailed this season
        $unsub = array();    // unsubscribed from any of their groups
        $out = array();
        $skipped = array();
        $count = function ( $why ) use ( &$skipped ) { $skipped[ $why ] = ( $skipped[ $why ] ?? 0 ) + 1; };

        foreach ( $rows as $r ) {
            $key = self::inboxKey( $r->email );
            if ( $r->optout ) $unsub[ $key ] = true;
            if ( $r->sent ) $blocked[ $key ] = true;
            if ( (string) $r->d >= $start || substr( str_replace( '-', '', $r->post_date ), 0, 8 ) >= $start ) $active[ $key ] = true;
        }

        $bad = array(); // inbox => reason (checked once per inbox)
        foreach ( $rows as $r ) {
            $email = strtolower( trim( (string) $r->email ) );
            $key = self::inboxKey( $email );
            $d = (string) $r->d;
            if ( ! preg_match( '/^\d{8}$/', $d ) || $d >= $start ) continue;       // not a past season
            if ( ! $includeOlder && $d < $prevStart ) continue;

            if ( isset( $active[ $key ] ) ) { $count( 'already started this season' ); continue; }
            if ( isset( $unsub[ $key ] ) ) { $count( 'unsubscribed' ); continue; }
            if ( isset( $blocked[ $key ] ) ) { $count( 'already emailed this season' ); continue; }
            if ( ! isset( $bad[ $key ] ) ) $bad[ $key ] = self::badEmail( $email );
            if ( $bad[ $key ] !== '' ) { $count( 'undeliverable: ' . $bad[ $key ] ); continue; }
            if ( isset( $out[ $key ] ) ) {
                $count( 'duplicate (same inbox)' );
                if ( $out[ $key ]['date'] >= $d ) continue;
            }

            $out[ $key ] = array( 'gid' => (int) $r->ID, 'email' => $email, 'name' => (string) $r->name, 'group' => (string) $r->gname, 'date' => $d );
        }

        self::$skipped = $skipped;

        return $out;
    }

    /* ------------------------------------------------------------------ The email */

    public static function send( $gid, $to = '' ) {
        $gid = (int) $gid;
        $name = trim( (string) get_field( 'your_name', $gid ) );
        $group = (string) get_field( 'group_name', $gid );
        $start = add_query_arg( 'mm_start', '1', Emails::groupLink( $gid ) );
        $first = $name !== '' ? strtok( $name, ' ' ) : 'there';

        return Emails::send( $to ?: (string) get_field( 'your_email', $gid ), 'Ready for this year&rsquo;s exchange, ' . $first . '?', array(
            'preheader'   => 'Start this year&rsquo;s group from ' . $group . ' and invite everyone back in a few taps.',
            'eyebrow'     => 'Christmas is coming',
            'title'       => 'Ready for this year&rsquo;s exchange?',
            'body'        => array(
                '<p>Hi ' . esc_html( $first ) . '! Last year you organized <strong>' . esc_html( $group ) . '</strong> on Manito Manita. Thank you!</p>',
                '<p>Doing it again? Start this year&rsquo;s group from last year&rsquo;s: enter the new date and budget, and invite last year&rsquo;s members with one tap. It&rsquo;s free, as always.</p>',
            ),
            'button'      => array( 'Start this year&rsquo;s group', $start ),
            'note'        => 'Prefer a fresh start? <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#1f8580;">Create a brand-new group</a>. You&rsquo;ll need your moderator password from last year; tap &ldquo;Forgot password&rdquo; if you don&rsquo;t remember it.',
            'unsubscribe' => $gid,
            'footer'      => 'You&rsquo;re getting this once a year because you organized ' . esc_html( $group ) . ' on Manito Manita.',
        ) );
    }

    /* ------------------------------------------------------------------ Sending in batches */

    public static function runBatch() {
        $q = get_option( self::QUEUE );
        if ( ! is_array( $q ) || empty( $q['ids'] ) ) {
            wp_clear_scheduled_hook( self::HOOK );
            return;
        }

        $meta = self::sentMeta();
        $optKey = self::SENT . substr( self::seasonStart(), 0, 4 );
        $done = (array) get_option( $optKey, array() );
        $batch = array_splice( $q['ids'], 0, self::BATCH );

        foreach ( $batch as $gid ) {
            $email = (string) get_field( 'your_email', $gid );
            $key = self::inboxKey( $email );
            if ( isset( $done[ $key ] ) || get_post_meta( $gid, $meta, true ) || Emails::optedOut( $gid ) || self::badEmail( $email ) !== '' ) continue;

            // Mark first, so a crash or a second click never sends twice.
            $done[ $key ] = time();
            update_option( $optKey, $done, false );
            update_post_meta( $gid, $meta, time() );

            if ( self::send( $gid ) ) $q['sent']++;
            else $q['failed']++;
        }

        if ( empty( $q['ids'] ) ) {
            $q['done'] = time();
            wp_clear_scheduled_hook( self::HOOK );
        }
        update_option( self::QUEUE, $q, false );
    }

    /* ------------------------------------------------------------------ Admin screen */

    public static function menu() {
        add_management_page( 'Season email', 'Season email', 'manage_options', self::PAGE, array( __CLASS__, 'screen' ) );
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        check_admin_referer( 'mm_season' );

        $older = ! empty( $_POST['older'] );
        $op = (string) ( $_POST['op'] ?? '' );
        $msg = '';

        if ( $op === 'test' ) {
            $aud = self::audience( $older );
            $first = reset( $aud );
            $me = wp_get_current_user()->user_email;
            $msg = $first ? ( self::send( $first['gid'], $me ) ? 'test-ok' : 'test-fail' ) : 'test-none';
        } elseif ( $op === 'start' ) {
            $q = get_option( self::QUEUE );
            if ( is_array( $q ) && ! empty( $q['ids'] ) ) {
                $msg = 'running';
            } else {
                $ids = array_values( wp_list_pluck( self::audience( $older ), 'gid' ) );
                update_option( self::QUEUE, array( 'ids' => $ids, 'total' => count( $ids ), 'sent' => 0, 'failed' => 0, 'started' => time() ), false );
                if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 5, 'mm_minute', self::HOOK );
                $msg = 'started';
            }
        } elseif ( $op === 'stop' ) {
            $q = get_option( self::QUEUE );
            if ( is_array( $q ) ) { $q['ids'] = array(); $q['stopped'] = time(); update_option( self::QUEUE, $q, false ); }
            wp_clear_scheduled_hook( self::HOOK );
            $msg = 'stopped';
        }

        wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'msg' => $msg, 'older' => $older ? 1 : 0 ), admin_url( 'tools.php' ) ) );
        exit;
    }

    public static function screen() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $older = ! empty( $_GET['older'] );
        $aud = self::audience( $older );
        $q = get_option( self::QUEUE );
        $running = is_array( $q ) && ! empty( $q['ids'] );
        $notes = array(
            'test-ok'   => array( 'success', 'Test email sent to ' . esc_html( wp_get_current_user()->user_email ) . '. Check how it looks before sending to everyone.' ),
            'test-fail' => array( 'error', 'The test email could not be sent.' ),
            'test-none' => array( 'warning', 'Nobody qualifies right now, so there was nothing to test with.' ),
            'started'   => array( 'success', 'Sending started. About ' . self::BATCH . ' emails go out each minute.' ),
            'running'   => array( 'warning', 'A send is already running.' ),
            'stopped'   => array( 'warning', 'Sending stopped. Emails already sent stay sent; the rest were cancelled.' ),
        );
        $msg = (string) ( $_GET['msg'] ?? '' );
        $form = function ( $op, $label, $class = 'button', $confirm = '' ) use ( $older ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;"' . ( $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : '' ) . '>';
            wp_nonce_field( 'mm_season' );
            echo '<input type="hidden" name="action" value="mm_season"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">';
            if ( $older ) echo '<input type="hidden" name="older" value="1">';
            submit_button( $label, $class, 'submit', false );
            echo '</form>';
        };

        echo '<div class="wrap"><h1>Season email</h1>';
        if ( isset( $notes[ $msg ] ) ) echo '<div class="notice notice-' . $notes[ $msg ][0] . ' is-dismissible"><p>' . $notes[ $msg ][1] . '</p></div>';

        echo '<p style="max-width:720px;">One friendly email to people who organized a group last season: <em>&ldquo;Ready for this year&rsquo;s exchange?&rdquo;</em> with a button that starts this year&rsquo;s group from their old one (they enter their moderator password, then the new details; last year&rsquo;s members are invited only if they tick them). Each organizer gets it once per season, with an unsubscribe link.</p>';

        echo '<h2 style="margin-top:24px;">Who gets it</h2>';
        echo '<p style="font-size:16px;"><strong>' . number_format( count( $aud ) ) . '</strong> organizers ' . ( $older ? 'from earlier seasons' : 'from last season (exchange between Sep 1 and Aug 31)' ) . '. Already excluded: anyone who made a group this season, unsubscribed, or already got this email.</p>';
        if ( self::$skipped ) {
            echo '<p class="description" style="max-width:720px;">Left out: ';
            $parts = array();
            foreach ( self::$skipped as $why => $n ) $parts[] = number_format( $n ) . ' ' . esc_html( $why );
            echo implode( ' &middot; ', $parts ) . '. Each person gets at most one email, even with several groups or address variations (dots or +tags in Gmail).</p>';
        }
        echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => self::PAGE, 'older' => $older ? 0 : 1 ), admin_url( 'tools.php' ) ) ) . '">' . ( $older ? 'Only last season' : 'Include all earlier seasons too' ) . '</a></p>';

        if ( $aud ) {
            echo '<table class="widefat striped" style="max-width:720px;"><thead><tr><th>Organizer</th><th>Group</th><th>Exchange</th></tr></thead><tbody>';
            foreach ( array_slice( $aud, 0, 8 ) as $a ) {
                $email = $a['email'];
                $at = strpos( $email, '@' );
                echo '<tr><td>' . esc_html( $a['name'] ) . ' &middot; ' . esc_html( substr( $email, 0, 2 ) . '&hellip;' . substr( $email, $at ) ) . '</td><td>' . esc_html( $a['group'] ) . '</td><td>' . esc_html( wp_date( 'M j, Y', strtotime( $a['date'] ) ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
            if ( count( $aud ) > 8 ) echo '<p class="description">Showing 8 of ' . number_format( count( $aud ) ) . '.</p>';
        }

        echo '<h2 style="margin-top:24px;">Send</h2>';
        if ( is_array( $q ) && ! empty( $q['total'] ) ) {
            $left = count( (array) $q['ids'] );
            echo '<p><strong>' . ( $running ? 'Sending now' : ( ! empty( $q['stopped'] ) ? 'Stopped' : 'Last send finished' ) ) . ':</strong> ' . (int) $q['sent'] . ' sent, ' . (int) $q['failed'] . ' failed' . ( $running ? ', ' . $left . ' left (about ' . ceil( $left / self::BATCH ) . ' min)' : '' ) . '. Started ' . esc_html( wp_date( 'M j, g:i A', (int) $q['started'] ) ) . '.</p>';
        }

        $form( 'test', 'Send a test to me', 'button' );
        if ( $running ) {
            $form( 'stop', 'Stop sending', 'button button-secondary', 'Stop sending the rest?' );
        } elseif ( $aud ) {
            $form( 'start', 'Send to ' . number_format( count( $aud ) ) . ' organizers', 'button button-primary', 'Send the season email to ' . count( $aud ) . ' organizers now? This can\'t be undone (you can stop it midway).' );
        }
        echo '<p class="description" style="margin-top:12px;">Tip: send the test first and open it on your phone. Sending uses WP-Cron (about ' . self::BATCH . ' per minute); keep in mind Amazon SES daily limits.</p>';
        echo '</div>';
    }
}

Season::init();
