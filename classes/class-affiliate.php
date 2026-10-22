<?php
/**
 * * Affiliate Class. Involve Asia deeplinks and gift idea helpers for Manito Manita.
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
 * Class Affiliate
 */
class Affiliate {
    const AFF_ID      = '189674';
    const PROPERTY_ID = '133170';

    /**
     * Involve Asia merchants. Add approved offers here.
     * 'domains' are matched against the end of the link's host.
     */
    protected static $merchants = array(
        'lazada' => array(
            'name'     => 'Lazada',
            'offer_id' => '101166',
            'domains'  => array('lazada.com.ph'),
        ),
        'shopee' => array(
            'name'     => 'Shopee',
            'offer_id' => '101653',
            'domains'  => array('shopee.ph', 'shp.ee', 'shope.ee'),
        ),
    );

    /**
     * Used when the Gift Ideas options page has no entries yet.
     */
    protected static $default_ideas = array(
        array('label' => 'Scented candles',   'icon' => 'fas fa-fire',          'keyword' => 'scented candle gift set'),
        array('label' => 'Mugs & tumblers',   'icon' => 'fas fa-mug-hot',       'keyword' => 'tumbler'),
        array('label' => 'Skincare sets',     'icon' => 'fas fa-spa',           'keyword' => 'skincare gift set'),
        array('label' => 'Wireless earbuds',  'icon' => 'fas fa-headphones',    'keyword' => 'wireless earbuds'),
        array('label' => 'Snacks & treats',   'icon' => 'fas fa-cookie-bite',   'keyword' => 'chocolate gift box'),
        array('label' => 'Desk gadgets',      'icon' => 'fas fa-lightbulb',     'keyword' => 'desk gadget'),
        array('label' => 'Bags & pouches',    'icon' => 'fas fa-shopping-bag',  'keyword' => 'tote bag'),
        array('label' => 'Board & card games','icon' => 'fas fa-dice',          'keyword' => 'party card game'),
    );

    /**
     * Returns the merchant key for a URL, or false.
     */
    public static function merchantFor( $url ) {
        $host = parse_url( self::normalize( $url ), PHP_URL_HOST );

        if ( ! $host ) return false;

        $host = strtolower( $host );

        foreach ( self::$merchants as $key => $m ) {
            foreach ( $m['domains'] as $domain ) {
                if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
                    return $key;
                }
            }
        }

