<?php
/**
 * ThankYou: a Member+ thank-you card to your Secret Santa (the member whose match you are).
 *
 * - From the exchange date on (Manila time), in a shuffled group, one card per member: a design and a short
 *   message. It's emailed to the giver and shown at the top of their See Match screen, from "your match".
 * - The sender never learns who it went to; the giver already knows their match, so nothing new is revealed.
 * - Members without Member+ see a prompt on their See Match screen that opens the Member+ panel.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class ThankYou {
    const META = '_mm_thanks'; // array( d => design, m => message, t => time, to => giver id )
    const MAX  = 300;

    public static function init() {
        add_action( 'mm_see_match_top', array( __CLASS__, 'seeMatch' ), 10, 3 );
        add_filter( 'mm_mplus_tabs', array( __CLASS__, 'tab' ), 20, 2 );
        add_action( 'wp_ajax_mm_ty_send', array( __CLASS__, 'ajaxSend' ) );
        add_action( 'wp_ajax_nopriv_mm_ty_send', array( __CLASS__, 'ajaxSend' ) );
    }

    /** key => array( label, emoji, from, to ) */
    public static function designs() {
        return array(
            'gifts'  => array( 'Gifts', '🎁', '#ff7a7f', '#ffd1d3' ),
            'tree'   => array( 'Tree', '🎄', '#2fae7f', '#c8f0de' ),
            'parol'  => array( 'Parol', '🌟', '#f2a516', '#ffe7b0' ),
            'snow'   => array( 'Snow', '❄️', '#6f95f2', '#d9e4ff' ),
            'cocoa'  => array( 'Cocoa', '☕', '#a8714e', '#f1dccd' ),
            'hearts' => array( 'Hearts', '💝', '#e46aa8', '#ffd6ea' ),
        );
    }

    /** The exchange date (Y-m-d) or ''. */
    protected static function exchangeDate( $gid ) {
        $raw = (string) get_field( 'gift_exchange_date', $gid );
        $d = $raw !== '' ? DateTime::createFromFormat( 'm/d/Y', $raw, wp_timezone() ) : false;

        return $d ? $d->format( 'Y-m-d' ) : '';
    }

    public static function open( $gid ) {
        $date = self::exchangeDate( $gid );
        $today = ( new DateTime( 'now', wp_timezone() ) )->format( 'Y-m-d' );

        return get_field( 'matched', $gid ) && $date !== '' && $today >= $date;
    }

    /** The member who drew $uid (their Secret Santa), or 0. */
    public static function giver( $uid ) {
        $gid = (int) get_field( 'groups', $uid, false );
        $ids = get_posts( array( 'post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => 2, 'fields' => 'ids', 'meta_query' => array(
            array( 'key' => 'pair', 'value' => (int) $uid ),
            array( 'key' => 'groups', 'value' => $gid ),
        ) ) );

        return count( $ids ) === 1 ? (int) $ids[0] : 0;
    }

    public static function sent( $uid ) {
        $t = get_post_meta( (int) $uid, self::META, true );

        return is_array( $t ) && ! empty( $t['t'] ) ? $t : null;
    }

    public static function cardHtml( array $t, $from ) {
        $d = self::designs()[ $t['d'] ] ?? self::designs()['gifts'];

        return '<div class="mm-ty" style="--ty-a:' . esc_attr( $d[2] ) . ';--ty-b:' . esc_attr( $d[3] ) . '">'
            . '<div class="mm-ty__art" aria-hidden="true">' . $d[1] . '</div>'
            . '<div class="mm-ty__title">Thank you!</div>'
            . '<p class="mm-ty__msg">' . nl2br( esc_html( $t['m'] ) ) . '</p>'
            . '<div class="mm-ty__from">&mdash; your match, ' . esc_html( $from ) . '</div></div>';
    }

    /* ------------------------------------------------------------------ See Match */

    /** $uid is looking at their match $pair. */
    public static function seeMatch( $uid, $pair, $gid ) {
        // A card from their match (they're $pair's Secret Santa).
        $t = self::sent( $pair );
        if ( $t && (int) ( $t['to'] ?? 0 ) === (int) $uid ) {
            echo '<section class="mm-sec mm-ty-got"><h3 class="mm-sec__title"><i class="fas fa-envelope-open-text"></i> A THANK-YOU<span>&nbsp;FOR YOU</span></h3>'
                . self::cardHtml( $t, (string) get_field( 'screen_name', $pair ) ) . '</section>';
        }

        // On or after the exchange date: thank your own Secret Santa.
        if ( self::open( $gid ) && ! self::sent( $uid ) && self::giver( $uid ) ) {
            $plus = class_exists( 'MemberPlus' ) && MemberPlus::has( $uid );
            echo '<div class="mm-ty-prompt"><span aria-hidden="true">💌</span><div><strong>Send a thank-you card to your Secret Santa</strong><span>' . ( $plus ? 'Pick a design and write a short note. They&rsquo;ll get it by email.' : 'A Member+ perk (&#8369;' . ( class_exists( 'MemberPlus' ) ? MemberPlus::PRICE : 99 ) . ', just your card).' ) . '</span></div>'
                . '<button type="button" class="mp-btn" data-mp-open="thanks" data-uid="' . (int) $uid . '">' . ( $plus ? 'Write it' : 'Get Member+' ) . '</button></div>';
        }
    }

    /* ------------------------------------------------------------------ Member+ panel tab */

    public static function tab( $tabs, $uid ) {
        $gid = (int) get_field( 'groups', $uid, false );
        $sent = self::sent( $uid );
        if ( $sent ) {
            $html = '<p class="mp-note"><i class="fa-solid fa-check" aria-hidden="true"></i> Sent ' . esc_html( wp_date( 'M j', (int) $sent['t'] ) ) . '. Your Secret Santa got it by email and sees it on their match screen.</p>' . self::cardHtml( $sent, (string) get_field( 'screen_name', $uid ) );
        } elseif ( ! get_field( 'matched', $gid ) ) {
            $html = '<p class="mp-note">Once the group is shuffled and the exchange day comes, you can send your Secret Santa a thank-you card here.</p>';
        } elseif ( ! self::open( $gid ) ) {
            $date = self::exchangeDate( $gid );
            $html = '<p class="mp-note">Available from the exchange day' . ( $date ? ', <strong>' . esc_html( wp_date( 'M j', strtotime( $date . ' 12:00:00' ) ) ) . '</strong>' : '' ) . '. Come back after you open your gift!</p>';
        } elseif ( ! self::giver( $uid ) ) {
            $html = '<p class="mp-note">We couldn&rsquo;t find who drew your name. Ask your organizer if the shuffle changed.</p>';
        } else {
            $opts = '';
            $first = true;
            foreach ( self::designs() as $k => $d ) {
                $opts .= '<label class="mp-ty-opt" style="--ty-a:' . esc_attr( $d[2] ) . ';--ty-b:' . esc_attr( $d[3] ) . '"><input type="radio" name="design" value="' . esc_attr( $k ) . '"' . ( $first ? ' checked' : '' ) . '><span>' . $d[1] . '</span></label>';
                $first = false;
            }
            $html = '<form class="mp-form" data-ty-form>'
                . '<p class="mp-note">Signed &ldquo;your match, ' . esc_html( (string) get_field( 'screen_name', $uid ) ) . '&rdquo;. One card only.</p>'
                . '<div class="mp-ty-opts" role="radiogroup" aria-label="Card design">' . $opts . '</div>'
                . '<label class="mp-field"><span>Your message <small>(up to ' . self::MAX . ' characters)</small></span><textarea name="message" rows="4" maxlength="' . self::MAX . '" required placeholder="Thank you so much! I love it because…"></textarea></label>'
                . '<div class="mp-form__foot"><button type="submit" class="mp-btn">Send thank-you card</button><p class="mp-msg" data-mp-msg hidden></p></div></form>';
        }
        $html = class_exists( 'MemberPlus' ) ? MemberPlus::section( 'fa-solid fa-envelope-open-text', 'Thank your Secret Santa', 'They get it by email and on their match screen. You won&rsquo;t see who they are.', $html ) : $html;
        $tabs['thanks'] = array( 'Thank you', $html );

        return $tabs;
    }

    public static function ajaxSend() {
        if ( ! check_ajax_referer( 'mm_mplus', 'nonce', false ) ) wp_send_json_error( array( 'message' => 'This page has expired. Please refresh.' ) );
        $uid = absint( $_POST['uid'] ?? 0 );
        if ( ! class_exists( 'MemberPlus' ) || ! MemberPlus::verified( $uid ) ) wp_send_json_error( array( 'message' => 'Please open your card with your password again.', 'reauth' => 1 ) );
        if ( ! MemberPlus::has( $uid ) ) wp_send_json_error( array( 'message' => 'Get Member+ first.' ) );
        $gid = (int) get_field( 'groups', $uid, false );
        if ( self::sent( $uid ) ) wp_send_json_error( array( 'message' => 'You already sent your thank-you card.' ) );
        if ( ! self::open( $gid ) ) wp_send_json_error( array( 'message' => 'Thank-you cards open on the exchange day.' ) );
        $giver = self::giver( $uid );
        if ( ! $giver ) wp_send_json_error( array( 'message' => 'We couldn&rsquo;t find who drew your name.' ) );

        $design = sanitize_key( (string) ( $_POST['design'] ?? '' ) );
        $msg = trim( sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) ) );
        if ( ! isset( self::designs()[ $design ] ) ) $design = 'gifts';
        if ( $msg === '' ) wp_send_json_error( array( 'message' => 'Write a short message first.' ) );
        $msg = mb_substr( $msg, 0, self::MAX );

        $t = array( 'd' => $design, 'm' => $msg, 't' => time(), 'to' => $giver );
        update_post_meta( $uid, self::META, $t );
        self::mail( $uid, $giver, $gid, $t );
        if ( class_exists( 'Insights' ) ) Insights::log( 'mp_use', $gid, $uid, 0, 'thankyou' );

        wp_send_json_success( array( 'message' => 'Sent! Your Secret Santa will love it.', 'html' => self::cardHtml( $t, (string) get_field( 'screen_name', $uid ) ) ) );
    }

    protected static function mail( $uid, $giver, $gid, array $t ) {
        if ( ! class_exists( 'Emails' ) ) return;
        $d = self::designs()[ $t['d'] ];
        $from = (string) get_field( 'screen_name', $uid );
        $card = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;"><tr><td style="padding:26px 22px;border-radius:18px;background:' . esc_attr( $d[3] ) . ';border:2px solid ' . esc_attr( $d[2] ) . ';text-align:center;font-family:Arial,sans-serif;">'
            . '<div style="font-size:40px;line-height:1;">' . $d[1] . '</div>'
            . '<div style="margin:10px 0 8px;font-size:22px;font-weight:700;color:#1b1b1b;">Thank you!</div>'
            . '<div style="font-size:16px;line-height:1.55;color:#1b1b1b;">' . nl2br( esc_html( $t['m'] ) ) . '</div>'
            . '<div style="margin-top:12px;font-size:13px;color:#555;">&mdash; your match, ' . esc_html( $from ) . '</div></td></tr></table>';
        Emails::send( (string) get_field( 'email', $giver ), $from . ' sent you a thank-you card', array(
            'preheader' => 'Your match loved your gift.',
            'eyebrow'   => 'From your match',
            'title'     => 'You got a thank-you card!',
            'body'      => array( '<p>Your match, <strong>' . esc_html( $from ) . '</strong>, sent you this after the exchange:</p>' . $card ),
            'button'    => array( 'Open the group', Emails::groupLink( $gid ) ),
            'footer'    => 'You&rsquo;re getting this because you&rsquo;re in ' . esc_html( (string) get_field( 'group_name', $gid ) ) . ' on Manito Manita.',
        ) );
    }
}

ThankYou::init();
