<?php
/**
 * * Main Class. Classes and functions for Manito Manita.
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
 * Class Theme
 */
class Theme {
    protected $user;
    protected $post_types = array(
        /**
         * added classes here
         */
        
        array(
            'post_type'		=> 'groups',
            'singular_name' => 'Group',
            'plural_name'	=> 'Groups',
            'menu_icon' 	=> 'dashicons-universal-access',
            'supports'		=> array( 'title', 'thumbnail')
        ),
        array(
            'post_type'		=> 'users',
            'singular_name' => 'User',
            'plural_name'	=> 'Users',
            'menu_icon' 	=> 'dashicons-universal-access',
            'supports'		=> array( 'title', 'thumbnail', 'comments')
        ),
        array(
            'post_type'		=> 'emails',
            'singular_name' => 'Email Template',
            'plural_name'	=> 'Email Templates',
            'menu_icon' 	=> 'dashicons-universal-access',
            'supports'		=> array( 'title', 'thumbnail')
        ),
        array(
            'post_type'		=> 'collections',
            'singular_name' => 'Email Collection',
            'plural_name'	=> 'Email Collections',
            'menu_icon' 	=> 'dashicons-universal-access',
            'supports'		=> array( 'title', 'thumbnail')
        ),
        array(
            'post_type'		=> 'promos',
            'singular_name' => 'Promo',
            'plural_name'	=> 'Promos',
            'menu_icon' 	=> 'dashicons-universal-access',
            'supports'		=> array( 'title', 'thumbnail')
        )
    );
    

    function __autoload() {
        $classes = array('error-alerts', 'affiliate', 'captcha', 'emails', 'email-deliver', 'email-check', 'seo', 'landing', 'themes', 'qa', 'gift-ai', 'involve', 'payments', 'groups', 'member-rules', 'comment-moderation', 'group-extras', 'repeat', 'registries', 'my-wishlist', 'registry-extras', 'season', 'sale-days', 'email-audience', 'email-center', 'email-center-admin', 'email-ai', 'support-bot', 'account', 'admin-lists', 'insights', 'insights-report', 'insights-google', 'ads', 'ads-paypal', 'ads-portal', 'ads-report', 'logs', 'users', 'member-list', 'wishlists');

        foreach($classes as $value){
            require_once PARENT_DIR . '/classes/class-'. $value .'.php';
        }
    }

	/**
	 * Constructor runs when this class instantiates.
	 *
	 * @param array $config Data via config file.
	 */
	public function __construct( array $config = array() ) {
        $this->initSession();
        $this->__autoload();
        $this->initActions();
        $this->initFilters();
        $this->user = wp_get_current_user();
    }

    protected function initActions() {
        /**
         * 
         * function should be public when adding to an action hook.
         */

        add_action( 'init', array($this, 'createPostTypes')); 
        
    }

    protected function initFilters() {
        /**
         * Place filters here
         */

         add_filter('acf/validate_value/name=your_email', array($this, 'validateEmail'), 10, 4);
         add_filter('acf/validate_value/name=email', array($this, 'validateEmail'), 10, 4);

         // Theme is instantiated in several places; register the front-end field hints once.
         static $hintsHooked = false;

         if(!$hintsHooked){
             $hintsHooked = true;
             add_filter('acf/prepare_field', array($this, 'frontEndFieldHints'));
             add_action('acf/save_post', array($this, 'protectHiddenFields'), 1); // before ACF saves (priority 10)
             // A mistyped URL shows the 404 page instead of redirecting to a "closest match" guess.
             add_filter('do_redirect_guess_404_permalink', '__return_false');
             // Drop the old public URLs of the internal post types (once).
             add_action('init', function(){
                 if(get_option('mm_cpt_private_v1')) return;
                 update_option('mm_cpt_private_v1', time(), false);
                 flush_rewrite_rules(false);
             }, 99);
         }
    }

    /**
     * Placeholders and short explanations for the Create Group / Join Group / Edit forms (front end only).
     */
    /**
     * Front-end forms (Create/Edit Group, Join/Edit Member) render some fields hidden with CSS only.
     * Never save those from a form: PRO status, credits, payment, matches and links are set by code
     * (Payments, the PRO tools, the shuffle). Without this, editing the form could give free PRO.
     */
    /**
     * Official social accounts (footer, homepage, See Match, emails, chat, SEO sameAs).
     * key => array( label, url, Font Awesome icon )
     */
    public static function socials(){
        return array(
            'facebook'  => array('Facebook', 'https://www.facebook.com/manitomanitaph', 'fa-brands fa-facebook-f'),
            'instagram' => array('Instagram', 'https://www.instagram.com/manitomanita', 'fa-brands fa-instagram'),
            'tiktok'    => array('TikTok', 'https://www.tiktok.com/@manitomanitaph', 'fa-brands fa-tiktok'),
        );
    }

