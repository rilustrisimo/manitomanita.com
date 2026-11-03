<?php
/**
 * Ads Class. Ad slots on group pages: a live sponsor card when one is booked for the slot,
 * otherwise a Google AdSense placeholder that Theme.js loads only when it scrolls near the
 * screen (no ad code on pages where nobody reaches a slot). PRO groups never show ads.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

/**
 * Class Ads
 */
class Ads {
    const ADSENSE_CLIENT = 'ca-pub-8648985139343614';

    /** AdSense fallback per slot: ad unit id, fixed size (w, h) or 0 for responsive. */
    protected static $adsense = array(
        'match-ideas'      => array( '3964563495', 300, 250 ),
        'dashboard-bottom' => array( '3637831254', 0, 0 ),
    );

    /**
     * The ad for a slot on a group page: '' for PRO groups, a sponsor card when one is live,
     * else the AdSense placeholder.
     */
    public static function slot( $slot, $gid = 0 ) {
        if ( $gid && function_exists( 'get_field' ) && get_field( 'pro', $gid ) ) return '';

        $card = method_exists( __CLASS__, 'sponsorCard' ) ? self::sponsorCard( $slot, $gid ) : '';
        if ( $card !== '' ) return $card;

        return self::adsense( $slot );
    }

    /** AdSense placeholder; Theme.initAdSense turns it into a live ad near the viewport. */
    public static function adsense( $slot ) {
        if ( empty( self::$adsense[ $slot ] ) ) return '';
        list( $unit, $w, $h ) = self::$adsense[ $slot ];

        $ins = $w
            ? '<ins class="adsbygoogle" style="display:inline-block;width:' . (int) $w . 'px;height:' . (int) $h . 'px" data-ad-client="' . self::ADSENSE_CLIENT . '" data-ad-slot="' . esc_attr( $unit ) . '"></ins>'
            : '<ins class="adsbygoogle" style="display:block" data-ad-client="' . self::ADSENSE_CLIENT . '" data-ad-slot="' . esc_attr( $unit ) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>';

        // The <ins> waits in a template so nothing loads until the slot is near the screen.
        return '<div class="mm-adslot' . ( $w ? ' mm-adslot--fixed' : '' ) . '" data-mm-adsense><template>' . $ins . '</template></div>';
    }
}
