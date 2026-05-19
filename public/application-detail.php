<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();
$id       = (int)($_GET['id'] ?? 0);
$app      = $appModel->getById($id, $userId);

if (!$app) {
    flash('error', 'Application not found.');
    redirect('/applications.php');
}

$events = $appModel->getEvents($id);

// Handle add event POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    if (Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $eventType = $_POST['event_type'] ?? '';
        $validEventTypes = ['phone_call', 'interview', 'email', 'follow_up', 'offer', 'rejection', 'note'];
        if (!in_array($eventType, $validEventTypes, true)) {
            flash('error', 'Invalid event type.');
            redirect(APP_URL . '/application-detail.php?id=' . $id);
        }
        $appModel->addEvent(
            $id,
            $eventType,
            trim($_POST['event_title'] ?? ''),
            trim($_POST['event_desc'] ?? ''),
            !empty($_POST['event_date']) ? $_POST['event_date'] : null
        );
        flash('success', 'Event added.');
        redirect(APP_URL . '/application-detail.php?id=' . $id);
    }
}

$pageTitle   = h($app['job_title']) . ' at ' . h($app['company']);
$currentPage = 'applications';

ob_start();
?>
<div class="mb-4">
  <a href="<?= APP_URL ?>/applications.php" class="text-decoration-none text-muted" style="font-size:13px">
    <i class="bi bi-arrow-left me-1"></i> Back to Applications
  </a>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Header Card -->
    <div class="card mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-start gap-3">
          <div style="width:52px;height:52px;border-radius:12px;background:var(--bg-page);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--brand)">
            <?= strtoupper(substr($app['company'],0,1)) ?>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h2 style="font-size:20px;margin:0"><?= h($app['job_title']) ?></h2>
              <span class="badge rounded-pill badge-<?= $app['status'] ?> ms-1"><?= h(statusLabel($app['status'])) ?></span>
            </div>
            <div class="text-muted" style="font-size:14px"><?= h($app['company']) ?></div>
            <div class="d-flex gap-3 mt-2 flex-wrap" style="font-size:13px;color:var(--text-secondary)">
              <?php if ($app['job_type'] !== 'not_specified'): ?>
              <span><i class="bi bi-geo-alt me-1"></i><?= h(ucfirst(str_replace('_',' ',$app['job_type']))) ?></span>
              <?php endif; ?>
              <?php if ($app['salary_range']): ?>
              <span><i class="bi bi-currency-dollar me-1"></i><?= h($app['salary_range']) ?></span>
              <?php endif; ?>
              <?php if ($app['applied_at']): ?>
              <span><i class="bi bi-calendar3 me-1"></i>Applied <?= date('M j, Y', strtotime($app['applied_at'])) ?></span>
              <?php endif; ?>
              <?php if ($app['job_url']): ?>
              <a href="<?= h($app['job_url']) ?>" target="_blank" style="color:var(--brand)"><i class="bi bi-box-arrow-up-right me-1"></i>Job Posting</a>
              <?php endif; ?>
            </div>
          </div>
          <a href="#" class="btn btn-sm btn-outline-primary"
             onclick="localStorage.setItem('editApp','<?= $app['id'] ?>');window.location.href=window.APP_URL+'/applications.php';return false;">
            <i class="bi bi-pencil me-1"></i> Edit
          </a>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <?php if ($app['notes']): ?>
    <div class="card mb-4">
      <div class="card-header"><span><i class="bi bi-journal-text me-2"></i>Notes</span></div>
      <div class="card-body" style="font-size:14px;white-space:pre-wrap"><?= h($app['notes']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Timeline -->
    <div class="card">
      <div class="card-header">
        <span><i class="bi bi-clock-history me-2"></i>Timeline</span>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
          <i class="bi bi-plus-lg me-1"></i>Add Event
        </button>
      </div>
      <div class="card-body">
        <?php if (empty($events)): ?>
        <p class="text-muted text-center py-3" style="font-size:13px">No events yet.</p>
        <?php else: ?>
        <div class="timeline">
          <?php foreach ($events as $ev): ?>
          <div class="timeline-item">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--brand);margin-bottom:2px">
              <?= h(str_replace('_',' ',strtoupper($ev['event_type']))) ?>
              <span class="text-muted fw-normal ms-2"><?= date('M j, Y', strtotime($ev['event_date'])) ?></span>
            </div>
            <div style="font-weight:600;font-size:14px"><?= h($ev['title']) ?></div>
            <?php if ($ev['description']): ?>
            <div style="font-size:13px;color:var(--text-secondary);margin-top:2px"><?= h($ev['description']) ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Resume -->
    <div class="card mb-4">
      <div class="card-header"><span><i class="bi bi-file-earmark-person me-2"></i>Resume Used</span></div>
      <div class="card-body">
        <?php if ($app['resume_name']): ?>
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-pdf text-danger" style="font-size:24px"></i>
          <div>
            <div style="font-weight:600;font-size:13px"><?= h($app['resume_name']) ?></div>
            <?php if ($app['resume_label']): ?>
            <div style="font-size:12px;color:var(--text-secondary)"><?= h($app['resume_label']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0" style="font-size:13px">No resume linked.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick status update -->
    <div class="card">
      <div class="card-header"><span>Update Status</span></div>
      <div class="card-body">
        <?php foreach (['wishlist','applied','interviewing','offer','rejected'] as $s): ?>
        <button class="btn btn-sm w-100 mb-2 text-start d-flex align-items-center gap-2 <?= $app['status']===$s ? 'btn-primary' : 'btn-outline-secondary' ?>"
                onclick="quickStatus(<?= $app['id'] ?>,'<?= $s ?>')">
          <span class="col-dot dot-<?= $s ?>" style="width:8px;height:8px;border-radius:50%;display:inline-block"></span>
          <?= ucfirst($s) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="add_event" value="1">
        <div class="modal-header"><h5 class="modal-title">Add Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Event Type</label>
            <select name="event_type" class="form-select" required>
              <option value="phone_call">Phone Call</option>
              <option value="interview">Interview</option>
              <option value="email">Email</option>
              <option value="follow_up">Follow-up</option>
              <option value="offer">Offer</option>
              <option value="rejection">Rejection</option>
              <option value="note">Note</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="event_title" class="form-control" required placeholder="e.g. Phone Screen with HR">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="event_desc" class="form-control" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Date & Time</label>
            <input type="datetime-local" name="event_date" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Event</button>
        </div>
    </div>
      <?php
      $content = ob_get_clean();
      include __DIR__ . '/../views/partials/header.php';
      echo $content;
?>
<input type="hidden" id="csrfToken" value="<?= Auth::csrfToken() ?>">
<?php
      include __DIR__ . '/../views/partials/footer.php';