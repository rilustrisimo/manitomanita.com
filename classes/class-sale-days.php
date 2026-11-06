<?php
/**
 * SaleDays Class. One short email the evening before the big shopping sales (11.11, 12.12; deals
 * start at midnight) to members of groups whose exchange is on or after the sale day:
 *  - Shuffled groups: "a gift for <match>" with the match's wishlist shop buttons + gift ideas within budget.
 *  - Not shuffled yet: gift ideas within the group budget.
 * Skipped: unconfirmed members, members who opted out of reminders, members whose gift is bought/given,
 * groups the organizer turned off. One email per inbox per sale (people in several groups get one).
 * PRO organizers get a heads-up at 9 am that day with a one-tap "turn it off for my group" link.
 *
 * Members' emails go out from 6 pm (Manila) the day before, in batches of BATCH every 2 minutes. Kill switch: option mm_sale_mail = '0'.
 * Admin preview: /?mm_sale_test=<group id>&sale=1111 (logged in as admin) emails you a sample.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class SaleDays {
    const HOOK  = 'mm_sale_batch';
    const BATCH = 150;
    const OFF   = '_mm_sale_off'; // group meta: organizer turned sale emails off

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( 'template_redirect', array( __CLASS__, 'routes' ), 5 );
    }

    /** key => label and month-day. */
    public static function sales() {
        return array(
            '1111' => array( 'label' => '11.11', 'md' => '11-11' ),
            '1212' => array( 'label' => '12.12', 'md' => '12-12' ),
        );
    }

    public static function schedules( $s ) {
        $s['mm_two_min'] = array( 'interval' => 120, 'display' => 'Every 2 minutes (Manito Manita)' );

        return $s;
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 60, 'mm_two_min', self::HOOK );
    }

    protected static function saleOn( $time ) {
        foreach ( self::sales() as $k => $s ) if ( wp_date( 'm-d', $time ) === $s['md'] ) return $k;

        return '';
    }

    /** Meta keys are per sale and year, so next year sends again. */
    protected static function tag( $key ) {
        return $key . '_' . wp_date( 'Y' );
    }

    protected static function sig( $what ) {
        return substr( hash_hmac( 'sha256', $what, wp_salt( 'auth' ) ), 0, 24 );
    }

    public static function run() {
        if ( get_option( 'mm_sale_mail', '1' ) === '0' ) return;
        $hour = (int) wp_date( 'G' );

        $eve = self::saleOn( time() + DAY_IN_SECONDS ); // the sale is tomorrow
        if ( $eve === '' ) return;
        if ( $hour >= 9 ) self::headsUp( $eve );              // PRO organizers, with time to turn it off
        if ( $hour >= 18 && $hour < 23 ) self::batch( $eve ); // members: add to cart tonight
    }

    /** Groups made this season whose exchange is today or later (exchange date m/d/Y). */
    protected static function groups( $key, $proOnly = false ) {
        $since = class_exists( 'Season' ) ? Season::seasonStart() : wp_date( 'Y' ) . '0901';
        $ids = get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            'date_query' => array( array( 'after' => substr( $since, 0, 4 ) . '-' . substr( $since, 4, 2 ) . '-' . substr( $since, 6, 2 ), 'inclusive' => true ) ),
            'meta_query' => array( array( 'key' => self::OFF, 'compare' => 'NOT EXISTS' ), array( 'key' => '_mm_sale_done_' . self::tag( $key ), 'compare' => 'NOT EXISTS' ) ) ) );
        $saleDay = wp_date( 'Ymd', time() + DAY_IN_SECONDS ); // only exchanges on or after the sale day
        $out = array();
        foreach ( $ids as $gid ) {
            $d = DateTime::createFromFormat( '!m/d/Y', (string) get_field( 'gift_exchange_date', $gid ), wp_timezone() );
            if ( ! $d || $d->format( 'Ymd' ) < $saleDay ) continue;
            if ( $proOnly && ! get_field( 'pro', $gid ) ) continue;
            $out[] = (int) $gid;
        }

        return $out;
    }

    /** The evening before: tell PRO organizers and give them an off switch. */
    protected static function headsUp( $key ) {
        $s = self::sales()[ $key ];
        $n = 0;
        foreach ( self::groups( $key, true ) as $gid ) {
            if ( $n >= 50 ) return;
            if ( ! add_post_meta( $gid, '_mm_sale_eve_' . self::tag( $key ), time(), true ) ) continue;
            $to = (string) get_field( 'your_email', $gid );
            if ( ! is_email( $to ) ) continue;
            list( $subject, $o ) = self::headsUpEmail( $key, $gid );
            Emails::send( $to, $subject, $o );
            $n++;
        }
    }

    /** The PRO organizer heads-up (9 am): array( subject, Emails options ). Used to send and to preview. */
    public static function headsUpEmail( $key, $gid ) {
        $s = self::sales()[ $key ];
        $name = (string) get_field( 'group_name', $gid );
        $budget = class_exists( 'Affiliate' ) ? Affiliate::budget( $gid ) : 0;
        $off = add_query_arg( array( 'mm_sale_off' => $gid, 's' => self::sig( 'off' . $gid ) ), home_url( '/' ) );
        return array( 'Tonight: a short ' . $s['label'] . ' gift email to your members', array( 'kind' => 'sale',
            'preheader' => 'One email with gift ideas within your budget. You can turn it off for ' . $name . '.',
            'eyebrow'   => $s['label'] . ' sale',
            'title'     => 'Heads-up for ' . esc_html( $name ),
            'body'      => array(
                '<p>Lazada and Shopee&rsquo;s <strong>' . $s['label'] . ' sale starts at midnight</strong>. At 6 pm today we&rsquo;ll send your members one short email with gift ideas' . ( $budget ? ' within your &#8369;' . number_format( $budget ) . ' budget' : '' ) . ', and after the shuffle, buttons to shop their match&rsquo;s wishlist.</p>',
                '<p>Members who already marked their gift as bought won&rsquo;t get it, and anyone can unsubscribe. Prefer your group not get it?</p>',
            ),
            'button'    => array( 'Turn it off for my group', $off ),
            'footer'    => 'You&rsquo;re getting this because you organize ' . esc_html( $name ) . ' (PRO) on Manito Manita.',
        ) );
    }

    /** Up to BATCH member emails for today's sale. */
    protected static function batch( $key ) {
        @set_time_limit( 110 );
        $tag = self::tag( $key );
        $inboxes = get_transient( 'mm_sale_inbox_' . $tag );
        $inboxes = is_array( $inboxes ) ? $inboxes : array();
        $sent = 0;

        foreach ( self::groups( $key ) as $gid ) {
            $members = Groups::memberIds( $gid );
            update_meta_cache( 'post', $members ); // one query for the whole group
            foreach ( $members as $uid ) {
                if ( $sent >= self::BATCH ) break 2;
                if ( get_post_meta( $uid, '_mm_sale_' . $tag, true ) || ! add_post_meta( $uid, '_mm_sale_' . $tag, time(), true ) ) continue; // handled in an earlier run
                if ( ! EmailCheck::isConfirmed( $uid ) || Emails::optedOut( $uid ) ) continue;
                if ( in_array( get_post_meta( $uid, '_mm_gift_status', true ), array( 'bought', 'given' ), true ) ) continue;
                $email = (string) get_field( 'email', $uid );
                $inbox = class_exists( 'Season' ) ? Season::inboxKey( $email ) : strtolower( $email );
                if ( isset( $inboxes[ $inbox ] ) ) continue;
                $inboxes[ $inbox ] = 1;
                if ( self::send( $key, $gid, $uid, $email ) ) $sent++;
            }
            if ( $sent < self::BATCH ) add_post_meta( $gid, '_mm_sale_done_' . $tag, time(), true ); // every member handled
        }

        set_transient( 'mm_sale_inbox_' . $tag, $inboxes, 3 * DAY_IN_SECONDS );
        if ( $sent && class_exists( 'Insights' ) ) Insights::log( 'sale_email', 0, 0, $sent, $key );
    }

    public static function send( $key, $gid, $uid, $to ) {
        list( $subject, $o ) = self::memberEmail( $key, $gid, $uid );

        return Emails::send( $to, $subject, $o );
    }

    /** One member's sale-eve email: array( subject, Emails options ). Used to send and to preview. */
    public static function memberEmail( $key, $gid, $uid ) {
        $s = self::sales()[ $key ];
        $name = (string) get_field( 'group_name', $gid );
        $budget = class_exists( 'Affiliate' ) ? Affiliate::budget( $gid ) : 0;
        $under = $budget ? ' under &#8369;' . number_format( $budget ) : '';
        $pair = get_field( 'matched', $gid ) ? (int) get_field( 'pair', $uid, false ) : 0;
        $pairName = $pair ? trim( (string) get_field( 'screen_name', $pair ) ) : '';
        $ideas = Affiliate::giftIdeasHtml( $gid, 'email-sale', true );

        if ( $pairName !== '' ) {
            $subject = $s['label'] . ' starts at midnight: a gift for ' . $pairName . $under;
            $o = array(
                'preheader' => 'Add ' . $pairName . '&rsquo;s gift to your cart tonight, then check out when the sale starts.',
                'title'     => 'Get ' . esc_html( $pairName ) . '&rsquo;s gift on ' . $s['label'],
                'highlight' => array( 'Your gift is for', $pairName ),
                'body'      => array( '<p>Lazada and Shopee&rsquo;s <strong>' . $s['label'] . ' sale starts at midnight</strong>. Add your gift for the ' . esc_html( $name ) . ' exchange to your cart tonight, then check out when the deals open. Here&rsquo;s what ' . esc_html( $pairName ) . ' wants, plus ideas' . $under . '.</p>' ),
                'button'    => array( 'See their wishlist', Emails::groupLink( $gid ) ),
                'raw'       => Affiliate::shopEmailHtml( $gid, 'email-sale-wish', Groups::wishKeywords( $pair ), 'Shop for ' . $pairName ) . $ideas,
            );
        } else {
            $subject = $s['label'] . ' starts at midnight: exchange gift ideas' . $under;
            $o = array(
                'preheader' => 'Gift ideas' . $under . ' for the ' . $name . ' exchange, before the sale starts.',
                'title'     => $s['label'] . ' gift ideas' . $under,
                'body'      => array( '<p>Lazada and Shopee&rsquo;s <strong>' . $s['label'] . ' sale starts at midnight</strong>. Names in ' . esc_html( $name ) . ' haven&rsquo;t been drawn yet, so here are ideas' . $under . ' that work for almost anyone. Add a few to your cart tonight.</p>' ),
                'button'    => array( 'Open the group', Emails::groupLink( $gid ) ),
                'raw'       => $ideas,
            );
        }

        return array( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ), $o + array( 'kind' => 'sale',
            'eyebrow'     => $s['label'] . ' sale tonight',
            'card'        => Emails::groupCard( $gid ),
            'unsubscribe' => $uid,
            'footer'      => 'You&rsquo;re getting this because you&rsquo;re in ' . esc_html( $name ) . ' on Manito Manita. We send this once, the evening before each big sale.',
        ) );
    }

    /* ------------------------------------------------------------------ Admin preview */

    /** Real groups to preview with: one shuffled (a member with a match), one not shuffled yet, one PRO. */
    protected static function samples() {
        $ids = get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
        $today = wp_date( 'Ymd' );
        $out = array( 'shuffled' => null, 'open' => null, 'pro' => 0 );
        foreach ( $ids as $gid ) {
            $d = DateTime::createFromFormat( '!m/d/Y', (string) get_field( 'gift_exchange_date', $gid ), wp_timezone() );
            if ( ! $d || $d->format( 'Ymd' ) < $today ) continue;
            if ( ! $out['pro'] && get_field( 'pro', $gid ) ) $out['pro'] = (int) $gid;
            $members = Groups::memberIds( $gid );
            if ( count( $members ) < 3 ) continue;
            if ( get_field( 'matched', $gid ) ) {
                if ( ! $out['shuffled'] ) foreach ( $members as $uid ) { if ( (int) get_field( 'pair', $uid, false ) ) { $out['shuffled'] = array( (int) $gid, (int) $uid ); break; } }
            } elseif ( ! $out['open'] ) {
                $out['open'] = array( (int) $gid, (int) $members[0] );
            }
            if ( $out['shuffled'] && $out['open'] && $out['pro'] ) break;
        }

        return $out;
    }

    /** Manito Emails > Automatic emails > Preview: the three sale-eve emails rendered with real groups. */
    public static function previewScreen() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $key = isset( self::sales()[ (string) ( $_GET['sale'] ?? '' ) ] ) ? (string) $_GET['sale'] : '1111';
        $label = self::sales()[ $key ]['label'];
        $x = self::samples();
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=mm-emails&tab=automatic' ) ) . '">&larr; Automatic emails</a></p>';
        echo '<h2>' . esc_html( $label ) . ' sale-eve emails &middot; preview</h2>';
        echo '<p>';
        foreach ( self::sales() as $k => $s ) echo '<a class="button' . ( $k === $key ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=mm-emails&tab=sale&sale=' . $k ) ) . '">' . esc_html( $s['label'] ) . '</a> ';
        echo '</p><p class="mmi-muted">Shown with real upcoming groups, exactly as the emails are built. Members get one at 6 pm the day before the sale; PRO organizers get the heads-up at 9 am that day. Nothing is sent from this page.</p>';

        $cards = array();
        if ( $x['shuffled'] ) $cards[] = array( 'Members of shuffled groups', 'With their match&rsquo;s name and wishlist shop buttons, plus ideas within budget.', self::memberEmail( $key, $x['shuffled'][0], $x['shuffled'][1] ) );
        if ( $x['open'] ) $cards[] = array( 'Members of groups not shuffled yet', 'Gift ideas within the group budget.', self::memberEmail( $key, $x['open'][0], $x['open'][1] ) );
        if ( $x['pro'] ) $cards[] = array( 'PRO organizers (9 am heads-up)', 'With a one-tap link to turn it off for their group.', self::headsUpEmail( $key, $x['pro'] ) );
        if ( ! $cards ) { echo '<p>No upcoming groups to preview with yet.</p>'; return; }

        foreach ( $cards as $c ) {
            list( $subject, $o ) = $c[2];
            $html = Emails::render( $o );
            echo '<div class="mmi-panel"><h3>' . $c[0] . '</h3><p class="mmi-muted">' . $c[1] . '</p>';
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 14px;max-width:960px;margin:0 0 12px;"><div style="font-weight:600;">' . esc_html( $subject ) . '</div><div style="color:#646970;font-size:13px;">' . esc_html( html_entity_decode( wp_strip_all_tags( (string) ( $o['preheader'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) ) . '</div></div>';
            echo '<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">';
            echo '<iframe title="Desktop preview" sandbox="" srcdoc="' . esc_attr( $html ) . '" style="flex:1 1 600px;max-width:680px;height:900px;border:1px solid #dcdcde;border-radius:8px;background:#f2f5f5;"></iframe>';
            echo '<iframe title="Phone preview" sandbox="" srcdoc="' . esc_attr( $html ) . '" style="flex:0 0 375px;width:375px;height:900px;border:1px solid #dcdcde;border-radius:18px;background:#f2f5f5;"></iframe>';
            echo '</div></div>';
        }
    }

    public static function routes() {
        if ( isset( $_GET['mm_sale_off'] ) ) {
            $gid = absint( $_GET['mm_sale_off'] );
            if ( $gid && get_post_type( $gid ) === 'groups' && hash_equals( self::sig( 'off' . $gid ), (string) ( $_GET['s'] ?? '' ) ) ) {
                update_post_meta( $gid, self::OFF, time() );
                wp_die( 'Sale-day emails are off for ' . esc_html( (string) get_field( 'group_name', $gid ) ) . '. Your members won&rsquo;t get them.', 'Turned off', array( 'response' => 200, 'link_url' => home_url( '/' ), 'link_text' => 'Manito Manita' ) );
            }
            wp_die( 'This link isn&rsquo;t valid.', 'Link not valid', array( 'response' => 400 ) );
        }

        if ( isset( $_GET['mm_sale_test'] ) && current_user_can( 'manage_options' ) ) {
            $gid = absint( $_GET['mm_sale_test'] );
            $key = isset( self::sales()[ (string) ( $_GET['sale'] ?? '' ) ] ) ? (string) $_GET['sale'] : '1111';
            $uid = get_post_type( $gid ) === 'groups' ? ( Groups::memberIds( $gid )[0] ?? 0 ) : 0;
            if ( ! $uid ) wp_die( 'That group has no confirmed members to preview as.' );
            $ok = self::send( $key, $gid, $uid, wp_get_current_user()->user_email );
            wp_die( $ok ? 'Preview sent to ' . esc_html( wp_get_current_user()->user_email ) . ' (as a member of that group).' : 'Could not send the preview.' );
        }
    }
}

SaleDays::init();
