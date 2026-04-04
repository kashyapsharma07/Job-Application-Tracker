<?php
// src/bootstrap.php

// Load Composer packages (2FA, JWT, QR Code)
require_once __DIR__ . '/../vendor/autoload.php';
use PragmaRX\Google2FA\Google2FA;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers/Auth.php';
require_once __DIR__ . '/helpers/Mailer.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Application.php';
require_once __DIR__ . '/models/Resume.php';
require_once __DIR__ . '/models/Reminder.php';

Auth::start();

// Simple base path helper - returns /jobtracker/ or just / depending on deployment
function base($path = '') {
    $basePath = '/jobtracker';
    return $basePath . $path;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECURITY HEADERS — Protect against common web attacks
// ═══════════════════════════════════════════════════════════════════════════

// Prevent browsers from MIME-sniffing (e.g., treating CSS as JS)
header('X-Content-Type-Options: nosniff');

// Prevent clickjacking attacks (page cannot be embedded in iframe)
header('X-Frame-Options: DENY');

// Enable XSS protection in older browsers
header('X-XSS-Protection: 1; mode=block');

// Enforce HTTPS for all future requests (1 year, include subdomains)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Content Security Policy — disabled for development, enable on production
// header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://checkout.razorpay.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https:; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https:; img-src 'self' data: https: http:; connect-src 'self' https: http:;");

// Prevent referrer leaking on external links
header('Referrer-Policy: strict-origin-when-cross-origin');


// Helper functions
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $path): void {
    header('Location: ' . APP_URL . $path);
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function validatePassword(string $password): array {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter (a-z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9).';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*).';
    }
    
    return $errors;
}

function statusLabel(string $status): string {
    return match($status) {
        'wishlist'     => 'Wishlist',
        'applied'      => 'Applied',
        'interviewing' => 'Interviewing',
        'offer'        => 'Offer',
        'rejected'     => 'Rejected',
        default        => ucfirst($status),
    };
}

function statusColor(string $status): string {
    return match($status) {
        'wishlist'     => 'secondary',
        'applied'      => 'primary',
        'interviewing' => 'warning',
        'offer'        => 'success',
        'rejected'     => 'danger',
        default        => 'secondary',
    };
}
