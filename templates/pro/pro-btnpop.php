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

                <div id="paypal-button-container"></div>

                <script src="https://www.paypal.com/sdk/js?client-id=Aev7owXcA7gNoB_lLSy9u2iikYz6Kt4YLFvtVnC-hjX7Z2DqjVpLB7Nxx-7l1ueYyDPeNQKt9_YtVcR0&currency=PHP"></script>

                <script>
                    paypal.Buttons({
                        createOrder: function(data, actions) {
                            return fetch('../../paypal/createOrder.php', {
                                method: 'post'
                            }).then(function(response) {
                                return response.json();
                            }).then(function(orderData) {
                                return orderData.id;
                            });
                        },
                        onApprove: function(data, actions) {
                            return fetch('../../paypal/captureOrder.php', {
                                method: 'post',
                                headers: {
                                    'content-type': 'application/json'
                                },
                                body: JSON.stringify({
                                    orderID: data.orderID
                                })
                            }).then(function(response) {
                                return response.json();
                            }).then(function(details) {
                                alert('Transaction completed by ' + details.payer.name.given_name);
                                console.log(details);
                            });
                        }
                    }).render('#paypal-button-container');
                </script>

            </div>
        </div>
    </div>
</div>