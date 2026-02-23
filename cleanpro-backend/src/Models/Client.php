<?php

declare(strict_types=1);

namespace CleanPro\Models;

class Client extends Model
{
    protected string $table = 'clients';

    const BILLING_TYPE_ONE_TIME = 'one_time';
    const BILLING_TYPE_MONTHLY = 'monthly';
    const BILLING_TYPE_ANNUAL = 'annual';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Création d'un client avec utilisateur associé
     */
    public function createClient(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_ACTIVE;
        }

        return $this->create($data);
    }

    /**
     * Récupération des clients avec détails utilisateur
     */
    public function getClientsWithDetails(): array
    {
        $sql = "
            SELECT c.*, u.email, u.first_name, u.last_name, u.phone
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.id
            ORDER BY c.company_name ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Récupération d'un client avec toutes ses informations
     */
    public function getClientWithDetails(int $clientId): ?array
    {
        $sql = "
            SELECT c.*, u.email, u.first_name, u.last_name, u.phone
            FROM {$this->table} c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $clientId]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Récupération des clients par type de facturation
     */
    public function getByBillingType(string $billingType): array
    {
        return $this->where(
            ['billing_type' => $billingType, 'status' => self::STATUS_ACTIVE],
            ['company_name' => 'ASC']
        );
    }

    /**
     * Mise à jour du quota utilisé
     */
    public function updateQuotaUsed(int $clientId, int $increment = 1): bool
    {
        $sql = "
            UPDATE {$this->table} 
            SET quota_used = quota_used + :increment,
                updated_at = :updated_at
            WHERE id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':increment' => $increment,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $clientId
        ]);
    }

    /**
     * Réinitialisation mensuelle des quotas
     */
    public function resetMonthlyQuotas(): int
    {
        $sql = "
            UPDATE {$this->table} 
            SET quota_used = 0,
                updated_at = :updated_at
            WHERE billing_type = :billing_type
            AND status = :status
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':updated_at' => date('Y-m-d H:i:s'),
            ':billing_type' => self::BILLING_TYPE_MONTHLY,
            ':status' => self::STATUS_ACTIVE
        ]);

        return $stmt->rowCount();
    }

    /**
     * Récupération des clients avec quota dépassé
     */
    public function getClientsWithExceededQuota(): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE quota_used >= monthly_quota
            AND billing_type IN (:monthly, :annual)
            AND status = :status
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':monthly' => self::BILLING_TYPE_MONTHLY,
            ':annual' => self::BILLING_TYPE_ANNUAL,
            ':status' => self::STATUS_ACTIVE
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Statistiques clients
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'by_billing_type' => [],
            'monthly_recurring' => 0
        ];

        // Total clients
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        $stats['total'] = (int) $stmt->fetchColumn();

        // Clients actifs
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = :status", [':status' => self::STATUS_ACTIVE]);
        $stats['active'] = (int) $stmt->fetchColumn();

        // Par type de facturation
        $stmt = $this->db->query("
            SELECT billing_type, COUNT(*) as count 
            FROM {$this->table} 
            GROUP BY billing_type
        ");
        $stats['by_billing_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Revenu récurrent mensuel
        $stmt = $this->db->query("
            SELECT SUM(monthly_amount) as total 
            FROM {$this->table} 
            WHERE billing_type IN ('monthly', 'annual') 
            AND status = 'active'
        ");
        $stats['monthly_recurring'] = (float) ($stmt->fetchColumn() ?? 0);

        return $stats;
    }
}
