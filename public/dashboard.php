<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();
Auth::require2faIfEnabled(); // Enforce 2FA if user has it enabled

$appModel = new Application();
$userId   = Auth::id();

$counts    = $appModel->countByStatus($userId);
$total     = array_sum($counts);
$recent    = $appModel->getAll($userId, []);
$upcoming  = $appModel->getUpcomingEvents($userId, 4);
$byMonth   = $appModel->countByMonth($userId, 6);
$topCo     = $appModel->topCompanies($userId, 4);

$pageTitle   = 'Dashboard';
$currentPage = 'dashboard';

// Group apps by status for kanban preview
$byStatus = [];
foreach ($recent as $app) {
    $byStatus[$app['status']][] = $app;
}

ob_start();
?>
<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= h($total) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Interviewing</div>
    <div class="stat-value"><?= h($counts['interviewing']) ?></div>
    <span class="stat-badge text-warning"><i class="bi bi-arrow-up-short"></i></span>
  </div>
  <div class="stat-card">
    <div class="stat-label">Offers Received</div>
    <div class="stat-value"><?= h($counts['offer']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Success Rate</div>
    <div class="stat-value"><?= $total > 0 ? h(round(($counts['offer']/$total)*100)) : 0 ?>%</div>
  </div>
</div>

<div class="row g-4">
  <!-- Kanban Preview -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <span>Applications Board</span>
        <a href="<?= APP_URL ?>/applications.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-3">
        <div class="kanban-board">
          <?php
          $statuses = ['wishlist','applied','interviewing','offer'];
          $statusLabels = ['Wishlist','Applied','Interviewing','Offer'];
          foreach ($statuses as $i => $s):
            $apps = $byStatus[$s] ?? [];
          ?>
          <div class="kanban-col">
            <div class="kanban-col-header">
              <span class="col-dot dot-<?= h($s) ?>"></span>
              <?= strtoupper(h($s)) ?>
              <span class="col-count"><?= h($counts[$s]) ?></span>
            </div>
            <div class="kanban-cards">
              <?php foreach (array_slice($apps, 0, 3) as $app): ?>
              <div class="app-card" onclick="window.location=window.APP_URL + '/application-detail.php?id=<?= $app['id'] ?>'" style="cursor:pointer">
                <div class="company-logo"><?= strtoupper(h(substr($app['company'],0,1))) ?></div>
                <div class="job-title"><?= h($app['job_title']) ?></div>
                <div class="company-name"><?= h($app['company']) ?></div>
                <div class="card-footer-row">
                  <i class="bi bi-calendar3"></i>
                  <?= $app['applied_at'] ? h(date('M d', strtotime($app['applied_at']))) : 'No date' ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($apps)): ?>
              <div class="text-center text-muted py-3" style="font-size:13px">No applications</div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar: Upcoming Events + Stage Breakdown -->
  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header">
        <span>Upcoming Events</span>
        <a href="<?= APP_URL ?>/calendar.php" class="btn btn-sm btn-outline-primary">Calendar</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($upcoming)): ?>
          <div class="text-center text-muted py-4" style="font-size:13px">No upcoming events</div>
        <?php else: ?>
          <?php foreach ($upcoming as $ev): ?>
          <div class="d-flex gap-3 p-3 border-bottom">
            <div class="text-center" style="min-width:40px">
              <div style="font-size:11px;font-weight:700;color:var(--brand);text-transform:uppercase"><?= date('M', strtotime($ev['event_date'])) ?></div>
              <div style="font-size:20px;font-weight:700;line-height:1"><?= date('d', strtotime($ev['event_date'])) ?></div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:<?= match($ev['event_type']){
                'interview'=>'var(--brand)','deadline'=>'var(--rejected)','follow_up'=>'var(--offer)',default=>'var(--text-secondary)'
              } ?>"><?= h(str_replace('_',' ',strtoupper($ev['event_type']))) ?></div>
              <div style="font-weight:600;font-size:13px"><?= h($ev['title']) ?></div>
              <div style="font-size:12px;color:var(--text-secondary)"><?= h($ev['company']) ?> &bull; <?= date('g:i A', strtotime($ev['event_date'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stage breakdown -->
    <div class="card" style="background:#1a1a2e;color:#fff">
      <div class="card-body p-3">
        <div style="font-size:13px;opacity:.7;margin-bottom:4px">MONTHLY STATUS</div>
        <div style="font-size:28px;font-weight:700;font-family:'Sora',sans-serif;margin-bottom:16px">
          <?= $total ?> <span style="font-size:14px;font-weight:400;opacity:.7">Active Apps</span>
        </div>
        <?php foreach (['interviewing'=>'Interviews Scheduled','offer'=>'Offers'] as $s => $label): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span style="font-size:13px;opacity:.8"><?= $label ?></span>
          <span style="font-weight:700"><?= $counts[$s] ?></span>
        </div>
        <div class="progress progress-thin mb-3" style="background:rgba(255,255,255,.15)">
          <div class="progress-bar" style="width:<?= $total > 0 ? round($counts[$s]/$total*100) : 0 ?>%;background:<?= $s==='offer' ? 'var(--offer)' : 'var(--brand)' ?>"></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
