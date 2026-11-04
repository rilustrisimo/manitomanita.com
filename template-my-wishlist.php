<?php
/**
 * Template Name: My Wishlist
 *
 * /my-wishlist/            landing: create a list (any occasion) or log in; "My lists" when signed in (indexed)
 * /wishlist/<slug>/        a shared list / registry: the owner sees the editor and share box; everyone else
 *                          sees the list with "I'll get this" (reservations hidden from the owner unless they
 *                          chose to see them, the default for weddings, baptisms and baby showers).
 * Logic: classes/class-my-wishlist.php, occasions in classes/class-registries.php.
 * Styles: mm-home.css (hero) + mm-wishlist.css. JS: Theme.initMyWishlist.
 *
 * @author    eyorsogood.com, Rouie Ilustrisimo
 * @version   2.0.0
 */

defined( 'ABSPATH' ) || die();

// Prefixed names: this file runs at global scope and WordPress sets a global $id while building the header.
$wlSlug = get_query_var( 'mm_wl' );
$wlId = $wlSlug ? MyWishlist::bySlug( $wlSlug ) : 0;
$wlOcc = $wlId ? Registries::occasionOf( $wlId ) : Registries::valid( sanitize_key( (string) ( $_GET['occasion'] ?? '' ) ) );
$wlO = Registries::get( $wlOcc );

// Personal and changing (owner editor, live reservations): never cached by LiteSpeed or the CDN.
nocache_headers();
do_action( 'litespeed_control_set_nocache', 'my wishlist' );

if ( $wlSlug && ! $wlId ) status_header( 404 );

get_header();

$hero = function ( $eyebrow, $title, $lead, $extra = '' ) { ?>
	<section class="mmh-hero mml-hero mm-wl-hero" data-mm-hero>
		<span class="mmh-hero__glow mmh-hero__glow--3" aria-hidden="true"></span>
		<div class="mmh-wrap mmh-wrap--narrow">
			<p class="mmh-eyebrow"><?php echo $eyebrow; ?></p>
			<h1 class="mmh-hero__title"><?php echo $title; ?></h1>
			<?php if ( $lead ) : ?><p class="mmh-hero__lead"><?php echo $lead; ?></p><?php endif; ?>
			<?php echo $extra; ?>
		</div>
	</section>
<?php };

$occSelect = function ( $name, $cur ) {
	$h = '<select name="' . esc_attr( $name ) . '" data-mm-wl-occ>';
	foreach ( Registries::occasions() as $k => $o ) {
		$h .= '<option value="' . esc_attr( $k ) . '" data-seeres="' . (int) $o['seeres'] . '" data-hint="' . esc_attr( Registries::forHint( $k ) ) . '"' . selected( $cur, $k, false ) . '>' . $o['label'] . '</option>';
	}

	return $h . '</select>';
};

// "More wedding gift ideas by budget" link to the occasion's registry guide, when it has one.
$guideLink = function ( $occ ) {
	$url = Registries::guideUrl( $occ );
	if ( $url === '' ) return '';

	return '<a class="mm-wl-guide" href="' . esc_url( $url ) . '">More ' . esc_html( strtolower( wp_strip_all_tags( html_entity_decode( Registries::get( $occ )['label'] ) ) ) ) . ' gift ideas by budget <i class="fa-solid fa-arrow-right"></i></a>';
};

echo '<div class="mm-home mm-landing mm-wl" style="--wl-occ:' . esc_attr( $wlO['accent'] ) . ';">';

/* ---------------------------------------------------------------- Not found */
if ( $wlSlug && ! $wlId ) {
	$hero( 'My Wishlist', 'This wishlist isn&rsquo;t available', 'The link may be mistyped, or the owner removed it.' );
	echo '<section class="mmh-section"><div class="mmh-wrap mmh-wrap--narrow mmh-center"><a class="mmh-btn mmh-btn--primary" href="' . esc_url( home_url( '/my-wishlist/' ) ) . '">Make your own wishlist</a></div></section>';
	echo '</div>';
	get_footer();
	return;
}

