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
    const DB_VERSION = 2;
    const CANARY_WAIT = 1800;  // seconds to wait for bounce reports after each test batch
    const BOUNCE_STOP = 3.0;   // % hard bounces that pauses a campaign
    const SPAM_STOP   = 0.1;   // % complaints that pauses a campaign
    const SITE_STOP   = 4.0;   // % site-wide bounces (24h) that pauses all campaigns
    const DOMAIN_STOP = 2;     // bounces from one company domain that skip the rest of it
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
            tier tinyint(3) unsigned NOT NULL DEFAULT 1,
            domain varchar(100) NOT NULL DEFAULT '',
            canary tinyint(1) NOT NULL DEFAULT 0,
            status varchar(12) NOT NULL DEFAULT 'queued',
            reason varchar(60) NOT NULL DEFAULT '',
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY camp_inbox (campaign_id,inbox),
            KEY camp_status (campaign_id,status),
            KEY camp_tier (campaign_id,tier,canary,status),
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
        $days = $d ? (int) round( ( $d->getTimestamp() - ( new DateTime( 'today', wp_timezone() ) )->getTimestamp() ) / DAY_IN_SECONDS ) : null; // both Manila midnights
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

    /** Big webmail providers: mailboxes of confirmed users rarely disappear (tier 1, sent first). */
    public static function webmail() {
        return array( 'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.com.ph', 'ymail.com', 'rocketmail.com', 'outlook.com', 'outlook.ph', 'hotmail.com', 'live.com', 'msn.com',
            'icloud.com', 'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'zoho.com', 'yandex.com', 'mail.com' );
    }

    public static function tierOf( $email ) {
        $domain = strtolower( substr( strrchr( (string) $email, '@' ), 1 ) );

        return in_array( $domain, self::webmail(), true ) ? 1 : 2;
    }

    /**
     * Snapshot the audience into the queue (when sending starts): tier 1 (webmail) then tier 2 (company,
     * school); each tier starts with a random test batch (canary) of 5% (20-200), checked for bounces first
     * (tiers under 100 webmail / 40 company addresses are sent without one).
     */
    public static function snapshot( $id ) {
        global $wpdb;
        $d = self::get( $id );
        $b = EmailAudience::build( $d['audience'], $id );
        $byTier = array( 1 => array(), 2 => array() );
        foreach ( $b['recipients'] as $h => $r ) $byTier[ self::tierOf( $r['email'] ) ][ $h ] = $r;

        $vals = array();
        $flush = function () use ( &$vals, $wpdb ) {
            if ( $vals ) $wpdb->query( 'INSERT IGNORE INTO ' . self::queueTable() . ' (campaign_id, email, inbox, ref_type, ref_id, gid, tier, domain, canary) VALUES ' . implode( ',', $vals ) );
            $vals = array();
        };
        foreach ( $byTier as $tier => $rows ) {
            $n = count( $rows );
            // Company/school emails bounce the most, so they get a test batch from 40 addresses (webmail from 100).
            $canary = $n >= ( $tier === 1 ? 100 : 40 ) ? min( 200, max( 20, (int) ceil( $n * 0.05 ) ) ) : 0;
            $pick = $canary ? array_flip( (array) array_rand( $rows, $canary ) ) : array();
            foreach ( $rows as $h => $r ) {
                $vals[] = $wpdb->prepare( '(%d, %s, %s, %s, %d, %d, %d, %s, %d)', $id, $r['email'], $h, $r['ref_type'], $r['ref_id'], $r['gid'], $tier, strtolower( substr( strrchr( $r['email'], '@' ), 1 ) ), isset( $pick[ $h ] ) ? 1 : 0 );
                if ( count( $vals ) >= 500 ) $flush();
            }
        }
        $flush();
        $d['total'] = count( $b['recipients'] );
        $d['tiers'] = array( 1 => count( $byTier[1] ), 2 => count( $byTier[2] ) );
        $d['started'] = time();
        $d['skipped_at_start'] = $b['skipped'];
        self::save( $id, $d );

        return $d['total'];
    }

    /** Hard bounces and complaints of a campaign since the last resume: array( sent, bounced, spam ). */
    public static function rates( $id, array $d ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(status IN (\'sent\',\'bounced\')) s, SUM(status = \'bounced\' AND reason = \'bounced\') b, SUM(status = \'bounced\' AND reason = \'marked as spam\') c FROM ' . self::queueTable() . ' WHERE campaign_id = %d', $id ) );
        $ack = (array) ( $d['ack'] ?? array() );

        if ( ! empty( $d['raw'] ) ) return array( (int) ( $r->s ?? 0 ), (int) ( $r->b ?? 0 ), (int) ( $r->c ?? 0 ) );

        return array( max( 0, (int) ( $r->s ?? 0 ) - (int) ( $ack['s'] ?? 0 ) ), max( 0, (int) ( $r->b ?? 0 ) - (int) ( $ack['b'] ?? 0 ) ), max( 0, (int) ( $r->c ?? 0 ) - (int) ( $ack['c'] ?? 0 ) ) );
    }

    /** '' when the campaign may keep sending, else why it must pause. */
    public static function breaker( $id, array $d ) {
        list( $s, $b, $c ) = self::rates( $id, $d );
        if ( $s >= 20 && $b >= 3 && $b / $s * 100 > self::BOUNCE_STOP ) return 'bounce rate ' . round( $b / $s * 100, 1 ) . '% (' . $b . ' of ' . $s . ')';
        if ( $s >= 20 && $c >= 2 && $c / $s * 100 > self::SPAM_STOP ) return 'spam complaints ' . round( $c / $s * 100, 2 ) . '% (' . $c . ' of ' . $s . ')';

        return '';
    }

    /** Site-wide: bounce rate of the last 24 hours (all email) when it is too high, else ''. */
    public static function siteBreaker() {
        $t = EmailDeliver::totals( 1 );
        $sent = (int) ( $t['sent'] ?? 0 );
        $b = (int) ( $t['bounce'] ?? 0 );

        return ( $sent >= 200 && $b / $sent * 100 > self::SITE_STOP ) ? 'site-wide bounce rate ' . round( $b / $sent * 100, 1 ) . '% today' : '';
    }

    public static function autoPause( $id, $why ) {
        $d = self::get( $id );
        $d['auto_paused'] = $why;
        self::save( $id, $d );
        self::setStatus( $id, 'paused', 'auto-paused: ' . $why );
        do_action( 'mm_alert', 'campaign', 'Campaign "' . get_the_title( $id ) . '" paused itself: ' . $why, array( 'campaign' => $id, 'review' => admin_url( 'admin.php?page=mm-emails&tab=edit&id=' . $id ) ) );
    }

    /**
     * What a campaign sends next: array( stage label, rows SQL where, wait reason ). Order: tier 1 test batch,
     * wait for bounce reports, rest of tier 1, tier 2 test batch, wait, rest of tier 2.
     */
    public static function stage( $id ) {
        global $wpdb;
        $q = self::queueTable();
        foreach ( array( 1, 2 ) as $t ) {
            $name = $t === 1 ? 'Webmail' : 'Company & school emails';
            if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $q WHERE campaign_id = %d AND tier = %d AND canary = 1 AND status = 'queued' LIMIT 1", $id, $t ) ) ) {
                return array( $name . ' · test batch', $wpdb->prepare( 'tier = %d AND canary = 1', $t ), '' );
            }
            $rest = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $q WHERE campaign_id = %d AND tier = %d AND canary = 0 AND status = 'queued' LIMIT 1", $id, $t ) );
            if ( ! $rest ) continue;
            $last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sent_at) FROM $q WHERE campaign_id = %d AND tier = %d AND canary = 1", $id, $t ) );
            $lastTs = $last ? ( new DateTime( $last, wp_timezone() ) )->getTimestamp() : 0; // sent_at is Manila time, not UTC
            if ( $lastTs && $lastTs > time() - self::CANARY_WAIT ) {
                return array( $name . ' · checking test batch', '', 'waiting for bounce reports until ' . wp_date( 'g:i A', $lastTs + self::CANARY_WAIT ) );
            }

            return array( $name, $wpdb->prepare( 'tier = %d', $t ), '' );
        }

        return array( 'Finishing', '', '' );
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

        $site = $ids ? self::siteBreaker() : '';
        foreach ( $ids as $id ) {
            $d = self::get( $id );
            if ( (int) $d['send_at'] > time() ) continue;
            if ( $site !== '' ) { if ( self::status( $id ) === 'sending' ) self::autoPause( $id, $site ); continue; } // protect the whole domain
            if ( self::status( $id ) === 'scheduled' ) {
                $n = self::snapshot( $id );
                self::setStatus( $id, 'sending', $n . ' recipients' );
                $d = self::get( $id );
            }
            if ( ! self::inWindow( $d ) ) continue; // waits for its send window
            $why = self::breaker( $id, $d );
            if ( $why !== '' ) { self::autoPause( $id, $why ); continue; }
            $today = EmailDeliver::totals( 1 );
            if ( ( $today['sent'] ?? 0 ) >= $ceiling ) break;

            list( , $where, $wait ) = self::stage( $id );
            if ( $wait !== '' ) continue; // test batch results not in yet
            if ( $where === '' ) {
                if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::queueTable() . " WHERE campaign_id = %d AND status IN ('queued','sending') LIMIT 1", $id ) ) ) {
                    $d['finished'] = time();
                    self::save( $id, $d );
                    self::setStatus( $id, 'done' );
                }
                continue;
            }
            $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::queueTable() . " WHERE campaign_id = %d AND status = 'queued' AND $where ORDER BY id LIMIT %d", $id, max( 1, min( 500, (int) $d['rate'] ) ) ) );
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

    /**
     * A bounce/complaint for an address we emailed: mark its rows. Two bounces from one company domain in a
     * campaign skip the rest of that domain (a closed company shouldn't cost dozens of bounces).
     */
    public static function onSuppressed( $email, $reason, $hash ) {
        global $wpdb;
        if ( $reason === 'promo_unsub' ) return;
        $q = self::queueTable();
        $since = wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, campaign_id, domain, tier FROM $q WHERE inbox = %s AND status = 'sent' AND sent_at > %s", $hash, $since ) );
        $wpdb->query( $wpdb->prepare( "UPDATE $q SET status = 'bounced', reason = %s WHERE inbox = %s AND status = 'sent' AND sent_at > %s", $reason === 'complaint' ? 'marked as spam' : 'bounced', $hash, $since ) );
        if ( $reason === 'complaint' ) return;
        foreach ( $rows as $r ) {
            if ( (int) $r->tier !== 2 || $r->domain === '' ) continue;
            $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $q WHERE campaign_id = %d AND domain = %s AND status = 'bounced' AND reason = 'bounced'", $r->campaign_id, $r->domain ) );
            if ( $n >= self::DOMAIN_STOP ) $wpdb->query( $wpdb->prepare( "UPDATE $q SET status = 'skipped', reason = %s WHERE campaign_id = %d AND domain = %s AND status = 'queued'", 'domain ' . $r->domain . ' bounced', $r->campaign_id, $r->domain ) );
        }
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
