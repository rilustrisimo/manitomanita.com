<?php
/**
 * Header clean template part.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

$promo = new Theme();
$activepromos = $promo->getActivePromos();

$group = new Groups();
$groupid = $group->getGroupId();  // Replace with your actual group ID
$fields = $group->getGroupDetails($groupid);
$pro = (isset($fields['pro']))?$fields['pro']:false;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php
	if ( ! qed_check( 'is_wordpress_seo_in_use' ) ) {
		printf( '<meta name="description" content="%s">', get_bloginfo( 'description', 'display' ) );
	}
	?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php acf_form_head(); ?>
	<?php wp_head(); ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/js-cookie/2.2.1/js.cookie.min.js" integrity="sha512-Meww2sXqNHxI1+5Dyh/9KAtvI9RZSA4c1K2k5iL02oiPO/RH3Q30L3M1albtqMg50u4gRTYdV4EXOQqXEI336A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script data-ad-client="ca-pub-8648985139343614" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
</head>
<body <?php body_class(); ?>>
<?php if(count($activepromos) > 0 && !is_front_page()): ?>
<div class="promos" style="display:none;">
	<div class="promos__inner">
	<div class="promos__close"><i class="far fa-times-circle"></i></div>
	<?php shuffle($activepromos); ?>
	<div class="promos__container"><?php echo $activepromos[0]; ?></div>
	</div>
</div>
<?php endif; ?>
<div class="loader-overlay" style="background:#1b1b1b;display:none;"><img src="<?php echo 
get_template_directory_uri().'/assets/images/loader.gif'; ?>"></div>
<!-- Loader Overlay -->
<div id="loader-overlay-paypal" style="display: none;">
    <div class="loader"></div>
</div>
<?php if(!$pro): ?>
	<?php get_template_part( 'templates/pro/pro', 'btnpop' ); ?>
	<?php get_template_part( 'templates/pro/pro', 'try' ); ?>
<?php else: ?>
	<?php get_template_part( 'templates/pro/pro', 'actions' ); ?>
	<?php get_template_part( 'templates/pro/pro', 'credits' ); ?>
<?php endif; ?>
<div class="layout-content">