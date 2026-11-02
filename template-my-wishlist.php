<?php
/**
 * Template Name: My Wishlist
 *
 * /my-wishlist/            landing: create a wishlist or log in (indexed, Yoast handles the meta)
 * /wishlist/<slug>/        a shared wishlist: the owner sees the editor and share box; everyone else
 *                          sees the list with "I'll get this" (reservations are hidden from the owner).
 * Logic: classes/class-my-wishlist.php. Styles: mm-home.css (hero) + mm-wishlist.css. JS: Theme.initMyWishlist.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   1.0.0
 */

defined( 'ABSPATH' ) || die();

// Prefixed names: this file runs at global scope and WordPress sets a global $id while building the header.
$wlSlug = get_query_var( 'mm_wl' );
$wlId = $wlSlug ? MyWishlist::bySlug( $wlSlug ) : 0;

// Personal and changing (owner editor, live reservations): never cached by LiteSpeed or the CDN.
nocache_headers();
do_action( 'litespeed_control_set_nocache', 'my wishlist' );

if ( $wlSlug && ! $wlId ) status_header( 404 );

get_header();

$hero = function ( $eyebrow, $title, $lead ) { ?>
	<section class="mmh-hero mml-hero mm-wl-hero" data-mm-hero>
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mmh-wrap--narrow">
			<p class="mmh-eyebrow"><?php echo $eyebrow; ?></p>
			<h1 class="mmh-hero__title"><?php echo $title; ?></h1>
			<?php if ( $lead ) : ?><p class="mmh-hero__lead"><?php echo $lead; ?></p><?php endif; ?>
		</div>
	</section>
<?php };

echo '<div class="mm-home mm-landing mm-wl">';

/* ---------------------------------------------------------------- Not found */
if ( $wlSlug && ! $wlId ) {
	$hero( 'My Wishlist', 'This wishlist isn&rsquo;t available', 'The link may be mistyped, or the owner removed it.' );
	echo '<section class="mmh-section"><div class="mmh-wrap mmh-wrap--narrow mmh-center"><a class="mmh-btn mmh-btn--primary" href="' . esc_url( home_url( '/my-wishlist/' ) ) . '">Make your own wishlist</a></div></section>';
	echo '</div>';
	get_footer();
	return;
}

