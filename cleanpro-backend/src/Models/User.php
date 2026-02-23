<?php

declare(strict_types=1);

namespace CleanPro\Models;

use CleanPro\Utils\Security;

class User extends Model
{
    protected string $table = 'users';

    const ROLE_ADMIN = 'admin';
    const ROLE_AGENT = 'agent';
    const ROLE_CLIENT = 'client';

    /**
     * Création d'un utilisateur avec mot de passe haché
     */
    public function createUser(array $data): int
    {
        if (isset($data['password'])) {
            $data['password_hash'] = Security::hashPassword($data['password']);
            unset($data['password']);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * Authentification
     */
    public function authenticate(string $email, string $password): ?array
    {
        $users = $this->where(['email' => $email], [], 1);
        
        if (empty($users)) {
            return null;
        }

        $user = $users[0];

        if (!Security::verifyPassword($password, $user['password_hash'])) {
            return null;
        }

        // Mise à jour de la dernière connexion
        $this->update($user['id'], [
            'last_login' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Ne pas retourner le hash du mot de passe
        unset($user['password_hash']);
        
        return $user;
    }

    /**
     * Recherche par email
     */
    public function findByEmail(string $email): ?array
    {
        $users = $this->where(['email' => $email], [], 1);
        return $users[0] ?? null;
    }

    /**
     * Récupération des agents actifs
     */
    public function getActiveAgents(): array
    {
        return $this->where(
            ['role' => self::ROLE_AGENT, 'is_active' => 1],
            ['last_name' => 'ASC', 'first_name' => 'ASC']
        );
    }

    /**
     * Récupération des clients
     */
    public function getClients(): array
    {
        return $this->where(
            ['role' => self::ROLE_CLIENT],
            ['company_name' => 'ASC', 'last_name' => 'ASC']
        );
    }

    /**
     * Changement de mot de passe
     */
    public function changePassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password_hash' => Security::hashPassword($newPassword),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Vérification si l'email existe déjà
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE email = :email";
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }
}
