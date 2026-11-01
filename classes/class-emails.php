<?php
/**
 * * Emails Class. One email layout for Manito Manita: table-based, inline styles, 600px, works in
 * Gmail / Outlook / Apple Mail, with a hidden preheader, one bulletproof button and a plain-text part.
 *
 * Emails::send( $to, $subject, array(
 *     'preheader' => 'Inbox preview line',
 *     'eyebrow'   => 'Almost there',
 *     'title'     => 'Confirm your email',
 *     'body'      => array( '<p>..</p>', ... ),      // trusted HTML (escape user data before)
 *     'card'      => array( 'Group' => 'Office Party', 'Exchange' => 'Dec 19' ),
 *     'button'    => array( 'Confirm', 'https://...' ),
 *     'note'      => 'Small print under the button',
 *     'footer'    => 'Why you got this email',
 * ) );
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Emails
 */
class Emails {
    const FROM      = 'Manito Manita <rouie@manitomanita.com>';
    const REPLY_TO  = 'rouie@manitomanita.com';
    const INK       = '#1b1b1b';
    const ACCENT    = '#3ec7c2';
    const MUTED     = '#6d6d6d';
    const FONT      = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    protected static $altBody = '';

    public static function render( array $o ) {
        $f = self::FONT;
        $pre = esc_html( $o['preheader'] ?? '' );
        $logo = esc_url( get_template_directory_uri() . '/assets/images/logo.png' );

        $html  = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light"><title>' . esc_html( $o['title'] ?? 'Manito Manita' ) . '</title></head>';
        $html .= '<body style="margin:0;padding:0;background:#f2f5f5;-webkit-text-size-adjust:100%;">';
        $html .= '<div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;">' . $pre . str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f2f5f5;"><tr><td align="center" style="padding:24px 12px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">';

        // Header
        $html .= '<tr><td style="background:' . self::INK . ';border-radius:16px 16px 0 0;padding:18px 24px;" align="left">';
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= '<td style="padding-right:10px;" valign="middle"><img src="' . $logo . '" width="28" height="28" alt="" style="display:block;border:0;"></td>';
        $html .= '<td valign="middle" style="font-family:' . $f . ';font-size:18px;font-weight:700;letter-spacing:1px;color:#ffffff;">MANITO<span style="color:' . self::ACCENT . ';font-weight:300;">MANITA</span></td>';
        $html .= '</tr></table></td></tr>';

        // Body
        $html .= '<tr><td style="background:#ffffff;padding:32px 28px 28px;border-radius:0 0 16px 16px;font-family:' . $f . ';color:' . self::INK . ';">';

        if ( ! empty( $o['eyebrow'] ) ) {
            $html .= '<p style="margin:0 0 10px;"><span style="display:inline-block;padding:4px 10px;border-radius:999px;background:#e6f7f6;color:#1f8580;font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">' . esc_html( $o['eyebrow'] ) . '</span></p>';
        }

        if ( ! empty( $o['title'] ) ) {
            $html .= '<h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;font-weight:800;color:' . self::INK . ';">' . esc_html( $o['title'] ) . '</h1>';
        }

        foreach ( (array) ( $o['body'] ?? array() ) as $block ) {
            $html .= self::style( $block );
        }

        if ( ! empty( $o['card'] ) ) {
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 6px;background:#f6f8f8;border-radius:12px;">';
            foreach ( $o['card'] as $label => $value ) {
                if ( $value === '' || $value === null ) continue;
                $html .= '<tr><td style="padding:10px 16px;font-size:13px;color:' . self::MUTED . ';width:40%;">' . esc_html( $label ) . '</td>';
                $html .= '<td style="padding:10px 16px;font-size:14px;font-weight:700;color:' . self::INK . ';">' . esc_html( $value ) . '</td></tr>';
            }
            $html .= '</table>';
        }

        if ( ! empty( $o['button'] ) ) {
            list( $label, $url ) = $o['button'];
            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;"><tr>';
            $html .= '<td align="center" bgcolor="' . self::INK . '" style="border-radius:12px;">';
            $html .= '<a href="' . esc_url( $url ) . '" target="_blank" style="display:inline-block;padding:15px 28px;font-family:' . $f . ';font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;">' . esc_html( $label ) . '</a>';
            $html .= '</td></tr></table>';
            $html .= '<p style="margin:0 0 4px;font-size:12px;line-height:1.5;color:' . self::MUTED . ';">Button not working? Copy this link:<br><a href="' . esc_url( $url ) . '" style="color:#1f8580;word-break:break-all;">' . esc_html( $url ) . '</a></p>';
        }

        if ( ! empty( $o['note'] ) ) {
            $html .= '<p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:' . self::MUTED . ';">' . $o['note'] . '</p>';
        }

        $html .= '</td></tr>';

        // Footer
        $foot = $o['footer'] ?? 'You&rsquo;re getting this email because of a Manito Manita gift exchange group.';
        $html .= '<tr><td style="padding:18px 12px 4px;text-align:center;font-family:' . $f . ';font-size:12px;line-height:1.6;color:#8a8a8a;">';
        $html .= $foot . '<br>Manito Manita &middot; free online gift exchange &middot; <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#8a8a8a;">manitomanita.com</a>';
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table></body></html>';

        return $html;
    }

