<?php
/**
 * CommentModeration Class. PRO groups: the organizer (or co-organizer, same moderator session) can remove
 * comments on members' cards. Members can't remove comments in any group. In free groups the organizer
 * sees a locked "Remove · PRO" button that opens the PRO offer (data-mm-upsell, Theme.proPurchaseButton).
 *
 * Users::... renders a Remove button on each comment only when CommentModeration::can( $gid ) is true;
 * this class prints the small script for it (only on those pages) and handles the AJAX call
 * (mm_comment_remove), re-checking everything server-side.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package   Eyorsogood
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

class CommentModeration {
    protected static $used = false;

    public static function init() {
        add_action( 'wp_ajax_mm_comment_remove', array( __CLASS__, 'ajaxRemove' ) );
        add_action( 'wp_ajax_nopriv_mm_comment_remove', array( __CLASS__, 'ajaxRemove' ) );
        add_action( 'wp_footer', array( __CLASS__, 'script' ), 30 );
    }

    /** Verified moderator of a PRO group. */
    public static function can( $gid ) {
        $gid = (int) $gid;

        return $gid && get_post_type( $gid ) === 'groups' && get_field( 'pro', $gid ) && Groups::isModerator( $gid );
    }

    /** The Remove button for one comment: working for PRO moderators, locked (PRO offer) for free-group moderators. */
    public static function button( $commentId, $gid ) {
        $gid = (int) $gid;
        if ( ! self::can( $gid ) ) {
            if ( ! $gid || get_post_type( $gid ) !== 'groups' || get_field( 'pro', $gid ) || ! Groups::isModerator( $gid ) ) return '';
            self::$used = true;

            return '<button type="button" class="mm-dcomment__rm is-locked" data-mm-upsell="fix" title="Removing comments is a PRO feature"><i class="fa-solid fa-lock" aria-hidden="true"></i> Remove &middot; PRO</button>';
        }
        self::$used = true;
        self::$used = true;

        return '<button type="button" class="mm-dcomment__rm" data-mm-crm="' . (int) $commentId . '" data-gid="' . (int) $gid . '" data-nonce="' . esc_attr( wp_create_nonce( 'mm_crm_' . (int) $gid ) ) . '" aria-label="Remove this comment"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Remove</button>';
    }

    public static function ajaxRemove() {
        $cid = absint( $_POST['cid'] ?? 0 );
        $gid = absint( $_POST['gid'] ?? 0 );
        if ( ! wp_verify_nonce( (string) ( $_POST['nonce'] ?? '' ), 'mm_crm_' . $gid ) ) wp_send_json_error( 'This page has expired. Please refresh and try again.' );
        if ( ! self::can( $gid ) ) wp_send_json_error( get_field( 'pro', $gid ) ? 'Please open PRO Actions and enter the organizer password again.' : 'Removing comments is a PRO feature.' );

        $c = get_comment( $cid );
        $uid = $c ? (int) $c->comment_post_ID : 0;
        if ( ! $c || get_post_type( $uid ) !== 'users' || (int) get_field( 'groups', $uid, false ) !== $gid ) wp_send_json_error( 'That comment was already removed.' );

        if ( ! wp_delete_comment( $cid, true ) ) wp_send_json_error( 'Could not remove the comment. Please try again.' );
        if ( class_exists( 'Insights' ) ) Insights::log( 'comment_removed', $gid );

        wp_send_json_success( array( 'cid' => $cid, 'uid' => $uid ) );
    }

    /**
     * Click handler for the Remove buttons, on group pages. Member cards arrive by AJAX after the page loads
     * (Users::user_card_generate), so it can't wait for a button to be rendered first. Works in the popup too.
     */
    public static function script() {
        if ( ! self::$used && ! is_page( 23 ) && empty( $_GET['gid'] ) ) return; // 23 = group dashboard
        ?>
<script>
jQuery(function($){
    $(document).on('click', '.mm-dcomment__rm.is-locked', function(){ if (window.Theme && Theme.track) Theme.track('select_promotion', { creative_slot: 'comment-remove', promotion_name: 'pro_upgrade' }); });
    $(document).on('click', '[data-mm-crm]', function(e){
        e.preventDefault();
        var b = $(this), cid = b.attr('data-mm-crm');
        if (!window.confirm('Remove this comment? Everyone in the group will stop seeing it. This can’t be undone.')) return;
        b.prop('disabled', true);
        $.post($('#ajax-url').val() || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { action: 'mm_comment_remove', cid: cid, gid: b.attr('data-gid'), nonce: b.attr('data-nonce') }).done(function(r){
            if (!r || !r.success) { b.prop('disabled', false); window.alert($('<div>').html((r && r.data) || 'Please try again.').text()); return; }
            // Remove it from the popup and from the hidden copy on the member card, then fix the counts.
            $('[data-cid="' + cid + '"]').each(function(){
                var list = $(this).closest('.mm-dcomments'), sec = $(this).closest('.mm-detail__col');
                $(this).remove();
                var n = list.children('li').length, count = sec.find('.mm-detail__count');
                if (n) count.text(n); else { count.remove(); list.replaceWith('<div class="mm-detail__empty"><div class="mm-detail__empty-text"><i class="far fa-comment-dots"></i><div><strong>No comments yet</strong><span>The comment was removed.</span></div></div></div>'); }
            });
            var meta = $('[data-mm-member="' + r.data.uid + '"] .mm-member__meta').first();
            meta.text(meta.text().replace(/ · (\d+) comments?/, function(m, k){ k = parseInt(k, 10) - 1; return k > 0 ? ' · ' + k + (k === 1 ? ' comment' : ' comments') : ''; }));
            if (window.Theme && Theme.toast) Theme.toast('Comment removed');
        }).fail(function(){ b.prop('disabled', false); window.alert('Network error. Please try again.'); });
    });
});
</script>
        <?php
    }
}

CommentModeration::init();
