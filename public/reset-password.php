<?php
require_once __DIR__ . '/../src/bootstrap.php';

// HTTPS Enforcement for sensitive auth pages
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    redirect('/login.php');
}

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = '';
$success = false;
$validToken = false;

if ($token && $email) {
    $userModel = new User();
    $user = $userModel->findByEmail($email);
    
    if ($user && $user['password_reset_token'] && $user['password_reset_expires']) {
        $tokenHash = hash('sha256', $token);
        
        // Check token matches and is not expired
        if (
            hash_equals($user['password_reset_token'], $tokenHash) &&
            strtotime($user['password_reset_expires']) > time()
        ) {
            $validToken = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
                    $error = 'Security validation failed. Please try again.';
                } else {
                    $password = $_POST['password'] ?? '';
                    $confirm  = $_POST['confirm'] ?? '';
                
                    // Validate password strength
                    $passwordErrors = validatePassword($password);
                    if (!empty($passwordErrors)) {
                        $error = implode('<br>', $passwordErrors);
                    } elseif ($password !== $confirm) {
                        $error = 'Passwords do not match.';
                    } else {
                        // Hash password and update user
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        Database::getInstance()->prepare(
                            'UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?'
                        )->execute([$passwordHash, $user['id']]);
                        $success = true;
                    }
                }
            }
        } else {
            $error = 'This reset link is invalid or has expired.';
        }
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'Invalid request.';
}

$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — JobTracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="auth-logo">
    <div class="brand-icon"><i class="bi bi-briefcase-fill"></i></div>
    <span class="brand-name">JobTracker</span>
  </div>
  <h2 class="text-center mb-1" style="font-family:'Sora',sans-serif;font-size:22px">Reset Password</h2>
  <p class="text-center text-muted mb-4" style="font-size:14px">Create a new password for your account.</p>
  
  <?php if ($success): ?>
    <div class="alert alert-success py-2 px-3 mb-3" style="font-size:13px">
      ✓ Your password has been reset successfully.
    </div>
    <p class="text-center" style="font-size:13px">
      <a href="login.php" class="btn btn-primary">Back to Login</a>
    </p>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px"><?= h($error) ?></div>
    <?php endif; ?>
    
    <?php if ($validToken): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required placeholder="••••••••" minlength="8">
          <small class="form-text text-muted">At least 8 characters</small>
        </div>
        <div class="mb-4">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required placeholder="••••••••" minlength="8">
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
      </form>
    <?php else: ?>
      <p class="text-center" style="font-size:13px">
        <a href="forgot-password.php" class="text-decoration-none fw-600">Request a new reset link</a>
      </p>
    <?php endif; ?>
  <?php endif; ?>
  
  <p class="text-center mt-4 mb-0" style="font-size:13px">
    <a href="login.php" class="text-decoration-none fw-600">Back to login</a>
  </p>
</div>
</body>
</html>
