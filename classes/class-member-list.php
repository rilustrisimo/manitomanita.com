<?php
/**
 * MemberList: the members list on the group page, built for big groups.
 *
 * - The page loads light rows only (screen name, wish and comment counts, the action buttons). Each member's
 *   wishlist and comments load when their card is opened (mm_member_detail), so a 150-member group no longer
 *   sends every wishlist and comment up front.
 * - All members' data comes from a few batched queries (one meta cache fill, one comment count query).
 * - Order is mixed per group and the same on every visit: never the join order (that would hint who is
 *   behind each screen name), and people don't lose their place on refresh.
 * - Both endpoints need the group page key (Users::cardsKey), printed only after the group password check.
 *
 * Front end: assets/js/mm-members.js (details on open, "Show all", skeletons). Theme.js keeps calling
 * user_card_generate, which now answers from here with the same response shape.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MemberList {
    const FIRST = 40; // rows shown before "Show all"

    public static function init() {
        add_action( 'wp_ajax_mm_member_detail', array( __CLASS__, 'ajaxDetail' ) );
        add_action( 'wp_ajax_nopriv_mm_member_detail', array( __CLASS__, 'ajaxDetail' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
    }

    public static function assets() {
        if ( ! is_page( 23 ) ) return; // group dashboard
        $f = '/assets/js/mm-members.js';
        wp_enqueue_script( 'mm-members', get_template_directory_uri() . $f, array( 'jquery' ), (string) @filemtime( get_template_directory() . $f ), true );
    }

    public static function validKey( $gid ) {
        $key = Users::cardsKey( $gid );

        return $gid && get_post_type( $gid ) === 'groups' && $key !== '' && hash_equals( $key, (string) ( $_POST['key'] ?? '' ) );
    }

    /** Published members of a group, in the group's fixed mixed order, with their meta cached in one query. */
    public static function ids( $gid ) {
        $ids = get_posts( array(
            'post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => array( array( 'key' => 'groups', 'value' => (int) $gid ) ),
            'no_found_rows' => true, 'update_post_term_cache' => false,
        ) );
        $ids = array_map( 'intval', (array) $ids );
        if ( ! $ids ) return array();
        update_meta_cache( 'post', $ids );
        $salt = wp_salt( 'auth' );
        usort( $ids, function ( $a, $b ) use ( $gid, $salt ) {
            return strcmp( md5( $gid . '|' . $a . '|' . $salt ), md5( $gid . '|' . $b . '|' . $salt ) );
        } );

        return $ids;
    }

    /** uid => number of comments (approved or held, like get_comments' default), in one query. */
    public static function commentCounts( array $ids ) {
        global $wpdb;
        if ( ! $ids ) return array();
        $out = array();
        $in = implode( ',', array_map( 'intval', $ids ) );
        foreach ( (array) $wpdb->get_results( "SELECT comment_post_ID p, COUNT(*) n FROM {$wpdb->comments} WHERE comment_post_ID IN ($in) AND comment_approved IN ('0','1') GROUP BY comment_post_ID" ) as $r ) {
            $out[ (int) $r->p ] = (int) $r->n;
        }

        return $out;
    }

    public static function meta( $wishes, $comments ) {
        $m = $wishes ? $wishes . ' ' . ( $wishes == 1 ? 'wish' : 'wishes' ) : 'No wishlist yet';
        if ( $comments ) $m .= ' · ' . $comments . ' ' . ( $comments == 1 ? 'comment' : 'comments' );

        return $m;
    }

    /**
     * Everything the page needs, as array( cards html, member count, members with a wishlist, who joined html ).
     */
    public static function build( $gid ) {
        $gid = (int) $gid;
        $ids = self::ids( $gid );
        $counts = self::commentCounts( $ids );
        $matched = get_field( 'matched', $gid );
        $html = '';
        $withWish = 0;
        $joined = array();

        foreach ( $ids as $i => $uid ) {
            $screen = (string) get_field( 'screen_name', $uid );
            $wishes = (int) get_post_meta( $uid, 'my_wishlists', true ); // ACF repeater: the row count
            if ( $wishes ) $withWish++;
            $html .= self::row( $uid, $screen, self::meta( $wishes, $counts[ $uid ] ?? 0 ), $matched, $i >= self::FIRST );

            // Who joined: real names only, A-Z (see Users: never screen names or this order)
            $full = trim( (string) get_field( 'name', $uid ) );
            $full = $full !== '' ? $full : 'Member';
            $joined[] = array( mb_strtolower( $full ), '<li class="mm-joined__item" data-name="' . esc_attr( mb_strtolower( $full ) ) . '"><span class="mm-avatar" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( $full, 0, 1 ) ) ) . '</span><span class="mm-joined__text"><strong>' . esc_html( $full ) . '</strong></span></li>' );
        }

        if ( count( $ids ) > self::FIRST ) {
            $html .= '<button type="button" class="mm-members-more" data-mm-more>Show all ' . count( $ids ) . ' members</button>';
        }

        usort( $joined, function ( $a, $b ) { return strcmp( $a[0], $b[0] ); } );
        // Under 3 members the real names could be matched to the screen names, so send none.
        $who = count( $joined ) >= 3 ? implode( '', array_column( $joined, 1 ) ) : '';

        return array( $html, count( $ids ), $withWish, $who );
    }

    /** One light row: the details stay empty until the card is opened. */
    protected static function row( $uid, $screen, $meta, $matched, $more ) {
        $matchattr  = ! $matched ? 'disabled class="mm-match-btn" title="Available once the organizer shuffles the group"' : 'class="user-action match-btn mm-match-btn" user-data="' . $uid . '" data-action="see-match"';
        $leaveattr  = $matched ? 'disabled class="mm-leave-btn mm-popup-only" title="Members can\'t leave after the group is shuffled"' : 'class="user-action leave-btn mm-leave-btn mm-popup-only" user-data="' . $uid . '" data-action="leave-group"';

        $plus = class_exists( 'MemberPlus' ); // Member+ look (screen-name card only, never Who Joined) and its chip

        return '<div class="card mm-member' . ( $more ? ' is-more' : '' ) . ( $plus ? MemberPlus::cardClasses( $uid ) : '' ) . '" data-mm-member="' . $uid . '">'
            . '<div class="card-header" id="heading' . $uid . '"><h2 class="mb-0"><button class="btn btn-link mm-member-open" type="button" aria-haspopup="dialog">'
            . '<span class="mm-avatar" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( trim( $screen ), 0, 1 ) ) ) . ( $plus ? MemberPlus::badgeHtml( $uid ) : '' ) . '</span>'
            . '<span class="mm-member__text"><span class="mm-member__name">' . esc_html( $screen ) . '</span><span class="mm-member__meta">' . esc_html( $meta ) . '</span></span>'
            . '</button>' . ( $plus ? MemberPlus::chip( $uid ) : '' ) . '</h2></div>'
            . '<div class="mm-member-details" hidden data-mm-details="0"></div>'
            . '<div class="user-action-btns">'
            . '<a href="#" class="user-action wish-btn" user-data="' . $uid . '" data-action="edit-wishlist"><i class="fas fa-gift"></i> Edit Wishlist</a>'
            . '<a href="javascript:;" ' . $matchattr . '><i class="fas fa-user-friends"></i> See Match</a>'
            . '<button type="button" class="mm-view-btn"><i class="far fa-eye"></i> View wishlist</button>'
            . '<a href="#" class="user-action comment-btn mm-popup-only" user-data="' . $uid . '"><i class="far fa-comment"></i> Comment</a>'
            . '<a href="javascript:;" ' . $leaveattr . '><i class="fas fa-sign-out-alt"></i> Leave Group</a>'
            . '</div>'
            . '<span class="mm-member__chevron" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>'
            . '</div>';
    }

    /** One member's wishlist and comments (the popup body). */
    public static function details( $uid, $gid ) {
        $screen = (string) get_field( 'screen_name', $uid );
        $wishlist = get_field( 'my_wishlists', $uid );
        $comments = get_comments( array( 'post_id' => $uid, 'orderby' => 'comment_date', 'order' => 'DESC' ) );
        $h = '<div class="mm-detail">';

        $h .= '<section class="mm-detail__col"><h2>Wish<span>List</span>' . ( $wishlist ? '<em class="mm-detail__count">' . count( $wishlist ) . '</em>' : '' ) . '</h2>';
        $h .= apply_filters( 'mm_wishlist_profile', '', (int) $uid ); // Wishlist+ (Member+)
        $wishlist = apply_filters( 'mm_wishlist_view', $wishlist, (int) $uid );
        if ( $wishlist ) {
            $h .= '<ol class="mm-dwish">';
            foreach ( $wishlist as $i => $w ) {
                $h .= '<li class="mm-dwish__item"><span class="mm-dwish__num">' . ( $i + 1 ) . '</span><div class="mm-dwish__body">';
                $h .= '<div class="wish-content">' . Affiliate::linkify( $w['wishlist_description'], 'members-wishlist', $gid ) . '</div>' . apply_filters( 'mm_wish_extra', '', $w, (int) $uid );
                if ( ! empty( $w['reference_links'] ) ) {
                    $h .= '<div class="wish-links">';
                    foreach ( $w['reference_links'] as $k => $l ) $h .= Affiliate::referenceLink( $l['link_url'], $k, 'members-wishlist', $gid );
                    $h .= '</div>';
                }
                $h .= '</div></li>';
            }
            $h .= '</ol>';
        } else {
            $h .= '<div class="mm-detail__empty"><div class="mm-detail__empty-text"><i class="fas fa-gifts"></i><div><strong>No wishlist yet</strong><span>' . esc_html( $screen ) . ' hasn&rsquo;t added any items.</span></div></div>'
                . Affiliate::shopButtonsHtml( $gid, 'members-empty-wishlist', '', 'Need gift ideas?' ) . '</div>';
        }
        $h .= '</section>';

        $h .= '<section class="mm-detail__col"><h2>Comment<span>s</span>' . ( $comments ? '<em class="mm-detail__count">' . count( $comments ) . '</em>' : '' ) . '</h2>';
        if ( ! $comments ) {
            $h .= '<div class="mm-detail__empty"><div class="mm-detail__empty-text"><i class="far fa-comment-dots"></i><div><strong>No comments yet</strong><span>Tap <b>Comment</b> to be the first to say something.</span></div></div></div>';
        } else {
            $h .= '<ul class="mm-dcomments">';
            foreach ( $comments as $c ) {
                $t = strtotime( $c->comment_date );
                $h .= '<li class="mm-dcomment" data-cid="' . (int) $c->comment_ID . '"><div class="com-content">' . Affiliate::linkifyText( $c->comment_content, 'members-comment', $gid ) . '</div>'
                    . '<div class="mm-dcomment__foot"><time class="com-date" datetime="' . esc_attr( date( 'c', $t ) ) . '">' . date( 'M j, Y · g:i a', $t ) . '</time>'
                    . ( class_exists( 'CommentModeration' ) ? CommentModeration::button( $c->comment_ID, $gid ) : '' ) . '</div></li>';
            }
            $h .= '</ul>';
        }
        $h .= '</section></div>';

        return array( 'html' => $h, 'meta' => self::meta( $wishlist ? count( $wishlist ) : 0, count( $comments ) ) );
    }

    public static function ajaxDetail() {
        $gid = absint( $_POST['gid'] ?? 0 );
        $uid = absint( $_POST['uid'] ?? 0 );
        if ( ! self::validKey( $gid ) ) wp_send_json_error( 'reload' );
        if ( get_post_type( $uid ) !== 'users' || get_post_status( $uid ) !== 'publish' || (int) get_field( 'groups', $uid, false ) !== $gid ) wp_send_json_error( 'This member is no longer in the group.' );

        wp_send_json_success( self::details( $uid, $gid ) );
    }
}

MemberList::init();
