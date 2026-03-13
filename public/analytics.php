<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();

// ── Existing data ─────────────────────────────────────────────────────────
$counts  = $appModel->countByStatus($userId);
$total   = array_sum($counts);
$byMonth = $appModel->countByMonth($userId, 6);
$topCo   = $appModel->topCompanies($userId, 5);

// Build recent activity
$allApps    = $appModel->getAll($userId);
$recentApps = array_slice($allApps, 0, 5);

// ── NEW: Funnel + Insight data ────────────────────────────────────────────
$funnel      = $appModel->conversionFunnel($userId);
$stalled     = $appModel->stalledApps($userId, 7);
$stalledCount = count($stalled);
$bestDay     = $appModel->bestDayOfWeek($userId);

// Build insight chips array — only show chips that have real data
$insights = [];

if ($stalledCount > 0) {
    $insights[] = [
        'type'  => 'alert',
        'icon'  => 'bi-exclamation-circle',
        'text'  => $stalledCount === 1
                    ? '1 application needs a follow-up (7+ days stale)'
                    : "{$stalledCount} applications need a follow-up (7+ days stale)",
        'url'   => APP_URL . '/applications.php?status=applied',
    ];
}

$interviewRate = $total > 0 ? round((($counts['interviewing'] + $counts['offer']) / $total) * 100) : 0;
if ($interviewRate > 0) {
    $rateLabel = $interviewRate >= 20 ? 'above average' : 'keep applying to improve this';
    $insights[] = [
        'type'  => 'info',
        'icon'  => 'bi-graph-up-arrow',
        'text'  => "Your interview rate is {$interviewRate}% — {$rateLabel}",
        'url'   => APP_URL . '/applications.php?status=interviewing',
    ];
}

if ($bestDay) {
    $insights[] = [
        'type'  => 'positive',
        'icon'  => 'bi-calendar-check',
        'text'  => "Applications submitted on {$bestDay}s progress the most",
        'url'   => null,
    ];
}

if ($counts['offer'] > 0) {
    $insights[] = [
        'type'  => 'positive',
        'icon'  => 'bi-trophy',
        'text'  => $counts['offer'] === 1
                    ? 'You have 1 active offer — nice work!'
                    : "You have {$counts['offer']} active offers — nice work!",
        'url'   => APP_URL . '/applications.php?status=offer',
    ];
}

// ── Page meta ─────────────────────────────────────────────────────────────
$pageTitle   = 'Analytics';
$currentPage = 'analytics';

