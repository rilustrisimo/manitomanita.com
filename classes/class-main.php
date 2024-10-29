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
        $classes = array('groups', 'logs', 'users', 'wishlists');

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

    }

    public function validateEmail($valid, $value, $field, $input) {
        // Correct email syntax
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return __('Please enter a valid email address.', 'acf');
        }

        // Check domain's MX records
        $domain = substr(strrchr($value, "@"), 1);
        if (!checkdnsrr($domain, 'MX')) {
            return __('The email domain does not have valid MX records.', 'acf');
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

    public function sendEmail($email = array(), $subject, $message, $bcc = false){
		$headers = "From: Manito Manita <info@dev.manitomanita.com>\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        
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
            $redirect = $redirect.'/?rnd='.$this->randString(10);
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
                        'rewrite'				=> array( 'slug' => $a_post_type['post_type'] ),
                        'capability_type'		=> 'post',
                        'has_archive'			=> $a_post_type['has_archive'],
                        'supports'				=> $a_post_type['supports'],
                        'publicly_queryable' 	=> true,
                        'public' 				=> true,
                        'query_var' 			=> true,
                        'menu_icon'				=> $a_post_type['menu_icon']
                    );
    
                    register_post_type( $a_post_type['post_type'], $a_args );
                }
            }
        }
    }

    public function initSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        return true;
    }
}
