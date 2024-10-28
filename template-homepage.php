<?php
/**
 * Template Name: Homepage
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

get_header();

$fields = get_fields(get_the_ID());
$steps = $fields['steps_content'];

if ( have_posts() ) : ?>
	<?php while ( have_posts() ) { the_post(); ?>
		<div class="page-single">
			<main class="page-single__content container-fluid" role="main">
				<!-- Steps Area -->
				<div class="row steps">
					<?php foreach($steps as $s): ?>
					<div class="col-md-12 steps__item" style="background-image: url(<?php echo $s['background']; ?>);">
						<div class="icon"><img src="<?php echo $s['icon']; ?>"></div>
						<div class="title"><?php echo $s['title']; ?></div>
						<div class="desc"><?php echo $s['description']; ?></div>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="row about">
					<div class="col-md-12"><h1><?php echo $fields['about_title']; ?></h1></div>
					<div class="col-md-12"><?php echo $fields['about_content']; ?></div>
					<div class="col-md-12 pizza"><img src="<?php echo $fields['about_pizza']; ?>"></div>
					<div class="col-md-12 pizza"><?php echo $fields['about_pizza_text']; ?></div>
				</div>
			</main>
		</div>
	<?php } ?>
<?php else :
	get_template_part( 'templates/content', 'none' );
endif;

get_footer();
