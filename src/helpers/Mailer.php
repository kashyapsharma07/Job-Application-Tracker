<?php
// src/helpers/Mailer.php
// Uses PHPMailer (install via Composer: composer require phpmailer/phpmailer)
// Falls back to PHP mail() if PHPMailer not available

class Mailer {

    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        // Try PHPMailer if available
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendWithPHPMailer($to, $toName, $subject, $htmlBody);
        }
        // Fallback: PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        return mail($to, $subject, $htmlBody, $headers);
    }

    private static function sendWithPHPMailer(string $to, string $toName, string $subject, string $html): bool {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    public static function reminderEmail(array $reminder): string {
        $company  = htmlspecialchars($reminder['company'] ?? 'a company');
        $jobTitle = htmlspecialchars($reminder['job_title'] ?? 'a position');
        $title    = htmlspecialchars($reminder['title']);
        $desc     = htmlspecialchars($reminder['description'] ?? '');
        $time     = date('M j, Y \a\t g:i A', strtotime($reminder['remind_at']));
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><style>
            body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px}
            .card{background:#fff;border-radius:8px;padding:30px;max-width:500px;margin:0 auto;box-shadow:0 2px 8px rgba(0,0,0,.08)}
            .header{color:#1a73e8;font-size:22px;font-weight:bold;margin-bottom:10px}
            .meta{color:#555;margin-bottom:16px}
            .pill{display:inline-block;background:#e8f0fe;color:#1a73e8;border-radius:20px;padding:4px 12px;font-size:13px}
            .footer{margin-top:20px;font-size:12px;color:#999}
        </style></head>
        <body>
        <div class="card">
          <div class="header">⏰ Reminder: {$title}</div>
          <div class="meta">Scheduled for <strong>{$time}</strong></div>
          <p><span class="pill">{$company}</span> &nbsp; <span class="pill">{$jobTitle}</span></p>
          {$desc}
          <div class="footer">JobTracker — manage your job search smarter.</div>
        </div>
        </body></html>
        HTML;
    }
}
