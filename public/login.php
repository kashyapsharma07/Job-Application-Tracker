<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (Auth::check()) {
    redirect('/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $userModel = new User();
            $user = $userModel->findByEmail($email);
            if ($user && $userModel->verifyPassword($password, $user['password_hash'])) {
                Auth::login($user);
                redirect('/dashboard.php');
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — JobTracker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="auth-logo">
    <div class="brand-icon"><i class="bi bi-briefcase-fill"></i></div>
    <span class="brand-name">JobTracker</span>
  </div>
  <h2 class="text-center mb-1" style="font-family:'Sora',sans-serif;font-size:22px">Welcome back</h2>
  <p class="text-center text-muted mb-4" style="font-size:14px">Sign in to your account</p>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="mb-3">
      <label class="form-label">Email address</label>
      <input type="email" name="email" class="form-control" required
             value="<?= h($_POST['email'] ?? '') ?>" placeholder="you@example.com">
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign In</button>
  </form>

  <p class="text-center mt-4 mb-0" style="font-size:13px">
    Don't have an account? <a href="<?= APP_URL ?>/register.php" class="text-decoration-none fw-600">Sign up</a>
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
