<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

try {
    // Check if user is pending 2FA setup (coming from login.php)
    $userId = $_SESSION['pending_2fa_user_id'] ?? null;
    if (!$userId) {
        redirect('/login.php');
    }

    $userModel = new User();
    $user = $userModel->findById($userId);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('/2fa-setup.php');
    }

    // Verify CSRF
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        redirect('/2fa-setup.php?error=csrf');
    }

    $code = trim($_POST['code'] ?? '');

    if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
        redirect('/2fa-setup.php?error=invalid_code');
    }

    // Verify the code
    /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
    $google2fa = new Google2FA();
    if (!$google2fa->verifyKey($user['twofa_secret'], $code, 2)) {
        // Code verification failed - redirect back with error
        redirect('/2fa-setup.php?error=wrong_code');
    }

    // Enable 2FA
    Database::getInstance()->prepare('UPDATE users SET twofa_enabled = 1, twofa_setup_required = 0 WHERE id = ?')
        ->execute([$userId]);

    // Now log the user in
    Auth::login($user);
    
    // Clear temp session
    unset($_SESSION['pending_2fa_user_id']);
    unset($_SESSION['pending_2fa_email']);

    // Redirect to dashboard
    redirect('/dashboard.php');
} catch (Exception $e) {
    // Log error and redirect back
    error_log('2FA Verification Error: ' . $e->getMessage());
    redirect('/2fa-setup.php?error=server_error');
    exit;
}
?>
