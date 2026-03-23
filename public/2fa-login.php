<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

// Check if user is pending 2FA verification (coming from login.php)
$userId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$userId) {
    redirect('/login.php');
}

$userModel = new User();
$user = $userModel->findById($userId);
if (!$user || !$user['twofa_enabled']) {
    redirect('/login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
        $error = 'Please enter a valid 6-digit code.';
    } else {
        // Verify 2FA code
        /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
        $google2fa = new Google2FA();
        if ($google2fa->verifyKey($user['twofa_secret'], $code, 2)) {
            // Code is valid - complete login
            Auth::login($user);
            
            // Clear temp session
            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_email']);
            
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid 2FA code. Please try again.';
        }
    }
}

$pageTitle = 'Verify 2FA Code';
$currentPage = 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?> — JobTracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width:400px">
  <div class="auth-logo">
    <div class="brand-icon"><i class="bi bi-lock-fill"></i></div>
    <span class="brand-name">JobTracker</span>
  </div>

  <h2 class="text-center mb-1" style="font-family:'Sora',sans-serif;font-size:22px">Verify Your Identity</h2>
  <p class="text-center text-muted mb-4" style="font-size:13px">Enter the 6-digit code from your authenticator app</p>

  <?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= h($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <form method="POST" style="margin-top:25px">
    <div class="form-group mb-3">
      <label style="font-size:12px;font-weight:600;color:#9ca3af;margin-bottom:8px;display:block">2FA Code</label>
      <input type="text" 
             name="code" 
             class="form-control" 
             maxlength="6" 
             placeholder="000000"
             inputmode="numeric"
             pattern="[0-9]{6}"
             style="font-size:24px;letter-spacing:8px;text-align:center;font-weight:600;padding:16px"
             required 
             autofocus>
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-3">
      <i class="bi bi-shield-check"></i> Verify Code
    </button>
  </form>

  <p class="text-center" style="font-size:12px;color:#9ca3af;margin-top:20px">
    Don't have access to your authenticator?<br>
    <a href="/settings.php" style="color:#1a73e8;text-decoration:none">Disable 2FA in Settings</a>
  </p>
</div>

</body>
</html>
