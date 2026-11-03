<?php
/**
 * Template Name: Advertiser account
 *
 * /advertiser/: sign in with an email code, see your ads (status, results, billing), edit a card,
 * book a placement (live preview, weekly/monthly PayPal subscription), cancel or switch billing, join
 * a waitlist. Admins add ?test=1 to pay with the PayPal sandbox. JS: assets/js/mm-ads.js.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

nocache_headers();
do_action( 'litespeed_control_set_nocache', 'advertiser account' );

$aid = AdsPortal::current();
$switchMsg = ( $aid && ! empty( $_GET['switched'] ) ) ? AdsPortal::confirmSwitch( absint( $_GET['switched'] ) ) : '';

get_header();

$statusLabel = array( 'checkout' => array( 'Not booked yet', 'is-draft' ), 'review' => array( 'In review', 'is-review' ), 'live' => array( 'Live', 'is-live' ), 'paused' => array( 'Paused', 'is-draft' ), 'ended' => array( 'Ended', 'is-draft' ), 'rejected' => array( 'Not approved', 'is-bad' ) );

// Gift guide pages an ad can target (Landing pages that aren't legal pages).
$guides = array();
foreach ( get_posts( array( 'post_type' => 'page', 'posts_per_page' => 50, 'meta_key' => '_wp_page_template', 'meta_value' => 'template-landing.php', 'orderby' => 'title', 'order' => 'ASC' ) ) as $gp ) {
	if ( function_exists( 'get_field' ) && get_field( 'mm_type', $gp->ID ) === 'legal' ) continue;
	$guides[ $gp->post_name ] = get_the_title( $gp );
}
?>
<div class="mm-home mm-landing mm-adv mm-adv--account" data-mm-ads>
	<section class="mmh-hero mml-hero">
		<div class="mmh-wrap mmh-wrap--narrow">
			<p class="mmh-eyebrow">Advertiser account<?php echo AdsPortal::testMode() ? ' &middot; <b>sandbox test mode</b>' : ''; ?></p>
			<h1 class="mmh-hero__title"><?php echo $aid ? 'Your <span>ads</span>' : 'Sign in to <span>advertise</span>'; ?></h1>
			<p class="mmh-hero__lead"><?php echo $aid ? 'Book a placement, check your results and manage billing.' : 'We&rsquo;ll email you a 6-digit code. New here? Signing in creates your account.'; ?> <a href="<?php echo esc_url( AdsPortal::pageUrl( 'advertise' ) ); ?>">Placements and prices</a></p>
		</div>
	</section>

	<section class="mmh-section">
		<div class="mmh-wrap mmh-wrap--narrow mma-account">
<?php if ( ! $aid ) : ?>
			<form class="mma-card mma-form" data-mma-login novalidate>
				<label for="mma-email">Business email<input id="mma-email" type="email" name="email" maxlength="120" autocomplete="email" placeholder="you@yourshop.ph" required></label>
				<button type="button" class="mma-btn" data-mma-send>Email me a code</button>
				<label for="mma-code" data-mma-codebox hidden>6-digit code<input id="mma-code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456"></label>
				<button type="submit" class="mma-btn mma-btn--primary" data-mma-signin hidden>Sign in</button>
				<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
				<p class="mma-fine">By continuing you agree to the <a href="<?php echo esc_url( AdsPortal::pageUrl( 'rules' ) ); ?>">ad guidelines</a>, <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms</a> and <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>.</p>
			</form>
<?php else :
	$adv = get_post( $aid );
	$ads = AdsPortal::adsOf( $aid );
	$hasBiz = $adv && $adv->post_title !== (string) get_post_meta( $aid, '_mm_adv_email', true );
	?>
			<div class="mma-bar">
				<span>Signed in as <b><?php echo esc_html( get_post_meta( $aid, '_mm_adv_email', true ) ); ?></b></span>
				<button type="button" class="mma-link" data-mma-logout>Sign out</button>
			</div>
			<?php if ( $switchMsg ) : ?><p class="mma-notice"><?php echo $switchMsg; ?></p><?php endif; ?>

			<form class="mma-card mma-form mma-profile" data-mma-profile<?php echo $hasBiz ? ' hidden' : ''; ?> novalidate>
				<h2>Your business</h2>
				<label for="mma-biz">Business name<input id="mma-biz" type="text" name="business" maxlength="60" value="<?php echo esc_attr( $hasBiz ? $adv->post_title : '' ); ?>" required></label>
				<label for="mma-name">Your name<input id="mma-name" type="text" name="name" maxlength="60" value="<?php echo esc_attr( get_post_meta( $aid, '_mm_adv_name', true ) ); ?>"></label>
				<label for="mma-contact">Phone or Facebook page <small>So we can reach you about your ad</small><input id="mma-contact" type="text" name="contact" maxlength="120" value="<?php echo esc_attr( get_post_meta( $aid, '_mm_adv_contact', true ) ); ?>"></label>
				<button type="submit" class="mma-btn mma-btn--primary">Save</button>
				<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
			</form>

			<?php if ( $ads ) : ?>
			<h2 class="mmh-h2 mml-left">Your ads</h2>
			<div class="mma-ads">
				<?php foreach ( $ads as $id ) :
					$st = Ads::meta( $id, 'status', 'checkout' );
					$pl = Ads::placements()[ Ads::meta( $id, 'slot' ) ] ?? array( 'label' => '' );
					$t = Ads::totals( $id );
					$base = $t['view'] + $t['open'];
					$end = (int) Ads::meta( $id, 'end', 0 );
					$paid = (float) Ads::meta( $id, 'paid', 0 );
					$cad = Ads::meta( $id, 'cadence', 'weekly' );
					$sub = Ads::meta( $id, 'sub' ) !== '' && ! Ads::meta( $id, 'cancelled' );
					$pending = is_array( get_post_meta( $id, '_mm_ad_pending', true ) );
					$c = Ads::creative( $id );
					?>
				<article class="mma-card mma-ad" data-mma-ad="<?php echo (int) $id; ?>" data-status="<?php echo esc_attr( $st ); ?>" data-cadences="<?php echo esc_attr( implode( ',', AdsPortal::cadences( Ads::meta( $id, 'slot' ) ) ) ); ?>" data-c="<?php echo esc_attr( wp_json_encode( $c ) ); ?>" data-slot="<?php echo esc_attr( Ads::meta( $id, 'slot' ) ); ?>">
					<header>
						<div><h3><?php echo esc_html( $pl['label'] ); ?><?php echo Ads::meta( $id, 'target' ) ? ' <small>&middot; ' . esc_html( $guides[ Ads::meta( $id, 'target' ) ] ?? Ads::meta( $id, 'target' ) ) . '</small>' : ''; ?></h3>
						<p class="mma-meta">
							<?php if ( $st === 'live' && $end ) echo 'Paid through ' . esc_html( wp_date( 'M j, g:i A', $end ) ) . ' &middot; '; ?>
							<?php if ( in_array( $st, array( 'live', 'review' ), true ) ) echo esc_html( ucfirst( $cad ) ) . ', &#8369;' . esc_html( number_format( (float) Ads::meta( $id, 'price', 0 ) ) ) . ( $sub ? ', renews automatically' : ', cancelled' ); ?>
							<?php if ( Ads::meta( $id, 'payfail' ) ) echo ' &middot; <b class="mma-bad">renewal payment failed</b>'; ?>
							<?php if ( $pending ) echo ' &middot; edit in review'; ?>
						</p></div>
						<span class="mma-chip <?php echo esc_attr( $statusLabel[ $st ][1] ?? '' ); ?>"><?php echo esc_html( $statusLabel[ $st ][0] ?? $st ); ?></span>
					</header>
					<div class="mma-ad__grid">
						<div class="mma-ad__card"><?php echo Ads::cardHtml( array( 'id' => $id, 'v' => 'a', 'c' => $c ), Ads::meta( $id, 'slot' ), 0, true ); ?></div>
						<dl class="mma-kpis">
							<div><dt>Views</dt><dd><?php echo esc_html( number_format( $t['view'] ) ); ?></dd></div>
							<div><dt>Emails</dt><dd><?php echo esc_html( number_format( $t['sent'] ) ); ?></dd></div>
							<div><dt>Clicks</dt><dd><?php echo esc_html( number_format( $t['click'] ) ); ?></dd></div>
							<div><dt>CTR</dt><dd><?php echo $base ? esc_html( round( $t['click'] / $base * 100, 2 ) ) . '%' : '&ndash;'; ?></dd></div>
							<div><dt>&#8369; per click</dt><dd><?php echo ( $paid && $t['click'] ) ? '&#8369;' . esc_html( number_format( $paid / $t['click'], 2 ) ) : '&ndash;'; ?></dd></div>
						</dl>
					</div>
					<div class="mma-actions">
						<button type="button" class="mma-btn" data-mma-edit>Edit card</button>
						<?php if ( in_array( $st, array( 'checkout', 'ended', 'rejected' ), true ) ) : ?><button type="button" class="mma-btn mma-btn--primary" data-mma-book>Pay and book</button><?php endif; ?>
						<?php if ( $sub && in_array( $st, array( 'live', 'review' ), true ) ) : ?>
							<button type="button" class="mma-link" data-mma-switch>Switch to <?php echo $cad === 'monthly' ? 'weekly' : 'monthly'; ?> billing</button>
							<button type="button" class="mma-link mma-link--danger" data-mma-cancel>Cancel subscription</button>
						<?php endif; ?>
					</div>
					<div class="mma-pay" data-mma-pay hidden></div>
					<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
				</article>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<h2 class="mmh-h2 mml-left" id="book">Book a placement</h2>
			<form class="mma-card mma-form mma-builder" data-mma-builder novalidate>
				<input type="hidden" name="ad" value="">
				<fieldset class="mma-places-pick" data-mma-places>
					<legend>1. Where should your ad show?</legend>
					<?php foreach ( Ads::placements() as $key => $pl ) :
						if ( ! AdsPortal::sellable( $key ) ) continue;
						$season = Ads::season( $key );
						$left = empty( $pl['target'] ) ? AdsPortal::seatsLeft( $key, '', $aid ) : 1;
						$row = Ads::prices()[ $key ][ $season === 'closed' ? 'peak' : $season ];
						$open = $season !== 'closed' && $left > 0;
						?>
					<label class="mma-opt<?php echo $open ? '' : ' is-full'; ?>">
						<input type="radio" name="placement" value="<?php echo esc_attr( $key ); ?>" data-open="<?php echo $open ? 1 : 0; ?>" data-cadences="<?php echo esc_attr( implode( ',', AdsPortal::cadences( $key ) ) ); ?>" data-weekly="<?php echo (int) $row[0]; ?>" data-monthly="<?php echo (int) $row[1]; ?>" data-target="<?php echo esc_attr( $pl['target'] ?? '' ); ?>">
						<span><b><?php echo esc_html( $pl['label'] ); ?></b><small><?php echo esc_html( $pl['where'] ); ?></small></span>
						<span class="mma-opt__price">&#8369;<?php echo esc_html( number_format( $row[0] ) ); ?>/wk<small><?php echo $season === 'closed' ? 'Opens Sep 1' : ( $open ? ( empty( $pl['target'] ) ? $left . ' of ' . Ads::SEATS . ' seats open' : 'Per page' ) : 'Full &middot; waitlist' ); ?></small></span>
					</label>
					<?php endforeach; ?>
					<label class="mma-target" for="mma-target" data-mma-target hidden>Which gift guide?
						<select id="mma-target" name="target"><option value="">Choose a page</option><?php foreach ( $guides as $slug => $title ) echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $title ) . '</option>'; ?></select>
					</label>
					<button type="button" class="mma-btn" data-mma-wait hidden>Join the waitlist</button>
				</fieldset>

				<fieldset>
					<legend>2. Your card</legend>
					<div class="mma-two">
						<div class="mma-fields">
							<label for="mma-brand">Brand name<input id="mma-brand" type="text" name="c[brand]" maxlength="40" required></label>
							<label for="mma-logo">Logo <small>Square PNG, JPG or WebP, under 1 MB</small><input id="mma-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
							<label for="mma-head">Headline <small data-mma-count="50">0/50</small><input id="mma-head" type="text" name="c[headline]" maxlength="50" required></label>
							<label for="mma-text">One line about your offer <small data-mma-count="120">0/120</small><input id="mma-text" type="text" name="c[text]" maxlength="120"></label>
							<label for="mma-btnl">Button label<input id="mma-btnl" type="text" name="c[button]" maxlength="24" value="Shop now"></label>
							<label for="mma-l0">Main link <small>https:// your shop, Shopee, Lazada or website</small><input id="mma-l0" type="url" name="c[links][0][1]" placeholder="https://" required><input type="hidden" name="c[links][0][0]" value="Main"></label>
							<details class="mma-more"><summary>More options</summary>
								<label for="mma-l1l">Second link label<input id="mma-l1l" type="text" name="c[links][1][0]" maxlength="24" placeholder="Facebook"></label>
								<label for="mma-l1">Second link<input id="mma-l1" type="url" name="c[links][1][1]" placeholder="https://"></label>
								<label for="mma-l2l">Third link label<input id="mma-l2l" type="text" name="c[links][2][0]" maxlength="24" placeholder="Instagram"></label>
								<label for="mma-l2">Third link<input id="mma-l2" type="url" name="c[links][2][1]" placeholder="https://"></label>
								<label for="mma-promo">Promo code<input id="mma-promo" type="text" name="c[promo]" maxlength="24" placeholder="MANITO10"></label>
								<label for="mma-bh">Version B headline <small>We rotate both and show which gets more clicks</small><input id="mma-bh" type="text" name="c[b][headline]" maxlength="50"></label>
								<label for="mma-bt">Version B line<input id="mma-bt" type="text" name="c[b][text]" maxlength="120"></label>
								<label for="mma-bb">Version B button<input id="mma-bb" type="text" name="c[b][button]" maxlength="24"></label>
							</details>
						</div>
						<div class="mma-live">
							<p class="mma-live__label">Preview</p>
							<div data-mma-preview></div>
						</div>
					</div>
				</fieldset>

				<fieldset data-mma-billing>
					<legend>3. Billing</legend>
					<div class="mma-cad">
						<label><input type="radio" name="cadence" value="weekly" checked> Weekly <b data-mma-price="weekly"></b></label>
						<label><input type="radio" name="cadence" value="monthly"> Monthly <b data-mma-price="monthly"></b></label>
					</div>
					<p class="mma-fine">Renews automatically until you cancel. We review your ad after payment; if it isn&rsquo;t approved you get a full refund.</p>
				</fieldset>

				<button type="submit" class="mma-btn mma-btn--primary mma-btn--block" data-mma-save>Save and continue to payment</button>
				<div class="mma-pay" data-mma-pay hidden></div>
				<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
			</form>
<?php endif; ?>
		</div>
	</section>
</div>
<?php
get_footer();