/* ---------------------------------------------------------------- A shared list */
if ( $wlId ) {
	$name = MyWishlist::name( $wlId );
	$items = MyWishlist::items( $wlId );
	$note = (string) get_post_meta( $wlId, '_mm_wl_note', true );
	$owner = MyWishlist::isOwner( $wlId );
	$preview = $owner && isset( $_GET['preview'] );
	$link = MyWishlist::url( $wlId );
	$title = Registries::title( $wlId );
	$isAny = $wlOcc === 'any';
	$eyebrow = '<i class="' . esc_attr( $wlO['icon'] ) . '" aria-hidden="true"></i> ' . ( $isAny ? 'Wishlist' : $wlO['label'] );
	$countdown = Registries::countdown( $wlId );
	$phase = Registries::phase( $wlId );
	$cash = (string) get_post_meta( $wlId, '_mm_wl_cash', true );
	$qr = (string) get_post_meta( $wlId, '_mm_wl_qr', true );
	$pill = $countdown !== '' ? '<p class="mm-wl-when"><i class="fa-regular fa-calendar"></i> ' . $countdown . '</p>' : '';

	if ( $owner && ! $preview ) {
		/* ----- Owner: lists, share box, editor ----- */
		$seeRes = Registries::seeRes( $wlId );
		$hero( $eyebrow, 'Hi ' . esc_html( $name ) . '! <span>' . ( $isAny ? 'Your wishlist' : 'Your ' . $wlO['noun'] ) . '</span>',
			'Add what you&rsquo;d love to receive, then share your link. Guests reserve a gift so nobody buys the same thing. ' . ( $seeRes ? 'You&rsquo;ll see who reserved what, so you know who to thank.' : 'You won&rsquo;t see what&rsquo;s reserved, so it stays a surprise.' ), $pill );
		$ideas = Registries::ideas( $wlOcc );
		$rows = $items ?: array( array( 'wishlist_description' => '', 'reference_links' => array() ) );
		$nonce = wp_create_nonce( 'mm_wl_' . $wlId );
		$config = array(
			'maxItems' => MyWishlist::MAX_ITEMS,
			'maxLinks' => 5,
			'budget'   => 0,
			'stores'   => array(
				'lazada' => array( 'prefix' => Affiliate::linkPrefix( 'lazada', 'my-wishlist-find' ), 'search' => Affiliate::searchUrl( 'lazada', '%s' ) ),
				'shopee' => array( 'prefix' => Affiliate::linkPrefix( 'shopee', 'my-wishlist-find' ), 'search' => Affiliate::searchUrl( 'shopee', '%s' ) ),
			),
		);
		$mine = MyWishlist::mine();
		$res = MyWishlist::reservations( $wlId );
		?>
		<section class="mmh-section mm-wl-main">
			<div class="mmh-wrap mmh-wrap--narrow">
				<div class="mm-wl-lists" data-mm-wl-lists>
					<p class="mm-wl-lists__label">Your lists</p>
					<div class="mm-wl-lists__row">
						<?php foreach ( $mine as $m ) : $mo = Registries::get( Registries::occasionOf( $m ) ); ?>
							<a class="mm-wl-chip<?php echo $m === $wlId ? ' is-on' : ''; ?>" href="<?php echo esc_url( MyWishlist::url( $m ) ); ?>"<?php echo $m === $wlId ? ' aria-current="page"' : ''; ?>><i class="<?php echo esc_attr( $mo['icon'] ); ?>"></i> <?php echo Registries::title( $m ); ?></a>
						<?php endforeach; ?>
						<button type="button" class="mm-wl-chip mm-wl-chip--add" data-mm-wl-another-open><i class="fa-solid fa-plus"></i> Create another list</button>
					</div>
					<form class="mm-wl-another" data-mm-wl-another hidden novalidate>
						<input type="hidden" name="from" value="<?php echo (int) $wlId; ?>">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
						<label>Occasion<?php echo $occSelect( 'occasion', 'wedding' ); ?></label>
						<label>Who it&rsquo;s for <small>(optional)</small><input type="text" name="for" maxlength="60" placeholder="<?php echo esc_attr( Registries::forHint( 'wedding' ) ); ?>" data-mm-wl-for></label>
						<label>Date <small>(optional)</small><input type="date" name="date"></label>
						<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
						<div class="mm-wl-another__acts"><button type="button" class="mm-wl-link" data-mm-wl-another-cancel>Cancel</button><button type="submit" class="mm-wl-btn mm-wl-btn--dark">Create list</button></div>
						<p class="mm-wl-another__note">Uses the same email and password as this list.</p>
					</form>
				</div>

				<div class="mm-wl-share" data-mm-wl-share data-url="<?php echo esc_url( $link ); ?>" data-name="<?php echo esc_attr( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ); ?>">
					<p class="mm-wl-share__label"><i class="fa-solid fa-link"></i> Your share link</p>
					<div class="mm-wl-share__row">
						<input type="text" readonly value="<?php echo esc_url( $link ); ?>" aria-label="Your wishlist link" data-mm-wl-link>
						<button type="button" class="mm-wl-btn mm-wl-btn--dark" data-mm-wl-copy><i class="fa-regular fa-copy"></i> Copy</button>
					</div>
					<div class="mm-wl-share__apps">
						<button type="button" class="mm-wl-chip" data-mm-wl-native hidden><i class="fa-solid fa-share-nodes"></i> Share</button>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="https://www.facebook.com/dialog/send?link=<?php echo rawurlencode( $link ); ?>&amp;app_id=62030021851&amp;redirect_uri=<?php echo rawurlencode( $link ); ?>"><i class="fa-brands fa-facebook-messenger"></i> Messenger</a>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="viber://forward?text=<?php echo rawurlencode( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) . ': ' . $link ); ?>"><i class="fa-brands fa-viber"></i> Viber</a>
						<a class="mm-wl-chip" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) . ': ' . $link ); ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
						<a class="mm-wl-chip" href="<?php echo esc_url( add_query_arg( 'preview', '1', $link ) ); ?>"><i class="fa-regular fa-eye"></i> See it as guests do</a>
						<button type="button" class="mm-wl-chip" data-mm-wl-qr aria-expanded="false"><i class="fa-solid fa-qrcode"></i> QR code &amp; invitation cards</button>
					</div>
					<div class="mm-wl-qrbox" data-mm-wl-qrbox hidden>
						<div class="mm-wl-qrbox__code" data-mm-wl-qrcode data-url="<?php echo esc_url( $link ); ?>"></div>
						<div class="mm-wl-qrbox__txt">
							<strong>Put it on your invitation</strong>
							<span>Guests scan it with their phone camera and your list opens. No app needed.</span>
							<div class="mm-wl-qrbox__acts">
								<button type="button" class="mm-wl-btn mm-wl-btn--dark" data-mm-wl-qrdl data-name="<?php echo esc_attr( sanitize_title( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ) ) ); ?>"><i class="fa-solid fa-download"></i> Download QR</button>
								<a class="mm-wl-btn" href="<?php echo esc_url( add_query_arg( 'card', '1', $link ) ); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Print invitation cards</a>
							</div>
						</div>
					</div>
				</div>

				<label class="mm-wl-toggle mm-wl-alerts"><input type="checkbox" data-mm-wl-alerts data-id="<?php echo (int) $wlId; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"<?php checked( RegistryExtras::alertsOn( $wlId ) ); ?>> <span><strong>Email me when gifts are reserved</strong><small>One summary a day at most, only when something new is reserved.<?php echo $seeRes ? '' : ' It won&rsquo;t say which gifts, so it stays a surprise.'; ?></small></span></label>

				<?php if ( $seeRes ) :
					$got = array();
					foreach ( $items as $w ) {
						$list = MyWishlist::resList( $res, substr( md5( strtolower( trim( (string) $w['wishlist_description'] ) ) ), 0, 12 ) );
						if ( $list ) $got[] = array( $w, $list );
					} ?>
				<?php $thx = RegistryExtras::thanked( $wlId ); $givers = 0; $thxN = 0; foreach ( $got as $g ) { foreach ( $g[1] as $r ) { $givers++; if ( ! empty( $thx[ $r['h'] ] ) ) $thxN++; } } $after = in_array( $phase, array( 'today', 'thanks', 'closed' ), true ); ?>
				<div class="mm-wl-got" data-mm-wl-got data-id="<?php echo (int) $wlId; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-for="<?php echo esc_attr( html_entity_decode( Registries::forName( $wlId ), ENT_QUOTES, 'UTF-8' ) ); ?>">
					<p class="mm-wl-got__label"><i class="fa-solid fa-clipboard-check"></i> <?php echo $after ? 'Thank-you list' : 'Who&rsquo;s getting what'; ?><?php if ( $givers ) : ?> <em data-mm-wl-thxcount><?php echo (int) $thxN; ?> of <?php echo (int) $givers; ?> thanked</em><?php endif; ?></p>
					<?php if ( $got ) : ?>
					<ul>
						<?php foreach ( $got as $g ) : $q = MyWishlist::qty( $g[0] ); $itemTxt = wp_trim_words( $g[0]['wishlist_description'], 10 ); ?>
						<li><strong><?php echo esc_html( $itemTxt ); ?></strong><?php echo $q > 1 ? ' <em>' . count( $g[1] ) . ' of ' . $q . '</em>' : ''; ?>
							<span class="mm-wl-thx">
								<?php foreach ( $g[1] as $r ) : $who = $r['by'] !== '' ? $r['by'] : 'Someone'; ?>
								<span class="mm-wl-thx__giver"><label><input type="checkbox" data-mm-wl-thx="<?php echo esc_attr( $r['h'] ); ?>"<?php checked( ! empty( $thx[ $r['h'] ] ) ); ?>> <?php echo esc_html( $who ); ?></label><button type="button" class="mm-wl-link" data-mm-wl-thxmsg data-who="<?php echo esc_attr( $r['by'] ); ?>" data-item="<?php echo esc_attr( $itemTxt ); ?>" title="Copy a thank-you message">Copy thank-you</button></span>
								<?php endforeach; ?>
							</span>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php else : ?>
					<p class="mm-wl-got__empty">Nothing reserved yet. Share your link and reservations show up here.</p>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="mm-match mm-edit mm-wl-edit">
				<form class="mm-edit__form" novalidate data-mm-wl-editor data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
					<input type="hidden" name="action" value="mm_wl_save">
					<input type="hidden" name="id" value="<?php echo (int) $wlId; ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-calendar-day"></i> THE<span>&nbsp;OCCASION</span></h3>
						<div class="mm-wl-grid">
							<label class="mm-field"><span class="mm-field__hint">Occasion</span><?php echo $occSelect( 'occasion', $wlOcc ); ?></label>
							<label class="mm-field"><span class="mm-field__hint">Date (optional)</span><input type="date" name="date" value="<?php echo esc_attr( (string) get_post_meta( $wlId, '_mm_wl_date', true ) ); ?>"></label>
						</div>
						<label class="mm-field"><span class="mm-field__hint">Who it&rsquo;s for (shown in the title; leave empty to use your name)</span>
							<input type="text" name="for" maxlength="60" value="<?php echo esc_attr( (string) get_post_meta( $wlId, '_mm_wl_for', true ) ); ?>" placeholder="e.g. Mark &amp; Joy, Baby Ana"></label>
						<label class="mm-field"><span class="mm-field__hint">Your name, as friends know you</span>
							<input type="text" name="name" maxlength="60" value="<?php echo esc_attr( $name ); ?>"></label>
						<label class="mm-field"><span class="mm-field__hint">A short note for guests (optional)</span>
							<input type="text" name="note" maxlength="<?php echo MyWishlist::MAX_NOTE; ?>" value="<?php echo esc_attr( $note ); ?>" placeholder="e.g. Dec 12, Manila Cathedral &middot; Thank you for celebrating with us!"></label>
						<label class="mm-wl-toggle"><input type="checkbox" name="seeres" value="1"<?php checked( $seeRes ); ?>> <span><strong>Show me who reserved what</strong><small>Turn off to keep gifts a surprise.</small></span></label>
					</section>

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-gifts"></i> WISH<span>LIST</span><em class="mm-sec__count" data-mm-count><?php echo count( $rows ); ?></em></h3>
						<ol class="mm-items" data-mm-items>
							<?php foreach ( $rows as $w ) {
								$links = array();
								foreach ( (array) ( $w['reference_links'] ?? array() ) as $l ) if ( ! empty( $l['link_url'] ) ) $links[] = $l['link_url'];
								echo Users::wishlistItemHtml( $w['wishlist_description'] ?? '', $links, array( 'qty' => MyWishlist::qty( $w ), 'top' => ! empty( $w['top'] ) ) );
							} ?>
						</ol>
						<template data-mm-item-tpl><?php echo Users::wishlistItemHtml( '', array(), array( 'qty' => 1, 'top' => 0 ) ); ?></template>
						<template data-mm-link-tpl><?php echo Users::wishlistLinkHtml( '' ); ?></template>
						<button type="button" class="mm-btn mm-btn--ghost mm-btn--block" data-mm-add-item><i class="fas fa-plus"></i> Add another item</button>
					</section>

					<section class="mm-sec mm-suggest">
						<h3 class="mm-sec__title"><i class="fas fa-lightbulb"></i> NEED<span>&nbsp;IDEAS?</span></h3>
						<p class="mm-gifts__sub">Tap <strong>Add</strong> to put an idea on your list<?php echo $isAny ? '' : ' &middot; ideas for a ' . esc_html( strtolower( wp_strip_all_tags( html_entity_decode( $wlO['label'] ) ) ) ); ?></p>
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
						<?php echo $isAny ? '' : $guideLink( $wlOcc ); ?>
					</section>

					<section class="mm-sec">
						<h3 class="mm-sec__title"><i class="fas fa-key"></i> PASS<span>WORD</span><em class="mm-sec__tag">Optional</em></h3>
						<label class="mm-field"><span class="mm-field__hint">Type a new password only if you want to change it (it changes for all your lists)</span>
							<input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="New password"></label>
					</section>

					<div class="mm-edit__footer">
						<div class="mm-edit__status" data-mm-status role="status" aria-live="polite"></div>
						<button type="submit" class="mm-btn mm-btn--primary" data-mm-save><i class="fas fa-check"></i> Save list</button>
					</div>
				</form>
				</div>

				<form class="mm-wl-cash" data-mm-wl-cash novalidate enctype="multipart/form-data">
					<input type="hidden" name="id" value="<?php echo (int) $wlId; ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">
					<h3 class="mm-wl-cash__title"><i class="fa-solid fa-qrcode"></i> Cash gift <em>Optional</em></h3>
					<p class="mm-wl-cash__lead">Common for weddings, debuts and baptisms. Upload your own GCash, Maya or bank QR and guests send money in their own app. It goes straight to you; Manito Manita never handles it.</p>
					<label>Note for guests<textarea name="cash" rows="2" maxlength="300" placeholder="e.g. Your presence is enough! If you wish to give, a cash gift for our new home is appreciated."><?php echo esc_textarea( $cash ); ?></textarea></label>
					<?php if ( $qr !== '' ) : ?>
					<div class="mm-wl-cash__qr"><img src="<?php echo esc_url( $qr ); ?>" alt="Your QR code" width="120" height="120"><label class="mm-wl-cash__rm"><input type="checkbox" name="remove_qr" value="1"> Remove this QR</label></div>
					<?php endif; ?>
					<label><?php echo $qr !== '' ? 'Replace QR image' : 'QR image'; ?> <small>(PNG, JPG or WebP, under 2 MB)</small><input type="file" name="qr" accept="image/png,image/jpeg,image/webp"></label>
					<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
					<button type="submit" class="mm-wl-btn mm-wl-btn--dark">Save cash gift</button>
				</form>

				<div class="mm-wl-account">
					<button type="button" class="mm-wl-link" data-mm-wl-logout>Log out</button>
					<button type="button" class="mm-wl-link mm-wl-link--danger" data-mm-wl-delete data-id="<?php echo (int) $wlId; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">Delete this list</button>
				</div>
			</div>
		</section>
		<?php
	} elseif ( $phase === 'closed' && ! $preview ) {
		/* ----- Guests, more than 30 days after the day ----- */
		$hero( $eyebrow, $title, 'This list has closed. Thank you to everyone who celebrated!' );
		echo '<section class="mmh-section mm-wl-main"><div class="mmh-wrap mmh-wrap--narrow"><div class="mm-wl-cta"><div><strong>Make your own list</strong><span>Free wishlists and gift registries for every occasion.</span></div><a class="mm-wl-btn mm-wl-btn--dark" href="' . esc_url( home_url( '/my-wishlist/' ) ) . '">Create mine</a></div></div></section>';
	} else {
		/* ----- Guests (and the owner's preview) ----- */
		$res = $preview ? array() : MyWishlist::reservations( $wlId );
		$count = count( $items );
		$hero( $eyebrow, $title, $note !== '' ? esc_html( $note ) : ( $count ? 'Pick something you think they&rsquo;ll love. Tap &ldquo;I&rsquo;ll get this&rdquo; so nobody buys the same gift.' : '' ), $pill );
		?>
		<section class="mmh-section mm-wl-main">
			<div class="mmh-wrap mmh-wrap--narrow">
				<?php if ( $preview ) : ?>
					<p class="mm-wl-preview"><i class="fa-regular fa-eye"></i> This is what guests see. <a href="<?php echo esc_url( $link ); ?>">Back to editing</a></p>
				<?php endif; ?>
				<?php if ( $phase === 'thanks' ) : ?>
					<p class="mm-wl-thanks"><i class="fa-solid fa-heart"></i> <strong>Thank you for celebrating!</strong> The list stays open for a few more weeks if you&rsquo;d still like to send a gift.</p>
				<?php endif; ?>
				<div class="mm-match">
					<?php if ( $items ) : ?>
					<?php $wlDays = Registries::days( $wlId ); ?>
					<ol class="mm-wish mm-wl-list" data-mm-wl-list data-id="<?php echo (int) $wlId; ?>" data-days="<?php echo $wlDays === null ? '' : (int) $wlDays; ?>" data-remind="<?php echo esc_attr( $wlDays !== null && $wlDays > RegistryExtras::REMIND_DAYS ? wp_date( 'M j', Registries::date( $wlId ) - RegistryExtras::REMIND_DAYS * DAY_IN_SECONDS ) : '' ); ?>">
						<?php foreach ( $items as $i => $w ) :
							$key = substr( md5( strtolower( trim( (string) $w['wishlist_description'] ) ) ), 0, 12 );
							$qty = MyWishlist::qty( $w );
							$list = MyWishlist::resList( $res, $key );
							$n = count( $list );
							$taken = $n >= $qty; ?>
						<li class="mm-wish__item<?php echo $taken ? ' is-taken' : ''; ?><?php echo ! empty( $w['top'] ) ? ' is-top' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>" data-qty="<?php echo (int) $qty; ?>" data-n="<?php echo (int) $n; ?>">
							<span class="mm-wish__num"><?php echo $i + 1; ?></span>
							<div class="mm-wish__body">
								<?php if ( ! empty( $w['top'] ) || $qty > 1 ) : ?>
								<div class="mm-wl-tags">
									<?php if ( ! empty( $w['top'] ) ) : ?><span class="mm-wl-tag mm-wl-tag--top"><i class="fa-solid fa-star"></i> Most wanted</span><?php endif; ?>
									<?php if ( $qty > 1 ) : ?><span class="mm-wl-tag">Needs <?php echo (int) $qty; ?></span><?php endif; ?>
								</div>
								<?php endif; ?>
								<div class="mm-wish__text"><?php echo Affiliate::linkify( $w['wishlist_description'], 'my-wishlist' ); ?></div>
								<?php if ( ! empty( $w['reference_links'] ) ) : ?>
								<div class="mm-wish__links">
									<?php foreach ( $w['reference_links'] as $k => $l ) echo Affiliate::referenceLink( $l['link_url'], $k, 'my-wishlist', '', 'mm-wish__link' ); ?>
								</div>
								<?php endif; ?>
								<?php if ( ! $preview ) : ?>
								<div class="mm-wl-res" data-mm-wl-res>
									<?php if ( $taken ) : ?>
										<span class="mm-wl-res__taken"><i class="fa-solid fa-circle-check"></i> <?php echo $qty > 1 ? 'All ' . (int) $qty . ' reserved' : 'Reserved' . ( $list[0]['by'] !== '' ? ' by ' . esc_html( $list[0]['by'] ) : '' ); ?></span>
									<?php else : ?>
										<button type="button" class="mm-wl-btn" data-mm-wl-reserve><i class="fa-solid fa-hand"></i> I&rsquo;ll get this</button>
										<?php if ( $qty > 1 && $n ) : ?><span class="mm-wl-res__count"><?php echo (int) $n; ?> of <?php echo (int) $qty; ?> reserved</span><?php endif; ?>
									<?php endif; ?>
								</div>
								<?php endif; ?>
							</div>
						</li>
						<?php endforeach; ?>
					</ol>
					<?php if ( $isAny ) echo Affiliate::shopButtonsHtml( '', 'my-wishlist-shop', Affiliate::keywordsFrom( $items[0]['wishlist_description'] ), 'Shop for ' . $name ); ?>
					<?php else : ?>
					<div class="mm-empty"><i class="fas fa-gift"></i><p><?php echo esc_html( $name ); ?> hasn&rsquo;t added anything yet. Check back soon.</p></div>
					<?php endif; ?>
				</div>

				<?php if ( $cash !== '' || $qr !== '' ) : ?>
				<div class="mm-wl-cashcard">
					<p class="mm-wl-cashcard__label"><i class="fa-solid fa-envelope-open-text"></i> Cash gift</p>
					<?php if ( $cash !== '' ) : ?><p><?php echo nl2br( esc_html( $cash ) ); ?></p><?php endif; ?>
					<?php if ( $qr !== '' ) : ?>
					<img src="<?php echo esc_url( $qr ); ?>" alt="QR code for a cash gift" loading="lazy" width="220" height="220">
					<small>Scan with GCash, Maya or your bank app. The money goes straight to <?php echo esc_html( Registries::forName( $wlId ) ); ?>; Manito Manita never handles it.</small>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( ! $isAny ) {
					echo Registries::sponsorHtml( $wlId );
					echo '<div class="mm-match mm-wl-ideas">' . Affiliate::giftIdeasHtml( 0, 'registry-' . $wlOcc, false, Registries::ideas( $wlOcc ), 'More ideas for a ' . esc_html( strtolower( wp_strip_all_tags( html_entity_decode( $wlO['label'] ) ) ) ), 0 ) . $guideLink( $wlOcc ) . '</div>';
				} ?>

				<div class="mm-wl-cta">
					<div><strong>Make your own <?php echo $isAny ? 'wishlist' : esc_html( strtolower( wp_strip_all_tags( html_entity_decode( $wlO['noun'] ) ) ) ); ?></strong><span>Free. Share one link for weddings, birthdays, baptisms or any day.</span></div>
					<a class="mm-wl-btn mm-wl-btn--dark" href="<?php echo esc_url( add_query_arg( $isAny ? array() : array( 'occasion' => $wlOcc ), home_url( '/my-wishlist/' ) ) ); ?>" data-mm-track="wishlist_cta" data-mm-slot="shared-wishlist">Create mine</a>
				</div>
				<p class="mm-wl-group">Doing a gift exchange? <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Draw names with Manito Manita</a>.</p>
				<div class="mm-wl-follow"><p>Follow us for gift ideas</p><?php echo Theme::socialLinks( 'wishlist', 'mm-social' ); ?></div>
			</div>
		</section>
		<?php
	}

	echo '</div>';
	get_footer();
	return;
}

