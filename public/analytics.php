<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();

// ── Core data ─────────────────────────────────────────────────────────────────
$counts     = $appModel->countByStatus($userId);
$total      = array_sum($counts);
$byMonth    = $appModel->countByMonth($userId, 6);
$topCo      = $appModel->topCompanies($userId, 5);
$allApps    = $appModel->getAll($userId);
$recentApps = array_slice($allApps, 0, 6);

// ── New analytics data (new methods in Application.php) ───────────────────────
$funnel    = $appModel->conversionFunnel($userId);
$stalled   = $appModel->stalledApps($userId, 7);
$bestDay   = $appModel->bestDayOfWeek($userId);
$thisMonth = $appModel->countThisMonth($userId);

// ── Computed stats ────────────────────────────────────────────────────────────
$interviewTotal = $counts['interviewing'] + $counts['offer'];
$interviewPct   = $total > 0 ? round(($interviewTotal / $total) * 100) : 0;
$offerPct       = $total > 0 ? round(($counts['offer']   / $total) * 100) : 0;
$successRate    = $offerPct;
$stalledCount   = count($stalled);
$funnelBase     = max(1, $funnel[0]['count']); // avoid division by zero

// ── Build insight chips ───────────────────────────────────────────────────────
$insights = [];

if ($stalledCount > 0) {
    $insights[] = [
        'type' => 'warning',
        'icon' => 'bi-clock-history',
        'text' => $stalledCount . ' application' . ($stalledCount > 1 ? 's' : '') . ' need' . ($stalledCount === 1 ? 's' : '') . ' follow-up (7+ days with no update)',
        'href' => APP_URL . '/applications.php?status=applied',
    ];
}

if ($interviewPct >= 20) {
    $insights[] = [
        'type' => 'positive',
        'icon' => 'bi-graph-up-arrow',
        'text' => 'Your interview rate is ' . $interviewPct . '% — above the 20% average',
        'href' => APP_URL . '/applications.php?status=interviewing',
    ];
} elseif ($total >= 3) {
    $insights[] = [
        'type' => 'neutral',
        'icon' => 'bi-graph-up',
        'text' => 'Interview rate: ' . $interviewPct . '% — keep applying to push this higher',
        'href' => APP_URL . '/applications.php',
    ];
}

if ($bestDay) {
    $insights[] = [
        'type' => 'neutral',
        'icon' => 'bi-calendar-check',
        'text' => 'Applications you send on ' . $bestDay . 's progress the most',
        'href' => '#',
    ];
}

if ($counts['offer'] > 0) {
    $insights[] = [
        'type' => 'positive',
        'icon' => 'bi-trophy',
        'text' => 'You have ' . $counts['offer'] . ' active offer' . ($counts['offer'] > 1 ? 's' : '') . ' — great work!',
        'href' => APP_URL . '/applications.php?status=offer',
    ];
}

// ── Get time period data (week/month/year) ────────────────────────────────────
$timeView = $_GET['view'] ?? 'month'; // Default to month

// Week data
$byWeek = $appModel->countByWeek($userId, 12);
$weekLabels = [];
$weekData   = [];
for ($i = 11; $i >= 0; $i--) {
    $weekStart = date('M d', strtotime("-{$i} weeks"));
    $weekLabels[] = 'W' . date('W', strtotime("-{$i} weeks"));
    $weekData[] = 0;
}
foreach ($byWeek as $row) {
    $weekNum = (int)substr($row['week'], -2);
    $idx = array_search('W' . str_pad($weekNum, 2, '0', STR_PAD_LEFT), $weekLabels);
    if ($idx === false) {
        $idx = array_search('W' . ltrim(str_pad($weekNum, 2, '0', STR_PAD_LEFT), '0'), $weekLabels);
    }
    if ($idx !== false) {
        $weekData[$idx] = (int)$row['count'];
    }
}

// Month data (already have $byMonth)
$monthLabels = [];
$monthData   = [];
for ($i = 5; $i >= 0; $i--) {
    $monthLabels[] = date('M', strtotime("-{$i} months"));
    $monthData[]   = 0;
}
foreach ($byMonth as $row) {
    $lbl = date('M', strtotime($row['month'] . '-01'));
    $idx = array_search($lbl, $monthLabels);
    if ($idx !== false) {
        $monthData[$idx] = (int)$row['count'];
    }
}

