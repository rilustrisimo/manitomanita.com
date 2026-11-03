<?php
/**
 * 404 page: friendly message, the way back, and "Lost your group link?" (many 404s are old or
 * mistyped group links). Styles: mm-home.css + mm-account.css.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   2.0.0
 */

qed_di( 'app' )->add_body_class( 'page-404' );

get_header();
?>
<div class="mm-home mm-landing mm-acc mm-404">
	<section class="mmh-hero mml-hero mm-acc__hero">
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mmh-wrap--narrow">
			<p class="mmh-eyebrow">Error 404</p>
			<h1 class="mmh-hero__title">This page took a <span>wrong turn</span></h1>
			<p class="mmh-hero__lead">The link may be old or mistyped. Let&rsquo;s get you to the right place.</p>
			<div class="mmh-hero__actions">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mmh-btn mmh-btn--primary">Go to the homepage</a>
				<a href="#create-group" class="mmh-btn mmh-btn--ghost" data-mm-track="create_group_click" data-mm-slot="404">Create a free group</a>
			</div>
		</div>
	</section>

	<section class="mmh-section mm-acc__main">
		<div class="mmh-wrap mmh-wrap--narrow">
			<div class="mm-acc__card">
				<?php get_template_part( 'templates/account/find-group', null, array( 'slot' => '404' ) ); ?>
			</div>
			<div class="mm-404__links">
				<p>Popular pages</p>
				<a href="<?php echo esc_url( home_url( '/monito-monita-generator/' ) ); ?>">Monito Monita generator</a>
				<a href="<?php echo esc_url( home_url( '/monito-monita-themes/' ) ); ?>">50+ Monito Monita themes</a>
				<a href="<?php echo esc_url( home_url( '/exchange-gift-ideas-500-pesos/' ) ); ?>">Gift ideas under &#8369;500</a>
				<a href="<?php echo esc_url( home_url( '/my-wishlist/' ) ); ?>">My Wishlist</a>
			</div>
			<p class="mm-acc__help">Need help? Tap <a href="#ask-manito" data-mm-support-open>Ask Manito</a>.</p>
		</div>
	</section>
</div>
<?php
get_footer();
