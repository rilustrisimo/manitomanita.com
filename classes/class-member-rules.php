<?php
/**
 * MemberRules Class. One person, one spot per group:
 *  - Joining with an email that is already a confirmed member of the group is refused (join form validation).
 *    An unconfirmed earlier try doesn't block: people who lost the confirmation email can join again.
 *  - On confirm (EmailCheck::confirmLink): if that email was confirmed in the meantime, the new copy is
 *    dropped; otherwise older unconfirmed copies of the same email in the group are removed.
 * The same email can still be in any number of different groups.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MemberRules {
    const EMAIL_FIELD = 'field_5f55f1d576677'; // "Your email" on the Join Group form

    public static function init() {
        add_filter( 'acf/validate_value/key=' . self::EMAIL_FIELD, array( __CLASS__, 'validateJoin' ), 20, 4 );
    }

    /** Members of a group with this email (case-insensitive), by status: array( 'publish' => ids, 'pending' => ids ). */
    public static function find( $gid, $email, $except = 0 ) {
        $email = strtolower( trim( (string) $email ) );
        $out = array( 'publish' => array(), 'pending' => array() );
        if ( ! $gid || $email === '' ) return $out;

        $ids = get_posts( array( 'post_type' => 'users', 'post_status' => array( 'publish', 'pending' ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'groups', 'value' => (int) $gid ) ) ) );
        foreach ( $ids as $uid ) {
            if ( (int) $uid === (int) $except ) continue;
            if ( strtolower( trim( (string) get_post_meta( $uid, 'email', true ) ) ) !== $email ) continue;
            $st = get_post_status( $uid );
            if ( isset( $out[ $st ] ) ) $out[ $st ][] = (int) $uid;
        }

        return $out;
    }

    public static function validateJoin( $valid, $value, $field, $input ) {
        if ( $valid !== true || ( $_POST['_acf_post_id'] ?? '' ) !== 'new_post' ) return $valid; // only new joins
        $gid = (int) ( $_SESSION['groupid'] ?? 0 );
        if ( ! $gid || get_post_type( $gid ) !== 'groups' ) return $valid;

        if ( self::find( $gid, $value )['publish'] ) {
            return __( 'This email is already a member of this group. Find your name in the member list and use your password, or tap &ldquo;Forgot password&rdquo; to get a new one.', 'acf' );
        }

        return $valid;
    }

    /**
     * A member confirmed their email. Returns false when the same email is already a confirmed member
     * (this copy is then removed); true when they may join (older unconfirmed copies are removed).
     */
    public static function onConfirm( $uid, $gid ) {
        $email = (string) get_post_meta( $uid, 'email', true );
        $found = self::find( $gid, $email, $uid );
        if ( $found['publish'] ) {
            wp_delete_post( $uid, true );
            return false;
        }
        foreach ( $found['pending'] as $old ) wp_delete_post( $old, true );

        return true;
    }
}

MemberRules::init();
