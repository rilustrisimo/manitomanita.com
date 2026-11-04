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
$rid = absint( $_GET['report'] ?? 0 );
$rkey = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_GET['k'] ?? '' ) );
$rwho = $rid && class_exists( 'AdsReport' ) ? AdsReport::access( $rid, $rkey ) : '';
$switchMsg = ( $aid && ! empty( $_GET['switched'] ) ) ? AdsPortal::confirmSwitch( absint( $_GET['switched'] ) ) : '';

get_header();

/* ---------------------------------------------------------------- Results page (owner, admin or share link) */
if ( $rwho !== '' ) :
	$rc = Ads::creative( $rid );
	$rpl = Ads::placements()[ Ads::meta( $rid, 'slot' ) ]['label'] ?? '';
	?>
<div class="mm-home mm-landing mm-adv mm-adv--account mm-adv--report" data-mm-ads>
	<section class="mmh-hero mml-hero">
		<div class="mmh-wrap">
			<?php if ( $rwho !== 'share' ) : ?>
			<nav class="mma-crumbs" aria-label="Back">
				<?php if ( $rwho === 'admin' ) : ?>
					<a class="mma-crumb" href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $rid . '&action=edit' ) ); ?>"><i class="fa-solid fa-arrow-left"></i> Back to this ad in WP Admin</a>
					<a class="mma-crumb mma-crumb--ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Ads::CPT ) ); ?>">All ads</a>
				<?php else : ?>
					<a class="mma-crumb" href="<?php echo esc_url( AdsPortal::pageUrl( 'account' ) . '#ad-' . (int) $rid ); ?>"><i class="fa-solid fa-arrow-left"></i> Back to your ads</a>
					<a class="mma-crumb mma-crumb--ghost" href="<?php echo esc_url( AdsPortal::pageUrl( 'account' ) ); ?>">Dashboard</a>
				<?php endif; ?>
			</nav>
			<?php endif; ?>
			<p class="mmh-eyebrow">Ad results<?php echo $rwho === 'share' ? ' &middot; shared report' : ''; ?></p>
			<h1 class="mmh-hero__title"><?php echo esc_html( $rc['brand'] ); ?> <span>&middot; <?php echo esc_html( $rpl ); ?></span></h1>
			<p class="mmh-hero__lead"><?php echo esc_html( $rc['headline'] ); ?></p>
		</div>
	</section>
	<section class="mmh-section">
		<div class="mmh-wrap mma-report" data-mma-report data-ad="<?php echo (int) $rid; ?>" data-k="<?php echo esc_attr( $rwho === 'share' ? $rkey : '' ); ?>" data-role="<?php echo esc_attr( $rwho ); ?>">
			<div class="mma-rbar">
				<div class="mma-rbar__range">
					<label for="mma-range" class="screen-reader-text">Period</label>
					<select id="mma-range" data-mma-range>
						<option value="7d">Last 7 days</option>
						<option value="30d">Last 30 days</option>
						<option value="all">Since the ad started</option>
						<option value="custom">Custom dates</option>
					</select>
					<span class="mma-rbar__custom" data-mma-custom hidden>
						<input type="date" data-mma-from aria-label="From"> <span>to</span> <input type="date" data-mma-to aria-label="To">
						<button type="button" class="mma-btn" data-mma-apply>Apply</button>
					</span>
					<span class="mma-rbar__period" data-mma-period></span>
				</div>
				<div class="mma-rbar__tools">
					<button type="button" class="mma-btn" data-mma-csv>Download CSV</button>
					<button type="button" class="mma-btn" data-mma-print>Print or save PDF</button>
					<?php if ( $rwho === 'owner' ) : ?><button type="button" class="mma-btn" data-mma-share>Share results</button><?php endif; ?>
				</div>
			</div>
			<div class="mma-sharebox" data-mma-sharebox hidden></div>
			<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
			<div data-mma-rbody><div class="mma-loader" role="status"><span class="mma-spin" aria-hidden="true"></span><span>Loading your results&hellip;</span></div></div>
		</div>
	</section>
</div>
<?php
	get_footer();
	return;
endif;

