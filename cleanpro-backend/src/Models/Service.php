<?php

declare(strict_types=1);

namespace CleanPro\Models;

class Service extends Model
{
    protected string $table = 'services';

    const TYPE_CLEANING = 'cleaning';
    const TYPE_GARDENING = 'gardening';
    const TYPE_MAINTENANCE = 'maintenance';

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Récupération des services actifs
     */
    public function getActiveServices(): array
    {
        return $this->where(
            ['status' => self::STATUS_ACTIVE],
            ['category' => 'ASC', 'name' => 'ASC']
        );
    }

    /**
     * Récupération des services par catégorie
     */
    public function getByCategory(string $category): array
    {
        return $this->where(
            ['category' => $category, 'status' => self::STATUS_ACTIVE],
            ['name' => 'ASC']
        );
    }

    /**
     * Calcul du prix d'un service
     */
    public function calculatePrice(int $serviceId, float $quantity = 1, ?array $promoCode = null): array
    {
        $service = $this->find($serviceId);
        
        if (!$service) {
            return ['error' => 'Service not found'];
        }

        $basePrice = $service['base_price'] * $quantity;
        $discount = 0;
        $discountType = null;

        // Application du code promo
        if ($promoCode) {
            if ($promoCode['discount_type'] === 'percentage') {
                $discount = $basePrice * ($promoCode['discount_value'] / 100);
                $discountType = $promoCode['discount_value'] . '%';
            } else {
                $discount = min($promoCode['discount_value'], $basePrice);
                $discountType = $promoCode['discount_value'] . '€';
            }
        }

        $finalPrice = $basePrice - $discount;

        return [
            'base_price' => $basePrice,
            'discount' => $discount,
            'discount_type' => $discountType,
            'final_price' => $finalPrice,
            'service_name' => $service['name']
        ];
    }

    /**
     * Services les plus demandés
     */
    public function getMostRequested(int $limit = 5): array
    {
        $sql = "
            SELECT s.*, COUNT(i.id) as intervention_count
            FROM {$this->table} s
            LEFT JOIN intervention_services i ON s.id = i.service_id
            WHERE s.status = :status
            GROUP BY s.id
            ORDER BY intervention_count DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => self::STATUS_ACTIVE, ':limit' => $limit]);
        
        return $stmt->fetchAll();
    }
}
