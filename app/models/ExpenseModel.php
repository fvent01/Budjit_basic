<?php
// app/models/ExpenseModel.php

class ExpenseModel extends Model
{
    protected string $table = 'expenses';

    // ── Fetch ─────────────────────────────────────────────────

    public function getForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT e.*, bc.name AS category_name, bc.icon, bc.color
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.user_id = ?
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll();
    }

    public function getForBudget(int $budgetId): array
    {
        return $this->query(
            "SELECT e.*, bc.name AS category_name, bc.icon, bc.color
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.budget_id = ?
             ORDER BY e.expense_date DESC",
            [$budgetId]
        )->fetchAll();
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->query(
            "SELECT e.*, bc.name AS category_name, bc.icon, bc.color
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.user_id = ?
             ORDER BY e.expense_date DESC, e.id DESC
             LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    public function getUnpaid(int $userId): array
    {
        return $this->query(
            "SELECT e.*, bc.name AS category_name
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.user_id = ? AND e.is_paid = 0
             ORDER BY e.expense_date ASC",
            [$userId]
        )->fetchAll();
    }

    // ── Totals & aggregates ───────────────────────────────────

    public function getTotalForBudget(int $budgetId): float
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM expenses WHERE budget_id = ?",
            [$budgetId]
        )->fetch();
        return (float) $row['total'];
    }

    public function getTotalForDateRange(int $userId, string $from, string $to): float
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE user_id = ? AND expense_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetch();
        return (float) $row['total'];
    }

    /** Returns per-category spending for a date range — used for donut chart */
    public function getCategoryTotals(int $userId, string $from, string $to): array
    {
        return $this->query(
            "SELECT bc.id, bc.name, bc.icon, bc.color,
                    COALESCE(SUM(e.amount), 0) AS total
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
             GROUP BY bc.id, bc.name, bc.icon, bc.color
             ORDER BY total DESC",
            [$userId, $from, $to]
        )->fetchAll();
    }

    /** Daily totals for the bar chart */
    public function getDailyTotals(int $userId, string $from, string $to): array
    {
        return $this->query(
            "SELECT expense_date AS day, COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE user_id = ? AND expense_date BETWEEN ? AND ?
             GROUP BY expense_date
             ORDER BY expense_date",
            [$userId, $from, $to]
        )->fetchAll();
    }

    /** Spending per category vs budget allocation — for over-budget detection */
    public function getVsBudget(int $budgetId): array
    {
        return $this->query(
            "SELECT bc.id, bc.name, bc.color,
                    COALESCE(bi.allocated, 0)   AS allocated,
                    COALESCE(SUM(e.amount), 0)  AS spent,
                    COALESCE(bi.allocated, 0) - COALESCE(SUM(e.amount), 0) AS remaining
             FROM budget_categories bc
             LEFT JOIN budget_items bi ON bi.category_id = bc.id AND bi.budget_id = ?
             LEFT JOIN expenses e      ON e.category_id  = bc.id AND e.budget_id  = ?
             WHERE bc.is_active = 1
             GROUP BY bc.id, bc.name, bc.color, bi.allocated
             HAVING allocated > 0 OR spent > 0
             ORDER BY bc.sort_order",
            [$budgetId, $budgetId]
        )->fetchAll();
    }

    // ── Create ────────────────────────────────────────────────

    public function addExpense(int $userId, array $data): int
    {
        return $this->insert([
            'user_id'      => $userId,
            'budget_id'    => $data['budget_id']    ?? null,
            'category_id'  => $data['category_id'],
            'description'  => $data['description'],
            'amount'       => $data['amount'],
            'expense_date' => $data['expense_date'],
            'is_paid'      => $data['is_paid']      ?? 0,
            'is_recurring' => $data['is_recurring'] ?? 0,
            'notes'        => $data['notes']        ?? null,
        ]);
    }

    public function markPaid(int $expenseId): bool
    {
        return $this->update($expenseId, ['is_paid' => 1]);
    }

    public function countUnpaid(int $userId): int
    {
        return $this->count(['user_id' => $userId, 'is_paid' => 0]);
    }
}
