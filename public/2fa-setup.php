<?php
require_once __DIR__ . '/../src/bootstrap.php';

use PragmaRX\Google2FA\Google2FA;

// Check if user is pending 2FA setup (coming from login.php)
$userId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$userId) {
    redirect('/login.php');
}

$userModel = new User();
$user = $userModel->findById($userId);

// Get error from redirect
$error = null;
if (!empty($_GET['error'])) {
    $errorCode = $_GET['error'];
    $errorMessages = [
        'invalid_code' => 'Please enter exactly 6 digits.',
        'wrong_code' => 'The code you entered is incorrect. Please try again.',
        'csrf' => 'Security validation failed. Please try again.',
        'server_error' => 'A server error occurred. Please try again.',
    ];
    $error = $errorMessages[$errorCode] ?? 'An error occurred.';
}

// Get or generate secret
$secret = $user['twofa_secret'] ?? null;

if (!$secret) {
    /** @var \PragmaRX\Google2FA\Google2FA $google2fa */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();
    
    // Store secret temporarily (not yet verified)
    Database::getInstance()->prepare('UPDATE users SET twofa_secret = ? WHERE id = ?')
        ->execute([$secret, $userId]);
}

// Generate QR Code
/** @var \PragmaRX\Google2FA\Google2FA $google2fa */
$google2fa = new Google2FA();
$appName = 'JobTracker';
$qrCodeUrl = $google2fa->getQRCodeUrl($appName, $user['email'], $secret);

// Create QR code image
$renderer = new \BaconQrCode\Renderer\ImageRenderer(
    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200),
    new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
);
$writer = new \BaconQrCode\Writer($renderer);
$qrCodeImage = $writer->writeString($qrCodeUrl);
$qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrCodeImage);

$pageTitle   = 'Setup 2-Factor Authentication';
$currentPage = 'settings';

ob_start();
?>

<div style="margin-bottom:30px">
  <h1 style="font-size:28px; margin-bottom:10px">Setup Two-Factor Authentication</h1>
  <p style="color:#9ca3af; margin:0">Secure your account with an additional layer of protection</p>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" style="margin-bottom:20px">
  <i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?>
</div>
<?php endif; ?>

<div class="row">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>Step 1: Download Authenticator App</span>
      </div>
      <div class="card-body">
        <p style="margin-bottom:15px">Download one of these apps on your phone:</p>
        <ul style="margin-bottom:20px">
          <li><strong>Google Authenticator</strong> - iOS & Android</li>
          <li><strong>Microsoft Authenticator</strong> - iOS & Android</li>
          <li><strong>Authy</strong> - iOS & Android</li>
        </ul>
        <p style="color:#9ca3af; font-size:13px">These apps generate 6-digit codes every 30 seconds</p>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>Step 2: Scan QR Code</span>
      </div>
      <div class="card-body text-center">
        <div style="margin-bottom:20px">
          <?= $qrCodeImage ?>
        </div>
        <p style="font-size:13px; color:#9ca3af; margin-bottom:10px">Can't scan? Enter this code manually:</p>
        <div style="background:#f0f4f9; padding:12px; border-radius:6px; font-family:monospace; font-size:14px; letter-spacing:2px; margin-bottom:15px">
          <?= h($secret) ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>Step 3: Verify Setup</span>
      </div>
      <div class="card-body">
        <p style="margin-bottom:15px">Enter the 6-digit code from your authenticator app to complete setup:</p>
        <form method="POST" action="./2fa-verify-setup.php">
          <div style="margin-bottom:15px">
            <label style="display:block; font-size:12px; color:#9ca3af; margin-bottom:6px; font-weight:600">Verification Code</label>
            <input type="text" name="code" maxlength="6" placeholder="000000" 
                   style="font-size:24px; letter-spacing:8px; text-align:center; padding:12px; width:100%; border:1px solid #e5e7eb; border-radius:6px"
                   inputmode="numeric" required>
          </div>
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <button type="submit" class="btn btn-primary" style="width:100%">Verify & Enable 2FA</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>Safety Information</span>
      </div>
      <div class="card-body">
        <p style="color:#ef4444; font-weight:600; margin-bottom:10px">⚠️ Important:</p>
        <ul style="margin-bottom:15px; font-size:13px">
          <li>Save your secret code in a safe place</li>
          <li>If you lose access to your authenticator app, you'll lose account access</li>
          <li>You can disable 2FA anytime from Settings</li>
          <li>Each code can only be used once</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
?>