/* ---------------------------------------------------------------- Landing: create or log in */
$mine = MyWishlist::mine();

$faq = array(
	array( 'Is it free?', 'Yes. Making and sharing wishlists and gift registries is free, with no app to install.' ),
	array( 'Can I make a wedding, debut or baptism registry?', 'Yes. Pick the occasion when you create your list: wedding, bridal shower, debut, baptism (binyag), first birthday, baby shower, housewarming, graduation, despedida and more. You can have several lists with one email.' ),
	array( 'Can I add links from Lazada or Shopee?', 'Yes. Paste a link from Lazada, Shopee or any online shop under each item, or type what you want and add a size, color or brand. You can also say how many you need, like 6 plates.' ),
	array( 'How do guests avoid buying the same gift?', 'Each item has an &ldquo;I&rsquo;ll get this&rdquo; button. Once someone taps it, others see it&rsquo;s reserved. For weddings and baptisms you can see who reserved what, so you know who to thank; for birthdays it stays a surprise. You can change this anytime.' ),
	array( 'Can guests give cash?', 'Yes. Upload your own GCash, Maya or bank QR and guests send money in their own app. It goes straight to you; we never handle money.' ),
	array( 'Who can see my list?', 'Only people you share your link with. Lists don&rsquo;t appear in search engines.' ),
);

