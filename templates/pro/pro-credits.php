<?php
/**
 * Buy 3 more unshuffle (redo) credits (#pro-credits), shown from PRO Actions when credits reach 0.
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

<div class="pop-container mm-upsell" id="pro-credits" role="dialog" aria-modal="true" aria-labelledby="mm-credits-title">
    <div class="pop-container__inner mm-upsell__inner">
        <div class="pop-container__close"><a href="#" aria-label="Close"><i class="fa-solid fa-xmark"></i></a></div>

        <div class="mm-upsell__top">
            <span class="mm-upsell__badge"><i class="fa-solid fa-star"></i> PRO</span>
            <h2 id="mm-credits-title">Add 3 more redos</h2>
            <p class="mm-upsell__lead">You&rsquo;ve used all your redo credits. Add 3 more to redo the draw if someone else leaves or joins late.</p>
        </div>

        <div class="mm-upsell__price">
            <strong>&#8369;99</strong>
            <span>for 3 redos<br>one-time</span>
        </div>

        <div class="paypal-pay mm-upsell__pay">
            <?php get_template_part( 'paypal/index', 'paypal-credits' ); ?>
        </div>

        <p class="mm-upsell__fine"><i class="fa-solid fa-lock"></i><span>Secure checkout by PayPal. Pay with your PayPal account or <b>any debit/credit card</b>.</span></p>
    </div>
</div>
