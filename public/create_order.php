<?php
// create_order.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

Auth::require();

$amount = 100; // Amount in paise (₹1.00) — must match go-premium.php
$currency = 'INR';

$api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
$order = $api->order->create([
    'amount'          => $amount,
    'currency'        => $currency,
    'payment_capture' => 1
]);

echo json_encode(['order_id' => $order['id']]);