<?php
// src/helpers/Auth.php

class Auth
{

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Always use a single session name throughout the website
            session_name('jt_session'); // Use a constant name for all pages
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            ]);
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        
        // Log the login event
        try {
            Database::getInstance()->prepare(
                'INSERT INTO login_logs (user_id, ip_address, status) VALUES (?, ?, ?)'
            )->execute([$user['id'], self::getClientIp(), 'success']);
        } catch (Exception $e) {
            // Login table might not exist yet - that's okay
        }
        
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['user_name']    = $user['name'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_plan']    = $user['plan'];
        $_SESSION['user_role']    = $user['role'] ?? 'user';
        $_SESSION['user_avatar']  = $user['avatar'] ?? null;
        $_SESSION['user_is_premium'] = (bool)($user['is_premium'] ?? 0);
        $_SESSION['logged_in']    = true;
        $_SESSION['user_ip']      = self::getClientIp();
        $_SESSION['user_agent']   = self::getUserAgent();
        $_SESSION['login_time']   = time();
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): array
    {
        return [
            'id'         => $_SESSION['user_id'] ?? null,
            'name'       => $_SESSION['user_name'] ?? '',
            'email'      => $_SESSION['user_email'] ?? '',
            'plan'       => $_SESSION['user_plan'] ?? 'free',
            'role'       => $_SESSION['user_role'] ?? 'user',
            'avatar'     => $_SESSION['user_avatar'] ?? null,
            'is_premium' => (bool)($_SESSION['user_is_premium'] ?? false),
        ];
    }

    public static function require(): void
    {
        self::start();
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            self::logout();
            redirect('/login.php?error=session_expired');
        }
        $_SESSION['last_activity'] = time();
        
        // Check if user is logged in
        if (!self::check()) {
            redirect('/login.php');
        }
        
        // IP address binding - detect session hijacking
        if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== self::getClientIp()) {
            self::logout();
            redirect('/login.php?error=security');
        }
        
        // User-Agent binding - detect session hijacking
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== self::getUserAgent()) {
            self::logout();
            redirect('/login.php?error=security');
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verifyCsrf(string $token): bool
    {
        self::start();
        return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
    }

    private static function getClientIp(): string
    {
        // Check for IP from shared internet (behind proxy)
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP from forwarded internet (behind firewall)
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        // Normal IP
        else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }

    private static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }


    // Check if current user is admin
    public static function isAdmin(): bool
    {
        self::start();
        return ($_SESSION['user_role'] ?? 'user') === 'admin';
    }

    // Require admin access - use at top of admin pages
    public static function requireAdmin(): void
    {
        self::start();
        self::require(); // First check authentication
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Access Denied. Admin only.';
            exit;
        }
    }

    // Check if user account is locked (brute force protection)
    public static function isAccountLocked(string $email): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT COUNT(*) FROM login_logs WHERE email = :email AND status = "failed" AND login_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );
            $stmt->execute([':email' => $email]);
            $failedAttempts = (int)$stmt->fetchColumn();
            return $failedAttempts >= 5; // Lock after 5 failed attempts in 15 minutes
        } catch (Exception $e) {
            return false; // If query fails, allow login
        }
    }

    // Log failed login attempt
    public static function logFailedLogin(string $email): void
    {
        try {
            Database::getInstance()->prepare(
                'INSERT INTO login_logs (email, ip_address, status) VALUES (?, ?, ?)'
            )->execute([$email, self::getClientIp(), 'failed']);
        } catch (Exception $e) {
            // Log table might not exist
        }
    }

    // Verify that 2FA is completed before allowing dashboard access
    public static function require2faIfEnabled(): void
    {
        self::start();
        $userModel = new User();
        $user = $userModel->findById(self::id());
        
        if ($user && !empty($user['twofa_enabled']) && $user['twofa_enabled'] == 1) {
            // If user has 2FA enabled but hasn't verified it yet
            if (empty($_SESSION['2fa_verified'])) {
                redirect('/2fa-login.php');
            }
        }
    }
}
