<?php
/**
 * Template Name: Homepage
 *
 * Landing page: what Manito Manita is, that it is free, how it works, and what PRO adds.
 * Styles: assets/csslib/mm-home.css. "Create a free group" links open the #create-group popup
 * (header.php); [data-mm-track] links send GA4 events (Theme.initTracking).
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   2.0.0
 */

/**
 * No direct access to this file.
 *
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || die();

get_header();

$stats = Groups::siteStats();

/*
 * Optional photos and looping videos (ACF "Homepage photos & videos"). Empty slots keep the plain design.
 * Videos only load on tablets and desktops, when motion is allowed and data saver is off (script at the end);
 * phones keep the photo. The photo <img> sits under the video, so no poster is needed (it would be a second download).
 */
$hf = function ( $name ) { return function_exists( 'get_field' ) ? get_field( $name ) : ''; };
$bg = function ( $img, $video, $eager = false ) {
	if ( ! $img && ! $video ) return '';
	$out = '<div class="mmh-bg" aria-hidden="true">';
	if ( $img ) $out .= wp_get_attachment_image( $img, 'full', false, array( 'class' => 'mmh-bg__media', 'alt' => '', 'sizes' => '100vw', 'loading' => $eager ? 'eager' : 'lazy', 'decoding' => 'async' ) );
	if ( $video ) $out .= '<video class="mmh-bg__media mmh-bg__video" muted loop playsinline preload="none" data-mm-bgvideo="' . esc_url( $video ) . '"></video>';
	return $out . '</div>';
};
$heroBg  = $bg( $hf( 'mm_hero_image' ), $hf( 'mm_hero_video' ), true );
$finalBg = $bg( $hf( 'mm_final_image' ), $hf( 'mm_final_video' ) );
$dropsImg = $hf( 'mm_drops_image' );
$audience = array_filter( array(
	array( $hf( 'mm_aud_office' ), 'Office parties', 'Kris Kringle for the whole team, even across branches and remote staff.' ),
	array( $hf( 'mm_aud_family' ), 'Family', 'Noche Buena exchanges with titos, titas and cousins, near or abroad.' ),
	array( $hf( 'mm_aud_friends' ), 'Barkada &amp; class', 'Friends, org mates or classmates, all on one link.' ),
), function ( $a ) { return ! empty( $a[0] ); } );

$free = array(
	array( 'fas fa-users',               'Unlimited members',          'Invite the whole office, family or barkada with one link.' ),
	array( 'fas fa-shuffle',             'Fair, private shuffle',      'Nobody gets themselves, and only you can see your match.' ),
	array( 'fas fa-list-check',          'Wishlists with links',       'Add what you want, with links to Lazada, Shopee and more.' ),
	array( 'fas fa-wand-magic-sparkles', 'Gift ideas within budget',   'Suggestions picked from your match&rsquo;s wishlist and the group budget.' ),
	array( 'fas fa-envelope-open-text',  'Email notifications',        'Everyone gets their group link and match by email.' ),
	array( 'fas fa-bell',                'Exchange-day reminder',      'A friendly reminder a few days before the exchange.' ),
);

$pro = array(
	'Exclusions: couples or family won&rsquo;t get each other',
	'Weekly Drops: an optional themed mini-gift each week, with an email to everyone',
	'A fun reveal of who gave to whom after the exchange',
	'Anonymous Q&amp;A: members ask their match about sizes or likes without revealing themselves',
	'Unshuffle so late joiners can get in, then reshuffle (3 included)',
	'One-click reminders and an auto-shuffle on the date you pick',
	'Gift status board: see who has bought or given (never who drew whom)',
	'Remove or fix members, see all matches and export everything',
	'A co-organizer and your own colors for the group page',
	'No ads for everyone in the group',
);

