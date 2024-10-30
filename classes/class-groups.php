<?php
/**
 * * Groups Class. Classes and functions for Manito Manita.
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
 * Class Groups
 */
class Groups extends Theme {
    public $groupid;

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
        add_action( 'wp_ajax_execute_matching', array($this, 'execute_matching') );
        add_action( 'wp_ajax_nopriv_execute_matching', array($this, 'execute_matching') ); 
        
        add_action( 'wp_ajax_get_pro_list', array($this, 'get_pro_list') );
        add_action( 'wp_ajax_nopriv_get_pro_list', array($this, 'get_pro_list') ); 

        // Register REST API route
        add_action('rest_api_init', array($this, 'unshuffle_group'));

    }

    protected function initFilters() {
        /**
         * Place filters here
         */

        add_filter('pre_get_document_title', array($this, 'replace_group_title'), 50);
    }

    public function unshuffle_group() {
        register_rest_route('custom-webhook/v1', '/unshuffle-group', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_unshuffle_group'),
            'permission_callback' => '__return_true', // Note: Customize for secure access if needed
        ));
    }

    public function handle_unshuffle_group($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
        
        // Check if the post exists and is of the 'groups' custom post type
        if (get_post_type($group_id) === 'groups') {
            // Update the 'matched' custom field to false
            $updated = update_post_meta($group_id, 'matched', false);
            
            // Check if the update was successful
            if ($updated) {
                // Respond with success
                return new WP_REST_Response(array('success' => true, 'message' => 'Group updated'), 200);
            } else {
                // Respond with failure if update failed
                return new WP_REST_Response(array('success' => false, 'message' => 'Failed to update group'), 500);
            }
        } else {
            // Respond with failure if post does not exist or is not of 'groups' type
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    }
    

    public function get_pro_list(){
        $gid = (int)$_POST['gid'];
        $fields = $this->getGroupDetails($gid);
        

        $list = "";
        $list .= "<ul class='pro-btns-list'>";
        $list .= (!$fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='shuffle'>Shuffle Group</a></li>":"<li><a href='javascript:;' disabled>Shuffle Group</a></li>";
        $list .= ($fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='un-shuffle'>Unshuffle</a></li>":"<li><a href='javascript:;' disabled>Unshuffle</a></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='joined'>Joined Names</a></li>";
        $list .= ($fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='matches'>See Matches</a></li>":"<li><a href='javascript:;' disabled>See Matches</a></li>";
        $list .= (!$fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='kick'>Kick Members</a></li>":"<li><a href='javascript:;' disabled>Kick Members</a></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='edit'>Edit Member Details</a></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='export'>Export Data</a></li>";
        $list .= "</ul>";

        wp_send_json_success($list);
    }

    
    public function replace_group_title($title){
        if(!isset($_GET['gid'])){
            return false;
        }

        if($this->getGroupId()){
            $title = 'Group Dashboard » Manito Manita » ' . get_field('group_name', $this->getGroupId());
        }else{
            $title = 'Group Dashboard » Manito Manita';
        }

        return $title;
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
    
    public function setUserGroupSession($groupid, $grouppw){
        $_SESSION['groupid'] = $groupid;
        $_SESSION['grouppw'] = $grouppw;
    }

    public function getGroupDetails($groupid){
        $this->groupid = (isset($_SESSION['groupid']))?$_SESSION['groupid']:$groupid;

        return get_fields($groupid);
    }

    public function get_users_count_with_meta($meta_query = array()) {
        global $wpdb;
    
        $args = array(
            'post_type' => 'users',  // Adjust the post type if needed
            'post_status' => 'publish',
            'meta_query' => $meta_query,
        );
    
        $sql = "SELECT COUNT(DISTINCT ID) FROM {$wpdb->posts} AS p ";
        $sql .= "LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id ";
        $sql .= "WHERE 1=1 ";
    
        if (!empty($meta_query)) {
            $sql .= "AND (";
            foreach ($meta_query as $meta_condition) {
                $sql .= $wpdb->prepare("(pm.meta_key = %s AND pm.meta_value = %s) OR ", $meta_condition['key'], $meta_condition['value']);
            }
            $sql = rtrim($sql, ' OR ');
            $sql .= ")";
        }
    
        $count = $wpdb->get_var($sql);
    
        return $count;
    }

    public function getGroupId(){
        if(isset($_SESSION['groupid']) || isset($_GET['gid'])){
            if(isset($_SESSION['groupid'])){
                return $_SESSION['groupid'];
            }else{
                return $_GET['gid'];
            }
        }else{
            return false;
        }
    }

    public function getGroupPassword($uid) {
        $gpw = "";

        if(get_post_type($uid) == 'users'){
            $field = get_field('groups', $uid);
            $gpw = get_field('group_password', $field);
        }else{
            $gpw = get_field('group_password', $uid);
        }
        
        return $gpw;
    }

    public function checkGroupCredentials(){
        $allow = false;

        if(isset($_GET['gid']) and isset($_GET['pw'])){
            $groupid = $_GET['gid']; 
            $grouppw = $_GET['pw']; 

            if((strlen($groupid) == 0) || (strlen($grouppw) == 0)) return false;

            if(trim(get_field('group_password', $groupid)) == trim($grouppw)){
                $_SESSION['groupid'] = $groupid;
                $_SESSION['grouppw'] = $grouppw;
                $allow = true;
            }

        }else{
            if(isset($_SESSION['groupid']) and isset($_SESSION['grouppw'])){
                if(trim(get_field('group_password', $_SESSION['groupid'])) == trim($_SESSION['grouppw'])){
                    $allow = true;
                }
            }
        }

        return $allow;
    }

    public function getGroupCredentials(){
        $creds = array();

        if($this->checkGroupCredentials()){
            $creds = array($_SESSION['groupid'], $_SESSION['grouppw']);
        }

        return $creds;
    }

    public function update_password(){
        $uid = $_POST['uid'];
        $newPass = $_POST['new-pass'];

        $posttype = get_post_type($uid);
		$email = ($posttype == "groups")?get_field('your_email', $uid):get_field('email', $uid);
        $update = update_field('password', wp_hash_password($newPass), $uid);
           
        if($update){
            $tag = "new-pass";

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

            $gpw = "";
            $gid = "";

            if(get_post_type($uid) == 'users'){
                $field = get_field('groups', $uid);
                $gid = $field;
                $gpw = get_field('group_password', $field);
            }else{
                $gid = $uid;
                $gpw = get_field('group_password', $uid);
            }

            $name = ($posttype == "groups")?get_field('your_name', $uid):get_field('name', $uid);

            $message = $e['email_body'];
            $message = str_replace('[email_name]', ucwords($name), $message);
            $message = str_replace('[email_grouplink]', get_permalink(23).'?gid='.$gid.'&pw='.$gpw, $message);
            
            $to = $email;

            $subject = $e['email_subject'];

            if(parent::sendEmail($to, $subject, $message)){
                return 'Password Changed Successfully!';
            }else{
                return 'Password Change Failed!';
            }
        }else {
            return 'Password Change Failed!';
        }
        
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
            $types = array('groups');

            if(!(in_array($post_values->post_type, $types))){
                return;
            }

            if($_POST['_acf_post_id'] == "new_post"){
                /**
                 * groups set values
                 */
                if($post_values->post_type == 'groups'){
                    /**
                     * update post
                     */

                    $my_post = array(
                        'ID'           => $post_id,
                        'post_title'   => $_POST['acf']['field_5f55be68de4c7'].' - '.$_POST['acf']['field_5f55bf19de4cb']
                    );

                    $gen = $this->randString(6);
                    //group password
                    update_field('group_password', $gen, $post_id);
                    //admin password hashed
                    update_field('password', wp_hash_password($_POST['acf']['field_5f55bf45de4cd']), $post_id);

                    wp_update_post( $my_post );

                    $this->setUserGroupSession($post_id, get_field('group_password', $post_id));
                    $this->setEmailForCreateGroup($post_id);
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
                //update_field('password', wp_hash_password($_POST['acf']['field_5f55bf45de4cd']), $post_id);
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

    public function setEmailForCreateGroup($gid) {
        $tag = "new-group";

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

        $creds = $this->getGroupCredentials();

        $message = $e['email_body'];
        $message = str_replace('[email_name]', get_field('your_name', $gid), $message);
        $message = str_replace('[email_grouplink]', get_permalink(23).'?gid='.$creds[0].'&pw='.$creds[1], $message);
        

        $to = get_field('your_email', $gid);

        $subject = $e['email_subject'];

        parent::sendEmail($to, $subject, $message, true);
    }

    public function setEmailForGroupMatched($gid){
        $tag = "group-matched";

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
        
        $creds = $this->getGroupCredentials();

		$message = $e['email_body'];
		$message = str_replace('[email_group]', get_field('group_name', $gid), $message);
        $message = str_replace('[email_grouplink]', get_permalink(23).'?gid='.$creds[0].'&pw='.$creds[1], $message);

		$to = $this->getAllMembersEmailsPerGroupId($gid);

		$subject = $e['email_subject'];
        $subject = str_replace('[email_group]', get_field('group_name', $gid), $subject);
        
        parent::sendEmail($to, $subject, $message);
    }

    public function getAllMembersEmailsPerGroupId($gid){
        $meta_query = array(
            'key' => 'groups',
            'value' => $gid
        );

        $q = parent::createQuery('users', $meta_query);

        $emails = array();

        foreach($q->posts as $u):
            $emails[] = get_field('email', $u->ID);
        endforeach;

        return $emails;
    }

    public function execute_matching() {
        $result = true; // Default result to true
        $message = 'success';
        $maxRetries = 3; // Maximum number of retries for each update
    
        try {
            $gid = $_POST['gid'];
            $matched = get_field('matched', $gid);
    
            $args = array(
                'key' => 'groups',
                'value' => $gid
            );
    
            $q = parent::createQuery('users', $args);
            $users = $q->posts;
            
            $dontStop = true;
            $match1 = array();
            $match2 = array();
            $emails = array();
    
            // Collect users for matching
            foreach ($users as $user) {
                $match1[] = $user->ID;
                $match2[] = $user->ID;
                $emails[] = get_field('email', $user->ID);
            }
    
            // Ensure no users match with themselves
            while ($dontStop) {
                shuffle($match2);
                $i = 0;
                
                foreach ($match1 as $k => $u) {
                    if ($u == $match2[$k]) {
                        $i++;
                    }
                }
    
                if ($i == 0) $dontStop = false; // Stop if no matches are the same
            }
    
            // Update field for each matched pair with retry logic
            foreach ($match1 as $k => $userId) {
                $attempts = 3;
                $updated = false;
    
                while ($attempts < $maxRetries && !$updated) {
                    $update = update_field('pair', $match2[$k], $userId);
    
                    if ($update) {
                        $updated = true; // Successfully updated
                    } else {
                        $attempts++; // Increment the attempt counter
                        sleep(1); // Optional: wait before retrying (1 second)
                    }
                }
    
                if (!$updated) {
                    $result = false; // If all attempts fail, set result to false
                    $message = 'Pair Update Failed for User ID: ' . $userId;
                    break; // Exit the loop on failure
                }
            }
    
            // If all went well, proceed to send email notifications
            if ($result) {
                $this->setEmailForMembers($gid);
                update_field('matched', true, $gid);
            }
            
        } catch (Exception $e) {
            // On any exception, set result to false and return the error message
            $result = false;
            $message = 'Matching Failed. Check Errors.';
            wp_send_json_error(['error' => $e->getMessage()]);
            return; // Stop execution after sending error response
        }
    
        // Send success response if all operations succeeded
        wp_send_json_success(array('result' => $result, 'message' => $message));
    }    
    
    public function setEmailForMembers($gid){
        $tag = "group-matched";

		$args = array(
		'post_type'   => 'emails'
		);
		
		$em = get_posts( $args );
		
		foreach($em as $epost){
			$f = get_fields($epost->ID);

			if($f['email_tag'] == $tag){
				$e = $f;
				break;
			}
        }
        
        $creds = $this->getGroupCredentials();

		$message = $e['email_body'];
		$message = str_replace('[email_group]', get_field('group_name', $gid), $message);
		$message = str_replace('[email_grouplink]', get_permalink(23).'?gid='.$creds[0].'&pw='.$creds[1], $message);

		$to = $this->getAllMembersEmailsPerGroupId($gid);

		$subject = $e['email_subject'];
		$subject = str_replace('[email_group]', get_field('group_name', $gid), $subject);
        
        parent::sendEmail($to, $subject, $message);
    }
}