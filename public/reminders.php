<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId      = Auth::id();
$remModel    = new Reminder();
$appModel    = new Application();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/reminders.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $remModel->create($userId, [
            'application_id' => !empty($_POST['application_id']) ? (int)$_POST['application_id'] : null,
            'title'          => trim($_POST['title']),
            'description'    => trim($_POST['description'] ?? ''),
            'remind_at'      => $_POST['remind_at'],
        ]);
        flash('success', 'Reminder created.');
    }
    if ($action === 'delete') {
        $remModel->delete((int)$_POST['reminder_id'], $userId);
        flash('success', 'Reminder deleted.');
    }
    redirect('/reminders.php');
}

$reminders   = $remModel->getAll($userId);
$apps        = $appModel->getAll($userId);
$pageTitle   = 'Reminders';
$currentPage = 'reminders';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 style="font-size:22px;margin:0">Reminders</h1>
    <p class="text-muted mb-0" style="font-size:13px">Follow-up reminders & deadlines</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reminderModal">
    <i class="bi bi-plus-lg me-1"></i> New Reminder
  </button>
</div>

<?php if (empty($reminders)): ?>
<div class="card text-center py-5">
  <i class="bi bi-bell text-muted" style="font-size:48px"></i>
  <h3 class="mt-3" style="font-size:18px">No reminders yet</h3>
  <p class="text-muted">Set reminders for interviews, follow-ups and deadlines.</p>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reminderModal">Create Reminder</button>
</div>
<?php else: ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr>
        <th class="ps-3">Title</th>
        <th>Application</th>
        <th>Remind At</th>
        <th>Status</th>
        <th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach ($reminders as $r): ?>
        <tr>
          <td class="ps-3">
            <div style="font-weight:600"><?= h($r['title']) ?></div>
            <?php if ($r['description']): ?>
            <div style="font-size:12px;color:var(--text-secondary)"><?= h(substr($r['description'],0,60)) ?>...</div>
            <?php endif; ?>
          </td>
          <td style="font-size:13px">
            <?= $r['company'] ? h($r['company'] . ' — ' . $r['job_title']) : '—' ?>
          </td>
          <td style="font-size:13px">
            <?= date('M j, Y g:i A', strtotime($r['remind_at'])) ?>
          </td>
          <td>
            <?php if ($r['sent']): ?>
            <span class="badge bg-success">Sent</span>
            <?php elseif (strtotime($r['remind_at']) < time()): ?>
            <span class="badge bg-warning text-dark">Overdue</span>
            <?php else: ?>
            <span class="badge bg-primary">Pending</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Delete this reminder?')">
              <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="reminder_id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Reminder Modal -->
<div class="modal fade" id="reminderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Follow up with HR">
          </div>
          <div class="mb-3">
            <label class="form-label">Application (optional)</label>
            <select name="application_id" class="form-select" id="reminderAppSelect">
              <option value="">— No application —</option>
              <?php foreach ($apps as $app): ?>
              <option value="<?= $app['id'] ?>" data-applied-at="<?= $app['applied_at'] ?>"><?= h($app['company'] . ' — ' . $app['job_title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Remind At *</label>
            <input type="datetime-local" name="remind_at" class="form-control" required
                   min="<?= date('Y-m-d\TH:i') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Reminder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-fill Remind At field when application is selected
document.getElementById('reminderAppSelect').addEventListener('change', function() {
  const selectedOption = this.options[this.selectedIndex];
  const appliedAt = selectedOption.getAttribute('data-applied-at');
  const remindAtField = document.querySelector('input[name="remind_at"]');
  
  if (appliedAt && appliedAt.trim()) {
    // Convert date format from YYYY-MM-DD to YYYY-MM-DDTHH:MM for datetime-local input
    const dateTime = appliedAt.split('T')[0] + 'T09:00'; // Set to 9 AM on that day
    remindAtField.value = dateTime;
  } else {
    remindAtField.value = '';
  }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
