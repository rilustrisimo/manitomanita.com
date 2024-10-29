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
        // Show the loader overlay
        document.getElementById('loader-overlay-paypal').style.display = 'flex';
        jQuery('.pop-container').hide();

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

            // Fire additional GET request to the specified URL after a successful purchase
            fetch(`https://dev.manitomanita.com/wp-json/custom-api/v1/webhook/?gid=${gid}&order_id=${data.orderID}`, { //staging
            //fetch(`https://manitomanita.com/wp-json/custom-api/v1/webhook/?gid=${gid}&order_id=${data.orderID}`, { //live
                method: 'GET'
            })
            .then(function(response) {
                return response.json(); // Parse response as JSON
            })
            .then(function(data) {
                if (data.status === 'success') {
                    console.log('API call to make-pro was successful:', data.message);
                    // Refresh the page after a successful API call
                    location.reload();
                } else {
                    console.error('API call to make-pro failed:', data.message);
                    // Hide the loader if the API call fails
                    document.getElementById('loader-overlay').style.display = 'none';
                    alert('There was an issue processing your request. Please try again.');
                }
            })
            .catch(function(error) {
                console.error('Error with make-pro API call:', error);
                // Hide the loader if there's a network error
                document.getElementById('loader-overlay').style.display = 'none';
                alert('Network error. Please check your connection or try again later.');
            });

        });
    }

    }).render('#paypal-button-container');
</script>
