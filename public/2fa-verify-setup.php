<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

$userId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$userId) {
    redirect('/login.php');
}

// Check if 2FA verification has timed out (30 minutes)
$twoFaStartTime = $_SESSION['pending_2fa_start_time'] ?? time();
if ((time() - $twoFaStartTime) > 30) { // 30 seconds
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_start_time']);
    flash('error', '2FA verification session expired. Please log in again.');
    redirect('/login.php');
}
$_SESSION['pending_2fa_start_time'] = $twoFaStartTime;

$userModel = new User();
$user = $userModel->findById($userId);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
            $error = 'Security validation failed. Please try again.';
        } else {
            $code = trim($_POST['code'] ?? '');

            if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
                $error = 'Please enter exactly 6 digits.';
            } else {
                /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
                $google2fa = new Google2FA();
                // 0 = only current 30-second window, no tolerance
                if (!$google2fa->verifyKey($user['twofa_secret'], $code, 0)) {
                    $error = 'The code you entered is incorrect. Please try again.';
                } else {
                    Database::getInstance()->prepare('UPDATE users SET twofa_enabled = 1, twofa_setup_required = 0 WHERE id = ?')
                        ->execute([$userId]);

                    // Mark that 2FA has been verified for this session
                    $_SESSION['2fa_verified'] = true;

                    Auth::login($user);
                    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_start_time']);
                    redirect('/dashboard.php');
                }
            }
        }
    } catch (Exception $e) {
        error_log('2FA Verification Error: ' . $e->getMessage());
        $error = 'A server error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify Setup — JobTracker</title>
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

      <!-- Step tracker -->
      <div class="twofa-steps">
        <div class="twofa-step-item">
          <div class="twofa-step-dot twofa-step-dot--done"><i class="bi bi-check"></i></div>
          <span class="twofa-step-label">Get App</span>
        </div>
        <div class="twofa-step-line twofa-step-line--done"></div>
        <div class="twofa-step-item">
          <div class="twofa-step-dot twofa-step-dot--done"><i class="bi bi-check"></i></div>
          <span class="twofa-step-label">Scan Code</span>
        </div>
        <div class="twofa-step-line twofa-step-line--done"></div>
        <div class="twofa-step-item">
          <div class="twofa-step-dot twofa-step-dot--active">3</div>
          <span class="twofa-step-label twofa-step-label--active">Verify</span>
        </div>
      </div>

      <div class="twofa-icon-block">
        <i class="bi bi-patch-check-fill"></i>
      </div>

      <h1>Confirm Your Setup</h1>
      <p class="twofa-subtitle">Enter the 6-digit code from your authenticator app to activate 2FA on your account.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-4">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
        <?= h($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

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
          <i class="bi bi-shield-check"></i> Verify & Enable 2FA
        </button>
      </form>

      <div class="twofa-footer">
        <a href="<?= APP_URL ?>/2fa-setup.php">
          <i class="bi bi-arrow-left"></i> Back to setup
        </a>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>