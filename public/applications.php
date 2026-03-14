<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();
$resumeModel = new Resume();
$resumes  = $resumeModel->getAll($userId);

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    json_response(['error' => 'CSRF error'], 403);
  }
  $action = $_POST['action'];
  $id     = (int)($_POST['id'] ?? 0);

  if ($action === 'delete') {
    $appModel->delete($id, $userId);
    json_response(['ok' => true]);
  }
  if ($action === 'update_status') {
    $status = $_POST['status'] ?? '';
    $appModel->updateStatus($id, $userId, $status);
    json_response(['ok' => true]);
  }
  if ($action === 'create' || $action === 'update') {
    $data = [
      'company'      => trim($_POST['company'] ?? ''),
      'job_title'    => trim($_POST['job_title'] ?? ''),
      'job_url'      => trim($_POST['job_url'] ?? ''),
      'job_type'     => $_POST['job_type'] ?? 'not_specified',
      'status'       => $_POST['status'] ?? 'wishlist',
      'salary_range' => trim($_POST['salary_range'] ?? ''),
      'resume_id'    => !empty($_POST['resume_id']) ? (int)$_POST['resume_id'] : null,
      'notes'        => trim($_POST['notes'] ?? ''),
      'applied_at'   => !empty($_POST['applied_at']) ? $_POST['applied_at'] : null,
    ];
    if (empty($data['company']) || empty($data['job_title'])) {
      json_response(['error' => 'Company and job title are required.'], 422);
    }
    if ($action === 'create') {
      $newId = $appModel->create($userId, $data);
      json_response(['ok' => true, 'id' => $newId]);
    } else {
      $appModel->update($id, $userId, $data);
      json_response(['ok' => true]);
    }
  }
}

$filters = [
  'status' => $_GET['status'] ?? '',
  'search' => $_GET['search'] ?? '',
];
$apps   = $appModel->getAll($userId, $filters);
$counts = $appModel->countByStatus($userId);

$pageTitle   = 'Applications';
$currentPage = 'applications';
$openNew     = ($_GET['action'] ?? '') === 'new';
$success     = $_GET['success'] ?? '';

