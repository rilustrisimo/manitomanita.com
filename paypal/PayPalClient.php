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

        /**test */
        //$clientId = 'Aev7owXcA7gNoB_lLSy9u2iikYz6Kt4YLFvtVnC-hjX7Z2DqjVpLB7Nxx-7l1ueYyDPeNQKt9_YtVcR0';
        //$clientSecret = 'ED1_WgCspRlKpGBna_0el69WI-Jv7Wsu-zvV6z3z9Zv0whVGHnFpfL10MBfMLNEsiBliNQi91zOhfGtH';
        //$environment = new SandboxEnvironment($clientId, $clientSecret);
        /**test end */

        $clientId = 'AWfSmpGYQbE7kAF11flB0JO4kVfYV4ya7ORa4w73N8nODYukMkV61FHpBDM0B3MkaWFPSA22L131zVCr';
        $clientSecret = 'ED69udEdVsoFt7J6-cXUP9yjBgeI09FPGTc71hpaY8KM48z5fQjZdfDWWZi49JkV53Kp4yjxWedNfIHX';
        $environment = new ProductionEnvironment($clientId, $clientSecret);
        
        
        return new PayPalHttpClient($environment);
    }
}
