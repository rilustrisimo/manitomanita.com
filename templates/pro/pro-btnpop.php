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

<div class="pop-container" id="make-pro">
    <div class="pop-container__inner">
        <div class="pop-container__close"><a href="#"><i class="fa-solid fa-xmark"></i></a></div>
        <div class="pop-container__header">Make Your Group a <span>PRO</span></div>
        <div class="pop-container__content">
            <a href="#" id="make-pro-btn">Make it PRO</a>
            <div class="paypal-pay" style="display:none;">
                <?php get_template_part( '../../paypal/index', 'paypal' ); ?>
            </div>
        </div>
    </div>
</div>