<?php
/**
 * Search landing page (Landing class): tool pages per search term and gift guides.
 * Uses the homepage styles (mm-home.css) plus a few .mml-* additions.
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

$p = Landing::current();
$slot = 'landing:' . $p['slug'];
$all = Landing::pages();

get_header();
?>
<div class="mm-home mm-landing">
<script>(function(d){var r=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;if(r||!('IntersectionObserver' in window))return;d.className+=' mm-anim';setTimeout(function(){if(!window.mmRevealReady)d.className+=' mm-reveal-all';},3000);})(document.documentElement);</script>

	<section class="mmh-hero mml-hero" data-mm-hero>
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap">
			<nav class="mml-crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span aria-hidden="true">/</span> <span><?php echo wp_strip_all_tags( $p['eyebrow'] ); ?></span></nav>
			<div class="mmh-hero__copy mml-hero__copy">
				<p class="mmh-eyebrow"><?php echo $p['eyebrow']; ?></p>
				<h1 class="mmh-hero__title"><?php echo $p['h1']; ?></h1>
				<p class="mmh-hero__lead"><?php echo $p['lead']; ?></p>
				<div class="mmh-hero__actions">
					<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="<?php echo esc_attr( $slot ); ?>">Create a free group</a>
					<?php if ( $p['type'] === 'guide' ) : ?>
						<a href="#ideas" class="mmh-btn mmh-btn--ghost">See the gift ideas</a>
					<?php else : ?>
						<a href="#how-it-works" class="mmh-btn mmh-btn--ghost">How it works</a>
					<?php endif; ?>
				</div>
				<ul class="mmh-hero__trust">
					<li><i class="fas fa-check"></i> Free forever</li>
					<li><i class="fas fa-check"></i> No app, no sign-up</li>
					<li><i class="fas fa-check"></i> Works on any phone</li>
				</ul>
			</div>
		</div>
	</section>

	<section class="mmh-section mmh-section--soft">
		<div class="mmh-wrap mmh-wrap--narrow mml-prose" data-reveal>
			<h2 class="mmh-h2 mml-left"><?php echo $p['intro']['title']; ?></h2>
			<?php echo $p['intro']['body']; ?>
			<p class="mml-updated">Updated <?php echo esc_html( date_i18n( 'F Y', strtotime( Landing::UPDATED ) ) ); ?></p>
		</div>
	</section>

	<?php if ( $p['type'] === 'guide' ) : ?>
	<section class="mmh-section" id="ideas">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>Gift ideas under &#8369;<?php echo esc_html( number_format( $p['budget'] ) ); ?></h2>
			<p class="mmh-sub" data-reveal>Each button opens the store already filtered to &#8369;<?php echo esc_html( number_format( $p['budget'] ) ); ?> and below.</p>
			<?php foreach ( $p['groups'] as $g ) : ?>
				<h3 class="mml-group" data-reveal><?php echo esc_html( $g[0] ); ?></h3>
				<div class="mml-ideas">
					<?php foreach ( $g[1] as $i => $idea ) : ?>
					<div class="mml-idea" data-reveal style="--d:<?php echo esc_attr( ( $i % 3 ) * 0.08 ); ?>s">
						<strong><?php echo esc_html( $idea[0] ); ?></strong>
						<div class="mml-idea__shops">
							<?php
							echo Affiliate::anchor( Affiliate::searchUrl( 'lazada', $idea[1], $p['budget'] ), 'Lazada', 'guide-' . $p['slug'], '', 'mml-shop mml-shop--lazada' );
							echo Affiliate::anchor( Affiliate::searchUrl( 'shopee', $idea[1], $p['budget'] ), 'Shopee', 'guide-' . $p['slug'], '', 'mml-shop mml-shop--shopee' );
							?>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
			<div class="mml-tip" data-reveal>
				<i class="fas fa-gift"></i>
				<div><strong>Organizing the exchange?</strong> Create a free group: everyone adds a wishlist, so nobody has to guess. Gift ideas are picked within your budget automatically.</div>
				<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="<?php echo esc_attr( $slot ); ?>:tip">Create a free group</a>
			</div>
		</div>
	</section>
	<?php else : ?>
	<section class="mmh-section" id="how-it-works">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>How it works</h2>
			<ol class="mmh-steps">
				<li data-reveal><span class="mmh-steps__num">1</span><h3>Create your group</h3><p>Add a group name, the gift budget and the exchange date. Takes a minute.</p></li>
				<li data-reveal style="--d:.12s"><span class="mmh-steps__num">2</span><h3>Share the link</h3><p>Send it on Messenger, Viber or email. Members join, confirm their email and add a wishlist.</p></li>
				<li data-reveal style="--d:.24s"><span class="mmh-steps__num">3</span><h3>Shuffle</h3><p>One tap, and everyone privately sees their match, wishlist and gift ideas.</p></li>
			</ol>
			<div class="mmh-features">
				<?php
				$feat = array(
					array( 'fas fa-shuffle', 'Fair, private shuffle', 'Nobody gets themselves, and only you can see your match.' ),
					array( 'fas fa-list-check', 'Wishlists with links', 'Add what you want, with links to Lazada, Shopee and more.' ),
					array( 'fas fa-wand-magic-sparkles', 'Gift ideas within budget', 'Suggestions picked from your match&rsquo;s wishlist and the budget.' ),
				);
				foreach ( $feat as $i => $f ) : ?>
				<div class="mmh-feature" data-reveal style="--d:<?php echo esc_attr( $i * 0.1 ); ?>s">
					<span class="mmh-feature__icon"><i class="<?php echo esc_attr( $f[0] ); ?>"></i></span>
					<h3><?php echo $f[1]; ?></h3>
					<p><?php echo $f[2]; ?></p>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="mmh-center mml-gap" data-reveal><a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="<?php echo esc_attr( $slot ); ?>:steps">Create a free group</a></div>
		</div>
	</section>
	<?php endif; ?>

	<section class="mmh-section mmh-section--soft" id="faq">
		<div class="mmh-wrap mmh-wrap--narrow">
			<h2 class="mmh-h2" data-reveal>Questions</h2>
			<div class="mmh-faq" data-reveal style="--d:.1s">
				<?php foreach ( $p['faq'] as $i => $q ) : ?>
				<details<?php echo $i === 0 ? ' open' : ''; ?>>
					<summary><?php echo $q[0]; ?></summary>
					<p><?php echo $q[1]; ?></p>
				</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="mmh-section">
		<div class="mmh-wrap">
			<h2 class="mmh-h2" data-reveal>More free tools and guides</h2>
			<div class="mml-related">
				<?php foreach ( $p['related'] as $i => $r ) : if ( empty( $all[ $r ] ) ) continue; ?>
				<a class="mml-related__card" href="<?php echo esc_url( Landing::url( $r ) ); ?>" data-reveal style="--d:<?php echo esc_attr( $i * 0.08 ); ?>s">
					<span class="mmh-eyebrow"><?php echo $all[ $r ]['eyebrow']; ?></span>
					<strong><?php echo wp_strip_all_tags( $all[ $r ]['h1'] ); ?></strong>
					<span class="mml-related__go">Open <i class="fas fa-arrow-right"></i></span>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="mmh-final">
		<div class="mmh-wrap mmh-center" data-reveal>
			<h2>Ready for this year&rsquo;s exchange?</h2>
			<p>Set it up in 2 minutes. Free forever.</p>
			<a href="#create-group" class="mmh-btn mmh-btn--primary" data-mm-track="create_group_click" data-mm-slot="<?php echo esc_attr( $slot ); ?>:final">Create a free group</a>
		</div>
	</section>

	<div class="mmh-sticky" data-mm-sticky aria-hidden="true">
		<a href="#create-group" class="mmh-btn mmh-btn--primary mmh-btn--block" data-mm-track="create_group_click" data-mm-slot="<?php echo esc_attr( $slot ); ?>:sticky" tabindex="-1">Create a free group</a>
	</div>
</div>
<?php
get_footer();
