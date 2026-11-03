<?php
/**
 * Template Name: Reset Password
 *
 * /reset-password/?uid=&exp=&t=  Personal, signed, 1-hour links from "Forgot password?" (Account::resetUrl).
 * States: form (valid link) / done (password changed, signed in) / invalid (expired or old link: request a
 * new one, or get your group links by email). Logic: Account::handleReset(). Styles: mm-home.css + mm-account.css.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   2.0.0
 */

defined( 'ABSPATH' ) || die();

nocache_headers();
list( $mmState, $mmMsg, $mmX ) = Account::handleReset(); // before any output: it may set the "remember" cookie

get_header();
?>
<div class="mm-home mm-landing mm-acc">
	<section class="mmh-hero mml-hero mm-acc__hero">
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mmh-wrap--narrow">
			<p class="mmh-eyebrow">Account</p>
			<h1 class="mmh-hero__title"><?php echo $mmState === 'done' ? 'Password <span>updated</span>' : ( $mmState === 'form' ? 'Set a new <span>password</span>' : 'This link has <span>expired</span>' ); ?></h1>
		</div>
	</section>

	<section class="mmh-section mm-acc__main">
		<div class="mmh-wrap mmh-wrap--narrow">
			<div class="mm-acc__card">
			<?php if ( $mmState === 'form' ) :
				$mmId = (int) $mmX['id'];
				$mmIsGroup = get_post_type( $mmId ) === 'groups';
				$mmWho = $mmIsGroup ? (string) get_field( 'your_name', $mmId ) : (string) get_field( 'screen_name', $mmId ); ?>
				<p class="mm-acc__for"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> <span><?php echo $mmIsGroup ? 'Organizer' : 'Member'; ?> password for <b><?php echo esc_html( $mmWho ); ?></b> in <b><?php echo esc_html( (string) get_field( 'group_name', Account::groupOf( $mmId ) ) ); ?></b></span></p>
				<?php if ( $mmMsg ) : ?><p class="mm-acc__error" role="alert"><?php echo $mmMsg; ?></p><?php endif; ?>
				<form method="post" class="mm-acc__form" data-mm-reset novalidate>
					<?php wp_nonce_field( 'mm_reset_' . $mmId, '_mmnonce' ); ?>
					<label>New password
						<span class="mm-acc__pw"><input type="password" name="new-pass" minlength="6" autocomplete="new-password" placeholder="At least 6 characters" required><button type="button" data-mm-pwshow aria-label="Show password">Show</button></span>
					</label>
					<label>Type it again
						<span class="mm-acc__pw"><input type="password" name="new-pass2" minlength="6" autocomplete="new-password" placeholder="Same password" required></span>
					</label>
					<p class="mm-acc__hint" data-mm-pwhint>Use something you&rsquo;ll remember. You can stay signed in on this device for 30 days.</p>
					<button type="submit" class="mm-acc__btn">Save new password</button>
				</form>
			<?php elseif ( $mmState === 'done' ) : ?>
				<div class="mm-acc__ok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
				<p class="mm-acc__lead">Your <?php echo $mmX['organizer'] ? 'organizer' : 'member'; ?> password for <b><?php echo esc_html( $mmX['group'] ); ?></b> is updated, and you&rsquo;re signed in on this device. We also sent you a confirmation email.</p>
				<a class="mm-acc__btn" href="<?php echo esc_url( $mmX['link'] ); ?>">Open the group</a>
			<?php else : ?>
				<p class="mm-acc__lead">Reset links work for 1 hour and only once. To get a new one, open your group, tap your name (or <b>PRO Actions</b> if you&rsquo;re the organizer), then <b>Forgot Password?</b>.</p>
				<?php get_template_part( 'templates/account/find-group', null, array( 'slot' => 'reset' ) ); ?>
			<?php endif; ?>
			</div>
			<p class="mm-acc__help">Still stuck? Tap <a href="#ask-manito" data-mm-support-open>Ask Manito</a> or email <a href="mailto:rouie@manitomanita.com">rouie@manitomanita.com</a>.</p>
		</div>
	</section>
</div>
<?php
get_footer();