        return false;
    }

    public static function merchantName( $key ) {
        return isset( self::$merchants[ $key ] ) ? self::$merchants[ $key ]['name'] : '';
    }

    /**
     * Converts a merchant URL to an Involve Asia deeplink. Non-merchant URLs are returned unchanged.
     *
     * @param string     $url       Destination URL.
     * @param string     $placement Where the link is shown (aff_sub), e.g. 'match-wishlist'.
     * @param int|string $groupId   Group post ID (aff_sub2).
     */
    public static function link( $url, $placement = '', $groupId = '' ) {
        $url = trim( (string) $url );

        if ( $url === '' || strpos( $url, 'invol.co' ) !== false ) return $url;

        $merchant = self::merchantFor( $url );

        if ( ! $merchant ) return $url;

        $params = array(
            'offer_id'    => self::$merchants[ $merchant ]['offer_id'],
            'aff_id'      => self::AFF_ID,
            'property_id' => self::PROPERTY_ID,
            'source'      => 'deeplink_generator_v2',
        );

        if ( $placement !== '' ) $params['aff_sub'] = $placement;
        if ( $groupId !== '' )   $params['aff_sub2'] = (string) $groupId;

        return 'https://invol.co/aff_m?' . http_build_query( $params ) . '&url=' . urlencode( self::normalize( $url ) );
    }

    /**
     * Anchor tag for an affiliate link.
     */
    public static function anchor( $url, $text, $placement = '', $groupId = '', $class = '', $attrs = array() ) {
        $isAff = self::merchantFor( $url ) !== false;
        $href  = $isAff ? self::link( $url, $placement, $groupId ) : self::normalize( $url );
        $cls   = trim( ( $isAff ? 'mm-aff ' : '' ) . $class );
        $rel   = $isAff ? 'nofollow sponsored noopener' : 'nofollow noopener';
        $extra = '';

        foreach ( $attrs as $name => $value ) {
            $extra .= ' ' . $name . '="' . esc_attr( $value ) . '"';
        }

        return '<a href="' . esc_url( $href ) . '"' . ( $cls ? ' class="' . esc_attr( $cls ) . '"' : '' ) . ' target="_blank" rel="' . $rel . '"' . $extra . '>' . $text . '</a>';
    }

    /**
     * Turns plain URLs inside text into (affiliate) links.
     */
    public static function linkify( $text, $placement = '', $groupId = '', $class = '', $attrs = array() ) {
        $pattern = "~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i";

        return preg_replace_callback( $pattern, function ( $m ) use ( $placement, $groupId, $class, $attrs ) {
            return self::anchor( $m[0], $m[0], $placement, $groupId, $class, $attrs );
        }, (string) $text );
    }

    /**
     * Price-filtered merchant search URL (not yet an affiliate link).
     */
    public static function searchUrl( $merchant, $keyword, $max = 0 ) {
        $max = (int) $max;

        if ( $merchant === 'lazada' ) {
            $url = 'https://www.lazada.com.ph/catalog/?q=' . urlencode( $keyword );
            if ( $max > 0 ) $url .= '&price=-' . $max;
            return $url;
        }

        if ( $merchant === 'shopee' ) {
            $url = 'https://shopee.ph/search?keyword=' . urlencode( $keyword );
            if ( $max > 0 ) $url .= '&maxPrice=' . $max;
            return $url;
        }

        return '';
    }

    /**
     * The group's spending minimum as an integer peso amount (0 if unset).
     */
    public static function budget( $groupId ) {
        if ( ! $groupId || ! function_exists( 'get_field' ) ) return 0;

        return (int) preg_replace( '/[^0-9]/', '', (string) get_field( 'spending_minimum', $groupId ) );
    }

    /**
     * Search keywords taken from a wishlist description (URLs and tags removed, first few words).
     */
    public static function keywordsFrom( $text, $words = 5 ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = preg_replace( '~(https?://|www\.)\S+~i', ' ', $text );
        $text = preg_replace( '/[^\p{L}\p{N}\s\-]/u', ' ', $text );
        $parts = preg_split( '/\s+/', strtolower( trim( $text ) ) );
        $stop  = array( 'i', 'a', 'an', 'the', 'my', 'me', 'want', 'would', 'like', 'love', 'please', 'any', 'or', 'and', 'of', 'for', 'to', 'with', 'is', 'it', 'gusto', 'ko', 'ng', 'na', 'sana', 'po', 'yung', 'ung', 'lang', 'kahit', 'ano' );
        $parts = array_filter( $parts, function ( $w ) use ( $stop ) { return $w !== '' && ! in_array( $w, $stop, true ); } );

        return implode( ' ', array_slice( $parts, 0, $words ) );
    }

    public static function giftIdeas() {
        $ideas = function_exists( 'get_field' ) ? get_field( 'gift_ideas', 'option' ) : false;

        return ( is_array( $ideas ) && count( $ideas ) > 0 ) ? $ideas : self::$default_ideas;
    }

    /**
     * "Gift ideas within ₱X" block.
     *
     * @param int    $groupId   Group post ID.
     * @param string $placement aff_sub value.
     * @param bool   $forEmail  Inline-styled table markup for email clients.
     * @param array  $ideas     Ideas to show (e.g. from GiftAI); defaults to the Gift Ideas list.
     * @param string $title     Heading text; " within ₱X" is appended.
     */
    public static function giftIdeasHtml( $groupId, $placement, $forEmail = false, $ideas = null, $title = null ) {
        $max   = self::budget( $groupId );
        $title = $title ? $title : 'Gift ideas';
        $title .= ( $max > 0 ) ? ' within &#8369;' . number_format( $max ) : '';
        $ideas = is_array( $ideas ) ? $ideas : self::giftIdeas();

        if ( $forEmail ) {
            $btn  = 'display:inline-block;padding:6px 12px;margin:2px;border-radius:4px;color:#ffffff;text-decoration:none;font-size:13px;';
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;border-top:1px solid #eeeeee;font-family:Arial,sans-serif;">';
            $html .= '<tr><td colspan="2" style="padding:16px 0 8px;font-size:18px;font-weight:bold;color:#333333;">' . $title . '</td></tr>';

            foreach ( $ideas as $idea ) {
                $html .= '<tr>';
                $html .= '<td style="padding:6px 0;font-size:14px;color:#333333;">' . esc_html( $idea['label'] ) . '</td>';
                $html .= '<td style="padding:6px 0;text-align:right;white-space:nowrap;">';
                $html .= '<a href="' . esc_url( self::link( self::searchUrl( 'lazada', $idea['keyword'], $max ), $placement, $groupId ) ) . '" target="_blank" rel="nofollow sponsored noopener" style="' . $btn . 'background:#0f146d;">Lazada</a>';
                $html .= '<a href="' . esc_url( self::link( self::searchUrl( 'shopee', $idea['keyword'], $max ), $placement, $groupId ) ) . '" target="_blank" rel="nofollow sponsored noopener" style="' . $btn . 'background:#ee4d2d;">Shopee</a>';
                $html .= '</td></tr>';
            }

            $html .= '<tr><td colspan="2" style="padding-top:8px;font-size:11px;color:#999999;">We may earn a small commission from these links.</td></tr>';
            $html .= '</table>';

            return $html;
        }

        $html  = '<div class="gift-ideas aff-cta-optional">';
        $html .= '<h2><i class="fas fa-lightbulb"></i> ' . $title . '</h2>';
        $html .= '<ul class="gift-ideas__list">';

        foreach ( $ideas as $idea ) {
            $icon  = ! empty( $idea['icon'] ) ? '<i class="' . esc_attr( $idea['icon'] ) . '"></i> ' : '';
            $html .= '<li class="gift-ideas__item">';
            $html .= '<span class="gift-ideas__label">' . $icon . esc_html( $idea['label'] );
            $html .= ! empty( $idea['why'] ) ? '<small class="gift-ideas__why">' . esc_html( $idea['why'] ) . '</small>' : '';
            $html .= '</span>';
            $html .= '<span class="gift-ideas__btns">';
            $html .= self::anchor( self::searchUrl( 'lazada', $idea['keyword'], $max ), 'Lazada', $placement, $groupId, 'gift-btn gift-btn--lazada' );
            $html .= self::anchor( self::searchUrl( 'shopee', $idea['keyword'], $max ), 'Shopee', $placement, $groupId, 'gift-btn gift-btn--shopee' );
            $html .= '</span>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '<div class="aff-disclosure">We may earn a small commission from these links.</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Compact "Browse gifts within ₱X" buttons (members list, shop-for buttons).
     */
    public static function shopButtonsHtml( $groupId, $placement, $keyword = '', $prefix = 'Browse gifts' ) {
        $max     = self::budget( $groupId );
        $keyword = trim( $keyword ) !== '' ? $keyword : 'gift';
        $within  = ( $max > 0 ) ? ' within &#8369;' . number_format( $max ) : '';

        $html  = '<div class="shop-for aff-cta-optional">';
        $html .= '<span class="shop-for__label">' . $prefix . $within . ':</span> ';
        $html .= self::anchor( self::searchUrl( 'lazada', $keyword, $max ), '<i class="fas fa-shopping-cart"></i> Lazada', $placement, $groupId, 'gift-btn gift-btn--lazada' );
        $html .= self::anchor( self::searchUrl( 'shopee', $keyword, $max ), '<i class="fas fa-shopping-bag"></i> Shopee', $placement, $groupId, 'gift-btn gift-btn--shopee' );
        $html .= '</div>';

        return $html;
    }

    /**
     * Wishlist reference link labelled by merchant ("View on Shopee", else "Link N").
     */
    public static function referenceLink( $url, $index, $placement, $groupId, $class = '', $attrs = array() ) {
        if ( strlen( trim( (string) $url ) ) === 0 ) return '';

        $merchant = self::merchantFor( $url );
        $text     = $merchant ? 'View on ' . self::merchantName( $merchant ) : 'Link ' . ( $index + 1 );

        return self::anchor( $url, $text, $placement, $groupId, $class, $attrs );
    }

    /**
     * Adds "https://" to bare "www." links so they can be parsed and encoded.
     */
    protected static function normalize( $url ) {
        $url = trim( (string) $url );

        return preg_match( '~^https?://~i', $url ) ? $url : 'https://' . ltrim( $url, '/' );
    }
}
