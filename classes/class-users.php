<?php
/**
 * * Users Class. Classes and functions for Manito Manita.
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
 * Class Users
 */
class Users extends Theme {
	/**
	 * Constructor runs when this class instantiates.
	 *
	 * @param array $config Data via config file.
	 */
	public function __construct( array $config = array() ) {
        $this->initActions();
        $this->initFilters();
    }

    protected function initActions() {
        /**
         * 
         * function should be public when adding to an action hook.
         */        

        add_action('acf/save_post', array($this, 'my_save_post'));
        add_action( 'wp_ajax_verify_password', array($this, 'verify_password') );
        add_action( 'wp_ajax_nopriv_verify_password', array($this, 'verify_password') ); 
        add_action( 'wp_ajax_load_popup_content', array($this, 'load_popup_content') );
        add_action( 'wp_ajax_nopriv_load_popup_content', array($this, 'load_popup_content') ); 
        add_action( 'wp_ajax_user_leave_group', array($this, 'user_leave_group') );
        add_action( 'wp_ajax_nopriv_user_leave_group', array($this, 'user_leave_group') ); 
        add_action( 'wp_ajax_pass_generate', array($this, 'pass_generate') );
        add_action( 'wp_ajax_nopriv_pass_generate', array($this, 'pass_generate') ); 

        add_action( 'wp_ajax_save_wishlist', array($this, 'save_wishlist') );
        add_action( 'wp_ajax_nopriv_save_wishlist', array($this, 'save_wishlist') );

        add_action( 'wp_ajax_user_card_generate', array($this, 'user_card_generate') );
        add_action( 'wp_ajax_nopriv_user_card_generate', array($this, 'user_card_generate') ); 

    }

    protected function initFilters() {
        /**
         * Place filters here
         */
    }

    public function user_card_generate() {
        //$userids = $_POST['userid'];
        $groupid = $_POST['groupid'];
        $userids = $this->getAllUsersPerGroup2($groupid);

        list($cards, $wishlist, $whojoined) = $this->generateCardUsers($userids, $groupid);

        $memcount = (!$userids)?0:count($userids);

       wp_send_json_success(array($groupid, $cards, $memcount, $wishlist, $whojoined));
       //wp_send_json_success($userids);
    }

    public function verify_password(){
        $password = $_POST['password'];
        $uid = $_POST['uid'];

        $hashed = get_field('password', $uid);

		if(wp_check_password($password, $hashed, $uid)) {
		    $result = true;

            // Lets save_wishlist() trust this member for the rest of the session.
            if(get_post_type($uid) == 'users') $_SESSION['mm_verified'][(int) $uid] = time();
		} else {
		    $result = false;
        }
        
        $posttype = get_post_type($uid);

        $name = ($posttype == "groups")?get_field('group_name', $uid):get_field('screen_name', $uid);
        
        wp_send_json_success(array($result, $name));
    }

    public function load_popup_content(){
        $uid = $_POST['uid'];
        $form = $_POST['form'];

        if($form){
            if(trim($form) == "9999"): //see match
                $this->generateSeeMatchElements($uid);
            elseif(trim($form) == "125"): //edit wishlist
                $this->generateWishlistEditor($uid);
            elseif(trim($form) == "99999"): //leave group
                echo "<h2>Are you sure you want to leave group?</h2><div class='leave-btns'><a href='#' data-action='false'>No</a><a href='#' data-action='true'>Yes</a></div>";
            else:
                $group = new Groups();
                $creds = $group->getGroupCredentials();

                parent::updateAcfForm($uid, $form, 'Update', 'group-dashboard/?gid='.$creds[0].'&pw='.$creds[1]);
            endif;
        }
    }

    public function user_leave_group(){
        $uid = $_POST['uid'];

        $result = wp_delete_post($uid);

        wp_send_json_success($result);
    }

    const WISH_MAX_ITEMS = 20;
    const WISH_MAX_LINKS = 5;
    const WISH_MAX_TEXT  = 500;

    protected function isVerifiedMember($uid){
        $t = (isset($_SESSION['mm_verified'][(int) $uid]))?(int) $_SESSION['mm_verified'][(int) $uid]:0;

        return ($t > time() - 2 * HOUR_IN_SECONDS);
    }

