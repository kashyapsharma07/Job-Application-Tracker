<?php
// public/diagnose.php
// Temporary diagnostic script to troubleshoot production rendering issues.

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== JOBTRACKER PRODUCTION DIAGNOSTIC ===\n\n";

// 1. PHP & Environment Info
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "APP_URL Constant: " . (defined('APP_URL') ? APP_URL : 'NOT DEFINED') . "\n";
echo "APP_URL Env: " . getenv('APP_URL') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'None') . "\n";
echo "HTTPS Server Var: " . ($_SERVER['HTTPS'] ?? 'None') . "\n";
echo "HTTP_X_FORWARDED_PROTO: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'None') . "\n";
echo "\n";

// 2. Session Info
echo "=== SESSION DATA ===\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "Session Status: Active (ID: " . session_id() . ")\n";
    echo "Session Name: " . session_name() . "\n";
    print_r($_SESSION);
} else {
    echo "Session Status: Inactive\n";
}
echo "\n";

// 3. Database Connection
echo "=== DATABASE CONNECTION ===\n";
try {
    $db = Database::getInstance();
    echo "Database: Connected Successfully!\n";
    
    // Check if users table exists and has data
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total registered users in DB: $count\n";
    
} catch (Exception $e) {
    echo "Database Connection Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. File Check
echo "=== FILES CHECK ===\n";
$files = [
    'config/config.php' => __DIR__ . '/../config/config.php',
    'views/partials/header.php' => __DIR__ . '/../views/partials/header.php',
    'views/partials/footer.php' => __DIR__ . '/../views/partials/footer.php',
    'public/css/app.css' => __DIR__ . '/css/app.css',
    'public/js/app.js' => __DIR__ . '/js/app.js'
];

foreach ($files as $name => $path) {
    echo "$name: ";
    if (file_exists($path)) {
        echo "EXISTS (Size: " . filesize($path) . " bytes, Readable: " . (is_readable($path) ? 'Yes' : 'No') . ")\n";
    } else {
        echo "DOES NOT EXIST\n";
    }
}
echo "\n";

// 5. Test Render Header in sandbox
echo "=== HEADER TEST RENDER ===\n";
try {
    ob_start();
    // Temporarily set a dummy user if not logged in to prevent crashes
    $temp_session = false;
    if (!isset($_SESSION['user'])) {
        $_SESSION['user'] = ['id' => 9999, 'name' => 'Diag User', 'plan' => 'free', 'role' => 'user'];
        $temp_session = true;
    }
    
    $pageTitle = 'Diagnostic Test';
    $currentPage = 'diagnostic';
    
    include __DIR__ . '/../views/partials/header.php';
    $header_html = ob_get_clean();
    
    if ($temp_session) {
        unset($_SESSION['user']);
    }
    
    echo "Header rendered successfully! (HTML length: " . strlen($header_html) . " characters)\n";
    echo "First 200 characters of rendered header:\n";
    echo substr($header_html, 0, 200) . "...\n";
} catch (Throwable $e) {
    ob_get_clean();
    echo "Header render failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";
