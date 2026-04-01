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
        
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_plan'] = $user['plan'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['logged_in'] = true;
        $_SESSION['user_ip']   = self::getClientIp();
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
            'id'    => $_SESSION['user_id'] ?? null,
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'plan'  => $_SESSION['user_plan'] ?? 'free',
            'role'  => $_SESSION['user_role'] ?? 'user',
        ];
    }

    public static function require(): void
    {
        self::start();
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            self::logout();
            redirect('/login.php');
        }
        $_SESSION['last_activity'] = time();
        if (!self::check()) {
            redirect('/login.php');
        }
        // IP address binding - detect session hijacking
        if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== self::getClientIp()) {
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
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Access Denied. Admin only.';
            exit;
        }
    }
}
