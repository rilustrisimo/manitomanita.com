<?php
/**
 * EmailCenter Class. Promo campaigns for Manito Emails (admin screens: EmailCenterAdmin):
 *  - CPT mm_campaign: meta _mm_cmp (settings), _mm_cmp_status (draft | scheduled | sending | paused | done | cancelled).
 *  - Queue {prefix}mm_email_queue: one row per inbox, snapshotted when sending starts; addresses wiped after 90 days.
 *  - Runner (WP-Cron every 2 minutes): due campaigns send `rate` emails per run inside their send window
 *    (Manila), under a daily ceiling; each row is claimed before sending, so nothing goes out twice.
 *    Unsubscribes, bounces, the frequency cap and "gift bought" are re-checked right before each email.
 *  - Personal tokens, signed click links (/?mm_em=..) and an open pixel (/wp-json/mm/v1/em-open).
 * Automatic (cron) emails report runs with EmailCenter::jobRan( $key, $sent ) for the Automatic tab.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailCenter {
    const CPT        = 'mm_campaign';
    const HOOK       = 'mm_email_runner';
    const DB_VERSION = 1;
    const CEILING    = 30000; // emails per day across campaigns (SES quota is 50k); option mm_email_ceiling overrides

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'init', array( __CLASS__, 'install' ), 0 );
        add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_action( 'template_redirect', array( __CLASS__, 'click' ), 1 );
        add_action( 'mm_email_suppressed', array( __CLASS__, 'onSuppressed' ), 10, 3 );
        add_action( 'mm_email_daily', array( __CLASS__, 'purge' ) );
        // Measure every automatic email job without touching its code: emails sent between start and end.
        foreach ( self::jobHooks() as $hook => $key ) {
            add_action( $hook, function () use ( $hook ) { self::$jobStart[ $hook ] = Emails::$sentCount; }, -1000 );
            add_action( $hook, function () use ( $hook, $key ) { self::jobRan( $key, Emails::$sentCount - ( self::$jobStart[ $hook ] ?? Emails::$sentCount ) ); }, 1000 );
        }
        if ( ! wp_next_scheduled( 'mm_email_daily' ) ) wp_schedule_event( time() + 3600, 'daily', 'mm_email_daily' );
    }

    public static function queueTable() { global $wpdb; return $wpdb->prefix . 'mm_email_queue'; }

    public static function register() {
        register_post_type( self::CPT, array( 'label' => 'Email campaigns', 'public' => false, 'show_ui' => false, 'supports' => array( 'title' ) ) );
    }

    public static function install() {
        if ( (int) get_option( 'mm_email_center_db' ) >= self::DB_VERSION ) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( 'CREATE TABLE ' . self::queueTable() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            email varchar(190) NOT NULL DEFAULT '',
            inbox char(64) NOT NULL,
            ref_type varchar(12) NOT NULL DEFAULT '',
            ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
            gid bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(12) NOT NULL DEFAULT 'queued',
            reason varchar(60) NOT NULL DEFAULT '',
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY camp_inbox (campaign_id,inbox),
            KEY camp_status (campaign_id,status),
            KEY inbox_sent (inbox,sent_at)
        ) " . $wpdb->get_charset_collate() . ';' );
        update_option( 'mm_email_center_db', self::DB_VERSION, false );
    }

    public static function schedules( $s ) {
        $s['mm_two_min'] = array( 'interval' => 120, 'display' => 'Every 2 minutes (Manito Manita)' );

        return $s;
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 90, 'mm_two_min', self::HOOK );
    }

    /* ------------------------------------------------------------------ Campaign data */

    public static function defaults() {
        return array(
            'audience' => EmailAudience::defaults(),
            'content'  => array( 'subject' => '', 'preheader' => '', 'eyebrow' => '', 'title' => '', 'body' => '', 'button_label' => 'Open your group', 'button_url' => '{group_link}', 'ideas' => 0, 'wish' => 0, 'card' => 1 ),
            'send_at'  => 0,
            'window'   => array( 8, 21 ),
            'rate'     => 150,
            'tested'   => '',
            'total'    => 0,
            'started'  => 0,
            'finished' => 0,
            'log'      => array(),
        );
    }

    public static function get( $id ) {
        $d = get_post_meta( (int) $id, '_mm_cmp', true );
        $d = is_array( $d ) ? array_replace_recursive( self::defaults(), $d ) : self::defaults();
        $d['audience'] = EmailAudience::clean( $d['audience'] );

        return $d;
    }

    public static function status( $id ) {
        return (string) get_post_meta( (int) $id, '_mm_cmp_status', true ) ?: 'draft';
    }

    public static function save( $id, array $d ) {
        update_post_meta( (int) $id, '_mm_cmp', $d );
    }

    public static function setStatus( $id, $status, $note = '' ) {
        update_post_meta( (int) $id, '_mm_cmp_status', $status );
        $d = self::get( $id );
        array_unshift( $d['log'], array( time(), $status, $note ) );
        $d['log'] = array_slice( $d['log'], 0, 30 );
        self::save( $id, $d );
    }

    /** Hash of what a test must cover (content + audience): scheduling needs a test of exactly this. */
    public static function testHash( array $d ) {
        return md5( wp_json_encode( array( $d['content'], $d['audience'] ) ) );
    }

    /** Queue counts: status => n, plus opens, clicks. */
    public static function counts( $id ) {
        global $wpdb;
        $out = array( 'queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'bounced' => 0, 'opened' => 0, 'clicked' => 0 );
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) n FROM ' . self::queueTable() . ' WHERE campaign_id = %d GROUP BY status', (int) $id ) ) as $r ) $out[ $r->status ] = (int) $r->n;
        $oc = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(opened_at IS NOT NULL) o, SUM(clicked_at IS NOT NULL) c FROM ' . self::queueTable() . ' WHERE campaign_id = %d', (int) $id ) );
        $out['opened'] = (int) ( $oc->o ?? 0 );
        $out['clicked'] = (int) ( $oc->c ?? 0 );

        return $out;
    }

    /** Skip reasons for a campaign: reason => n. */
    public static function reasons( $id ) {
        global $wpdb;
        $out = array();
        foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT reason, COUNT(*) n FROM ' . self::queueTable() . " WHERE campaign_id = %d AND status IN ('skipped','failed','bounced') GROUP BY reason ORDER BY n DESC", (int) $id ) ) as $r ) $out[ $r->reason ?: 'other' ] = (int) $r->n;

        return $out;
    }

    /* ------------------------------------------------------------------ Rendering */

    /** Values for the personal tokens of one recipient. */
    public static function tokens( $refType, $refId, $gid ) {
        $gid = (int) $gid;
        $budget = class_exists( 'Affiliate' ) ? Affiliate::budget( $gid ) : 0;
        $d = DateTime::createFromFormat( '!Ymd', (string) get_post_meta( $gid, 'gift_exchange_date', true ), wp_timezone() );
        $days = $d ? (int) floor( ( $d->getTimestamp() - strtotime( 'today', time() ) ) / DAY_IN_SECONDS ) : null;
        if ( $refType === 'member' ) {
            $first = trim( (string) get_field( 'screen_name', $refId ) ) ?: strtok( trim( (string) get_field( 'name', $refId ) ), ' ' );
            $pair = get_field( 'matched', $gid ) ? (int) get_field( 'pair', $refId, false ) : 0;
            $match = $pair ? trim( (string) get_field( 'screen_name', $pair ) ) : '';
        } else {
            $first = strtok( trim( (string) get_field( 'your_name', $gid ) ), ' ' );
            $match = '';
        }

        return array(
            'first_name'    => $first ?: 'there',
            'group_name'    => html_entity_decode( (string) get_field( 'group_name', $gid ), ENT_QUOTES, 'UTF-8' ) ?: 'your group',
            'match_name'    => $match ?: 'your match',
            'budget'        => $budget ? '₱' . number_format( $budget ) : 'your budget',
            'exchange_date' => $d ? wp_date( 'l, F j', $d->getTimestamp() ) : 'the exchange',
            'days_left'     => $days !== null && $days >= 0 ? (string) $days : 'a few',
            'group_link'    => $gid ? Emails::groupLink( $gid ) : home_url( '/' ),
            'start_link'    => ( $refType === 'organizer' && class_exists( 'Repeat' ) ) ? Repeat::signedLink( $gid ) : home_url( '/' ),
        );
    }

    public static function tokenNames() {
        return array( 'first_name', 'group_name', 'match_name', 'budget', 'exchange_date', 'days_left', 'group_link', 'start_link' );
    }

    protected static function fill( $text, array $t, $html = true ) {
        return preg_replace_callback( '/\{([a-z_]+)\}/', function ( $m ) use ( $t, $html ) {
            if ( ! isset( $t[ $m[1] ] ) ) return $m[0];
            return $html ? esc_html( $t[ $m[1] ] ) : $t[ $m[1] ];
        }, (string) $text );
    }

    protected static function sig( $what ) {
        return substr( hash_hmac( 'sha256', $what, wp_salt( 'auth' ) ), 0, 20 );
    }

    public static function trackUrl( $qid, $url ) {
        if ( ! $qid || ! preg_match( '~^https?://~i', $url ) ) return $url;

        return add_query_arg( array( 'mm_em' => (int) $qid, 'u' => rtrim( strtr( base64_encode( $url ), '+/', '-_' ), '=' ), 's' => self::sig( 'c' . $qid . '|' . $url ) ), home_url( '/' ) );
    }

    /**
     * Subject + Emails::send options for one recipient. $qid = queue row (0 for tests/previews: no tracking).
     */
    public static function message( $id, $refType, $refId, $gid, $email, $qid = 0, $d = null ) {
        $d = is_array( $d ) ? $d : self::get( $id );
        $c = $d['content'];
        $t = self::tokens( $refType, $refId, $gid );
        $track = function ( $url ) use ( $qid ) { return self::trackUrl( $qid, $url ); };

        $body = self::fill( wp_kses_post( (string) $c['body'] ), $t );
        $body = preg_replace_callback( '~href="([^"]+)"~i', function ( $m ) use ( $track, $t ) { return 'href="' . esc_url( $track( html_entity_decode( self::fill( $m[1], $t, false ) ) ) ) . '"'; }, $body );
        // Plain paragraphs typed without tags become <p> blocks.
        if ( strpos( $body, '<p' ) === false ) $body = wpautop( $body );
        $blocks = array();
        foreach ( preg_split( '~(?=<p[ >])|(?=<ul)|(?=<ol)~', $body ) as $b ) if ( trim( wp_strip_all_tags( $b ) ) !== '' ) $blocks[] = trim( $b );

        $raw = '';
        if ( ! empty( $c['wish'] ) && $refType === 'member' && $t['match_name'] !== 'your match' ) {
            $pair = (int) get_field( 'pair', $refId, false );
            if ( $pair ) $raw .= Affiliate::shopEmailHtml( $gid, 'email-campaign-' . (int) $id . '-wish', Groups::wishKeywords( $pair ), 'Shop for ' . $t['match_name'] );
        }
        if ( ! empty( $c['ideas'] ) && $gid ) $raw .= Affiliate::giftIdeasHtml( $gid, 'email-campaign-' . (int) $id, true );

        $o = array(
            'kind'        => 'promo',
            'preheader'   => self::fill( $c['preheader'], $t ),
            'eyebrow'     => self::fill( $c['eyebrow'], $t ),
            'title'       => self::fill( $c['title'], $t ),
            'body'        => $blocks,
            'card'        => ! empty( $c['card'] ) && $gid ? Emails::groupCard( $gid ) : array(),
            'raw'         => $raw,
            'promo_unsub' => EmailDeliver::promoUnsubUrl( $email ),
            'footer'      => 'You&rsquo;re getting this because you&rsquo;re ' . ( $refType === 'member' ? 'in' : 'the organizer of' ) . ' ' . esc_html( $t['group_name'] ) . ' on Manito Manita.',
        );
        if ( trim( (string) $c['button_label'] ) !== '' ) $o['button'] = array( self::fill( $c['button_label'], $t, false ), $track( self::fill( (string) $c['button_url'] ?: '{group_link}', $t, false ) ) );
        if ( $qid ) $o['pixel'] = add_query_arg( array( 'q' => (int) $qid, 's' => self::sig( 'o' . $qid ) ), rest_url( 'mm/v1/em-open' ) );

        return array( self::fill( $c['subject'], $t, false ), $o );
    }

    /** Warnings before scheduling (spam signals, missing parts). */
    public static function spamCheck( array $c ) {
        $w = array();
        $subj = html_entity_decode( (string) $c['subject'], ENT_QUOTES, 'UTF-8' );
        $text = wp_strip_all_tags( (string) $c['body'] );
        if ( $subj === '' ) $w[] = 'Add a subject.';
        elseif ( mb_strlen( $subj ) > 70 ) $w[] = 'The subject is long (' . mb_strlen( $subj ) . ' characters); 40–60 reads best on phones.';
        if ( preg_match( '/\b[A-Z]{5,}\b/', $subj . ' ' . $text ) ) $w[] = 'Words in ALL CAPS look like spam.';
        if ( preg_match( '/!!|\?\?|\$\$|₱₱/', $subj . $text ) ) $w[] = 'Repeated !!! or ??? look like spam.';
        if ( preg_match( '/\b(free money|act now|click here|guaranteed|winner|100% free|limited time only|urgent)\b/i', $subj . ' ' . $text ) ) $w[] = 'Some words (e.g. "click here", "guaranteed", "act now") trigger spam filters.';
        if ( substr_count( strtolower( (string) $c['body'] ), '<a ' ) > 5 ) $w[] = 'More than 5 links in the text; keep it to a few.';
        if ( mb_strlen( $text ) < 40 ) $w[] = 'The message is very short; add a sentence or two.';
        if ( trim( (string) $c['preheader'] ) === '' ) $w[] = 'Add a preheader (the grey preview line in the inbox).';

        return $w;
    }

    /* ------------------------------------------------------------------ Sending */

    protected static function inWindow( array $d ) {
        $h = (int) wp_date( 'G' );

        return $h >= (int) $d['window'][0] && $h < (int) $d['window'][1];
    }

    /** Snapshot the audience into the queue (when sending starts). */
    public static function snapshot( $id ) {
        global $wpdb;
        $d = self::get( $id );
        $b = EmailAudience::build( $d['audience'], $id );
        $vals = array();
        foreach ( $b['recipients'] as $h => $r ) {
            $vals[] = $wpdb->prepare( '(%d, %s, %s, %s, %d, %d)', $id, $r['email'], $h, $r['ref_type'], $r['ref_id'], $r['gid'] );
            if ( count( $vals ) >= 500 ) { $wpdb->query( 'INSERT IGNORE INTO ' . self::queueTable() . ' (campaign_id, email, inbox, ref_type, ref_id, gid) VALUES ' . implode( ',', $vals ) ); $vals = array(); }
        }
        if ( $vals ) $wpdb->query( 'INSERT IGNORE INTO ' . self::queueTable() . ' (campaign_id, email, inbox, ref_type, ref_id, gid) VALUES ' . implode( ',', $vals ) );
        $d['total'] = count( $b['recipients'] );
        $d['started'] = time();
        $d['skipped_at_start'] = $b['skipped'];
        self::save( $id, $d );

        return $d['total'];
    }

    public static function run() {
        global $wpdb;
        $lock = 'mm_email_runner_lock';
        if ( get_transient( $lock ) ) return; // the previous run is still going
        set_transient( $lock, 1, 110 );
        @set_time_limit( 110 );

        $ceiling = (int) get_option( 'mm_email_ceiling', self::CEILING );
        $ids = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC',
            'meta_query' => array( array( 'key' => '_mm_cmp_status', 'value' => array( 'scheduled', 'sending' ), 'compare' => 'IN' ) ) ) );

        foreach ( $ids as $id ) {
            $d = self::get( $id );
            if ( (int) $d['send_at'] > time() ) continue;
            if ( self::status( $id ) === 'scheduled' ) {
                $n = self::snapshot( $id );
                self::setStatus( $id, 'sending', $n . ' recipients' );
                $d = self::get( $id );
            }
            if ( ! self::inWindow( $d ) ) continue; // waits for its send window
            $today = EmailDeliver::totals( 1 );
            if ( ( $today['sent'] ?? 0 ) >= $ceiling ) break;

            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::queueTable() . " WHERE campaign_id = %d AND status = 'queued' ORDER BY id LIMIT %d", $id, max( 1, min( 500, (int) $d['rate'] ) ) ) );
            if ( ! $rows ) {
                $d['finished'] = time();
                self::save( $id, $d );
                self::setStatus( $id, 'done' );
                continue;
            }
            $cap = (int) $d['audience']['cap_days'];
            foreach ( $rows as $r ) {
                // Claim the row first: overlapping runs never send twice.
                if ( ! $wpdb->update( self::queueTable(), array( 'status' => 'sending' ), array( 'id' => $r->id, 'status' => 'queued' ) ) ) continue;
                $why = '';
                if ( EmailDeliver::blocked( $r->email, true ) ) $why = 'unsubscribed or bounced';
                elseif ( $cap > 0 && $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::queueTable() . " WHERE inbox = %s AND status = 'sent' AND campaign_id <> %d AND sent_at > %s LIMIT 1", $r->inbox, $id, wp_date( 'Y-m-d H:i:s', time() - $cap * DAY_IN_SECONDS ) ) ) ) $why = 'got another promo recently';
                elseif ( $r->ref_type === 'member' && $d['audience']['gift'] === 'not_bought' && in_array( get_post_meta( $r->ref_id, '_mm_gift_status', true ), array( 'bought', 'given' ), true ) ) $why = 'gift bought since';
                elseif ( $r->ref_type === 'member' && get_post_status( $r->ref_id ) !== 'publish' ) $why = 'left the group';
                if ( $why !== '' ) { $wpdb->update( self::queueTable(), array( 'status' => 'skipped', 'reason' => $why ), array( 'id' => $r->id ) ); continue; }

                list( $subject, $o ) = self::message( $id, $r->ref_type, $r->ref_id, $r->gid, $r->email, $r->id );
                $ok = Emails::send( $r->email, $subject, $o );
                $wpdb->update( self::queueTable(), array( 'status' => $ok ? 'sent' : 'failed', 'reason' => $ok ? '' : 'mail error', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $r->id ) );
            }
        }
        delete_transient( $lock );
    }

    /** A bounce/complaint for an address we emailed: mark its rows. */
    public static function onSuppressed( $email, $reason, $hash ) {
        global $wpdb;
        if ( $reason === 'promo_unsub' ) return;
        $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::queueTable() . " SET status = 'bounced', reason = %s WHERE inbox = %s AND status = 'sent' AND sent_at > %s", $reason === 'complaint' ? 'marked as spam' : 'bounced', $hash, wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
    }

    /** Daily: forget addresses in queue rows older than 90 days (counts stay). */
    public static function purge() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::queueTable() . " SET email = '' WHERE email <> '' AND sent_at IS NOT NULL AND sent_at < %s", wp_date( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
    }

    /* ------------------------------------------------------------------ Tracking */

    public static function routes() {
        register_rest_route( 'mm/v1', '/em-open', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'open' ), 'permission_callback' => '__return_true' ) );
    }

    public static function open( WP_REST_Request $req ) {
        global $wpdb;
        $q = absint( $req->get_param( 'q' ) );
        if ( $q && hash_equals( self::sig( 'o' . $q ), (string) $req->get_param( 's' ) ) ) {
            $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::queueTable() . ' SET opened_at = %s WHERE id = %d AND opened_at IS NULL', current_time( 'mysql' ), $q ) );
        }
        header( 'Content-Type: image/gif' );
        header( 'Cache-Control: no-store' );
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    public static function click() {
        if ( empty( $_GET['mm_em'] ) ) return;
        global $wpdb;
        $q = absint( $_GET['mm_em'] );
        $url = (string) base64_decode( strtr( (string) ( $_GET['u'] ?? '' ), '-_', '+/' ) );
        if ( ! $q || ! preg_match( '~^https?://~i', $url ) || ! hash_equals( self::sig( 'c' . $q . '|' . $url ), (string) ( $_GET['s'] ?? '' ) ) ) { wp_safe_redirect( home_url( '/' ) ); exit; }
        $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::queueTable() . ' SET clicked_at = %s, opened_at = COALESCE(opened_at, %s) WHERE id = %d AND clicked_at IS NULL', current_time( 'mysql' ), current_time( 'mysql' ), $q ) );
        nocache_headers();
        wp_redirect( $url, 302 ); // signed URL, so not an open redirect
        exit;
    }

    /* ------------------------------------------------------------------ Automatic emails */

    protected static $jobStart = array();

    /** Cron hook => job key on the Automatic tab. */
    public static function jobHooks() {
        return array( 'mm_exchange_reminders' => 'reminder', 'mm_auto_draw' => 'autodraw', 'mm_sale_batch' => 'sale', 'mm_theme_emails' => 'drops', 'mm_wl_daily' => 'registry', 'mm_ads_weekly' => 'adsweekly', 'mm_insights_weekly' => 'insights' );
    }

    /** Last run of an automatic email job and how many it sent (idle runs keep the last real batch). */
    public static function jobRan( $key, $sent ) {
        $j = get_option( 'mm_email_jobs', array() );
        $j = is_array( $j ) ? $j : array();
        $prev = $j[ $key ] ?? array();
        $j[ $key ] = array( 'last' => time(), 'sent' => (int) $sent, 'last_sent' => $sent ? time() : (int) ( $prev['last_sent'] ?? 0 ), 'last_sent_n' => $sent ? (int) $sent : (int) ( $prev['last_sent_n'] ?? 0 ) );
        update_option( 'mm_email_jobs', $j, false );
    }
}

EmailCenter::init();
