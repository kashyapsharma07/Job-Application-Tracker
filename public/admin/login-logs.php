<?php
require_once __DIR__ . '/../../src/bootstrap.php';
Auth::require();
Auth::requireAdmin();

// Note: This requires a login_logs table. See migration below.
$db = Database::getInstance();

// Try to get login logs if table exists
$logs = [];
try {
    $stmt = $db->prepare(
        'SELECT id, user_id, ip_address, login_time, status FROM login_logs 
         ORDER BY login_time DESC LIMIT 100'
    );
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    // Table doesn't exist yet
}

$pageTitle   = 'Login Activity';
$currentPage = 'admin';

ob_start();
?>
<div style="margin-bottom:30px">
  <h1 style="font-size:28px; margin-bottom:10px">Login Activity</h1>
  <p style="color:#9ca3af; margin:0">Monitor user authentication events</p>
</div>

<?php if (empty($logs)): ?>
<div class="alert alert-info">
  <strong>ℹ️ Login Logs</strong><br>
  To enable login tracking, run this SQL query in your database:
  <pre style="margin-top:10px; padding:10px; background:#f0f4f9; border-radius:4px; overflow-x:auto"><code>
CREATE TABLE IF NOT EXISTS login_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  ip_address VARCHAR(45),
  login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('success', 'failed', 'suspicious') DEFAULT 'success',
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
  </code></pre>
  Then add this to <code>Auth::login()</code> in Auth.php:
  <pre style="margin-top:10px; padding:10px; background:#f0f4f9; border-radius:4px; overflow-x:auto"><code>
Database::getInstance()->prepare(
  'INSERT INTO login_logs (user_id, ip_address, status) VALUES (?, ?, ?)'
)->execute([$user['id'], self::getClientIp(), 'success']);
  </code></pre>
</div>
<?php else: ?>

<div class="card">
  <div class="card-header">
    <span>Recent Login Activity</span>
  </div>
  <div class="card-body" style="padding:0">
    <table class="table table-hover" style="margin:0">
      <thead style="background:#f9fafb">
        <tr>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">User ID</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">IP Address</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Login Time</th>
          <th style="padding:12px 16px; font-weight:600; font-size:13px">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="padding:12px 16px"><?= $log['user_id'] ?></td>
          <td style="padding:12px 16px; font-family:monospace; font-size:12px"><?= h($log['ip_address']) ?></td>
          <td style="padding:12px 16px; font-size:13px; color:#9ca3af">
            <?= date('M d, Y · g:i A', strtotime($log['login_time'])) ?>
          </td>
          <td style="padding:12px 16px">
            <span class="badge 
              <?= $log['status'] === 'success' ? 'bg-success' : 
                  ($log['status'] === 'failed' ? 'bg-danger' : 'bg-warning') ?>" 
              style="font-size:11px">
              <?= ucfirst($log['status']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/partials/header.php';
echo $content;
include __DIR__ . '/../../views/partials/footer.php';
?>