// Year data
$byYear = $appModel->countByYear($userId, 3);
$yearLabels = [];
$yearData   = [];
for ($i = 2; $i >= 0; $i--) {
    $yearLabels[] = date('Y', strtotime("-{$i} years"));
    $yearData[] = 0;
}
foreach ($byYear as $row) {
    $idx = array_search($row['year'], $yearLabels);
    if ($idx !== false) {
        $yearData[$idx] = (int)$row['count'];
    }
}

// Select active labels/data based on view
$chartLabels = $monthLabels;
$chartData   = $monthData;
if ($timeView === 'week') {
    $chartLabels = $weekLabels;
    $chartData   = $weekData;
} elseif ($timeView === 'year') {
    $chartLabels = $yearLabels;
    $chartData   = $yearData;
}

// JSON-encode for inline JS
$chartLabels_json = json_encode($chartLabels);
$chartData_json   = json_encode($chartData);
$chartMonthLabels = json_encode($monthLabels);
$chartMonthData   = json_encode($monthData);
$chartWeekLabels  = json_encode($weekLabels);
$chartWeekData    = json_encode($weekData);
$chartYearLabels  = json_encode($yearLabels);
$chartYearData    = json_encode($yearData);
$funnelJson       = json_encode($funnel);

$stageColors = [
    'wishlist'     => '#6b7280',
    'applied'      => '#1a73e8',
    'interviewing' => '#f59e0b',
    'offer'        => '#10b981',
    'rejected'     => '#ef4444',
];

$pageTitle   = 'Analytics';
$currentPage = 'analytics';

ob_start();
?>

<!-- ── Page header ───────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <h4 style="font-family:'Sora',sans-serif;font-weight:700;margin:0">Analytics</h4>
    <div style="font-size:12px;color:var(--text-secondary);margin-top:3px">
      Updated <?= date('g:i A') ?> &middot; Showing last 6 months
    </div>
  </div>
  <a href="<?= APP_URL ?>/applications.php" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-folder2-open me-1"></i>All Applications
  </a>
</div>

<!-- ── Insight chips ─────────────────────────────────────────────────────────── -->
<?php if (!empty($insights)): ?>
<div class="insight-chips-row mb-4">
  <?php foreach ($insights as $chip): ?>
    <a href="<?= h($chip['href']) ?>" class="insight-chip insight-chip--<?= $chip['type'] ?>">
      <i class="bi <?= $chip['icon'] ?>"></i>
      <span><?= h($chip['text']) ?></span>
      <?php if ($chip['href'] !== '#'): ?>
        <i class="bi bi-arrow-right" style="font-size:11px;opacity:.5"></i>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Stat cards ────────────────────────────────────────────────────────────── -->
<div class="stat-grid mb-4">

  <div class="stat-card stat-card--blue">
    <div class="stat-label">Total Applications</div>
    <div class="stat-value"><?= $total ?></div>
    <span class="stat-delta stat-delta--neutral">
      <i class="bi bi-plus-circle"></i> <?= $thisMonth ?> this month
    </span>
  </div>

  <div class="stat-card stat-card--yellow">
    <div class="stat-label">Interviews Secured</div>
    <div class="stat-value"><?= $interviewTotal ?></div>
    <span class="stat-delta <?= $interviewPct >= 20 ? 'stat-delta--up' : 'stat-delta--neutral' ?>">
      <i class="bi bi-arrow-<?= $interviewPct >= 20 ? 'up' : 'right' ?>-short"></i>
      <?= $interviewPct ?>% conversion
    </span>
  </div>

  <div class="stat-card stat-card--green">
    <div class="stat-label">Offers Received</div>
    <div class="stat-value"><?= $counts['offer'] ?></div>
    <span class="stat-delta <?= $offerPct > 0 ? 'stat-delta--up' : 'stat-delta--neutral' ?>">
      <i class="bi bi-award"></i> <?= $offerPct ?>% offer rate
    </span>
  </div>

  <div class="stat-card stat-card--purple">
    <div class="stat-label">Success Rate</div>
    <div class="stat-value"><?= $successRate ?>%</div>
    <span class="stat-delta stat-delta--<?= $successRate > 0 ? 'up' : 'neutral' ?>">
      <i class="bi bi-pie-chart"></i> Offers ÷ Total
    </span>
  </div>

</div>

