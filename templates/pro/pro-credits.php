<?php
/**
 * Post attributes rendering template part.
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

?>

<div class="pop-container" id="pro-credits">
    <div class="pop-container__inner">
        <div class="pop-container__close"><a href="#"><i class="fa-solid fa-xmark"></i></a></div>
        <div class="pop-container__header">Add More Unshuffle <span>Credits</span></div>
        <div class="pop-container__content">
            <div class="paypal-pay">
                <?php get_template_part( 'paypal/index', 'paypal-credits' ); ?>
            </div>
        </div>
    </div>
</div>