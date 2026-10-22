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
     * Fallback Involve Asia merchants, used when the API offer map (Involve::offerFor) has no match.
     * 'offer_id' is the aff_m tracking id; 'api_id' is the id the Deeplink API expects.
     * 'domains' are matched against the end of the link's host.
     */
    protected static $merchants = array(
        'lazada' => array(
            'name'     => 'Lazada',
            'offer_id' => '103490', // Lazada Talent (PH); the old 101166 redirects here
            'api_id'   => 4644,
            'domains'  => array('lazada.com.ph'),
        ),
        'shopee' => array(
            'name'     => 'Shopee',
            'offer_id' => '103880', // Shopee PH - CPS; the old 101653 redirects here
            'api_id'   => 5034,
            'domains'  => array('shopee.ph', 'shp.ee', 'shope.ee'),
        ),
        'shein' => array(
            'name'     => 'SHEIN',
            'offer_id' => '103795', // Shein Global - CPS (covers PH)
            'api_id'   => 4949,
            'domains'  => array('shein.com', 'shein.top'),
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
     * Merchant for a URL: ['name', 'offer_id', 'api_id'] or false.
     * Approved offers from the Involve Asia API win; the built-in list is the fallback.
     */
    public static function merchantFor( $url ) {
        $host = parse_url( self::normalize( $url ), PHP_URL_HOST );

        if ( ! $host ) return false;

        $host = strtolower( $host );

        foreach ( self::$merchants as $key => $m ) {
            foreach ( $m['domains'] as $domain ) {
                if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
                    $fallback = array( 'name' => $m['name'], 'offer_id' => $m['offer_id'], 'api_id' => $m['api_id'] );
                    break 2;
                }
            }
        }

        if ( class_exists( 'Involve' ) && Involve::enabled() && ( $offer = Involve::offerFor( $host ) ) ) {
            return array(
                'name'     => isset( $fallback ) ? $fallback['name'] : $offer['name'],
                'offer_id' => $offer['link_id'],
                'api_id'   => $offer['api_id'],
            );
        }

        return isset( $fallback ) ? $fallback : false;
    }

    public static function merchantName( $merchant ) {
        if ( is_array( $merchant ) ) return $merchant['name'];

        return isset( self::$merchants[ $merchant ] ) ? self::$merchants[ $merchant ]['name'] : '';
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

        // Product pages: use the stored Deeplink API link, which opens the exact product (also in the
        // store's app). Search pages (gift ideas, "Find it") are skipped to save the monthly quota.
        if ( class_exists( 'Involve' ) && Involve::enabled() && ! self::isSearchUrl( $url ) ) {
            $deeplink = Involve::deeplink( self::normalize( $url ), $merchant['api_id'] );
            if ( $deeplink ) return $deeplink;
        }

        $params = array(
            'offer_id'    => $merchant['offer_id'],
            'aff_id'      => self::AFF_ID,
            'property_id' => self::PROPERTY_ID,
            'source'      => 'deeplink_generator_v2',
        );

        if ( $placement !== '' ) $params['aff_sub'] = $placement;
        if ( $groupId !== '' )   $params['aff_sub2'] = (string) $groupId;

        return 'https://invol.co/aff_m?' . http_build_query( $params ) . '&url=' . urlencode( self::normalize( $url ) );
    }

    /**
     * Deeplink up to and including "&url=", for building links in JavaScript (append an encoded URL).
     */
    public static function linkPrefix( $merchant, $placement = '', $groupId = '' ) {
        if ( ! isset( self::$merchants[ $merchant ] ) ) return '';

        $link = self::link( self::searchUrl( $merchant, 'x' ), $placement, $groupId );

        return substr( $link, 0, strpos( $link, '&url=' ) + 5 );
    }

    /**
     * Store search/listing pages (not single products).
     */
    public static function isSearchUrl( $url ) {
        $path = (string) parse_url( self::normalize( $url ), PHP_URL_PATH );

        return (bool) preg_match( '~^/(catalog|search|pdsearch|tag)\b|^/?$~i', $path );
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
     * Plain user text (e.g. comments) to safe HTML: text escaped and shown as written, line breaks kept,
     * and URLs turned into links (affiliate links for supported stores).
     */
    public static function linkifyText( $text, $placement = '', $groupId = '' ) {
        // http(s):// or www. links, plus bare store links like "s.lazada.com.ph/..." or "shp.ee/...".
        $pattern = "~((?:https?://|www\.|(?<![\w@./-])(?:[a-z0-9-]+\.)*(?:lazada\.com\.ph|shopee\.ph|shp\.ee|shope\.ee|shein\.com)/)[^\s<]+?)(?=[.,:;!?)\]]*(?:\s|$))~i";
        // The text is shown exactly as written (escaped, never stripped); only link destinations change.
        $parts   = preg_split( $pattern, (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
        $html    = '';

        foreach ( $parts as $i => $part ) {
            $html .= ( $i % 2 === 1 ) ? self::anchor( $part, esc_html( $part ), $placement, $groupId ) : esc_html( $part );
        }

        return nl2br( $html );
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
     * "Gift ideas" section: a card per idea with Lazada / Shopee buttons.
     *
     * @param int    $groupId   Group post ID.
     * @param string $placement aff_sub value.
     * @param bool   $forEmail  Inline-styled table markup for email clients.
     * @param array  $ideas     Ideas to show (e.g. from GiftAI); defaults to the Gift Ideas list.
     * @param string $subtitle  Line under the heading; defaults to the budget hint.
     */
    public static function giftIdeasHtml( $groupId, $placement, $forEmail = false, $ideas = null, $subtitle = null ) {
        $max      = self::budget( $groupId );
        $within   = ( $max > 0 ) ? 'Within &#8369;' . number_format( $max ) : 'For any budget';
        $subtitle = $subtitle ? $subtitle . ' &middot; ' . strtolower( $within ) : $within . ' &middot; tap a store to browse';
        $ideas    = is_array( $ideas ) ? $ideas : self::giftIdeas();

        if ( $forEmail ) {
            $btn  = 'display:inline-block;padding:7px 14px;margin:0 0 0 6px;border-radius:999px;color:#ffffff;text-decoration:none;font-size:13px;font-weight:bold;';
            $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 8px;font-family:Arial,Helvetica,sans-serif;">';
            $html .= '<tr><td colspan="2" style="padding:0 0 4px;font-size:18px;font-weight:bold;color:#1b1b1b;letter-spacing:.5px;">GIFT <span style="color:#3ec7c2;font-weight:normal;">IDEAS</span></td></tr>';
            $html .= '<tr><td colspan="2" style="padding:0 0 12px;font-size:13px;color:#6d6d6d;">' . $subtitle . '</td></tr>';

            foreach ( $ideas as $idea ) {
                $html .= '<tr>';
                $html .= '<td style="padding:10px 0;border-top:1px solid #eeeeee;font-size:15px;color:#1b1b1b;">' . esc_html( $idea['label'] ) . '</td>';
                $html .= '<td style="padding:10px 0;border-top:1px solid #eeeeee;text-align:right;white-space:nowrap;">';
                $html .= '<a href="' . esc_url( self::link( self::searchUrl( 'lazada', $idea['keyword'], $max ), $placement, $groupId ) ) . '" target="_blank" rel="nofollow sponsored noopener" style="' . $btn . 'background:#0f146d;">Lazada</a>';
                $html .= '<a href="' . esc_url( self::link( self::searchUrl( 'shopee', $idea['keyword'], $max ), $placement, $groupId ) ) . '" target="_blank" rel="nofollow sponsored noopener" style="' . $btn . 'background:#ee4d2d;">Shopee</a>';
                $html .= '</td></tr>';
            }

            $html .= '</table>';

            return $html;
        }

        $html  = '<section class="mm-sec mm-gifts">';
        $html .= '<h3 class="mm-sec__title"><i class="fas fa-lightbulb"></i> GIFT<span>IDEAS</span></h3>';
        $html .= '<p class="mm-gifts__sub">' . $subtitle . '</p>';
        $html .= '<div class="mm-gifts__grid">';

        foreach ( $ideas as $idea ) {
            $icon  = ! empty( $idea['icon'] ) ? $idea['icon'] : 'fas fa-gift';
            $html .= '<div class="mm-gift">';
            $html .= '<div class="mm-gift__head">';
            $html .= '<span class="mm-gift__icon"><i class="' . esc_attr( $icon ) . '"></i></span>';
            $html .= '<div class="mm-gift__text">';
            $html .= '<div class="mm-gift__label">' . esc_html( $idea['label'] ) . '</div>';
            $html .= ! empty( $idea['why'] ) ? '<div class="mm-gift__why">' . esc_html( $idea['why'] ) . '</div>' : '';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<div class="mm-gift__btns">';
            $html .= self::anchor( self::searchUrl( 'lazada', $idea['keyword'], $max ), '<i class="fas fa-cart-shopping"></i> Lazada', $placement, $groupId, 'mm-store mm-store--lazada' );
            $html .= self::anchor( self::searchUrl( 'shopee', $idea['keyword'], $max ), '<i class="fas fa-bag-shopping"></i> Shopee', $placement, $groupId, 'mm-store mm-store--shopee' );
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Compact call-to-action strip with Lazada / Shopee search buttons.
     *
     * @param string $title   e.g. "Shop for Ana".
     * @param string $keyword Search query; when empty the strip offers general gifts.
     */
    public static function shopButtonsHtml( $groupId, $placement, $keyword = '', $title = 'Browse gifts' ) {
        $max     = self::budget( $groupId );
        $keyword = trim( $keyword );
        $within  = ( $max > 0 ) ? ' within &#8369;' . number_format( $max ) : '';
        $hint    = ( $keyword !== '' ) ? 'Search &ldquo;' . esc_html( $keyword ) . '&rdquo;' . $within : 'Gift ideas' . $within;
        $query   = ( $keyword !== '' ) ? $keyword : 'gift';

        // Only the repeated members-list strips are capped (Theme.js initAffiliateCap); match/editor CTAs always show.
        $capped = ( strpos( $placement, 'members-' ) === 0 ) ? ' aff-cta-optional' : '';

        $html  = '<div class="mm-shop' . $capped . '">';
        $html .= '<div class="mm-shop__text">';
        $html .= '<span class="mm-shop__icon"><i class="fas fa-gift"></i></span>';
        $html .= '<span><strong>' . esc_html( $title ) . '</strong><small>' . $hint . '</small></span>';
        $html .= '</div>';
        $html .= '<div class="mm-shop__btns">';
        $html .= self::anchor( self::searchUrl( 'lazada', $query, $max ), '<i class="fas fa-cart-shopping"></i> Lazada', $placement, $groupId, 'mm-store mm-store--lazada' );
        $html .= self::anchor( self::searchUrl( 'shopee', $query, $max ), '<i class="fas fa-bag-shopping"></i> Shopee', $placement, $groupId, 'mm-store mm-store--shopee' );
        $html .= '</div>';
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
