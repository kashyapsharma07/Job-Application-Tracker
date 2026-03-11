<?php
require_once __DIR__ . '/../src/bootstrap.php';
if (Auth::check()) {
    header('Location: ' . APP_URL . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
