<?php

declare(strict_types=1);

namespace CleanPro\Models;

use CleanPro\Config\Database;
use PDO;
use PDOStatement;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Recherche par ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Récupération de tous les enregistrements
     */
    public function all(array $orderBy = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(
                fn($col, $dir) => "$col $dir",
                array_keys($orderBy),
                $orderBy
            ));
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Création d'un enregistrement
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mise à jour d'un enregistrement
     */
    public function update(int $id, array $data): bool
    {
        $fields = implode(', ', array_map(
            fn($col) => "$col = :$col",
            array_keys($data)
        ));

        $sql = "UPDATE {$this->table} SET $fields WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(array_merge($data, [':id' => $id]));
    }

    /**
     * Suppression d'un enregistrement
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Recherche avec conditions
     */
    public function where(array $conditions, array $orderBy = [], ?int $limit = null): array
    {
        $whereClause = implode(' AND ', array_map(
            fn($col) => "$col = :$col",
            array_keys($conditions)
        ));

        $sql = "SELECT * FROM {$this->table} WHERE $whereClause";

        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(
                fn($col, $dir) => "$col $dir",
                array_keys($orderBy),
                $orderBy
            ));
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($conditions);

        return $stmt->fetchAll();
    }

    /**
     * Requête personnalisée
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Début d'une transaction
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Validation d'une transaction
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Annulation d'une transaction
     */
    public function rollback(): bool
    {
        return $this->db->rollBack();
    }
}