$faq = array(
	array( 'Is Manito Manita really free?', 'Yes. Creating a group, inviting everyone, shuffling names, wishlists, gift ideas and emails are free, with no limit on members. PRO is an optional one-time add-on for organizers who want extra tools.' ),
	array( 'Do members need to create an account?', 'No. Members open the group link, enter their name, a screen name, their email and a password for that group. No app to install and no sign-up.' ),
	array( 'How does the shuffle work?', 'When everyone has joined, the organizer shuffles names in one tap. Each member is matched with someone else at random, never themselves, and each person only sees their own match.' ),
	array( 'What does PRO add, and how much is it?', 'PRO is a one-time &#8369;199 per group. It adds exclusions for couples, Weekly Drops (a themed mini-gift each week) with a reveal at the end, anonymous questions to your match, unshuffle for late joiners, reminders and auto-shuffle, a gift status board, member editing, matches and export, a co-organizer, group page colors, and removes ads for the whole group. You can pay with PayPal or any debit or credit card.' ),
	array( 'Can couples or family avoid getting each other?', 'Yes, with PRO. The organizer adds exclusions, like a couple or siblings, and the shuffle makes sure they never get each other.' ),
	array( 'Who can see my address and wishlist?', 'Your wishlist is visible to your group. Your address and contact details are shown only to the person who drew you, and to the organizer of a PRO group in the export.' ),
);
?>
<div class="mm-home">
<script>(function(d){var r=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;if(r||!('IntersectionObserver' in window))return;d.className+=' mm-anim';setTimeout(function(){if(!window.mmRevealReady)d.className+=' mm-reveal-all';},3000);})(document.documentElement);</script>

	<section class="mmh-hero<?php echo $heroBg ? ' has-bg' : ''; ?>" data-mm-hero>
		<?php echo $heroBg; ?>
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mmh-hero__grid">
			<div class="mmh-hero__copy">
				<p class="mmh-eyebrow">Online Manito Manita &amp; Secret Santa</p>
				<h1 class="mmh-hero__title">Shuffle names for your gift exchange <span>in 2 minutes</span></h1>
				<p class="mmh-hero__lead">Create a group, share one link, and everyone privately sees who they&rsquo;re buying for, with wishlists and gift ideas in one place.</p>
				<div class="mmh-hero__actions">
					<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="hero">Create a free group</a>
					<button type="button" class="mmh-btn mmh-btn--ghost" data-mm-open-link>Have a group link?</button>
				</div>
				<form class="mmh-link" data-mm-link-form hidden>
					<label for="mmh-link-input">Paste your group link</label>
					<div class="mmh-link__row">
						<input id="mmh-link-input" type="url" inputmode="url" placeholder="https://manitomanita.com/group-dashboard/?gid=..." autocomplete="off">
						<button type="submit" class="mmh-btn mmh-btn--dark">Open</button>
					</div>
					<p class="mmh-link__error" data-mm-link-error hidden>That doesn&rsquo;t look like a Manito Manita group link. Check the link in your invite or email.</p>
				</form>
				<ul class="mmh-hero__trust">
					<li><i class="fas fa-check"></i> Free forever</li>
					<li><i class="fas fa-check"></i> No app, no sign-up</li>
					<li><i class="fas fa-check"></i> Works on any phone</li>
				</ul>
			</div>

			<div class="mmh-hero__visual" aria-hidden="true">
				<div class="mmh-phone">
					<div class="mmh-phone__screen">
						<div class="mmh-mock__head">
							<div class="mmh-mock__title">Office Christmas Party</div>
							<div class="mmh-mock__meta"><span>&#8369;500</span><span>Dec 19</span><span>12 members</span></div>
						</div>
						<div class="mmh-mock__status"><i class="fas fa-circle-check"></i> The group has been shuffled</div>
						<div class="mmh-mock__match">
							<div class="mmh-mock__label">You&rsquo;re buying for</div>
							<div class="mmh-mock__person"><span class="mmh-mock__avatar">A</span><div><strong>Ana</strong><small>3 wishes</small></div></div>
							<ul class="mmh-mock__wishes">
								<li>Wireless earbuds</li>
								<li>Scented candle set</li>
								<li>Tumbler (any colour)</li>
							</ul>
						</div>
						<div class="mmh-mock__ideas">
							<div class="mmh-mock__label">Gift ideas within &#8369;500</div>
							<div class="mmh-mock__chips"><span>Mugs &amp; tumblers</span><span>Skincare sets</span><span>Desk gadgets</span></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<?php if ( $stats['groups'] >= 100 ) : ?>
	<section class="mmh-stats" aria-label="Manito Manita in numbers">
		<div class="mmh-wrap mmh-stats__row">
			<div data-reveal><strong><span data-count="<?php echo (int) $stats['groups']; ?>"><?php echo esc_html( number_format_i18n( $stats['groups'] ) ); ?></span>+</strong><span>groups created</span></div>
			<div data-reveal style="--d:.1s"><strong><span data-count="<?php echo (int) $stats['members']; ?>"><?php echo esc_html( number_format_i18n( $stats['members'] ) ); ?></span>+</strong><span>members joined</span></div>
			<div data-reveal style="--d:.2s"><strong>&#8369;0</strong><span>to use, forever</span></div>
		</div>
	</section>
	<?php endif; ?>

	<section class="mmh-section" id="how-it-works">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>How it works</h2>
			<ol class="mmh-steps">
				<li data-reveal><span class="mmh-steps__num">1</span><h3>Create your group</h3><p>Add a group name, the budget and the exchange date. Takes a minute.</p></li>
				<li data-reveal style="--d:.12s"><span class="mmh-steps__num">2</span><h3>Share the link</h3><p>Send it on Messenger, Viber or email. Members join and add their wishlist.</p></li>
				<li data-reveal style="--d:.24s"><span class="mmh-steps__num">3</span><h3>Shuffle</h3><p>One tap, and everyone privately sees their match, wishlist and gift ideas.</p></li>
			</ol>
			<div class="mmh-center" data-reveal><a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="how-it-works">Create a free group</a></div>
		</div>
	</section>

	<?php if ( $audience ) : ?>
	<section class="mmh-section mmh-section--tight" id="who-its-for">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>Made for every kind of group</h2>
			<div class="mmh-aud">
				<?php foreach ( array_values( $audience ) as $i => $a ) : ?>
				<figure class="mmh-aud__card" data-reveal style="--d:<?php echo esc_attr( $i * 0.1 ); ?>s">
					<?php echo wp_get_attachment_image( $a[0], 'large', false, array( 'class' => 'mmh-aud__img', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 900px) 360px, 100vw' ) ); ?>
					<figcaption><strong><?php echo $a[1]; ?></strong><span><?php echo $a[2]; ?></span></figcaption>
				</figure>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="mmh-section mmh-section--soft">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>Everything you need, free</h2>
			<p class="mmh-sub" data-reveal style="--d:.08s">No trial, no limits on members, no credit card.</p>
			<div class="mmh-features">
				<?php foreach ( $free as $i => $f ) : ?>
				<div class="mmh-feature" data-reveal style="--d:<?php echo esc_attr( ( $i % 3 ) * 0.1 ); ?>s">
					<span class="mmh-feature__icon"><i class="<?php echo esc_attr( $f[0] ); ?>"></i></span>
					<h3><?php echo $f[1]; ?></h3>
					<p><?php echo $f[2]; ?></p>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="mmh-wlband" aria-label="My Wishlist">
		<div class="mmh-wrap">
			<div class="mmh-wlband__card" data-reveal>
				<span class="mmh-wlband__icon"><i class="fas fa-heart"></i></span>
				<div class="mmh-wlband__txt"><strong>Not just for Christmas</strong><span>Make one wishlist for birthdays, debuts and every occasion, and share it with a single link. Friends can reserve a gift so nobody buys the same thing.</span></div>
				<a href="<?php echo esc_url( home_url( '/my-wishlist/' ) ); ?>" class="mmh-btn mmh-btn--ghost-dark" data-mm-track="wishlist_cta" data-mm-slot="home-band">Make my wishlist</a>
			</div>
		</div>
	</section>

	<section class="mmh-section mmh-drops" id="weekly-drops">
		<div class="mmh-wrap mmh-drops__grid">
			<div class="mmh-drops__copy" data-reveal>
				<p class="mmh-eyebrow">PRO &middot; optional activity</p>
				<h2 class="mmh-h2 mml-left">Weekly Drops: Manito Manita, <span>the Filipino way</span></h2>
				<p class="mmh-drops__lead">On top of the main gift, everyone leaves their Manito or Manita a small themed gift each week until the party. We send the theme, gift ideas and the reveal.</p>
				<ol class="mmh-drops__steps">
					<li><b>Shuffle</b> names as usual.</li>
					<li>Each week a theme <b>drops</b>, like <i>something sweet</i>. Everyone gets an email with ideas.</li>
					<li>Leave a <b>small secret gift</b> that fits the theme (&#8369;50 to &#8369;150).</li>
					<li>On exchange day, give the main gift and <b>reveal</b> who gave to whom.</li>
				</ol>
				<div class="mmh-drops__actions">
					<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="weekly-drops">Create a free group</a>
					<a href="<?php echo esc_url( home_url( '/monito-monita-themes/' ) ); ?>" class="mmh-drops__link">See 50+ theme ideas <i class="fas fa-arrow-right"></i></a>
				</div>
			</div>
			<div class="mmh-drops__side" data-reveal style="--d:.12s">
			<?php if ( $dropsImg ) echo wp_get_attachment_image( $dropsImg, 'large', false, array( 'class' => 'mmh-drops__img', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 900px) 420px, 100vw' ) ); ?>
			<ol class="mmh-drops__line" aria-label="Example Weekly Drops">
				<li class="is-done"><span>Nov 28</span>Something sweet</li>
				<li class="is-now"><span>Dec 5</span>Something soft</li>
				<li><span>Dec 12</span>Something red</li>
				<li class="is-final"><span>Dec 19</span>Main gift &amp; reveal</li>
			</ol>
			</div>
		</div>
	</section>

	<section class="mmh-section" id="pro">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>Free forever. PRO when you need more control.</h2>
			<p class="mmh-sub" data-reveal style="--d:.08s">PRO is optional. Most groups never need it, but organizers of bigger groups love it.</p>
			<div class="mmh-plans">
				<div class="mmh-plan mmh-plan--free" data-reveal>
					<div class="mmh-plan__badge">Free forever</div>
					<div class="mmh-plan__name">Free</div>
					<div class="mmh-plan__price">&#8369;0</div>
					<ul class="mmh-plan__list">
						<li>Unlimited members</li>
						<li>Private shuffle</li>
						<li>Wishlists and gift ideas</li>
						<li>Email notifications and reminders</li>
						<li>Comments on wishlists</li>
					</ul>
					<a href="#create-group" class="mmh-btn mmh-btn--primary mmh-btn--block" data-mm-track="create_group_click" data-mm-slot="plans-free">Create a free group</a>
				</div>
				<div class="mmh-plan mmh-plan--pro" data-mm-promo="pro-table" data-reveal style="--d:.15s">
					<div class="mmh-plan__badge mmh-plan__badge--pro">For organizers</div>
					<div class="mmh-plan__name">PRO</div>
					<div class="mmh-plan__price">&#8369;199 <small>one-time, per group</small></div>
					<p class="mmh-plan__note">Everything in Free, plus:</p>
					<ul class="mmh-plan__list">
						<?php foreach ( $pro as $p ) : ?><li><?php echo $p; ?></li><?php endforeach; ?>
					</ul>
					<p class="mmh-plan__how">Upgrade anytime from your group&rsquo;s dashboard. Pay with PayPal or any debit/credit card, no PayPal account needed.</p>
				</div>
			</div>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft" id="faq">
		<div class="mmh-wrap mmh-wrap--narrow">
			<h2 class="mmh-h2" data-reveal>Questions</h2>
			<div class="mmh-faq" data-reveal style="--d:.1s">
				<?php foreach ( $faq as $i => $q ) : ?>
				<details<?php echo $i === 0 ? ' open' : ''; ?>>
					<summary><?php echo $q[0]; ?></summary>
					<p><?php echo $q[1]; ?></p>
				</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="mmh-final<?php echo $finalBg ? ' has-bg' : ''; ?>">
		<?php echo $finalBg; ?>
		<div class="mmh-wrap mmh-center" data-reveal>
			<h2>Ready for this year&rsquo;s exchange?</h2>
			<p>Set it up in 2 minutes. Free forever.</p>
			<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="final">Create a free group</a>
		</div>
	</section>

	<div class="mmh-sticky" data-mm-sticky aria-hidden="true">
		<a href="#create-group" class="mmh-btn mmh-btn--primary mmh-btn--block" data-mm-track="create_group_click" data-mm-slot="sticky" tabindex="-1">Create a free group</a>
	</div>
</div>

<?php if ( strpos( $heroBg . $finalBg, 'data-mm-bgvideo' ) !== false ) : ?>
<script>(function(){var c=navigator.connection,m=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;if(m||window.innerWidth<768||(c&&(c.saveData||/2g/.test(c.effectiveType||''))))return;var v=document.querySelectorAll('video[data-mm-bgvideo]');var go=function(el){if(el.src)return;el.src=el.getAttribute('data-mm-bgvideo');el.addEventListener('playing',function(){el.classList.add('is-playing');});var p=el.play();if(p&&p.catch)p.catch(function(){});};if(!('IntersectionObserver' in window)){[].forEach.call(v,go);return;}var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){go(e.target);e.target.play&&e.target.src&&e.target.play().catch(function(){});}else if(e.target.src){e.target.pause();}});},{rootMargin:'200px'});[].forEach.call(v,function(el){io.observe(el);});})();</script>
<?php endif; ?>
<script type="application/ld+json"><?php
echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map( function ( $q ) {
		return array(
			'@type'          => 'Question',
			'name'           => html_entity_decode( $q[0], ENT_QUOTES, 'UTF-8' ),
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( $q[1], ENT_QUOTES, 'UTF-8' ) ),
		);
	}, $faq ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?></script>
<?php
get_footer();
