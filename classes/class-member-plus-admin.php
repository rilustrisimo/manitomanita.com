<?php
/**
 * MemberPlusAdmin: Member+ in WP Admin.
 *
 * - Members > Member+: everyone who has it (paid or given), with Remove, and a form to give it to any
 *   member by member ID or email, free.
 * - Members > All: a Member+ column, Give / Remove row actions and a "Member+" view.
 * Giving and removing go through MemberPlus::grant() / revoke() (logged in Insights as mp_grant / mp_revoke).
 * Removing never refunds; refunds are done in PayPal.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class MemberPlusAdmin {
    const PAGE = 'mm-member-plus';

    public static function init() {
        if ( ! is_admin() ) return;
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_mm_mplus_admin', array( __CLASS__, 'handle' ) );
        add_filter( 'manage_users_posts_columns', array( __CLASS__, 'columns' ), 20 );
        add_action( 'manage_users_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
        add_filter( 'post_row_actions', array( __CLASS__, 'rowActions' ), 20, 2 );
        add_filter( 'views_edit-users', array( __CLASS__, 'views' ) );
        add_action( 'pre_get_posts', array( __CLASS__, 'filter' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
    }

    public static function menu() {
        add_submenu_page( 'edit.php?post_type=users', 'Member+', 'Member+', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
    }

    protected static function actionUrl( $do, $uid, $back = '' ) {
        return wp_nonce_url( admin_url( 'admin-post.php?action=mm_mplus_admin&do=' . $do . '&uid=' . (int) $uid . ( $back ? '&back=' . rawurlencode( $back ) : '' ) ), 'mm_mplus_admin_' . (int) $uid );
    }

    /* ------------------------------------------------------------------ Give / remove */

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
        $do = sanitize_key( (string) ( $_REQUEST['do'] ?? '' ) );
        $uid = absint( $_REQUEST['uid'] ?? 0 );

        // The "Give Member+" form: a member ID or an email (if the email is in several groups, list them).
        if ( $do === 'give-form' ) {
            check_admin_referer( 'mm_mplus_admin_form' );
            $q = trim( wp_unslash( (string) ( $_POST['who'] ?? '' ) ) );
            $ids = ctype_digit( $q ) ? array( (int) $q ) : self::byEmail( $q );
            $ids = array_values( array_filter( $ids, function ( $id ) { return get_post_type( $id ) === 'users'; } ) );
            if ( count( $ids ) !== 1 ) self::back( count( $ids ) ? 'many&who=' . rawurlencode( $q ) : 'none' );
            $uid = $ids[0];
            $do = 'give';
        } else {
            check_admin_referer( 'mm_mplus_admin_' . $uid );
        }
        if ( get_post_type( $uid ) !== 'users' || ! class_exists( 'MemberPlus' ) ) self::back( 'none' );

        $by = 'admin:' . wp_get_current_user()->user_login;
        if ( $do === 'give' ) $msg = MemberPlus::has( $uid ) ? 'already' : ( MemberPlus::grant( $uid, $by ) ? 'given' : 'none' );
        elseif ( $do === 'remove' ) $msg = MemberPlus::revoke( $uid, $by ) ? 'removed' : 'none';
        else $msg = 'none';

        self::back( $msg . '&m=' . $uid );
    }

    protected static function back( $msg ) {
        $to = (string) ( $_REQUEST['back'] ?? '' );
        $to = $to !== '' ? $to : admin_url( 'edit.php?post_type=users&page=' . self::PAGE );
        wp_safe_redirect( $to . ( strpos( $to, '?' ) !== false ? '&' : '?' ) . 'mplus=' . $msg );
        exit;
    }

    protected static function byEmail( $email ) {
        $email = strtolower( sanitize_email( $email ) );
        if ( $email === '' ) return array();

        return array_map( 'intval', get_posts( array( 'post_type' => 'users', 'post_status' => 'any', 'posts_per_page' => 20, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'email', 'value' => $email ) ) ) ) );
    }

    public static function notice() {
        if ( ! isset( $_GET['mplus'] ) || ! current_user_can( 'manage_options' ) ) return;
        $m = (int) ( $_GET['m'] ?? 0 );
        $who = $m ? ' for <strong>' . esc_html( (string) get_post_meta( $m, 'screen_name', true ) ) . '</strong> (#' . $m . ')' : '';
        $list = array(
            'given'   => array( 'success', 'Member+ is on' . $who . '. Their card shows it right away; no email was sent.' ),
            'removed' => array( 'success', 'Member+ is off' . $who . '. Their look is kept if you give it back. Paid? Refund it in PayPal.' ),
            'already' => array( 'info', 'That member already has Member+.' ),
            'none'    => array( 'error', 'No member found. Use the member ID (Members list) or the exact email.' ),
            'many'    => array( 'warning', 'That email is in more than one group. Use the member ID instead (shown below).' ),
        );
        $k = sanitize_key( (string) $_GET['mplus'] );
        if ( ! isset( $list[ $k ] ) ) return;
        echo '<div class="notice notice-' . $list[ $k ][0] . ' is-dismissible"><p>' . $list[ $k ][1] . '</p></div>';
    }

    /* ------------------------------------------------------------------ Members > Member+ */

    protected static function source( $uid ) {
        $by = (string) get_post_meta( $uid, MemberPlus::SOURCE, true );
        $orders = array_filter( (array) get_post_meta( $uid, MemberPlus::ORDERS, true ) );
        if ( $by === 'paid' || ( $by === '' && $orders ) ) return array( 'Paid', implode( ', ', $orders ) );
        if ( strpos( $by, 'admin:' ) === 0 ) return array( 'Given by ' . substr( $by, 6 ), $orders ? 'Earlier order: ' . implode( ', ', $orders ) : '' );

        return array( 'Given', '' );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'MemberPlus' ) ) return;
        $ids = get_posts( array( 'post_type' => 'users', 'post_status' => 'any', 'posts_per_page' => 500, 'fields' => 'ids', 'meta_key' => MemberPlus::META, 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_query' => array( array( 'key' => MemberPlus::META, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ) ) );
        $paid = 0;
        foreach ( $ids as $id ) if ( self::source( $id )[0] === 'Paid' ) $paid++;

        echo '<div class="wrap"><h1>Member+</h1>';
        echo '<p>Members with Member+ on their card: <strong>' . count( $ids ) . '</strong> (' . $paid . ' paid, ' . ( count( $ids ) - $paid ) . ' given). Removing turns the perks off right away and keeps their look; it never refunds (refund paid ones in PayPal).</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;margin:16px 0;flex-wrap:wrap">';
        wp_nonce_field( 'mm_mplus_admin_form' );
        echo '<input type="hidden" name="action" value="mm_mplus_admin"><input type="hidden" name="do" value="give-form">';
        echo '<label for="mm-mp-who"><strong>Give Member+ for free:</strong></label> <input id="mm-mp-who" type="text" name="who" class="regular-text" placeholder="Member ID or email" value="' . esc_attr( (string) ( $_GET['who'] ?? '' ) ) . '" required> ';
        echo '<button class="button button-primary">Give Member+</button></form>';

        // An email in several groups: show its cards to pick from.
        if ( ! empty( $_GET['who'] ) ) {
            $many = self::byEmail( wp_unslash( (string) $_GET['who'] ) );
            if ( count( $many ) > 1 ) {
                echo '<table class="widefat striped" style="max-width:900px;margin-bottom:20px"><thead><tr><th>Member</th><th>Group</th><th></th></tr></thead><tbody>';
                foreach ( $many as $id ) {
                    $gid = (int) get_post_meta( $id, 'groups', true );
                    echo '<tr><td>#' . $id . ' ' . esc_html( (string) get_post_meta( $id, 'screen_name', true ) ) . '</td><td>' . esc_html( (string) get_post_meta( $gid, 'group_name', true ) ) . '</td><td>'
                        . ( MemberPlus::has( $id ) ? 'Has Member+' : '<a class="button" href="' . esc_url( self::actionUrl( 'give', $id ) ) . '">Give Member+</a>' ) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        }

        if ( ! $ids ) { echo '<p><em>No one has Member+ yet.</em></p></div>'; return; }

        $themes = MemberPlus::themes();
        $badges = MemberPlus::badges();
        echo '<table class="widefat striped"><thead><tr><th>Since</th><th>Screen name</th><th>Name</th><th>Email</th><th>Group</th><th>How</th><th>Look</th><th></th></tr></thead><tbody>';
        foreach ( $ids as $id ) {
            $gid = (int) get_post_meta( $id, 'groups', true );
            $src = self::source( $id );
            $l = MemberPlus::look( $id );
            $look = ( $l['theme'] ? esc_html( $themes[ $l['theme'] ][0] ) : 'No color' ) . ( $l['badge'] ? ' ' . $badges[ $l['badge'] ] : '' ) . ( $l['frame'] ? ' &middot; frame' : '' );
            echo '<tr>'
                . '<td>' . esc_html( wp_date( 'M j, Y g:i A', (int) get_post_meta( $id, MemberPlus::META, true ) ) ) . '</td>'
                . '<td><a href="' . esc_url( get_edit_post_link( $id ) ) . '"><strong>' . esc_html( (string) get_post_meta( $id, 'screen_name', true ) ) . '</strong></a> <span style="color:#8c8f94">#' . $id . '</span></td>'
                . '<td>' . esc_html( (string) get_post_meta( $id, 'name', true ) ) . '</td>'
                . '<td>' . esc_html( (string) get_post_meta( $id, 'email', true ) ) . '</td>'
                . '<td>' . ( $gid ? '<a href="' . esc_url( get_edit_post_link( $gid ) ) . '">' . esc_html( (string) get_post_meta( $gid, 'group_name', true ) ) . '</a>' . ( class_exists( 'Emails' ) ? ' <a href="' . esc_url( Emails::groupLink( $gid ) ) . '" target="_blank" rel="noopener" title="Open the group page">&#8599;</a>' : '' ) : '&mdash;' ) . '</td>'
                . '<td>' . esc_html( $src[0] ) . ( $src[1] !== '' ? '<br><small style="color:#8c8f94">' . esc_html( $src[1] ) . '</small>' : '' ) . '</td>'
                . '<td>' . $look . '</td>'
                . '<td><a class="button button-link-delete" href="' . esc_url( self::actionUrl( 'remove', $id ) ) . '" onclick="return confirm(\'Remove Member+ from this card? It does not refund.\')">Remove</a></td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';
    }

    /* ------------------------------------------------------------------ Members > All */

    public static function columns( $cols ) {
        $cols['mm_plus'] = 'Member+';

        return $cols;
    }

    public static function column( $col, $id ) {
        if ( $col !== 'mm_plus' || ! class_exists( 'MemberPlus' ) ) return;
        if ( ! MemberPlus::has( $id ) ) { echo '<span class="mm-al-free">&mdash;</span>'; return; }
        $src = self::source( $id );
        echo '<span class="mm-al-pro" title="' . esc_attr( trim( $src[0] . ' ' . $src[1] ) ) . '">' . ( $src[0] === 'Paid' ? 'Paid' : 'Given' ) . '</span>';
    }

    public static function rowActions( $actions, $post ) {
        if ( $post->post_type !== 'users' || ! class_exists( 'MemberPlus' ) || ! current_user_can( 'manage_options' ) ) return $actions;
        $back = admin_url( 'edit.php?post_type=users' . ( ! empty( $_GET['mm_plus'] ) ? '&mm_plus=1' : '' ) );
        $actions['mm_plus'] = MemberPlus::has( $post->ID )
            ? '<a href="' . esc_url( self::actionUrl( 'remove', $post->ID, $back ) ) . '" style="color:#b32d2e" onclick="return confirm(\'Remove Member+ from this card? It does not refund.\')">Remove Member+</a>'
            : '<a href="' . esc_url( self::actionUrl( 'give', $post->ID, $back ) ) . '">Give Member+</a>';

        return $actions;
    }

    public static function views( $views ) {
        if ( ! class_exists( 'MemberPlus' ) ) return $views;
        global $wpdb;
        $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0", MemberPlus::META ) );
        $views['mm_plus'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=users&mm_plus=1' ) ) . '"' . ( ! empty( $_GET['mm_plus'] ) ? ' class="current"' : '' ) . '>Member+ <span class="count">(' . $n . ')</span></a>';

        return $views;
    }

    public static function filter( $q ) {
        if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== 'users' || empty( $_GET['mm_plus'] ) || ! class_exists( 'MemberPlus' ) ) return;
        $mq = (array) $q->get( 'meta_query' );
        $mq[] = array( 'key' => MemberPlus::META, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
        $q->set( 'meta_query', $mq );
    }
}

MemberPlusAdmin::init();