// ── Chart.js data ─────────────────────────────────────────────────────────
$monthLabels = [];
$monthData   = [];
for ($i = 5; $i >= 0; $i--) {
    $monthLabels[] = date('M', strtotime("-$i months"));
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
$chartFunnelData  = json_encode($funnel);

ob_start();
?>

<!-- ── Page header ──────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h1 style="font-size:22px;margin:0">Analytics</h1>
    <p class="text-muted mb-0" style="font-size:13px">
      Last updated <?= date('g:i A') ?> &middot; Past 6 months
    </p>
  </div>
  <a href="<?= APP_URL ?>/applications.php" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-folder2-open me-1"></i> View All Applications
  </a>
</div>

<!-- ── Insight chips ────────────────────────────────────────────────────── -->
<?php if (!empty($insights)): ?>
<div class="insight-chips mb-4">
  <?php foreach ($insights as $chip): ?>
    <?php $tag = $chip['url'] ? 'a' : 'span'; ?>
    <<?= $tag ?>
      <?= $chip['url'] ? 'href="' . h($chip['url']) . '"' : '' ?>
      class="insight-chip insight-chip--<?= h($chip['type']) ?>"
    >
      <i class="bi <?= h($chip['icon']) ?>"></i>
      <?= h($chip['text']) ?>
      <?php if ($chip['url']): ?><i class="bi bi-arrow-right ms-1" style="font-size:11px"></i><?php endif; ?>
    </<?= $tag ?>>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Stat cards ───────────────────────────────────────────────────────── -->
<div class="stat-grid mb-4">
  <div class="stat-card stat-card--blue">
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-badge text-success"><i class="bi bi-arrow-up-short"></i> All time</div>
  </div>
  <div class="stat-card stat-card--yellow">
    <div class="stat-label">Interviews Secured</div>
    <div class="stat-value"><?= $counts['interviewing'] + $counts['offer'] ?></div>
    <div class="stat-badge" style="color:var(--text-secondary);font-size:12px">
      <?= $interviewRate ?>% conversion
    </div>
  </div>
  <div class="stat-card stat-card--green">
    <div class="stat-label">Offers Received</div>
    <div class="stat-value"><?= $counts['offer'] ?></div>
    <div class="stat-badge" style="color:var(--text-secondary);font-size:12px">
      <?= $total > 0 ? round(($counts['offer'] / $total) * 100) : 0 ?>% offer rate
    </div>
  </div>
  <div class="stat-card stat-card--red">
    <div class="stat-label">Success Rate</div>
    <div class="stat-value"><?= $total > 0 ? round(($counts['offer'] / $total) * 100) : 0 ?>%</div>
    <div class="stat-badge text-danger"><i class="bi bi-arrow-down-short"></i> Offers / Total</div>
  </div>
</div>

<!-- ── Row 1: Timeline + Funnel ─────────────────────────────────────────── -->
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

  <!-- Conversion Funnel (NEW) -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <span>Application Funnel</span>
        <span style="font-size:12px;color:var(--text-secondary)">Conversion rates</span>
      </div>
      <div class="card-body" id="funnelPanel">
        <?php foreach ($funnel as $i => $step): ?>
        <a href="<?= APP_URL ?>/applications.php?status=<?= h($step['status']) ?>"
           class="funnel-step" style="text-decoration:none">
          <div class="funnel-step__meta">
            <span class="funnel-step__label"><?= h($step['label']) ?></span>
            <span class="funnel-step__count"><?= $step['count'] ?></span>
          </div>
          <div class="funnel-step__track">
            <div
              class="funnel-step__fill"
              style="width:0%;background:<?= h($step['color']) ?>"
              data-width="<?= $step['pct'] ?>"
            ></div>
          </div>
          <div class="funnel-step__pct" style="color:<?= h($step['color']) ?>">
            <?= $step['pct'] ?>%
          </div>
        </a>
        <?php if ($i < count($funnel) - 1): ?>
          <div class="funnel-arrow"><i class="bi bi-chevron-down"></i></div>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($funnel) || $total === 0): ?>
          <div class="text-center text-muted py-4" style="font-size:13px">
            <i class="bi bi-bar-chart-steps d-block mb-2" style="font-size:28px;opacity:.3"></i>
            Add applications to see your funnel
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Row 2: By Stage + Top Companies + Recent Activity ────────────────── -->
<div class="row g-4">

  <!-- By Stage -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><span>Applications by Stage</span></div>
      <div class="card-body">
        <?php
        $stageColors = [
          'wishlist'     => '#6b7280',
          'applied'      => '#1a73e8',
          'interviewing' => '#f59e0b',
          'offer'        => '#10b981',
          'rejected'     => '#ef4444',
        ];
        foreach ($counts as $s => $n):
          $pct = $total > 0 ? round(($n / $total) * 100) : 0;
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

  <!-- Top Companies -->
  <div class="col-lg-5">
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
                    <?= strtoupper(substr($co['company'], 0, 1)) ?>
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

</div>

<!-- ── Row 3: Recent Activity ────────────────────────────────────────────── -->
<div class="row g-4 mt-0">
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

  <!-- Stalled Applications (NEW — only shown when there are stalled apps) -->
  <?php if ($stalledCount > 0): ?>
  <div class="col-lg-7">
    <div class="card stalled-card">
      <div class="card-header" style="border-left:3px solid #f59e0b">
        <span><i class="bi bi-clock-history me-2" style="color:#f59e0b"></i>Needs Follow-up</span>
        <span style="font-size:12px;color:var(--text-secondary)">Stale for 7+ days</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th class="ps-3">Role</th>
            <th>Status</th>
            <th>Days Stale</th>
            <th>Action</th>
          </tr></thead>
          <tbody>
            <?php foreach ($stalled as $app): ?>
            <tr>
              <td class="ps-3">
                <div style="font-weight:600;font-size:13px"><?= h($app['job_title']) ?></div>
                <div style="font-size:12px;color:var(--text-secondary)"><?= h($app['company']) ?></div>
              </td>
              <td><span class="badge rounded-pill badge-<?= $app['status'] ?>"><?= h(statusLabel($app['status'])) ?></span></td>
              <td>
                <span style="font-size:13px;font-weight:600;color:#f59e0b">
                  <?= (int)$app['days_stalled'] ?> days
                </span>
              </td>
              <td>
                <a href="<?= APP_URL ?>/application-detail.php?id=<?= $app['id'] ?>"
                   class="btn btn-sm btn-outline-warning py-0 px-2" style="font-size:12px">
                  Follow up
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php
$content = ob_get_clean();

$inlineScript = <<<JS
const MONTH_LABELS   = $chartMonthLabels;
const MONTH_DATA     = $chartMonthData;
const STAGE_LABELS   = $chartStageLabels;
const STAGE_DATA     = $chartStageData;
const FUNNEL_DATA    = $chartFunnelData;
initAnalytics();
JS;

include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';