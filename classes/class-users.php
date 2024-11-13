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

    public function generateSeeMatchElements($uid) {
        $pair = get_field('pair', $uid);
        $group = new Groups();
        $groupid = $group->getGroupId();  // Replace with your actual group ID
        $fields = $group->getGroupDetails($groupid);
        $pro = (isset($fields['pro']))?$fields['pro']:false;

        echo '<div class="header-image"><img src="'.get_template_directory_uri().'/assets/images/gift.png"></div>';
        echo '<div class="inner-contents">';
        echo '    <div class="container">';
        echo '        <div class="row">';
        echo '            <div class="col-md-12">';
        echo '                <h2><i class="far fa-user"></i> '.get_field('screen_name', $pair).'</h2>';
        echo '                <h2><i class="fas fa-gifts"></i> WISH<span>LIST</span></h2>';
        echo '                  <ul class="wishlist-list">';
                                $wishlist = get_field('my_wishlists', $pair);
        echo '                                                ';
                                if(!$wishlist){
                                    echo '<div class="placeholder">No wishlist provided yet.</div>';
                                }
                                if($wishlist):
                                foreach($wishlist as $w):
        echo '                <li>';
        echo '                    <div class="wish-content">'.$w['wishlist_description'].'</div>';
        echo '                    <div class="wish-links">';
                                    if($w['reference_links']):
                                        foreach($w['reference_links'] as $k => $links):
                                            $stringlink = (strpos($links['link_url'], 'lazada.com.ph') !== false)?'https://invol.co/aff_m?offer_id=101166&aff_id=189674&source=deeplink_generator_v2&url='.urlencode($links['link_url']):$links['link_url'];

                                            $stringlink = (strpos($links['link_url'], 'shopee.ph') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;

                                            $stringlink = (strpos($links['link_url'], 'shp.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;

                                            $stringlink = (strpos($links['link_url'], 'shope.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;

                                            if(!$pro):
                                                echo '<a href="'.$stringlink.'" target="_blank" class="aff-link" data-url="https://invl.io/cllz36p">Link '.($k+1).'</a>';
                                            else:
                                                echo '<a href="'.$stringlink.'" target="_blank">Link '.($k+1).'</a>';
                                            endif;
                                        endforeach;
                                    endif;
        echo '                    </div>';
        echo '                </li>';
                                endforeach; 
                                endif;
        echo '                  </ul>';
        echo '                <h2><i class="fas fa-gifts"></i> ADDRESS <span>AND</span> CONTACTS</h2>';
        echo                  '<div style="padding: 32px;">'.get_field('my_address_and_contact_details', $pair).'</div>';

        if(!$pro):
            /** VALUE DEALS */
            echo '<h2>GREAT VALUE <span>DEALS</span></h2><div class="row product-list"><div class="col-md-12 col-sm-12 col-12 product-item text-center"><script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
            <!-- Manito Manita Match Page -->
            <ins class="adsbygoogle"
                style="display:inline-block;width:300px;height:250px"
                data-ad-client="ca-pub-8648985139343614"
                data-ad-slot="3964563495"></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
            </script></div></div>';
            //get_template_part('lazada/assets/offers', 'content');// offers template
            /** VALUE DEALS END */
        endif;

        echo '            </div>';
        echo '            <div class="col-md-12">';
        echo '                <h2><i class="far fa-comments"></i> COMMENT<span>S</span></h2>';
        echo '                        <ul class="comments-list">';
                                        $comments = get_comments(array('post_id' => $pair));
                                        if(count($comments) == 0){
                                            echo '<div class="placeholder"><i class="fas fa-comment"></i> Somebody has to say something.</div>';
                                        }
                                        foreach($comments as $c):
        echo '                            <li>';
        echo '                                <div class="com-content">'.$c->comment_content.'</div>';
        echo '                                <div class="com-date">'.date('F d, Y h:i:sa', strtotime($c->comment_date)).'</div>';
        echo '                            </li>';
                                        endforeach;
        echo '                    </ul>';
        echo '            </div>';
        echo '        </div>';
        echo '      <h2 class="fb">Thank you for using Manito<span>Manita</span>. Kindly show support by clicking Like and <span>Share</span> below for our facebook page.</h2>';
        echo '      <iframe src="https://www.facebook.com/plugins/like.php?href=https%3A%2F%2Fwww.facebook.com%2Fmanitomanitaph&width=450&layout=standard&action=like&size=large&show_faces=false&share=true&height=35&appId=62030021851" width="264" height="60" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowTransparency="true" allow="encrypted-media"></iframe>';
        echo '    </div>';
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

            $creds = $group->getGroupCredentials();

            $name = ($posttype == "groups")?get_field('your_name', $uid):get_field('name', $uid);

            $message = $e['email_body'];
            $message = str_replace('[email_name]', ucwords($name), $message);
            $message = str_replace('[email_resetpass]', get_permalink(1389).'?uid='.$uid.'&gpw='.$creds[1], $message);
            
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
                                        }
                                        
                                        if($wishlist):
                                            $wishcount++;

                                            foreach($wishlist as $w):

        $cardres .= '                       <li>';
        $cardres .= '                        <div class="wish-content">';
                                                $str = $w['wishlist_description'];
                                                $pattern = "~(?:(https?)://([^\s<]+)|(www\.[^\s<]+?\.[^\s<]+))(?<![\.,:])~i";

                                                if(preg_match_all($pattern, $str, $matches)):

                                                    foreach($matches[0] as $link):
                                                        $stringlink = (strpos($link, 'lazada.com.ph') !== false)?'https://invol.co/aff_m?offer_id=101166&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($link):$link;
        
                                                        $stringlink = (strpos($link, 'shopee.ph') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($link):$stringlink;

                                                        $stringlink = (strpos($link, 'shp.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($link):$stringlink;

                                                        $stringlink = (strpos($link, 'shope.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($link):$stringlink;

                                                        if(!$pro):
                                                            $str = str_replace($link, '<a href="'.$stringlink.'" target="_blank" class="aff-link" data-url="https://invl.io/cllz36p">'.$link.'</a>', $str);
                                                        else:
                                                            $str = str_replace($link, '<a href="'.$stringlink.'" target="_blank">'.$link.'</a>', $str);
                                                        endif;
                                                    endforeach;
        
        $cardres .=                                 ''.$str.'';
                                                else:
        $cardres .=                                 $w['wishlist_description'];
                                                endif;
        
        
        $cardres .= '                        </div>';
        $cardres .= '                        <div class="wish-links">';
        
                                                if($w['reference_links']):
                                                    foreach($w['reference_links'] as $k => $links):
                                                        $stringlink = (strpos($links['link_url'], 'lazada.com.ph') !== false)?'https://invol.co/aff_m?offer_id=101166&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$links['link_url'];
        
                                                        $stringlink = (strpos($links['link_url'], 'shopee.ph') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;

                                                        $stringlink = (strpos($links['link_url'], 'shp.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;

                                                        $stringlink = (strpos($links['link_url'], 'shope.ee') !== false)?'https://invol.co/aff_m?offer_id=101653&aff_id=189674&source=deeplink_generator_v2&property_id=133170&url='.urlencode($links['link_url']):$stringlink;
        
                                                        if(!$pro):
                                                            $cardres .= (strlen($stringlink) > 0)?'<a href="'.$stringlink.'" target="_blank" class="aff-link" data-url="https://invl.io/cllz36p">Link '.($k+1).'</a>':'';
                                                        else:
                                                            $cardres .= (strlen($stringlink) > 0)?'<a href="'.$stringlink.'" target="_blank">Link '.($k+1).'</a>':'';
                                                        endif;
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