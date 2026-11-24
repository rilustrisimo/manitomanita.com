<?php
/**
 * ShuffleEarly Class. Daily at 10 am (Manila): organizers of groups that aren't shuffled yet, with the exchange
 * 10-16 days away and 3+ members, get one "Everyone in? Shuffle now" email. Shuffling earlier gives members
 * more time to shop (and catch 12.12 when it falls before the exchange); the match screen and match emails
 * are where most gift shopping starts. The later 2-6 day reminder (Groups::sendShuffleReminders) still follows.
 * Once per group (meta _mm_shuffle_early); skips groups with an auto-shuffle set. Kill switch: option
 * mm_shuffle_early = '0'.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class ShuffleEarly {
    const HOOK = 'mm_shuffle_early';
    const META = '_mm_shuffle_early';
    const MAX  = 400; // emails per day

    public static function init() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
    }

    public static function schedule() {
        if ( wp_next_scheduled( self::HOOK ) ) return;
        $at = new DateTime( 'today 10:00', wp_timezone() );
        if ( $at->getTimestamp() <= time() ) $at->modify( '+1 day' );
        wp_schedule_event( $at->getTimestamp(), 'daily', self::HOOK );
    }

    public static function run() {
        if ( get_option( 'mm_shuffle_early', '1' ) === '0' ) return;
        $ids = get_posts( array( 'post_type' => 'groups', 'post_status' => 'publish', 'posts_per_page' => 1500, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => array(
                array( 'key' => 'gift_exchange_date', 'value' => array( wp_date( 'Ymd', strtotime( '+10 days' ) ), wp_date( 'Ymd', strtotime( '+16 days' ) ) ), 'compare' => 'BETWEEN' ),
                array( 'key' => self::META, 'compare' => 'NOT EXISTS' ),
            ) ) );
        $sent = 0;
        foreach ( $ids as $gid ) {
            if ( $sent >= self::MAX ) break;
            if ( get_field( 'matched', $gid ) || get_post_meta( $gid, Groups::AUTODRAW_META, true ) ) continue;
            $to = (string) get_field( 'your_email', $gid );
            $count = count( Groups::memberIds( $gid ) );
            if ( ! is_email( $to ) || $count < 3 ) continue;
            if ( ! add_post_meta( $gid, self::META, time(), true ) ) continue; // marked first: never twice
            list( $subject, $o ) = self::email( $gid, $count );
            if ( Emails::send( $to, $subject, $o ) ) $sent++;
            if ( class_exists( 'Insights' ) ) Insights::log( 'shuffle_reminder', $gid, 0, 0, 'early' );
        }
    }

    /** array( subject, Emails options ). */
    public static function email( $gid, $count ) {
        $name = (string) get_field( 'group_name', $gid );
        $link = Emails::groupLink( $gid );
        $date = DateTime::createFromFormat( '!m/d/Y', (string) get_field( 'gift_exchange_date', $gid ), wp_timezone() );
        $days = $date ? max( 1, (int) ( new DateTime( 'today', wp_timezone() ) )->diff( $date )->format( '%a' ) ) : 0;
        // A big sale between now and the exchange is a good reason to shuffle now.
        $sale = '';
        if ( $date && class_exists( 'SaleDays' ) ) {
            foreach ( SaleDays::sales() as $s ) {
                $d = new DateTime( wp_date( 'Y' ) . '-' . $s['md'], wp_timezone() );
                if ( $d->getTimestamp() > time() && $d <= $date ) { $sale = $s['label']; break; }
            }
        }

        return array( 'Everyone in? Shuffle ' . html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) . ' now', array(
            'preheader' => $count . ' members have joined. Shuffling now gives everyone ' . $days . ' days to shop' . ( $sale ? ', including the ' . $sale . ' sale' : '' ) . '.',
            'eyebrow'   => 'Ready when you are',
            'title'     => 'Give everyone time to shop',
            'body'      => array(
                '<p><strong>' . $count . ' members</strong> have joined ' . esc_html( $name ) . ' and the exchange is ' . ( $date ? 'on <strong>' . $date->format( 'l, F j' ) . '</strong> (' . $days . ' days away)' : 'coming up' ) . '. If everyone who&rsquo;s coming is in, shuffle now: each member gets their match by email and has ' . ( $days ? 'about ' . $days . ' days' : 'more time' ) . ' to find a gift' . ( $sale ? ', with the <strong>' . $sale . ' sale</strong> in between' : '' ) . '.</p>',
                '<p>Open the group, tap <strong>Shuffle</strong> and enter your organizer password.</p>',
                '<p>Still waiting for a few people? Share the group link in your chat first. After the shuffle, new people can&rsquo;t join unless you unshuffle (PRO).</p>',
            ),
            'card'      => Emails::groupCard( $gid ),
            'button'    => array( 'Open the group and shuffle', $link ),
            // Last moment exclusions can still be set up: price it per person (office committees compare it with collecting).
            'note'      => get_field( 'pro', $gid ) ? '' : '<strong>Couples or family in the group?</strong> Set exclusions before you shuffle so they don&rsquo;t get each other. PRO is &#8369;199 one-time for the whole group' . ( $count >= 4 ? ', about <strong>&#8369;' . (int) ceil( 199 / $count ) . ' per person</strong> for your ' . $count . ' members' : '' ) . ', and anyone in the group can pay. <a href="' . esc_url( $link . '&pop=pro-exclude' ) . '" style="color:#1f8580;">See PRO</a>',
            'footer'    => 'You&rsquo;re getting this because you organize ' . esc_html( $name ) . ' on Manito Manita. We send this once; a last reminder follows a few days before the exchange if it&rsquo;s still not shuffled.',
        ) );
    }
}

ShuffleEarly::init();
