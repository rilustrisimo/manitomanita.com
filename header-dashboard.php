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
	//SD_Js_Client_Script::add_script( 'sticky-header', 'Theme.initStickyHeader();' );
	echo '<div class="header-wrap">';
}

$group = new Groups();
$groupid = $group->getGroupId();  // Replace with your actual group ID
$fields = $group->getGroupDetails($groupid);
$pro = (isset($fields['pro']))?$fields['pro']:false;

$meta_query = array(
	array(
		'key' => 'groups',
		'value' => $groupid,
	),
);
/*
$post_count = $group->get_users_count_with_meta($meta_query);
$print = ($post_count > 2)?"class='user-action shuffle-btn' group-data='".$group->getGroupId()."' data-action='shuffle-group'":'disabled';

$cookiename = 'users_group_count_' . $groupid;
$expirationTime = time() + (30 * 24 * 60 * 60); // 30 days * 24 hours * 60 minutes * 60 seconds
setcookie($cookiename, $post_count, $expirationTime, '/');
*/

$post_count = $group->get_users_count_with_meta($meta_query);
$cookiename = 'users_group_count_' . $groupid;
$expirationTime = time() + (30 * 24 * 60 * 60); // 30 days
$print = ($post_count > 2) ? "class='user-action shuffle-btn' group-data='" . $group->getGroupId() . "' data-action='shuffle-group'" : 'disabled';

// Check if cookie exists and compare it with current post_count
if (!(isset($_COOKIE[$cookiename]) && $_COOKIE[$cookiename] == $post_count)) {
    // Update the cookie with the new post count
    setcookie($cookiename, $post_count, $expirationTime, '/');
}

?>
<script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
<script type="text/javascript">
	var onloadCallback = function() {
		grecaptcha.render('comment-grecaptcha', {
			'sitekey' : '6LfV5iMUAAAAAKt1NU6cqlBLjjSVE3gsEYvyM2Ny'
		});
	};
</script>
<header class="header" role="banner">
	<div class="top_layer">
		<div class="header__content-wrap">
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-12">
						<?php get_template_part( 'templates/header/logo' ); ?>
					</div><!-- .header__content -->
					<div class="clearfix"></div>
				</div>
			</div><!-- .container -->
		</div><!-- .header__content-wrap -->
	</div>
	<div class="clearfix"></div>
	<div class="bottom_layer dasboard-nav">
		<div class="container">
			<div class="header__content-wrap">
				<div class="row">
					<div class="col-md-12 header__content">
						<nav class="main-nav-header" role="navigation">
							<ul id="navigation-dashboard" class="main-nav">
								<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="javascript:;" <?php echo (get_field('matched', $group->getGroupId()))?'disabled':'data-fancybox="" data-src="#join-group"'; ?>>Join Group</a></li>
								
								
								<?php if(!$pro): ?>
									<?php if(!get_field('matched', $group->getGroupId())): ?>
										<li class="menu-item menu-item-type-custom menu-item-object-custom shuff-btn"><a href="javascript:;" <?php echo $print; ?>><?php echo (wp_is_mobile())?'Shuffle':'Shuffle Match'; ?></a></li>
									<?php else: ?>
										<li class="menu-item menu-item-type-custom menu-item-object-custom join-btn"><a href="javascript:;" id="who-joined" data-fancybox data-src="#who-joined-box"><?php echo (wp_is_mobile())?'Joined':'Who Joined'; ?></a></li>
									<?php endif; ?>

									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="#" id="go-pro-btn">Try Pro <span><i class="fa-solid fa-star"></i></span></a></li>
								<?php else:?>
									<?php if(get_field('matched', $group->getGroupId())): ?>
										<li class="menu-item menu-item-type-custom menu-item-object-custom join-btn"><a href="javascript:;" id="who-joined" data-fancybox data-src="#who-joined-box"><?php echo (wp_is_mobile())?'Joined':'Who Joined'; ?></a></li>
									<?php endif; ?>

									<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="#" id="pro-actions-btn">PRO Actions</a></li>
								<?php endif; ?>
								<!--<li class="menu-item menu-item-type-post_type menu-item-object-page"><a href="<?php echo get_permalink(77); ?>">Gift Ideas</a></li>-->
							</ul>							
						</nav>
						<div class="clearfix"></div>
					</div><!-- .header__content -->
				</div>
			</div><!-- .header__content-wrap -->
		</div><!-- .container -->
	</div>
	<div class="clearfix"></div>
</header>
<?php if ( $is_sticky_header ) { echo '</div>'; }
SD_Js_Client_Script::add_script( 'initResizeHandler', 'Theme.initResizeHandler();' );
//SD_Js_Client_Script::add_script( 'initResizeHandler', 'Theme.initResizeHandler(' . wp_json_encode( $js_config ) . ');' );
get_template_part( 'templates/header/header', 'section' );
do_action('eyor_before_main_content');
