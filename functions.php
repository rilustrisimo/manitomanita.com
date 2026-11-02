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
	$names = array( 'mm-header', 'mm-footer', 'mm-match', 'mm-dashboard', 'mm-forms', 'mm-pro', 'mm-support' );
	if ( is_page_template( 'template-homepage.php' ) || ( class_exists( 'Landing' ) && Landing::isActive() ) ) $names[] = 'mm-home';
	if ( is_page_template( 'template-my-wishlist.php' ) ) array_push( $names, 'mm-home', 'mm-wishlist' );

	foreach ( $names as $name ) {
		$file = '/assets/csslib/' . $name . '.css';
		wp_enqueue_style( $name, PARENT_URL . $file, array(), (string) @filemtime( PARENT_DIR . $file ) );
	}
}

/**
 * Date picker: the gift exchange date can't be in the past, and days of the neighbouring months show
 * (and can be picked) so the grid never looks half empty. Styles: mm-forms.css "Date picker".
 */
add_action( 'acf/input/admin_enqueue_scripts', 'mm_date_picker_args' );
function mm_date_picker_args() {
	wp_add_inline_script( 'acf-input', "acf.add_filter('date_picker_args',function(args,field){args.showOtherMonths=true;args.selectOtherMonths=true;var \$f=field&&field.jquery?field:(field&&field.\$el);if(\$f&&\$f.closest('.acf-field').attr('data-name')==='gift_exchange_date'){args.minDate=0;}return args;});" );
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
	static $popup = array( 'dashicons', 'fontawesome', 'icons-font-daf285204f208daff44cf703ef74f361', 'fancybox', 'jquery-ui', 'bxslider', 'bootstrap-select', 'acf-global', 'acf-input', 'acf-pro-input', 'select2', 'acf-datepicker', 'acf-timepicker', 'wp-color-picker', 'chaty-front-css', 'mm-support', 'mm-forms', 'mm-match', 'mm-dashboard', 'mm-pro' );
	if ( ! in_array( $handle, $popup, true ) || ! mm_is_marketing_page() || strpos( $tag, "media='all'" ) === false ) return $tag;
	return str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $tag ) . '<noscript>' . $tag . '</noscript>';
}

/**
 * Early hints on marketing pages: open connections to the font/icon hosts, start the chained font CSS
 * (main-custom.css @imports it), and fetch the homepage banner photo first (it is the largest paint).
 */
add_action( 'wp_head', 'mm_marketing_head_hints', 1 );
function mm_marketing_head_hints() {
	if ( ! mm_is_marketing_page() ) return;

	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
	echo '<link rel="preload" as="style" href="' . esc_url( PARENT_URL . '/assets/fonts/stylesheet.css' ) . '">' . "\n";

	$hero = is_page_template( 'template-homepage.php' ) && function_exists( 'get_field' ) ? get_field( 'mm_hero_image', get_queried_object_id() ) : 0;
	if ( ! $hero ) return;

	// Same tag as the template prints, so the preload uses the exact URLs the page will request.
	$img = wp_get_attachment_image( $hero, 'full', false, array( 'sizes' => '100vw' ) );
	if ( ! preg_match( '~\ssrc="([^"]+)"~', $img, $src ) ) return;
	$set = preg_match( '~\ssrcset="([^"]+)"~', $img, $m ) ? ' imagesrcset="' . esc_attr( $m[1] ) . '" imagesizes="100vw"' : '';
	echo '<link rel="preload" as="image" href="' . esc_url( $src[1] ) . '"' . $set . ' fetchpriority="high">' . "\n";
}
