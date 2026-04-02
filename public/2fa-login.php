<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

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
        /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
        $google2fa = new Google2FA();
        if ($google2fa->verifyKey($user['twofa_secret'], $code, 2)) {
            // Mark that 2FA has been verified
            $_SESSION['2fa_verified'] = true;
            
            Auth::login($user);
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email']);
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid 2FA code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify 2FA — JobTracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css" rel="stylesheet">
</head>
<body class="twofa-page">

  <div class="twofa-wrap">

    <a href="<?= APP_URL ?>/login.php" class="twofa-logo">
      <div class="brand-icon" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:10px;overflow:hidden;padding:0;box-shadow:0 2px 12px 0 rgba(30, 64, 175, 0.18);">
        <img src="<?= APP_URL ?>/uploads/resumes/job_logo.png" alt="Logo" style="width:180%;height:180%;object-fit:cover;display:block;margin-left:-10%;margin-top:-10%;">
      </div>
      <span class="brand-name">JobTracker</span>
    </a>

    <div class="twofa-card">

      <div class="twofa-icon-block">
        <i class="bi bi-shield-lock-fill"></i>
      </div>

      <h1>Two-Factor Verification</h1>
      <p class="twofa-subtitle">Open your authenticator app and enter the 6-digit code to continue.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-4">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
        <?= h($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <label class="twofa-code-label">Authentication Code</label>
        <input
          type="text"
          name="code"
          class="twofa-code-input"
          maxlength="6"
          placeholder="······"
          inputmode="numeric"
          autocomplete="one-time-code"
          required
          autofocus>

        <button type="submit" class="twofa-btn">
          <i class="bi bi-shield-check"></i> Verify & Sign In
        </button>
      </form>

      <div class="twofa-footer">
        Lost access to your authenticator?
        <a href="<?= APP_URL ?>/settings.php">Manage 2FA in Settings</a>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>