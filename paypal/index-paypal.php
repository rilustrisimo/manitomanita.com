<div id="paypal-button-container"></div>
<script src="https://www.paypal.com/sdk/js?client-id=Aev7owXcA7gNoB_lLSy9u2iikYz6Kt4YLFvtVnC-hjX7Z2DqjVpLB7Nxx-7l1ueYyDPeNQKt9_YtVcR0&currency=PHP"></script>
<script>
    const themeBaseUrl = '<?php echo get_template_directory_uri(); ?>';
</script>
<script>
    paypal.Buttons({
        locale: 'en_PH', // Sets the locale to English, Philippines
        createOrder: function(data, actions) {
            return fetch(`${themeBaseUrl}/paypal/createOrder.php`, {
                method: 'post'
            }).then(function(response) {
                return response.json();
            }).then(function(orderData) {
                return orderData.id;
            });
        },
        onApprove: function(data, actions) {
            return fetch(`${themeBaseUrl}/paypal/captureOrder.php`, {
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