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
        add_action('rest_api_init', array($this, 'members_group'));
        add_action('rest_api_init', array($this, 'exclusions_route'));
        add_action('rest_api_init', array($this, 'pro_extra_routes'));

        // Groups is instantiated in several places; register the cron hooks once.
        static $cronHooked = false;

        if(!$cronHooked){
            $cronHooked = true;
            add_action('init', array($this, 'scheduleExchangeReminders'));
            add_action('mm_exchange_reminders', array($this, 'sendExchangeReminders'));
            add_action('init', array($this, 'scheduleAutoDraws'));
            add_action('mm_auto_draw', array($this, 'runAutoDraws'));
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

    /**
     * Totals for the homepage: groups, members and PRO groups (cached for 12 hours).
     */
    public static function siteStats(){
        $stats = get_transient('mm_site_stats');
        if(is_array($stats)) return $stats;

        global $wpdb;

        $stats = array(
            'groups'  => (int) wp_count_posts('groups')->publish,
            'members' => (int) wp_count_posts('users')->publish,
            'pro'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'groups' AND pm.meta_key = 'pro' AND pm.meta_value = '1'"),
        );

        // Round down to a clean "+" figure (1,234 -> 1,200).
        foreach(array('groups', 'members') as $k){
            if($stats[$k] >= 1000) $stats[$k] = (int) (floor($stats[$k] / 100) * 100);
        }

        set_transient('mm_site_stats', $stats, 12 * HOUR_IN_SECONDS);

        return $stats;
    }

    /* ---------------------------------------------------- Access checks */

    const MOD_TTL = 7200; // a moderator password check is trusted for 2 hours

    /**
     * Called after the group (moderator) password was verified.
     */
    public static function markModerator($gid){
        $_SESSION['mm_mod'][(int) $gid] = time();
    }

    public static function isModerator($gid){
        $gid = (int) $gid;
        $t = (isset($_SESSION['mm_mod'][$gid]))?(int) $_SESSION['mm_mod'][$gid]:0;

        return $gid && get_post_type($gid) === 'groups' && $t > time() - self::MOD_TTL;
    }

    /**
     * REST: PRO tools need a verified moderator of a PRO group.
     */
    public function canUsePro($request){
        $gid = (int) $request->get_param('group_id');

        if(!self::isModerator($gid)) return new WP_Error('mm_forbidden', 'Please enter the moderator password again.', array('status' => 403));
        if(!get_field('pro', $gid)) return new WP_Error('mm_not_pro', 'This is a PRO feature.', array('status' => 403));

        return true;
    }

    /**
     * REST: kicking is for a member of the moderator's own PRO group, before the group is shuffled.
     */
    public function canKickMember($request){
        $uid = (int) $request->get_param('user_id');
        $gid = (get_post_type($uid) === 'users')?(int) get_field('groups', $uid, false):0;

        $request->set_param('group_id', $gid);
        $allowed = $this->canUsePro($request);

        if($allowed !== true) return $allowed;
        if(get_field('matched', $gid)) return new WP_Error('mm_matched', 'Members cannot be removed after the group is shuffled. Unshuffle first.', array('status' => 403));

        return true;
    }

    /**
     * REST: unshuffling needs a matched group and at least one credit.
     */
    public function canUnshuffle($request){
        $allowed = $this->canUsePro($request);
        $gid = (int) $request->get_param('group_id');

        if($allowed !== true) return $allowed;
        if(!get_field('matched', $gid)) return new WP_Error('mm_not_matched', 'The group hasn&rsquo;t been shuffled yet.', array('status' => 403));
        if((int) get_field('unshuffle_credits', $gid) < 1) return new WP_Error('mm_no_credits', 'No unshuffle credits left.', array('status' => 403));

        return true;
    }

    /* ---------------------------------------------------------- Exclusions */

    const EXCLUSIONS_META = '_mm_exclusions'; // array of array(memberA, memberB): they never get each other
    const EXCLUSIONS_MAX  = 50;

    /**
     * Random assignment giver => receiver where nobody gets themselves or a blocked receiver.
     * $blocked is a set: $blocked["giver|receiver"] = true. Returns null when impossible.
     * Random tries first (fast for normal groups), then a randomized backtracking search.
     */
    public static function drawPairs(array $ids, array $blocked = array()){
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $n = count($ids);
        if($n < 2) return null;

        $ok = function($giver, $receiver) use ($blocked){
            return $giver !== $receiver && empty($blocked[$giver.'|'.$receiver]);
        };

        for($try = 0; $try < 300; $try++){
            $receivers = $ids;
            shuffle($receivers);
            $good = true;
            foreach($ids as $k => $g){
                if(!$ok($g, $receivers[$k])){ $good = false; break; }
            }
            if($good) return array_combine($ids, $receivers);
        }

        // Backtracking: most constrained givers first.
        $options = array();
        foreach($ids as $g){
            $options[$g] = array_values(array_filter($ids, function($r) use ($g, $ok){ return $ok($g, $r); }));
            if(!$options[$g]) return null;
            shuffle($options[$g]);
        }
        $order = $ids;
        usort($order, function($a, $b) use ($options){ return count($options[$a]) - count($options[$b]); });

        $result = array();
        $used = array();
        $steps = 0;

        $solve = function($i) use (&$solve, &$result, &$used, &$steps, $order, $options, $n){
            if($i === $n) return true;
            if(++$steps > 200000) return false; // safety valve
            $g = $order[$i];
            foreach($options[$g] as $r){
                if(isset($used[$r])) continue;
                $used[$r] = true;
                $result[$g] = $r;
                if($solve($i + 1)) return true;
                unset($used[$r], $result[$g]);
            }
            return false;
        };

        return $solve(0) ? $result : null;
    }

    /**
     * Exclusions of a group, limited to current members, as rows array(a, b).
     */
    public static function exclusions($gid, $memberIds = null){
        $rows = get_post_meta((int) $gid, self::EXCLUSIONS_META, true);
        $rows = is_array($rows) ? $rows : array();
        if($memberIds === null) return $rows;

        $members = array_flip(array_map('intval', $memberIds));
        return array_values(array_filter($rows, function($r) use ($members){
            return isset($members[(int) $r[0]], $members[(int) $r[1]]);
        }));
    }

    const EXCLUSIONS_MIN_OPTIONS = 2; // in groups of 4+, everyone keeps at least 2 people they could get

    protected static function memberName($id){
        $name = trim((string) get_field('name', $id));
        return $name !== '' ? $name : (string) get_field('screen_name', $id);
    }

    /**
     * Can the group still be shuffled fairly with these rules?
     * Returns array('ok' => bool, 'message' => string, 'tight' => array(member ids with too few options)).
     */
    public static function exclusionHealth(array $ids, array $rows){
        $n = count($ids);
        $out = array('ok' => true, 'message' => '', 'tight' => array());
        if(!$rows) return $out;

        // People each member could still get (everyone but themselves and their rules).
        $options = array();
        foreach($ids as $id) $options[$id] = $n - 1;
        foreach($rows as $r){
            foreach(array((int) $r[0], (int) $r[1]) as $id){ if(isset($options[$id])) $options[$id]--; }
        }

        foreach($options as $id => $left){
            if($left < 1){
                $out['ok'] = false;
                $out['message'] = self::memberName($id).' is excluded from everyone, so they can&rsquo;t get anyone. Remove one of their rules.';
                return $out;
            }
            if($n >= 4 && $left < self::EXCLUSIONS_MIN_OPTIONS) $out['tight'][] = $id;
        }

        if($n < 3){
            $out['ok'] = false;
            $out['message'] = 'Exclusions need at least 3 members.';
        }elseif(self::drawPairs($ids, self::blockedPairs(0, $rows)) === null){
            $out['ok'] = false;
            $out['message'] = 'With the current members, these rules make a fair shuffle impossible (often after someone was removed). Remove a rule or add members.';
        }

        return $out;
    }

    public static function blockedPairs($gid, $rows = null){
        $rows = ($rows === null) ? self::exclusions($gid) : $rows;
        $blocked = array();
        foreach($rows as $r){
            $blocked[(int) $r[0].'|'.(int) $r[1]] = true;
            $blocked[(int) $r[1].'|'.(int) $r[0]] = true;
        }
        return $blocked;
    }

    protected static function memberIds($gid){
        return array_map('intval', get_posts(array(
            'post_type'      => 'users',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(array('key' => 'groups', 'value' => (int) $gid)),
        )));
    }

    public function exclusions_route() {
        register_rest_route('custom-webhook/v1', '/exclusions', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_exclusions'),
            'permission_callback' => array($this, 'canUsePro'),
        ));
    }

    /**
     * op: list | add (a, b) | remove (a, b). Adding is refused when it would make the shuffle impossible.
     */
    public function handle_exclusions($request){
        $gid = (int) $request->get_param('group_id');
        $op = (string) $request->get_param('op');
        $ids = self::memberIds($gid);
        $all = self::exclusions($gid);
        $rows = self::exclusions($gid, $ids);
        if(count($rows) !== count($all)) update_post_meta($gid, self::EXCLUSIONS_META, $rows); // drop rules of removed members
        $error = '';

        $a = (int) $request->get_param('a');
        $b = (int) $request->get_param('b');
        $same = function($r) use ($a, $b){ return ((int) $r[0] === $a && (int) $r[1] === $b) || ((int) $r[0] === $b && (int) $r[1] === $a); };

        if($op === 'add'){
            if(!in_array($a, $ids, true) || !in_array($b, $ids, true) || $a === $b){
                $error = 'Pick two different members.';
            }elseif(array_filter($rows, $same)){
                $error = 'That rule already exists.';
            }elseif(count($rows) >= self::EXCLUSIONS_MAX){
                $error = 'That&rsquo;s the maximum number of rules.';
            }elseif(count($ids) < 4){
                $error = 'Exclusions need at least 4 members (with 3, any rule leaves no fair way to shuffle). Invite more people first.';
            }else{
                $try = array_merge($rows, array(array($a, $b)));
                $health = self::exclusionHealth($ids, $try);

                if(!$health['ok']){
                    $error = $health['message'];
                }elseif(array_intersect(array($a, $b), $health['tight'])){
                    $who = in_array($a, $health['tight'], true) ? $a : $b;
                    $error = 'With this rule '.self::memberName($who).' could only get one person, so the shuffle wouldn&rsquo;t be a surprise. Remove another rule for them first.';
                }else{
                    $rows = $try;
                    update_post_meta($gid, self::EXCLUSIONS_META, $rows);
                }
            }
        }elseif($op === 'remove'){
            $rows = array_values(array_filter($rows, function($r) use ($same){ return !$same($r); }));
            update_post_meta($gid, self::EXCLUSIONS_META, $rows);
        }

        $label = function($id){
            $name = trim((string) get_field('name', $id));
            return array('ID' => $id, 'name' => $name !== '' ? $name : (string) get_field('screen_name', $id), 'screen' => (string) get_field('screen_name', $id));
        };

        $health = self::exclusionHealth($ids, $rows);

        return new WP_REST_Response(array(
            'success' => $error === '',
            'message' => $error,
            'health'  => array('ok' => $health['ok'], 'message' => $health['message']),
            'matched' => (bool) get_field('matched', $gid),
            'members' => array_map($label, $ids),
            'rules'   => array_map(function($r) use ($label){ return array($label((int) $r[0]), $label((int) $r[1])); }, $rows),
        ), 200);
    }

    public function members_group() {
        register_rest_route('custom-webhook/v1', '/members', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_members'),
            'permission_callback' => array($this, 'canUsePro'),
        ));
    }

    /**
     * PRO members manager / matches view: one list with everything the moderator tools need.
     */
    public function handle_members($request) {
        $gid = (int) $request->get_param('group_id');
        $matched = (bool) get_field('matched', $gid);

        $q = new WP_Query(array(
            'post_type'      => 'users',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(array('key' => 'groups', 'value' => $gid, 'compare' => '=')),
            'orderby'        => 'date',
            'order'          => 'ASC',
        ));

        $users = array();
        foreach($q->posts as $u){
            $wishes = get_field('my_wishlists', $u->ID);
            $pair = $matched ? (int) get_field('pair', $u->ID, false) : 0;
            $users[] = array(
                'ID'          => $u->ID,
                'name'        => (string) get_field('name', $u->ID),
                'screen'      => (string) get_field('screen_name', $u->ID),
                'email'       => (string) get_field('email', $u->ID),
                'wishes'      => is_array($wishes) ? count($wishes) : 0,
                'confirmed'   => EmailCheck::isConfirmed($u->ID),
                'pair_name'   => $pair ? (string) get_field('name', $pair) : '',
                'pair_screen' => $pair ? (string) get_field('screen_name', $pair) : '',
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'group'   => array(
                'name'    => (string) get_field('group_name', $gid),
                'matched' => $matched,
                'credits' => (int) get_field('unshuffle_credits', $gid),
            ),
            'users'   => $users,
        ), 200);
    }

    public function get_user_export() {
        register_rest_route('custom-webhook/v1', '/get-user-export', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => array($this, 'canUsePro'),
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
            'post_status'    => 'publish',
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

            foreach((array) get_field('my_wishlists', $user->ID) as $key => $w):
                $wishlists[] =  '['.($key+1).'] '.$w['wishlist_description'];
                $links[] =  ($w['reference_links'])?$w['reference_links']:'';
            endforeach;

            $users_data[] = array(
                'ID' => $user->ID,
                'name' => get_field('name', $user->ID),
                'screen' => get_field('screen_name', $user->ID),
                'email' => get_field('email', $user->ID),
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
            'permission_callback' => array($this, 'canUsePro'),
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
            'post_status'    => 'publish',
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
            'permission_callback' => array($this, 'canKickMember'),
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
            'permission_callback' => array($this, 'canUsePro'),
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
            'post_status'    => 'publish',
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
            'permission_callback' => array($this, 'canUsePro'),
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
            'post_status'    => 'publish',
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
            'permission_callback' => array($this, 'canUsePro'),
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
            'post_status'    => 'publish',
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
            'permission_callback' => array($this, 'canUnshuffle'),
        ));
    }

    /**
     * After an unshuffle: clear pairs, gift status and "match opened"; optionally email members that
     * their match is cancelled and the group is open again.
     */
    public function resetMatches($gid, $notify = true){
        $name = (string) get_field('group_name', $gid);

        foreach(self::memberIds($gid) as $uid){
            delete_post_meta($uid, '_mm_gift_status');
            delete_post_meta($uid, '_mm_match_viewed');
            update_field('pair', '', $uid);

            if(!$notify || !EmailCheck::isConfirmed($uid)) continue;

            $first = trim((string) get_field('screen_name', $uid));
            Emails::send((string) get_field('email', $uid), $name.' has been unshuffled', array(
                'preheader' => 'Your previous match is cancelled. You&rsquo;ll get a new one when the group is shuffled again.',
                'eyebrow'   => 'Heads up',
                'title'     => $name.' has been unshuffled',
                'body'      => array(
                    '<p>Hi '.esc_html($first !== '' ? $first : 'there').', the organizer unshuffled the group, usually so more people can join.</p>',
                    '<p><strong>Your previous match is cancelled, so please don&rsquo;t buy a gift for them yet.</strong> You&rsquo;ll get an email with your new match when the group is shuffled again.</p>',
                ),
                'card'      => Emails::groupCard($gid),
                'button'    => array('Open the group', Emails::groupLink($gid)),
                'footer'    => 'You&rsquo;re getting this because you&rsquo;re a member of '.esc_html($name).' on Manito Manita.',
            ));
        }
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

                // Old matches are void: reset per-member state, and tell members (not when reshuffling at once).
                $this->resetMatches((int) $group_id, $request->get_param('notify') !== false);
    
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

        if(!self::isModerator($gid) || !get_field('pro', $gid)) wp_send_json_error('Please enter the moderator password again.');

        $fields  = $this->getGroupDetails($gid);
        $matched = !empty($fields['matched']);
        $count   = (int) $this->get_users_count_with_meta(array(array('key' => 'groups', 'value' => $gid)));
        $credits = (int) get_field('unshuffle_credits', $gid);
        $unconfirmed = 0;
        if(!$matched){
            foreach(get_posts(array('post_type' => 'users', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array(array('key' => 'groups', 'value' => $gid)))) as $uid){
                if(!EmailCheck::isConfirmed($uid)) $unconfirmed++;
            }
        }

        $tool = function($btn, $icon, $title, $desc, $disabled = '', $extra = ''){
            $attr = $disabled ? ' disabled aria-disabled="true"' : ' data-btn="'.esc_attr($btn).'"';
            return '<button type="button" class="mm-prot__tool'.($disabled ? ' is-off' : '').'"'.$attr.'>'
                .'<span class="mm-prot__icon"><i class="'.esc_attr($icon).'"></i></span>'
                .'<span class="mm-prot__txt"><strong>'.$title.'</strong><small>'.($disabled ? esc_html($disabled) : $desc).'</small></span>'
                .$extra.'<i class="fa-solid fa-chevron-right mm-prot__go" aria-hidden="true"></i></button>';
        };

        $html  = '<div class="mm-prot" data-group-name="'.esc_attr((string) ($fields['group_name'] ?? '')).'" data-matched="'.($matched ? '1' : '0').'" data-credits="'.$credits.'" data-unconfirmed="'.$unconfirmed.'">';
        $html .= '<div class="mm-prot__head"><div><span class="mm-prot__badge"><i class="fa-solid fa-star"></i> PRO</span>';
        $html .= '<h3>'.esc_html((string) ($fields['group_name'] ?? 'Your group')).'</h3>';
        $html .= '<p>'.$count.' '.($count == 1 ? 'member' : 'members').' &middot; '.($matched ? 'Shuffled' : 'Not shuffled yet').'</p></div>';
        $html .= '<div class="mm-prot__credits"><strong>'.$credits.'</strong><span>'.($credits == 1 ? 'unshuffle' : 'unshuffles').' left</span></div></div>';

        $html .= '<h4 class="mm-prot__group">Members</h4>';
        $html .= $tool('members', 'fa-solid fa-users', 'Members', 'Names and emails, edit or remove members');

        $html .= '<h4 class="mm-prot__group">Shuffle</h4>';
        $exIds = self::memberIds($gid);
        $exRows = self::exclusions($gid, $exIds);
        $rules = count($exRows);
        $exHealth = self::exclusionHealth($exIds, $exRows);
        $html .= $tool('exclusions', 'fa-solid fa-user-slash', 'Exclusions', $exHealth['ok']
            ? ($rules ? $rules.' '.($rules == 1 ? 'rule' : 'rules').' &middot; ' : '').'Couples or family who shouldn&rsquo;t get each other'
            : '<span class="mm-prot__warn">&#9888; Rules need fixing before you can shuffle</span>');
        if(!$matched){
            $html .= $tool('shuffle', 'fa-solid fa-shuffle', 'Shuffle', 'Everyone gets their match by email', ($count < 3) ? 'Needs at least 3 members' : '');
        }elseif($credits > 0){
            $html .= $tool('unshuffle', 'fa-solid fa-rotate-left', 'Unshuffle', 'Reopen the group so late joiners can get in, then shuffle again &middot; uses 1 of '.$credits);
            $html .= $tool('reshuffle', 'fa-solid fa-shuffle', 'Reshuffle now', 'New matches right away, same members &middot; uses 1 of '.$credits);
        }else{
            $html .= $tool('add-credits', 'fa-solid fa-plus', 'Add 3 more unshuffles', 'You&rsquo;ve used all unshuffles &middot; &#8369;99');
        }

        // Reminders & schedule
        $st = self::memberStatus($gid);
        $html .= '<h4 class="mm-prot__group">Reminders</h4>';
        $noWish = count($st['no_wishlist']);
        $html .= $tool('nudge-wishlist', 'fa-solid fa-envelope', 'Remind: add a wishlist',
            $noWish.' '.($noWish == 1 ? 'member has' : 'members have').' no wishlist yet',
            self::nudgedToday($gid, 'wishlist') ? 'Sent today. You can send it again tomorrow' : ($noWish ? '' : 'Everyone has a wishlist'));
        if($matched){
            $notSeen = count($st['not_viewed']);
            $html .= $tool('nudge-match', 'fa-solid fa-bell', 'Remind: open their match',
                $notSeen.' '.($notSeen == 1 ? 'member hasn&rsquo;t' : 'members haven&rsquo;t').' opened their match',
                self::nudgedToday($gid, 'match') ? 'Sent today. You can send it again tomorrow' : ($notSeen ? '' : 'Everyone has opened their match'));
            $html .= $tool('gifts', 'fa-solid fa-gift', 'Gift status', $st['bought'].' bought &middot; '.$st['given'].' given &middot; '.$st['total'].' members');
        }else{
            $at = (int) get_post_meta($gid, self::AUTODRAW_META, true);
            $html .= $tool('autodraw', 'fa-solid fa-calendar-check', 'Auto-shuffle', $at ? 'Scheduled: '.esc_html(wp_date('M j, g:i A', $at)) : 'Shuffle automatically on a date you pick');
        }

        // Weekly Drops (optional weekly themes) + reveal
        $themes = Themes::get($gid);
        $tNow = Themes::now($gid);
        $html .= '<h4 class="mm-prot__group">Fun extras (optional)</h4>';
        $html .= $tool('themes', 'fa-solid fa-gift', 'Weekly Drops', $themes
            ? count($themes).' '.(count($themes) == 1 ? 'drop' : 'drops').($tNow['current'] ? ' &middot; now: '.esc_html($tNow['current']['t']) : ' &middot; first drop '.esc_html(wp_date('M j', strtotime($themes[0]['d']))))
            : 'A themed mini-gift each week (something sweet, something soft&hellip;)');
        if($matched){
            $html .= $tool('reveal', 'fa-solid fa-masks-theater', Themes::isRevealed($gid) ? 'Hide the reveal' : 'Reveal who gave to whom',
                Themes::isRevealed($gid) ? 'Everyone can see all pairs on the group page' : 'For after the exchange: show all pairs to the group');
        }

        $html .= '<h4 class="mm-prot__group">Results</h4>';
        $html .= $tool('matches', 'fa-solid fa-handshake', 'See matches', 'Who is buying for whom', $matched ? '' : 'Available after the shuffle');
        $html .= $tool('export', 'fa-solid fa-file-arrow-down', 'Export to spreadsheet', 'Names, emails, matches, wishlists and addresses');
        $html .= '</div>';

        // Let the Shuffle confirm sheet know when exclusion rules are broken.
        $html = preg_replace('~^<div class="mm-prot"~', '<div class="mm-prot" data-ex-ok="'.($exHealth['ok'] ? '1' : '0').'"', $html, 1);

        wp_send_json_success($html);
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

    /**
     * Organizer: "Your group is ready" with the share link and next steps.
     */
    public function setEmailForCreateGroup($gid) {
        $name = (string) get_field('group_name', $gid);
        $organizer = trim((string) get_field('your_name', $gid));
        $link = Emails::groupLink($gid);

        Emails::send((string) get_field('your_email', $gid), 'Your group '.$name.' is ready', array(
            'preheader' => 'Share the link so everyone can join. Here&rsquo;s everything you need.',
            'eyebrow'   => 'Group created',
            'title'     => 'Your group is ready'.($organizer !== '' ? ', '.$organizer : '').'!',
            'body'      => array(
                '<p>Here&rsquo;s how it works from here:</p>',
                '<ol><li><strong>Share the group link</strong> below on Messenger, Viber or email.</li><li>Members <strong>join and confirm their email</strong>, then add a wishlist.</li><li>When everyone is in, open the group and <strong>shuffle</strong>. Everyone gets their match by email.</li></ol>',
                '<p><strong>Your group link</strong> (share this with members):<br><a href="'.esc_url($link).'" style="color:#1f8580;word-break:break-all;">'.esc_html($link).'</a></p>',
            ),
            'card'      => Emails::groupCard($gid),
            'button'    => array('Open your group', $link),
            'note'      => 'Keep your organizer password safe: you need it to edit the group and shuffle. Forgot it? Use &ldquo;Forgot password&rdquo; on the group page.'
                .'<br><br><strong>Optional PRO extras (&#8369;199 one-time):</strong> exclusions for couples, Weekly Drops (a themed mini-gift each week), unshuffle for late joiners, reminders and no ads. <a href="'.esc_url($link.'&pop=pro-drops').'" style="color:#1f8580;">See PRO</a>',
            'footer'    => 'You&rsquo;re getting this because you created '.esc_html($name).' on Manito Manita.',
        ));
    }

    public function setEmailForGroupMatched($gid){
        $this->setEmailForMembers($gid);
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
        $gid = (int) $_POST['gid'];

        // Only the group's moderator, and only once (PRO unshuffle resets 'matched' first).
        if(!self::isModerator($gid)) wp_send_json_error(['error' => 'Please enter the moderator password again']);
        if(get_field('matched', $gid)) wp_send_json_error(['error' => 'This group has already been shuffled']);

        try {
            $draw = $this->runDraw($gid);
        } catch (Exception $e) {
            wp_send_json_error(['error' => $e->getMessage()]);
            return;
        }

        if($draw['result']) delete_post_meta($gid, self::AUTODRAW_META);

        wp_send_json_success($draw);
    }

    /**
     * Draws names for a group (moderator button or scheduled auto-draw): saves each member's pair,
     * emails everyone and marks the group matched. Returns array('result' => bool, 'message' => string).
     */
    public function runDraw($gid){
        $gid = (int) $gid;
        $maxRetries = 3;

        $match1 = array();
        foreach(parent::createQuery('users', array('key' => 'groups', 'value' => $gid))->posts as $user){
            $match1[] = $user->ID;
        }

        if(count($match1) < 2) return array('result' => false, 'message' => 'At least 2 members are needed to shuffle');

        // Nobody draws themselves; PRO groups also respect their exclusions.
        $blocked = get_field('pro', $gid) ? self::blockedPairs($gid) : array();
        $draw = self::drawPairs($match1, $blocked);

        if($draw === null) return array('result' => false, 'message' => 'These exclusions make a fair shuffle impossible. Remove one in PRO Actions > Exclusions and try again');

        foreach($match1 as $userId){
            if(!$this->reliable_update_field('pair', $draw[$userId], $userId, $maxRetries)){
                return array('result' => false, 'message' => 'Pair Update Failed for User ID: '.$userId);
            }
            delete_post_meta($userId, '_mm_match_viewed'); // a new match has to be opened again
        }

        $this->setEmailForMembers($gid);
        $this->reliable_update_field('matched', true, $gid, $maxRetries);

        return array('result' => true, 'message' => 'success');
    }

    /* ------------------------------------------- Reminders, auto-draw, gifts (PRO) */

    const AUTODRAW_META = '_mm_autodraw_at';   // unix time the names are drawn automatically
    const NUDGE_META    = '_mm_nudge_';        // + type: unix time of the last reminder of that type

    /**
     * Member lists the PRO tools need: no wishlist, match not opened, gift status.
     */
    public static function memberStatus($gid){
        $out = array('total' => 0, 'no_wishlist' => array(), 'not_viewed' => array(), 'bought' => 0, 'given' => 0, 'members' => array());

        foreach(self::memberIds($gid) as $uid){
            $name = trim((string) get_field('name', $uid));
            $row = array(
                'ID'     => $uid,
                'name'   => $name !== '' ? $name : (string) get_field('screen_name', $uid),
                'screen' => (string) get_field('screen_name', $uid),
                'gift'   => (string) get_post_meta($uid, '_mm_gift_status', true),
                'viewed' => (bool) get_post_meta($uid, '_mm_match_viewed', true),
            );
            $out['total']++;
            if(!get_field('my_wishlists', $uid)) $out['no_wishlist'][] = $uid;
            if(!$row['viewed']) $out['not_viewed'][] = $uid;
            if($row['gift'] === 'bought') $out['bought']++;
            if($row['gift'] === 'given') $out['given']++;
            $out['members'][] = $row;
        }

        return $out;
    }

    public static function nudgedToday($gid, $type){
        return (int) get_post_meta((int) $gid, self::NUDGE_META.$type, true) > time() - DAY_IN_SECONDS;
    }

    public function pro_extra_routes(){
        foreach(array('nudge' => 'handle_nudge', 'autodraw' => 'handle_autodraw', 'gifts' => 'handle_gifts') as $route => $cb){
            register_rest_route('custom-webhook/v1', '/'.$route, array(
                'methods'             => 'POST',
                'callback'            => array($this, $cb),
                'permission_callback' => array($this, 'canUsePro'),
            ));
        }
    }

    /**
     * type: wishlist (members with no wishlist) | match (members who haven't opened their match).
     * Once per type per day; members who never confirmed their email are skipped.
     */
    public function handle_nudge($request){
        $gid = (int) $request->get_param('group_id');
        $type = (string) $request->get_param('type');
        $matched = (bool) get_field('matched', $gid);

        if(!in_array($type, array('wishlist', 'match'), true)) return new WP_REST_Response(array('success' => false, 'message' => 'Unknown reminder.'), 200);
        if($type === 'match' && !$matched) return new WP_REST_Response(array('success' => false, 'message' => 'The group hasn&rsquo;t been shuffled yet.'), 200);
        if(self::nudgedToday($gid, $type)) return new WP_REST_Response(array('success' => false, 'message' => 'You already sent this reminder today. Try again tomorrow.'), 200);

        $st = self::memberStatus($gid);
        $targets = ($type === 'wishlist') ? $st['no_wishlist'] : $st['not_viewed'];

        $name = (string) get_field('group_name', $gid);
        $link = Emails::groupLink($gid);
        $date = get_field('gift_exchange_date', $gid);
        $when = ($date && ($d = DateTime::createFromFormat('m/d/Y', $date))) ? $d->format('F j') : '';
        $ideas = ($type === 'match') ? Affiliate::giftIdeasHtml($gid, 'email-nudge', true) : '';

        $sent = 0;
        foreach($targets as $uid){
            $to = (string) get_field('email', $uid);
            if(!is_email($to) || !EmailCheck::isConfirmed($uid) || Emails::optedOut($uid)) continue;

            $first = trim((string) get_field('screen_name', $uid));
            $hi = 'Hi '.esc_html($first !== '' ? $first : 'there').', ';

            $ok = ($type === 'wishlist')
                ? Emails::send($to, 'Add your wishlist for '.$name, array(
                    'preheader'   => 'Help your Secret Santa pick something you&rsquo;ll love. It takes a minute.',
                    'eyebrow'     => 'Friendly reminder',
                    'title'       => 'Add your wishlist for '.$name,
                    'body'        => array('<p>'.$hi.'your Secret Santa will have a much easier time finding you a gift you&rsquo;ll love if you add a few wishes. Links to Lazada or Shopee items work great.</p>'),
                    'card'        => Emails::groupCard($gid),
                    'button'      => array('Add my wishlist', $link),
                    'unsubscribe' => $uid,
                    'footer'      => 'Sent by your group organizer through Manito Manita.',
                ))
                : Emails::send($to, $name.': see who you&rsquo;re giving a gift to', array(
                    'preheader'   => 'Your match is waiting'.($when ? ', and the exchange is on '.$when : '').'.',
                    'eyebrow'     => 'Friendly reminder',
                    'title'       => 'Your match is waiting',
                    'body'        => array('<p>'.$hi.'the group has been shuffled, but you haven&rsquo;t opened your match yet. Find your name in the group and tap <strong>See Match</strong>.</p>'),
                    'card'        => Emails::groupCard($gid),
                    'button'      => array('See my match', $link),
                    'raw'         => $ideas,
                    'unsubscribe' => $uid,
                    'footer'      => 'Sent by your group organizer through Manito Manita.',
                ));

            if($ok) $sent++;
        }

        update_post_meta($gid, self::NUDGE_META.$type, time());

        return new WP_REST_Response(array('success' => true, 'sent' => $sent, 'message' => $sent ? '' : 'Nobody needed this reminder.'), 200);
    }

    /**
     * op: get | set (at = "YYYY-MM-DDTHH:MM" in the site's timezone) | clear.
     */
    public function handle_autodraw($request){
        $gid = (int) $request->get_param('group_id');
        $op = (string) $request->get_param('op');
        $error = '';

        if($op === 'set'){
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', (string) $request->get_param('at'), wp_timezone());

            if(get_field('matched', $gid)){
                $error = 'This group has already been shuffled.';
            }elseif(!$dt || $dt->getTimestamp() < time() + 10 * MINUTE_IN_SECONDS){
                $error = 'Pick a date and time at least 10 minutes from now.';
            }else{
                update_post_meta($gid, self::AUTODRAW_META, $dt->getTimestamp());
            }
        }elseif($op === 'clear'){
            delete_post_meta($gid, self::AUTODRAW_META);
        }

        $at = (int) get_post_meta($gid, self::AUTODRAW_META, true);

        return new WP_REST_Response(array(
            'success' => $error === '',
            'message' => $error,
            'at'      => $at ? wp_date('Y-m-d\TH:i', $at) : '',
            'label'   => $at ? wp_date('l, F j \a\t g:i A', $at) : '',
            'members' => count(self::memberIds($gid)),
        ), 200);
    }

    public function handle_gifts($request){
        $st = self::memberStatus((int) $request->get_param('group_id'));

        return new WP_REST_Response(array(
            'success' => true,
            'total'   => $st['total'],
            'bought'  => $st['bought'],
            'given'   => $st['given'],
            'members' => array_map(function($m){ return array('name' => $m['name'], 'screen' => $m['screen'], 'gift' => $m['gift'], 'viewed' => $m['viewed']); }, $st['members']),
        ), 200);
    }

    /**
     * Hourly cron: draws names for PRO groups whose scheduled time has passed, then tells the moderator.
     */
    public function runAutoDraws(){
        $due = get_posts(array(
            'post_type'      => 'groups',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'meta_query'     => array(array('key' => self::AUTODRAW_META, 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC')),
        ));

        foreach($due as $gid){
            delete_post_meta($gid, self::AUTODRAW_META); // one attempt per schedule

            if(get_field('matched', $gid) || !get_field('pro', $gid)) continue;

            $count = count(self::memberIds($gid));
            $draw = ($count >= 3) ? $this->runDraw($gid) : array('result' => false, 'message' => 'At least 3 members are needed to shuffle');

            $to = (string) get_field('your_email', $gid);
            if(!is_email($to)) continue;

            $name = (string) get_field('group_name', $gid);
            Emails::send($to, $draw['result'] ? $name.' has been shuffled' : $name.': the automatic shuffle didn&rsquo;t run', $draw['result']
                ? array(
                    'preheader' => 'All '.$count.' members have their match.',
                    'eyebrow'   => 'Auto-shuffle done',
                    'title'     => $name.' has been shuffled',
                    'body'      => array('<p>Your group was shuffled automatically as scheduled. All '.$count.' members have been emailed their match.</p>'),
                    'card'      => Emails::groupCard($gid),
                    'button'    => array('Open your group', Emails::groupLink($gid)),
                    'footer'    => 'You&rsquo;re getting this because you organize '.esc_html($name).' on Manito Manita.',
                )
                : array(
                    'preheader' => 'Open your group to fix it and shuffle.',
                    'eyebrow'   => 'Action needed',
                    'title'     => 'The automatic shuffle didn&rsquo;t run',
                    'body'      => array('<p>We couldn&rsquo;t shuffle <strong>'.esc_html($name).'</strong> as scheduled: '.esc_html($draw['message']).'.</p>', '<p>Open your group to fix it, then shuffle from PRO Actions.</p>'),
                    'card'      => Emails::groupCard($gid),
                    'button'    => array('Open your group', Emails::groupLink($gid)),
                    'footer'    => 'You&rsquo;re getting this because you organize '.esc_html($name).' on Manito Manita.',
                ));
        }
    }

    public function scheduleAutoDraws(){
        if(!wp_next_scheduled('mm_auto_draw')) wp_schedule_event(time() + 300, 'hourly', 'mm_auto_draw');
    }

    /**
     * After a shuffle: one email per member (never a shared To: list) with who they're giving to.
     */
    public function setEmailForMembers($gid){
        $name = (string) get_field('group_name', $gid);
        $link = Emails::groupLink($gid);
        $ideas = Affiliate::giftIdeasHtml($gid, 'email-match', true);
        $proLine = get_field('pro', $gid) ? '' : 'Want extra fun this season? Add <a href="'.esc_url($link.'&pop=pro-drops').'" style="color:#1f8580;">Weekly Drops</a> to your group: a themed mini-gift each week, plus a reveal at the end (PRO).';

        foreach(self::memberIds($gid) as $uid){
            $pair = (int) get_field('pair', $uid, false);
            $pairName = $pair ? trim((string) get_field('screen_name', $pair)) : '';
            $first = trim((string) get_field('screen_name', $uid));

            Emails::send((string) get_field('email', $uid), $name.' has been shuffled! See who you&rsquo;re giving a gift to', array(
                'preheader' => 'Your Secret Santa match is ready. Open it to see their wishlist.',
                'eyebrow'   => 'The group has been shuffled',
                'title'     => 'Hi '.($first !== '' ? $first : 'there').', your match is ready!',
                'highlight' => $pairName !== '' ? array('You&rsquo;re giving a gift to', $pairName) : array(),
                'body'      => array(
                    '<p>Open the group to see their <strong>wishlist</strong>, address and gift ideas within the budget. Keep it a secret!</p>',
                ),
                'card'      => Emails::groupCard($gid),
                'button'    => array('See their wishlist', $link),
                'raw'       => $ideas,
                'note'      => $proLine,
                'footer'    => 'You&rsquo;re getting this because you&rsquo;re a member of '.esc_html($name).' on Manito Manita.',
            ));
        }
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
     * Uses the "group-reminder" email template (a built-in one when none exists). Each group is reminded once.
     */
    public function sendExchangeReminders(){
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

            $name = (string) get_field('group_name', $gid);
            $when = DateTime::createFromFormat('m/d/Y', $date)->format('l, F j');
            $ideas = Affiliate::giftIdeasHtml($gid, 'email-reminder', true);

            // Reminders skip members who opted out or never confirmed their email.
            foreach(self::memberIds($gid) as $uid){
                if(!EmailCheck::isConfirmed($uid) || Emails::optedOut($uid)) continue;

                $pair = (int) get_field('pair', $uid, false);
                $pairName = $pair ? trim((string) get_field('screen_name', $pair)) : '';
                $done = get_post_meta($uid, '_mm_gift_status', true);

                Emails::send((string) get_field('email', $uid), '3 days to go: '.$name.' gift exchange', array(
                    'preheader'   => 'The exchange is on '.$when.'. '.($done ? 'You&rsquo;re all set!' : 'Still need a gift? Here are some ideas.'),
                    'eyebrow'     => '3 days to go',
                    'title'       => 'The '.$name.' exchange is on '.$when,
                    'highlight'   => $pairName !== '' ? array('Your gift is for', $pairName) : array(),
                    'body'        => array($done
                        ? '<p>You marked your gift as ready. Nice work! See you at the exchange.</p>'
                        : '<p>Haven&rsquo;t bought your gift yet? Check their wishlist, or pick from the ideas below.</p>'),
                    'card'        => Emails::groupCard($gid),
                    'button'      => array('See their wishlist', Emails::groupLink($gid)),
                    'raw'         => $done ? '' : $ideas,
                    'unsubscribe' => $uid,
                    'footer'      => 'You&rsquo;re getting this reminder because you&rsquo;re a member of '.esc_html($name).' on Manito Manita.',
                ));
            }
        }
    }
}
