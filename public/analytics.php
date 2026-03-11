<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();

$counts  = $appModel->countByStatus($userId);
$total   = array_sum($counts);
$byMonth = $appModel->countByMonth($userId, 6);
$topCo   = $appModel->topCompanies($userId, 5);

// Build recent activity
$allApps    = $appModel->getAll($userId);
$recentApps = array_slice($allApps, 0, 5);

$pageTitle   = 'Analytics';
$currentPage = 'analytics';

// Prepare Chart.js data
$monthLabels = [];
$monthData   = [];
// Build last 6 months
for ($i = 5; $i >= 0; $i--) {
    $key  = date('Y-m', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $monthLabels[] = $label;
    $monthData[]   = 0;
}
foreach ($byMonth as $row) {
    $label = date('M', strtotime($row['month'] . '-01'));
    $idx   = array_search($label, $monthLabels);
    if ($idx !== false) $monthData[$idx] = (int)$row['count'];
}

$chartMonthLabels = json_encode($monthLabels);
$chartMonthData   = json_encode($monthData);
$chartStageLabels = json_encode(['Wishlist','Applied','Interviewing','Offer','Rejected']);
$chartStageData   = json_encode(array_values($counts));

ob_start();
?>
<!-- Stats row -->
<div class="stat-grid mb-4">
  <div class="stat-card">
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-badge text-success"><i class="bi bi-arrow-up-short"></i> All time</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Interviews Secured</div>
    <div class="stat-value"><?= $counts['interviewing'] + $counts['offer'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Offers Received</div>
    <div class="stat-value"><?= $counts['offer'] ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Success Rate</div>
    <div class="stat-value"><?= $total > 0 ? round(($counts['offer'] / $total) * 100) : 0 ?>%</div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Applications Over Time -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <span>Applications Over Time</span>
        <span style="font-size:12px;color:var(--text-secondary)">Last 6 Months</span>
      </div>
      <div class="card-body">
        <canvas id="chartTimeline" height="220"></canvas>
      </div>
    </div>
  </div>

  <!-- By Stage -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><span>Applications by Stage</span></div>
      <div class="card-body">
        <?php
        $stageColors = ['wishlist'=>'#6b7280','applied'=>'#1a73e8','interviewing'=>'#f59e0b','offer'=>'#10b981','rejected'=>'#ef4444'];
        foreach ($counts as $s => $n):
          $pct = $total > 0 ? round(($n/$total)*100) : 0;
        ?>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span style="font-size:13px;font-weight:500"><?= ucfirst($s) ?></span>
          <span style="font-size:13px;font-weight:600"><?= $n ?></span>
        </div>
        <div class="progress mb-3" style="height:6px;border-radius:4px;background:#f0f4f9">
          <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $stageColors[$s] ?>;border-radius:4px"></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Top Companies -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><span>Top Companies</span></div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th class="ps-3">Company</th>
            <th>Applications</th>
            <th>Status</th>
            <th>Volume</th>
          </tr></thead>
          <tbody>
            <?php foreach ($topCo as $co): ?>
            <tr>
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <div style="width:30px;height:30px;border-radius:8px;background:var(--bg-page);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--brand)">
                    <?= strtoupper(substr($co['company'],0,1)) ?>
                  </div>
                  <?= h($co['company']) ?>
                </div>
              </td>
              <td><?= $co['count'] ?></td>
              <td><span class="badge rounded-pill badge-<?= $co['status'] ?>"><?= h(statusLabel($co['status'])) ?></span></td>
              <td style="width:120px">
                <div class="progress" style="height:5px;border-radius:4px;background:#f0f4f9">
                  <div class="progress-bar bg-primary" style="width:<?= min(100, round($co['count'] / max(1, $topCo[0]['count']) * 100)) ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><span>Recent Activity</span></div>
      <div class="card-body p-0">
        <?php foreach ($recentApps as $app): ?>
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
          <div style="width:36px;height:36px;border-radius:50%;background:var(--brand-light);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-briefcase text-primary" style="font-size:14px"></i>
          </div>
          <div class="flex-grow-1">
            <div style="font-weight:600;font-size:13px"><?= h($app['job_title']) ?></div>
            <div style="font-size:12px;color:var(--text-secondary)">At <?= h($app['company']) ?></div>
          </div>
          <span class="badge badge-<?= $app['status'] ?>"><?= h(statusLabel($app['status'])) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="text-center p-3">
          <a href="<?= APP_URL ?>/applications.php" class="btn btn-sm btn-outline-primary">View All Activity</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
$inlineScript = <<<JS
const MONTH_LABELS = $chartMonthLabels;
const MONTH_DATA   = $chartMonthData;
const STAGE_LABELS = $chartStageLabels;
const STAGE_DATA   = $chartStageData;
initAnalytics();
JS;
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
