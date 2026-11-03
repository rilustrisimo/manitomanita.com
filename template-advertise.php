<?php
/**
 * Template Name: Advertise
 *
 * /advertise/: the audience in numbers, placements with live seats and prices, a sample results
 * dashboard, how every number is counted, what advertisers manage themselves, booking steps, FAQ.
 * Booking happens at /advertiser/ (template-advertiser.php). Logic: classes/class-ads.php, class-ads-portal.php.
 * Styles: mm-home.css + mm-ads.css.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.1.0
 */

defined( 'ABSPATH' ) || die();

get_header();

$aud = AdsPortal::audience();
$book = AdsPortal::pageUrl( 'account' );
$peso = html_entity_decode( '&#8369;', ENT_QUOTES, 'UTF-8' );
$sample = array( 'id' => 0, 'v' => 'a', 'c' => array( 'brand' => 'Your brand', 'logo' => '', 'headline' => 'Gift boxes under ' . $peso . '500, delivered in 2 days', 'text' => 'Your short line here: what you sell and why it makes a great gift.', 'button' => 'Shop now', 'links' => array( array( 'Shop', 'https://example.com' ), array( 'Facebook', 'https://example.com' ) ), 'promo' => 'MANITO10', 'b' => array() ) );

// Sample dashboard (example numbers, labelled as such on the page).
$days = array( 980, 1120, 1340, 1210, 1460, 1630, 1390, 1180, 1420, 1510, 1700, 1460, 1290, 1740 );
$clicksDay = array( 21, 24, 30, 26, 33, 38, 29, 25, 31, 34, 40, 32, 28, 41 );
$maxDay = max( $days );

