<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Utils\Response;
use CleanPro\Utils\JWTAuth;

abstract class Controller
{
    /**
     * Récupération des données JSON de la requête
     */
    protected function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }

    /**
     * Validation des données requises
     */
    protected function validateRequired(array $data, array $required): ?array
    {
        $errors = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "Le champ $field est requis";
            }
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Récupération de l'utilisateur authentifié
     */
    protected function getAuthUser(): ?array
    {
        $token = JWTAuth::getTokenFromHeader();
        
        if (!$token) {
            return null;
        }

        $payload = JWTAuth::validateToken($token);
        return $payload;
    }

    /**
     * Vérification de l'authentification
     */
    protected function requireAuth(): array
    {
        $user = $this->getAuthUser();
        
        if (!$user) {
            Response::unauthorized('Token invalide ou expiré');
        }

        return $user;
    }

    /**
     * Vérification du rôle
     */
    protected function requireRole(array $user, string $role): void
    {
        if ($user['role'] !== $role && $user['role'] !== 'admin') {
            Response::forbidden('Accès non autorisé');
        }
    }

    /**
     * Pagination
     */
    protected function paginate(array $items, int $page = 1, int $perPage = 20): array
    {
        $total = count($items);
        $totalPages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'data' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ];
    }
}
