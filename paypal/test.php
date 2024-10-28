<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Add validation
$dotenv->required(['PAYPAL_CLIENT_ID', 'PAYPAL_CLIENT_SECRET', 'PAYPAL_MODE'])->notEmpty();

echo getenv('PAYPAL_CLIENT_ID');  // Check if this prints the client ID