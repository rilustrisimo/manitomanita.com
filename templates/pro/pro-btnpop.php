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
        <div class="pop-container__header">Manito Manita <span>PRO</span></div>
        <div class="pop-container__content">
            <p class="subheading">Unlock Advanced Moderator Tools</p>
            <h1>₱ 199.00</h1>
            <p class="subheading">One-time payment<sup>*</sup></p>
            <a href="#" id="make-pro-btn">Upgrade to PRO</a>
            <div class="paypal-pay" style="display:none;">
                <?php get_template_part( 'paypal/index', 'paypal' ); ?>
            </div>
            <p class="heading">With Manito Manita PRO, Moderators Can:</p>
            <ul>
            <li><span><b>No</b> Ads</span></li>
            <li><span><b>Reshuffle</b> group pairings</span></li>
            <li><span><b>View</b> member names and screen names</span></li>
            <li><span><b>Access</b> and review member matches/pairs</span></li>
            <li><span><b>Remove</b> members from the group</span></li>
            <li><span><b>Edit</b> member information</span></li>
            <li><span><b>Export</b> group data</span></li>
            </ul>
        </div>
    </div>
</div>