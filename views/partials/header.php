<?php
// views/partials/header.php
$flash = getFlash();
$currentUser = Auth::user();
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? 'JobTracker') ?> — JobTracker</title>
<link rel="icon" href="<?= APP_URL ?>/img/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= APP_URL ?>/css/app.css" rel="stylesheet">
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="<?= APP_URL ?>/dashboard.php" class="sidebar-brand" style="text-decoration:none">
      <div class="brand-icon"><i class="bi bi-briefcase-fill"></i></div>
      <span class="brand-name">JobTracker</span>
    </a>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
        <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/applications.php" class="nav-item <?= $currentPage==='applications'?'active':'' ?>">
        <i class="bi bi-folder2-open"></i> <span>Applications</span>
      </a>
      <a href="<?= APP_URL ?>/calendar.php" class="nav-item <?= $currentPage==='calendar'?'active':'' ?>">
        <i class="bi bi-calendar3"></i> <span>Calendar</span>
      </a>
      <a href="<?= APP_URL ?>/analytics.php" class="nav-item <?= $currentPage==='analytics'?'active':'' ?>">
        <i class="bi bi-bar-chart-line"></i> <span>Analytics</span>
      </a>
      <a href="<?= APP_URL ?>/resumes.php" class="nav-item <?= $currentPage==='resumes'?'active':'' ?>">
        <i class="bi bi-file-earmark-person"></i> <span>Resumes</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="<?= APP_URL ?>/settings.php" class="nav-item <?= $currentPage==='settings'?'active':'' ?>">
        <i class="bi bi-gear"></i> <span>Settings</span>
      </a>
      <div class="user-card">
        <div class="user-avatar">
          <?php if (!empty($currentUser['avatar'])): ?>
            <img src="<?= APP_URL ?>/uploads/avatars/<?= h($currentUser['avatar']) ?>" alt="Avatar">
          <?php else: ?>
            <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
          <?php endif; ?>
        </div>
        <div class="user-info">
          <span class="user-name"><?= h($currentUser['name']) ?></span>
          <span class="user-plan"><?= ucfirst($currentUser['plan']) ?> Plan</span>
        </div>
        <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top bar -->
    <div class="topbar">
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="globalSearch" placeholder="Search applications..." autocomplete="off">
      </div>
      <div class="topbar-actions">
        <button class="btn btn-primary btn-sm" onclick="window.location='<?= APP_URL ?>/applications.php?action=new'">
          <i class="bi bi-plus-lg"></i> Add New Application
        </button>
        <a href="<?= APP_URL ?>/reminders.php" class="topbar-icon" title="Reminders">
          <i class="bi bi-bell"></i>
        </a>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show mx-3 mt-3" role="alert">
      <?= h($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="page-content">
