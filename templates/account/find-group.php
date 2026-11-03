<?php
/**
 * "Lost your group link?" form (Account::ajaxFindGroups via Theme.initFindGroup).
 * Used on the homepage, the reset password page and the 404 page.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

$slot = isset( $args['slot'] ) ? (string) $args['slot'] : 'page';
?>
<form class="mm-find" data-mm-find data-slot="<?php echo esc_attr( $slot ); ?>" novalidate>
	<p class="mm-find__title"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Lost your group link?</p>
	<p class="mm-find__lead">Enter your email and we&rsquo;ll send you the links to all your groups.</p>
	<div class="mm-find__row">
		<label class="screen-reader-text" for="mm-find-<?php echo esc_attr( $slot ); ?>">Your email</label>
		<input type="email" id="mm-find-<?php echo esc_attr( $slot ); ?>" name="email" maxlength="120" autocomplete="email" placeholder="you@example.com" required>
		<button type="submit">Email my links</button>
	</div>
	<p class="mm-find__msg" role="status" aria-live="polite"></p>
</form>
