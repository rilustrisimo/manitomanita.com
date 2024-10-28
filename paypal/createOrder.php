<?php

require 'PayPalClient.php';

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

header('Content-Type: application/json');

function createOrder()
{
    $client = PayPalClient::client();
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    
    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [
            [
                "amount" => [
                    "currency_code" => "PHP",
                    "value" => "500.00"  // Set this to the actual amount
                ]
            ]
        ]
    ];

    try {
        $response = $client->execute($request);
        return $response;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

$response = createOrder();
echo json_encode($response->result);
