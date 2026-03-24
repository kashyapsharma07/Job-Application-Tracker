<?php
require_once __DIR__ . '/../../src/bootstrap.php';
Auth::require();
Auth::requireAdmin();

$userModel = new User();

// Get system stats
$totalUsers = $userModel->getTotalUsers();
$appModel = new Application();
$allApps = $appModel->countByStatus(0); // Get all apps count (simplified)

$pageTitle   = 'Admin Dashboard';
$currentPage = 'admin';

ob_start();
?>
<div style="margin-bottom:30px">
  <h1 style="font-size:28px; margin-bottom:10px">Admin Dashboard</h1>
  <p style="color:#9ca3af; margin:0">System overview and management</p>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:32px; font-weight:700; color:#1a73e8; margin-bottom:10px"><?= $totalUsers ?></div>
        <div style="font-size:14px; color:#9ca3af">Total Users</div>
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:32px; font-weight:700; color:#10b981; margin-bottom:10px">
          <?php 
            $userModel = new User();
            $admins = count(array_filter($userModel->getAllUsers(), function($u) { return $u['role'] === 'admin'; }));
            echo $admins;
          ?>
        </div>
        <div style="font-size:14px; color:#9ca3af">Admin Users</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:32px; font-weight:700; color:#f59e0b; margin-bottom:10px"><?= count($userModel->getAllUsers()) > 0 ? floor(count(array_filter($userModel->getAllUsers(), function($u) { return $u['role'] === 'admin'; })) / $totalUsers * 100) : 0 ?>%</div>
        <div style="font-size:14px; color:#9ca3af">Admin Percentage</div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:32px; font-weight:700; color:#ef4444; margin-bottom:10px">
          <?php 
            $activeUsers = count(array_filter($userModel->getAllUsers(), function($u) { 
              return strtotime($u['created_at']) > strtotime('-30 days'); 
            }));
            echo $activeUsers;
          ?>
        </div>
        <div style="font-size:14px; color:#9ca3af">Active (30 days)</div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <span>Quick Actions</span>
      </div>
      <div class="card-body" style="display:flex; gap:10px; flex-wrap:wrap">
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-primary">
          <i class="bi bi-people"></i> Manage Users
        </a>
        <a href="<?= APP_URL ?>/admin/login-logs.php" class="btn btn-secondary">
          <i class="bi bi-clock-history"></i> View Login Logs
        </a>
        <a href="<?= APP_URL ?>/applications.php" class="btn btn-outline-secondary">
          <i class="bi bi-folder2-open"></i> View All Applications
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Recent Users -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <span>Recent Users</span>
      </div>
      <div class="card-body" style="padding:0">
        <table class="table table-hover" style="margin:0">
          <thead style="background:#f9fafb">
            <tr>
              <th style="padding:12px 16px; font-weight:600; font-size:13px">Name</th>
              <th style="padding:12px 16px; font-weight:600; font-size:13px">Email</th>
              <th style="padding:12px 16px; font-weight:600; font-size:13px">Role</th>
              <th style="padding:12px 16px; font-weight:600; font-size:13px">Joined</th>
              <th style="padding:12px 16px; font-weight:600; font-size:13px">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $recentUsers = array_slice($userModel->getAllUsers(), 0, 5); ?>
            <?php foreach ($recentUsers as $user): ?>
            <tr>
              <td style="padding:12px 16px"><?= h($user['name']) ?></td>
              <td style="padding:12px 16px"><?= h($user['email']) ?></td>
              <td style="padding:12px 16px">
                <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>" style="font-size:11px">
                  <?= ucfirst($user['role']) ?>
                </span>
              </td>
              <td style="padding:12px 16px; font-size:13px; color:#9ca3af">
                <?= date('M d, Y', strtotime($user['created_at'])) ?>
              </td>
              <td style="padding:12px 16px">
                <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= base64_encode($user['id']) ?>" class="btn btn-sm btn-outline-secondary" style="font-size:12px">View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
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
