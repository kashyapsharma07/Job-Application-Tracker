<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId    = Auth::id();
$userModel = new User();
$user      = $userModel->findById($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/settings.php');
    }

    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name     = trim($_POST['name'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (!$name || !$email) {
            flash('error', 'Name and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email.');
        } elseif ($userModel->emailExists($email, $userId)) {
            flash('error', 'Email already in use.');
        } else {
            $userModel->update($userId, ['name' => $name, 'job_title' => $jobTitle]);
            $_SESSION['user_name'] = $name;
            flash('success', 'Profile updated.');
        }
    } elseif ($action === 'notifications') {
        $userModel->update($userId, [
            'email_alerts'        => !empty($_POST['email_alerts']) ? 1 : 0,
            'interview_reminders' => !empty($_POST['interview_reminders']) ? 1 : 0,
            'marketing_comms'     => !empty($_POST['marketing_comms']) ? 1 : 0,
        ]);
        flash('success', 'Notification preferences saved.');
    } elseif ($action === 'password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$userModel->verifyPassword($current, $user['password_hash'])) {
          flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
          flash('error', 'New password must be at least 8 characters.');
        } elseif (!preg_match('/[A-Z]/', $new) ||
              !preg_match('/[a-z]/', $new) ||
              !preg_match('/[0-9]/', $new) ||
              !preg_match('/[^a-zA-Z0-9]/', $new)) {
          flash('error', 'Password must include uppercase, lowercase, number, and symbol.');
        } elseif ($new !== $confirm) {
          flash('error', 'Passwords do not match.');
        } else {
            $userModel->updatePassword($userId, $new);
            flash('success', 'Password updated.');
        }
    } elseif ($action === '2fa_setup') {
        // Just enable the setup flag - actual setup happens at next login
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET twofa_setup_required = 1 WHERE id = ?');
        $stmt->execute([$userId]);
        flash('success', '2FA will be set up on your next login.');
    } elseif ($action === '2fa_disable') {
        $password = $_POST['confirmation_password'] ?? '';
        if (!$userModel->verifyPassword($password, $user['password_hash'])) {
            flash('error', 'Password is incorrect. 2FA not disabled.');
        } else {
            $db = Database::getInstance();
            $stmt = $db->prepare('UPDATE users SET twofa_enabled = 0, twofa_secret = NULL, twofa_setup_required = 0 WHERE id = ?');
            $stmt->execute([$userId]);
            flash('success', '2FA has been disabled.');
        }
    }

    redirect('/settings.php');
}

// Reload user after POST
$user = $userModel->findById($userId);
$pageTitle   = 'Settings';
$currentPage = 'settings';