/* ---------------------------------------------------------------- A shared wishlist */
if ( $wlId ) {
	$name = MyWishlist::name( $wlId );
	$items = MyWishlist::items( $wlId );
	$note = (string) get_post_meta( $wlId, '_mm_wl_note', true );
	$owner = MyWishlist::isOwner( $wlId );
	$preview = $owner && isset( $_GET['preview'] );
	$link = MyWishlist::url( $wlId );

	if ( $owner && ! $preview ) {
		/* ----- Owner: share box + editor ----- */
		$hero( 'My Wishlist', 'Hi ' . esc_html( $name ) . '! <span>Your wishlist</span>', 'Add what you&rsquo;d love to receive, then share your link. Friends can reserve a gift so nobody buys the same thing. You won&rsquo;t see what&rsquo;s reserved.' );
		$ideas = Affiliate::giftIdeas();
		$rows = $items ?: array( array( 'wishlist_description' => '', 'reference_links' => array() ) );
		$config = array(
			'maxItems' => MyWishlist::MAX_ITEMS,
			'maxLinks' => 5,
			'budget'   => 0,
			'stores'   => array(
				'lazada' => array( 'prefix' => Affiliate::linkPrefix( 'lazada', 'my-wishlist-find' ), 'search' => Affiliate::searchUrl( 'lazada', '%s' ) ),
				'shopee' => array( 'prefix' => Affiliate::linkPrefix( 'shopee', 'my-wishlist-find' ), 'search' => Affiliate::searchUrl( 'shopee', '%s' ) ),
			),
		);
		?>
		<section class="mmh-section mm-wl-main">
			<div class="mmh-wrap mmh-wrap--narrow">
				<div class="mm-wl-share" data-mm-wl-share data-url="<?php echo esc_url( $link ); ?>" data-name="<?php echo esc_attr( $name ); ?>">
					<p class="mm-wl-share__label"><i class="fa-solid fa-link"></i> Your share link</p>
					<div class="mm-wl-share__row">
						<input type="text" readonly value="<?php echo esc_url( $link ); ?>" aria-label="Your wishlist link" data-mm-wl-link>
						<button type="button" class="mm-wl-btn mm-wl-btn--dark" data-mm-wl-copy><i class="fa-regular fa-copy"></i> Copy</button>
					</div>
					<div class="mm-wl-share__apps">
						<button type="button" class="mm-wl-chip" data-mm-wl-native hidden><i class="fa-solid fa-share-nodes"></i> Share</button>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="https://www.facebook.com/dialog/send?link=<?php echo rawurlencode( $link ); ?>&amp;app_id=62030021851&amp;redirect_uri=<?php echo rawurlencode( $link ); ?>"><i class="fa-brands fa-facebook-messenger"></i> Messenger</a>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="viber://forward?text=<?php echo rawurlencode( 'My wishlist: ' . $link ); ?>"><i class="fa-brands fa-viber"></i> Viber</a>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( 'My wishlist: ' . $link ); ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
						<a class="mm-wl-chip" href="<?php echo esc_url( add_query_arg( 'preview', '1', $link ) ); ?>"><i class="fa-regular fa-eye"></i> See it as friends do</a>
					</div>
				</div>

				<div class="mm-match mm-edit mm-wl-edit">
				<form class="mm-edit__form" novalidate data-mm-wl-editor data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
					<input type="hidden" name="action" value="mm_wl_save">
					<input type="hidden" name="id" value="<?php echo (int) $wlId; ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mm_wl_' . $wlId ) ); ?>">

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-user"></i> ABOUT<span>&nbsp;YOU</span></h3>
						<label class="mm-field"><span class="mm-field__hint">Your name, as friends know you</span>
							<input type="text" name="name" maxlength="60" value="<?php echo esc_attr( $name ); ?>"></label>
						<label class="mm-field"><span class="mm-field__hint">Occasion or a short note (optional)</span>
							<input type="text" name="note" maxlength="<?php echo MyWishlist::MAX_NOTE; ?>" value="<?php echo esc_attr( $note ); ?>" placeholder="e.g. Birthday on Dec 3 &middot; I love plants and coffee"></label>
					</section>

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-gifts"></i> WISH<span>LIST</span><em class="mm-sec__count" data-mm-count><?php echo count( $rows ); ?></em></h3>
						<ol class="mm-items" data-mm-items>
							<?php foreach ( $rows as $w ) {
								$links = array();
								foreach ( (array) ( $w['reference_links'] ?? array() ) as $l ) if ( ! empty( $l['link_url'] ) ) $links[] = $l['link_url'];
								echo Users::wishlistItemHtml( $w['wishlist_description'] ?? '', $links );
							} ?>
						</ol>
						<template data-mm-item-tpl><?php echo Users::wishlistItemHtml( '', array() ); ?></template>
						<template data-mm-link-tpl><?php echo Users::wishlistLinkHtml( '' ); ?></template>
						<button type="button" class="mm-btn mm-btn--ghost mm-btn--block" data-mm-add-item><i class="fas fa-plus"></i> Add another item</button>
					</section>

					<section class="mm-sec mm-suggest">
						<h3 class="mm-sec__title"><i class="fas fa-lightbulb"></i> NEED<span>&nbsp;IDEAS?</span></h3>
						<p class="mm-gifts__sub">Tap <strong>Add</strong> to put an idea on your wishlist</p>
						<div class="mm-suggest__grid">
							<?php foreach ( $ideas as $idea ) : ?>
							<div class="mm-suggest__item">
								<span class="mm-gift__icon"><i class="<?php echo esc_attr( ! empty( $idea['icon'] ) ? $idea['icon'] : 'fas fa-gift' ); ?>"></i></span>
								<div class="mm-suggest__text"><div class="mm-gift__label"><?php echo esc_html( $idea['label'] ); ?></div>
									<div class="mm-suggest__stores">
										<?php echo Affiliate::anchor( Affiliate::searchUrl( 'lazada', $idea['keyword'] ), 'Lazada', 'my-wishlist-ideas', '', 'mm-suggest__store' ); ?>
										<?php echo Affiliate::anchor( Affiliate::searchUrl( 'shopee', $idea['keyword'] ), 'Shopee', 'my-wishlist-ideas', '', 'mm-suggest__store' ); ?>
									</div>
								</div>
								<button type="button" class="mm-btn mm-btn--add" data-mm-suggest="<?php echo esc_attr( $idea['label'] ); ?>"><i class="fas fa-plus"></i> Add</button>
							</div>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-key"></i> PASS<span>WORD</span><em class="mm-sec__tag">Optional</em></h3>
						<label class="mm-field"><span class="mm-field__hint">Type a new password only if you want to change it</span>
							<input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="New password"></label>
					</section>

					<div class="mm-edit__footer">
						<div class="mm-edit__status" data-mm-status role="status" aria-live="polite"></div>
						<button type="submit" class="mm-btn mm-btn--primary" data-mm-save><i class="fas fa-check"></i> Save wishlist</button>
					</div>
				</form>
				</div>

				<div class="mm-wl-account">
					<button type="button" class="mm-wl-link" data-mm-wl-logout>Log out</button>
					<button type="button" class="mm-wl-link mm-wl-link--danger" data-mm-wl-delete data-id="<?php echo (int) $wlId; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mm_wl_' . $wlId ) ); ?>">Delete my wishlist</button>
				</div>
			</div>
		</section>
		<?php
	} else {
		/* ----- Friends (and the owner's preview) ----- */
		$res = $preview ? array() : MyWishlist::reservations( $wlId );
		$count = count( $items );
		$hero( 'Wishlist', esc_html( $name ) . '&rsquo;s <span>wishlist</span>', $note !== '' ? esc_html( $note ) : ( $count ? 'Pick something you think they&rsquo;ll love. Tap &ldquo;I&rsquo;ll get this&rdquo; so nobody buys the same gift.' : '' ) );
		?>
		<section class="mmh-section mm-wl-main">
			<div class="mmh-wrap mmh-wrap--narrow">
				<?php if ( $preview ) : ?>
					<p class="mm-wl-preview"><i class="fa-regular fa-eye"></i> This is what friends see. Reservations stay hidden from you. <a href="<?php echo esc_url( $link ); ?>">Back to editing</a></p>
				<?php endif; ?>
				<div class="mm-match">
					<?php if ( $items ) : ?>
					<ol class="mm-wish mm-wl-list" data-mm-wl-list data-id="<?php echo (int) $wlId; ?>">
						<?php foreach ( $items as $i => $w ) :
							$key = substr( md5( strtolower( trim( (string) $w['wishlist_description'] ) ) ), 0, 12 );
							$taken = isset( $res[ $key ] ); ?>
						<li class="mm-wish__item<?php echo $taken ? ' is-taken' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>">
							<span class="mm-wish__num"><?php echo $i + 1; ?></span>
							<div class="mm-wish__body">
								<div class="mm-wish__text"><?php echo Affiliate::linkify( $w['wishlist_description'], 'my-wishlist' ); ?></div>
								<?php if ( ! empty( $w['reference_links'] ) ) : ?>
								<div class="mm-wish__links">
									<?php foreach ( $w['reference_links'] as $k => $l ) echo Affiliate::referenceLink( $l['link_url'], $k, 'my-wishlist', '', 'mm-wish__link' ); ?>
								</div>
								<?php endif; ?>
								<?php if ( ! $preview ) : ?>
								<div class="mm-wl-res" data-mm-wl-res>
									<?php if ( $taken ) : ?>
										<span class="mm-wl-res__taken"><i class="fa-solid fa-circle-check"></i> Reserved<?php echo $res[ $key ]['by'] !== '' ? ' by ' . esc_html( $res[ $key ]['by'] ) : ''; ?></span>
									<?php else : ?>
										<button type="button" class="mm-wl-btn" data-mm-wl-reserve><i class="fa-solid fa-hand"></i> I&rsquo;ll get this</button>
									<?php endif; ?>
								</div>
								<?php endif; ?>
							</div>
						</li>
						<?php endforeach; ?>
					</ol>
					<?php echo Affiliate::shopButtonsHtml( '', 'my-wishlist-shop', Affiliate::keywordsFrom( $items[0]['wishlist_description'] ), 'Shop for ' . $name ); ?>
					<?php else : ?>
					<div class="mm-empty"><i class="fas fa-gift"></i><p><?php echo esc_html( $name ); ?> hasn&rsquo;t added anything yet. Check back soon.</p></div>
					<?php endif; ?>
				</div>

				<div class="mm-wl-cta">
					<div><strong>Make your own wishlist</strong><span>Free. Share one link for birthdays, Christmas or any day.</span></div>
					<a class="mm-wl-btn mm-wl-btn--dark" href="<?php echo esc_url( home_url( '/my-wishlist/' ) ); ?>" data-mm-track="wishlist_cta" data-mm-slot="shared-wishlist">Create mine</a>
				</div>
				<p class="mm-wl-group">Doing a gift exchange? <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Draw names with Manito Manita</a>.</p>
			</div>
		</section>
		<?php
	}

	echo '</div>';
	get_footer();
	return;
}

