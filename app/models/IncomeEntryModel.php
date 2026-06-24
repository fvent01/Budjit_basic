<?php
// app/models/IncomeEntryModel.php

class IncomeEntryModel extends Model
{
    protected string $table = 'income_entries';

    // ── Fetch ─────────────────────────────────────────────────

    public function getForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT ie.*, iss.name AS source_name, iss.source_type
             FROM income_entries ie
             LEFT JOIN income_sources iss ON iss.id = ie.income_source_id
             WHERE ie.user_id = ?
             ORDER BY ie.received_date DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll();
    }

    public function getForBudget(int $budgetId): array
    {
        return $this->query(
            "SELECT ie.*, iss.name AS source_name
             FROM income_entries ie
             LEFT JOIN income_sources iss ON iss.id = ie.income_source_id
             WHERE ie.budget_id = ?
             ORDER BY ie.received_date DESC",
            [$budgetId]
        )->fetchAll();
    }

    public function getTotalForBudget(int $budgetId): float
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM income_entries WHERE budget_id = ?",
            [$budgetId]
        )->fetch();
        return (float) $row['total'];
    }

    public function getTotalForDateRange(int $userId, string $from, string $to): float
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM income_entries
             WHERE user_id = ? AND received_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetch();
        return (float) $row['total'];
    }

    // ── Weekly breakdown (for charts) ─────────────────────────

    public function getDailyTotals(int $userId, string $from, string $to): array
    {
        return $this->query(
            "SELECT received_date AS day, COALESCE(SUM(amount), 0) AS total
             FROM income_entries
             WHERE user_id = ? AND received_date BETWEEN ? AND ?
             GROUP BY received_date
             ORDER BY received_date",
            [$userId, $from, $to]
        )->fetchAll();
    }

    // ── Create ────────────────────────────────────────────────

    public function addEntry(int $userId, array $data): int
    {
        return $this->insert([
            'user_id'          => $userId,
            'budget_id'        => $data['budget_id']        ?? null,
            'income_source_id' => $data['income_source_id'] ?? null,
            'description'      => $data['description'],
            'amount'           => $data['amount'],
            'received_date'    => $data['received_date'],
            'is_recurring'     => $data['is_recurring'] ?? 0,
            'notes'            => $data['notes'] ?? null,
        ]);
    }
}
