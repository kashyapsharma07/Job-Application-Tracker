<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId      = Auth::id();
$resumeModel = new Resume();

// Refresh premium status from database
$userModel = new User();
$freshUser = $userModel->findById($userId);
if ($freshUser && isset($freshUser['is_premium'])) {
    $_SESSION['user_is_premium'] = (bool)$freshUser['is_premium'];
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        redirect('/resumes.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && !empty($_FILES['resume_file']['name'])) {
      $file = $_FILES['resume_file'];
      if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Upload error. Try again.');
      } elseif ($file['size'] > UPLOAD_MAX_SIZE) {
        flash('error', 'File too large. Max 10MB.');
      } elseif (!in_array($file['type'], ALLOWED_MIME_TYPES)) {
        flash('error', 'Only PDF and DOCX files are allowed.');
      } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Only allow pdf and docx extensions
        if (!in_array($ext, ['pdf', 'docx'])) {
          flash('error', 'Invalid file extension. Only PDF and DOCX allowed.');
        } elseif (preg_match('/\.(php|exe|sh|js|pl|py|rb|jsp|asp|aspx|bat|cmd|com|dll|vbs|wsf|csh|ksh|bash|zsh|fish|cgi)$/i', $file['name'])) {
          flash('error', 'Invalid file name.');
        } else {
          $filename = 'resume_' . $userId . '_' . uniqid() . '.' . $ext;
          $dest     = UPLOAD_PATH . $filename;
          if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Set file permissions to 0644 (not executable)
            @chmod($dest, 0644);
            $resumeModel->create($userId, [
              'filename'      => $filename,
              'original_name' => $file['name'],
              'file_size'     => $file['size'],
              'version_label' => trim($_POST['version_label'] ?? ''),
              'is_default'    => !empty($_POST['is_default']) ? 1 : 0,
            ]);
            flash('success', 'Resume uploaded successfully.');
          } else {
            flash('error', 'Failed to save file. Check upload directory permissions.');
          }
        }
      }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['resume_id'] ?? 0);
        $filename = $resumeModel->delete($id, $userId);
        if ($filename) {
            @unlink(UPLOAD_PATH . $filename);
            flash('success', 'Resume deleted.');
        }
    }

    if ($action === 'set_default') {
        $resumeModel->setDefault((int)$_POST['resume_id'], $userId);
        flash('success', 'Default resume updated.');
    }

    redirect('/resumes.php');
}

$resumes     = $resumeModel->getAll($userId);
$pageTitle   = 'Resumes';
$currentPage = 'resumes';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 style="font-size:22px;margin:0">Resume Versions</h1>
    <p class="text-muted mb-0" style="font-size:13px"><?= count($resumes) ?> version<?= count($resumes)!=1?'s':'' ?> uploaded</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
    <i class="bi bi-upload me-1"></i> Upload Resume
  </button>
</div>

<?php if (empty($resumes)): ?>
<div class="card text-center py-5">
  <i class="bi bi-file-earmark-person text-muted" style="font-size:48px"></i>
  <h3 class="mt-3" style="font-size:18px">No resumes yet</h3>
  <p class="text-muted">Upload your resume versions to link them to applications.</p>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload First Resume</button>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($resumes as $r): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card h-100" style="<?= $r['is_default']?'border-color:var(--brand);border-width:2px':'' ?>">
      <div class="card-body">
        <?php if ($r['is_default']): ?>
        <span class="badge bg-primary mb-2">Default</span>
        <?php endif; ?>
        <div class="d-flex gap-3 align-items-start">
          <div style="font-size:36px;color:#dc2626;line-height:1;min-width:36px"><i class="bi bi-file-earmark-pdf"></i></div>
          <div class="flex-grow-1">
            <div style="font-weight:600;font-size:14px;word-break:break-all"><?= h($r['original_name']) ?></div>
            <?php if ($r['version_label']): ?>
            <div class="badge bg-light text-dark border mt-1"><?= h($r['version_label']) ?></div>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:4px">
              <?= $r['file_size'] ? round($r['file_size']/1024) . ' KB' : '' ?>
              &bull; Uploaded <?= date('M j, Y', strtotime($r['created_at'])) ?>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer bg-transparent d-flex gap-2">
        <a href="<?= APP_URL ?>/uploads/resumes/<?= h($r['filename']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary flex-fill">
          <i class="bi bi-eye me-1"></i>View
        </a>
        <?php if (!$r['is_default']): ?>
        <form method="POST" class="flex-fill">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="set_default">
          <input type="hidden" name="resume_id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-primary w-100">Set Default</button>
        </form>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Delete this resume?')">
          <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="resume_id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Upload Resume</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="action" value="upload">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Version Label</label>
            <input type="text" name="version_label" class="form-control" placeholder="e.g. Software Engineer v2, Design-focused">
          </div>
          <div class="mb-3">
            <label class="form-label">Resume File *</label>
            <div class="dropzone" id="dropzone">
              <i class="bi bi-cloud-upload" style="font-size:32px;color:var(--text-secondary)"></i>
              <div class="mt-2"><strong style="color:var(--brand)">Click to upload</strong> or drag and drop</div>
              <div style="font-size:12px;color:var(--text-secondary)">PDF, DOCX up to 10MB</div>
              <input type="file" name="resume_file" id="resumeFile" accept=".pdf,.docx" style="display:none" required>
              <div id="fileName" class="mt-2 text-muted" style="font-size:13px"></div>
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
            <label class="form-check-label" for="isDefault" style="font-size:13px">Set as default resume</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Upload Resume</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if (!empty($resumes)): ?>
<!-- AI Career Coach Panel (Premium Feature) -->
<div class="mt-5">
  <?php 
    $userId = Auth::id();
    $currentUser = Auth::user();
    $isPremium = (bool)($currentUser['is_premium'] ?? false);
    include __DIR__ . '/../html_snippet_ai_coach_panel.php'; 
  ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$inlineScript = "initResumePage();";
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