$statusLabel = array( 'checkout' => array( 'Not booked yet', 'is-draft' ), 'review' => array( 'In review', 'is-review' ), 'live' => array( 'Live', 'is-live' ), 'paused' => array( 'Paused', 'is-draft' ), 'ended' => array( 'Ended', 'is-draft' ), 'removed' => array( 'Removed', 'is-bad' ), 'rejected' => array( 'Not approved', 'is-bad' ) );

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
			<?php if ( $rid ) : ?><p class="mma-notice">Sign in to see the results of your ad.</p><?php endif; ?>
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
				<article class="mma-card mma-ad" id="ad-<?php echo (int) $id; ?>" data-mma-ad="<?php echo (int) $id; ?>" data-status="<?php echo esc_attr( $st ); ?>" data-cadences="<?php echo esc_attr( implode( ',', AdsPortal::cadences( Ads::meta( $id, 'slot' ) ) ) ); ?>" data-c="<?php echo esc_attr( wp_json_encode( $c ) ); ?>" data-slot="<?php echo esc_attr( Ads::meta( $id, 'slot' ) ); ?>">
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
					<?php if ( in_array( $st, array( 'rejected', 'removed' ), true ) ) : $why = (string) get_post_meta( $id, '_mm_ad_reason', true ); ?>
					<div class="mma-reject">
						<b><?php echo $st === 'removed' ? 'This ad was removed.' : 'This ad wasn&rsquo;t approved.'; ?></b>
						<?php if ( $why !== '' ) : ?><span>Reason: <?php echo esc_html( $why ); ?></span><?php endif; ?>
						<span>Edit the card to fix it, then book it again. It&rsquo;s reviewed again before going live. <a href="<?php echo esc_url( AdsPortal::pageUrl( 'rules' ) ); ?>" target="_blank" rel="noopener">Ad guidelines</a></span>
					</div>
					<?php endif; ?>
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
						<?php if ( in_array( $st, array( 'live', 'ended', 'paused', 'removed' ), true ) ) : ?><a class="mma-btn<?php echo in_array( $st, array( 'live', 'paused' ), true ) ? ' mma-btn--primary' : ''; ?>" href="<?php echo esc_url( AdsReport::url( $id ) ); ?>">View results</a><?php endif; ?>
						<button type="button" class="mma-btn<?php echo in_array( $st, array( 'rejected', 'removed' ), true ) ? ' mma-btn--primary' : ''; ?>" data-mma-edit><?php echo in_array( $st, array( 'rejected', 'removed' ), true ) ? 'Edit and resubmit' : 'Edit card'; ?></button>
						<?php if ( in_array( $st, array( 'checkout', 'ended' ), true ) ) : ?><button type="button" class="mma-btn mma-btn--primary" data-mma-book>Pay and book</button><?php endif; ?>
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

			<div class="mma-booknew"<?php echo $ads ? '' : ' hidden'; ?>>
				<button type="button" class="mma-btn mma-btn--primary" data-mma-new><span aria-hidden="true">+</span> Book a new placement</button>
			</div>

			<form class="mma-wizard mma-form" id="book" data-mma-builder data-mode="new" novalidate<?php echo $ads ? ' hidden' : ''; ?>>
				<input type="hidden" name="ad" value="">
				<div class="mma-wizard__head">
					<h2 data-mma-wtitle>Book a placement</h2>
					<button type="button" class="mma-link" data-mma-close<?php echo $ads ? '' : ' hidden'; ?>>Close</button>
				</div>
				<ol class="mma-progress" aria-label="Steps">
					<li data-tab="1" class="is-on"><span>1</span>Placement</li>
					<li data-tab="2"><span>2</span>Your card</li>
					<li data-tab="3"><span>3</span>Review &amp; pay</li>
				</ol>

				<!-- Step 1: placement -->
				<section class="mma-step" data-step="1">
					<p class="mma-step__lead">Where should your ad show? Each placement has <?php echo Ads::SEATS; ?> seats that take turns evenly.</p>
					<div class="mma-pick" data-mma-places>
						<?php foreach ( Ads::placements() as $key => $pl ) :
							if ( ! AdsPortal::sellable( $key ) ) continue;
							$season = Ads::season( $key );
							$left = empty( $pl['target'] ) ? AdsPortal::seatsLeft( $key, '', $aid ) : 1;
							$row = Ads::prices()[ $key ][ $season === 'closed' ? 'peak' : $season ];
							$open = $season !== 'closed' && $left > 0;
							$seatTxt = $season === 'closed' ? 'Opens Sep 1' : ( $open ? ( empty( $pl['target'] ) ? $left . ' of ' . Ads::SEATS . ' seats open' : 'Per page' ) : 'Full &middot; waitlist' );
							?>
						<label class="mma-opt<?php echo $open ? '' : ' is-full'; ?>">
							<input type="radio" name="placement" value="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $pl['label'] ); ?>" data-where="<?php echo esc_attr( $pl['where'] ); ?>" data-season="<?php echo esc_attr( $pl['season'] === 'xmas' ? 'Sep 1 - Dec 20' : 'All year' ); ?>" data-seats="<?php echo esc_attr( wp_strip_all_tags( html_entity_decode( $seatTxt ) ) ); ?>" data-open="<?php echo $open ? 1 : 0; ?>" data-cadences="<?php echo esc_attr( implode( ',', AdsPortal::cadences( $key ) ) ); ?>" data-weekly="<?php echo (int) $row[0]; ?>" data-monthly="<?php echo (int) $row[1]; ?>" data-target="<?php echo esc_attr( $pl['target'] ?? '' ); ?>">
							<span class="mma-opt__top"><b><?php echo esc_html( $pl['label'] ); ?></b><span class="mma-chip <?php echo $open ? 'is-open' : 'is-wait'; ?>"><?php echo $seatTxt; ?></span></span>
							<span class="mma-opt__where"><?php echo esc_html( $pl['where'] ); ?></span>
							<span class="mma-opt__price"><b>&#8369;<?php echo esc_html( number_format( $row[0] ) ); ?></b> / week <em>&#8369;<?php echo esc_html( number_format( $row[1] ) ); ?> / month &middot; <?php echo $pl['season'] === 'xmas' ? 'Sep 1 &ndash; Dec 20' : 'All year'; ?></em></span>
						</label>
						<?php endforeach; ?>
					</div>
					<label class="mma-target" for="mma-target" data-mma-target hidden>Which gift guide should it appear on?
						<select id="mma-target" name="target"><option value="">Choose a page</option><?php foreach ( $guides as $slug => $title ) echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $title ) . '</option>'; ?></select>
					</label>
					<div class="mma-waitbox" data-mma-waitbox hidden>
						<p>All seats for this placement are taken right now. Join the waitlist and we&rsquo;ll hold the next free seat for you for 48 hours.</p>
						<button type="button" class="mma-btn" data-mma-wait>Join the waitlist</button>
					</div>
				</section>

				<!-- Step 2: the card -->
				<section class="mma-step" data-step="2" hidden>
					<div class="mma-two">
						<div class="mma-fields">
							<div class="mma-group">
								<h3>Brand</h3>
								<label for="mma-brand">Brand name<input id="mma-brand" type="text" name="c[brand]" maxlength="40" placeholder="e.g. Regalo Box PH" required></label>
								<label class="mma-drop" for="mma-logo">
									<span class="mma-drop__thumb" data-mma-thumb aria-hidden="true">Logo</span>
									<span class="mma-drop__txt"><b>Upload your logo</b><small>Square PNG, JPG or WebP, under 1 MB</small></span>
									<input id="mma-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
								</label>
							</div>
							<div class="mma-group">
								<h3>Message</h3>
								<label for="mma-head">Headline <small data-mma-count="50">0/50</small><input id="mma-head" type="text" name="c[headline]" maxlength="50" placeholder="Gift boxes under &#8369;500, delivered in 2 days" required></label>
								<label for="mma-text">One line about your offer <small data-mma-count="120">0/120</small><input id="mma-text" type="text" name="c[text]" maxlength="120" placeholder="What you sell and why it makes a great gift"></label>
								<label for="mma-btnl">Button label<input id="mma-btnl" type="text" name="c[button]" maxlength="24" value="Shop now"></label>
							</div>
							<div class="mma-group">
								<h3>Link</h3>
								<label for="mma-l0">Where the button goes <small>Your shop, Shopee, Lazada or website (https://)</small><input id="mma-l0" type="url" name="c[links][0][1]" placeholder="https://" required><input type="hidden" name="c[links][0][0]" value="Main"></label>
							</div>
							<details class="mma-more">
								<summary>Extras: more links, promo code, version B</summary>
								<div class="mma-more__body">
									<div class="mma-pair">
										<label for="mma-l1l">Second link label<input id="mma-l1l" type="text" name="c[links][1][0]" maxlength="24" placeholder="Facebook"></label>
										<label for="mma-l1">Second link<input id="mma-l1" type="url" name="c[links][1][1]" placeholder="https://"></label>
									</div>
									<div class="mma-pair">
										<label for="mma-l2l">Third link label<input id="mma-l2l" type="text" name="c[links][2][0]" maxlength="24" placeholder="Instagram"></label>
										<label for="mma-l2">Third link<input id="mma-l2" type="url" name="c[links][2][1]" placeholder="https://"></label>
									</div>
									<label for="mma-promo">Promo code <small>Shown on the card</small><input id="mma-promo" type="text" name="c[promo]" maxlength="24" placeholder="MANITO10"></label>
									<p class="mma-fine">Version B: we rotate both versions evenly and show which gets more clicks.</p>
									<label for="mma-bh">Version B headline<input id="mma-bh" type="text" name="c[b][headline]" maxlength="50"></label>
									<div class="mma-pair">
										<label for="mma-bt">Version B line<input id="mma-bt" type="text" name="c[b][text]" maxlength="120"></label>
										<label for="mma-bb">Version B button<input id="mma-bb" type="text" name="c[b][button]" maxlength="24"></label>
									</div>
								</div>
							</details>
						</div>
						<div class="mma-live">
							<div class="mma-live__tabs" role="tablist">
								<button type="button" class="is-on" data-mma-view="site" role="tab" aria-selected="true">On the site</button>
								<button type="button" data-mma-view="email" role="tab" aria-selected="false">In the email</button>
							</div>
							<div class="mma-live__frame" data-mma-frame="site"><div data-mma-preview></div></div>
							<p class="mma-fine">This is exactly how people will see it. It&rsquo;s always labelled &ldquo;Sponsored&rdquo;.</p>
						</div>
					</div>
				</section>

				<!-- Step 3: review and pay -->
				<section class="mma-step" data-step="3" hidden>
					<div class="mma-two mma-two--review">
						<div>
							<dl class="mma-summary" data-mma-summary></dl>
							<fieldset class="mma-cad" data-mma-billing>
								<legend>Billing</legend>
								<label class="mma-cad__opt"><input type="radio" name="cadence" value="weekly" checked><span><b>Weekly</b><em data-mma-price="weekly"></em><small>Most flexible</small></span></label>
								<label class="mma-cad__opt"><input type="radio" name="cadence" value="monthly"><span><b>Monthly</b><em data-mma-price="monthly"></em><small data-mma-save-note></small></span></label>
							</fieldset>
							<div class="mma-confirm">
							<ul class="mma-notes">
								<li>Renews automatically until you cancel. Cancel anytime from this page.</li>
								<li>We review every ad, usually within a day. Not approved? Full refund.</li>
								<li>Pay with PayPal or a debit or credit card.</li>
							</ul>
							<label class="mma-agree"><input type="checkbox" name="agree" value="1" data-mma-agree> <span>I confirm my ad follows the <a href="<?php echo esc_url( AdsPortal::pageUrl( 'rules' ) ); ?>" target="_blank" rel="noopener">ad guidelines</a>.</span></label>
							</div>
						</div>
						<div class="mma-live">
							<p class="mma-live__label">Your card</p>
							<div data-mma-preview2></div>
						</div>
					</div>
					<div class="mma-pay" data-mma-pay hidden></div>
				</section>

				<p class="mma-msg" data-mma-msg role="status" aria-live="polite"></p>
				<div class="mma-nav">
					<button type="button" class="mma-btn" data-mma-back hidden>Back</button>
					<button type="button" class="mma-btn mma-btn--primary" data-mma-next>Next: your card</button>
					<button type="submit" class="mma-btn mma-btn--primary" data-mma-save hidden>Continue to payment</button>
				</div>
			</form>
<?php endif; ?>
		</div>
	</section>
</div>
<?php
get_footer();
