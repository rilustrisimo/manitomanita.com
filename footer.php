<?php
/**
 * Footer template part.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @package Eyorsogood_Design
 * @version   1.0.0
 */

$is_back_to_top = qed_get_option( 'back-to-top', 'option' );
do_action('eyor_after_main_content');
if ( qed_get_option( 'is_testimonial_enabled', 'option' ) && qed_is_display_testimonials() ) :?>
<div class="qed-testimonials__wrap">
	<div class="qed-testimonials__inner-wrap">
		<?php
		$testimonial_title = ( qed_get_option( 'testimonial_section_title', 'option' ) ) ? ' title="' . qed_get_option( 'testimonial_section_title', 'option' ) . '"': '';
		$testimonial_words_limit = ( qed_get_option( 'is_testimonial_excerpt', 'option' ) ) ? ' words_limit="' . qed_get_option( 'testimonial_excerpt_length', 'option' ) . '"':'';
		$testimonial_order = ( qed_get_option( 'testimonial_order', 'option' ) ) ? ' order="' . qed_get_option( 'testimonial_order', 'option' ) . '"':'';
		$testimonial_orderby = ( qed_get_option( 'testimonial_orderby', 'option' ) ) ? ' orderby="' . qed_get_option( 'testimonial_orderby', 'option' ) . '"':'';
		$testimonial_shortcode = sprintf('[testimonials%s%s%s]',
			$testimonial_words_limit,
			$testimonial_order,
			$testimonial_orderby
		);
		echo do_shortcode( $testimonial_shortcode );
		?>
	</div>
</div>
<?php endif; ?>
<footer class="mm-footer" role="contentinfo">
	<div class="mm-footer__wrap">
		<div class="mm-footer__brand">
			<?php get_template_part( 'templates/header/logo' ); ?>
			<p>The free online Manito Manita and Secret Santa organizer for offices, families and barkadas.</p>
			<a href="#create-group" class="mm-footer__cta" data-mm-track="create_group_click" data-mm-slot="footer">Create a free group</a>
		</div>
		<nav class="mm-footer__cols" aria-label="Footer">
			<div>
				<h3>Product</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/#how-it-works' ) ); ?>">How it works</a></li>
					<li><a href="<?php echo esc_url( home_url( '/#pro' ) ); ?>">Free &amp; PRO</a></li>
					<li><a href="<?php echo esc_url( home_url( '/#faq' ) ); ?>">FAQ</a></li>
				</ul>
			</div>
			<div>
				<h3>Explore</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/gift-ideas/' ) ); ?>">Gift ideas</a></li>
					<li><a href="<?php echo esc_url( home_url( '/blogs/' ) ); ?>">Blog</a></li>
				</ul>
			</div>
			<div>
				<h3>Help</h3>
				<ul>
					<li><a href="mailto:rouie@manitomanita.com">Contact us</a></li>
					<li><a href="<?php echo esc_url( home_url( '/reset-password/' ) ); ?>">Forgot password?</a></li>
				</ul>
			</div>
		</nav>
	</div>
	<div class="mm-footer__bottom">
		<div class="mm-footer__wrap mm-footer__wrap--bottom">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Manito Manita. Free forever.</span>
			<span>Made in the Philippines by <a href="https://eyorsogood.com" rel="noopener" target="_blank">Rouie Ilustrisimo</a></span>
		</div>
	</div>
</footer>
<input type="hidden" id="base-url" value="<?php echo home_url(); ?>">
<input type="hidden" id="ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>">

<div style="display:none" class="fancybox-hidden">
	<div id="contact-lightbox" style="display: block;max-width: 1080px;width: 100%;">
		<?php get_template_part( 'templates/lightbox', 'contact-form' );?>
	</div>
</div>
<?php
get_template_part( 'templates/footer/footer', 'clean' );
