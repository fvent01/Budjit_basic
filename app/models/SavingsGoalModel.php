<?php
// app/models/SavingsGoalModel.php

class SavingsGoalModel extends Model
{
    protected string $table = 'savings_goals';

    public function getForUser(int $userId): array
    {
        return $this->query(
            "SELECT g.*,
                    COALESCE(SUM(c.amount), 0) AS contributed,
                    ROUND((g.current_amount / g.target_amount) * 100, 1) AS percent
             FROM savings_goals g
             LEFT JOIN savings_contributions c ON c.goal_id = g.id
             WHERE g.user_id = ?
             GROUP BY g.id
             ORDER BY g.is_completed ASC, g.priority ASC",
            [$userId]
        )->fetchAll();
    }

    public function nextPriority(int $userId): int
    {
        $row = $this->query(
            "SELECT COALESCE(MAX(priority), -1) + 1 AS next FROM savings_goals WHERE user_id = ?",
            [$userId]
        )->fetch();
        return (int)$row['next'];
    }

    public function addContribution(int $goalId, int $userId, float $amount, string $note, string $source): void
    {
        // Log contribution
        $this->query(
            "INSERT INTO savings_contributions (goal_id, user_id, amount, note, source, contributed_at)
             VALUES (?, ?, ?, ?, ?, CURDATE())",
            [$goalId, $userId, $amount, $note, $source]
        );

        // Update running balance on goal
        $this->query(
            "UPDATE savings_goals
             SET current_amount = current_amount + ?,
                 is_completed   = IF(current_amount + ? >= target_amount, 1, 0)
             WHERE id = ?",
            [$amount, $amount, $goalId]
        );
    }

    public function getContributions(int $goalId): array
    {
        return $this->query(
            "SELECT * FROM savings_contributions WHERE goal_id = ? ORDER BY contributed_at DESC",
            [$goalId]
        )->fetchAll();
    }

    /** Run auto-allocations for a user based on a given income amount */
    public function runAutoAllocate(int $userId, float $incomeAmount): void
    {
        $goals = $this->findWhere(['user_id' => $userId, 'auto_allocate' => 1, 'is_completed' => 0]);
        foreach ($goals as $goal) {
            $contribution = round($incomeAmount * ($goal['auto_percent'] / 100), 2);
            if ($contribution > 0) {
                $this->addContribution($goal['id'], $userId, $contribution, 'Auto-allocated', 'auto');
            }
        }
    }
}
