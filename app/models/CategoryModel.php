<?php
// app/models/CategoryModel.php

class CategoryModel extends Model
{
    protected string $table = 'budget_categories';

    // ── Queries ───────────────────────────────────────────────

    /**
     * All active categories (system + user's own), with derived expense_count.
     * Admins should call getAll() instead.
     */
    public function getAllForUser(int $userId): array
    {
        return $this->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) AS expense_count
             FROM budget_categories c
             WHERE c.is_active = 1
               AND (c.is_system = 1 OR c.user_id = ?)
             ORDER BY c.is_system DESC, c.sort_order ASC, c.name ASC",
            [$userId]
        )->fetchAll();
    }

    /**
     * All active categories — admin view.
     */
    public function getAll(): array
    {
        return $this->query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM expenses WHERE category_id = c.id) AS expense_count
             FROM budget_categories c
             WHERE c.is_active = 1
             ORDER BY c.is_system DESC, c.sort_order ASC, c.name ASC"
        )->fetchAll();
    }

    /**
     * Lightweight list for dropdowns — excludes hidden categories.
     * Backward-compatible replacement for the old getForUser().
     */
    public function getForUser(int $userId): array
    {
        return $this->query(
            "SELECT id, name, icon, color
             FROM budget_categories
             WHERE is_active = 1
               AND is_hidden = 0
               AND (is_system = 1 OR user_id = ?)
             ORDER BY is_system DESC, sort_order ASC, name ASC",
            [$userId]
        )->fetchAll();
    }

    // ── Derived counts ────────────────────────────────────────

    /**
     * Number of expenses that reference this category.
     */
    public function getExpenseCount(int $id): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM expenses WHERE category_id = ?",
            [$id]
        )->fetchColumn();
    }

    /**
     * Number of budget_items that reference this category.
     */
    public function getBudgetItemCount(int $id): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM budget_items WHERE category_id = ?",
            [$id]
        )->fetchColumn();
    }

    // ── Permission helpers ────────────────────────────────────

    /**
     * Returns true if the given user may modify (edit / delete / toggle) this category.
     */
    public function canEdit(array $category, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) return true;
        if ((int) $category['is_system'] === 1) return false;
        return (int) $category['user_id'] === $userId;
    }

    // ── Delete safety ─────────────────────────────────────────

    /**
     * Returns ['ok' => bool, 'message' => string].
     * ok = false when expenses or budget items still reference the category.
     */
    public function isDeletable(int $id): array
    {
        $expCount    = $this->getExpenseCount($id);
        $budgetCount = $this->getBudgetItemCount($id);

        if ($expCount > 0 || $budgetCount > 0) {
            $parts = [];
            if ($expCount > 0)    $parts[] = "{$expCount} expense" . ($expCount !== 1 ? 's' : '');
            if ($budgetCount > 0) $parts[] = "{$budgetCount} budget item" . ($budgetCount !== 1 ? 's' : '');
            $detail = implode(' and ', $parts);
            return [
                'ok'      => false,
                'message' => "Cannot delete: category is used by {$detail}. Hide it instead.",
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    // ── Sort-order helpers ────────────────────────────────────

    /**
     * Returns the next available sort_order within the same group (system / custom).
     */
    public function getNextSortOrder(bool $isSystem): int
    {
        $max = $this->query(
            "SELECT MAX(sort_order) FROM budget_categories WHERE is_system = ? AND is_active = 1",
            [(int) $isSystem]
        )->fetchColumn();
        return (int) $max + 1;
    }

    // ── Bulk reorder ──────────────────────────────────────────

    /**
     * Persist reordering for a list of ['id' => int, 'sort_order' => int] pairs.
     * Permission checks are done in the controller before calling this.
     *
     * @param  array<array{id:int,sort_order:int}> $items
     */
    public function applyReorder(array $items): void
    {
        $stmt = $this->db->prepare(
            "UPDATE budget_categories SET sort_order = ? WHERE id = ?"
        );
        foreach ($items as $item) {
            $id    = (int) ($item['id']         ?? 0);
            $order = (int) ($item['sort_order'] ?? 0);
            if ($id > 0) {
                $stmt->execute([$order, $id]);
            }
        }
    }
}