    /**
     * Wishlist editor (replaces the ACF form for field group 125). Saves via save_wishlist().
     */
    public function generateWishlistEditor($uid) {
        $uid = (int) $uid;

        if(get_post_type($uid) != 'users' || !$this->isVerifiedMember($uid)){
            echo '<div class="mm-match"><div class="mm-empty"><i class="fas fa-lock"></i><p>Please enter your password again to edit your wishlist.</p></div></div>';
            return;
        }

        $gid = get_field('groups', $uid);
        $group = get_fields($gid);
        $wishlist = get_field('my_wishlists', $uid);
        $address = (string) get_field('my_address_and_contact_details', $uid, false);
        $budget = Affiliate::budget($gid);
        $date = (!empty($group['gift_exchange_date']))?DateTime::createFromFormat('m/d/Y', $group['gift_exchange_date']):false;
        $ideas = GiftAI::ideasFor($uid);
        $ideas = ($ideas)?$ideas:Affiliate::giftIdeas();
        $items = ($wishlist)?$wishlist:array(array('wishlist_description' => '', 'reference_links' => array()));

        $config = array(
            'maxItems' => self::WISH_MAX_ITEMS,
            'maxLinks' => self::WISH_MAX_LINKS,
            'budget'   => $budget,
            'stores'   => array(
                'lazada' => array('prefix' => Affiliate::linkPrefix('lazada', 'wishlist-editor-find', $gid), 'search' => Affiliate::searchUrl('lazada', '%s', $budget)),
                'shopee' => array('prefix' => Affiliate::linkPrefix('shopee', 'wishlist-editor-find', $gid), 'search' => Affiliate::searchUrl('shopee', '%s', $budget)),
            ),
        );

        echo '<div class="mm-match mm-edit">';
        echo '<form class="mm-edit__form" novalidate data-config="'.esc_attr(wp_json_encode($config)).'">';
        echo '<input type="hidden" name="action" value="save_wishlist">';
        echo '<input type="hidden" name="uid" value="'.$uid.'">';
        echo '<input type="hidden" name="nonce" value="'.wp_create_nonce('mm_wishlist_'.$uid).'">';

        /** INTRO */
        echo '<div class="mm-match__hero mm-edit__hero">';
        echo '    <div class="mm-match__eyebrow">Your wishlist</div>';
        echo '    <div class="mm-edit__title">What would you like to receive?</div>';
        echo '    <p class="mm-edit__lead">Your Manito/Manita will see this. Be specific &mdash; size, colour or brand helps a lot.</p>';
        echo '    <div class="mm-match__chips">';
        if($budget > 0) echo '<span class="mm-chip"><i class="fas fa-wallet"></i> &#8369;'.number_format($budget).' minimum</span>';
        if($date) echo '<span class="mm-chip"><i class="far fa-calendar"></i> '.$date->format('M j, Y').'</span>';
        echo '    </div>';
        echo '</div>';

        /** ITEMS */
        echo '<section class="mm-sec">';
        echo '    <h3 class="mm-sec__title"><i class="fas fa-gifts"></i> WISH<span>LIST</span><em class="mm-sec__count" data-mm-count>'.count($items).'</em></h3>';
        echo '    <ol class="mm-items" data-mm-items>';
        foreach($items as $w):
            $links = array();
            foreach((array) ($w['reference_links'] ?? array()) as $l){
                if(!empty($l['link_url'])) $links[] = $l['link_url'];
            }
            echo $this->wishlistItemHtml($w['wishlist_description'] ?? '', $links);
        endforeach;
        echo '    </ol>';
        echo '    <template data-mm-item-tpl>'.$this->wishlistItemHtml('', array()).'</template>';
        echo '    <template data-mm-link-tpl>'.$this->wishlistLinkHtml('').'</template>';
        echo '    <button type="button" class="mm-btn mm-btn--ghost mm-btn--block" data-mm-add-item><i class="fas fa-plus"></i> Add another item</button>';
        echo '</section>';

        /** SUGGESTIONS */
        echo '<section class="mm-sec mm-suggest">';
        echo '    <h3 class="mm-sec__title"><i class="fas fa-lightbulb"></i> NEED<span>&nbsp;IDEAS?</span></h3>';
        echo '    <p class="mm-gifts__sub">Tap <strong>Add</strong> to put an idea on your wishlist'.(($budget > 0)?' &middot; within &#8369;'.number_format($budget):'').'</p>';
        echo '    <div class="mm-suggest__grid">';
        foreach($ideas as $idea):
            $icon = !empty($idea['icon'])?$idea['icon']:'fas fa-gift';
            echo '<div class="mm-suggest__item">';
            echo '    <span class="mm-gift__icon"><i class="'.esc_attr($icon).'"></i></span>';
            echo '    <div class="mm-suggest__text"><div class="mm-gift__label">'.esc_html($idea['label']).'</div>';
            if(!empty($idea['why'])) echo '<div class="mm-gift__why">'.esc_html($idea['why']).'</div>';
            echo '        <div class="mm-suggest__stores">';
            echo              Affiliate::anchor(Affiliate::searchUrl('lazada', $idea['keyword'], $budget), 'Lazada', 'wishlist-editor-ideas', $gid, 'mm-suggest__store');
            echo              Affiliate::anchor(Affiliate::searchUrl('shopee', $idea['keyword'], $budget), 'Shopee', 'wishlist-editor-ideas', $gid, 'mm-suggest__store');
            echo '        </div>';
            echo '    </div>';
            echo '    <button type="button" class="mm-btn mm-btn--add" data-mm-suggest="'.esc_attr($idea['label']).'"><i class="fas fa-plus"></i> Add</button>';
            echo '</div>';
        endforeach;
        echo '    </div>';
        echo '</section>';

        /** ADDRESS */
        echo '<section class="mm-sec">';
        echo '    <h3 class="mm-sec__title"><i class="fas fa-location-dot"></i> ADDRESS<span>&nbsp;&amp; CONTACTS</span><em class="mm-sec__tag">Optional</em></h3>';
        echo '    <label class="mm-field">';
        echo '        <span class="mm-field__hint">Only your match sees this. Add it if your gift will be delivered.</span>';
        echo '        <textarea name="address" rows="3" maxlength="1000" aria-label="Address and contact details" placeholder="House no., street, city &middot; mobile number">'.esc_textarea($address).'</textarea>';
        echo '    </label>';
        echo '</section>';

        /** FOOTER */
        echo '<div class="mm-edit__footer">';
        echo '    <div class="mm-edit__status" data-mm-status role="status" aria-live="polite"></div>';
        echo '    <button type="button" class="mm-btn mm-btn--ghost" data-fancybox-close>Cancel</button>';
        echo '    <button type="submit" class="mm-btn mm-btn--primary" data-mm-save><i class="fas fa-check"></i> Save wishlist</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    protected function wishlistItemHtml($text, $links){
        $html  = '<li class="mm-item" data-mm-item>';
        $html .= '<div class="mm-item__head">';
        $html .= '    <span class="mm-wish__num" data-mm-num>1</span>';
        $html .= '    <span class="mm-item__label">Item <span data-mm-num>1</span></span>';
        $html .= '    <button type="button" class="mm-icon-btn" data-mm-remove-item aria-label="Remove item" title="Remove item"><i class="far fa-trash-can"></i></button>';
        $html .= '</div>';
        $html .= '<textarea class="mm-item__text" rows="2" aria-label="Describe the item" maxlength="'.self::WISH_MAX_TEXT.'" placeholder="e.g. Red insulated tumbler, 500ml" data-mm-text>'.esc_textarea(wp_strip_all_tags((string) $text)).'</textarea>';
        $html .= '<div class="mm-item__meta" data-mm-meta><span data-mm-chars>0</span>/'.self::WISH_MAX_TEXT.'</div>';
        $html .= '<div class="mm-item__error" data-mm-error></div>';
        $html .= '<div class="mm-links" data-mm-links>';
        foreach($links as $l) $html .= $this->wishlistLinkHtml($l);
        $html .= '</div>';
        $html .= '<div class="mm-item__actions">';
        $html .= '    <button type="button" class="mm-btn-text" data-mm-add-link><i class="fas fa-link"></i> Add a shop link</button>';
        $html .= '    <span class="mm-item__find" data-mm-find hidden>Find it on <a class="mm-aff mm-find mm-find--lazada" target="_blank" rel="nofollow sponsored noopener" data-store="lazada" href="#">Lazada</a> <a class="mm-aff mm-find mm-find--shopee" target="_blank" rel="nofollow sponsored noopener" data-store="shopee" href="#">Shopee</a></span>';
        $html .= '</div>';
        $html .= '</li>';

        return $html;
    }

