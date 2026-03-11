# JobTracker — Job Application Management System

A full-featured PHP/MySQL web application for job seekers to efficiently track applications, manage resume versions, set reminders, and view analytics.

---

## Features

- **Authentication** — Secure register/login with PHP sessions, CSRF protection, and bcrypt password hashing
- **Application Management** — Full CRUD with Kanban board and list view. Track status (Wishlist → Applied → Interviewing → Offer/Rejected)
- **Resume Versions** — Upload and manage multiple resume versions (PDF/DOCX). Link specific resume versions to each application
- **Timeline & Notes** — Per-application event timeline (interviews, deadlines, follow-ups, notes) and rich notes
- **Calendar View** — Monthly calendar showing all scheduled events across applications
- **Reminders** — Schedule follow-up reminders linked to applications, with automated email notifications
- **Analytics Dashboard** — Applications over time (Chart.js), stage breakdown, top companies, recent activity
- **Settings** — Profile management, notification preferences, password change
- **Cron Job** — Automated reminder email delivery every 5 minutes

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+ (plain OOP, MVC-inspired) |
| Frontend | HTML5, CSS3, Bootstrap 5.3, Chart.js 4, Bootstrap Icons |
| Database | MySQL 8.0 |
| Auth | PHP Sessions + CSRF tokens |
| Email | PHPMailer (SMTP) / PHP `mail()` fallback |
| Fonts | Sora (headings) + Inter (body) via Google Fonts |

---

## Directory Structure

```
jobtracker/
├── config/
│   ├── config.php          # App configuration (DB, SMTP, upload settings)
│   └── database.php        # PDO singleton
├── cron/
│   └── send_reminders.php  # Automated email reminder cron job
├── public/                 # Document root (point your web server here)
│   ├── css/app.css
│   ├── js/app.js
│   ├── uploads/resumes/    # Resume file storage (writable)
│   ├── index.php           # Redirect to dashboard/login
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── applications.php
│   ├── application-detail.php
│   ├── analytics.php
│   ├── calendar.php
│   ├── resumes.php
│   ├── reminders.php
│   └── settings.php
├── sql/
│   └── schema.sql          # Complete MySQL schema
├── src/
│   ├── bootstrap.php       # Autoloader + helpers
│   ├── helpers/
│   │   ├── Auth.php        # Session authentication + CSRF
│   │   └── Mailer.php      # Email helper (PHPMailer/mail fallback)
│   └── models/
│       ├── User.php
│       ├── Application.php
│       ├── Resume.php
│       └── Reminder.php
└── views/
    └── partials/
        ├── header.php      # Sidebar, topbar layout
        └── footer.php      # Scripts
```

---

## Setup Instructions

### 1. Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`
- MySQL 8.0+
- Apache/Nginx with `mod_rewrite` enabled
- (Optional) Composer for PHPMailer

### 2. Database Setup

```sql
-- In MySQL client or phpMyAdmin:
SOURCE /path/to/jobtracker/sql/schema.sql;
```

Or run manually:
```bash
mysql -u root -p < sql/schema.sql
```

### 3. Configuration

Edit `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobtracker');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// App URL (no trailing slash)
define('APP_URL', 'http://localhost/jobtracker/public');

// SMTP Email (for reminders)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your@gmail.com');
define('SMTP_PASS', 'your_app_password');  // Gmail App Password
```

### 4. Web Server Config

**Apache** — Point DocumentRoot to `public/` or use a virtual host:
```apache
<VirtualHost *:80>
    ServerName jobtracker.local
    DocumentRoot /var/www/html/jobtracker/public
    <Directory /var/www/html/jobtracker/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name jobtracker.local;
    root /var/www/html/jobtracker/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    # Block PHP in uploads
    location ~ ^/uploads/.*\.php$ { deny all; }
}
```

### 5. Permissions

```bash
chmod -R 755 public/
chmod -R 775 public/uploads/resumes/
chown -R www-data:www-data public/uploads/
```

### 6. (Optional) Install PHPMailer

```bash
composer require phpmailer/phpmailer
```
Then require the autoloader in `config/config.php`:
```php
require_once __DIR__ . '/../vendor/autoload.php';
```

### 7. Set Up Cron Job

```bash
# Edit crontab
crontab -e

# Add this line (runs every 5 minutes):
*/5 * * * * /usr/bin/php /var/www/html/jobtracker/cron/send_reminders.php >> /var/log/jobtracker_cron.log 2>&1
```

---

## Security Features

- **CSRF Protection** — Every POST request validated with CSRF tokens
- **Password Hashing** — bcrypt with cost factor 12
- **Session Security** — HttpOnly cookies, `session_regenerate_id()` on login, `SameSite=Lax`
- **SQL Injection Prevention** — All queries use PDO prepared statements
- **XSS Prevention** — All output HTML-escaped with `htmlspecialchars()`
- **File Upload Security** — MIME type validation, extension checking, random filenames, PHP blocked in upload directory
- **Directory Listing** — Disabled via `.htaccess`

---

## Environment Notes

- The upload directory `public/uploads/resumes/` must be writable by the web server
- For production, set `display_errors = Off` in PHP config
- Use HTTPS in production and update `session.cookie_secure = 1`
- For Gmail SMTP, generate an [App Password](https://support.google.com/accounts/answer/185833)

---

## License

MIT License — Free to use and modify.
