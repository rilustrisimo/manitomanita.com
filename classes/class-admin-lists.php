<?php
/**
 * WP Admin lists for members (Users post type) and groups: search by email / names (not just the
 * title), plus Email and Group columns on the members list.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class AdminLists {
    /** Post type => meta keys the admin search also looks in. */
    const SEARCH = array(
        'users'  => array( 'email', 'name', 'screen_name' ),
        'groups' => array( 'your_email', 'your_name', 'group_name' ),
    );

    protected static $hooked = false;

    public static function init() {
        if ( self::$hooked || ! is_admin() ) return;
        self::$hooked = true;

        add_filter( 'posts_search', array( __CLASS__, 'search' ), 10, 2 );
        add_filter( 'manage_users_posts_columns', array( __CLASS__, 'columns' ) );
        add_action( 'manage_users_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
        add_filter( 'manage_groups_posts_columns', array( __CLASS__, 'groupColumns' ) );
        add_action( 'manage_groups_posts_custom_column', array( __CLASS__, 'groupColumn' ), 10, 2 );
        add_filter( 'post_row_actions', array( __CLASS__, 'rowActions' ), 10, 2 );
        add_action( 'admin_head-edit.php', array( __CLASS__, 'css' ) );
    }

    /** Adds "OR the post has a matching email/name" to the admin list search. */
    public static function search( $search, $q ) {
        global $wpdb;

        $type = $q->get( 'post_type' );
        $term = trim( (string) $q->get( 's' ) );
        if ( $search === '' || $term === '' || ! $q->is_main_query() || ! is_string( $type ) || ! isset( self::SEARCH[ $type ] ) ) return $search;

        $keys = "'" . implode( "','", array_map( 'esc_sql', self::SEARCH[ $type ] ) ) . "'";
        $match = $wpdb->prepare(
            "{$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$keys}) AND meta_value LIKE %s)",
            '%' . $wpdb->esc_like( $term ) . '%'
        );

        // WordPress builds " AND (((title LIKE ...) OR ...))": put the meta match inside the same group.
        return preg_replace( '/^\s*AND\s*\(/', ' AND (' . $match . ' OR ', $search, 1 );
    }

    /** The group's page (same link members get), for a new tab. */
    protected static function groupUrl( $gid ) {
        return class_exists( 'Emails' ) ? Emails::groupLink( $gid ) : '';
    }

    public static function rowActions( $actions, $post ) {
        if ( ! in_array( $post->post_type, array( 'users', 'groups' ), true ) ) return $actions;
        $gid = $post->post_type === 'groups' ? (int) $post->ID : (int) get_post_meta( $post->ID, 'groups', true );
        if ( $gid && get_post_type( $gid ) === 'groups' ) {
            $actions['mm_view'] = '<a href="' . esc_url( self::groupUrl( $gid ) ) . '" target="_blank" rel="noopener">View group &#8599;</a>';
        }

        return $actions;
    }

    public static function groupColumns( $cols ) {
        $out = array();
        foreach ( $cols as $k => $v ) {
            $out[ $k ] = $v;
            if ( $k === 'title' ) {
                $out['mm_org'] = 'Organizer';
                $out['mm_pro'] = 'PRO';
                $out['mm_open'] = '';
            }
        }

        return $out;
    }

    public static function groupColumn( $col, $id ) {
        if ( $col === 'mm_org' ) {
            $name = (string) get_post_meta( $id, 'your_name', true );
            $e = (string) get_post_meta( $id, 'your_email', true );
            echo esc_html( $name ) . ( $e !== '' ? '<br><a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '' );
        }
        if ( $col === 'mm_pro' ) {
            echo get_post_meta( $id, 'pro', true ) ? '<span class="mm-al-pro">PRO</span>' : '<span class="mm-al-free">Free</span>';
        }
        if ( $col === 'mm_open' ) {
            echo '<a class="button button-small" href="' . esc_url( self::groupUrl( $id ) ) . '" target="_blank" rel="noopener">Open group &#8599;</a>';
        }
    }

    public static function css() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, array( 'users', 'groups' ), true ) ) return;
        echo '<style>.column-mm_pro{width:70px}.column-mm_open{width:120px;text-align:right}'
            . '.mm-al-pro{display:inline-block;padding:2px 8px;border-radius:999px;background:#D4AF37;color:#1b1b1b;font-size:11px;font-weight:700}'
            . '.mm-al-free{color:#8c8f94;font-size:12px}</style>';
    }

    public static function columns( $cols ) {
        $out = array();
        foreach ( $cols as $k => $v ) {
            $out[ $k ] = $v;
            if ( $k === 'title' ) {
                $out['mm_email'] = 'Email';
                $out['mm_group'] = 'Group';
            }
        }

        return $out;
    }

    public static function column( $col, $id ) {
        if ( $col === 'mm_email' ) {
            $e = (string) get_post_meta( $id, 'email', true );
            echo $e !== '' ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '&mdash;';
            if ( class_exists( 'EmailCheck' ) && $e !== '' && ! EmailCheck::isConfirmed( $id ) ) echo ' <span style="color:#b26200;">(not confirmed)</span>';
        }
        if ( $col === 'mm_group' ) {
            $gid = (int) get_post_meta( $id, 'groups', true );
            echo $gid ? '<a href="' . esc_url( get_edit_post_link( $gid ) ) . '">' . esc_html( (string) get_post_meta( $gid, 'group_name', true ) ) . '</a>' : '&mdash;';
        }
    }
}

AdminLists::init();
