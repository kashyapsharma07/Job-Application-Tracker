# JobTracker — Job Application Management System

A full-featured PHP/MySQL web application for job seekers to efficiently track applications, manage resume versions, set reminders, leverage AI resume analysis, and view analytics.

---

## Features

- **Authentication & Security** — Secure register/login with PHP sessions, Two-Factor Authentication (2FA), CSRF protection, and bcrypt password hashing
- **AI Career Coach** — Powered by **Google Gemini API** (`gemini-2.5-flash`), analyzes uploaded PDF resumes and provides ATS scoring, brutal FAANG recruiter feedback, and actionable bullet-point rewrites
- **Application Management** — Full CRUD with Kanban board and list view. Track status (Wishlist → Applied → Interviewing → Offer/Rejected)
- **Resume Versions** — Upload and manage multiple resume versions (PDF/DOCX). Link specific resume versions to each application
- **Timeline & Notes** — Per-application event timeline (interviews, deadlines, follow-ups, notes) and rich notes
- **Calendar View** — Monthly calendar showing all scheduled events across applications
- **Reminders** — Schedule follow-up reminders linked to applications, with automated email notifications
- **Analytics Dashboard** — Applications over time (Chart.js), conversion funnel, stage breakdown, top companies, recent activity
- **Premium Subscription & Payments** — Integrated Razorpay payment gateway for unlocking premium AI features
- **Settings** — Profile management, notification preferences, 2FA setup, and security management
- **Cron Job** — Automated reminder email delivery every 5 minutes

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+ (plain OOP, MVC-inspired) |
| AI Integration | Google Gemini API (`gemini-2.5-flash`) |
| Payments | Razorpay API |
| Frontend | HTML5, Vanilla CSS, Bootstrap 5.3, Chart.js 4, Bootstrap Icons |
| Database | MySQL 8.0 |
| Auth | PHP Sessions + 2FA + CSRF tokens |
| Email | PHPMailer (SMTP) / PHP `mail()` fallback |
| Fonts | Sora (headings) + Inter (body) via Google Fonts |

---

## Directory Structure

```
jobtracker/
├── config/
│   ├── config.php          # App configuration (DB, SMTP, Gemini API, upload settings)
│   └── database.php        # PDO singleton
├── cron/
│   └── send_reminders.php  # Automated email reminder cron job
├── public/                 # Document root (point your web server here)
│   ├── css/app.css
│   ├── js/app.js
│   ├── js/ai-resume.js     # AI Career Coach frontend script
│   ├── uploads/resumes/    # Resume file storage (writable)
│   ├── index.php           # Redirect to dashboard/login
│   ├── login.php
│   ├── register.php
│   ├── 2fa-login.php
│   ├── 2fa-setup.php
│   ├── dashboard.php
│   ├── applications.php
│   ├── application-detail.php
│   ├── ai_analyse.php      # Gemini AI analysis endpoint
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
│   │   ├── Auth.php        # Session authentication + 2FA + CSRF
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

## Quick Start & Running Locally

### 1. Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `curl`
- MySQL 8.0+
- XAMPP / Apache / Built-in PHP CLI Server

### 2. Database Setup

Import schema into your MySQL database (`jobtracker`):
```bash
mysql -u root -p jobtracker < sql/schema.sql
```

### 3. Environment Configuration (`.env`)

Create a `.env` file in the project root:
```env
RAZORPAY_KEY_ID=your_razorpay_key_id
RAZORPAY_KEY_SECRET=your_razorpay_key_secret

GEMINI_API_KEY=your_google_gemini_api_key
```

### 4. Running the Server

#### Option A: PHP Built-in Server (Quickest)
```bash
cd /path/to/jobtracker
php -S localhost:8000 -t public
```
Access in browser: `http://localhost:8000`

#### Option B: Ngrok Tunneling
If running locally and tunneling via ngrok:
```bash
ngrok http 8000
```

---

## Security Features

- **CSRF Protection** — Every POST request validated with CSRF tokens
- **Two-Factor Authentication (2FA)** — Optional 2FA security layer for user accounts
- **Environment Isolation** — Sensitive API keys kept in `.env` (excluded from source control)
- **Password Hashing** — bcrypt with cost factor 12
- **Session Security** — HttpOnly cookies, `session_regenerate_id()` on login, `SameSite=Lax`
- **SQL Injection Prevention** — All queries use PDO prepared statements
- **XSS Prevention** — All output HTML-escaped with `htmlspecialchars()`
- **File Upload Security** — MIME type validation, extension checking, random filenames, PHP execution blocked in upload directory

---

## License

MIT License — Free to use and modify.