<!-- ── Row 2: Timeline chart + Funnel ────────────────────────────────────────── -->
<div class="row g-4 mb-4">

  <!-- Timeline chart -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Applications Over Time</span>
        <div class="btn-group btn-group-sm" role="group">
          <a href="?view=week" class="btn btn-outline-secondary <?= $timeView === 'week' ? 'active' : '' ?>" style="font-size:11px">Week</a>
          <a href="?view=month" class="btn btn-outline-secondary <?= $timeView === 'month' ? 'active' : '' ?>" style="font-size:11px">Month</a>
          <a href="?view=year" class="btn btn-outline-secondary <?= $timeView === 'year' ? 'active' : '' ?>" style="font-size:11px">Year</a>
        </div>
      </div>
      <div class="card-body" style="padding:20px">
        <canvas id="chartTimeline" style="max-height:220px"></canvas>
      </div>
    </div>
  </div>

  <!-- Funnel -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <span>Application Funnel</span>
        <span style="font-size:12px;color:var(--text-secondary)">Conversion</span>
      </div>
      <div class="card-body" style="padding:16px 20px">
        <?php foreach ($funnel as $i => $step):
          $pct  = round(($step['count'] / $funnelBase) * 100);
          $link = APP_URL . '/applications.php?status=' . $step['status'];
        ?>
        <a href="<?= $link ?>" class="funnel-step" style="--step-color:<?= $step['color'] ?>">
          <div class="funnel-step__top">
            <span class="funnel-step__label" style="color:<?= $step['color'] ?>">
              <?= h($step['label']) ?>
            </span>
            <span class="funnel-step__meta">
              <strong style="color:<?= $step['color'] ?>"><?= $step['count'] ?></strong>
              <span style="color:var(--text-secondary);font-size:12px;margin-left:4px"><?= $pct ?>%</span>
            </span>
          </div>
          <div class="funnel-step__track">
            <div class="funnel-step__fill" data-width="<?= $pct ?>" style="width:0%;background:<?= $step['color'] ?>"></div>
          </div>
          <?php if ($i < count($funnel) - 1): ?>
            <div class="funnel-step__arrow"><i class="bi bi-chevron-down"></i></div>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Row 3: By Stage + Top Companies ───────────────────────────────────────── -->
<div class="row g-4 mb-4">

  <!-- By Stage -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <span>By Stage</span>
        <span style="font-size:12px;color:var(--text-secondary)"><?= $total ?> total</span>
      </div>
      <div class="card-body" style="padding:8px 20px">
        <?php foreach ($counts as $s => $n):
          $pct = $total > 0 ? round(($n / $total) * 100) : 0;
        ?>
        <a href="<?= APP_URL ?>/applications.php?status=<?= $s ?>" class="stage-row">
          <div class="stage-row__labels">
            <span class="stage-row__dot" style="background:<?= $stageColors[$s] ?>"></span>
            <span class="stage-row__name"><?= ucfirst($s) ?></span>
            <span class="stage-row__count"><?= $n ?></span>
            <span class="stage-row__pct"><?= $pct ?>%</span>
          </div>
          <div class="stage-row__track">
            <div class="stage-row__fill" data-width="<?= $pct ?>" style="width:0%;background:<?= $stageColors[$s] ?>"></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Top Companies -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><span>Top Companies</span></div>
      <div class="card-body p-0">
        <?php if (empty($topCo)): ?>
          <div class="text-center py-4 text-muted" style="font-size:13px">No applications yet.</div>
        <?php else: ?>
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th class="ps-3">Company</th>
              <th>Apps</th>
              <th>Latest Status</th>
              <th style="width:110px">Volume</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topCo as $co):
              $maxCount = max(1, $topCo[0]['count']);
              $barPct   = round($co['count'] / $maxCount * 100);
            ?>
            <tr style="cursor:pointer" onclick="window.location='<?= APP_URL ?>/applications.php?search=<?= urlencode($co['company']) ?>'">
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <div class="company-logo-cell">
                    <img
                      src="https://logo.clearbit.com/<?= urlencode(strtolower(preg_replace('/\s+/', '', $co['company']))) ?>.com"
                      alt=""
                      onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                      style="width:100%;height:100%;object-fit:contain;border-radius:6px;display:block">
                    <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--brand)">
                      <?= strtoupper(substr($co['company'], 0, 1)) ?>
                    </span>
                  </div>
                  <span style="font-weight:500;font-size:13px"><?= h($co['company']) ?></span>
                </div>
              </td>
              <td style="font-size:13px"><?= (int)$co['count'] ?></td>
              <td>
                <span class="badge rounded-pill badge-<?= $co['status'] ?>">
                  <?= h(statusLabel($co['status'])) ?>
                </span>
              </td>
              <td>
                <div class="progress" style="height:5px;border-radius:4px;background:#f0f4f9">
                  <div class="progress-bar" style="width:<?= $barPct ?>%;background:var(--brand);border-radius:4px"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Recent Activity ────────────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <span>Recent Activity</span>
    <a href="<?= APP_URL ?>/applications.php" style="font-size:12px;color:var(--brand);text-decoration:none">View all</a>
  </div>
  <?php if (empty($recentApps)): ?>
    <div class="card-body text-center text-muted py-4" style="font-size:13px">
      No applications yet. <a href="<?= APP_URL ?>/applications.php?action=new">Add your first one</a>.
    </div>
  <?php else: ?>
  <div class="card-body p-0">
    <div class="row g-0">
      <?php foreach ($recentApps as $app): ?>
      <div class="col-lg-4 col-md-6" style="border-bottom:1px solid var(--border)">
        <a href="<?= APP_URL ?>/application-detail.php?id=<?= (int)$app['id'] ?>" class="activity-card">
          <div class="activity-card__icon">
            <i class="bi bi-briefcase"></i>
          </div>
          <div class="activity-card__body">
            <div class="activity-card__title"><?= h($app['job_title']) ?></div>
            <div class="activity-card__company"><?= h($app['company']) ?></div>
          </div>
          <span class="badge badge-<?= $app['status'] ?>"><?= h(statusLabel($app['status'])) ?></span>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