    /**
     * Adds inline styles to simple body blocks (<p>, <ol>/<ul>, <li>, <strong>).
     */
    protected static function style( $block ) {
        $p = 'margin:0 0 12px;font-size:15px;line-height:1.6;color:#3a3a3a;';
        $block = preg_replace( '~<p>~', '<p style="' . $p . '">', $block );
        $block = preg_replace( '~<(ol|ul)>~', '<$1 style="margin:0 0 12px;padding-left:20px;font-size:15px;line-height:1.6;color:#3a3a3a;">', $block );
        $block = preg_replace( '~<li>~', '<li style="margin:0 0 6px;">', $block );
        return $block;
    }

    /**
     * Plain-text version (better deliverability) from the same content.
     */
    protected static function text( array $o ) {
        $lines = array();
        if ( ! empty( $o['title'] ) ) $lines[] = wp_strip_all_tags( $o['title'] );
        foreach ( (array) ( $o['body'] ?? array() ) as $b ) {
            $b = preg_replace( '~</(p|li)>~i', "\n", $b );
            $lines[] = trim( html_entity_decode( wp_strip_all_tags( $b ), ENT_QUOTES, 'UTF-8' ) );
        }
        foreach ( (array) ( $o['card'] ?? array() ) as $k => $v ) {
            if ( $v !== '' && $v !== null ) $lines[] = $k . ': ' . $v;
        }
        if ( ! empty( $o['button'] ) ) $lines[] = $o['button'][0] . ': ' . $o['button'][1];
        if ( ! empty( $o['note'] ) ) $lines[] = html_entity_decode( wp_strip_all_tags( $o['note'] ), ENT_QUOTES, 'UTF-8' );
        $lines[] = "--\nManito Manita, free online gift exchange: " . home_url( '/' );

        return implode( "\n\n", array_filter( $lines ) );
    }

    public static function send( $to, $subject, array $o, array $headers = array() ) {
        if ( ! is_email( $to ) ) return false;

        self::$altBody = self::text( $o );
        add_action( 'phpmailer_init', array( __CLASS__, 'addAltBody' ) );

        $sent = wp_mail( $to, html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ), self::render( $o ), array_merge( array(
            'From: ' . self::FROM,
            'Reply-To: ' . self::REPLY_TO,
            'Content-Type: text/html; charset=UTF-8',
        ), $headers ) );

        remove_action( 'phpmailer_init', array( __CLASS__, 'addAltBody' ) );
        self::$altBody = '';

        return $sent;
    }

    public static function addAltBody( $phpmailer ) {
        if ( self::$altBody !== '' ) $phpmailer->AltBody = self::$altBody;
    }

    /* ------------------------------------------------------------ Helpers */

    public static function groupLink( $gid ) {
        return get_permalink( 23 ) . '?gid=' . (int) $gid . '&pw=' . rawurlencode( (string) get_field( 'group_password', $gid ) );
    }

    /**
     * Group facts for the info card.
     */
    public static function groupCard( $gid ) {
        $date = get_field( 'gift_exchange_date', $gid );
        $d = $date ? DateTime::createFromFormat( 'm/d/Y', $date ) : false;
        $budget = class_exists( 'Affiliate' ) ? Affiliate::budget( $gid ) : 0;

        return array(
            'Group'          => (string) get_field( 'group_name', $gid ),
            'Exchange date'  => $d ? $d->format( 'F j, Y' ) : '',
            'Gift budget'    => $budget ? '₱' . number_format( $budget ) . ' minimum' : '',
            'Organizer'      => (string) get_field( 'your_name', $gid ),
        );
    }
}
