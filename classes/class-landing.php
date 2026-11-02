<?php
/**
 * * Landing Class. Helpers for pages using the "Landing" template (template-landing.php): generator
 * pages, gift guides, theme lists and legal pages. Content lives in WordPress (editor + ACF fields
 * from includes/acf-json/group_mm_landing.json); Yoast handles titles, descriptions, social tags,
 * breadcrumbs and the sitemap. This class adds the FAQ structured data and old-URL redirects.
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
 * Class Landing
 */
class Landing {
    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked ) return;
        self::$hooked = true;

        add_action( 'template_redirect', array( __CLASS__, 'redirects' ), -1 );
        add_action( 'wp_head', array( __CLASS__, 'faqSchema' ), 40 );
    }

    public static function isActive() {
        return is_page_template( 'template-landing.php' );
    }

    /**
     * Old URLs that moved (301 keeps their search ranking). /gift-ideas/ was the old Lazada feed.
     */
    public static function redirects() {
        $moved = array( 'gift-ideas' => 'exchange-gift-ideas-500-pesos' );
        $slug = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

        if ( isset( $moved[ $slug ] ) ) {
            wp_redirect( home_url( '/' . $moved[ $slug ] . '/' ), 301 );
            exit;
        }
    }

    protected static function lines( $text ) {
        return array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $text ) ) ) );
    }

    /**
     * Everything the template needs for one page, from its fields.
     */
    public static function fromPage( $id ) {
        $id = (int) $id;
        $f = function ( $name ) use ( $id ) { return function_exists( 'get_field' ) ? get_field( $name, $id ) : ''; };

        $groups = array();
        foreach ( (array) $f( 'mm_groups' ) as $g ) {
            $ideas = array();
            foreach ( self::lines( $g['ideas'] ?? '' ) as $line ) {
                $parts = array_map( 'trim', explode( '|', $line, 2 ) );
                $ideas[] = array( $parts[0], $parts[1] ?? $parts[0] );
            }
            if ( $ideas ) $groups[] = array( (string) ( $g['title'] ?? '' ), $ideas );
        }

        $lists = array();
        foreach ( (array) $f( 'mm_lists' ) as $l ) {
            $items = self::lines( $l['items'] ?? '' );
            if ( $items ) $lists[] = array( (string) ( $l['title'] ?? '' ), $items );
        }

        $faq = array();
        foreach ( (array) $f( 'mm_faq' ) as $q ) {
            if ( ! empty( $q['question'] ) ) $faq[] = array( (string) $q['question'], (string) ( $q['answer'] ?? '' ) );
        }

        $post = get_post( $id );

        return array(
            'id'      => $id,
            'slug'    => $post ? $post->post_name : '',
            'type'    => (string) ( $f( 'mm_type' ) ?: 'tool' ),
            'eyebrow' => (string) $f( 'mm_eyebrow' ),
            'h1'      => (string) ( $f( 'mm_h1' ) ?: get_the_title( $id ) ),
            'lead'    => (string) $f( 'mm_lead' ),
            'intro'   => array(
                'title' => (string) $f( 'mm_intro_title' ),
                'body'  => $post ? apply_filters( 'the_content', $post->post_content ) : '',
            ),
            'budget'  => (int) $f( 'mm_budget' ),
            'groups'  => $groups,
            'lists'   => $lists,
            'faq'     => $faq,
            'related' => array_map( 'intval', array_filter( (array) $f( 'mm_related' ) ) ),
            'updated' => (string) ( $f( 'mm_updated' ) ?: ( $post ? get_the_modified_date( 'Y-m-d', $id ) : '' ) ),
        );
    }

    /**
     * Legal pages: give each <h2> an id and build the contents list.
     */
    public static function legalContent( $html ) {
        $toc = array();
        $n = 0;
        $html = preg_replace_callback( '~<h2([^>]*)>(.*?)</h2>~is', function ( $m ) use ( &$toc, &$n ) {
            $n++;
            $toc[] = array( 's' . $n, trim( wp_strip_all_tags( preg_replace( '/^\s*\d+\.\s*/', '', $m[2] ) ) ) );
            return '<h2 id="s' . $n . '"' . $m[1] . '>' . $m[2] . '</h2>';
        }, $html );

        return array( $html, $toc );
    }

    /**
     * FAQPage structured data for landing pages with questions (Yoast covers WebPage + breadcrumbs).
     */
    public static function faqSchema() {
        if ( ! self::isActive() ) return;

        $p = self::fromPage( get_queried_object_id() );
        if ( ! $p['faq'] ) return;

        $faq = array_map( function ( $q ) {
            return array( '@type' => 'Question', 'name' => html_entity_decode( wp_strip_all_tags( $q[0] ), ENT_QUOTES, 'UTF-8' ),
                'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( wp_strip_all_tags( $q[1] ), ENT_QUOTES, 'UTF-8' ) ) );
        }, $p['faq'] );

        echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}

Landing::init();
