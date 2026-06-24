<?php
// app/models/BudgetModel.php

class BudgetModel extends Model
{
    protected string $table = 'budgets';

    // ── Read ──────────────────────────────────────────────────

    public function getForUser(int $userId, string $status = 'active'): array
    {
        return $this->query(
            "SELECT * FROM budgets
             WHERE user_id = ? AND status = ?
             ORDER BY start_date DESC",
            [$userId, $status]
        )->fetchAll();
    }

    public function getCurrentBudget(int $userId): ?array
    {
        $today = date('Y-m-d');
        $row = $this->query(
            "SELECT * FROM budgets
             WHERE user_id = ? AND status = 'active'
               AND start_date <= ? AND end_date >= ?
             ORDER BY start_date DESC LIMIT 1",
            [$userId, $today, $today]
        )->fetch();
        return $row ?: null;
    }

    public function getWithItems(int $budgetId): ?array
    {
        $budget = $this->find($budgetId);
        if (!$budget) return null;

        $budget['items'] = $this->query(
            "SELECT bi.*, bc.name AS category_name, bc.icon, bc.color
             FROM budget_items bi
             JOIN budget_categories bc ON bc.id = bi.category_id
             WHERE bi.budget_id = ?
             ORDER BY bc.sort_order",
            [$budgetId]
        )->fetchAll();

        return $budget;
    }

    // ── Create ────────────────────────────────────────────────

    public function createBudget(int $userId, array $data): int
    {
        return $this->insert([
            'user_id'      => $userId,
            'title'        => $data['title'],
            'period_type'  => $data['period_type'] ?? 'weekly',
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date'],
            'total_income' => $data['total_income'] ?? 0,
            'total_budget' => $data['total_budget'] ?? 0,
            'status'       => 'active',
            'notes'        => $data['notes'] ?? null,
        ]);
    }

    // ── Duplicate (copy prior week) ───────────────────────────

    public function duplicate(int $budgetId, string $newStartDate, string $newEndDate): int
    {
        $source = $this->getWithItems($budgetId);
        if (!$source) return 0;

        $newId = $this->createBudget($source['user_id'], [
            'title'        => $source['title'] . ' (copy)',
            'period_type'  => $source['period_type'],
            'start_date'   => $newStartDate,
            'end_date'     => $newEndDate,
            'total_income' => $source['total_income'],
            'total_budget' => $source['total_budget'],
        ]);

        // Copy budget items
        $itemModel = new BudgetItemModel();
        foreach ($source['items'] as $item) {
            $itemModel->insert([
                'budget_id'   => $newId,
                'category_id' => $item['category_id'],
                'allocated'   => $item['allocated'],
            ]);
        }

        return $newId;
    }

    // ── Archive ───────────────────────────────────────────────

    public function archive(int $budgetId): bool
    {
        return $this->update($budgetId, ['status' => 'archived']);
    }

    // ── Totals (recalculate from items) ───────────────────────

    public function recalculateTotals(int $budgetId): void
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(allocated), 0) AS total
             FROM budget_items WHERE budget_id = ?",
            [$budgetId]
        )->fetch();

        $this->update($budgetId, ['total_budget' => $row['total']]);
    }
}
