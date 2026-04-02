keep height and#!/usr/bin/env php
<?php
/**
 * cron/send_reminders.php
 * Run every 5 minutes via cron:
 *   * /5 * * * * /usr/bin/php /var/www/html/jobtracker/cron/send_reminders.php >> /var/log/jobtracker_cron.log 2>&1
 */

define('CRON_RUN', true);

// Bootstrap
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers/Mailer.php';
require_once __DIR__ . '/../src/models/Reminder.php';

$remModel = new Reminder();
$pending  = $remModel->getPending();

if (empty($pending)) {
    echo date('[Y-m-d H:i:s]') . " No pending reminders.\n";
    exit(0);
}

$sent = 0;
foreach ($pending as $reminder) {
    $html    = Mailer::reminderEmail($reminder);
    $subject = '[JobTracker] Reminder: ' . $reminder['title'];
    $ok      = Mailer::send($reminder['email'], $reminder['user_name'], $subject, $html);
    if ($ok) {
        $remModel->markSent($reminder['id']);
        $sent++;
        echo date('[Y-m-d H:i:s]') . " Sent reminder #{$reminder['id']} to {$reminder['email']}\n";
    } else {
        echo date('[Y-m-d H:i:s]') . " FAILED reminder #{$reminder['id']} to {$reminder['email']}\n";
    }
}

echo date('[Y-m-d H:i:s]') . " Done. Sent $sent / " . count($pending) . " reminders.\n";
