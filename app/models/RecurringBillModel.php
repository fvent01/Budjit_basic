<?php
// app/models/RecurringBillModel.php

class RecurringBillModel extends Model
{
    protected string $table = 'recurring_bills';

    public function getForUser(int $userId): array
    {
        return $this->query(
            "SELECT b.*,
                    (SELECT l.is_paid FROM recurring_bill_logs l
                     WHERE l.bill_id = b.id
                     ORDER BY l.due_date DESC LIMIT 1) AS last_paid_status,
                    (SELECT l.due_date FROM recurring_bill_logs l
                     WHERE l.bill_id = b.id AND l.is_paid = 0
                     ORDER BY l.due_date ASC LIMIT 1) AS next_due_date
             FROM recurring_bills b
             WHERE b.user_id = ? AND b.is_active = 1
             ORDER BY b.due_day ASC",
            [$userId]
        )->fetchAll();
    }

    public function getUpcomingUnpaid(int $userId, int $days = 30): array
    {
        $cutoff = date('Y-m-d', strtotime("+{$days} days"));
        return $this->query(
            "SELECT l.*, b.name, b.color, b.icon, b.billing_url
             FROM recurring_bill_logs l
             JOIN recurring_bills b ON b.id = l.bill_id
             WHERE l.user_id = ? AND l.is_paid = 0 AND l.due_date <= ?
             ORDER BY l.due_date ASC",
            [$userId, $cutoff]
        )->fetchAll();
    }

    public function getMonthlyTotal(int $userId): float
    {
        // Normalise all frequencies to monthly cost
        $row = $this->query(
            "SELECT SUM(
                CASE frequency
                    WHEN 'weekly'     THEN amount * 4.33
                    WHEN 'biweekly'   THEN amount * 2.17
                    WHEN 'monthly'    THEN amount
                    WHEN 'quarterly'  THEN amount / 3
                    WHEN 'annually'   THEN amount / 12
                    ELSE amount
                END
             ) AS total
             FROM recurring_bills WHERE user_id = ? AND is_active = 1",
            [$userId]
        )->fetch();
        return round((float)$row['total'], 2);
    }

    public function generateLogs(int $billId): void
    {
        $bill = $this->find($billId);
        if (!$bill) return;

        // Generate the next 3 months of due-date log entries (skip if already exist)
        for ($m = 0; $m < 3; $m++) {
            $dueDate = date('Y-m-' . str_pad($bill['due_day'], 2, '0', STR_PAD_LEFT),
                           strtotime("+{$m} months"));

            $exists = $this->query(
                "SELECT id FROM recurring_bill_logs WHERE bill_id = ? AND due_date = ?",
                [$billId, $dueDate]
            )->fetch();

            if (!$exists) {
                $this->query(
                    "INSERT INTO recurring_bill_logs (bill_id, user_id, amount, due_date) VALUES (?, ?, ?, ?)",
                    [$billId, $bill['user_id'], $bill['amount'], $dueDate]
                );
            }
        }
    }

    public function markPaid(int $logId): void
    {
        $this->query(
            "UPDATE recurring_bill_logs SET is_paid = 1, paid_date = CURDATE() WHERE id = ?",
            [$logId]
        );
    }
}
