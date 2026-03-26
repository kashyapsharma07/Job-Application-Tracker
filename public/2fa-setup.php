<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

$userId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$userId) {
    redirect('/login.php');
}

$userModel = new User();
$user = $userModel->findById($userId);

$error = null;
if (!empty($_GET['error'])) {
    $errorMessages = [
        'invalid_code'  => 'Please enter exactly 6 digits.',
        'wrong_code'    => 'The code you entered is incorrect. Please try again.',
        'csrf'          => 'Security validation failed. Please try again.',
        'server_error'  => 'A server error occurred. Please try again.',
    ];
    $error = $errorMessages[$_GET['error']] ?? 'An error occurred.';
}

$secret = $user['twofa_secret'] ?? null;
if (!$secret) {
    /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    Database::getInstance()->prepare('UPDATE users SET twofa_secret = ? WHERE id = ?')
        ->execute([$secret, $userId]);
}

/** @var \PragmaRX\Google2FA\Google2FA $google2fa */
$google2fa   = new Google2FA();
$qrCodeUrl   = $google2fa->getQRCodeUrl('JobTracker', $user['email'], $secret);

$renderer    = new \BaconQrCode\Renderer\ImageRenderer(
    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
);
$writer      = new \BaconQrCode\Writer($renderer);
$qrCodeImage = $writer->writeString($qrCodeUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup 2FA — JobTracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="./css/app.css" rel="stylesheet">
</head>
<body class="twofa-page">

  <div class="twofa-wrap--wide">

    <a href="<?= APP_URL ?>/login.php" class="twofa-logo">
      <div class="brand-icon"><i class="bi bi-briefcase-fill"></i></div>
      <span class="brand-name">JobTracker</span>
    </a>

    <div class="twofa-page-head">
      <h1>Set Up Two-Factor Authentication</h1>
      <p>Follow the steps below to secure your account with a one-time password app.</p>
    </div>

    <!-- Step progress -->
    <div class="twofa-steps">
      <div class="twofa-step-item">
        <div class="twofa-step-dot twofa-step-dot--active">1</div>
        <span class="twofa-step-label twofa-step-label--active">Get App</span>
      </div>
      <div class="twofa-step-line"></div>
      <div class="twofa-step-item">
        <div class="twofa-step-dot twofa-step-dot--active">2</div>
        <span class="twofa-step-label twofa-step-label--active">Scan Code</span>
      </div>
      <div class="twofa-step-line"></div>
      <div class="twofa-step-item">
        <div class="twofa-step-dot twofa-step-dot--pending">3</div>
        <span class="twofa-step-label">Verify</span>
      </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-4">
      <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
      <?= h($error) ?>
    </div>
    <?php endif; ?>

    <!-- Step 1 + Step 2 side by side -->
    <div class="row g-3 mb-3">

      <div class="col-md-5">
        <div class="twofa-section-card h-100">
          <div class="twofa-section-card-header">
            <span class="twofa-step-badge">1</span>
            Download an Authenticator App
          </div>
          <div class="twofa-section-card-body">
            <p class="text-secondary mb-3" style="font-size:13px">Install one of these free apps on your phone:</p>
            <ul class="twofa-app-list">
              <li>
                <i class="bi bi-google"></i>
                <div><span>Google Authenticator</span><small>iOS &amp; Android — Free</small></div>
              </li>
              <li>
                <i class="bi bi-microsoft"></i>
                <div><span>Microsoft Authenticator</span><small>iOS &amp; Android — Free</small></div>
              </li>
              <li>
                <i class="bi bi-phone"></i>
                <div><span>Authy</span><small>iOS &amp; Android — Free</small></div>
              </li>
            </ul>
            <p class="text-secondary mb-0 mt-3" style="font-size:12px">These apps generate a new 6-digit code every 30 seconds.</p>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="twofa-section-card h-100">
          <div class="twofa-section-card-header">
            <span class="twofa-step-badge">2</span>
            Scan the QR Code
          </div>
          <div class="twofa-section-card-body">
            <p class="text-secondary mb-3" style="font-size:13px">Open your authenticator app, tap <strong>+</strong> or <strong>Add account</strong>, then scan this code.</p>
            <div class="twofa-qr-area">
              <div class="twofa-qr-frame"><?= $qrCodeImage ?></div>
              <span class="twofa-qr-divider">— or enter manually —</span>
              <div class="twofa-secret-box" title="Click to select and copy"><?= h($secret) ?></div>
              <p class="twofa-secret-hint"><i class="bi bi-cursor-text me-1"></i>Click the code above to select it for copying</p>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Step 3: Proceed -->
    <div class="twofa-section-card mb-3">
      <div class="twofa-section-card-header">
        <span class="twofa-step-badge">3</span>
        Verify Your Setup
      </div>
      <div class="twofa-section-card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <p class="text-secondary mb-0" style="font-size:13px;max-width:420px">
          Once your app is showing a 6-digit code for JobTracker, click the button to verify and activate 2FA.
        </p>
        <a href="<?= APP_URL ?>/2fa-verify-setup.php" class="twofa-btn-inline">
          Continue to Verification <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>

    <!-- Warning notice -->
    <div class="twofa-warn">
      <div class="twofa-warn-header">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Important — Save Your Secret Key
      </div>
      <ul class="twofa-warn-list">
        <li><i class="bi bi-key-fill"></i> Store your secret key in a password manager or secure location.</li>
        <li><i class="bi bi-phone-fill"></i> Losing access to your authenticator without a backup will lock you out.</li>
        <li><i class="bi bi-shield-x"></i> You can disable 2FA anytime from your account Settings.</li>
        <li><i class="bi bi-clock"></i> Each code is valid for 30 seconds and can only be used once.</li>
      </ul>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>