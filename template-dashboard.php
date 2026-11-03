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
                    <div class="col-md-12">
                        <div class="mm-nogroup">
                            <span class="mm-nogroup__icon"><i class="fa-solid fa-link-slash" aria-hidden="true"></i></span>
                            <h1 class="mm-nogroup__title">We couldn&rsquo;t open this group</h1>
                            <p class="mm-nogroup__lead">The link may have been cut off when it was copied, or it&rsquo;s an old link. Group links look like <b>manitomanita.com/group-dashboard/?gid=&hellip;&amp;pw=&hellip;</b></p>

                            <form class="mm-paste" data-mm-paste-link novalidate>
                                <label for="mm-paste-input">Paste the full group link</label>
                                <div class="mm-paste__row">
                                    <input id="mm-paste-input" type="url" inputmode="url" autocomplete="off" placeholder="https://manitomanita.com/group-dashboard/?gid=...">
                                    <button type="submit">Open</button>
                                </div>
                                <p class="mm-paste__error" data-mm-paste-error hidden>That doesn&rsquo;t look like a complete group link. Copy the whole link from your invite or email and try again.</p>
                            </form>

                            <div class="mm-nogroup__or"><span>or</span></div>
                            <?php get_template_part( 'templates/account/find-group', null, array( 'slot' => 'dashboard' ) ); ?>

                            <div class="mm-nogroup__actions">
                                <a class="mm-nogroup__btn" href="<?php echo esc_url( home_url( '/' ) ); ?>" data-mm-track="create_group_click" data-mm-slot="no-group">Create a free group</a>
                                <a class="mm-nogroup__link" href="#ask-manito" data-mm-support-open>Ask Manito for help</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
			</main>
		</div>
	<?php } ?>
<?php else :
	get_template_part( 'templates/content', 'none' );
endif;

get_footer();
