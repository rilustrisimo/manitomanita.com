<?php

require 'vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;

class PayPalClient
{
    public static function client()
    {
        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_CLIENT_SECRET');
        $environment = getenv('PAYPAL_MODE') === 'sandbox' ?
            new SandboxEnvironment($clientId, $clientSecret) :
            new ProductionEnvironment($clientId, $clientSecret);
        
        return new PayPalHttpClient($environment);
    }
}
