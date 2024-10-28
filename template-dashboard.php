<?php
/**
 * Template Name: Group Dashboard
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

get_header('dashboard');

$group = new Groups();

if ( have_posts() ) : ?>
	<?php while ( have_posts() ) { the_post(); ?>
		<div class="page-single">
			<main class="page-single__content" role="main">
                <div class="row">
                    <?php if($group->checkGroupCredentials()): ?>
                    <?php get_template_part( 'templates/content', 'dashboard' ); ?>
                    <?php else: ?>
                    <div class="col-md-12 warning"><div class="icon"><i class="far fa-frown"></i></div><p>Please use the link provided by your group administrator.</p></div>
                    <?php endif; ?>
                </div>
			</main>
		</div>
	<?php } ?>
<?php else :
	get_template_part( 'templates/content', 'none' );
endif;

get_footer();
