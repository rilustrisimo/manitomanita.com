<?php
/**
 * Anonymous Q&A (PRO). After the shuffle, a member can ask the person they're giving to a question
 * ("What's your shirt size?") without revealing who they are; that person answers on their own
 * See Match screen. Only the two people involved see it: not the organizer, not the export.
 *
 * Stored on the receiver as post meta (_mm_qa): a list of
 * array('id', 'from' => giver uid, 'q', 'a', 'tq', 'ta'). The giver's id never leaves the server.
 * Cleared whenever matches change (Groups::resetMatches / runDraw).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class QA {
    const META      = '_mm_qa';
    const MAX_ASK   = 10;    // questions one giver can ask their match
    const MAX_Q     = 300;   // characters
    const MAX_A     = 500;
    const RATE_HOUR = 5;     // questions per giver per hour

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        foreach ( array( 'mm_qa_ask' => 'ask', 'mm_qa_answer' => 'answer' ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $method ) );
        }
    }

    public static function suggestions() {
        return array(
            'What&rsquo;s your shirt or shoe size?',
            'Any allergies or foods you avoid?',
            'Favorite color?',
            'Sweet or salty snacks?',
            'Coffee, tea or milk tea?',
            'What hobby are you into lately?',
        );
    }

    /** Member who entered their own password in the last 2 hours (same rule as Users::isVerifiedMember). */
    protected static function verified( $uid ) {
        $t = isset( $_SESSION['mm_verified'][ (int) $uid ] ) ? (int) $_SESSION['mm_verified'][ (int) $uid ] : 0;

        return $t > time() - 2 * HOUR_IN_SECONDS;
    }

    protected static function groupOf( $uid ) {
        return (int) get_field( 'groups', $uid, false );
    }

    public static function isOn( $gid ) {
        return (bool) get_field( 'pro', $gid );
    }

    protected static function items( $uid ) {
        $items = get_post_meta( (int) $uid, self::META, true );

        return is_array( $items ) ? $items : array();
    }

    protected static function clean( $text, $max ) {
        $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( wp_unslash( (string) $text ) ) ) );

        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
    }

    public static function clear( $uid ) {
        delete_post_meta( (int) $uid, self::META );
    }

    /* ------------------------------------------------------------------ See Match sections */

    /**
     * "Ask {name} anonymously": the giver's own questions to their match, with answers and a form.
     * Free groups get a locked teaser that opens the Gift PRO sheet.
     */
    public static function askSection( $uid, $pair, $gid ) {
        $uid = (int) $uid;
        $pair = (int) $pair;
        if ( ! $pair ) return '';

        $name = esc_html( (string) get_field( 'screen_name', $pair ) );

        if ( ! self::isOn( $gid ) ) {
            return '<section class="mm-sec mm-qa mm-qa--locked">'
                . '<h3 class="mm-sec__title"><i class="fas fa-user-secret"></i> ASK<span>&nbsp;ANONYMOUSLY</span><em class="mm-qa__pro">PRO</em></h3>'
                . '<p class="mm-qa__lead">Not sure about their size or what they like? In PRO groups you can ask ' . $name . ' a question without revealing who you are.</p>'
                . '<button type="button" class="mm-qa__unlock" data-mm-upsell="qa"><i class="fas fa-lock"></i> Unlock anonymous questions</button>'
                . '</section>';
        }

        $mine = array_values( array_filter( self::items( $pair ), function ( $i ) use ( $uid ) { return (int) $i['from'] === $uid; } ) );
        $left = self::MAX_ASK - count( $mine );

        $html  = '<section class="mm-sec mm-qa" data-mm-qa="ask" data-uid="' . $uid . '" data-nonce="' . esc_attr( wp_create_nonce( 'mm_qa_' . $uid ) ) . '">';
        $html .= '<h3 class="mm-sec__title"><i class="fas fa-user-secret"></i> ASK<span>&nbsp;ANONYMOUSLY</span></h3>';
        $html .= '<p class="mm-qa__lead">Ask ' . $name . ' anything that helps you pick the gift. ' . $name . ' won&rsquo;t see your name, only &ldquo;your Secret Santa&rdquo;.</p>';

        if ( $mine ) {
            $html .= '<ul class="mm-qa__list">';
            foreach ( array_reverse( $mine ) as $i ) {
                $html .= '<li class="mm-qa__item">';
                $html .= '<p class="mm-qa__q"><span>You asked</span>' . esc_html( $i['q'] ) . '</p>';
                $html .= ( $i['a'] !== '' )
                    ? '<p class="mm-qa__a"><span>' . $name . ' answered</span>' . esc_html( $i['a'] ) . '</p>'
                    : '<p class="mm-qa__wait"><i class="far fa-clock"></i> Waiting for ' . $name . '&rsquo;s answer. We&rsquo;ll email you.</p>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        if ( $left > 0 ) {
            $html .= '<form class="mm-qa__form" data-mm-qa-ask>';
            $html .= '<div class="mm-qa__chips">';
            foreach ( self::suggestions() as $s ) $html .= '<button type="button" data-mm-qa-chip>' . $s . '</button>';
            $html .= '</div>';
            $html .= '<label class="screen-reader-text" for="mm-qa-q">Your question</label>';
            $html .= '<textarea id="mm-qa-q" name="q" rows="2" maxlength="' . self::MAX_Q . '" placeholder="Type your question&hellip;" required></textarea>';
            $html .= '<button type="submit" class="mm-qa__send"><i class="fas fa-paper-plane"></i> Send anonymously</button>';
            $html .= '</form>';
        } else {
            $html .= '<p class="mm-qa__note">You&rsquo;ve asked ' . self::MAX_ASK . ' questions, the most for one match.</p>';
        }

        return $html . '</section>';
    }

    /**
     * "Your Secret Santa asked you": questions to this member, with answer boxes. Empty when none.
     */
    public static function inboxSection( $uid ) {
        $uid = (int) $uid;
        $items = self::items( $uid );
        if ( ! $items ) return '';

        $open = count( array_filter( $items, function ( $i ) { return $i['a'] === ''; } ) );

        $html  = '<section class="mm-sec mm-qa mm-qa--inbox" data-mm-qa="inbox" data-uid="' . $uid . '" data-nonce="' . esc_attr( wp_create_nonce( 'mm_qa_' . $uid ) ) . '">';
        $html .= '<h3 class="mm-sec__title"><i class="fas fa-envelope-open-text"></i> YOUR SECRET SANTA<span>&nbsp;ASKED</span>' . ( $open ? '<em class="mm-sec__count">' . $open . '</em>' : '' ) . '</h3>';
        $html .= '<p class="mm-qa__lead">The person giving you a gift wants to get it right. Your answer goes only to them.</p>';
        $html .= '<ul class="mm-qa__list">';

        foreach ( array_reverse( $items ) as $i ) {
            $html .= '<li class="mm-qa__item">';
            $html .= '<p class="mm-qa__q"><span>Your Secret Santa asked</span>' . esc_html( $i['q'] ) . '</p>';
            if ( $i['a'] !== '' ) {
                $html .= '<p class="mm-qa__a"><span>You answered</span>' . esc_html( $i['a'] ) . '</p>';
            } else {
                $html .= '<form class="mm-qa__form mm-qa__form--answer" data-mm-qa-answer data-qid="' . esc_attr( $i['id'] ) . '">';
                $html .= '<label class="screen-reader-text" for="mm-qa-a-' . esc_attr( $i['id'] ) . '">Your answer</label>';
                $html .= '<textarea id="mm-qa-a-' . esc_attr( $i['id'] ) . '" name="a" rows="2" maxlength="' . self::MAX_A . '" placeholder="Type your answer&hellip;" required></textarea>';
                $html .= '<button type="submit" class="mm-qa__send"><i class="fas fa-reply"></i> Send answer</button>';
                $html .= '</form>';
            }
            $html .= '</li>';
        }

        return $html . '</ul></section>';
    }

    /* ------------------------------------------------------------------ AJAX */

    protected static function guard( $uid ) {
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_qa_' . $uid ) || get_post_type( $uid ) !== 'users' || ! self::verified( $uid ) ) {
            wp_send_json_error( 'Please open your match again.' );
        }
        if ( ! self::isOn( self::groupOf( $uid ) ) ) wp_send_json_error( 'Anonymous questions are a PRO feature.' );
    }

    public static function ask() {
        $uid = (int) ( $_POST['uid'] ?? 0 );
        self::guard( $uid );

        $pair = (int) get_field( 'pair', $uid );
        $gid = self::groupOf( $uid );
        if ( ! $pair || get_post_type( $pair ) !== 'users' || self::groupOf( $pair ) !== $gid ) wp_send_json_error( 'You don&rsquo;t have a match yet.' );

        $q = self::clean( $_POST['q'] ?? '', self::MAX_Q );
        if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $q ) : strlen( $q ) ) < 3 ) wp_send_json_error( 'Please type a question.' );

        $items = self::items( $pair );
        $mine = array_filter( $items, function ( $i ) use ( $uid ) { return (int) $i['from'] === $uid; } );
        if ( count( $mine ) >= self::MAX_ASK ) wp_send_json_error( 'You&rsquo;ve reached the limit of ' . self::MAX_ASK . ' questions.' );

        $rate = 'mm_qa_rate_' . $uid;
        $sent = (int) get_transient( $rate );
        if ( $sent >= self::RATE_HOUR ) wp_send_json_error( 'That&rsquo;s a lot of questions! Please wait a bit before asking more.' );
        set_transient( $rate, $sent + 1, HOUR_IN_SECONDS );

        $items[] = array( 'id' => wp_generate_password( 8, false ), 'from' => $uid, 'q' => $q, 'a' => '', 'tq' => time(), 'ta' => 0 );
        update_post_meta( $pair, self::META, $items );

        self::notify( $pair, 'question', $q, $gid );

        wp_send_json_success( self::askSection( $uid, $pair, $gid ) );
    }

    public static function answer() {
        $uid = (int) ( $_POST['uid'] ?? 0 );
        self::guard( $uid );

        $qid = (string) ( $_POST['qid'] ?? '' );
        $a = self::clean( $_POST['a'] ?? '', self::MAX_A );
        if ( $a === '' ) wp_send_json_error( 'Please type your answer.' );

        $items = self::items( $uid );
        $from = 0;
        $question = '';
        foreach ( $items as $k => $i ) {
            if ( $i['id'] !== $qid ) continue;
            if ( $i['a'] !== '' ) wp_send_json_error( 'You already answered this one.' );
            $items[ $k ]['a'] = $a;
            $items[ $k ]['ta'] = time();
            $from = (int) $i['from'];
            $question = $i['q'];
        }
        if ( ! $from ) wp_send_json_error( 'This question is no longer available.' );

        update_post_meta( $uid, self::META, $items );

        // The giver sees the answer next time they open their match (their match is this member).
        if ( get_post_type( $from ) === 'users' && (int) get_field( 'pair', $from ) === $uid ) {
            self::notify( $from, 'answer', $question . "\n" . $a, self::groupOf( $uid ), $uid );
        }

        wp_send_json_success( self::inboxSection( $uid ) );
    }

    /* ------------------------------------------------------------------ Emails */

    /**
     * Question: email the receiver (never says who asked). Answer: email the giver.
     * Same rules as other notices: confirmed emails only, and members who unsubscribed are skipped.
     */
    protected static function notify( $to, $type, $text, $gid, $answeredBy = 0 ) {
        if ( ! class_exists( 'Emails' ) || ( class_exists( 'EmailCheck' ) && ! EmailCheck::isConfirmed( $to ) ) || Emails::optedOut( $to ) ) return;

        $group = (string) get_field( 'group_name', $gid );
        $link = add_query_arg( 'qa', (int) $to, Emails::groupLink( $gid ) );
        $quote = function ( $label, $value ) {
            return '<p style="margin:16px 0;padding:14px 16px;border-left:4px solid #3ec7c2;border-radius:8px;background:#f2f9f9;">'
                . '<span style="display:block;font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#1f8580;">' . esc_html( $label ) . '</span>'
                . '<span style="display:block;margin-top:4px;font-size:17px;line-height:1.45;color:#1b1b1b;">' . esc_html( $value ) . '</span></p>';
        };

        if ( $type === 'question' ) {
            Emails::send( (string) get_field( 'email', $to ), 'Your Secret Santa has a question for you', array(
                'preheader'   => 'They asked: ' . $text,
                'eyebrow'     => 'Anonymous question',
                'title'       => 'Your Secret Santa has a question',
                'body'        => array( $quote( 'Your Secret Santa asked', $text ), '<p>The person giving you a gift in <strong>' . esc_html( $group ) . '</strong> wants to get it right. Open the group, tap your name, then <strong>See match</strong> to answer. Your answer goes only to them.</p>' ),
                'button'      => array( 'Answer the question', $link ),
                'unsubscribe' => (int) $to,
                'footer'      => 'You&rsquo;re getting this because you&rsquo;re a member of ' . esc_html( $group ) . ' on Manito Manita.',
            ) );
            return;
        }

        list( $q, $a ) = array_pad( explode( "\n", $text, 2 ), 2, '' );
        $name = (string) get_field( 'screen_name', $answeredBy );

        Emails::send( (string) get_field( 'email', $to ), $name . ' answered your question', array(
            'preheader'   => $name . ' answered: ' . $a,
            'eyebrow'     => 'Anonymous question',
            'title'       => $name . ' answered your question',
            'body'        => array( $quote( 'You asked', $q ), $quote( $name . ' answered', $a ), '<p>' . esc_html( $name ) . ' still doesn&rsquo;t know it was you. Open your match to see everything in one place.</p>' ),
            'button'      => array( 'Open my match', $link ),
            'unsubscribe' => (int) $to,
            'footer'      => 'You&rsquo;re getting this because you asked a question in ' . esc_html( $group ) . ' on Manito Manita.',
        ) );
    }
}

QA::init();
