<?php
/**
 * EmailListCheck Class. Free list cleanup for campaigns: before company/school emails go out, every domain still
 * queued is looked up (no email is sent):
 *  - the domain doesn't exist, or has no mail server (or says it takes no mail: "null MX") -> 'domain has no mail server'
 *  - a misspelled webmail domain (gmial.com, yaho.com...) -> 'misspelled domain'
 *  - a throwaway inbox domain (EmailCheck's list) -> 'throwaway email domain'
 *  - not an email address at all -> 'not a valid address'
 * Those queue rows are skipped for the campaign. They are NOT added to the suppression list, so a DNS hiccup
 * can never block someone's match emails; the next campaign checks again (domain answers are cached).
 *
 * Runs by itself when a campaign starts, or from "Check addresses" on the Campaigns list. Works in the
 * background (WP cron, ~40 s per run). While it runs, the company & school stage of that campaign waits.
 * Progress lives in the campaign data: $d['check'] = state, domains, done, skipped, by (reason => n), t.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailListCheck {
    const HOOK     = 'mm_ec_list_check';
    const BUDGET   = 40;            // seconds of lookups per run
    const OK_TTL   = 30 * DAY_IN_SECONDS;
    const DEAD_TTL = 7 * DAY_IN_SECONDS;

    /** Common misspellings of webmail domains (never real inboxes for our users). */
    protected static $typos = array(
        'gmial.com', 'gmai.com', 'gamil.com', 'gmali.com', 'gmaill.com', 'gnail.com', 'gmal.com', 'gmail.co', 'gmail.con', 'gmail.cm', 'gmail.om',
        'gmail.comm', 'gmail.cmo', 'gmail.ocm', 'gmaik.com', 'gmsil.com', 'gimail.com', 'gemail.com', 'gmail.com.ph', 'gmail.ph', 'gmeil.com', 'gmil.com',
        'yaho.com', 'yahooo.com', 'yhoo.com', 'yahoo.co', 'yahoo.con', 'yahho.com', 'yaoo.com', 'yahoo.cm', 'yahoo.com.ph.', 'yahoo.ph', 'yhaoo.com',
        'hotmial.com', 'hotmal.com', 'hotmai.com', 'hotmil.com', 'hotmail.co', 'hotmail.con', 'homail.com', 'hotamil.com',
        'outlok.com', 'outllook.com', 'outlook.co', 'outlook.con', 'otlook.com', 'outloook.com',
        'icloud.co', 'icloud.con', 'iclod.com', 'icoud.com', 'icluod.com', 'ymail.co', 'ymail.con',
    );

    public static function init() {
        add_action( self::HOOK, array( __CLASS__, 'work' ) );
    }

    /** Start (or restart) the check of a campaign's queued addresses. */
    public static function start( $id ) {
        $d = EmailCenter::get( $id );
        $d['check'] = array( 'state' => 'running', 'domains' => 0, 'done' => 0, 'skipped' => 0, 'by' => array(), 't' => time() );
        EmailCenter::save( $id, $d );
        if ( ! wp_next_scheduled( self::HOOK, array( (int) $id ) ) ) wp_schedule_single_event( time(), self::HOOK, array( (int) $id ) );
        spawn_cron();
    }

    public static function running( $id, $d = null ) {
        $d = $d ?: EmailCenter::get( $id );
        $c = (array) ( $d['check'] ?? array() );
        // A run that died (cron stopped) shouldn't hold the campaign for ever: give up after 2 hours.
        $on = ( $c['state'] ?? '' ) === 'running' && (int) ( $c['t'] ?? 0 ) > time() - 2 * HOUR_IN_SECONDS;
        // Lost its next run (not scheduled, not working right now): queue it again.
        if ( $on && ! wp_next_scheduled( self::HOOK, array( (int) $id ) ) && ! get_transient( 'mm_ec_lc_lock_' . (int) $id ) ) wp_schedule_single_event( time() + 5, self::HOOK, array( (int) $id ) );

        return $on;
    }

    /** One run: look up as many domains as fit in the budget, skip the dead ones, reschedule until done. */
    public static function work( $id ) {
        global $wpdb;
        $id = (int) $id;
        if ( get_post_type( $id ) !== EmailCenter::CPT || ( EmailCenter::get( $id )['check']['state'] ?? '' ) !== 'running' ) return;
        $lock = 'mm_ec_lc_lock_' . $id;
        if ( get_transient( $lock ) ) return;
        set_transient( $lock, 1, self::BUDGET + 30 );
        @set_time_limit( self::BUDGET + 30 );
        $q = EmailCenter::queueTable();
        $start = microtime( true );

        $d = EmailCenter::get( $id );
        $c = (array) $d['check'];

        // 1) First run: rows that aren't an email address at all.
        $bad = 0;
        if ( empty( $c['syntax'] ) ) {
            $ids = array();
            foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, email FROM $q WHERE campaign_id = %d AND status = 'queued'", $id ) ) as $r ) {
                if ( ! is_email( $r->email ) ) $ids[] = (int) $r->id;
            }
            foreach ( array_chunk( $ids, 500 ) as $chunk ) {
                $bad += (int) $wpdb->query( $wpdb->prepare( "UPDATE $q SET status = 'skipped', reason = 'not a valid address' WHERE campaign_id = %d AND status = 'queued' AND id IN (" . implode( ',', $chunk ) . ')', $id ) );
            }
            $c['syntax'] = 1;
        }

        // 2) Each company/school domain still queued (webmail domains are known good).
        $domains = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT domain FROM $q WHERE campaign_id = %d AND status = 'queued' AND tier = 2 AND domain <> ''", $id ) );
        // Good domains stay queued and come back next run (their answer is cached, so that's instant). Dead
        // ones are gone from the list, so: done = domains gone since the first run + looked at in this run.
        if ( ! $c['domains'] ) $c['domains'] = count( $domains );
        $c['done'] = max( 0, (int) $c['domains'] - count( $domains ) );
        if ( $bad ) { $c['skipped'] += $bad; $c['by']['not a valid address'] = (int) ( $c['by']['not a valid address'] ?? 0 ) + $bad; }

        $finished = true;
        foreach ( $domains as $dom ) {
            if ( microtime( true ) - $start > self::BUDGET ) { $finished = false; break; }
            $why = self::verdict( $dom );
            $c['done'] = min( (int) $c['domains'], $c['done'] + 1 );
            if ( $why === '' ) continue;
            $n = (int) $wpdb->query( $wpdb->prepare( "UPDATE $q SET status = 'skipped', reason = %s WHERE campaign_id = %d AND status = 'queued' AND domain = %s", $why, $id, $dom ) );
            $c['skipped'] += $n;
            $c['by'][ $why ] = (int) ( $c['by'][ $why ] ?? 0 ) + $n;
        }
        if ( $finished ) { $c['state'] = 'done'; $c['done'] = $c['domains']; }
        $c['t'] = time();

        $d = EmailCenter::get( $id ); // fresh copy: the runner may have saved meanwhile
        $d['check'] = $c;
        EmailCenter::save( $id, $d );
        delete_transient( $lock );

        if ( ! $finished ) wp_schedule_single_event( time() + 5, self::HOOK, array( $id ) );
    }

    /** '' when mail can go to this domain (or we can't tell), else the skip reason. */
    public static function verdict( $domain ) {
        $domain = strtolower( trim( (string) $domain, ". \t" ) );
        if ( in_array( $domain, self::$typos, true ) ) return 'misspelled domain';
        if ( class_exists( 'EmailCheck' ) && EmailCheck::isDisposable( '@' . $domain ) ) return 'throwaway email domain';
        if ( ! preg_match( '/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $domain ) ) return 'domain has no mail server';

        $key = 'mm_dom_' . md5( $domain );
        $cached = get_transient( $key );
        if ( $cached !== false ) return $cached === 'dead' ? 'domain has no mail server' : '';

        $state = self::lookup( $domain );
        if ( $state === 'dead' ) $state = self::lookup( $domain ); // a second look before skipping anyone
        // 'unknown' (DNS didn't answer): sent as usual; remembered for an hour so the check moves on.
        set_transient( $key, $state, array( 'ok' => self::OK_TTL, 'dead' => self::DEAD_TTL )[ $state ] ?? HOUR_IN_SECONDS );

        return $state === 'dead' ? 'domain has no mail server' : '';
    }

    /** 'ok' (has a mail server), 'dead' (no domain / no mail server / null MX) or 'unknown' (lookup failed). */
    protected static function lookup( $domain ) {
        $mx = @dns_get_record( $domain, DNS_MX );
        if ( $mx === false ) return 'unknown';
        if ( $mx ) {
            foreach ( $mx as $r ) if ( trim( (string) ( $r['target'] ?? '' ), '.' ) !== '' ) return 'ok';

            return 'dead'; // only a null MX: the domain says it takes no email
        }
        // No MX: mail falls back to the domain's own address (A/AAAA).
        $a = @dns_get_record( $domain, DNS_A );
        if ( $a === false ) return 'unknown';
        if ( $a ) {
            // Some registries answer for names nobody registered (.ph / .com.ph point them all at a parking
            // page): if a made-up name next to this one gets the same address, this domain doesn't exist either.
            $wild = self::wildcard( substr( $domain, strpos( $domain, '.' ) + 1 ) );
            return array_intersect( array_column( $a, 'ip' ), $wild ) ? 'dead' : 'ok';
        }
        $aaaa = @dns_get_record( $domain, DNS_AAAA );
        if ( $aaaa === false ) return 'unknown';

        return $aaaa ? 'ok' : 'dead';
    }

    /** Addresses a made-up name under $suffix resolves to (a registry wildcard), cached a day; usually none. */
    protected static function wildcard( $suffix ) {
        static $memo = array();
        if ( $suffix === '' || strpos( $suffix, '.' ) === false && strlen( $suffix ) < 2 ) return array();
        if ( isset( $memo[ $suffix ] ) ) return $memo[ $suffix ];
        $key = 'mm_dom_wild_' . md5( $suffix );
        $ips = get_transient( $key );
        if ( ! is_array( $ips ) ) {
            $r = @dns_get_record( 'mm-nx-' . wp_generate_password( 12, false, false ) . '.' . $suffix, DNS_A );
            $ips = $r ? array_column( $r, 'ip' ) : array();
            set_transient( $key, $ips, DAY_IN_SECONDS );
        }

        return $memo[ $suffix ] = $ips;
    }

    /** One line for the Campaigns list, or ''. */
    public static function summary( array $d ) {
        $c = (array) ( $d['check'] ?? array() );
        if ( empty( $c['state'] ) ) return '';
        if ( $c['state'] === 'running' ) return 'Checking addresses: ' . number_format( (int) $c['done'] ) . ' of ' . number_format( max( (int) $c['done'], (int) $c['domains'] ) ) . ' domains' . ( $c['skipped'] ? ' · ' . number_format( (int) $c['skipped'] ) . ' dead so far' : '' );
        $parts = array();
        foreach ( (array) $c['by'] as $why => $n ) if ( $n ) $parts[] = number_format( (int) $n ) . ' ' . $why;

        return 'Address check ' . wp_date( 'M j, g:i A', (int) $c['t'] ) . ': ' . ( $parts ? 'skipped ' . implode( ', ', $parts ) : 'all domains can receive email' );
    }
}

EmailListCheck::init();
