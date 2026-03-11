<?php
// src/helpers/Auth.php

class Auth
{

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
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
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_plan'] = $user['plan'];
        $_SESSION['logged_in'] = true;
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
}