    protected function wishlistLinkHtml($url){
        $html  = '<div class="mm-link" data-mm-link>';
        $html .= '    <span class="mm-link__store" data-mm-store>Link</span>';
        $html .= '    <input type="url" inputmode="url" autocomplete="off" placeholder="Paste a Lazada, Shopee or any shop link" value="'.esc_attr($url).'" data-mm-url>';
        $html .= '    <button type="button" class="mm-icon-btn" data-mm-remove-link aria-label="Remove link" title="Remove link"><i class="fas fa-xmark"></i></button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * AJAX: saves the wishlist editor. Only for members who entered their password this session.
     */
    public function save_wishlist(){
        $uid = isset($_POST['uid'])?(int) $_POST['uid']:0;

        if(!$uid || get_post_type($uid) != 'users' || !wp_verify_nonce($_POST['nonce'] ?? '', 'mm_wishlist_'.$uid)){
            wp_send_json_error(array('message' => 'This form has expired. Please close it and try again.'));
        }

        if(!$this->isVerifiedMember($uid)){
            wp_send_json_error(array('message' => 'Please enter your password again to save your wishlist.'));
        }

        $rows = array();
        $items = (isset($_POST['items']) && is_array($_POST['items']))?wp_unslash($_POST['items']):array();

        foreach($items as $item){
            $text = trim(sanitize_textarea_field($item['text'] ?? ''));
            $text = mb_substr($text, 0, self::WISH_MAX_TEXT);
            $links = array();

            foreach((array) ($item['links'] ?? array()) as $url){
                $url = trim((string) $url);
                if($url === '') continue;
                if(!preg_match('~^https?://~i', $url)) $url = 'https://'.ltrim($url, '/');

                $url = esc_url_raw($url, array('http', 'https'));
                if($url && filter_var($url, FILTER_VALIDATE_URL)) $links[] = array('link_url' => $url);
                if(count($links) >= self::WISH_MAX_LINKS) break;
            }

            if($text === '' && count($links) === 0) continue;
            if($text === '') $text = 'See link';

            $rows[] = array('wishlist_description' => $text, 'reference_links' => $links);
            if(count($rows) >= self::WISH_MAX_ITEMS) break;
        }

        $address = mb_substr(trim(sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''))), 0, 1000);

