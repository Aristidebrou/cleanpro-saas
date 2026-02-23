<?php

declare(strict_types=1);

namespace CleanPro\Models;

class Invoice extends Model
{
    protected string $table = 'invoices';

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';

    const TYPE_ONE_TIME = 'one_time';
    const TYPE_RECURRING = 'recurring';

    /**
     * Création d'une facture
     */
    public function createInvoice(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_DRAFT;
        }

        if (!isset($data['invoice_number'])) {
            $data['invoice_number'] = $this->generateInvoiceNumber();
        }

        if (!isset($data['issue_date'])) {
            $data['issue_date'] = date('Y-m-d');
        }

        if (!isset($data['due_date'])) {
            $data['due_date'] = date('Y-m-d', strtotime('+30 days'));
        }

        return $this->create($data);
    }

    /**
     * Génération d'un numéro de facture unique
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'FAC-' . date('Y');
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE invoice_number LIKE :prefix";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $count = (int) $stmt->fetchColumn();
        
        return $prefix . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Récupération des factures avec détails client
     */
    public function getInvoicesWithDetails(array $filters = []): array
    {
        $sql = "
            SELECT i.*, 
                   c.company_name as client_company,
                   c.address as client_address,
                   c.city as client_city,
                   c.postal_code as client_postal_code,
                   c.contact_name as client_contact,
                   c.contact_email as client_email
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE 1=1
        ";
        
        $params = [];

        if (isset($filters['client_id'])) {
            $sql .= " AND i.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (isset($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (isset($filters['type'])) {
            $sql .= " AND i.type = :type";
            $params[':type'] = $filters['type'];
        }

        if (isset($filters['date_from'])) {
            $sql .= " AND i.issue_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND i.issue_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (isset($filters['unpaid_only']) && $filters['unpaid_only']) {
            $sql .= " AND i.status IN ('sent', 'overdue')";
        }

        $sql .= " ORDER BY i.issue_date DESC, i.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Récupération d'une facture avec tous ses détails
     */
    public function getInvoiceWithDetails(int $invoiceId): ?array
    {
        $sql = "
            SELECT i.*, 
                   c.company_name as client_company,
                   c.address as client_address,
                   c.city as client_city,
                   c.postal_code as client_postal_code,
                   c.contact_name as client_contact,
                   c.contact_email as client_email,
                   c.siret as client_siret,
                   c.vat_number as client_vat
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.id = :id
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $invoiceId]);
        
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            return null;
        }

        // Récupération des lignes de facture
        $invoice['items'] = $this->getInvoiceItems($invoiceId);

        // Récupération des interventions liées
        $invoice['interventions'] = $this->getLinkedInterventions($invoiceId);

        return $invoice;
    }

    /**
     * Récupération des lignes de facture
     */
    public function getInvoiceItems(int $invoiceId): array
    {
        $sql = "
            SELECT * FROM invoice_items
            WHERE invoice_id = :invoice_id
            ORDER BY id ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Ajout d'une ligne de facture
     */
    public function addItem(int $invoiceId, array $item): bool
    {
        $sql = "
            INSERT INTO invoice_items 
            (invoice_id, description, quantity, unit_price, total_price, service_id)
            VALUES (:invoice_id, :description, :quantity, :unit_price, :total_price, :service_id)
        ";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':description' => $item['description'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price'],
            ':total_price' => $item['quantity'] * $item['unit_price'],
            ':service_id' => $item['service_id'] ?? null
        ]);

        if ($result) {
            $this->recalculateTotals($invoiceId);
        }

        return $result;
    }

    /**
     * Recalcul des totaux
     */
    public function recalculateTotals(int $invoiceId): void
    {
        $sql = "
            UPDATE {$this->table} 
            SET subtotal = (
                SELECT COALESCE(SUM(total_price), 0) 
                FROM invoice_items 
                WHERE invoice_id = :invoice_id
            ),
            total_amount = (
                SELECT COALESCE(SUM(total_price), 0) 
                FROM invoice_items 
                WHERE invoice_id = :invoice_id_2
            ) - discount_amount + tax_amount,
            updated_at = :updated_at
            WHERE id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':invoice_id_2' => $invoiceId,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $invoiceId
        ]);
    }

    /**
     * Application d'une remise
     */
    public function applyDiscount(int $invoiceId, float $amount, ?string $reason = null): bool
    {
        return $this->update($invoiceId, [
            'discount_amount' => $amount,
            'discount_reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Application d'un code promo
     */
    public function applyPromoCode(int $invoiceId, array $promoCode): bool
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return false;
        }

        $discountAmount = 0;
        
        if ($promoCode['discount_type'] === 'percentage') {
            $discountAmount = $invoice['subtotal'] * ($promoCode['discount_value'] / 100);
        } else {
            $discountAmount = min($promoCode['discount_value'], $invoice['subtotal']);
        }

        return $this->update($invoiceId, [
            'promo_code_id' => $promoCode['id'],
            'discount_amount' => $discountAmount,
            'discount_reason' => 'Code promo: ' . $promoCode['code'],
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marquer comme payée
     */
    public function markAsPaid(int $invoiceId, string $paymentMethod, ?string $transactionId = null): bool
    {
        return $this->update($invoiceId, [
            'status' => self::STATUS_PAID,
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Envoi de la facture
     */
    public function markAsSent(int $invoiceId): bool
    {
        return $this->update($invoiceId, [
            'status' => self::STATUS_SENT,
            'sent_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Liaison d'une intervention à une facture
     */
    public function linkIntervention(int $invoiceId, int $interventionId): bool
    {
        $sql = "
            INSERT INTO invoice_interventions (invoice_id, intervention_id)
            VALUES (:invoice_id, :intervention_id)
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':intervention_id' => $interventionId
        ]);
    }

    /**
     * Récupération des interventions liées
     */
    public function getLinkedInterventions(int $invoiceId): array
    {
        $sql = "
            SELECT i.*, c.company_name as client_name
            FROM interventions i
            JOIN invoice_interventions ii ON i.id = ii.intervention_id
            WHERE ii.invoice_id = :invoice_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':invoice_id' => $invoiceId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Création de facture depuis une intervention
     */
    public function createFromIntervention(int $interventionId, ?array $promoCode = null): ?int
    {
        $interventionModel = new Intervention();
        $intervention = $interventionModel->getInterventionWithDetails($interventionId);

        if (!$intervention || $intervention['status'] !== Intervention::STATUS_COMPLETED) {
            return null;
        }

        // Vérifier si une facture existe déjà
        $existing = $this->where(['intervention_id' => $interventionId], [], 1);
        if (!empty($existing)) {
            return $existing[0]['id'];
        }

        $this->beginTransaction();

        try {
            // Création de la facture
            $invoiceData = [
                'client_id' => $intervention['client_id'],
                'intervention_id' => $interventionId,
                'type' => self::TYPE_ONE_TIME,
                'subtotal' => $intervention['total_amount'],
                'total_amount' => $intervention['total_amount'],
                'notes' => 'Facture générée automatiquement depuis l\'intervention ' . $intervention['reference']
            ];

            $invoiceId = $this->createInvoice($invoiceData);

            // Ajout des lignes de facture depuis les services
            foreach ($intervention['services'] as $service) {
                $this->addItem($invoiceId, [
                    'description' => $service['name'] . ($service['notes'] ? ' - ' . $service['notes'] : ''),
                    'quantity' => $service['quantity'],
                    'unit_price' => $service['unit_price'],
                    'service_id' => $service['id']
                ]);
            }

            // Application du code promo si fourni
            if ($promoCode) {
                $this->applyPromoCode($invoiceId, $promoCode);
            }

            // Liaison de l'intervention
            $this->linkIntervention($invoiceId, $interventionId);

            $this->commit();

            return $invoiceId;
        } catch (\Exception $e) {
            $this->rollback();
            return null;
        }
    }

    /**
     * Factures en retard
     */
    public function getOverdue(): array
    {
        $sql = "
            SELECT i.*, c.company_name as client_company, c.contact_email as client_email
            FROM {$this->table} i
            LEFT JOIN clients c ON i.client_id = c.id
            WHERE i.due_date < CURDATE()
            AND i.status IN ('sent', 'overdue')
            ORDER BY i.due_date ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Mise à jour du statut des factures en retard
     */
    public function updateOverdueStatus(): int
    {
        $sql = "
            UPDATE {$this->table} 
            SET status = :overdue_status
            WHERE due_date < CURDATE()
            AND status = :sent_status
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':overdue_status' => self::STATUS_OVERDUE,
            ':sent_status' => self::STATUS_SENT
        ]);

        return $stmt->rowCount();
    }

    /**
     * Statistiques de facturation
     */
    public function getStatistics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = [];
        $dateCondition = '';

        if ($dateFrom && $dateTo) {
            $dateCondition = "AND issue_date BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $dateFrom;
            $params[':date_to'] = $dateTo;
        }

        $stats = [
            'total_invoiced' => 0,
            'total_paid' => 0,
            'total_outstanding' => 0,
            'by_status' => [],
            'overdue_count' => 0,
            'overdue_amount' => 0
        ];

        // Total facturé
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM {$this->table} 
            WHERE status != :cancelled $dateCondition
        ", array_merge([':cancelled' => self::STATUS_CANCELLED], $params));
        $stats['total_invoiced'] = (float) $stmt->fetchColumn();

        // Total payé
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM {$this->table} 
            WHERE status = :paid $dateCondition
        ", array_merge([':paid' => self::STATUS_PAID], $params));
        $stats['total_paid'] = (float) $stmt->fetchColumn();

        // Total en attente
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM {$this->table} 
            WHERE status IN (:sent, :overdue) $dateCondition
        ", array_merge([':sent' => self::STATUS_SENT, ':overdue' => self::STATUS_OVERDUE], $params));
        $stats['total_outstanding'] = (float) $stmt->fetchColumn();

        // Par statut
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount
            FROM {$this->table}
            WHERE 1=1 $dateCondition
            GROUP BY status
        ", $params);
        $stats['by_status'] = $stmt->fetchAll();

        // Factures en retard
        $stmt = $this->db->query("
            SELECT COUNT(*), COALESCE(SUM(total_amount), 0)
            FROM {$this->table}
            WHERE status = :overdue
        ", [':overdue' => self::STATUS_OVERDUE]);
        $overdue = $stmt->fetch();
        $stats['overdue_count'] = (int) $overdue[0];
        $stats['overdue_amount'] = (float) $overdue[1];

        return $stats;
    }
}
