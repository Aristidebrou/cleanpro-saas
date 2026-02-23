<?php

declare(strict_types=1);

namespace CleanPro\Utils;

class Response
{
    /**
     * Envoi d'une réponse JSON
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        echo json_encode($data);
        exit;
    }

    /**
     * Réponse de succès
     */
    public static function success(array $data = [], string $message = 'Success'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 200);
    }

    /**
     * Réponse d'erreur
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Réponse 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    /**
     * Réponse 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    /**
     * Réponse 404 Not Found
     */
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Réponse 422 Validation Error
     */
    public static function validationError(array $errors): void
    {
        self::error('Validation failed', 422, $errors);
    }

    /**
     * Réponse 500 Server Error
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }
}
