<?php
require_once __DIR__ . '/../../src/bootstrap.php';
Auth::require();
Auth::requireAdmin();

$userModel = new User();
$users = $userModel->getAllUsers();

$pageTitle   = 'Manage Users';
$currentPage = 'admin';

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $userModel->delete((int)$_POST['user_id']);
        redirect('/admin/users.php?msg=deleted');
    }
}

ob_start();
?>
<div style="margin-bottom:30px">
  <h1 style="font-size:28px; margin-bottom:10px">Manage Users</h1>
  <p style="color:#9ca3af; margin:0">View and manage all user accounts</p>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="bi bi-check-circle"></i> User deleted successfully
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span><?= count($users) ?> User<?= count($users) !== 1 ? 's' : '' ?> Total</span>
  </div>
  <div class="card-body" style="padding:0">
    <table class="table table-hover" style="margin:0">
      <thead style="background:#f9fafb">
        <tr>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">ID</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Name</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Email</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Role</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Joined</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
        <tr>
          <td style="padding:12px 16px"><code style="font-size:12px; background:#f0f4f9; padding:2px 6px; border-radius:3px">#<?= h($user['id']) ?></code></td>
          <td style="padding:12px 16px"><?= h($user['name']) ?></td>
          <td style="padding:12px 16px; font-size:13px; color:#9ca3af"><?= h($user['email']) ?></td>
          <td style="padding:12px 16px">
            <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>" style="font-size:11px">
              <?= h(ucfirst($user['role'])) ?>
            </span>
          </td>
          <td style="padding:12px 16px; font-size:13px; color:#9ca3af">
            <?= h(date('M d, Y', strtotime($user['created_at']))) ?>
          </td>
          <td style="padding:12px 16px">
            <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= base64_encode($user['id']) ?>" class="btn btn-sm btn-outline-secondary" style="font-size:12px">View</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user and all their data? This cannot be undone.')">
              <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:12px">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/partials/header.php';
echo $content;
include __DIR__ . '/../../views/partials/footer.php';
?>