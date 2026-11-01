<?php
/**
 * PRO upgrade sheet (#make-pro). Opened by Theme.openUpsell(reason), which sets the headline for the
 * moment (redo the draw, fix names, gift to the group...) and highlights the matching benefit.
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
?>

<div class="pop-container mm-upsell" id="make-pro" role="dialog" aria-modal="true" aria-labelledby="mm-upsell-title">
    <div class="pop-container__inner mm-upsell__inner">
        <div class="pop-container__close"><a href="#" aria-label="Close"><i class="fa-solid fa-xmark"></i></a></div>

        <div class="mm-upsell__top">
            <span class="mm-upsell__badge"><i class="fa-solid fa-star"></i> Manito Manita PRO</span>
            <h2 id="mm-upsell-title" data-mm-upsell-title>Extra tools for your group&rsquo;s organizer</h2>
            <p class="mm-upsell__lead" data-mm-upsell-lead>Fix mistakes, redo the draw and keep everything organized.</p>
        </div>

        <div class="mm-upsell__price">
            <strong>&#8369;199</strong>
            <span>one-time for this group<br>no subscription</span>
        </div>

        <ul class="mm-upsell__list">
            <li data-reason="exclude"><i class="fa-solid fa-user-slash"></i><span><b>Exclusions:</b> couples or family won&rsquo;t draw each other</span></li>
            <li data-reason="redo"><i class="fa-solid fa-shuffle"></i><span><b>Redo the draw</b> if someone leaves or joins late <em>(3 redos included)</em></span></li>
            <li data-reason="fix"><i class="fa-solid fa-user-xmark"></i><span><b>Remove</b> duplicate or wrong members</span></li>
            <li data-reason="fix"><i class="fa-solid fa-pen-to-square"></i><span><b>Fix</b> a misspelled name or email</span></li>
            <li data-reason="names"><i class="fa-solid fa-address-book"></i><span><b>See</b> every member&rsquo;s real name and email</span></li>
            <li data-reason="matches"><i class="fa-solid fa-handshake"></i><span><b>See all matches</b> when you need to</span></li>
            <li data-reason="export"><i class="fa-solid fa-file-arrow-down"></i><span><b>Export</b> everything to a spreadsheet</span></li>
            <li data-reason="gift"><i class="fa-solid fa-ban"></i><span><b>No ads</b> for everyone in the group</span></li>
        </ul>

        <div class="paypal-pay mm-upsell__pay">
            <?php get_template_part( 'paypal/index', 'paypal' ); ?>
        </div>

        <p class="mm-upsell__fine"><i class="fa-solid fa-lock"></i><span>Secure checkout by PayPal. Pay with your PayPal account or <b>any debit/credit card</b>, no PayPal account needed.</span></p>
        <p class="mm-upsell__fine"><i class="fa-solid fa-gift"></i><span>Not the organizer? Anyone in the group can upgrade it as a gift.</span></p>
        <?php if ( ! empty( $mm_stats['groups'] ) && $mm_stats['groups'] >= 100 ) : ?>
            <p class="mm-upsell__fine"><i class="fa-solid fa-heart"></i><span>Trusted by <?php echo esc_html( number_format_i18n( $mm_stats['groups'] ) ); ?>+ groups for their gift exchange.</span></p>
        <?php endif; ?>
    </div>
</div>
