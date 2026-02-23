<?php

declare(strict_types=1);

namespace CleanPro\Models;

class Intervention extends Model
{
    protected string $table = 'interventions';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_VALIDATED = 'validated';

    const TYPE_ONE_TIME = 'one_time';
    const TYPE_RECURRING = 'recurring';

    /**
     * Création d'une intervention
     */
    public function createIntervention(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_SCHEDULED;
        }

        if (!isset($data['reference'])) {
            $data['reference'] = $this->generateReference();
        }

        return $this->create($data);
    }

    /**
     * Génération d'une référence unique
     */
    private function generateReference(): string
    {
        $prefix = 'INT-' . date('Y');
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE reference LIKE :prefix";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $count = (int) $stmt->fetchColumn();
        
        return $prefix . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Récupération des interventions avec détails complets
     */
    public function getInterventionsWithDetails(array $filters = []): array
    {
        $sql = "
            SELECT i.*, 
                   c.company_name as client_company,
                   c.address as client_address,
                   c.city as client_city,
                   c.postal_code as client_postal_code,
                   CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                   u.phone as agent_phone
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.agent_id = u.id
            WHERE 1=1
        ";
        
        $params = [];

        if (isset($filters['client_id'])) {
            $sql .= " AND i.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (isset($filters['agent_id'])) {
            $sql .= " AND i.agent_id = :agent_id";
            $params[':agent_id'] = $filters['agent_id'];
        }

        if (isset($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $sql .= " AND i.scheduled_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND i.scheduled_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY i.scheduled_date DESC, i.scheduled_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Récupération d'une intervention avec tous ses détails
     */
    public function getInterventionWithDetails(int $interventionId): ?array
    {
        $sql = "
            SELECT i.*, 
                   c.company_name as client_company,
                   c.address as client_address,
                   c.city as client_city,
                   c.postal_code as client_postal_code,
                   c.contact_name as client_contact,
                   c.contact_phone as client_phone,
                   CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                   u.phone as agent_phone,
                   u.email as agent_email
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.agent_id = u.id
            WHERE i.id = :id
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $interventionId]);
        
        $intervention = $stmt->fetch();
        
        if (!$intervention) {
            return null;
        }

        // Récupération des services associés
        $intervention['services'] = $this->getInterventionServices($interventionId);

        return $intervention;
    }

    /**
     * Récupération des services d'une intervention
     */
    public function getInterventionServices(int $interventionId): array
    {
        $sql = "
            SELECT s.*, is.quantity, is.unit_price, is.total_price, is.notes
            FROM services s
            JOIN intervention_services is ON s.id = is.service_id
            WHERE is.intervention_id = :intervention_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':intervention_id' => $interventionId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Ajout d'un service à une intervention
     */
    public function addService(int $interventionId, int $serviceId, float $quantity, float $unitPrice, ?string $notes = null): bool
    {
        $totalPrice = $quantity * $unitPrice;
        
        $sql = "
            INSERT INTO intervention_services 
            (intervention_id, service_id, quantity, unit_price, total_price, notes)
            VALUES (:intervention_id, :service_id, :quantity, :unit_price, :total_price, :notes)
        ";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':intervention_id' => $interventionId,
            ':service_id' => $serviceId,
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':total_price' => $totalPrice,
            ':notes' => $notes
        ]);

        if ($result) {
            $this->recalculateTotal($interventionId);
        }

        return $result;
    }

    /**
     * Recalcul du total d'une intervention
     */
    public function recalculateTotal(int $interventionId): void
    {
        $sql = "
            UPDATE {$this->table} 
            SET total_amount = (
                SELECT COALESCE(SUM(total_price), 0) 
                FROM intervention_services 
                WHERE intervention_id = :intervention_id
            ),
            updated_at = :updated_at
            WHERE id = :intervention_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':intervention_id' => $interventionId,
            ':updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Démarrage d'une intervention
     */
    public function startIntervention(int $interventionId): bool
    {
        return $this->update($interventionId, [
            'status' => self::STATUS_IN_PROGRESS,
            'actual_start_time' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Terminaison d'une intervention
     */
    public function completeIntervention(int $interventionId, ?string $notes = null, ?string $agentSignature = null): bool
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'actual_end_time' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        if ($agentSignature !== null) {
            $data['agent_signature'] = $agentSignature;
        }

        return $this->update($interventionId, $data);
    }

    /**
     * Validation d'une intervention par le client
     */
    public function validateIntervention(int $interventionId, ?string $clientSignature = null, ?string $feedback = null, ?int $rating = null): bool
    {
        $data = [
            'status' => self::STATUS_VALIDATED,
            'validated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($clientSignature !== null) {
            $data['client_signature'] = $clientSignature;
        }

        if ($feedback !== null) {
            $data['client_feedback'] = $feedback;
        }

        if ($rating !== null) {
            $data['client_rating'] = $rating;
        }

        return $this->update($interventionId, $data);
    }

    /**
     * Vérification des conflits de planning
     */
    public function checkConflicts(int $agentId, string $date, string $startTime, string $endTime, ?int $excludeId = null): array
    {
        $sql = "
            SELECT i.*, c.company_name as client_name
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.agent_id = :agent_id
            AND i.scheduled_date = :date
            AND i.status NOT IN ('cancelled', 'completed', 'validated')
            AND (
                (i.scheduled_time <= :start_time AND i.estimated_end_time > :start_time)
                OR (i.scheduled_time < :end_time AND i.estimated_end_time >= :end_time)
                OR (i.scheduled_time >= :start_time AND i.estimated_end_time <= :end_time)
            )
        ";

        $params = [
            ':agent_id' => $agentId,
            ':date' => $date,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ];

        if ($excludeId !== null) {
            $sql .= " AND i.id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Planning d'un agent
     */
    public function getAgentSchedule(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT i.*, c.company_name as client_name, c.address, c.city
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.agent_id = :agent_id
            AND i.scheduled_date BETWEEN :date_from AND :date_to
            AND i.status != :cancelled_status
            ORDER BY i.scheduled_date, i.scheduled_time
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':agent_id' => $agentId,
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
            ':cancelled_status' => self::STATUS_CANCELLED
        ]);
        
        return $stmt->fetchAll();
    }

    /**
     * Statistiques des interventions
     */
    public function getStatistics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = [];
        $dateCondition = '';

        if ($dateFrom && $dateTo) {
            $dateCondition = "WHERE scheduled_date BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $dateFrom;
            $params[':date_to'] = $dateTo;
        }

        $stats = [
            'total' => 0,
            'by_status' => [],
            'total_revenue' => 0,
            'average_duration' => 0
        ];

        // Total
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} $dateCondition", $params);
        $stats['total'] = (int) $stmt->fetchColumn();

        // Par statut
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count 
            FROM {$this->table} 
            $dateCondition
            GROUP BY status
        ", $params);
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Revenu total
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM {$this->table} 
            WHERE status IN ('completed', 'validated')
            $dateCondition
        ", $params);
        $stats['total_revenue'] = (float) $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Interventions à venir
     */
    public function getUpcoming(int $limit = 10): array
    {
        $sql = "
            SELECT i.*, c.company_name as client_name, c.address, c.city,
                   CONCAT(u.first_name, ' ', u.last_name) as agent_name
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            LEFT JOIN users u ON i.agent_id = u.id
            WHERE i.scheduled_date >= CURDATE()
            AND i.status IN ('scheduled', 'in_progress')
            ORDER BY i.scheduled_date ASC, i.scheduled_time ASC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':limit' => $limit]);
        
        return $stmt->fetchAll();
    }
}
