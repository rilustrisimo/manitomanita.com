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

require PARENT_DIR . '/includes/env.php'; // .env secrets, before the classes read them
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
	$names = array( 'mm-header', 'mm-footer', 'mm-match', 'mm-dashboard', 'mm-forms', 'mm-pro' );
	if ( is_page_template( 'template-homepage.php' ) || ( class_exists( 'Landing' ) && Landing::isActive() ) ) $names[] = 'mm-home';

	foreach ( $names as $name ) {
		$file = '/assets/csslib/' . $name . '.css';
		wp_enqueue_style( $name, PARENT_URL . $file, array(), (string) @filemtime( PARENT_DIR . $file ) );
	}
}

/**
 * Page speed on the public marketing pages (homepage, landing pages): every script prints in the footer,
 * and styles that are only used by popups and forms (Create Group, Fancybox, ACF) load without blocking
 * the first paint. Analytics and ads keep their own async tags.
 */
function mm_is_marketing_page() {
	return ! is_admin() && ( is_page_template( 'template-homepage.php' ) || ( class_exists( 'Landing' ) && Landing::isActive() ) );
}

add_action( 'wp_print_scripts', 'mm_scripts_to_footer', 1 );
function mm_scripts_to_footer() {
	if ( ! mm_is_marketing_page() ) return;
	$scripts = wp_scripts();
	foreach ( $scripts->registered as $handle => $script ) {
		if ( $script->src && preg_match( '~gtag|googletagmanager|googlesyndication~', $script->src ) ) continue;
		$scripts->add_data( $handle, 'group', 1 );
	}
}

add_filter( 'style_loader_tag', 'mm_async_popup_styles', 10, 2 );
function mm_async_popup_styles( $tag, $handle ) {
	static $popup = array( 'fancybox', 'jquery-ui', 'bxslider', 'bootstrap-select', 'acf-global', 'acf-input', 'acf-pro-input', 'select2', 'acf-datepicker', 'acf-timepicker', 'wp-color-picker', 'chaty-front-css', 'mm-forms', 'mm-match', 'mm-dashboard', 'mm-pro' );
	if ( ! in_array( $handle, $popup, true ) || ! mm_is_marketing_page() || strpos( $tag, "media='all'" ) === false ) return $tag;
	return str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $tag ) . '<noscript>' . $tag . '</noscript>';
}
