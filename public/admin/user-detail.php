<?php
require_once __DIR__ . '/../../src/bootstrap.php';
Auth::require();
Auth::requireAdmin();

$userId = (int)base64_decode($_GET['id'] ?? '');
$userModel = new User();

if ($userId <= 0) {
    redirect('/admin/users.php');
}

$user = $userModel->findById($userId);
if (!$user) {
    redirect('/admin/users.php?error=not_found');
}

// Handle role change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role'])) {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $newRole = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $userModel->updateRole($userId, $newRole);
        redirect('/admin/user-detail.php?id=' . base64_encode($userId) . '&msg=updated');
    }
}

// Get user stats
$appModel = new Application();
// Count apps for this user by querying database
$stmt = Database::getInstance()->prepare('SELECT COUNT(*) as count FROM applications WHERE user_id = ?');
$stmt->execute([$userId]);
$userAppCount = (int)($stmt->fetch()['count'] ?? 0);

$pageTitle   = 'User Details';
$currentPage = 'admin';

ob_start();
?>
<div style="margin-bottom:20px">
  <a href="<?= APP_URL ?>/admin/users.php" style="color:#1a73e8; text-decoration:none">← Back to Users</a>
</div>

<div class="row">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>User Information</span>
      </div>
      <div class="card-body">
        <div style="margin-bottom:15px">
          <label style="font-size:12px; color:#9ca3af; font-weight:600">Name</label>
          <div style="font-size:16px; margin-top:4px"><?= h($user['name']) ?></div>
        </div>

        <div style="margin-bottom:15px">
          <label style="font-size:12px; color:#9ca3af; font-weight:600">Email</label>
          <div style="font-size:14px; margin-top:4px; color:#1a1a2e"><?= h($user['email']) ?></div>
        </div>

        <div style="margin-bottom:15px">
          <label style="font-size:12px; color:#9ca3af; font-weight:600">Plan</label>
          <div style="font-size:14px; margin-top:4px"><?= ucfirst($user['plan']) ?> Plan</div>
        </div>

        <div style="margin-bottom:15px">
          <label style="font-size:12px; color:#9ca3af; font-weight:600">Joined</label>
          <div style="font-size:14px; margin-top:4px"><?= date('F d, Y at g:i A', strtotime($user['created_at'])) ?></div>
        </div>

        <div style="margin-bottom:15px">
          <label style="font-size:12px; color:#9ca3af; font-weight:600">Applications Created</label>
          <div style="font-size:14px; margin-top:4px"><?= $userAppCount ?> applications</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header">
        <span>Admin Actions</span>
      </div>
      <div class="card-body">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="alert alert-success" style="margin-bottom:15px">Role updated successfully</div>
        <?php endif; ?>

        <form method="POST" style="margin-bottom:20px">
          <div style="margin-bottom:15px">
            <label style="font-size:12px; color:#9ca3af; font-weight:600">User Role</label>
            <div style="margin-top:8px">
              <div style="display:flex; gap:15px">
                <label style="display:flex; align-items:center; cursor:pointer">
                  <input type="radio" name="role" value="user" <?= $user['role'] !== 'admin' ? 'checked' : '' ?> style="margin-right:6px">
                  <span>Regular User</span>
                </label>
                <label style="display:flex; align-items:center; cursor:pointer">
                  <input type="radio" name="role" value="admin" <?= $user['role'] === 'admin' ? 'checked' : '' ?> style="margin-right:6px">
                  <span>Admin</span>
                </label>
              </div>
            </div>
          </div>

          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <button type="submit" class="btn btn-primary" style="width:100%; margin-bottom:10px">Update Role</button>
        </form>

        <form method="POST" action="/admin/users.php" style="display:contents;">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
          <button type="submit" class="btn btn-danger" style="width:100%" 
                  onclick="return confirm('Delete this user and all their data? This cannot be undone.');">
            Delete User
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/partials/header.php';
echo $content;
include __DIR__ . '/../../views/partials/footer.php';
?>
