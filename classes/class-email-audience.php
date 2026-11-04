<?php
/**
 * EmailAudience Class. Who a promo campaign goes to (Manito Emails). Sources: group members and/or organizers.
 *
 * Filters (array, all optional):
 *   source        array('members','organizers')
 *   season        this | last | all            (by group creation: season starts Sep 1)
 *   created_from / created_to  Y-m-d           (group created)
 *   exchange      any | upcoming | range | past, with days (upcoming) or exchange_from / exchange_to
 *   shuffled      any | yes | no
 *   pro           any | yes | no
 *   budget_min / budget_max   pesos
 *   min_size      confirmed members in the group
 *   gift          any | not_bought              (members)
 *   joined_from / joined_to   Y-m-d             (members)
 *   org_active    any | yes | no                (organizers: made a group this season)
 *   only_email    one address (tests)
 *   cap_days      frequency cap (default 3)
 *   old_company   1 = include company/school addresses from before this season (off: they bounce the most)
 *   engaged       months: only inboxes that opened or clicked a promo in that time (0 = everyone)
 *
 * Always left out (reason counted): unconfirmed members, reminder opt-outs, promo opt-outs, bounces,
 * complaints, undeliverable addresses (Season::badEmail), recent promo (cap), already in this campaign,
 * duplicates (one email per inbox; a member of a shuffled group wins over a plain member over an organizer).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailAudience {
    const CAP_DAYS = 3;

    /** Defaults merged into saved filters. */
    public static function defaults() {
        return array( 'source' => array( 'members' ), 'season' => 'this', 'created_from' => '', 'created_to' => '', 'exchange' => 'upcoming', 'days' => 60, 'exchange_from' => '', 'exchange_to' => '',
            'shuffled' => 'any', 'pro' => 'any', 'budget_min' => '', 'budget_max' => '', 'min_size' => '', 'gift' => 'not_bought', 'joined_from' => '', 'joined_to' => '', 'org_active' => 'any', 'only_email' => '', 'cap_days' => self::CAP_DAYS, 'old_company' => 0, 'engaged' => 0 );
    }

    public static function clean( $f ) {
        $d = self::defaults();
        $f = is_array( $f ) ? $f : array();
        $out = array();
        foreach ( $d as $k => $v ) {
            $x = $f[ $k ] ?? $v;
            if ( $k === 'source' ) { $x = array_values( array_intersect( (array) $x, array( 'members', 'organizers' ) ) ); if ( ! $x ) $x = array( 'members' ); }
            elseif ( in_array( $k, array( 'created_from', 'created_to', 'exchange_from', 'exchange_to', 'joined_from', 'joined_to' ), true ) ) $x = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $x ) ? (string) $x : '';
            elseif ( in_array( $k, array( 'days', 'budget_min', 'budget_max', 'min_size', 'cap_days' ), true ) ) $x = $x === '' ? '' : max( 0, (int) $x );
            elseif ( $k === 'old_company' ) $x = empty( $x ) ? 0 : 1;
            elseif ( $k === 'engaged' ) $x = max( 0, min( 24, (int) $x ) );
            elseif ( $k === 'only_email' ) $x = strtolower( sanitize_email( (string) $x ) );
            else $x = sanitize_key( (string) $x );
            $out[ $k ] = $x;
        }
        if ( $out['cap_days'] === '' ) $out['cap_days'] = self::CAP_DAYS;

        return $out;
    }

    /** One line describing the audience (for the list, the AI prompt and confirmations). */
    public static function summary( array $f ) {
        $f = self::clean( $f );
        $who = implode( ' and ', array_map( function ( $s ) { return $s === 'members' ? 'group members' : 'organizers'; }, $f['source'] ) );
        $bits = array( array( 'this' => 'groups from this season', 'last' => 'groups from last season', 'all' => 'groups from any season' )[ $f['season'] ] ?? 'groups' );
        if ( $f['exchange'] === 'upcoming' ) $bits[] = 'exchange in the next ' . (int) $f['days'] . ' days';
        elseif ( $f['exchange'] === 'past' ) $bits[] = 'exchange already passed';
        elseif ( $f['exchange'] === 'range' ) $bits[] = 'exchange ' . $f['exchange_from'] . ' to ' . $f['exchange_to'];
        if ( $f['shuffled'] !== 'any' ) $bits[] = $f['shuffled'] === 'yes' ? 'already shuffled' : 'not shuffled yet';
        if ( $f['pro'] !== 'any' ) $bits[] = $f['pro'] === 'yes' ? 'PRO' : 'free';
        if ( $f['budget_min'] !== '' || $f['budget_max'] !== '' ) $bits[] = 'budget ₱' . ( $f['budget_min'] !== '' ? $f['budget_min'] : '0' ) . '–' . ( $f['budget_max'] !== '' ? $f['budget_max'] : 'any' );
        if ( in_array( 'members', $f['source'], true ) && $f['gift'] === 'not_bought' ) $bits[] = 'gift not bought yet';
        if ( in_array( 'organizers', $f['source'], true ) && $f['org_active'] !== 'any' ) $bits[] = $f['org_active'] === 'yes' ? 'organizer made a group this season' : 'organizer hasn&rsquo;t made a group this season';
        if ( $f['engaged'] ) $bits[] = 'opened or clicked a promo in the last ' . (int) $f['engaged'] . ' months';
        if ( ! $f['old_company'] && $f['season'] !== 'this' ) $bits[] = 'company emails only if active this season';
        if ( $f['only_email'] !== '' ) $bits[] = 'only ' . $f['only_email'];

        return ucfirst( $who ) . ' of ' . implode( ', ', $bits );
    }

    /**
     * Recipients: array( inboxHash => array( email, ref_type, ref_id, gid, shuffled ) ), plus skip reasons.
     * $campaign: exclude inboxes already queued for it.
     */
    public static function build( array $f, $campaign = 0 ) {
        global $wpdb;
        $f = self::clean( $f );
        $skipped = array();
        $skip = function ( $why ) use ( &$skipped ) { $skipped[ $why ] = ( $skipped[ $why ] ?? 0 ) + 1; };

        $start = Season::seasonStart(); // Ymd
        $seasonFrom = array( 'this' => $start, 'last' => ( (int) substr( $start, 0, 4 ) - 1 ) . '0901', 'all' => '00000000' )[ $f['season'] ] ?? $start;
        $seasonTo = $f['season'] === 'last' ? $start : '99999999';
        $today = wp_date( 'Ymd' );

        // Groups, one pivot query.
        $rows = $wpdb->get_results( "SELECT p.ID, p.post_date,
                MAX(CASE WHEN m.meta_key = 'gift_exchange_date' THEN m.meta_value END) AS d,
                MAX(CASE WHEN m.meta_key = 'your_email' THEN m.meta_value END) AS email,
                MAX(CASE WHEN m.meta_key = 'matched' THEN m.meta_value END) AS matched,
                MAX(CASE WHEN m.meta_key = 'pro' THEN m.meta_value END) AS pro,
                MAX(CASE WHEN m.meta_key = 'spending_minimum' THEN m.meta_value END) AS budget,
                MAX(CASE WHEN m.meta_key = '" . esc_sql( Emails::OPTOUT_META ) . "' THEN 1 END) AS optout
            FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('gift_exchange_date','your_email','matched','pro','spending_minimum','" . esc_sql( Emails::OPTOUT_META ) . "')
            WHERE p.post_type = 'groups' AND p.post_status = 'publish' GROUP BY p.ID" );

        $orgActive = array(); // inbox key => made a group this season
        foreach ( $rows as $r ) if ( str_replace( '-', '', substr( $r->post_date, 0, 10 ) ) >= $start ) $orgActive[ Season::inboxKey( $r->email ) ] = true;

        $sizes = array();
        foreach ( (array) $wpdb->get_results( "SELECT m.meta_value g, COUNT(*) n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'groups' WHERE p.post_type = 'users' AND p.post_status = 'publish' GROUP BY m.meta_value" ) as $s ) $sizes[ (int) $s->g ] = (int) $s->n;

        $groups = array();
        foreach ( $rows as $r ) {
            $created = str_replace( '-', '', substr( $r->post_date, 0, 10 ) );
            if ( $created < $seasonFrom || $created >= $seasonTo ) continue;
            if ( $f['created_from'] !== '' && $created < str_replace( '-', '', $f['created_from'] ) ) continue;
            if ( $f['created_to'] !== '' && $created > str_replace( '-', '', $f['created_to'] ) ) continue;
            $d = preg_match( '/^\d{8}$/', (string) $r->d ) ? (string) $r->d : '';
            if ( $f['exchange'] === 'upcoming' && ( $d === '' || $d < $today || $d > wp_date( 'Ymd', time() + (int) $f['days'] * DAY_IN_SECONDS ) ) ) continue;
            if ( $f['exchange'] === 'past' && ( $d === '' || $d >= $today ) ) continue;
            if ( $f['exchange'] === 'range' && ( $d === '' || ( $f['exchange_from'] !== '' && $d < str_replace( '-', '', $f['exchange_from'] ) ) || ( $f['exchange_to'] !== '' && $d > str_replace( '-', '', $f['exchange_to'] ) ) ) ) continue;
            $matched = ! empty( $r->matched );
            if ( $f['shuffled'] === 'yes' && ! $matched ) continue;
            if ( $f['shuffled'] === 'no' && $matched ) continue;
            if ( $f['pro'] === 'yes' && empty( $r->pro ) ) continue;
            if ( $f['pro'] === 'no' && ! empty( $r->pro ) ) continue;
            $budget = (int) preg_replace( '/\D/', '', (string) $r->budget );
            if ( $f['budget_min'] !== '' && $budget < $f['budget_min'] ) continue;
            if ( $f['budget_max'] !== '' && $budget > $f['budget_max'] ) continue;
            if ( $f['min_size'] !== '' && ( $sizes[ (int) $r->ID ] ?? 0 ) < $f['min_size'] ) continue;
            $groups[ (int) $r->ID ] = array( 'email' => (string) $r->email, 'matched' => $matched, 'optout' => ! empty( $r->optout ) );
        }

        $cands = array(); // inbox key => candidate (best wins)
        $rank = function ( $c ) { return $c['ref_type'] === 'member' ? ( $c['shuffled'] ? 3 : 2 ) : 1; };
        $add = function ( $c ) use ( &$cands, $rank, $skip ) {
            $key = Season::inboxKey( $c['email'] );
            if ( isset( $cands[ $key ] ) ) {
                $skip( 'duplicate (same inbox)' );
                if ( $rank( $cands[ $key ] ) >= $rank( $c ) ) return;
            }
            $cands[ $key ] = $c;
        };

        if ( in_array( 'organizers', $f['source'], true ) ) {
            foreach ( $groups as $gid => $g ) {
                $key = Season::inboxKey( $g['email'] );
                if ( $f['org_active'] === 'yes' && empty( $orgActive[ $key ] ) ) continue;
                if ( $f['org_active'] === 'no' && ! empty( $orgActive[ $key ] ) ) continue;
                if ( $g['optout'] ) { $skip( 'opted out of reminders' ); continue; }
                $add( array( 'email' => strtolower( trim( $g['email'] ) ), 'ref_type' => 'organizer', 'ref_id' => $gid, 'gid' => $gid, 'shuffled' => $g['matched'], 'recent' => ! empty( $orgActive[ $key ] ) ) );
            }
        }

        if ( in_array( 'members', $f['source'], true ) && $groups ) {
            $ids = implode( ',', array_map( 'intval', array_keys( $groups ) ) );
            $mrows = $wpdb->get_results( "SELECT p.ID, p.post_date,
                    MAX(CASE WHEN m.meta_key = 'groups' THEN m.meta_value END) AS g,
                    MAX(CASE WHEN m.meta_key = 'email' THEN m.meta_value END) AS email,
                    MAX(CASE WHEN m.meta_key = '_mm_gift_status' THEN m.meta_value END) AS gift,
                    MAX(CASE WHEN m.meta_key = '" . esc_sql( EmailCheck::VERIFIED_META ) . "' THEN m.meta_value END) AS verified,
                    MAX(CASE WHEN m.meta_key = '" . esc_sql( Emails::OPTOUT_META ) . "' THEN 1 END) AS optout
                FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('groups','email','_mm_gift_status','" . esc_sql( EmailCheck::VERIFIED_META ) . "','" . esc_sql( Emails::OPTOUT_META ) . "')
                WHERE p.post_type = 'users' AND p.post_status = 'publish'
                  AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'groups' AND meta_value IN ($ids))
                GROUP BY p.ID" );
            foreach ( $mrows as $r ) {
                $gid = (int) $r->g;
                if ( ! isset( $groups[ $gid ] ) ) continue;
                $email = strtolower( trim( (string) $r->email ) );
                $confirmed = ( (string) $r->verified !== '' && strtolower( (string) $r->verified ) === $email ) || $r->post_date < EmailCheck::VERIFY_SINCE;
                if ( ! $confirmed ) { $skip( 'email not confirmed' ); continue; }
                if ( $r->optout ) { $skip( 'opted out of reminders' ); continue; }
                if ( $f['gift'] === 'not_bought' && in_array( (string) $r->gift, array( 'bought', 'given' ), true ) ) { $skip( 'gift already bought' ); continue; }
                $joined = str_replace( '-', '', substr( $r->post_date, 0, 10 ) );
                if ( $f['joined_from'] !== '' && $joined < str_replace( '-', '', $f['joined_from'] ) ) continue;
                if ( $f['joined_to'] !== '' && $joined > str_replace( '-', '', $f['joined_to'] ) ) continue;
                $add( array( 'email' => $email, 'ref_type' => 'member', 'ref_id' => (int) $r->ID, 'gid' => $gid, 'shuffled' => $groups[ $gid ]['matched'], 'recent' => $joined >= $start ) );
            }
        }

        // Recent promos (frequency cap) and this campaign's queue, by inbox hash.
        $recent = array();
        $cap = (int) $f['cap_days'];
        if ( $cap > 0 ) {
            foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT inbox FROM ' . EmailCenter::queueTable() . " WHERE status = 'sent' AND sent_at > %s", wp_date( 'Y-m-d H:i:s', time() - $cap * DAY_IN_SECONDS ) ) ) as $h ) $recent[ $h ] = true;
        }
        $engaged = null;
        if ( $f['engaged'] ) {
            $cut = wp_date( 'Y-m-d H:i:s', time() - (int) $f['engaged'] * 30 * DAY_IN_SECONDS );
            $engaged = array_flip( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT inbox FROM ' . EmailCenter::queueTable() . ' WHERE opened_at > %s OR clicked_at > %s', $cut, $cut ) ) );
        }
        $queued = array();
        if ( $campaign ) foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT inbox FROM ' . EmailCenter::queueTable() . ' WHERE campaign_id = %d', (int) $campaign ) ) as $h ) $queued[ $h ] = true;

        $out = array();
        foreach ( $cands as $key => $c ) {
            if ( $f['only_email'] !== '' && Season::inboxKey( $f['only_email'] ) !== $key ) continue;
            $h = EmailDeliver::hash( $c['email'] );
            if ( isset( $queued[ $h ] ) ) { $skip( 'already in this campaign' ); continue; }
            if ( $engaged !== null && ! isset( $engaged[ $h ] ) ) { $skip( 'no promo opened or clicked lately' ); continue; }
            if ( ! $f['old_company'] && empty( $c['recent'] ) && EmailCenter::tierOf( $c['email'] ) === 2 ) { $skip( 'older company or school email' ); continue; }
            $bad = Season::badEmail( $c['email'] );
            if ( $bad !== '' ) { $skip( 'undeliverable: ' . $bad ); continue; }
            $sup = EmailDeliver::reason( $c['email'] );
            if ( $sup !== '' && $sup !== 'soft' ) { $skip( array( 'promo_unsub' => 'unsubscribed from promos', 'bounce' => 'bounced before', 'complaint' => 'marked as spam before', 'manual' => 'blocked' )[ $sup ] ?? 'blocked' ); continue; }
            if ( isset( $recent[ $h ] ) ) { $skip( 'got a promo in the last ' . $cap . ' days' ); continue; }
            $out[ $h ] = $c;
        }
        arsort( $skipped );

        return array( 'recipients' => $out, 'skipped' => $skipped );
    }

    /** Count + skip reasons + 8 masked samples (cached 60s per filter set). */
    public static function preview( array $f, $campaign = 0 ) {
        $key = 'mm_aud_' . md5( wp_json_encode( self::clean( $f ) ) . '|' . (int) $campaign );
        $c = get_transient( $key );
        if ( is_array( $c ) ) return $c;
        $b = self::build( $f, $campaign );
        $samples = array();
        foreach ( array_slice( $b['recipients'], 0, 8 ) as $r ) $samples[] = array( 'email' => EmailDeliver::mask( $r['email'] ), 'type' => $r['ref_type'], 'group' => html_entity_decode( (string) get_field( 'group_name', $r['gid'] ), ENT_QUOTES, 'UTF-8' ), 'ref' => $r['ref_type'] . ':' . $r['ref_id'] );
        $f = self::clean( $f );
        $warn = $f['season'] === 'all' ? 'Older lists bounce more. Consider "This season" or "Last season", or add the "opened or clicked lately" filter.' : ( $f['old_company'] ? 'Older company and school addresses are included; expect more bounces. The test batch and auto-stop will catch a bad list.' : '' );
        $c = array( 'count' => count( $b['recipients'] ), 'skipped' => $b['skipped'], 'samples' => $samples, 'summary' => self::summary( $f ), 'warning' => $warn );
        set_transient( $key, $c, 60 );

        return $c;
    }
}
