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
            <p lass="subheading">One-time payment</p>
            <p class="heading">With Manito Manita PRO, Moderators Can:</p>
            <ul>
            <li><b>Reshuffle</b> group pairings</li>
            <li><b>View</b> member names and screen names</li>
            <li><b>Access</b> and review member matches/pairs</li>
            <li><b>Remove</b> members from the group</li>
            <li><b>Edit</b> member information</li>
            <li><b>Export</b> group data</li>
            </ul>
            <a href="#" id="make-pro-btn">Upgrade to PRO</a>
            <div class="paypal-pay" style="display:none;">
                <?php get_template_part( 'paypal/index', 'paypal' ); ?>
            </div>
        </div>
    </div>
</div>