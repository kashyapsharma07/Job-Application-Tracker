<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();
$userId = Auth::id();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Invalid application ID.');
}

$appId = (int)base64_decode($_GET['id']);
$applicationModel = new Application();
$application = $applicationModel->getById($appId, $userId);

if (!$application) {
    die('Application not found or access denied.');
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_title = trim($_POST['job_title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $applied_at = trim($_POST['applied_at'] ?? '');
    $job_url = trim($_POST['job_url'] ?? '');
    $salary_range = trim($_POST['salary_range'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$job_title || !$company || !$status || !$applied_at) {
        $error = 'All fields are required.';
    } else {
        $applicationModel->update($appId, $userId, [
            'job_title' => $job_title,
            'company' => $company,
            'status' => $status,
            'applied_at' => $applied_at,
            'job_url' => $job_url,
            'salary_range' => $salary_range,
            'notes' => $notes
        ]);
        $success = 'Application updated successfully!';
        // Refresh application data
        $application = $applicationModel->getById($appId, $userId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2>Edit Application</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?= $error ?> </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"> <?= $success ?> </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Job Title</label>
                <input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($application['job_title']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Company</label>
                <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($application['company']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control" required>
                    <?php $statuses = ['wishlist', 'applied', 'interviewing', 'offer', 'rejected']; ?>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $application['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Applied At</label>
                <input type="date" name="applied_at" class="form-control" value="<?= htmlspecialchars(date('Y-m-d', strtotime($application['applied_at']))) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Job URL</label>
                <input type="text" name="job_url" class="form-control" value="<?= htmlspecialchars($application['job_url'] ?? '') ?>" placeholder="e.g., https://example.com/job">
            </div>
            <div class="mb-3">
                <label class="form-label">Salary Range</label>
                <input type="text" name="salary_range" class="form-control" value="<?= htmlspecialchars($application['salary_range'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control"><?= htmlspecialchars($application['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="/jobtracker/applications.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
</body>

</html>