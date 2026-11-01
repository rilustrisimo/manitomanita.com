<?php
/**
 * Header template part.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package Eyorsogood_Design
 * @version   1.0.0
 */

get_template_part( 'templates/header/header', 'clean' );

$is_sticky_header = qed_get_option( 'sticky_header', 'option' );

if ( $is_sticky_header ) {
	SD_Js_Client_Script::add_script( 'sticky-header', 'Theme.initStickyHeader();' );
	echo '<div class="header-wrap">';
}
?>
<header class="mm-header" role="banner">
	<div class="mm-header__bar">
		<?php get_template_part( 'templates/header/logo' ); ?>
		<?php if ( has_nav_menu( 'header-menu' ) ) : ?>
			<nav class="mm-header__nav" aria-label="Main">
				<?php wp_nav_menu(array(
					'theme_location' => 'header-menu',
					'container'      => false,
					'menu_class'     => 'mm-header__menu',
					'menu_id'        => 'navigation',
					'depth'          => 1,
				)); ?>
			</nav>
		<?php endif; ?>
	</div>
</header>
<?php if ( $is_sticky_header ) { echo '</div>'; }
SD_Js_Client_Script::add_script( 'initResizeHandler', 'Theme.initResizeHandler();' );
//SD_Js_Client_Script::add_script( 'initResizeHandler', 'Theme.initResizeHandler(' . wp_json_encode( $js_config ) . ');' );
get_template_part( 'templates/header/header', 'section' );
do_action('eyor_before_main_content');

$theme = new Theme();
?>

<div id="create-group" class="popup">
	<div class="popup__title">Create<span>Group</span></div>
	<div class="popup__divider"></div>
    <div class="popup__inner">
		<?php $theme->createAcfForm(27, 'groups', 'Create Group', 'group-dashboard'); ?>
	</div>
</div>

<?php
