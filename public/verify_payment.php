<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

Auth::require();

$data = json_decode(file_get_contents('php://input'), true);

$success = false;
$message = '';

if (isset($data['razorpay_payment_id'], $data['razorpay_order_id'], $data['razorpay_signature'])) {
    $attributes = [
        'razorpay_order_id' => $data['razorpay_order_id'],
        'razorpay_payment_id' => $data['razorpay_payment_id'],
        'razorpay_signature' => $data['razorpay_signature']
    ];

    try {
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        $api->utility->verifyPaymentSignature($attributes);
        
        // Signature is valid - mark user as premium
        $userId = Auth::id();
        $userModel = new User();
        $userModel->update($userId, ['plan' => 'premium']);
        
        // Update session
        $_SESSION['user_plan'] = 'premium';
        
        $success = true;
        $message = 'Payment verified successfully! Premium unlocked.';
    } catch (SignatureVerificationError $e) {
        $message = 'Payment verification failed!';
    }
} else {
    $message = 'Missing payment data.';
}

echo json_encode(['success' => $success, 'message' => $message]);