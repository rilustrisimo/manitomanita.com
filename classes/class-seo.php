<?php
/**
 * * SEO Class. Search presence for Manito Manita, on top of Yoast SEO (the live SEO plugin).
 *
 * - Homepage title / description / social image (versioned in code, not in Yoast settings).
 * - Private or utility pages (group dashboard, reset password) are noindex and out of the sitemap.
 * - Philippine English (en-PH) language and og:locale.
 * - WebApplication + Organization structured data on the homepage.
 * - /llms.txt: a plain summary of the site for AI search engines.
 *
 * Group dashboards keep their own title + noindex (Groups::groupMeta / groupWpRobots).
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
 * Class SEO
 */
class SEO {
    const DASHBOARD_PAGE = 23;
    const RESET_PAGE     = 1389;
    const OLD_GIFT_IDEAS = 77; // redirected to /exchange-gift-ideas-500-pesos/ (Landing::redirects)
    const FACEBOOK       = 'https://www.facebook.com/manitomanitaph';

    const HOME_TITLE = 'Manito Manita: Free Online Bunutan & Secret Santa Generator (PH)';
    const HOME_DESC  = 'Do your Monito Monita, Kris Kringle or online bunutan in 2 minutes. Free forever: wishlists, gift ideas within budget and private matches. No sign-up.';

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        // Homepage metadata (Yoast).
        add_filter( 'wpseo_title', array( __CLASS__, 'title' ), 20 );
        add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'title' ), 20 );
        add_filter( 'wpseo_twitter_title', array( __CLASS__, 'title' ), 20 );
        add_filter( 'wpseo_metadesc', array( __CLASS__, 'description' ), 20 );
        add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'description' ), 20 );
        add_filter( 'wpseo_twitter_description', array( __CLASS__, 'description' ), 20 );
        add_filter( 'wpseo_opengraph_image', array( __CLASS__, 'image' ), 20 );
        add_filter( 'wpseo_twitter_image', array( __CLASS__, 'image' ), 20 );
        add_filter( 'wpseo_twitter_card_type', array( __CLASS__, 'cardType' ), 20 );
        add_action( 'wpseo_add_opengraph_images', array( __CLASS__, 'defaultImage' ) );

        // Utility pages: noindex + not in the sitemap.
        add_filter( 'wpseo_robots', array( __CLASS__, 'robots' ), 20 );
        add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'sitemapExclude' ) );

        // Philippine English.
        add_filter( 'language_attributes', array( __CLASS__, 'lang' ) );
        add_filter( 'wpseo_locale', array( __CLASS__, 'locale' ) );
        add_filter( 'wpseo_og_locale', array( __CLASS__, 'locale' ) ); // Yoast 14+ name for the og:locale filter

        add_action( 'wp_head', array( __CLASS__, 'schema' ), 30 );
        add_action( 'init', array( __CLASS__, 'llms' ), 1 );
    }

    protected static function isHome() {
        return is_front_page() || is_page_template( 'template-homepage.php' );
    }

    public static function title( $title ) {
        return self::isHome() ? self::HOME_TITLE : $title;
    }

    public static function description( $desc ) {
        return self::isHome() ? self::HOME_DESC : $desc;
    }

    public static function imageUrl() {
        return get_template_directory_uri() . '/assets/images/og-manitomanita.jpg';
    }

    public static function image( $img ) {
        // Homepage and any page without its own image get the branded card.
        return ( self::isHome() || ! $img ) ? self::imageUrl() : $img;
    }

    /**
     * Pages without their own image (e.g. landing pages) still get the branded share card.
     */
    public static function defaultImage( $images ) {
        if ( is_object( $images ) && method_exists( $images, 'has_images' ) && ! $images->has_images() ) {
            $images->add_image( array( 'url' => self::imageUrl(), 'width' => 1200, 'height' => 630, 'type' => 'image/jpeg' ) );
        }
    }

    public static function cardType( $type ) {
        return 'summary_large_image';
    }

    public static function robots( $robots ) {
        if ( is_page( self::DASHBOARD_PAGE ) && isset( $_GET['gid'] ) ) return 'noindex, nofollow'; // private group pages
        if ( is_page( array( self::DASHBOARD_PAGE, self::RESET_PAGE ) ) ) return 'noindex, follow';
        return $robots;
    }

    public static function sitemapExclude( $ids ) {
        return array_unique( array_merge( (array) $ids, array( self::DASHBOARD_PAGE, self::RESET_PAGE, self::OLD_GIFT_IDEAS ) ) );
    }

    public static function lang( $output ) {
        return preg_replace( '/lang="[^"]*"/', 'lang="en-PH"', $output );
    }

    public static function locale( $locale ) {
        return 'en_PH';
    }

    /**
     * Homepage: the app itself (free web application) and who runs it.
     */
    public static function schema() {
        if ( ! self::isHome() ) return;

        $home = home_url( '/' );
        $graph = array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'  => 'Organization',
                    '@id'    => $home . '#mm-org',
                    'name'   => 'Manito Manita',
                    'url'    => $home,
                    'logo'   => get_template_directory_uri() . '/assets/images/logo.png',
                    'sameAs' => array_values( wp_list_pluck( Theme::socials(), 1 ) ),
                ),
                array(
                    '@type'               => 'WebApplication',
                    '@id'                 => $home . '#mm-app',
                    'name'                => 'Manito Manita',
                    'alternateName'       => array( 'Monito Monita generator', 'Kris Kringle generator', 'Secret Santa generator', 'Online bunutan' ),
                    'url'                 => $home,
                    'image'               => self::imageUrl(),
                    'description'         => self::HOME_DESC,
                    'applicationCategory' => 'LifestyleApplication',
                    'operatingSystem'     => 'Any (web browser)',
                    'inLanguage'          => 'en-PH',
                    'isAccessibleForFree' => true,
                    'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'PHP' ),
                    'featureList'         => array(
                        'Free name draw for Secret Santa, Kris Kringle and Monito Monita',
                        'Wishlists with links',
                        'Gift ideas within the group budget',
                        'Private match reveal and email notifications',
                        'Exclusions, unshuffle and organizer tools with PRO',
                    ),
                    'publisher'           => array( '@id' => $home . '#mm-org' ),
                ),
            ),
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    /**
     * /llms.txt (https://llmstxt.org): what the site is, for AI search engines.
     */
    public static function llms() {
        $path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        if ( $path !== 'llms.txt' ) return;

        $home = home_url( '/' );
        $stats = class_exists( 'Groups' ) ? Groups::siteStats() : array( 'groups' => 0 );
        $groups = ! empty( $stats['groups'] ) ? number_format( $stats['groups'] ) . '+ groups' : 'thousands of groups';

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );

        echo "# Manito Manita\n\n";
        echo "> Free online Secret Santa, Kris Kringle and Monito Monita (Manito Manita) name generator, made in the Philippines. Used by {$groups}.\n\n";
        echo "Manito Manita lets anyone create a gift exchange group in about 2 minutes, share one link, and shuffle names so every member privately sees who they give a gift to. It is free forever with no sign-up and no app: members join with a name, screen name, email and password for that group.\n\n";
        echo "## Free features\n\n";
        echo "- Unlimited members, fair private shuffle (nobody gets themselves)\n";
        echo "- Wishlists with links (e.g. Lazada, Shopee)\n";
        echo "- Gift ideas within the group's spending minimum (in pesos)\n";
        echo "- Email notifications, exchange-day reminder, comments on wishlists\n\n";
        echo "## PRO (optional, PHP 199 one-time per group)\n\n";
        echo "- Exclusions (couples or family won't get each other)\n";
        echo "- Weekly Drops: an optional themed mini-gift each week with an email to everyone, and a reveal of who gave to whom\n";
        echo "- A co-organizer with their own password, and color themes for the group page\n";
        echo "- Anonymous Q&A: members ask the person they're giving to a question (size, likes, allergies) without revealing who they are\n";
        echo "- Unshuffle to let late joiners in (3 unshuffles included), reshuffle\n";
        echo "- Reminders, auto-shuffle, gift status board, remove or edit members, see matches, export, no ads\n\n";
        echo "## Key pages\n\n";
        echo "- [Home and group creation]({$home})\n";
        echo "- [How it works]({$home}#how-it-works)\n";
        echo "- [Free vs PRO]({$home}#pro)\n";
        echo "- [FAQ]({$home}#faq)\n";
        echo "- [My Wishlist (personal, any occasion)]({$home}my-wishlist/)\n";
        echo "- Help: the \"Ask Manito\" chat on every page answers questions about the site\n\n";
        echo "## Social\n\n";
        foreach ( Theme::socials() as $s ) echo '- [' . $s[0] . '](' . $s[1] . ")\n";
        echo "\n";
        echo "## Terms\n\n";
        echo "Monito Monita / Manito Manita is the Filipino name for Secret Santa, often with weekly themed gifts in December. Kris Kringle is the name used in the Philippines, Australia and Ireland.\n";
        exit;
    }
}

SEO::init();
