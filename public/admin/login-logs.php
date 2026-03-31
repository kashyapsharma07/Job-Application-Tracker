<?php
require_once __DIR__ . '/../../src/bootstrap.php';
Auth::require();
Auth::requireAdmin();

// Note: This requires a login_logs table. See migration below.

$db = Database::getInstance();

// Pagination settings
$perPage = 10; // logs per page
$pagesPerGroup = 10; // how many page numbers to show at once
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Get total log count
$totalLogs = 0;
try {
  $stmt = $db->query('SELECT COUNT(*) FROM login_logs');
  $totalLogs = (int)$stmt->fetchColumn();
} catch (Exception $e) {
  // Table doesn't exist yet
}

$totalPages = $totalLogs > 0 ? (int)ceil($totalLogs / $perPage) : 1;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Calculate current page group (set of 10 pages)
$currentGroup = (int)floor(($page - 1) / $pagesPerGroup);
$groupStart = $currentGroup * $pagesPerGroup + 1;
$groupEnd = min($groupStart + $pagesPerGroup - 1, $totalPages);

// Fetch logs for current page
$logs = [];
if ($totalLogs > 0) {
  try {
    $stmt = $db->prepare(
      'SELECT id, user_id, ip_address, login_time, status FROM login_logs 
       ORDER BY login_time DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
  } catch (Exception $e) {
    // Table doesn't exist yet
  }
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

    <!-- Pagination Controls -->
    <div style="padding:20px 0 0 0; text-align:center">
      <?php if ($totalPages > 1): ?>
        <nav aria-label="Login log pagination">
          <ul class="pagination" style="display:inline-flex; gap:2px; list-style:none; padding:0; margin:0">
            <!-- Prev group arrow -->
            <li>
              <a href="?page=<?= max(1, $groupStart - $pagesPerGroup) ?>" class="page-link" style="padding:6px 12px;<?= $groupStart == 1 ? 'pointer-events:none;opacity:0.5;' : '' ?>">&laquo;</a>
            </li>
            <?php for ($i = $groupStart; $i <= $groupEnd; $i++): ?>
              <li>
                <a href="?page=<?= $i ?>" class="page-link<?= $i == $page ? ' active' : '' ?>" style="padding:6px 12px;<?= $i == $page ? 'background:#2563eb;color:#fff;border-radius:3px;' : '' ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
            <!-- Next group arrow -->
            <li>
              <a href="?page=<?= min($totalPages, $groupEnd + 1) ?>" class="page-link" style="padding:6px 12px;<?= $groupEnd == $totalPages ? 'pointer-events:none;opacity:0.5;' : '' ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/partials/header.php';
echo $content;
include __DIR__ . '/../../views/partials/footer.php';
?>