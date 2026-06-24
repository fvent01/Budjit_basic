<?php
// app/models/DebtModel.php

class DebtModel extends Model
{
    protected string $table = 'debts';

    public function getForUser(int $userId): array
    {
        // Snowball order: smallest balance first
        return $this->query(
            "SELECT d.*,
                    COALESCE(SUM(p.amount), 0) AS total_paid
             FROM debts d
             LEFT JOIN debt_payments p ON p.debt_id = d.id
             WHERE d.user_id = ?
             GROUP BY d.id
             ORDER BY d.is_paid_off ASC, d.balance ASC",
            [$userId]
        )->fetchAll();
    }

    public function getTotalDebt(int $userId): float
    {
        $row = $this->query(
            "SELECT COALESCE(SUM(balance), 0) AS total FROM debts WHERE user_id = ? AND is_paid_off = 0",
            [$userId]
        )->fetch();
        return (float)$row['total'];
    }

    public function addPayment(int $debtId, int $userId, float $amount, string $note, string $date): void
    {
        $this->query(
            "INSERT INTO debt_payments (debt_id, user_id, amount, note, paid_date) VALUES (?, ?, ?, ?, ?)",
            [$debtId, $userId, $amount, $note, $date]
        );
        // Reduce balance
        $this->query(
            "UPDATE debts SET balance = GREATEST(0, balance - ?),
             is_paid_off = IF(balance - ? <= 0, 1, 0)
             WHERE id = ?",
            [$amount, $amount, $debtId]
        );
    }

    public function getPayments(int $debtId): array
    {
        return $this->query(
            "SELECT * FROM debt_payments WHERE debt_id = ? ORDER BY paid_date DESC",
            [$debtId]
        )->fetchAll();
    }

    /** Snowball payoff projection — months to payoff each debt in order */
    public function snowballProjection(int $userId, float $extraMonthly = 0): array
    {
        $debts = $this->getForUser($userId);
        $results = [];
        $rollover = $extraMonthly;

        foreach ($debts as $debt) {
            if ($debt['is_paid_off']) continue;

            $balance  = (float)$debt['balance'];
            $minPay   = (float)$debt['minimum_payment'];
            $apr      = (float)$debt['interest_rate'] / 100 / 12;
            $payment  = $minPay + $rollover;
            $months   = 0;

            while ($balance > 0 && $months < 600) {
                $interest  = $balance * $apr;
                $principal = $payment - $interest;
                if ($principal <= 0) { $months = 9999; break; }
                $balance  -= $principal;
                $months++;
            }

            $results[] = [
                'id'        => $debt['id'],
                'name'      => $debt['name'],
                'balance'   => $debt['balance'],
                'months'    => $months,
                'payoff_date' => $months < 600 ? date('M Y', strtotime("+{$months} months")) : 'Never',
                'payment'   => $payment,
            ];

            $rollover += $minPay; // freed minimum rolls to next debt
        }

        return $results;
    }
}
