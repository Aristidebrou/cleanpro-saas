<?php

declare(strict_types=1);

namespace CleanPro\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JWTAuth
{
    private static string $secret;
    private static string $algorithm = 'HS256';

    private static function init(): void
    {
        if (empty(self::$secret)) {
            self::$secret = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_in_production';
        }
    }

    /**
     * Génération d'un token JWT
     */
    public static function generateToken(array $payload, ?int $expiration = null): string
    {
        self::init();

        $issuedAt = time();
        $expire = $issuedAt + ($expiration ?? ($_ENV['JWT_EXPIRATION'] ?? 86400));

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
            'jti' => bin2hex(random_bytes(16))
        ]);

        return JWT::encode($tokenPayload, self::$secret, self::$algorithm);
    }

    /**
     * Décodage et validation d'un token JWT
     */
    public static function validateToken(string $token): ?array
    {
        self::init();

        try {
            $decoded = JWT::decode($token, new Key(self::$secret, self::$algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            return null;
        } catch (SignatureInvalidException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extraction du token depuis les headers
     */
    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Rafraîchissement d'un token
     */
    public static function refreshToken(string $token): ?string
    {
        $payload = self::validateToken($token);
        
        if ($payload === null) {
            return null;
        }

        // Supprimer les claims JWT internes
        unset($payload['iat'], $payload['exp'], $payload['jti']);

        return self::generateToken($payload);
    }
}
