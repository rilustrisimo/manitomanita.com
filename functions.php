<?php
/**
 * Swish Design functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Eyorsogood_Design
 */

if ( ! defined( 'THEME_IS_DEV_MODE' ) ) {
	define( 'THEME_IS_DEV_MODE', true );
}

define( 'QED_VERSION', '1.0.0' );
define( 'PARENT_DIR', get_template_directory() );
define( 'PARENT_URL', get_template_directory_uri() );

require PARENT_DIR . '/includes/core.php';
require PARENT_DIR . '/classes/class-main.php';

/**
 * 
 *  Instantiate main class
 */

$theme = new Theme();
$groups = new Groups();
$users = new Users();

/******** */

add_action( 'admin_menu', 'isa_remove_menus', 999 ); 
function isa_remove_menus() { 
     remove_menu_page( 'branding' );
     remove_menu_page( 'wpmudev' );
 }

 /** color picker issue fix */
add_action( 'wp_print_scripts', 'pp_deregister_javascript', 99 );

function pp_deregister_javascript() {
	if(!is_admin())
	{
		 wp_dequeue_script('wp-color-picker');
		 wp_deregister_script( 'wp-color-picker-js-extra' );
		 wp_deregister_script( 'wp-color-picker' );

	}

}

add_action('admin_head', 'my_custom_styles');

function my_custom_styles() {
  echo '<style>
    #adminmenuwrap {
      margin-top:0!important;
    } 
  </style>';
}

/**
 * Plain-CSS UI styles (the LESS build is cached per theme version, so these are separate files).
 * Versioned by file mtime so changes show up immediately.
 */
add_action( 'wp_enqueue_scripts', 'mm_enqueue_ui_styles', 20 );
function mm_enqueue_ui_styles() {
	foreach ( array( 'mm-match', 'mm-dashboard', 'mm-forms' ) as $name ) {
		$file = '/assets/csslib/' . $name . '.css';
		wp_enqueue_style( $name, PARENT_URL . $file, array(), (string) @filemtime( PARENT_DIR . $file ) );
	}
}