ob_start();
?>
<div class="mb-4">
  <h1 style="font-size:22px;margin:0">Account Preferences</h1>
  <p class="text-muted mb-0" style="font-size:13px">Manage your profile and notifications.</p>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Profile -->
    <div class="card mb-4">
      <div class="card-header"><span>Profile Information</span></div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="user-avatar" style="width:64px;height:64px;font-size:24px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700">
            <?= strtoupper(substr($user['name'],0,1)) ?>
          </div>
          <div>
            <div style="font-weight:600"><?= h($user['name']) ?></div>
            <div style="font-size:13px;color:var(--text-secondary)"><?= h($user['job_title'] ?: 'Job Hunter') ?></div>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="profile">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= h($user['name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Job Title</label>
              <input type="text" name="job_title" class="form-control" value="<?= h($user['job_title'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>" readonly style="background:#f8f9fb">
            <div class="form-text">Email cannot be changed.</div>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Password -->
    <div class="card mb-4">
      <div class="card-header"><span>Change Password</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="password">
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
          </div>
          <button type="submit" class="btn btn-outline-primary">Update Password</button>
        </form>
      </div>
    </div>

    <!-- Two-Factor Authentication -->
    <div class="card mb-4">
      <div class="card-header"><span>Two-Factor Authentication (2FA)</span></div>
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between py-3">
          <div>
            <div style="font-weight:500;font-size:14px;margin-bottom:4px">Google Authenticator</div>
            <div style="font-size:12px;color:var(--text-secondary)">
              <?php if (!empty($user['twofa_enabled']) && $user['twofa_enabled'] == 1): ?>
                <span style="color:var(--success)">✓ Enabled</span> - Your account is protected with 2FA
              <?php elseif (!empty($user['twofa_setup_required']) && $user['twofa_setup_required'] == 1): ?>
                <span style="color:#f59e0b">⏳ Setup Pending</span> - 2FA will be set up at next login
              <?php else: ?>
                Not yet enabled - Add extra security to your account
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <?php if (empty($user['twofa_enabled']) && empty($user['twofa_setup_required'])): ?>
        <form method="POST" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="2fa_setup">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-shield-lock me-1"></i>Enable 2FA</button>
        </form>
        <?php elseif (!empty($user['twofa_setup_required'])): ?>
        <div class="mt-3">
          <p style="font-size:12px;color:var(--text-secondary);margin:0">Setup will begin when you logout and login again.</p>
        </div>
        <?php else: ?>
        <form method="POST" class="mt-3" onsubmit="return confirm('Are you sure? You will need to use your authenticator app to log in.')">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="2fa_disable">
          <div class="mb-2">
            <label class="form-label">
              <small>Confirm your password to disable 2FA:</small>
            </label>
            <input type="password" name="confirmation_password" class="form-control form-control-sm" required>
          </div>
          <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Disable 2FA</button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notifications -->
    <div class="card">
      <div class="card-header"><span>Notification Preferences</span></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="notifications">
          <?php
          $notifs = [
              'email_alerts'        => ['Email Alerts', 'Notify me when application status changes', 'bi-envelope'],
              'interview_reminders' => ['Interview Reminders', 'Get reminders for upcoming interviews', 'bi-calendar-check'],
              'marketing_comms'     => ['Marketing Communications', 'Updates about new features and job trends', 'bi-megaphone'],
          ];
          foreach ($notifs as $key => [$title, $desc, $icon]):
          ?>
          <div class="d-flex align-items-center gap-3 py-3 border-bottom">
            <div style="width:36px;height:36px;border-radius:8px;background:var(--bg-page);display:flex;align-items:center;justify-content:center">
              <i class="bi <?= $icon ?>" style="color:var(--brand)"></i>
            </div>
            <div class="flex-grow-1">
              <div style="font-weight:500;font-size:14px"><?= $title ?></div>
              <div style="font-size:12px;color:var(--text-secondary)"><?= $desc ?></div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="<?= $key ?>" id="<?= $key ?>"
                     <?= !empty($user[$key]) ? 'checked' : '' ?> style="width:40px;height:22px">
            </div>
          </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary mt-3">Save Preferences</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Membership -->
    <div class="card mb-4" style="background:var(--brand-light);border-color:var(--brand)">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-star-fill text-warning"></i>
          <strong style="font-size:15px">Membership Status</strong>
        </div>
        <p style="font-size:13px;margin:0">
          You are currently a <strong style="color:var(--brand)"><?= ucfirst($user['plan']) ?> Member</strong>
        </p>
      </div>
    </div>

    <!-- Danger Zone -->
    <div class="card border-danger mb-3">
      <div class="card-header" style="color:var(--rejected)">
        <span><i class="bi bi-exclamation-triangle me-1"></i>Account Security & Safety</span>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center py-2">
          <div>
            <div style="font-weight:500;font-size:13px">Delete Account</div>
            <div style="font-size:12px;color:var(--text-secondary)">Permanently remove account and all data</div>
          </div>
          <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete()">Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$inlineScript = "function confirmDelete(){if(confirm('Are you sure? This cannot be undone. All your data will be permanently deleted.')){window.location='<?= APP_URL ?>/delete-account.php';}}";
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
