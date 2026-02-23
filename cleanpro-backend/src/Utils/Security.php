<?php

declare(strict_types=1);

namespace CleanPro\Utils;

class Security
{
    /**
     * Hachage sécurisé avec Argon2id
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Vérification du mot de passe
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Génération d'un token CSRF
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }

    /**
     * Validation du token CSRF
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Vérification de l'expiration (2 heures)
        if (isset($_SESSION['csrf_token_time']) && 
            (time() - $_SESSION['csrf_token_time']) > 7200) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Nettoyage XSS
     */
    public static function sanitize(string $data): string
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $data;
    }

    /**
     * Nettoyage pour affichage HTML
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Génération d'un token aléatoire sécurisé
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validation d'email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Rate limiting simple (basé sur IP)
     */
    public static function checkRateLimit(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        $key = 'rate_limit_' . md5($identifier);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
            return true;
        }

        $data = $_SESSION[$key];
        
        // Reset si la fenêtre est dépassée
        if ($now - $data['first_attempt'] > $windowSeconds) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
            return true;
        }

        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }

        $_SESSION[$key]['attempts']++;
        return true;
    }
}
