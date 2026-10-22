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
        add_action('rest_api_init', array($this, 'joined_group'));
        add_action('rest_api_init', array($this, 'matches_group'));
        add_action('rest_api_init', array($this, 'kick_group'));
        add_action('rest_api_init', array($this, 'trash_user'));
        add_action('rest_api_init', array($this, 'edit_user'));
        add_action('rest_api_init', array($this, 'get_user_export'));

        // Groups is instantiated in several places; register the cron hooks once.
        static $cronHooked = false;

        if(!$cronHooked){
            $cronHooked = true;
            add_action('init', array($this, 'scheduleExchangeReminders'));
            add_action('mm_exchange_reminders', array($this, 'sendExchangeReminders'));
        }
    }

    protected function initFilters() {
        /**
         * Place filters here
         */

        add_filter('body_class', array($this, 'my_custom_body_class'));

        // Per-group title, description and sharing tags on the group dashboard. Registered once;
        // priority beats All in One SEO, which sets the document title at 99999.
        static $seoHooked = false;

        if(!$seoHooked){
            $seoHooked = true;
            add_filter('pre_get_document_title', array($this, 'replace_group_title'), 100000);
            add_filter('aioseo_title', array($this, 'replace_group_title'));
            add_filter('aioseo_description', array($this, 'groupMetaDescription'));
            add_filter('aioseo_facebook_tags', array($this, 'groupSocialTags'));
            add_filter('aioseo_twitter_tags', array($this, 'groupSocialTags'));
            add_filter('aioseo_robots_meta', array($this, 'groupRobots'));
            add_filter('wp_robots', array($this, 'groupWpRobots'));
        }
    }

    /**
     * Title/description for the group dashboard. The group's name and details are only used
     * when the link's group password is valid; otherwise a generic title is returned.
     *
     * @return array|false ['title', 'description', 'url'] on the dashboard, false elsewhere.
     */
    public function groupMeta(){
        static $meta = null;

        if($meta !== null) return $meta;
        if(!isset($_GET['gid'])) return $meta = false;

        $gid = (int) $_GET['gid'];
        $valid = $gid && get_post_type($gid) == 'groups' && $this->checkGroupCredentials();

        if(!$valid){
            return $meta = array(
                'title'       => 'Group Dashboard | Manito Manita',
                'description' => 'Manito Manita makes Secret Santa easy: join a group, add your wishlist and see who you&rsquo;ll give a gift to.',
                'url'         => '',
            );
        }

        // Escaped here: All in One SEO prints the title into <title> as-is.
        $name = esc_html(trim(wp_strip_all_tags((string) get_field('group_name', $gid))));
        $date = get_field('gift_exchange_date', $gid);
        $date = ($date && ($d = DateTime::createFromFormat('m/d/Y', $date)))?$d->format('F j, Y'):'';
        $budget = (int) preg_replace('/[^0-9]/', '', (string) get_field('spending_minimum', $gid));
        $host = esc_html(trim(wp_strip_all_tags((string) get_field('your_name', $gid))));

        $details = array();
        if($date) $details[] = 'Exchange on '.$date;
        if($budget > 0) $details[] = '&#8369;'.number_format($budget).' minimum';

        $description = 'You&rsquo;re invited to '.$name.(($host)?', hosted by '.$host:'').' on Manito Manita. '
                     .(($details)?implode(' · ', $details).'. ':'')
                     .'Join the group, add your wishlist and see who you&rsquo;ll give a gift to.';

        return $meta = array(
            'title'       => $name.' · Secret Santa Group | Manito Manita',
            'description' => $description,
            'url'         => add_query_arg(array('gid' => $gid, 'pw' => rawurlencode((string) $_GET['pw'])), get_permalink()),
        );
    }

    public function groupMetaDescription($description){
        $meta = $this->groupMeta();

        return ($meta)?$meta['description']:$description;
    }

    /**
     * og:/twitter: tags so link previews (Messenger, Facebook, Viber) show the group.
     */
    public function groupSocialTags($tags){
        $meta = $this->groupMeta();

        if(!$meta || !is_array($tags)) return $tags;

        $title = html_entity_decode($meta['title'], ENT_QUOTES, 'UTF-8');
        $description = html_entity_decode($meta['description'], ENT_QUOTES, 'UTF-8');

        // AIOSEO escapes these when printing, so pass plain text.
        foreach(array('og:title', 'twitter:title') as $k){
            if(array_key_exists($k, $tags)) $tags[$k] = $title;
        }

        foreach(array('og:description', 'twitter:description') as $k){
            if(array_key_exists($k, $tags)) $tags[$k] = $description;
        }

        if(array_key_exists('og:url', $tags) && $meta['url']) $tags['og:url'] = $meta['url'];

        return $tags;
    }

    /**
     * Keep group dashboards out of search results: their links contain the group password
     * and the page lists members and wishlists.
     */
    public function groupRobots($robots){
        if(!$this->groupMeta() || !is_array($robots)) return $robots;

        $robots['noindex'] = 'noindex';
        $robots['nofollow'] = 'nofollow';
        unset($robots['index'], $robots['follow']);

        return $robots;
    }

    public function groupWpRobots($robots){
        if(!$this->groupMeta()) return $robots;

        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        unset($robots['index'], $robots['follow']);

        return $robots;
    }

    // Add a custom class to the body
    public function my_custom_body_class($classes) {
        $groupid = $this->getGroupId();  // Replace with your actual group ID
        $fields = $this->getGroupDetails($groupid);
        $pro = (isset($fields['pro']))?$fields['pro']:false;

        if($pro):
            $classes[] = 'pro-version'; // Replace 'my-custom-class' with your desired class name
        endif;
        
        return $classes;
    }

    public function get_user_export() {
        register_rest_route('custom-webhook/v1', '/get-user-export', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => '__return_true', // Allow public access
        ));
    }

    public function get_user_data($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
    
        // Validate that the group_id exists and is of the 'groups' custom post type
        if (get_post_type($group_id) !== 'groups') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    
        // Query 'users' posts where 'groups' field (post object) matches the specified group_id
        $args = array(
            'post_type'      => 'users',
            'posts_per_page' => -1, // Retrieve all matching posts
            'meta_query'     => array(
                array(
                    'key'     => 'groups',   // The ACF field name that links to 'groups' post ID
                    'value'   => $group_id,  // The group ID to match
                    'compare' => '='
                )
            )
        );
    
        // Run the query to get matching users
        $user_query = new WP_Query($args);
        $users = $user_query->posts;
    
        // Prepare response data for each matching user
        $users_data = array();
        foreach ($users as $user) {
            
            $wishlists = array();
            $links = array();

            foreach(get_field('my_wishlists', $user->ID) as $key => $w):
                $wishlists[] =  '['.($key+1).'] '.$w['wishlist_description'];
                $links[] =  ($w['reference_links'])?$w['reference_links']:'';
            endforeach;

            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID),
                'pair_name' => (get_field('matched', $group_id))?get_field('name', get_field('pair', $user->ID)):'',
                'pair_screen' => (get_field('matched', $group_id))?get_field('screen_name', get_field('pair', $user->ID)):'',
                'address_contact' => get_field('my_address_and_contact_details', $user->ID),
                'wishlists' => $wishlists,
                'links' => $links,
                // Include other fields as needed
            );
        }
    
        // Return JSON response with matching users
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'User details retrieved successfully',
            'users' => $users_data
        ), 200);
    }    

    public function edit_user() {
        register_rest_route('custom-webhook/v1', '/edit-user', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_edit_user'),
            'permission_callback' => '__return_true', // Allow public access
        ));
    }

    public function handle_edit_user($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
    
        // Validate that the group_id exists and is of the 'groups' custom post type
        if (get_post_type($group_id) !== 'groups') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    
        // Query 'users' posts where 'groups' field (post object) matches the specified group_id
        $args = array(
            'post_type'      => 'users',
            'posts_per_page' => -1, // Retrieve all matching posts
            'meta_query'     => array(
                array(
                    'key'     => 'groups',   // The ACF field name that links to 'groups' post ID
                    'value'   => $group_id,  // The group ID to match
                    'compare' => '='
                )
            )
        );
    
        // Run the query to get matching users
        $user_query = new WP_Query($args);
        $users = $user_query->posts;
    
        // Prepare response data for each matching user
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID),
                // Include other fields as needed
            );
        }
    
        // Return the response with matching users
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Users details retrieved successfully',
            'users' => $users_data
        ), 200);
    }

    public function trash_user() {
        register_rest_route('custom-webhook/v1', '/trash-user', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_trash_user'),
            'permission_callback' => '__return_true', // Allow public access
        ));
    }

    function handle_trash_user($request) {
        global $wpdb;
        $user_id = $request->get_param('user_id'); // User post ID to trash
    
        // Validate user ID and post type
        if (!$user_id || get_post_type($user_id) !== 'users') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid user ID'), 400);
        }
    
        // Prepare the SQL query to directly update the post status
        $update_sql = $wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %d",
            'trash',
            $user_id
        );
    
        // Execute the query
        $result = $wpdb->query($update_sql);
    
        // Check for errors during the update
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to trash the user due to a database error: ' . $wpdb->last_error
            ), 500);
        } else {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'User trashed successfully'
            ), 200);
        }
    }        

    public function kick_group() {
        register_rest_route('custom-webhook/v1', '/kick', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_kick'),
            'permission_callback' => '__return_true', // Note: Customize for secure access if needed
        ));
    }

    public function handle_kick($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
    
        // Validate that the group_id exists and is of the 'groups' custom post type
        if (get_post_type($group_id) !== 'groups') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    
        // Query 'users' posts where 'groups' field (post object) matches the specified group_id
        $args = array(
            'post_type'      => 'users',
            'posts_per_page' => -1, // Retrieve all matching posts
            'meta_query'     => array(
                array(
                    'key'     => 'groups',   // The ACF field name that links to 'groups' post ID
                    'value'   => $group_id,  // The group ID to match
                    'compare' => '='
                )
            )
        );
    
        // Run the query to get matching users
        $user_query = new WP_Query($args);
        $users = $user_query->posts;
    
        // Prepare response data for each matching user
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID)
                // Include other fields as needed
            );
        }
    
        // Return the response with matching users
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Users data and trash link retrieved successfully',
            'users' => $users_data
        ), 200);
    }

    public function matches_group() {
        register_rest_route('custom-webhook/v1', '/matches', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_matches'),
            'permission_callback' => '__return_true', // Note: Customize for secure access if needed
        ));
    }

    public function handle_matches($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
    
        // Validate that the group_id exists and is of the 'groups' custom post type
        if (get_post_type($group_id) !== 'groups') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    
        // Query 'users' posts where 'groups' field (post object) matches the specified group_id
        $args = array(
            'post_type'      => 'users',
            'posts_per_page' => -1, // Retrieve all matching posts
            'meta_query'     => array(
                array(
                    'key'     => 'groups',   // The ACF field name that links to 'groups' post ID
                    'value'   => $group_id,  // The group ID to match
                    'compare' => '='
                )
            )
        );
    
        // Run the query to get matching users
        $user_query = new WP_Query($args);
        $users = $user_query->posts;
    
        // Prepare response data for each matching user
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID),
                'pair_name' => get_field('name', get_field('pair', $user->ID)),
                'pair_screen' => get_field('screen_name', get_field('pair', $user->ID)),
                // Include other fields as needed
            );
        }
    
        // Return the response with matching users
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Users and pair retrieved successfully',
            'users' => $users_data
        ), 200);
    }

    public function joined_group() {
        register_rest_route('custom-webhook/v1', '/joined', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_joined'),
            'permission_callback' => '__return_true', // Note: Customize for secure access if needed
        ));
    }

    public function handle_joined($request) {
        // Get the group_id from the request
        $group_id = $request->get_param('group_id');
    
        // Validate that the group_id exists and is of the 'groups' custom post type
        if (get_post_type($group_id) !== 'groups') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid group ID'), 400);
        }
    
        // Query 'users' posts where 'groups' field (post object) matches the specified group_id
        $args = array(
            'post_type'      => 'users',
            'posts_per_page' => -1, // Retrieve all matching posts
            'meta_query'     => array(
                array(
                    'key'     => 'groups',   // The ACF field name that links to 'groups' post ID
                    'value'   => $group_id,  // The group ID to match
                    'compare' => '='
                )
            )
        );
    
        // Run the query to get matching users
        $user_query = new WP_Query($args);
        $users = $user_query->posts;
    
        // Prepare response data for each matching user
        $users_data = array();
        foreach ($users as $user) {
            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID),
                'email' => get_field('email', $user->ID),
                // Include other fields as needed
            );
        }
    
        // Return the response with matching users
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Users retrieved successfully',
            'users' => $users_data
        ), 200);
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
                // Get the current value of the 'unshuffle_credits' field
                $current_credits = (int) get_field('unshuffle_credits', $group_id);
                
                // Decrement the unshuffle_credits by 1, ensuring it does not go below 0
                $new_credits = max(0, $current_credits - 1);
    
                // Update the 'unshuffle_credits' field with the new value
                update_field('unshuffle_credits', $new_credits, $group_id);
    
                // Respond with success
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Group updated successfully',
                    'group_id' => $group_id,
                    'matched' => false,
                    'unshuffle_credits' => $new_credits
                ), 200);
            } else {
                // Respond with failure if update failed
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Failed to update group',
                    'group_id' => $group_id
                ), 500);
            }
        } else {
            // Respond with failure if post does not exist or is not of 'groups' type
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid group ID',
                'group_id' => $group_id
            ), 400);
        }
    }    
    

    public function get_pro_list(){
        $gid = (int)$_POST['gid'];
        $fields = $this->getGroupDetails($gid);

        $meta_query = array(
            array(
                'key' => 'groups',
                'value' => $gid,
            ),
        );

        $post_count = $this->get_users_count_with_meta($meta_query);
        $unshuf_count = (int)get_field('unshuffle_credits', $gid);
        $unshuf_action = ($unshuf_count > 0)?'un-shuffle':'add-credits';
        $unshuf_txt = ($unshuf_count > 0)?'Unshuffle':'Add Unshuffle Credits';
        

        $list = "";
        $list .= "<ul class='pro-btns-list'>";
        $list .= (!$fields['matched'] && $post_count > 2)?"<li><a href='#' class='pro-list-btn' data-btn='shuffle'><div><span><i class='fa-solid fa-shuffle'></i></span> Shuffle Group</div></a></li>":"<li><a href='javascript:;' disabled><span><i class='fa-solid fa-shuffle'></i></span> Shuffle Group</a></li>";
        $list .= ($fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='".$unshuf_action."'><div><span><i class='fa-solid fa-clock-rotate-left'></i></span>".$unshuf_txt."</div></a><span class='uncount'>".$unshuf_count."</span></li>":"<li><a href='javascript:;' disabled><div><span><i class='fa-solid fa-clock-rotate-left'></i></span>Unshuffle</div></a><span class='uncount'>".$unshuf_count."</span></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='joined'><div><span><i class='fa-solid fa-user-plus'></i></span>Joined Names</div></a></li>";
        $list .= ($fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='matches'><div><span><i class='fa-solid fa-handshake'></i></span>See Matches</div></a></li>":"<li><a href='javascript:;' disabled><div><span><i class='fa-solid fa-handshake'></i></span>See Matches</div></a></li>";
        $list .= (!$fields['matched'])?"<li><a href='#' class='pro-list-btn' data-btn='kick'><div><span><i class='fa-solid fa-user-xmark'></i></span>Kick Members</div></a></li>":"<li><a href='javascript:;' disabled><div><span><i class='fa-solid fa-user-xmark'></i></span>Kick Members</div></a></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='edit'><div><span><i class='fa-solid fa-pen-to-square'></i></span>Edit Member</div></a></li>";
        $list .= "<li><a href='#' class='pro-list-btn' data-btn='export'><div><span><i class='fa-solid fa-file-arrow-down'></i></span>Export Data</div></a></li>";
        $list .= "</ul>";

        wp_send_json_success($list);
    }

    
    public function replace_group_title($title){
        $meta = $this->groupMeta();

        return ($meta)?$meta['title']:$title;
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
        if($groupid):
            $this->groupid = $groupid;
        else:
            $this->groupid = (isset($_SESSION['groupid']))?$_SESSION['groupid']:$groupid;
        endif;

        return get_fields($this->groupid);
    }

    public function get_users_count_with_meta($meta_query = array()) {
        global $wpdb;
    
        $sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} AS p ";
        $sql .= "LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id ";
        $sql .= "WHERE p.post_type = %s AND p.post_status = %s ";
    
        // Prepare the SQL with post type 'users' and status 'publish'
        $sql = $wpdb->prepare($sql, 'users', 'publish');
    
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
        if(isset($_GET['gid'])){
            return $_GET['gid'];
        }else{

            if(isset($_SESSION['groupid'])){
                return $_SESSION['groupid'];
            }else{
                return false;
            }
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
            if(isset($_GET['gid']) and isset($_GET['pw'])):
                $creds = array($_GET['gid'], $_GET['pw']);
            else:
                $creds = array($_SESSION['groupid'], $_SESSION['grouppw']);
            endif;
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


                    // Retrieve the initial redirect URL (you may have it set as a parameter or derived in your function)
                    $initial_redirect = home_url('/group-dashboard/?rnd='.$this->randString(10).'&pop=try-pro'); // Replace this if you retrieve from elsewhere

                    // Append gid (post_id) and pw (group password)
                    $redirect_url = add_query_arg(
                        array(
                            'gid' => $post_id,
                            'pw'  => $gen
                        ),
                        $initial_redirect
                    );

                    // Redirect to the modified URL
                    wp_redirect($redirect_url);
                    exit; // Ensure no further code is executed
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
        $message = $this->addGiftIdeas($message, $gid, 'email-match');

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

    // Custom function to reliably update a field with retries using ACF
    private function reliable_update_field($field_name, $value, $post_id, $max_retries = 3) {
        $attempts = 0;
        $updated = false;

        while ($attempts < $max_retries && !$updated) {
            // Check the current value
            $current_value = get_field($field_name, $post_id);

            // Only update if the current value is different from the desired value
            if ($current_value !== $value) {
                $update = update_field($field_name, $value, $post_id);

                if ($update) {
                    $updated = true; // Successfully updated
                } else {
                    $attempts++; // Increment the attempt counter
                    sleep(1); // Optional: wait before retrying
                }
            } else {
                $updated = true; // No update needed
            }
        }

        return $updated; // Return whether the update was successful
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

            // Update field for each matched pair using reliable_update_field
            foreach ($match1 as $k => $userId) {
                $updated = $this->reliable_update_field('pair', $match2[$k], $userId, $maxRetries);

                if (!$updated) {
                    $result = false; // If update fails, set result to false
                    $message = 'Pair Update Failed for User ID: ' . $userId;
                    break; // Exit the loop on failure
                }
            }

            // If all went well, proceed to send email notifications
            if ($result) {
                $this->setEmailForMembers($gid);
                $this->reliable_update_field('matched', true, $gid, $maxRetries);
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
		$message = $this->addGiftIdeas($message, $gid, 'email-match');

		$to = $this->getAllMembersEmailsPerGroupId($gid);

		$subject = $e['email_subject'];
		$subject = str_replace('[email_group]', get_field('group_name', $gid), $subject);
        
        parent::sendEmail($to, $subject, $message);
    }

    /**
     * Replaces [email_gift_ideas] with the affiliate gift ideas block,
     * or appends it when the template has no placeholder.
     */
    public function addGiftIdeas($message, $gid, $placement){
        $block = Affiliate::giftIdeasHtml($gid, $placement, true);

        if(strpos($message, '[email_gift_ideas]') !== false){
            return str_replace('[email_gift_ideas]', $block, $message);
        }

        $pos = stripos($message, '</body>');

        return ($pos !== false)?substr_replace($message, $block, $pos, 0):$message.$block;
    }

    public function getEmailTemplate($tag){
        $em = get_posts(array('post_type' => 'emails', 'posts_per_page' => -1));

        foreach($em as $epost){
            $f = get_fields($epost->ID);

            if(isset($f['email_tag']) && $f['email_tag'] == $tag) return $f;
        }

        return false;
    }

    public function scheduleExchangeReminders(){
        if(!wp_next_scheduled('mm_exchange_reminders')){
            wp_schedule_event(time(), 'daily', 'mm_exchange_reminders');
        }
    }

    /**
     * Daily cron: emails members of matched groups whose gift exchange is 3 days away.
     * Uses the "group-reminder" email template. Each group is reminded once.
     */
    public function sendExchangeReminders(){
        $e = $this->getEmailTemplate('group-reminder');

        if(!$e) return;

        // gift_exchange_date returns m/d/Y; compare in the site's timezone.
        $target = wp_date('m/d/Y', strtotime('+3 days'));

        $groups = get_posts(array(
            'post_type'      => 'groups',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array('key' => 'matched', 'value' => '1'),
                array('key' => '_mm_reminder_sent', 'compare' => 'NOT EXISTS'),
            ),
        ));

        foreach($groups as $gid){
            $date = get_field('gift_exchange_date', $gid);

            if(!$date || $date !== $target) continue;

            // Mark first so an overlapping cron run can't send twice.
            if(!add_post_meta($gid, '_mm_reminder_sent', time(), true)) continue;

            $groupName = get_field('group_name', $gid);
            $link = get_permalink(23).'?gid='.$gid.'&pw='.urlencode(get_field('group_password', $gid));

            $message = $e['email_body'];
            $message = str_replace('[email_group]', $groupName, $message);
            $message = str_replace('[email_grouplink]', $link, $message);
            $message = str_replace('[email_date]', DateTime::createFromFormat('m/d/Y', $date)->format('F j, Y'), $message);
            $message = $this->addGiftIdeas($message, $gid, 'email-reminder');

            $subject = str_replace('[email_group]', $groupName, $e['email_subject']);

            foreach($this->getAllMembersEmailsPerGroupId($gid) as $to){
                if($to) parent::sendEmail($to, $subject, $message);
            }
        }
    }
}
