<?php 
$group = new Groups();
$groupid = $group->getGroupId();  // Replace with your actual group ID
?>
<div id="paypal-button-container"></div>
<script src="https://www.paypal.com/sdk/js?client-id=AWfSmpGYQbE7kAF11flB0JO4kVfYV4ya7ORa4w73N8nODYukMkV61FHpBDM0B3MkaWFPSA22L131zVCr&currency=PHP"></script>
<script>
    const themeBaseUrl = '<?php echo get_template_directory_uri(); ?>';
    const gid = '<?php echo $groupid; ?>';
</script>
<script>
    paypal.Buttons({
        locale: 'en_PH', // Sets the locale to English, Philippines
        createOrder: function(data, actions) {
            return fetch(`${themeBaseUrl}/paypal/createOrderCredits.php`, {
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
            fetch(`/wp-json/custom-api/v1/webhook-credits/?gid=${gid}&order_id=${data.orderID}`, { 
                method: 'GET'
            })
            .then(function(response) {
                return response.json(); // Parse response as JSON
            })
            .then(function(data) {
                if (data.status === 'success') {
                    console.log('API call to add credits was successful:', data.message);
                    // Refresh the page after a successful API call
                    jQuery('#reload').submit(); // Refresh the page if matching succeeded
                } else {
                    console.error('API call to add credits failed:', data.message);
                    // Hide the loader if the API call fails
                    document.getElementById('loader-overlay').style.display = 'none';
                    alert('There was an issue processing your request. Please try again.');
                }
            })
            .catch(function(error) {
                console.error('Error with add credits API call:', error);
                // Hide the loader if there's a network error
                document.getElementById('loader-overlay').style.display = 'none';
                alert('Network error. Please check your connection or try again later.');
            });

        });
    }

    }).render('#paypal-button-container');
</script>
