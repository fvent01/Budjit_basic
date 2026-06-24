<?php
// core/database/Model.php

abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Generic finders ──────────────────────────────────────

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAll(string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$dir}");
        return $stmt->fetchAll();
    }

    public function findWhere(array $conditions, string $orderBy = 'id', string $dir = 'ASC'): array
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $clauses = [];
        $values  = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "{$col} = ?";
            $values[]  = $val;
        }
        $where = implode(' AND ', $clauses);
        $stmt  = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} {$dir}"
        );
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    public function findOneWhere(array $conditions): ?array
    {
        $rows = $this->findWhere($conditions);
        return $rows[0] ?? null;
    }

    // ── Insert / Update / Delete ──────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$cols}) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets   = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey} = ?"
        );
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?"
        );
        return $stmt->execute([$id]);
    }

    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int) $this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        }
        $clauses = [];
        $values  = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "{$col} = ?";
            $values[]  = $val;
        }
        $where = implode(' AND ', $clauses);
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    }

    // ── Raw query helper ─────────────────────────────────────

    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