/* ---------------------------------------------------------------- Landing: create or log in */
$mine = 0;
foreach ( (array) ( $_SESSION['mm_wl'] ?? array() ) as $wid => $t ) if ( get_post_type( $wid ) === MyWishlist::CPT ) $mine = (int) $wid;

$faq = array(
	array( 'Is it free?', 'Yes. Making and sharing a wishlist is free, with no app to install.' ),
	array( 'Can I add links from Lazada or Shopee?', 'Yes. Paste a link from Lazada, Shopee or any online shop under each item, or type what you want and add a size, color or brand.' ),
	array( 'How do friends avoid buying the same gift?', 'Each item has an &ldquo;I&rsquo;ll get this&rdquo; button. Once someone taps it, others see it&rsquo;s reserved. You won&rsquo;t see what&rsquo;s reserved, so your gifts stay a surprise.' ),
	array( 'Who can see my wishlist?', 'Only people you share your link with. Wishlists don&rsquo;t appear in search engines.' ),
	array( 'Can I use it for a Secret Santa or Monito Monita?', 'Yes. Share your wishlist link in any group chat, or create a free Manito Manita group to draw names, where each member also gets a wishlist.' ),
);

$hero( 'My Wishlist &middot; free', 'One wishlist for <span>every occasion</span>', 'Birthdays, Christmas, debuts or just because. Add what you&rsquo;d love to receive and share one link with family and friends.' );
?>
<section class="mmh-section mm-wl-main">
	<div class="mmh-wrap mmh-wrap--narrow">
		<?php if ( $mine ) : ?>
			<div class="mm-wl-back"><span>You&rsquo;re logged in as <?php echo esc_html( MyWishlist::name( $mine ) ); ?>.</span> <a class="mm-wl-btn mm-wl-btn--dark" href="<?php echo esc_url( MyWishlist::url( $mine ) ); ?>">Open my wishlist</a></div>
		<?php endif; ?>

		<div class="mm-wl-auth" data-mm-wl-auth>
			<div class="mm-wl-tabs" role="tablist">
				<button type="button" role="tab" class="is-on" data-mm-wl-tab="create" aria-selected="true">Create a wishlist</button>
				<button type="button" role="tab" data-mm-wl-tab="login" aria-selected="false">I have one</button>
			</div>

			<form class="mm-wl-form" data-mm-wl-form="create" novalidate>
				<label>Your name<input type="text" name="name" maxlength="60" autocomplete="name" placeholder="e.g. Ana Santos" required></label>
				<label>Your email<input type="email" name="email" maxlength="120" autocomplete="email" placeholder="you@example.com" required></label>
				<div class="mm-wl-otp" data-mm-wl-otp hidden>
					<button type="button" class="mm-wl-btn" data-mm-wl-send>Send verification code</button>
					<label data-mm-wl-codebox hidden>Enter the 6-digit code we emailed you<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456"></label>
					<input type="hidden" name="otp_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mm_otp' ) ); ?>">
				</div>
				<label>Create a password<input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="At least 6 characters" required><small>You&rsquo;ll use this to edit your wishlist.</small></label>
				<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
				<button type="submit" class="mm-wl-btn mm-wl-btn--primary mm-wl-btn--block">Create my wishlist</button>
				<p class="mm-wl-form__legal">By continuing you agree to the <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Use</a> and <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>.</p>
			</form>

			<form class="mm-wl-form" data-mm-wl-form="login" novalidate hidden>
				<label>Your email<input type="email" name="email" maxlength="120" autocomplete="email" placeholder="you@example.com" required></label>
				<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
				<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
				<button type="submit" class="mm-wl-btn mm-wl-btn--primary mm-wl-btn--block">Open my wishlist</button>
				<button type="button" class="mm-wl-link" data-mm-wl-magic>Forgot your password? Email me a login link</button>
			</form>
		</div>

		<ol class="mm-wl-steps">
			<li><span>1</span><div><strong>Add your wishes</strong>Type what you&rsquo;d love, with a size or color, and paste shop links.</div></li>
			<li><span>2</span><div><strong>Share one link</strong>Send it on Messenger, Viber or post it before your birthday.</div></li>
			<li><span>3</span><div><strong>No double gifts</strong>Friends tap &ldquo;I&rsquo;ll get this&rdquo;. You won&rsquo;t see it, so it stays a surprise.</div></li>
		</ol>

		<div class="mm-wl-faq">
			<h2 class="mmh-h2">Questions</h2>
			<div class="mmh-faq">
				<?php foreach ( $faq as $i => $q ) : ?>
				<details<?php echo $i === 0 ? ' open' : ''; ?>><summary><?php echo $q[0]; ?></summary><p><?php echo $q[1]; ?></p></details>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
</div>
<script type="application/ld+json"><?php
echo wp_json_encode( array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map( function ( $q ) {
		return array( '@type' => 'Question', 'name' => html_entity_decode( $q[0], ENT_QUOTES, 'UTF-8' ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => html_entity_decode( $q[1], ENT_QUOTES, 'UTF-8' ) ) );
	}, $faq ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
?></script>
<?php
get_footer();