$inlineScript = <<<JS
(function() {
  const WEEK_LABELS  = {$chartWeekLabels};
  const WEEK_DATA    = {$chartWeekData};
  const MONTH_LABELS = {$chartMonthLabels};
  const MONTH_DATA   = {$chartMonthData};
  const YEAR_LABELS  = {$chartYearLabels};
  const YEAR_DATA    = {$chartYearData};

  let chartInstance = null;

  function initChartWithData(labels, data) {
    const ctxEl = document.getElementById('chartTimeline');
    if (!ctxEl || typeof Chart === 'undefined') return;
    
    if (chartInstance) {
      chartInstance.destroy();
    }
    
    chartInstance = new Chart(ctxEl, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Applications',
          data: data,
          borderColor: '#1a73e8',
          backgroundColor: 'rgba(26,115,232,0.07)',
          borderWidth: 2.5,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#1a73e8',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1a1a2e',
            titleColor: '#fff',
            bodyColor: '#e5e7eb',
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label: function(ctx) {
                return '  ' + ctx.parsed.y + ' application' + (ctx.parsed.y !== 1 ? 's' : '');
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1, color: '#9ca3af', font: { size: 11 } },
            grid: { color: '#f0f4f9' },
            border: { display: false }
          },
          x: {
            ticks: { color: '#9ca3af', font: { size: 11 } },
            grid: { display: false },
            border: { display: false }
          }
        }
      }
    });
  }

  // Initialize with current view data
  const currentView = new URLSearchParams(window.location.search).get('view') || 'month';
  if (currentView === 'week') {
    initChartWithData(WEEK_LABELS, WEEK_DATA);
  } else if (currentView === 'year') {
    initChartWithData(YEAR_LABELS, YEAR_DATA);
  } else {
    initChartWithData(MONTH_LABELS, MONTH_DATA);
  }

  // Handle time period button clicks
  document.querySelectorAll('.card-header .btn-group-sm a').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const view = new URLSearchParams(this.href).get('view') || 'month';
      // Page will reload with new view, but this could be enhanced with AJAX
    });
  });


  // ── Animate progress bars (funnel + stage rows) ─────────────────────────────
  // Use data-width attribute so we can animate from 0 → target
  function animateBars(selector) {
    document.querySelectorAll(selector).forEach(function(el) {
      var target = el.getAttribute('data-width') || '0';
      el.style.width = '0%';
      el.style.transition = 'width 0.8s cubic-bezier(.4,0,.2,1)';
      setTimeout(function() { el.style.width = target + '%'; }, 100);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      animateBars('.funnel-step__fill');
      animateBars('.stage-row__fill');
    });
  } else {
    animateBars('.funnel-step__fill');
    animateBars('.stage-row__fill');
  }

})();
JS;

include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';