        update_field('field_5f5c72c5260fc', $rows, $uid);     // my_wishlists
        update_field('field_617145f7ec732', $address, $uid);  // my_address_and_contact_details

        GiftAI::onSavePost($uid);

        wp_send_json_success(array('message' => 'Wishlist saved!', 'count' => count($rows)));
    }

    public function generateSeeMatchElements($uid) {
        $pair = get_field('pair', $uid);
        $group = new Groups();
        $groupid = $group->getGroupId();  // Replace with your actual group ID
        $fields = $group->getGroupDetails($groupid);
        $pro = (isset($fields['pro']))?$fields['pro']:false;

        $gid = $groupid;
        $pairName = get_field('screen_name', $pair);
        $wishlist = get_field('my_wishlists', $pair);
        $address = get_field('my_address_and_contact_details', $pair);
        $comments = get_comments(array('post_id' => $pair));
        $budget = Affiliate::budget($gid);
        $date = (!empty($fields['gift_exchange_date']))?DateTime::createFromFormat('m/d/Y', $fields['gift_exchange_date']):false;
        $legacyAff = (!$pro)?array('class' => 'aff-link', 'attrs' => array('data-url' => 'https://invl.io/cllz36p')):array('class' => '', 'attrs' => array());

        echo '<div class="mm-match">';

        /** HEADER */
        echo '<div class="mm-match__hero">';
        echo '    <img class="mm-match__gift" src="'.get_template_directory_uri().'/assets/images/gift.png" alt="">';
        echo '    <div class="mm-match__eyebrow">You&rsquo;re giving a gift to</div>';
        echo '    <div class="mm-match__name">'.esc_html($pairName).'</div>';
        echo '    <div class="mm-match__chips">';
        if($budget > 0) echo '<span class="mm-chip"><i class="fas fa-wallet"></i> &#8369;'.number_format($budget).' minimum</span>';
        if($date) echo '<span class="mm-chip"><i class="far fa-calendar"></i> '.$date->format('M j, Y').'</span>';
        echo '    </div>';
        echo '</div>';

        /** WISHLIST */
        echo '<section class="mm-sec">';
        echo '    <h3 class="mm-sec__title"><i class="fas fa-gifts"></i> WISH<span>LIST</span>'.(($wishlist)?'<em class="mm-sec__count">'.count($wishlist).'</em>':'').'</h3>';
        if($wishlist):
            echo '<ol class="mm-wish">';
            foreach($wishlist as $i => $w):
                echo '<li class="mm-wish__item">';
                echo '    <span class="mm-wish__num">'.($i+1).'</span>';
                echo '    <div class="mm-wish__body">';
                echo '        <div class="mm-wish__text">'.Affiliate::linkify($w['wishlist_description'], 'match-wishlist', $gid).'</div>';
                if($w['reference_links']):
                    echo '    <div class="mm-wish__links">';
                    foreach($w['reference_links'] as $k => $links):
                        echo Affiliate::referenceLink($links['link_url'], $k, 'match-wishlist', $gid, trim('mm-wish__link '.$legacyAff['class']), $legacyAff['attrs']);
                    endforeach;
                    echo '    </div>';
                endif;
                echo '    </div>';
                echo '</li>';
            endforeach;
            echo '</ol>';
            echo Affiliate::shopButtonsHtml($gid, 'match-shop-btn', Affiliate::keywordsFrom($wishlist[0]['wishlist_description']), 'Shop for '.$pairName);
        else:
            echo '<div class="mm-empty"><i class="fas fa-gift"></i><p>'.esc_html($pairName).' hasn&rsquo;t added a wishlist yet. Check the gift ideas below.</p></div>';
        endif;
        echo '</section>';

        /** GIFT IDEAS */
        $aiIdeas = GiftAI::ideasFor($pair);
        echo ($aiIdeas)
            ? Affiliate::giftIdeasHtml($gid, 'ai-gift-ideas', false, $aiIdeas, 'Picked for '.esc_html($pairName).' from their wishlist')
            : Affiliate::giftIdeasHtml($gid, 'match-gift-ideas');

        /** ADDRESS AND CONTACTS */
        echo '<section class="mm-sec">';
        echo '    <h3 class="mm-sec__title"><i class="fas fa-location-dot"></i> ADDRESS<span>&nbsp;&amp; CONTACTS</span></h3>';
        echo (trim(wp_strip_all_tags((string) $address)) !== '')
            ? '<div class="mm-card mm-address">'.$address.'</div>'
            : '<div class="mm-empty"><i class="fas fa-address-card"></i><p>No address or contact details yet.</p></div>';
        echo '</section>';

        if(!$pro):
            /** VALUE DEALS */
            echo '<section class="mm-sec mm-sec--ad"><div class="mm-sec__label">Sponsored</div><div class="mm-ad"><script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
            <!-- Manito Manita Match Page -->
            <ins class="adsbygoogle"
                style="display:inline-block;width:300px;height:250px"
                data-ad-client="ca-pub-8648985139343614"
                data-ad-slot="3964563495"></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
            </script></div></section>';
            //get_template_part('lazada/assets/offers', 'content');// offers template
            /** VALUE DEALS END */
        endif;

        /** COMMENTS */
        echo '<section class="mm-sec">';
        echo '    <h3 class="mm-sec__title"><i class="far fa-comments"></i> COMMENT<span>S</span>'.((count($comments) > 0)?'<em class="mm-sec__count">'.count($comments).'</em>':'').'</h3>';
        if(count($comments) == 0):
            echo '<div class="mm-empty"><i class="fas fa-comment"></i><p>Somebody has to say something.</p></div>';
        else:
            echo '<ul class="mm-comments">';
            foreach($comments as $c):
                echo '<li class="mm-comment">';
                echo '    <div class="mm-comment__text">'.$c->comment_content.'</div>';
                echo '    <time class="mm-comment__date">'.date('M j, Y · g:i a', strtotime($c->comment_date)).'</time>';
                echo '</li>';
            endforeach;
            echo '</ul>';
        endif;
        echo '</section>';

        /** FOOTER */
        echo '<div class="mm-match__footer">';
        echo '    <p>Thank you for using Manito<span>Manita</span>! Support us by liking and sharing our Facebook page.</p>';
        echo '    <iframe src="https://www.facebook.com/plugins/like.php?href=https%3A%2F%2Fwww.facebook.com%2Fmanitomanitaph&width=450&layout=standard&action=like&size=large&show_faces=false&share=true&height=35&appId=62030021851" width="264" height="60" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true" allow="encrypted-media"></iframe>';
        echo '</div>';

        echo '</div>';
    }

    public function my_save_post( $post_id ) {	

        if(isset($_POST['_acf_post_id'])) {
            /**
             * get post details
             */
            $post_values = get_post($post_id);


            /**
             * bail out if not a custom type and admin
             */
            $types = array('users');

            if(!(in_array($post_values->post_type, $types))){
                return;
            }

            if($_POST['_acf_post_id'] == "new_post"){
                /**
                 * groups set values
                 */
                if($post_values->post_type == 'users'){
                    /**
                     * update post
                     */

                    $my_post = array(
                        'ID'           => $post_id,
                        'post_title'   => $_POST['acf']['field_5f55f0e476675'].' - '.$_POST['acf']['field_5f55f1bc76676']
                    );

                    $group = new Groups();
                    $groupId = $group->getGroupId();

                    //group assign
                    update_field('groups', $groupId, $post_id);
                    //user password hashed
                    update_field('password', wp_hash_password($_POST['acf']['field_5f55f1e576678']), $post_id);

                    wp_update_post( $my_post );

                    $this->setEmailNewMember($post_id);
                }

                /**
                 *  Clear POST data
                 */
                unset($_POST);

                /**
                 * notifications
                 */
         
            }
            else if($_POST['_acf_post_id'] == $post_id) {

                /**
                 *  Clear POST data
                 */
                unset($_POST);

                /**
                 * notifications
                 */

            }
        }
    }

    public function getAllUsersPerGroup($groupid){
        $meta_query = array(
            'key' => 'groups',
            'value' => $groupid
        );

        $p = parent::createQuery('users', $meta_query);
        $users = wp_list_pluck($p->posts, 'ID');

        if(count($users) > 0){
            $users = (array)$users;
            shuffle($users); //randomize users
            
            return $users;
        }else{
            return false;
        }
    }
    
    public function getAllusers() {
        $users = parent::createQuery2('users', array(), 100);

        return $users;
    }

    public function getAllUsersPerGroup2($groupid) {
        $matchbool = get_field('matched', $groupid);

        if($matchbool):
            $cookiename = 'users_group_' . $groupid;
            $cookienamecount = 'users_group_count_' . $groupid;

            if (isset($_COOKIE[$cookiename])):
                $decodecount = json_decode($_COOKIE[$cookiename], true);

                if(count($decodecount) == $_COOKIE[$cookienamecount]):
                    return $decodecount;
                endif;
            endif;
        endif;

        
        $meta_query = array(
            'key' => 'groups',
            'value' => $groupid
        );

        $p = parent::createQuery2('users', $meta_query);
        $users = wp_list_pluck($p, 'ID');

        if(count($users) > 0){
            $users = (array)$users;

            shuffle($users); //randomize users
            
            if($matchbool):
                $cookiename = 'users_group_' . $groupid;
                $expirationTime = time() + (30 * 24 * 60 * 60); // 30 days * 24 hours * 60 minutes * 60 seconds
                setcookie($cookiename, json_encode($users), $expirationTime, '/');
            endif;
            
            return $users;
        }else{
            return false;
        }
    }

    public function randString($length) {
        $char = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $char = str_shuffle($char);
    
        $rand = '';
        $l = strlen($char) - 1;
    
        for ($i = 0; $i < $length; $i++) {
            $rand .= $char[mt_rand(0, $l)];
        }
    
        return $rand;
    }

    public function pass_generate(){
        $uid = $_POST['uid'];
        $groupId = get_field('groups', $uid);

        //$newPass = $this->randString(5);

        $posttype = get_post_type($uid);
		$email = ($posttype == "groups")?get_field('your_email', $uid):get_field('email', $uid);

		if($email){

            //$update = update_field('password', wp_hash_password($newPass), $uid);
            
            //if($update){
            $tag = "pass-gen";

            $args = array(
            'post_type'   => 'emails',
            'posts_per_page' => -1
            );
            
            $em = get_posts( $args );
            
            foreach($em as $epost){
                $f = get_fields($epost->ID);

                if($f['email_tag'] == $tag){
                    $e = $f;
                    break;
                }
            }

            $group = new Groups();

            //$creds = $group->getGroupCredentials();
            $gpass = $group->getGroupPassword($uid);

            $name = ($posttype == "groups")?get_field('your_name', $uid):get_field('name', $uid);

            $message = $e['email_body'];
            $message = str_replace('[email_name]', ucwords($name), $message);
            $message = str_replace('[email_resetpass]', get_permalink(1389).'?uid='.$uid.'&gpw='.$gpass, $message);
            
            $to = $email;

            $subject = $e['email_subject'];

            if(parent::sendEmail($to, $subject, $message)){
                wp_send_json_success(true);
            }else{
                wp_send_json_success(false);
            }
            //}
		}
		else
		{
			wp_send_json_success(false);
		}
    }

    public function getWishlistCount($groupid){
        $users = $this->getAllUsersPerGroup($groupid);
        $c = 0;

        if($users):

            foreach($users as $u):
                $w = get_field('my_wishlists', $u);

                if($w) $c++;
            endforeach;

        endif;

        return $c;
    }

    public function addCommentToUser() {
        if(!(isset($_POST['user-data']) and isset($_POST['comment']) and isset($_POST['g-recaptcha-response']))){
            return;
        }

        if(!$this->gCaptchaChecker()){
            return 'You are not human! Must check captcha before sending new comment.';
        }
        
        $uid = $_POST['user-data'];
        $mes = $_POST['comment'];

        $commentdata = array(
            'comment_content' => $mes,
            'comment_post_ID' => $uid
        );

        $insert = wp_insert_comment($commentdata);

        return ($insert)? 'Comment Successfully Added.': 'Adding comment failed.';
    }

    public function gCaptchaChecker(){
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        #
        # Verify captcha
        $post_data = http_build_query(
            array(
                'secret' => '6LfV5iMUAAAAAKugV-K9Ss0VRsnodlpPzzkvGDek',
                'response' => $_POST['g-recaptcha-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        );
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $post_data
            )
        );
        $context  = stream_context_create($opts);
        $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        $result = json_decode($response);
        

        if (!$result->success) {
            return false;
        }

        return true;
    }

    public function setEmailNewMember($uid){
        $tag = "new-member";

        $args = array(
        'post_type'   => 'emails',
        'posts_per_page' => -1
        );
        
        $em = get_posts( $args );
        
        foreach($em as $epost){
            $f = get_fields($epost->ID);

            if($f['email_tag'] == $tag){
                $e = $f;
                break;
            }
        }

        $group = new Groups();

        $creds = $group->getGroupCredentials();

        $message = $e['email_body'];
        $message = str_replace('[email_name]', get_field('name', $uid), $message);
        $message = str_replace('[email_group]', get_field('group_name', get_field('groups', $uid)), $message);
        $message = str_replace('[email_grouplink]', get_permalink(23).'?gid='.$creds[0].'&pw='.$creds[1], $message);
        
        $to = get_field('email', $uid);

        $subject = $e['email_subject'];
        $subject = str_replace('[email_group]', get_field('group_name', get_field('groups', $uid)), $subject);

        parent::sendEmail($to, $subject, $message);
    }

    public function generateCardUsers($userids, $groupid){
        $groupid = (int)$groupid;
        $group = new Groups();
        $fields = $group->getGroupDetails($groupid);
        $pro = (isset($fields['pro']))?$fields['pro']:false;

        $cardres = '';
        $wishcount = 0;
        $whojoined = '';

        foreach($userids as $userid):

        $cardres .= '<div class="card">';
        $cardres .= '    <div class="card-header" id="heading'.$userid.'">';
        $cardres .= '    <h2 class="mb-0">';
        $cardres .= '        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse'.$userid.'" aria-expanded="false" aria-controls="collapse'.$userid.'">';
        $cardres .= '        <i class="fas fa-chevron-circle-right"></i> '.get_field('screen_name', $userid).'';
        $cardres .= '        </button>';
        $cardres .= '        <div class="action-btns">';
        $cardres .= '            <a href="#" class="expand-deets"><i class="fas fa-stream"></i> Expand Details</a>';
        $cardres .= '            <a href="#" class="user-action comment-btn" user-data="'.$userid.'"><i class="far fa-comment"></i> Comment Member</a>';
        $cardres .= '        </div>';
        $cardres .= '        ';
        $cardres .= '    </h2>';
        $cardres .= '    </div>';
        $cardres .= '    <div id="collapse'.$userid.'" class="collapse" aria-labelledby="heading'.$userid.'" data-parent="#members-list">';
        $cardres .= '    <div class="card-body">';
        $cardres .= '        <div class="row">';
        $cardres .= '            <div class="col-md-6">';
        $cardres .= '                <h2>Wish<span>List</span></h2>';
        $cardres .= '                <ul class="wishlist-list">';
                                        $wishlist = get_field("my_wishlists", $userid);

                                        if(!$wishlist){
        $cardres .= '                       <div class="placeholder"><i class="fas fa-gifts"></i> No wishlist provided yet.</div>';
        $cardres .=                         Affiliate::shopButtonsHtml($groupid, 'members-empty-wishlist');
                                        }
                                        
                                        if($wishlist):
                                            $wishcount++;

                                            foreach($wishlist as $w):

        $cardres .= '                       <li>';
        $cardres .= '                        <div class="wish-content">';
                                                $legacyAff = (!$pro)?array('class' => 'aff-link', 'attrs' => array('data-url' => 'https://invl.io/cllz36p')):array('class' => '', 'attrs' => array());
        $cardres .=                                 Affiliate::linkify($w['wishlist_description'], 'members-wishlist', $groupid, $legacyAff['class'], $legacyAff['attrs']);
        $cardres .= '                        </div>';
        $cardres .= '                        <div class="wish-links">';
        
                                                if($w['reference_links']):
                                                    foreach($w['reference_links'] as $k => $links):
        $cardres .=                                     Affiliate::referenceLink($links['link_url'], $k, 'members-wishlist', $groupid, $legacyAff['class'], $legacyAff['attrs']);
                                                    endforeach;
                                                endif;
        $cardres .= '                        </div>';
        $cardres .= '                    </li>';


                                            endforeach;
                                        endif;
        $cardres .= '                </ul>';
        $cardres .= '            </div>';
        $cardres .= '            <div class="col-md-6">';
        $cardres .= '                <h2>Comment<span>s</span></h2>';
        $cardres .= '                <ul class="comments-list">';
        
                                        $comments = get_comments(array('post_id' => $userid,'orderby'=>'comment_date', 'order'=>'ASC'));
        
                                        if(count($comments) == 0){
        $cardres .= '                       <div class="placeholder"><i class="far fa-comment-dots"></i> Say something for me.</div>';
                                        }

                                        foreach($comments as $c):
        $cardres .= '                    <li>';
        $cardres .= '                        <div class="com-content">'.$c->comment_content.'</div>';
        $cardres .= '                        <div class="com-date">'.date('F d, Y h:i:sa', strtotime($c->comment_date)).'</div>';
        $cardres .= '                    </li>';
                                        endforeach;
        $cardres .= '                </ul>';
        $cardres .= '            </div>';
        $cardres .= '        </div>';
        $cardres .= '    </div>';
        $cardres .= '    </div>';

        $cardres .= '    <div class="user-action-btns">';
        $cardres .= '        <a href="#" class="user-action wish-btn" user-data="'.$userid.'" data-action="edit-wishlist"><i class="fas fa-gift"></i> Edit Wishlist</a>';
        
                        $matchattr =  (!get_field('matched', $groupid))?'disabled':'class="user-action match-btn" user-data="'.$userid.'" data-action="see-match"'; 

                        $matchattr2 = (get_field('matched', $groupid))?'disabled':'class="user-action leave-btn" user-data="'.$userid.'" data-action="leave-group"';

        $cardres .= '        <a href="javascript:;" '.$matchattr.'><i class="fas fa-user-friends"></i> See Match</a>';
        $cardres .= '        <a href="javascript:;" '.$matchattr2.'><i class="fas fa-sign-out-alt"></i> Leave Group</a>';
        $cardres .= '    </div>';
        $cardres .= '</div>';

        $whojoined .= '<div class="card">'.get_field('name', $userid).'</div>';

        endforeach;

        return array($cardres, $wishcount, $whojoined);
    }
}