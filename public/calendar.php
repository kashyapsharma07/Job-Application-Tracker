<?php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::require();

$userId   = Auth::id();
$appModel = new Application();
$remModel = new Reminder();

$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Navigate
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$monthStr    = str_pad($month, 2, '0', STR_PAD_LEFT);
$events      = $appModel->getCalendarEvents($userId, $year, $monthStr);
$upcoming    = $appModel->getUpcomingEvents($userId, 5);
$reminders   = $remModel->getAll($userId);
$counts      = $appModel->countByStatus($userId);

//Create a map of dates to events for calender display
$eventsByDate = [];

//Map application events
foreach ($upcoming as $ev) {
  $dateKey = date('Y-m-d', strtotime($ev['event_date']));
  if (!isset($eventsByDate[$dateKey])) {
    $eventsByDate[$dateKey] = [];
  }
  $eventsByDate[$dateKey][] = [
    'title' => $ev['title'],
    'type' => $ev['event_type'],
    'is_reminder' => false
  ];
}

//Map reminders
foreach ($reminders as $rem) {
  if (strtotime($rem['remind_at']) > time()) {
    $dateKey = date('Y-m-d', strtotime($rem['remind_at']));
    if (!isset($eventsByDate[$dateKey])) {
      $eventsByDate[$dateKey] = [];
    }
    $eventsByDate[$dateKey][] = [
      'title' => $rem['title'],
      'type' => 'reminder',
      'is_reminder' => true
    ];
  }
}
// Group events by day
$eventsByDay = [];
foreach ($events as $ev) {
    $day = (int)date('j', strtotime($ev['event_date']));
    $eventsByDay[$day][] = $ev;
}

// Build calendar grid
$firstDay = (int)date('w', mktime(0,0,0,$month,1,$year));
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$prevMonth = $month - 1 < 1 ? 12 : $month - 1;
$prevYear  = $month - 1 < 1 ? $year - 1 : $year;
$daysInPrev = (int)date('t', mktime(0,0,0,$prevMonth,1,$prevYear));

$pageTitle   = 'Calendar';
$currentPage = 'calendar';

$evTypeColor = ['interview'=>'cal-event-interview','deadline'=>'cal-event-deadline',
                'follow_up'=>'cal-event-follow_up','note'=>'cal-event-note','offer'=>'cal-event-interview'];

