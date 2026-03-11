<?php
// config/config.php

define('APP_NAME', 'JobTracker');
define('APP_URL', 'http://localhost/jobtracker');
define('APP_VERSION', '1.0.0');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobtracker');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'jt_session');

// File uploads
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/resumes/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_MIME_TYPES', ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Email (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM_NAME', 'JobTracker');
define('SMTP_FROM_EMAIL', 'noreply@jobtracker.app');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('HASH_COST', 12);
