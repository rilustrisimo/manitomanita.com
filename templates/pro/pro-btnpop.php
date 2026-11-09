<?php
/**
 * PRO upgrade sheet (#make-pro). Opened by Theme.openUpsell(reason), which sets the headline for the
 * moment (unshuffle, fix names, gift to the group...) and highlights the matching benefit.
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

$mm_stats = Groups::siteStats();
// Per-person price for this group: office committees compare it with collecting from everyone.
$mm_g = new Groups();
$mm_n = count( (array) Groups::memberIds( (int) $mm_g->getGroupId() ) );
$mm_each = $mm_n >= 4 ? (int) ceil( 199 / $mm_n ) : 0;
?>

<div class="pop-container mm-upsell mm-upsell--buy" id="make-pro" role="dialog" aria-modal="true" aria-labelledby="mm-upsell-title">
    <div class="pop-container__inner mm-upsell__inner">
        <div class="pop-container__close"><a href="#" aria-label="Close"><i class="fa-solid fa-xmark"></i></a></div>

        <div class="mm-upsell__top">
            <span class="mm-upsell__badge"><i class="fa-solid fa-star"></i> Manito Manita PRO</span>
            <h2 id="mm-upsell-title" data-mm-upsell-title>Keep couples and family from getting each other</h2>
            <p class="mm-upsell__lead" data-mm-upsell-lead>Exclusions, unshuffle for late joiners, reminders and no ads, for the whole group.</p>
        </div>

        <!-- Shown when the sheet was opened for exclusions (Theme.openUpsell sets data-mm-reason) -->
        <div class="mm-upsell__example" aria-hidden="true">
            <span class="mm-upsell__example-label">Example</span>
            <span class="mm-upsell__pair">Ana <i class="fa-solid fa-xmark"></i> Ben</span>
            <span class="mm-upsell__pair">Carlo <i class="fa-solid fa-xmark"></i> Dana</span>
            <span class="mm-upsell__example-note">They&rsquo;ll never draw each other. Set it up before you shuffle.</span>
        </div>

        <!-- Buy first: price and checkout are visible without scrolling -->
        <div class="mm-upsell__buy">
            <div class="mm-upsell__price">
                <strong>&#8369;199</strong>
                <span><b>one-time</b> for the whole group<br>anyone in the group can pay &middot; no subscription</span>
            </div>
            <?php if ( $mm_each ) : ?>
            <p class="mm-upsell__each">That&rsquo;s about <b>&#8369;<?php echo (int) $mm_each; ?> per person</b> for your <?php echo (int) $mm_n; ?> members.</p>
            <?php endif; ?>
            <div class="paypal-pay mm-upsell__pay">
                <?php get_template_part( 'paypal/index', 'paypal' ); ?>
            </div>
            <p class="mm-upsell__secure"><i class="fa-solid fa-lock"></i> Secure checkout by PayPal &middot; <b>no PayPal account needed</b> for cards</p>
        </div>

        <h3 class="mm-upsell__h3">What you get</h3>
        <div class="mm-upsell__tiles">
            <div class="mm-upsell__tile" data-reason="exclude"><i class="fa-solid fa-user-slash"></i><b>Exclusions</b><span>Couples won&rsquo;t get each other</span></div>
            <div class="mm-upsell__tile" data-reason="themes"><i class="fa-solid fa-gift"></i><b>Weekly Drops</b><span>Themed mini-gifts + reveal</span></div>
            <div class="mm-upsell__tile" data-reason="qa"><i class="fa-solid fa-user-secret"></i><b>Anonymous Q&amp;A</b><span>Ask your match secretly</span></div>
            <div class="mm-upsell__tile" data-reason="gift"><i class="fa-solid fa-ban"></i><b>No ads</b><span>For everyone in the group</span></div>
        </div>

        <details class="mm-upsell__all">
            <summary><span>See all 10 PRO features</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <ul class="mm-upsell__list">
                <li data-reason="exclude"><i class="fa-solid fa-user-slash"></i><span><b>Exclusions:</b> couples or family won&rsquo;t get each other</span></li>
                <li data-reason="themes"><i class="fa-solid fa-gift"></i><span><b>Weekly Drops:</b> an optional themed mini-gift each week, plus a fun reveal</span></li>
                <li data-reason="qa"><i class="fa-solid fa-user-secret"></i><span><b>Anonymous Q&amp;A:</b> members ask their match questions without revealing themselves</span></li>
                <li data-reason="unshuffle"><i class="fa-solid fa-rotate-left"></i><span><b>Unshuffle</b> so late joiners can get in, then shuffle again <em>(3 included)</em></span></li>
                <li data-reason="remind"><i class="fa-solid fa-bell"></i><span><b>Reminders and auto-shuffle</b> on the date you pick</span></li>
                <li data-reason="status"><i class="fa-solid fa-list-check"></i><span><b>Gift status:</b> see who has bought or given</span></li>
                <li data-reason="fix"><i class="fa-solid fa-user-pen"></i><span><b>Remove or fix</b> members, names, emails and comments</span></li>
                <li data-reason="comod"><i class="fa-solid fa-user-plus"></i><span><b>Co-organizer</b> and <b>group colors</b> for your group page</span></li>
                <li data-reason="matches"><i class="fa-solid fa-handshake"></i><span><b>See matches</b> and <b>export</b> everything</span></li>
                <li data-reason="gift"><i class="fa-solid fa-ban"></i><span><b>No ads</b> for everyone in the group</span></li>
            </ul>
        </details>

        <p class="mm-upsell__fine"><i class="fa-solid fa-gift"></i><span>Not the organizer? Anyone in the group can upgrade it as a gift.</span></p>
        <?php if ( ! empty( $mm_stats['groups'] ) && $mm_stats['groups'] >= 100 ) : ?>
            <p class="mm-upsell__fine"><i class="fa-solid fa-heart"></i><span>Trusted by <?php echo esc_html( number_format_i18n( $mm_stats['groups'] ) ); ?>+ groups for their gift exchange.</span></p>
        <?php endif; ?>
    </div>
</div>
