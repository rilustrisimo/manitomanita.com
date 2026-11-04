<?php
/**
 * EmailAI Class. Gemini writes or rewrites promo email content for Manito Emails.
 * Key: GEMINI_EMAIL_KEY (wp-config.php) if defined, else GEMINI_API_KEY; model GEMINI_EMAIL_MODEL or GiftAI's.
 * Returns JSON: subjects (3), preheader, eyebrow, title, paragraphs, button. Only known {tokens} are kept.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class EmailAI {
    public static function key() {
        if ( defined( 'GEMINI_EMAIL_KEY' ) && GEMINI_EMAIL_KEY ) return GEMINI_EMAIL_KEY;

        return defined( 'GEMINI_API_KEY' ) ? (string) GEMINI_API_KEY : '';
    }

    public static function enabled() {
        return self::key() !== '';
    }

    protected static function model() {
        if ( defined( 'GEMINI_EMAIL_MODEL' ) && GEMINI_EMAIL_MODEL ) return GEMINI_EMAIL_MODEL;

        return ( defined( 'GEMINI_MODEL' ) && GEMINI_MODEL ) ? GEMINI_MODEL : 'gemini-3.5-flash-lite';
    }

    protected static function system() {
        $tokens = '{' . implode( '}, {', EmailCenter::tokenNames() ) . '}';

        return 'You write short marketing emails for Manito Manita (manitomanita.com), a free Secret Santa / Monito Monita / Kris Kringle organizer used mostly in the Philippines: an organizer creates a group, members join by link, add wishlists, and the organizer shuffles; PRO is an optional one-time PHP 199 upgrade per group. Prices are in pesos (PHP, written with the peso sign).'
            . "\nRules: write only what the brief asks; never invent features, prices, discounts, deadlines or statistics. Warm, friendly, clear; short sentences; mobile-first (2-4 short paragraphs, under 120 words). No ALL CAPS words, no repeated !!! or ???, no spam phrases (click here, act now, guaranteed, free money, winner, urgent). At most one emoji in the subject, none required."
            . "\nPersonal tokens you may use (exactly as written, nothing else in braces): " . $tokens . '. {group_link} and {start_link} are URLs: use them only as the button URL, never in the text. Prefer {first_name} in a greeting. Only use {match_name} if the audience is members of shuffled groups.'
            . "\nLanguage: as requested (English, Taglish = natural mix of Filipino and English as Filipinos text, or Filipino)."
            . "\nReturn JSON only: {\"subjects\":[3 options, 30-60 characters],\"preheader\":\"40-90 characters\",\"eyebrow\":\"1-3 words\",\"title\":\"under 60 characters\",\"paragraphs\":[\"...\"],\"button\":\"2-4 words\"}";
    }

    /**
     * $brief: goal text; $tone friendly|fun|urgent; $lang English|Taglish|Filipino; $audience summary line;
     * $current: existing content for rewrites; $mode write|shorter|fun|taglish|grammar.
     * Returns array or array( 'error' => message ).
     */
    public static function write( $brief, $tone, $lang, $audience, array $current = array(), $mode = 'write' ) {
        if ( ! self::enabled() ) return array( 'error' => 'Add GEMINI_API_KEY (or GEMINI_EMAIL_KEY) to wp-config.php to use AI writing.' );
        $ask = array(
            'write'   => 'Write a new email.',
            'shorter' => 'Rewrite the current email to be about half as long, same meaning.',
            'fun'     => 'Rewrite the current email to be more fun and playful, same meaning.',
            'taglish' => 'Rewrite the current email in natural Taglish.',
            'grammar' => 'Fix grammar and clarity of the current email; keep the wording close.',
        )[ $mode ] ?? 'Write a new email.';
        $prompt = $ask . "\nBrief: " . mb_substr( wp_strip_all_tags( (string) $brief ), 0, 800 )
            . "\nTone: " . sanitize_key( $tone ) . "\nLanguage: " . sanitize_text_field( $lang )
            . "\nAudience: " . wp_strip_all_tags( html_entity_decode( (string) $audience, ENT_QUOTES, 'UTF-8' ) );
        if ( $mode !== 'write' && $current ) {
            $prompt .= "\nCurrent email:\nSubject: " . wp_strip_all_tags( (string) ( $current['subject'] ?? '' ) ) . "\nTitle: " . wp_strip_all_tags( (string) ( $current['title'] ?? '' ) ) . "\nBody: " . mb_substr( wp_strip_all_tags( (string) ( $current['body'] ?? '' ) ), 0, 1500 );
        }

        $body = array(
            'systemInstruction' => array( 'parts' => array( array( 'text' => self::system() ) ) ),
            'contents'          => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
            'generationConfig'  => array( 'responseMimeType' => 'application/json', 'maxOutputTokens' => 1200, 'temperature' => 0.8 ),
        );
        for ( $try = 0; $try < 2; $try++ ) {
            $r = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( self::model() ) . ':generateContent', array(
                'timeout' => 30,
                'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => self::key() ),
                'body'    => wp_json_encode( $body ),
            ) );
            $code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
            if ( $code === 429 ) return array( 'error' => 'Gemini&rsquo;s quota is used up for now. Try again in a while.' );
            if ( $code === 200 ) break;
            if ( $try === 1 || ( $code && $code < 500 ) ) return array( 'error' => 'Gemini didn&rsquo;t answer (' . ( $code ?: 'timeout' ) . '). Try again.' );
        }

        $json = json_decode( wp_remote_retrieve_body( $r ), true );
        $text = '';
        foreach ( (array) ( $json['candidates'][0]['content']['parts'] ?? array() ) as $p ) if ( isset( $p['text'] ) && empty( $p['thought'] ) ) $text .= $p['text'];
        $out = json_decode( trim( preg_replace( '/^```(json)?|```$/m', '', $text ) ), true );
        if ( ! is_array( $out ) || empty( $out['paragraphs'] ) ) return array( 'error' => 'Gemini&rsquo;s answer couldn&rsquo;t be read. Try again.' );

        $known = EmailCenter::tokenNames();
        $clean = function ( $s ) use ( $known ) {
            $s = sanitize_text_field( (string) $s );
            return preg_replace_callback( '/\{([a-z_]+)\}/', function ( $m ) use ( $known ) { return in_array( $m[1], $known, true ) && ! in_array( $m[1], array( 'group_link', 'start_link' ), true ) ? $m[0] : ''; }, $s );
        };

        return array(
            'subjects'   => array_slice( array_map( $clean, (array) ( $out['subjects'] ?? array() ) ), 0, 3 ),
            'preheader'  => $clean( $out['preheader'] ?? '' ),
            'eyebrow'    => $clean( $out['eyebrow'] ?? '' ),
            'title'      => $clean( $out['title'] ?? '' ),
            'paragraphs' => array_slice( array_map( $clean, (array) $out['paragraphs'] ), 0, 6 ),
            'button'     => $clean( $out['button'] ?? '' ),
        );
    }
}