$faq = array(
	array( 'How much does it cost to advertise on Manito Manita?', 'From &#8369;300 a week for gift guide and registry placements, and &#8369;800 to &#8369;1,500 a week for the match screen, group page and shuffle emails in peak season (Nov 1 &ndash; Dec 20). Monthly billing costs less per week. There are no setup fees and you can cancel anytime.' ),
	array( 'Is this good for Shopee and Lazada sellers?', 'Yes. Most people here are about to buy a gift within a set budget, and your button can link straight to your Shopee or Lazada store, your website or your Facebook page. Add a promo code to see how many sales come from us.' ),
	array( 'Can a small business advertise for Christmas?', 'Yes. Booking takes a few minutes, there&rsquo;s no agency or contract, and weekly billing lets you try one week first. It works well for gift shops, bakeries, gift boxes, personalised items, party suppliers and online sellers.' ),
	array( 'Who sees my ad?', 'Members of Secret Santa, Kris Kringle and Monito Monita groups in the Philippines (offices, schools, families and barkadas) at the moment they find out who they&rsquo;re buying a gift for, plus visitors to our gift guides. Most groups set a budget of &#8369;300 to &#8369;1,000 per gift.' ),
	array( 'How do I pay?', 'With PayPal or a debit or credit card through PayPal, weekly or monthly. It renews automatically until you cancel, and you can cancel anytime from your account.' ),
	array( 'What happens after I pay?', 'We review every ad, usually within a day. Once approved it goes live right away. If we can&rsquo;t approve it, your payment is refunded in full.' ),
	array( 'What does a &ldquo;seat&rdquo; mean?', 'Each placement has ' . Ads::SEATS . ' seats. Ads in the same placement take turns evenly, so each one shows in about a third of the views. When all seats are taken you can join the waitlist; we&rsquo;ll hold the next free seat for you for 48 hours.' ),
	array( 'How do you count views and clicks?', 'A view counts only when at least half of your card is on screen for a full second. Clicks go through our link so we can count them, with bots, email link scanners and double clicks filtered out. Email opens are estimates, because some email apps load images automatically.' ),
	array( 'When do Christmas placements run?', 'From Sep 1 to Dec 20, with peak prices from Nov 1, when most groups shuffle and shop. Gift guide placements run all year.' ),
	array( 'Can I change my ad?', 'Yes. Edit it anytime from your account. Edits to a live ad are reviewed first, and your current ad keeps running meanwhile.' ),
);
?>
<div class="mm-home mm-landing mm-adv">
	<section class="mmh-hero mma-hero" data-mm-hero>
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mma-hero__grid">
			<div class="mma-hero__copy">
				<p class="mmh-eyebrow">Advertise on Manito Manita</p>
				<h1 class="mmh-hero__title">Advertise to Filipino gift buyers <span>the moment they shop</span></h1>
				<p class="mmh-hero__lead">When a group is shuffled, everyone finds out who they&rsquo;re buying for and starts looking for a gift. Put your shop in front of them on the match screen, in the &ldquo;your match is ready&rdquo; email and on our gift guides, and see every view and click.</p>
				<div class="mmh-hero__actions">
					<a class="mmh-btn mmh-btn--primary" href="<?php echo esc_url( $book ); ?>">Book a spot</a>
					<a class="mmh-btn mmh-btn--ghost" href="#placements">Placements and prices</a>
				</div>
				<ul class="mma-trust">
					<li>Weekly or monthly, cancel anytime</li>
					<li>Reviewed before it goes live</li>
					<li>Full refund if not approved</li>
				</ul>
			</div>
			<div class="mma-hero__panel" aria-label="Sample results for one week">
				<p class="mma-panel__label">Sample week &middot; Shuffle email</p>
				<div class="mma-panel__kpis">
					<div><b>6,240</b><span>emails sent</span></div>
					<div><b>4,380</b><span>opens (est.)</span></div>
					<div><b>187</b><span>clicks</span></div>
					<div><b><?php echo $peso; ?>8.02</b><span>per click</span></div>
				</div>
				<div class="mma-spark" aria-hidden="true">
					<?php foreach ( array_slice( $clicksDay, 7 ) as $v ) echo '<i style="height:' . (int) round( $v / 41 * 100 ) . '%"></i>'; ?>
				</div>
				<p class="mma-panel__foot">Example numbers. Your account shows your own, live.</p>
			</div>
		</div>
	</section>

	<section class="mmh-section mma-band">
		<div class="mmh-wrap">
			<div class="mma-stats">
				<div><b>43,900</b><span>people in the 30 days before Christmas last year</span></div>
				<?php if ( $aud['people30'] ) : ?><div><b><?php echo esc_html( number_format( $aud['people30'] ) ); ?></b><span>people in the last 30 days</span></div><?php endif; ?>
				<?php if ( $aud['groups'] ) : ?><div><b><?php echo esc_html( number_format( $aud['groups'] ) ); ?></b><span>groups created since Sep 1</span></div><?php endif; ?>
				<?php if ( $aud['members'] ) : ?><div><b><?php echo esc_html( number_format( $aud['members'] ) ); ?></b><span>members joined since Sep 1</span></div><?php endif; ?>
				<div><b>3&ndash;5 min</b><span>average time on site per person in December</span></div>
			</div>
			<p class="mma-note">From our own analytics, updated daily. Visitors are mostly in the Philippines, with Filipinos abroad in the US, Singapore and Australia, split about evenly between phones and computers.</p>
		</div>
	</section>

	<section class="mmh-section">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">Christmas advertising in the Philippines, made simple</h2>
			<p class="mmh-sub mma-intro">Manito Manita is where offices, schools and families draw names for their Secret Santa, Monito Monita and Kris Kringle exchanges. That makes it one of the few places online to reach people who are about to buy a gift, with a budget already set. Shopee and Lazada sellers, gift shops, bakeries and small businesses can book a sponsored spot in minutes and track every view and click.</p>
			<h3 class="mma-h3">Why it works</h3>
			<div class="mma-why">
				<div><h3>They&rsquo;re about to buy</h3><p>Your card appears right after people learn who they&rsquo;re giving to, next to that person&rsquo;s wishlist and gift ideas.</p></div>
				<div><h3>The budget is known</h3><p>Every group sets a gift budget, usually &#8369;300 to &#8369;1,000. Your offer lands with people shopping in that range.</p></div>
				<div><h3>Offices, schools and families</h3><p>Company teams, classes and big families run their Christmas exchanges here, often 20 to 60 people per group.</p></div>
			</div>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft" id="placements">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">Placements and prices</h2>
			<p class="mmh-sub">Per seat. <?php echo Ads::SEATS; ?> seats per placement take turns evenly. Peak runs Nov 1 &ndash; Dec 20.</p>
			<div class="mma-places">
				<?php foreach ( Ads::placements() as $key => $pl ) :
					if ( ! AdsPortal::sellable( $key ) ) continue;
					$season = Ads::season( $key );
					$prices = Ads::prices()[ $key ];
					$row = $season === 'closed' ? $prices['peak'] : $prices[ $season ];
					$left = empty( $pl['target'] ) ? AdsPortal::seatsLeft( $key ) : null;
					?>
				<article class="mma-place">
					<header>
						<h3><?php echo esc_html( $pl['label'] ); ?></h3>
						<?php if ( $season === 'closed' ) : ?><span class="mma-chip is-wait">Opens Sep 1</span>
						<?php elseif ( $left === null ) : ?><span class="mma-chip">Per page</span>
						<?php elseif ( $left > 0 ) : ?><span class="mma-chip is-open"><?php echo (int) $left; ?> of <?php echo Ads::SEATS; ?> seats open</span>
						<?php else : ?><span class="mma-chip is-wait">Full &middot; waitlist</span><?php endif; ?>
					</header>
					<p><?php echo esc_html( $pl['where'] ); ?>.</p>
					<p class="mma-price"><b>&#8369;<?php echo esc_html( number_format( $row[0] ) ); ?></b> / week <span>or &#8369;<?php echo esc_html( number_format( $row[1] ) ); ?> / month</span></p>
					<p class="mma-season"><?php echo $pl['season'] === 'xmas' ? 'Sep 1 &ndash; Dec 20' : 'All year'; ?><?php echo $season === 'shoulder' ? ' &middot; peak from Nov 1: &#8369;' . esc_html( number_format( $prices['peak'][0] ) ) . '/week' : ''; ?></p>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="mmh-section">
		<div class="mmh-wrap mma-split">
			<div>
				<h2 class="mmh-h2 mml-left">One clean card, clearly labelled</h2>
				<p>Your logo, brand, a headline, one line, a button and an optional promo code. It looks the same on the site and in emails, on phones and computers.</p>
				<ul class="mma-list">
					<li>One ad per page or email, never crowded</li>
					<li>Never shown to PRO groups (they pay for no ads)</li>
					<li>Up to 3 links, each tracked separately</li>
					<li>Optional version B: we rotate both and show which one gets more clicks</li>
				</ul>
			</div>
			<div class="mma-preview"><?php echo Ads::cardHtml( $sample, 'match-ideas', 0, true ); ?></div>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft" id="results">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">See exactly what you paid for</h2>
			<p class="mmh-sub">This is what your account shows. The numbers below are examples for a two-week booking.</p>
			<div class="mma-dash" aria-label="Sample advertiser dashboard with example numbers">
				<div class="mma-dash__top">
					<span class="mma-dash__title">Regalo Box PH &middot; Match screen + shuffle email</span>
					<span class="mma-chip is-open">Live</span>
					<span class="mma-dash__range">Last 14 days &middot; example</span>
				</div>
				<div class="mma-dash__kpis">
					<div><span>Views</span><b>18,420</b><em class="up">+12% vs last week</em></div>
					<div><span>Unique viewers</span><b>9,870</b><em>people</em></div>
					<div><span>Emails sent</span><b>6,240</b><em>4,380 opened (est.)</em></div>
					<div><span>Clicks</span><b>412</b><em>371 unique</em></div>
					<div><span>Click-through rate</span><b>1.81%</b><em class="up">vs 1.2% placement average</em></div>
					<div><span>Cost per click</span><b><?php echo $peso; ?>11.89</b><em><?php echo $peso; ?>4,900 paid</em></div>
				</div>
				<div class="mma-dash__grid">
					<div class="mma-dash__panel mma-dash__wide">
						<h3>Views and clicks per day</h3>
						<div class="mma-bars" role="img" aria-label="Views per day rising from about 1,000 to 1,700 over two weeks">
							<?php foreach ( $days as $i => $v ) : ?>
							<div class="mma-bars__col"><i style="height:<?php echo (int) round( $v / $maxDay * 100 ); ?>%"></i><b style="height:<?php echo (int) round( $clicksDay[ $i ] / 41 * 60 ); ?>%"></b></div>
							<?php endforeach; ?>
						</div>
						<p class="mma-legend"><span class="v"></span>Views <span class="c"></span>Clicks</p>
					</div>
					<div class="mma-dash__panel">
						<h3>Clicks by link</h3>
						<table class="mma-table"><tbody>
							<tr><td>Shop now (Shopee)</td><td>338</td><td>82%</td></tr>
							<tr><td>Facebook page</td><td>51</td><td>12%</td></tr>
							<tr><td>Instagram</td><td>23</td><td>6%</td></tr>
						</tbody></table>
						<h3>Version A vs B</h3>
						<table class="mma-table"><tbody>
							<tr><td>A &middot; &ldquo;Gift boxes under &#8369;500&rdquo;</td><td>1.52%</td><td></td></tr>
							<tr><td>B &middot; &ldquo;Free gift wrap this week&rdquo;</td><td>2.09%</td><td><span class="mma-win">Winner</span></td></tr>
						</tbody></table>
					</div>
					<div class="mma-dash__panel">
						<h3>Who clicks <small>group budget</small></h3>
						<div class="mma-hbar"><span>&#8369;300 and below</span><i style="width:38%"></i><b>38%</b></div>
						<div class="mma-hbar"><span>&#8369;301 &ndash; &#8369;999</span><i style="width:47%"></i><b>47%</b></div>
						<div class="mma-hbar"><span>&#8369;1,000 and up</span><i style="width:15%"></i><b>15%</b></div>
						<h3>Group size</h3>
						<div class="mma-hbar"><span>Up to 10</span><i style="width:29%"></i><b>29%</b></div>
						<div class="mma-hbar"><span>11 &ndash; 30</span><i style="width:51%"></i><b>51%</b></div>
						<div class="mma-hbar"><span>31 and up</span><i style="width:20%"></i><b>20%</b></div>
					</div>
					<div class="mma-dash__panel">
						<h3>Best times to reach them</h3>
						<div class="mma-heat" role="img" aria-label="Most clicks on weekday afternoons">
							<?php
							$dnames = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
							$curve = array( 0.15, 0.1, 0.3, 0.7, 1, 0.85, 0.6, 0.35 );
							foreach ( $dnames as $di => $dn ) {
								echo '<span class="mma-heat__d">' . $dn . '</span>';
								foreach ( $curve as $hi => $cv ) {
									$lv = $cv * ( $di < 5 ? 1 : 0.45 ) * ( $di === 3 || $di === 4 ? 1.1 : 1 );
									echo '<i style="opacity:' . esc_attr( round( max( 0.08, min( 1, $lv ) ), 2 ) ) . '"></i>';
								}
							}
							?>
							<span></span><em>8a</em><em>10a</em><em>12n</em><em>2p</em><em>4p</em><em>6p</em><em>8p</em><em>10p</em>
						</div>
						<p class="mma-fine">Weekday afternoons are busiest: people plan gifts at work.</p>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="mmh-section" id="tracking">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">How we count, in plain terms</h2>
			<p class="mmh-sub">Our own tracking, the same rules for every advertiser. It isn&rsquo;t affected by ad blockers.</p>
			<div class="mma-count">
				<div><h3>Viewable views</h3><p>A view counts only when at least half of your card is on screen for one full second. Scrolling past doesn&rsquo;t count.</p></div>
				<div><h3>Unique viewers</h3><p>Each browser counts once per day, however many times the card is shown.</p></div>
				<div><h3>Emails and opens</h3><p>Every email that includes your card is counted when it&rsquo;s sent. Opens are estimates: some email apps load images automatically, others block them.</p></div>
				<div><h3>Real clicks only</h3><p>Clicks pass through our link. Bots, email security scanners and repeat clicks within 30 seconds are filtered out.</p></div>
				<div><h3>Tagged for your analytics</h3><p>Every link carries UTM tags, so visits from us show up in Google Analytics, Shopify or your shop&rsquo;s own stats:</p><code>utm_source=manitomanita&amp;utm_medium=email&amp;utm_campaign=ad-128</code></div>
				<div><h3>Private by design</h3><p>You see totals and ranges, like group budget and size, never names, emails or group details. Small groups of numbers are hidden.</p></div>
			</div>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft" id="manage">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">Manage everything yourself</h2>
			<p class="mmh-sub">One account for all your ads. No emails back and forth.</p>
			<div class="mma-manage">
				<div><h3>Book in minutes</h3><p>Pick a placement, upload your logo, write your card with a live preview, then pay.</p></div>
				<div><h3>Edit anytime</h3><p>Change your headline, links or promo code. Your current card keeps running while we review the edit.</p></div>
				<div><h3>Test two versions</h3><p>Add a version B. We rotate both and show which gets more clicks; keep the winner.</p></div>
				<div><h3>Weekly or monthly</h3><p>Switch billing whenever you like. It changes from your next charge.</p></div>
				<div><h3>Cancel anytime</h3><p>No more charges after you cancel. Your ad keeps running until the end of the period you paid for.</p></div>
				<div><h3>Waitlist when full</h3><p>If all seats are taken, join the waitlist. We email you and hold the next free seat for 48 hours.</p></div>
			</div>
		</div>
	</section>

	<section class="mmh-section">
		<div class="mmh-wrap">
			<h2 class="mmh-h2">How booking works</h2>
			<ol class="mmh-steps">
				<li><span class="mmh-steps__num">1</span><h3>Sign in with your email</h3><p>We email you a 6-digit code. No password needed.</p></li>
				<li><span class="mmh-steps__num">2</span><h3>Make your card</h3><p>Upload your logo, write a headline and add your link, with a live preview.</p></li>
				<li><span class="mmh-steps__num">3</span><h3>Pay weekly or monthly</h3><p>With PayPal or your card. Cancel anytime.</p></li>
				<li><span class="mmh-steps__num">4</span><h3>Go live</h3><p>We review it, usually within a day. Then it runs and renews on its own.</p></li>
			</ol>
			<p class="mma-note mmh-center">Read the <a href="<?php echo esc_url( AdsPortal::pageUrl( 'rules' ) ); ?>">ad guidelines</a> before booking. Gambling, adult content, investment schemes and political ads aren&rsquo;t accepted.</p>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft">
		<div class="mmh-wrap mmh-wrap--narrow">
			<h2 class="mmh-h2">Questions</h2>
			<div class="mmh-faq">
				<?php foreach ( $faq as $i => $q ) : ?>
				<details<?php echo $i === 0 ? ' open' : ''; ?>><summary><?php echo $q[0]; ?></summary><p><?php echo $q[1]; ?></p></details>
				<?php endforeach; ?>
			</div>
			<p class="mmh-center mma-cta"><a class="mmh-btn mmh-btn--primary" href="<?php echo esc_url( $book ); ?>">Book a spot</a></p>
		</div>
	</section>
