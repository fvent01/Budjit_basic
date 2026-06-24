<?php
// app/models/BudgetItemModel.php

class BudgetItemModel extends Model
{
    protected string $table = 'budget_items';

    public function upsert(int $budgetId, int $categoryId, float $allocated): void
    {
        $this->query(
            "INSERT INTO budget_items (budget_id, category_id, allocated)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE allocated = VALUES(allocated)",
            [$budgetId, $categoryId, $allocated]
        );
    }

    public function deleteByBudget(int $budgetId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM budget_items WHERE budget_id = ?");
        return $stmt->execute([$budgetId]);
    }
}
