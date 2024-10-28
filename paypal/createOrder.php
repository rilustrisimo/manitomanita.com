<?php

require 'PayPalClient.php';

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

header('Content-Type: application/json');

function createOrder()
{
    $client = PayPalClient::client();
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    
    // Create the order request body
    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [
            [
                "amount" => [
                    "currency_code" => "PHP", // Philippine Peso
                    "value" => "500.00", // Set this to the actual amount
                    "breakdown" => [ // Optional: provides more detail on the pricing
                        "item_total" => [
                            "currency_code" => "PHP",
                            "value" => "500.00"
                        ]
                    ]
                ]
            ]
        ],
        "application_context" => [ // Set the context for the transaction
            "return_url" => "https://manitomanita.com", // Redirect URL after payment
            "cancel_url" => "https://manitomanita.com", // Redirect URL if payment is canceled
            "locale" => "en-PH", // Localized experience
            "shipping_preference" => "NO_SHIPPING", // No shipping for digital products
            "user_action" => "PAY_NOW" // Prompt user for immediate payment
        ]
    ];

    try {
        // Execute the request
        $response = $client->execute($request);
        return $response;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Create order and output the response
$response = createOrder();
echo json_encode($response->result);