<?php
// config/config.php

define('APP_NAME', 'JobTracker');
define('APP_URL', 'https://unarousable-nontraceable-kira.ngrok-free.dev/jobtracker');
define('APP_VERSION', '1.0.0');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobtracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'jt_session');

// File uploads
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/resumes/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Email (SMTP) — For Gmail: Enable 2FA, then generate an App Password
// 1. Go to myaccount.google.com/apppasswords
// 2. Select "Mail" and "Windows Computer" (or your device)
// 3. Copy the 16-character password and paste it in SMTP_PASS below
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'kashyaps1304@gmail.com');  // Your Gmail address for authentication
define('SMTP_PASS', 'oywtozsocyqgwnfb');   // Your App Password (16 characters)
define('SMTP_FROM_NAME', 'No-Reply JobTracker');  // How sender appears in email
define('SMTP_FROM_EMAIL', 'kashyaps1304@gmail.com');  // Must match SMTP_USER for Gmail

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('HASH_COST', 12);
