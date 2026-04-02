<?php
require_once __DIR__ . '/../src/bootstrap.php';

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (!$name || !$email || !$password) {
          $error = 'All required fields must be filled.';
        } elseif (!preg_match("/^[a-zA-Z .'-]{2,50}$/u", $name)) {
          $error = 'Name must only contain letters, spaces, hyphens, apostrophes, and dots (2-50 chars).';
        } elseif (preg_match('/<[^>]+>/', $name)) {
          $error = 'Name cannot contain HTML or script tags.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
          $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password) ||
              !preg_match('/[a-z]/', $password) ||
              !preg_match('/[0-9]/', $password) ||
              !preg_match('/[^a-zA-Z0-9]/', $password)) {
          $error = 'Password must include uppercase, lowercase, number, and symbol.';
        } elseif ($password !== $confirm) {
          $error = 'Passwords do not match.';
        } else {
            $userModel = new User();
            if ($userModel->emailExists($email)) {
                $error = 'Email already registered. <a href="login.php">Sign in</a>';
            } else {
                $id = $userModel->create(['name'=>$name,'email'=>$email,'password'=>$password,'job_title'=>$jobTitle]);
                $success = 'User registered successfully!';
                // Do not redirect, show success message
            }
        }
    }
}
$csrf = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up — JobTracker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/css/app.css" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="auth-logo">
    <div class="brand-icon" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:10px;overflow:hidden;padding:0;box-shadow:0 2px 12px 0 rgba(30, 64, 175, 0.18);">
      <img src="<?= APP_URL ?>/uploads/resumes/job_logo.png" alt="Logo" style="width:140%;height:140%;object-fit:cover;display:block;margin-left:-10%;margin-top:-10%;">
    </div>
    <span class="brand-name">JobTracker</span>
  </div>
  <h2 class="text-center mb-1" style="font-family:'Sora',sans-serif;font-size:22px">Create your account</h2>
  <p class="text-center text-muted mb-4" style="font-size:14px">Start tracking your job search today</p>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px"><?php echo $error; ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success py-2 px-3 mb-3" style="font-size:13px"><?= $success ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="mb-3">
      <label class="form-label">Full Name *</label>
      <input type="text" name="name" class="form-control" required
             value="<?= h($_POST['name'] ?? '') ?>" placeholder="Alex Rivers">
    </div>
    <div class="mb-3">
      <label class="form-label">Email *</label>
      <input type="email" name="email" class="form-control" required
             value="<?= h($_POST['email'] ?? '') ?>" placeholder="you@example.com">
    </div>
    <div class="mb-3">
      <label class="form-label">Job Title</label>
      <input type="text" name="job_title" class="form-control"
             value="<?= h($_POST['job_title'] ?? '') ?>" placeholder="Product Designer">
    </div>
    <div class="mb-3">
      <label class="form-label">Password *</label>
      <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
    </div>
    <div class="mb-4">
      <label class="form-label">Confirm Password *</label>
      <input type="password" name="password_confirm" class="form-control" required placeholder="Repeat password">
    </div>
    <button type="submit" class="btn btn-primary w-100">Create Account</button>
  </form>

  <p class="text-center mt-4 mb-0" style="font-size:13px">
    Already have an account? <a href="<?= APP_URL ?>/login.php" class="text-decoration-none">Sign in</a>
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
