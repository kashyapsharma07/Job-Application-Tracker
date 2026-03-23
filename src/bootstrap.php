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
