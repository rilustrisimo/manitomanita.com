<?php
/**
 * Shown once after a group is created (?pop=try-pro): the group is ready, plus an optional PRO intro.
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
?>

<div class="pop-container mm-upsell" id="pro-try" role="dialog" aria-modal="true" aria-labelledby="mm-try-title">
    <div class="pop-container__inner mm-upsell__inner">
        <div class="pop-container__close"><a href="#" aria-label="Close"><i class="fa-solid fa-xmark"></i></a></div>

        <div class="mm-upsell__top">
            <span class="mm-upsell__badge mm-upsell__badge--ok"><i class="fa-solid fa-circle-check"></i> Group created</span>
            <h2 id="mm-try-title">Your group is ready!</h2>
            <p class="mm-upsell__lead">Copy the group link from the <b>Members Area</b> and share it on Messenger, Viber or email. Everything you need is free.</p>
        </div>

        <div class="mm-upsell__extra">
            <p class="mm-upsell__extra-title">Want extra organizer tools? <span>Optional</span></p>
            <ul class="mm-upsell__list mm-upsell__list--compact">
                <li><i class="fa-solid fa-user-slash"></i><span>Keep couples or family from getting each other</span></li>
                <li><i class="fa-solid fa-shuffle"></i><span>Unshuffle so late joiners can get in</span></li>
                <li><i class="fa-solid fa-user-xmark"></i><span>Remove or fix members</span></li>
                <li><i class="fa-solid fa-handshake"></i><span>See matches and export the group</span></li>
                <li><i class="fa-solid fa-ban"></i><span>No ads for everyone</span></li>
            </ul>
        </div>

        <div class="mm-upsell__actions">
            <a href="#" id="try-pro-btn" class="mm-upsell__btn mm-upsell__btn--gold">See PRO, &#8369;199 one-time</a>
            <a href="#" class="mm-upsell__btn mm-upsell__btn--ghost" data-mm-close>Maybe later</a>
        </div>
    </div>
</div>
