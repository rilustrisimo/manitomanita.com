<?php

require 'vendor/autoload.php';

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use Dotenv\Dotenv;

class PayPalClient
{
    public static function client()
    {
        // Load environment variables from .env file
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Access the variables
        //$clientId = getenv('PAYPAL_CLIENT_ID');
        //$clientSecret = getenv('PAYPAL_CLIENT_SECRET');
        //$environment = getenv('PAYPAL_MODE') === 'sandbox' ?
        //    new SandboxEnvironment($clientId, $clientSecret) :
        //    new ProductionEnvironment($clientId, $clientSecret);

        $clientId = 'Aev7owXcA7gNoB_lLSy9u2iikYz6Kt4YLFvtVnC-hjX7Z2DqjVpLB7Nxx-7l1ueYyDPeNQKt9_YtVcR0';
        $clientSecret = 'ED1_WgCspRlKpGBna_0el69WI-Jv7Wsu-zvV6z3z9Zv0whVGHnFpfL10MBfMLNEsiBliNQi91zOhfGtH';
        $environment = new SandboxEnvironment($clientId, $clientSecret);
        
        return new PayPalHttpClient($environment);
    }
}
