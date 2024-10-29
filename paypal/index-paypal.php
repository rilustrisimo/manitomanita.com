<?php 
$group = new Groups();
$groupid = $group->getGroupId();  // Replace with your actual group ID
?>
<div id="paypal-button-container"></div>
<script src="https://www.paypal.com/sdk/js?client-id=Aev7owXcA7gNoB_lLSy9u2iikYz6Kt4YLFvtVnC-hjX7Z2DqjVpLB7Nxx-7l1ueYyDPeNQKt9_YtVcR0&currency=PHP"></script>
<script>
    const themeBaseUrl = '<?php echo get_template_directory_uri(); ?>';
    const gid = '<?php echo $groupid; ?>';
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

                // Fire additional GET request to the specified URL after successful purchase
                fetch(`https://dev.manitomanita.com/wp-json/custom-api/v1/webhook/?gid=${gid}&order_id=${data.orderID}`, { //staging
                //fetch(`https://manitomanita.com/wp-json/custom-api/v1/webhook/?gid=${gid}&order_id=${data.orderID}`, {
                    method: 'GET'
                }).then(function(response) {
                    if (response.ok) {
                        console.log('API call to make-pro was successful.');
                        // Refresh the page after successful API call
                        location.reload();
                    } else {
                        console.error('API call to make-pro failed.');
                    }
                }).catch(function(error) {
                    console.error('Error with make-pro API call:', error);
                });
            });
        }
    }).render('#paypal-button-container');
</script>
