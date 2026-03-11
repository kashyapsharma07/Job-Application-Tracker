<?php
// public/logout.php
require_once __DIR__ . '/../src/bootstrap.php';
Auth::logout();
header('Location: ' . APP_URL . '/login.php');
exit;
