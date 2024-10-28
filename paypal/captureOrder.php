<?php

require 'PayPalClient.php';

use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

header('Content-Type: application/json');

$orderID = json_decode(file_get_contents("php://input"))->orderID;

function captureOrder($orderID)
{
    $client = PayPalClient::client();
    $request = new OrdersCaptureRequest($orderID);
    $request->prefer('return=representation');
    
    try {
        $response = $client->execute($request);
        return $response;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

$response = captureOrder($orderID);
echo json_encode($response->result);