ob_start();
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h1 style="font-size:22px;margin:0">Calendar</h1>
    <p class="text-muted mb-0" style="font-size:13px">Interviews, deadlines & follow-ups</p>
  </div>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
    <i class="bi bi-plus-lg me-1"></i> Schedule New
  </button>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body p-3">
        <!-- Month navigation -->
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h2 style="font-size:20px;margin:0;font-family:'Sora',sans-serif">
            <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?>
          </h2>
          <div class="d-flex align-items-center gap-2">
            <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-sm btn-outline-secondary">Today</a>
            <a href="?year=<?= $month==12?$year+1:$year ?>&month=<?= $month==12?1:$month+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
          </div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
          <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
          <div class="cal-day-header"><?= $d ?></div>
          <?php endforeach; ?>

          <?php
          $today = (int)date('j');
          $thisMonth = (int)date('n');
          $thisYear  = (int)date('Y');
          $cellCount = 0;

          // Prev month days
          for ($i = $firstDay - 1; $i >= 0; $i--) {
              echo '<div class="cal-day other-month"><div class="cal-day-num">' . ($daysInPrev - $i) . '</div></div>';
              $cellCount++;
          }

          // Current month days
          for ($d = 1; $d <= $daysInMonth; $d++) {
              $isToday = ($d === $today && $month === $thisMonth && $year === $thisYear);
              echo '<div class="cal-day' . ($isToday ? ' today' : '') . '">';
              echo '<div class="cal-day-num">' . $d . '</div>';
              
              // Combine events from both sources
              $dateKey = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
              $allDayEvents = array_merge($eventsByDay[$d] ?? [], $eventsByDate[$dateKey] ?? []);
              
              if (!empty($allDayEvents)) {
                  foreach (array_slice($allDayEvents, 0, 3) as $ev) {
                      if (isset($ev['event_type'])) {
                          // From eventsByDay (application events)
                          $cls = $evTypeColor[$ev['event_type']] ?? 'cal-event-note';
                          echo '<div class="cal-event ' . $cls . '">' . htmlspecialchars($ev['title']) . '</div>';
                      } else {
                          // From eventsByDate (upcoming events and reminders)
                          $badge = $ev['is_reminder'] ? '🔔' : '📌';
                          $color = $ev['type'] === 'interview' ? 'cal-event-interview' : 
                                   ($ev['type'] === 'reminder' ? 'cal-event-note' : 
                                   ($ev['type'] === 'deadline' ? 'cal-event-deadline' : 'cal-event-follow_up'));
                          echo '<div class="cal-event ' . $color . '">' . $badge . ' ' . htmlspecialchars(substr($ev['title'], 0, 20)) . '</div>';
                      }
                  }
                  if (count($allDayEvents) > 3) {
                      echo '<div class="cal-event" style="background:#f0f4f9;color:var(--text-secondary)">+' . (count($allDayEvents)-3) . ' more</div>';
                  }
              }
              echo '</div>';
              $cellCount++;
          }

          // Next month days
          $remaining = 42 - $cellCount;
          for ($d = 1; $d <= $remaining; $d++) {
              echo '<div class="cal-day other-month"><div class="cal-day-num">' . $d . '</div></div>';
          }
          ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Upcoming Events -->
    <div class="card mb-4">
      <div class="card-header"><span>Upcoming Events</span></div>
      <div class="card-body p-0">
        <?php
        // Merge upcoming events and reminders, sorted by date
        $allEvents = [];
        
        // Add application events
        foreach ($upcoming as $ev) {
          $allEvents[] = [
            'event_date' => $ev['event_date'],
            'title' => $ev['title'],
            'company' => $ev['company'] ?? '',
            'event_type' => $ev['event_type'] ?? 'note',
            'is_reminder' => false
          ];
        }
        
        // Add reminders
        foreach ($reminders as $rem) {
          if (strtotime($rem['remind_at']) > time()) { // Only future reminders
            $allEvents[] = [
              'event_date' => $rem['remind_at'],
              'title' => $rem['title'],
              'company' => $rem['application_id'] ? '📒 Reminder' : '',
              'event_type' => 'reminder',
              'is_reminder' => true
            ];
          }
        }
        
        // Sort by date
        usort($allEvents, function($a, $b) {
          return strtotime($a['event_date']) - strtotime($b['event_date']);
        });
        
        // Limit to 5
        $allEvents = array_slice($allEvents, 0, 5);
        
        if (empty($allEvents)): ?>
          <p class="text-center text-muted py-4" style="font-size:13px">No upcoming events</p>
        <?php else: ?>
        <?php
        $typeColor = ['interview'=>'var(--brand)','deadline'=>'var(--rejected)','follow_up'=>'var(--offer)','note'=>'#92400e','reminder'=>'#8b5cf6'];
        foreach ($allEvents as $ev):
          $color = $typeColor[$ev['event_type']] ?? 'var(--text-secondary)';
        ?>
        <div class="d-flex gap-3 p-3 border-bottom">
          <div class="text-center" style="min-width:42px">
            <div style="font-size:11px;font-weight:700;color:var(--brand);text-transform:uppercase"><?= date('M',strtotime($ev['event_date'])) ?></div>
            <div style="font-size:22px;font-weight:700;line-height:1;color:var(--text-primary)"><?= date('d',strtotime($ev['event_date'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:<?= $color ?>">
              <?= $ev['is_reminder'] ? '🔔 REMINDER' : h(str_replace('_',' ',strtoupper($ev['event_type']))) ?>
            </div>
            <div style="font-weight:600;font-size:13px"><?= h($ev['title']) ?></div>
            <div style="font-size:12px;color:var(--text-secondary)"><?php 
              if ($ev['company']) echo h($ev['company']) . ' &bull; '; 
              echo date('g:i A',strtotime($ev['event_date'])); 
            ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="text-center p-3">
          <a href="reminders.php" class="text-decoration-none" style="font-size:13px;color:var(--brand)">View All Reminders</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Monthly status -->
    <div class="card" style="background:#1a1a2e;color:#fff">
      <div class="card-body p-3">
        <div style="font-size:12px;opacity:.6;margin-bottom:4px">MONTHLY STATUS</div>
        <div style="font-size:28px;font-weight:700;font-family:'Sora',sans-serif;margin-bottom:16px">
          <?= array_sum($counts) ?> <span style="font-size:14px;font-weight:400;opacity:.6">Active Apps</span>
        </div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:13px;opacity:.8">Interviews Scheduled</span><b><?= $counts['interviewing'] ?></b></div>
        <div class="progress mb-3" style="height:4px;background:rgba(255,255,255,.15)">
          <div class="progress-bar" style="width:<?= array_sum($counts)>0?round($counts['interviewing']/array_sum($counts)*100):0 ?>%;background:var(--brand)"></div>
        </div>
        <div class="d-flex justify-content-between mb-1"><span style="font-size:13px;opacity:.8">Follow-ups Pending</span><b><?= $counts['applied'] ?></b></div>
        <div class="progress" style="height:4px;background:rgba(255,255,255,.15)">
          <div class="progress-bar" style="width:<?= array_sum($counts)>0?round($counts['applied']/array_sum($counts)*100):0 ?>%;background:var(--offer)"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Schedule New Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted" style="font-size:13px">To add an event, go to a specific application and use the <strong>Add Event</strong> button on its detail page.</p>
        <a href="<?= APP_URL ?>/applications.php" class="btn btn-primary w-100">Go to Applications</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../views/partials/header.php';
echo $content;
include __DIR__ . '/../views/partials/footer.php';