    /** Follow buttons; $slot is the GA4 placement (footer, home, match, wishlist, chat...). */
    public static function socialLinks($slot, $class = 'mm-social'){
        $html = '<div class="'.esc_attr($class).'">';
        foreach(self::socials() as $key => $s){
            $html .= '<a href="'.esc_url($s[1]).'" target="_blank" rel="noopener" class="'.esc_attr($class).'__btn '.esc_attr($class).'__btn--'.$key.'" data-mm-social="'.$key.'" data-mm-slot="'.esc_attr($slot).'" aria-label="'.esc_attr($s[0]).'"><i class="'.esc_attr($s[2]).'" aria-hidden="true"></i><span>'.esc_html($s[0]).'</span></a>';
        }
        return $html.'</div>';
    }

    public function protectHiddenFields(){
        if(is_admin() || empty($_POST['acf']) || !is_array($_POST['acf'])) return;

        $locked = array(
            'field_672035cd1110f', // groups: pro
            'field_67258a3f10bcb', // groups: unshuffle_credits
            'field_6720f0ccaba26', // groups: order_id
            'field_5f5cc15d79b68', // groups: matched
            'field_5f55c6cc698b1', // groups: group_password (generated)
            'field_5f55f1f576679', // users: groups (set from the session)
            'field_5f5cc0bb4a1fd', // users: pair (set by the shuffle)
        );

        foreach($locked as $key) unset($_POST['acf'][$key]);
    }

    public function frontEndFieldHints($field){
        if(!$field || is_admin()) return $field;

        $hints = array(
            // Group fields
            'field_5f55be68de4c7' => array('placeholder' => 'e.g. Office Christmas Party 2026'),
            'field_5f55bf05de4ca' => array('placeholder' => 'e.g. 500', 'instructions' => 'The minimum gift budget per person, in pesos (&#8369;).'),
            'field_5f55bf19de4cb' => array('placeholder' => 'Your full name', 'instructions' => 'You&rsquo;ll be the group moderator.'),
            'field_5f55bf2fde4cc' => array('placeholder' => 'you@example.com'),
            'field_5f55bf45de4cd' => array('placeholder' => 'Create a password', 'instructions' => 'You&rsquo;ll use this to manage the group: edit details and shuffle names.'),
            // Member fields
            'field_5f55f0e476675' => array('placeholder' => 'Your full name'),
            'field_5f55f1bc76676' => array('placeholder' => 'e.g. Ana', 'instructions' => 'This is how others in the group will see you.'),
            'field_5f55f1d576677' => array('placeholder' => 'you@example.com'),
            'field_5f55f1e576678' => array('placeholder' => 'Create a password', 'instructions' => 'You&rsquo;ll need this to edit your wishlist and see your match.'),
        );

        if(!isset($hints[$field['key']])) return $field;

        foreach($hints[$field['key']] as $k => $v){
            if($k == 'instructions' && !empty($field['instructions'])) continue; // keep existing instructions
            $field[$k] = $v;
        }

        return $field;
    }

    public function validateEmail($valid, $value, $field, $input) {
        // Correct email syntax
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return __('Please enter a valid email address.', 'acf');
        }

        if (class_exists('EmailCheck') && EmailCheck::isDisposable($value)) {
            return __('Please use your regular email address, not a temporary one.', 'acf');
        }

        // Check domain's MX records
        $domain = substr(strrchr($value, "@"), 1);
        if (!checkdnsrr($domain, 'MX')) {
            return __('The email domain does not have valid MX records and is not valid.', 'acf');
        }

        // Check if email is from a disposable domain using Disify API
        $apiUrl = "https://www.disify.com/api/email/" . $value;

        // Perform the API request
        $response = wp_remote_get($apiUrl);
        
        if (is_wp_error($response)) {
            return __('There was an issue validating your email. Please try again.', 'acf');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($data && $data->disposable) {
            return __('Please use a non-disposable email address.', 'acf');
        }

        


        return $valid;
    }

    public function sendEmail($email, $subject, $message, $bcc = false){
		$headers = "From: Manito Manita <rouie@manitomanita.com>\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        if($bcc){
            //$headers .= "BCC: itsmerouie@gmail.com\r\n";
        }
        
