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
			<p class="mm-footer__follow-label">Follow us for gift ideas and party themes</p>
			<?php echo Theme::socialLinks( 'footer', 'mm-social' ); ?>
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
				<h3>Free tools</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/monito-monita-generator/' ) ); ?>">Monito Monita generator</a></li>
					<li><a href="<?php echo esc_url( home_url( '/kris-kringle-generator-philippines/' ) ); ?>">Kris Kringle generator</a></li>
					<?php $mmBunot = get_page_by_path( 'online-bunutan' ); if ( $mmBunot && $mmBunot->post_status === 'publish' ) : // shows once the page is published ?><li><a href="<?php echo esc_url( home_url( '/online-bunutan/' ) ); ?>">Online bunutan</a></li><?php endif; ?>
					<li><a href="<?php echo esc_url( home_url( '/secret-santa-generator/' ) ); ?>">Secret Santa generator</a></li>
					<li><a href="<?php echo esc_url( home_url( '/exchange-gift-ideas-500-pesos/' ) ); ?>">Gift ideas under &#8369;500</a></li>
					<li><a href="<?php echo esc_url( home_url( '/monito-monita-themes/' ) ); ?>">Monito Monita themes</a></li>
					<li><a href="<?php echo esc_url( home_url( '/my-wishlist/' ) ); ?>">My Wishlist (any occasion)</a></li>
				</ul>
			</div>
			<div>
				<h3>Help</h3>
				<ul>
					<li><a href="mailto:rouie@manitomanita.com">Contact us</a></li>
					<li><a href="#ask-manito" data-mm-support-open>Ask Manito (help chat)</a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Use</a></li>
				</ul>
			</div>
			<?php if ( get_option( 'mm_ads_pages' ) ) : // shows once the ad pages exist ?>
			<div>
				<h3>For businesses</h3>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/advertise/' ) ); ?>" data-mm-track="advertise_cta" data-mm-slot="footer">Advertise with us</a></li>
					<li><a href="<?php echo esc_url( home_url( '/advertise/#placements' ) ); ?>" data-mm-track="advertise_cta" data-mm-slot="footer-prices">Ad placements &amp; prices</a></li>
					<li><a href="<?php echo esc_url( home_url( '/advertiser/' ) ); ?>">Advertiser sign in</a></li>
					<li><a href="<?php echo esc_url( home_url( '/ad-guidelines/' ) ); ?>">Ad guidelines</a></li>
				</ul>
			</div>
			<?php endif; ?>
		</nav>
	</div>
	<div class="mm-footer__bottom">
		<div class="mm-footer__wrap mm-footer__wrap--bottom">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Manito Manita. Free forever.</span>
			<span>Made in the Philippines by <a href="https://eyorsogood.com" rel="noopener" target="_blank">Rouie Ilustrisimo</a></span>
		</div>
	</div>
</footer>
<?php
// "Ask Manito" help chat (SupportBot). The panel is filled by Theme.initSupport on first open.
$mmPage = ( is_front_page() || is_page_template( 'template-homepage.php' ) || ( class_exists( 'Landing' ) && Landing::isActive() ) ) ? 'home'
	: ( is_page( 23 ) ? 'group' : ( is_page_template( 'template-my-wishlist.php' ) ? 'wishlist' : 'other' ) );
?>
<div class="mm-support" data-mm-support data-page="<?php echo esc_attr( $mmPage ); ?>" data-suggest="<?php echo esc_attr( wp_json_encode( SupportBot::suggestions( $mmPage ) ) ); ?>" data-socials="<?php echo esc_attr( wp_json_encode( array_values( Theme::socials() ) ) ); ?>">
	<button type="button" class="mm-support__fab" data-mm-support-open aria-label="Open help chat" aria-expanded="false">
		<i class="fa-solid fa-comment-dots" aria-hidden="true"></i><span class="mm-support__hint">Need help?</span>
	</button>
	<div class="mm-support__panel" role="dialog" aria-modal="false" aria-label="Ask Manito help chat" hidden></div>
</div>
<input type="hidden" id="base-url" value="<?php echo home_url(); ?>">
<input type="hidden" id="ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>">

<div style="display:none" class="fancybox-hidden">
	<div id="contact-lightbox" style="display: block;max-width: 1080px;width: 100%;">
		<?php get_template_part( 'templates/lightbox', 'contact-form' );?>
	</div>
</div>
<?php
get_template_part( 'templates/footer/footer', 'clean' );
