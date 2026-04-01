<?php
require_once __DIR__ . '/../src/bootstrap.php';

// HTTPS Enforcement for sensitive auth pages
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    redirect('/login.php');
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        if ($email) {
                $userModel = new User();
                $user = $userModel->findByEmail($email);
                if ($user) {
                        // Rate limiting: Check password reset attempts in last 15 minutes
                        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                        $fifteenMinutesAgo = date('Y-m-d H:i:s', time() - 900); // 15 minutes
                        
                        $stmt = Database::getInstance()->prepare(
                            'SELECT COUNT(*) as count FROM login_logs 
                             WHERE user_id = ? AND ip_address = ? AND login_time > ? AND status = ?'
                        );
                        $stmt->execute([$user['id'], $clientIp, $fifteenMinutesAgo, 'password_reset']);
                        $resetAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Allow up to 3 attempts per 15 minutes
                        if ($resetAttempts >= 3) {
                            // Still show success for privacy, but don't send email
                            $success = true;
                        } else {
                            // Generate secure token
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                            $tokenHash = hash('sha256', $token);

                            // Store hash and expiry in DB
                            Database::getInstance()->prepare(
                                    'UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?'
                            )->execute([$tokenHash, $expires, $user['id']]);

                            // Log password reset attempt
                            Database::getInstance()->prepare(
                                'INSERT INTO login_logs (user_id, ip_address, status) VALUES (?, ?, ?)'
                            )->execute([$user['id'], $clientIp, 'password_reset']);

                            // Send email
                            $resetLink = APP_URL . '/reset-password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
                            $emailBody = <<<HTML
                            <!DOCTYPE html>
                            <html>
                        <head><meta charset="UTF-8"><style>
                            body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}
                            .card{background:#fff;border-radius:8px;padding:30px;max-width:500px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.08)}
                            .header{color:#1a73e8;font-size:22px;font-weight:bold;margin-bottom:20px}
                            .button{display:inline-block;background:#1a73e8;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;margin:20px 0;text-align:center}
                            .footer{margin-top:20px;font-size:12px;color:#999}
                            .warning{color:#d32f2f;font-size:12px;margin-top:20px}
                            a{color:#1a73e8}
                        </style></head>
                        <body>
                        <div class="card">
                          <div class="header">🔐 Password Reset Request</div>
                          <p>We received a request to reset your password. Click the button below to set a new password:</p>
                          <a href="$resetLink" class="button">Reset Password</a>
                          <p>Or copy and paste this link in your browser:</p>
                          <p style="word-break:break-all;"><small>$resetLink</small></p>
                          <p class="warning">⚠️ This link will expire in 1 hour. If you did not request this, you can safely ignore this email.</p>
                          <div class="footer">JobTracker — manage your job search smarter.</div>
                        </div>
                        </body></html>
                        HTML;
                            Mailer::send($email, $user['name'], 'Password Reset Request', $emailBody);
                            $success = true;
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
    <title>Forgot Password — JobTracker</title>
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
    <h2 class="text-center mb-1" style="font-family:'Sora',sans-serif;font-size:22px">Forgot Password</h2>
    <p class="text-center text-muted mb-4" style="font-size:14px">Enter your email to receive a password reset link.</p>
    <?php if ($success): ?>
        <div class="alert alert-success py-2 px-3 mb-3" style="font-size:13px">If that email is registered, you’ll receive a reset link.</div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" class="form-control" required placeholder="you@example.com">
            </div>
            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
        </form>
    <?php endif; ?>
    <p class="text-center mt-4 mb-0" style="font-size:13px">
        <a href="login.php" class="text-decoration-none fw-600">Back to login</a>
    </p>
</div>
</body>
</html>