		if(wp_mail($email, $subject, $message, $headers))
		{
			return true;
		}
		else
		{
			return false;
		}
    }

    public function getActivePromos() {
        $promos = $this->createQuery('promos');
        $promocodes = array();

        foreach($promos->posts as $p):
            $expiry = strtotime(get_field('promo_expiry', $p->ID));
            $curdate = strtotime(date('F j, Y'));

            if($expiry >= $curdate):
                $code = get_field('promo_html_code', $p->ID);
                $promocodes[] = $code;
            endif;

        endforeach;

        return $promocodes;
    }

    public function createQuery($posttype, $meta_query = array(), $numberposts = -1, $orderby = 'date', $order = 'DESC') {
        $args = array(
            'orderby'			=> $orderby,
            'order'				=> $order,
            'numberposts'	=> $numberposts,
            'post_type'		=> $posttype,
            'post_status'   => 'publish', // pending (unconfirmed) members never count
            'meta_query'    => array($meta_query),
            'posts_per_page' => $numberposts
        );

        $the_query = new WP_Query( $args );

        return $the_query;
    }

    public function createQuery2($posttype, $meta_query = array(), $numberposts = -1, $orderby = 'date', $order = 'DESC') {
        // Query the database
        $args = array(
            'orderby'       => $orderby,
            'order'         => $order,
            'numberposts'   => $numberposts,
            'post_type'     => $posttype,
            'post_status'   => 'publish', // pending (unconfirmed) members never count
            'meta_query'    => array($meta_query),
            'posts_per_page'=> $numberposts
        );
        
        $the_query = get_posts($args);

        return $the_query;
    }
    
    public function createQuery3($posttype, $meta_query = array(), $posts_per_page = 100, $offset = 0, $orderby = 'date', $order = 'DESC') {
        // Adjust query arguments for batching
        $args = array(
            'orderby'         => $orderby,
            'order'           => $order,
            'post_type'       => $posttype,
            'post_status'   => 'publish', // pending (unconfirmed) members never count
            'meta_query'      => array($meta_query),
            'posts_per_page'  => $posts_per_page,
            'offset'          => $offset
        );
    
        $the_query = get_posts($args);
    
        return $the_query;
    }


    public function createPostQuery($postType, $postPerPage, $pagination = false, $meta_query = array()) {
        $rows = array();
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

        $args = array(
            'post_type' => $postType,
            'post_status' => array('publish'),
            'posts_per_page' => $postPerPage,
            'paged' => $paged,
            'orderby'			=> 'date',
            'order'				=> 'DESC',
            'meta_query'        => $meta_query
        );

        $pagi = '';
    
        $the_query = new WP_Query( $args );
        // The Loop
        if ( $the_query->have_posts() ) {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                $fields = get_fields(get_the_ID());
    
                $rows[get_the_ID()] = $fields;
            } // end while
        } // endif
    
        if($pagination){
            $pagi = '<div class="pagination">'.paginate_links( array(
                'base'         => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'total'        => $the_query->max_num_pages,
                'current'      => max( 1, get_query_var( 'paged' ) ),
                'format'       => '?paged=%#%',
                'show_all'     => false,
                'type'         => 'plain',
                'end_size'     => 2,
                'mid_size'     => 1,
                'prev_next'    => true,
                'prev_text'    => sprintf( '<i></i> %1$s', __( '<i class="fas fa-angle-double-left"></i>', 'text-domain' ) ),
                'next_text'    => sprintf( '%1$s <i></i>', __( '<i class="fas fa-angle-double-right"></i>', 'text-domain' ) ),
                'add_args'     => false,
                'add_fragment' => '',
            ) ).'</div>';
        }
    
        // Reset Post Data
        wp_reset_postdata();
    
        return array($rows, $pagi);
    }

    public function initAcfScripts(){
        return acf_form_head();
    }

    public function createAcfForm($fieldGroupId, $postType, $button = 'Submit', $redirect = null){
        
        if($redirect == 'group-dashboard'){
            $redirect = $redirect.'/?rnd='.$this->randString(10).'&pop=try-pro';
        }

        return 	acf_form(array(
            'post_id'		=> 'new_post',
            'post_title'	=> false,
            'post_content'	=> false,
            'field_groups'	=> array($fieldGroupId),
            'submit_value'	=> $button,
            'new_post'		=> array(
                'post_type'		=> $postType,
                'post_status'	=> 'publish'
            ),
            'form' => true,
            'return' => (is_null($redirect))?home_url():home_url('/'.$redirect),
            'updated_message' => __("Account Created", 'acf'),
            // Turnstile (when its keys are set) + the email code box on Create Group
            'html_after_fields' => Captcha::widget().(($postType == 'groups')?EmailCheck::codeField():'')
                .'<p class="mm-legal-note">By continuing you agree to our <a href="'.esc_url(home_url('/terms/')).'" target="_blank">Terms of Use</a> and <a href="'.esc_url(home_url('/privacy-policy/')).'" target="_blank">Privacy Policy</a>.</p>',
        ));
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

    public function updateAcfForm($postid, $fieldGroupId, $button = 'Update', $redirect = null) {
        return acf_form(array(
            'post_id'		=> $postid,
            'post_title'	=> false,
            'post_content'	=> false,
            'field_groups'	=> array($fieldGroupId),
            'submit_value'	=> $button,
            'form' => true,
            'return' => (is_null($redirect))?home_url():home_url('/'.$redirect)
        ));
    }

    public function createPostTypes() {
        /*
        * Added Theme Post Types
        *
        */
        // Uncomment the $a_post_types declaration to register your custom post type
        
        $a_post_types = $this->post_types;

        if( !empty( $a_post_types ) ) {
            foreach( $a_post_types as $a_post_type ) {
                $a_defaults = array(
                    'supports'		=> $a_post_type['supports'],
                    'has_archive'	=> TRUE
                );
    
                $a_post_type = wp_parse_args( $a_post_type, $a_defaults );
    
                if( !empty( $a_post_type['post_type'] ) ) {
    
                    $a_labels = array(
                        'name'				=> $a_post_type['plural_name'],
                        'singular_name'		=> $a_post_type['singular_name'],
                        'menu_name'			=> $a_post_type['plural_name'],
                        'name_admin_bar'		=> $a_post_type['singular_name'],
                        'add_new_item'			=> 'Add New '.$a_post_type['singular_name'],
                        'new_item'			=> 'New '.$a_post_type['singular_name'],
                        'edit_item'			=> 'Edit '.$a_post_type['singular_name'],
                        'view_item'			=> 'View '.$a_post_type['singular_name'],
                        'all_items'			=> 'All '.$a_post_type['plural_name'],
                        'search_items'			=> 'Search '.$a_post_type['plural_name'],
                        'parent_item_colon'		=> 'Parent '.$a_post_type['plural_name'],
                        'not_found'			=> 'No '.$a_post_type['singular_name'].' found',
                        'not_found_in_trash'	=> 'No '.$a_post_type['singular_name'].' found in Trash'
                    );
    
                    $a_args = array(
                        'labels'				=> $a_labels,
                        'show_in_menu'			=> true,
                        'show_ui'				=> true,
                        // Internal records (groups, members, email templates, collections, promos): editable in
                        // WP Admin, but no public URLs, archives, search results or sitemap entries. The site shows
                        // groups through /group-dashboard/?gid=... only. (Public URLs exposed names and emails.)
                        'rewrite'				=> false,
                        'capability_type'		=> 'post',
                        'has_archive'			=> false,
                        'supports'				=> $a_post_type['supports'],
                        'publicly_queryable' 	=> false,
                        'public' 				=> false,
                        'exclude_from_search'	=> true,
                        'show_in_nav_menus'		=> false,
                        'query_var' 			=> false,
                        'menu_icon'				=> $a_post_type['menu_icon']
                    );
    
                    register_post_type( $a_post_type['post_type'], $a_args );
                }
            }
        }
    }

    public function initSession() {
        if (session_status() != PHP_SESSION_NONE) return true;

        // First visit to a page that may be a marketing page: decide at template_redirect, so the
        // homepage and landing pages can be cached by the CDN (a session adds no-store and a cookie).
        if ($this->canDeferSession()) {
            if (!isset($_SESSION)) $_SESSION = array();
            add_action('template_redirect', array($this, 'startDeferredSession'), 0);
            return true;
        }

        session_start();

        return true;
    }

    /**
     * Plain GET, no session cookie yet, no query (gid/pw, confirm links...), not admin/ajax/REST/cron.
     */
    protected function canDeferSession() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return false;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
        if (isset($_COOKIE[session_name()])) return false;

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('~/wp-(json|login|admin|cron)|xmlrpc|rest_route~', $uri)) return false;

        $query = array_diff_key($_GET, array_flip(array('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid')));
        return empty($query);
    }

    public function startDeferredSession() {
        if (function_exists('mm_is_marketing_page') && mm_is_marketing_page()) {
            header('Cache-Control: public, max-age=0, s-maxage=300');
            return;
        }
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) session_start();
    }
}
