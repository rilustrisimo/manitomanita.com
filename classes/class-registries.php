<?php
/**
 * Registries Class. Occasions for My Wishlist lists (wedding, debut, baptism, baby shower...): label,
 * icon, accent colour, registry title, whether the owner sees reservations by default, and gift ideas.
 * Plus the date helpers (countdown, thank-you mode after the day, closed 30 days later).
 *
 * Lists stay in MyWishlist (CPT mm_wishlist). Extra meta: _mm_wl_occasion, _mm_wl_for, _mm_wl_date (Y-m-d),
 * _mm_wl_seeres (1/0), _mm_wl_cash (note), _mm_wl_qr (image url).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class Registries {
    const KEEP_DAYS = 30; // guests can still open the list this long after the day

    /** key => label, noun (title), icon, accent, seeres (owner sees reservations by default), major (ad price group). */
    public static function occasions() {
        return array(
            'any'            => array( 'label' => 'Any occasion', 'noun' => 'wishlist', 'icon' => 'fa-solid fa-gift', 'accent' => '#3ec7c2', 'seeres' => 0 ),
            'birthday'       => array( 'label' => 'Birthday', 'noun' => 'Birthday Wishlist', 'icon' => 'fa-solid fa-cake-candles', 'accent' => '#f0a03c', 'seeres' => 0 ),
            'debut'          => array( 'label' => 'Debut (18th)', 'noun' => 'Debut Wishlist', 'icon' => 'fa-solid fa-crown', 'accent' => '#d16ba5', 'seeres' => 0, 'major' => 1 ),
            'wedding'        => array( 'label' => 'Wedding', 'noun' => 'Wedding Registry', 'icon' => 'fa-solid fa-ring', 'accent' => '#b08d57', 'seeres' => 1, 'major' => 1 ),
            'bridal-shower'  => array( 'label' => 'Bridal shower', 'noun' => 'Bridal Shower Registry', 'icon' => 'fa-solid fa-champagne-glasses', 'accent' => '#e48fa8', 'seeres' => 1 ),
            'baptism'        => array( 'label' => 'Baptism / Binyag', 'noun' => 'Baptism Registry', 'icon' => 'fa-solid fa-dove', 'accent' => '#5b9bd5', 'seeres' => 1 ),
            'first-birthday' => array( 'label' => 'First birthday', 'noun' => 'First Birthday Wishlist', 'icon' => 'fa-solid fa-baby', 'accent' => '#f2a65a', 'seeres' => 1 ),
            'kiddie-party'   => array( 'label' => 'Kiddie party', 'noun' => 'Birthday Wishlist', 'icon' => 'fa-solid fa-shapes', 'accent' => '#f27f5a', 'seeres' => 1 ),
            'baby-shower'    => array( 'label' => 'Baby shower / Gender reveal', 'noun' => 'Baby Shower Registry', 'icon' => 'fa-solid fa-baby-carriage', 'accent' => '#7fb8de', 'seeres' => 1 ),
            'housewarming'   => array( 'label' => 'Housewarming / House blessing', 'noun' => 'Housewarming Registry', 'icon' => 'fa-solid fa-house-chimney', 'accent' => '#6aa84f', 'seeres' => 1 ),
            'graduation'     => array( 'label' => 'Graduation', 'noun' => 'Graduation Wishlist', 'icon' => 'fa-solid fa-graduation-cap', 'accent' => '#4a6fa5', 'seeres' => 0 ),
            'despedida'      => array( 'label' => 'Despedida / Going abroad', 'noun' => 'Despedida Wishlist', 'icon' => 'fa-solid fa-plane-departure', 'accent' => '#3fa7a2', 'seeres' => 1 ),
            'retirement'     => array( 'label' => 'Retirement', 'noun' => 'Retirement Wishlist', 'icon' => 'fa-solid fa-umbrella-beach', 'accent' => '#d9a441', 'seeres' => 1 ),
            'anniversary'    => array( 'label' => 'Anniversary', 'noun' => 'Anniversary Wishlist', 'icon' => 'fa-solid fa-heart', 'accent' => '#d9534f', 'seeres' => 0 ),
            'communion'      => array( 'label' => 'First Communion / Confirmation', 'noun' => 'Communion Registry', 'icon' => 'fa-solid fa-church', 'accent' => '#8e7cc3', 'seeres' => 1 ),
            'christmas'      => array( 'label' => 'Christmas', 'noun' => 'Christmas Wishlist', 'icon' => 'fa-solid fa-tree', 'accent' => '#2e8b57', 'seeres' => 0 ),
            'valentines'     => array( 'label' => 'Valentine&rsquo;s', 'noun' => 'Valentine&rsquo;s Wishlist', 'icon' => 'fa-solid fa-heart-circle-check', 'accent' => '#e0457b', 'seeres' => 0 ),
        );
    }

    public static function valid( $key ) {
        return isset( self::occasions()[ (string) $key ] ) ? (string) $key : 'any';
    }

    public static function get( $key ) {
        return self::occasions()[ self::valid( $key ) ];
    }

    /** Ad placement for an occasion's registries (wedding and debut are the premium group). */
    public static function placement( $key ) {
        return ! empty( self::get( $key )['major'] ) ? 'registry-major' : 'registry';
    }

    /** Registry ads are open now that the registry guides exist; set option mm_registry_ads to 0 to close them. */
    public static function adsOpen() {
        return (bool) get_option( 'mm_registry_ads', 1 );
    }

    /** Published registry guides, occasion => url (one query per request). */
    public static function guides() {
        static $out = null;
        if ( $out !== null ) return $out;
        $out = array();
        $slugs = Landing::registrySlugs();
        $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name__in' => array_values( $slugs ), 'posts_per_page' => count( $slugs ) ) );
        $by = array();
        foreach ( $pages as $pg ) $by[ $pg->post_name ] = get_permalink( $pg );
        foreach ( $slugs as $k => $slug ) if ( isset( $by[ $slug ] ) ) $out[ $k ] = $by[ $slug ];

        return $out;
    }

    /** The occasion's registry guide (published landing page), or ''. */
    public static function guideUrl( $key ) {
        return self::guides()[ self::valid( $key ) ] ?? '';
    }

    /** Link to create a list with this occasion picked (jumps to the form). */
    public static function createUrl( $key ) {
        $key = self::valid( $key );

        return add_query_arg( $key === 'any' ? array() : array( 'occasion' => $key ), home_url( '/my-wishlist/' ) ) . '#create';
    }

    /** Placeholder for "Who is it for?" per occasion. */
    public static function forHint( $key ) {
        $h = array( 'wedding' => 'e.g. Mark & Joy', 'bridal-shower' => 'e.g. Joy', 'baptism' => 'e.g. Baby Ana', 'first-birthday' => 'e.g. Baby Ana', 'baby-shower' => 'e.g. Baby Santos',
            'kiddie-party' => 'e.g. Miguel', 'housewarming' => 'e.g. The Santos Family', 'anniversary' => 'e.g. Mark & Joy', 'communion' => 'e.g. Ana' );

        return $h[ self::valid( $key ) ] ?? 'e.g. Ana';
    }

    /** Gift ideas per occasion (label, icon, keyword) for the editor and the guest page. */
    public static function ideas( $key ) {
        $i = function ( $label, $icon, $kw ) { return array( 'label' => $label, 'icon' => 'fas fa-' . $icon, 'keyword' => $kw ); };
        $all = array(
            'wedding'        => array( $i( 'Dinnerware set', 'utensils', 'dinnerware set' ), $i( 'Bedsheet set', 'bed', 'bedsheet set queen' ), $i( 'Air fryer', 'fire-burner', 'air fryer' ), $i( 'Rice cooker', 'bowl-rice', 'rice cooker' ), $i( 'Towel set', 'bath', 'bath towel set' ), $i( 'Glassware set', 'wine-glass', 'wine glass set' ) ),
            'bridal-shower'  => array( $i( 'Robe set', 'shirt', 'bridal robe' ), $i( 'Spa kit', 'spa', 'spa gift set' ), $i( 'Kitchen tools', 'kitchen-set', 'kitchen utensil set' ), $i( 'Scented candles', 'fire', 'scented candle gift set' ), $i( 'Jewelry box', 'gem', 'jewelry box' ), $i( 'Lingerie set', 'heart', 'lingerie set' ) ),
            'debut'          => array( $i( 'Perfume', 'spray-can-sparkles', 'perfume for women' ), $i( 'Jewelry', 'gem', 'necklace for women' ), $i( 'Makeup set', 'wand-magic-sparkles', 'makeup set' ), $i( 'Instax camera', 'camera', 'instax mini' ), $i( 'Bag', 'bag-shopping', 'sling bag women' ), $i( 'Skincare set', 'spa', 'skincare gift set' ) ),
            'baptism'        => array( $i( 'Baby clothes set', 'shirt', 'baby clothes set newborn' ), $i( 'Baby blanket', 'bed', 'baby blanket' ), $i( 'Silver keepsake', 'gem', 'baby silver bracelet' ), $i( 'Baby bath set', 'bath', 'baby bath set' ), $i( 'Feeding set', 'utensils', 'baby feeding set' ), $i( 'Diapers', 'baby', 'diapers' ) ),
            'first-birthday' => array( $i( 'Learning toys', 'shapes', 'educational toys 1 year old' ), $i( 'Push walker', 'person-walking', 'baby walker push' ), $i( 'Board books', 'book', 'baby board books' ), $i( 'Baby clothes', 'shirt', 'baby clothes 12 months' ), $i( 'Ride-on toy', 'car', 'ride on toy toddler' ), $i( 'Bath toys', 'bath', 'baby bath toys' ) ),
            'kiddie-party'   => array( $i( 'Building blocks', 'cubes', 'building blocks kids' ), $i( 'Art set', 'palette', 'art set kids' ), $i( 'Story books', 'book', 'story books for kids' ), $i( 'Plush toy', 'paw', 'plush toy' ), $i( 'Kids tumbler', 'mug-hot', 'kids tumbler' ), $i( 'Board games', 'chess-board', 'board games for kids' ) ),
            'baby-shower'    => array( $i( 'Diapers', 'baby', 'diapers newborn' ), $i( 'Feeding bottles', 'bottle-water', 'baby feeding bottle set' ), $i( 'Swaddles', 'bed', 'swaddle blanket' ), $i( 'Baby carrier', 'baby-carriage', 'baby carrier' ), $i( 'Baby monitor', 'video', 'baby monitor' ), $i( 'Newborn clothes', 'shirt', 'newborn clothes set' ) ),
            'housewarming'   => array( $i( 'Cookware set', 'kitchen-set', 'cookware set' ), $i( 'Indoor plants', 'seedling', 'indoor plant' ), $i( 'Throw pillows', 'couch', 'throw pillow' ), $i( 'Coffee maker', 'mug-hot', 'coffee maker' ), $i( 'Storage boxes', 'box', 'storage box' ), $i( 'Wall clock', 'clock', 'wall clock' ) ),
            'graduation'     => array( $i( 'Laptop bag', 'briefcase', 'laptop backpack' ), $i( 'Watch', 'clock', 'watch' ), $i( 'Wireless earbuds', 'headphones', 'wireless earbuds' ), $i( 'Planner', 'book', 'planner' ), $i( 'Power bank', 'battery-full', 'power bank' ), $i( 'Perfume', 'spray-can-sparkles', 'perfume' ) ),
            'despedida'      => array( $i( 'Luggage', 'suitcase-rolling', 'luggage' ), $i( 'Travel organizer', 'bag-shopping', 'travel organizer' ), $i( 'Universal adapter', 'plug', 'universal travel adapter' ), $i( 'Neck pillow', 'bed', 'travel neck pillow' ), $i( 'Power bank', 'battery-full', 'power bank' ), $i( 'Pasalubong snacks', 'cookie-bite', 'filipino snacks' ) ),
            'retirement'     => array( $i( 'Gardening set', 'seedling', 'gardening tools set' ), $i( 'Massager', 'spa', 'massager' ), $i( 'Coffee maker', 'mug-hot', 'coffee maker' ), $i( 'Books', 'book', 'books' ), $i( 'Travel bag', 'suitcase-rolling', 'travel bag' ), $i( 'Smart watch', 'clock', 'smart watch' ) ),
            'anniversary'    => array( $i( 'Couple watch', 'clock', 'couple watch' ), $i( 'Photo frame', 'image', 'photo frame' ), $i( 'Perfume', 'spray-can-sparkles', 'perfume' ), $i( 'Jewelry', 'gem', 'jewelry' ), $i( 'Wine glasses', 'wine-glass', 'wine glass set' ), $i( 'Scented candles', 'fire', 'scented candle gift set' ) ),
            'communion'      => array( $i( 'Rosary', 'cross', 'rosary' ), $i( 'Bible for kids', 'book-bible', 'bible for kids' ), $i( 'Keepsake box', 'box', 'keepsake box' ), $i( 'Necklace with cross', 'gem', 'cross necklace' ), $i( 'Story books', 'book', 'story books for kids' ), $i( 'Photo album', 'image', 'photo album' ) ),
            'christmas'      => array( $i( 'Mugs & tumblers', 'mug-hot', 'tumbler' ), $i( 'Scented candles', 'fire', 'scented candle gift set' ), $i( 'Skincare sets', 'spa', 'skincare gift set' ), $i( 'Wireless earbuds', 'headphones', 'wireless earbuds' ), $i( 'Snacks & treats', 'cookie-bite', 'chocolate gift box' ), $i( 'Desk gadgets', 'lightbulb', 'desk gadget' ) ),
            'valentines'     => array( $i( 'Flowers', 'seedling', 'preserved flowers' ), $i( 'Chocolates', 'cookie-bite', 'chocolate gift box' ), $i( 'Perfume', 'spray-can-sparkles', 'perfume' ), $i( 'Jewelry', 'gem', 'necklace' ), $i( 'Couple shirt', 'shirt', 'couple shirt' ), $i( 'Plush toy', 'paw', 'teddy bear' ) ),
        );
        $all['birthday'] = $all['christmas'];

        return $all[ self::valid( $key ) ] ?? Affiliate::giftIdeas();
    }

    /* ------------------------------------------------------------------ One list */

    public static function occasionOf( $id ) {
        return self::valid( get_post_meta( (int) $id, '_mm_wl_occasion', true ) );
    }

    /** Who it's for ("Mark & Joy", "Baby Ana"); falls back to the owner's name. */
    public static function forName( $id ) {
        $f = (string) get_post_meta( (int) $id, '_mm_wl_for', true );

        return $f !== '' ? $f : MyWishlist::name( $id );
    }

    /** "Mark & Joy's Wedding Registry" / "Ana's wishlist" (HTML entities, not escaped). */
    public static function title( $id ) {
        $o = self::get( self::occasionOf( $id ) );

        return esc_html( self::forName( $id ) ) . '&rsquo;s ' . $o['noun'];
    }

    public static function seeRes( $id ) {
        $v = get_post_meta( (int) $id, '_mm_wl_seeres', true );

        return $v === '' ? (bool) self::get( self::occasionOf( $id ) )['seeres'] : (bool) $v;
    }

    /** Event date as a timestamp (site timezone, midnight) or 0. */
    public static function date( $id ) {
        $d = DateTime::createFromFormat( '!Y-m-d', (string) get_post_meta( (int) $id, '_mm_wl_date', true ), wp_timezone() );

        return $d ? $d->getTimestamp() : 0;
    }

    /** Whole days until the event (negative after), or null without a date. */
    public static function days( $id ) {
        $t = self::date( $id );
        if ( ! $t ) return null;
        $today = new DateTime( 'today', wp_timezone() );

        return (int) round( ( $t - $today->getTimestamp() ) / DAY_IN_SECONDS );
    }

    /** upcoming | today | thanks (up to 30 days after) | closed | none (no date). */
    public static function phase( $id ) {
        $d = self::days( $id );
        if ( $d === null ) return 'none';
        if ( $d > 0 ) return 'upcoming';
        if ( $d === 0 ) return 'today';

        return -$d <= self::KEEP_DAYS ? 'thanks' : 'closed';
    }

    /** "In 12 days · Sat, Dec 12" / "Today!" / "" */
    public static function countdown( $id ) {
        $d = self::days( $id );
        if ( $d === null || $d < 0 ) return '';
        $when = wp_date( 'D, M j, Y', self::date( $id ) );
        if ( $d === 0 ) return 'Today! &middot; ' . $when;

        return ( $d === 1 ? 'Tomorrow' : 'In ' . $d . ' days' ) . ' &middot; ' . $when;
    }

    /** Sponsor card for this registry's occasion (only once registry ads are open). */
    public static function sponsorHtml( $id ) {
        if ( ! self::adsOpen() || ! class_exists( 'Ads' ) ) return '';
        $occ = self::occasionOf( $id );
        if ( $occ === 'any' ) return '';

        return Ads::sponsorCard( self::placement( $occ ), 0, $occ );
    }
}