if ( $wlOcc !== 'any' ) { // arrived from a registry guide or "Create mine" on someone's list
	$hero( $wlO['label'] . ' &middot; free', 'Create your <span>' . strtolower( $wlO['noun'] ) . '</span>', 'Add the gifts you&rsquo;d love, with shop links and how many you need, then share one link. Guests reserve so nothing is doubled.' );
} else {
	$hero( 'My Wishlist &middot; free', 'Wishlists &amp; gift registries <span>for every occasion</span>', 'Weddings, debuts, baptisms, birthdays or just because. Add what you&rsquo;d love to receive and share one link with family and friends.' );
}
?>
<section class="mmh-section mm-wl-main">
	<div class="mmh-wrap mmh-wrap--narrow">
		<?php if ( $mine ) : ?>
			<div class="mm-wl-mine">
				<p class="mm-wl-lists__label">You&rsquo;re logged in. Your lists:</p>
				<ul>
					<?php foreach ( $mine as $m ) : $mo = Registries::get( Registries::occasionOf( $m ) ); $cd = Registries::countdown( $m ); ?>
					<li><a href="<?php echo esc_url( MyWishlist::url( $m ) ); ?>"><span class="mm-wl-mine__icon" style="--wl-occ:<?php echo esc_attr( $mo['accent'] ); ?>;"><i class="<?php echo esc_attr( $mo['icon'] ); ?>"></i></span><span><strong><?php echo Registries::title( $m ); ?></strong><small><?php echo count( MyWishlist::items( $m ) ); ?> items<?php echo $cd !== '' ? ' &middot; ' . $cd : ''; ?></small></span><i class="fa-solid fa-chevron-right"></i></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="mm-wl-auth" id="create" data-mm-wl-auth>
			<div class="mm-wl-tabs" role="tablist">
				<button type="button" role="tab" class="is-on" data-mm-wl-tab="create" aria-selected="true">Create a list</button>
				<button type="button" role="tab" data-mm-wl-tab="login" aria-selected="false">I have one</button>
			</div>

			<form class="mm-wl-form" data-mm-wl-form="create" novalidate>
				<label>Occasion<?php echo $occSelect( 'occasion', $wlOcc ); ?></label>
				<label data-mm-wl-forbox<?php echo $wlOcc === 'any' ? ' hidden' : ''; ?>>Who it&rsquo;s for <small>(optional, shown in the title)</small><input type="text" name="for" maxlength="60" placeholder="<?php echo esc_attr( Registries::forHint( $wlOcc ) ); ?>" data-mm-wl-for></label>
				<label>Your name<input type="text" name="name" maxlength="60" autocomplete="name" placeholder="e.g. Ana Santos" required></label>
				<label>Date <small>(optional, shows a countdown)</small><input type="date" name="date"></label>
				<label>Your email<input type="email" name="email" maxlength="120" autocomplete="email" placeholder="you@example.com" required></label>
				<div class="mm-wl-otp" data-mm-wl-otp hidden>
					<button type="button" class="mm-wl-btn" data-mm-wl-send>Send verification code</button>
					<label data-mm-wl-codebox hidden>Enter the 6-digit code we emailed you<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="123456"></label>
					<input type="hidden" name="otp_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mm_otp' ) ); ?>">
				</div>
				<label>Create a password<input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="At least 6 characters" required><small>You&rsquo;ll use this to edit your lists.</small></label>
				<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
				<button type="submit" class="mm-wl-btn mm-wl-btn--primary mm-wl-btn--block">Create my list</button>
				<p class="mm-wl-form__legal">By continuing you agree to the <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms of Use</a> and <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>.</p>
			</form>

			<form class="mm-wl-form" data-mm-wl-form="login" novalidate hidden>
				<label>Your email<input type="email" name="email" maxlength="120" autocomplete="email" placeholder="you@example.com" required></label>
				<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
				<p class="mm-wl-form__msg" data-mm-wl-msg role="status" aria-live="polite"></p>
				<button type="submit" class="mm-wl-btn mm-wl-btn--primary mm-wl-btn--block">Open my lists</button>
				<button type="button" class="mm-wl-link" data-mm-wl-magic>Forgot your password? Email me a login link</button>
			</form>
		</div>

		<ol class="mm-wl-steps">
			<li><span>1</span><div><strong>Pick the occasion</strong>Wedding, debut, baptism, birthday or any day. Add what you&rsquo;d love, with shop links and how many you need.</div></li>
			<li><span>2</span><div><strong>Share one link</strong>Send it on Messenger or Viber, or add it to your invitation.</div></li>
			<li><span>3</span><div><strong>No double gifts</strong>Guests tap &ldquo;I&rsquo;ll get this&rdquo;. See who reserved what, or keep it a surprise.</div></li>
		</ol>

		<?php
		$guides = array();
		foreach ( array_keys( Landing::registrySlugs() ) as $gk ) {
			$gu = Registries::guideUrl( $gk );
			if ( $gu !== '' ) $guides[] = '<a class="mm-wl-chip" href="' . esc_url( $gu ) . '"><i class="' . esc_attr( Registries::get( $gk )['icon'] ) . '"></i> ' . Registries::get( $gk )['label'] . '</a>';
		}
		if ( $guides ) : ?>
		<div class="mm-wl-guides">
			<p class="mm-wl-lists__label">Gift ideas and registry guides</p>
			<div class="mm-wl-lists__row"><?php echo implode( '', $guides ); ?></div>
		</div>
		<?php endif; ?>

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