</div>
<script type="application/ld+json"><?php
echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map( function ( $q ) {
		return array( '@type' => 'Question', 'name' => html_entity_decode( $q[0], ENT_QUOTES, 'UTF-8' ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( wp_strip_all_tags( $q[1] ), ENT_QUOTES, 'UTF-8' ) ) );
	}, $faq ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?></script>
<script type="application/ld+json"><?php
$mmLow = PHP_INT_MAX; $mmHigh = 0;
foreach ( Ads::placements() as $k => $pl ) {
	if ( ! AdsPortal::sellable( $k ) ) continue;
	foreach ( Ads::prices()[ $k ] as $row ) { $mmLow = min( $mmLow, (int) $row[0] ); $mmHigh = max( $mmHigh, (int) $row[1] ); }
}
echo wp_json_encode( array(
	'@context'    => 'https://schema.org',
	'@type'       => 'Service',
	'name'        => 'Advertising on Manito Manita',
	'serviceType' => 'Online advertising',
	'description' => 'Sponsored placements that reach Filipino Secret Santa, Monito Monita and Kris Kringle gift buyers: match screen, group pages, shuffle and reminder emails, and gift guides. Weekly or monthly, with live stats.',
	'url'         => AdsPortal::pageUrl( 'advertise' ),
	'areaServed'  => array( '@type' => 'Country', 'name' => 'Philippines' ),
	'audience'    => array( '@type' => 'BusinessAudience', 'audienceType' => 'Shops, brands and online sellers' ),
	'provider'    => array( '@type' => 'Organization', 'name' => 'Manito Manita', 'url' => home_url( '/' ) ),
	'offers'      => array( '@type' => 'AggregateOffer', 'priceCurrency' => 'PHP', 'lowPrice' => $mmLow, 'highPrice' => $mmHigh, 'offerCount' => count( array_filter( array_keys( Ads::placements() ), array( 'AdsPortal', 'sellable' ) ) ) ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?></script>
<?php
get_footer();