ob_start();
?>
<!-- Success Message -->
<?php if ($success === 'created'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom:16px;">
  <i class="bi bi-check-circle me-2"></i>
  <strong>Success!</strong> New application added successfully.
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php elseif ($success === 'updated'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert" style="margin-bottom:16px;">
  <i class="bi bi-check-circle me-2"></i>
  <strong>Success!</strong> Application updated successfully.
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 style="font-size:22px;margin:0">Applications</h1>
    <p class="text-muted mb-0" style="font-size:13px"><?= array_sum($counts) ?> total applications</p>
  </div>
  <div class="d-flex gap-2">
    <div class="btn-group btn-group-sm" id="viewToggle">
      <button class="btn btn-outline-secondary active" data-view="kanban"><i class="bi bi-kanban"></i></button>
      <button class="btn btn-outline-secondary" data-view="list"><i class="bi bi-list-ul"></i></button>
    </div>
    <button class="btn btn-primary btn-sm" id="btnNewApp">
      <i class="bi bi-plus-lg"></i> Add Application
    </button>
  </div>
</div>

<!-- Status filter tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <a href="applications.php" class="btn btn-sm <?= empty($filters['status']) ? 'btn-dark' : 'btn-outline-secondary' ?>">
    All <span class="badge bg-secondary ms-1"><?= array_sum($counts) ?></span>
  </a>
  <?php foreach (['wishlist', 'applied', 'interviewing', 'offer', 'rejected'] as $s): ?>
    <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filters['status'] === $s ? 'btn-dark' : 'btn-outline-secondary' ?>">
      <span class="col-dot dot-<?= $s ?> me-1" style="display:inline-block;width:7px;height:7px;border-radius:50%;vertical-align:middle"></span>
      <?= ucfirst($s) ?> <span class="badge bg-secondary ms-1"><?= $counts[$s] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- KANBAN VIEW -->
<div id="kanbanView">
  <div class="kanban-board">
    <?php
    $statuses = ['wishlist', 'applied', 'interviewing', 'offer', 'rejected'];
    foreach ($statuses as $s):
      $colApps = array_filter($apps, fn($a) => $a['status'] === $s);
    ?>
      <div class="kanban-col" data-status="<?= $s ?>">
        <div class="kanban-col-header">
          <span class="col-dot dot-<?= $s ?>"></span>
          <?= strtoupper($s) ?>
          <span class="col-count"><?= count($colApps) ?></span>
        </div>
        <div class="kanban-cards">
          <?php foreach ($colApps as $app): ?>
            <div class="app-card" data-id="<?= $app['id'] ?>">
              <span class="app-status-badge badge badge-<?= $app['status'] ?>"><?= h(statusLabel($app['status'])) ?></span>
              <div class="company-logo"><?= strtoupper(substr($app['company'], 0, 1)) ?></div>
              <div class="job-title"><?= h($app['job_title']) ?></div>
              <div class="company-name"><?= h($app['company']) ?></div>
              <div class="card-footer-row">
                <i class="bi bi-calendar3"></i>
                <?= $app['applied_at'] ? date('M d', strtotime($app['applied_at'])) : 'No date' ?>
                <div class="ms-auto d-flex gap-1">
                  <a href="edit-application.php?id=<?= base64_encode($app['id']) ?>" class="btn btn-sm p-0 px-1" title="Edit"><i class="bi bi-pencil" style="font-size:12px"></i></a>
                  <button class="btn btn-sm p-0 px-1 btn-view" data-id="<?= $app['id'] ?>" title="View"><i class="bi bi-eye" style="font-size:12px"></i></button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($colApps)): ?>
            <div class="text-center text-muted py-3" style="font-size:13px">No applications</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- LIST VIEW -->
<div id="listView" style="display:none">
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Company & Role</th>
            <th>Status</th>
            <th>Type</th>
            <th>Salary</th>
            <th>Applied</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($apps)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">No applications found.</td>
            </tr>
          <?php endif; ?>
          <?php foreach ($apps as $app): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div style="width:32px;height:32px;border-radius:8px;background:var(--bg-page);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--brand)"><?= strtoupper(substr($app['company'], 0, 1)) ?></div>
                  <div>
                    <div style="font-weight:600"><?= h($app['job_title']) ?></div>
                    <div style="font-size:12px;color:var(--text-secondary)"><?= h($app['company']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge rounded-pill badge-<?= $app['status'] ?>"><?= h(statusLabel($app['status'])) ?></span></td>
              <td style="font-size:13px"><?= h(ucfirst(str_replace('_', ' ', $app['job_type']))) ?></td>
              <td style="font-size:13px"><?= h($app['salary_range'] ?: '—') ?></td>
              <td style="font-size:13px"><?= $app['applied_at'] ? date('M d, Y', strtotime($app['applied_at'])) : '—' ?></td>
              <td>
                <div class="d-flex gap-1">
                  <a href="<?= APP_URL ?>/application-detail.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="bi bi-eye"></i></a>
                  <a href="edit-application.php?id=<?= base64_encode($app['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                  <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete" data-id="<?= $app['id'] ?>"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Application Modal -->
<div class="modal fade" id="appModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" style="font-family:'Sora',sans-serif" id="modalTitle">New Application</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="appForm">
          <input type="hidden" name="csrf_token" id="csrfToken" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" id="formAction" value="create">
          <input type="hidden" name="id" id="formId" value="">

          <div class="d-flex gap-2 align-items-center mb-3">
            <i class="bi bi-briefcase text-primary"></i>
            <strong>Company & Role</strong>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Company Name *</label>
              <input type="text" name="company" id="f_company" class="form-control" required placeholder="e.g. Google, Stripe">
            </div>
            <div class="col-md-6">
              <label class="form-label">Job Title *</label>
              <input type="text" name="job_title" id="f_job_title" class="form-control" required placeholder="e.g. Senior Product Designer">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Description URL</label>
            <input type="url" name="job_url" id="f_job_url" class="form-control" placeholder="https://careers.company.com/job/123">
          </div>

          <hr>
          <div class="d-flex gap-2 align-items-center mb-3">
            <i class="bi bi-grid text-primary"></i>
            <strong>Logistics</strong>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Job Type</label>
              <select name="job_type" id="f_job_type" class="form-select">
                <option value="remote">Remote</option>
                <option value="hybrid">Hybrid</option>
                <option value="onsite">On-site</option>
                <option value="not_specified">Not Specified</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" id="f_status" class="form-select">
                <option value="wishlist">Wishlist</option>
                <option value="applied">Applied</option>
                <option value="interviewing">Interviewing</option>
                <option value="offer">Offer</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Salary Range (Est.)</label>
              <input type="text" name="salary_range" id="f_salary" class="form-control" placeholder="e.g. $120k–$150k">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Applied Date</label>
              <input type="date" name="applied_at" id="f_applied_at" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Resume Version</label>
              <select name="resume_id" id="f_resume" class="form-select">
                <option value="">— Select resume —</option>
                <?php foreach ($resumes as $r): ?>
                  <option value="<?= $r['id'] ?>"><?= h($r['version_label'] ?: $r['original_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <hr>
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-journal-text text-primary me-1"></i> Notes</label>
            <textarea name="notes" id="f_notes" class="form-control" rows="4"
              placeholder="Mention key keywords, referral names, or why you're interested..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveApp">Save Application</button>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

// Store all apps as JSON for JS
$appsJson = json_encode(array_values($apps));

$inlineScript = <<<JS
const APPS_DATA = $appsJson;
const APP_URL = '<?= APP_URL ?>';
initApplicationsPage();
JS;

if ($openNew) {
  $inlineScript .= "\ndocument.addEventListener('DOMContentLoaded',()=>document.getElementById('btnNewApp').click());";
}

include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
