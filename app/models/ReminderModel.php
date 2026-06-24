<?php
// app/models/ReminderModel.php

class ReminderModel extends Model
{
    protected string $table = 'reminders';

    public function getForUser(int $userId, bool $includesDismissed = false): array
    {
        $where = $includesDismissed ? '' : 'AND is_dismissed = 0';
        return $this->query(
            "SELECT * FROM reminders
             WHERE user_id = ? {$where}
             ORDER BY remind_date ASC",
            [$userId]
        )->fetchAll();
    }

    /** Reminders due today or overdue and not yet sent */
    public function getPendingToSend(): array
    {
        return $this->query(
            "SELECT r.*, u.email, u.first_name
             FROM reminders r
             JOIN users u ON u.id = r.user_id
             WHERE r.remind_date <= CURDATE()
               AND r.is_sent = 0
               AND r.is_dismissed = 0",
            []
        )->fetchAll();
    }

    /** All events for a given month (reminders + bills + expenses) */
    public function getMonthEvents(int $userId, int $year, int $month): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $reminders = $this->query(
            "SELECT remind_date AS event_date, title, 'reminder' AS event_type,
                    channels, linked_id, linked_type
             FROM reminders WHERE user_id = ? AND remind_date BETWEEN ? AND ? AND is_dismissed = 0",
            [$userId, $from, $to]
        )->fetchAll();

        // Pull in upcoming recurring bill due dates
        $bills = $this->query(
            "SELECT l.due_date AS event_date, b.name AS title, 'bill' AS event_type,
                    b.id AS linked_id, l.is_paid, l.amount
             FROM recurring_bill_logs l
             JOIN recurring_bills b ON b.id = l.bill_id
             WHERE l.user_id = ? AND l.due_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchAll();
        foreach ($bills as &$bill) {
            $bill['url'] = '/recurring-bills/' . $bill['linked_id'] . '/edit';
        }
        unset($bill);

        // Pull in expenses
        $expenses = $this->query(
            "SELECT e.id, e.expense_date AS event_date, e.description AS title, 'expense' AS event_type,
                    bc.color, e.amount, e.is_paid
             FROM expenses e
             JOIN budget_categories bc ON bc.id = e.category_id
             WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchAll();
        foreach ($expenses as &$expense) {
            $expense['url'] = '/expenses/' . $expense['id'] . '/edit';
        }
        unset($expense);

        // Pull in income entries
        $income = $this->query(
            "SELECT ie.id, ie.received_date AS event_date,
                    COALESCE(iss.name, 'Income') AS title, 'income' AS event_type,
                    ie.amount
             FROM income_entries ie
             LEFT JOIN income_sources iss ON iss.id = ie.income_source_id
             WHERE ie.user_id = ? AND ie.received_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchAll();
        foreach ($income as &$entry) {
            $entry['url'] = '/income/' . $entry['id'] . '/edit';
        }
        unset($entry);

        // Pull in debts with a monthly due day
        $daysInMonth = (int)date('t', strtotime($from));
        $debts = $this->query(
            "SELECT id, name, due_day, is_paid_off
             FROM debts
             WHERE user_id = ? AND is_paid_off = 0 AND due_day IS NOT NULL",
            [$userId]
        )->fetchAll();
        $debtEvents = [];
        foreach ($debts as $debt) {
            $dueDay = (int)$debt['due_day'];
            if ($dueDay < 1) {
                continue;
            }
            $dueDay = min($dueDay, $daysInMonth);
            $debtEvents[] = [
                'event_date' => sprintf('%04d-%02d-%02d', $year, $month, $dueDay),
                'title'      => $debt['name'] . ' due',
                'event_type' => 'debt',
                'url'        => '/debt-tracker/' . $debt['id'] . '/edit',
            ];
        }

        foreach ($reminders as &$reminder) {
            if ($reminder['linked_type'] === 'recurring_bill' && $reminder['linked_id']) {
                $reminder['url'] = '/recurring-bills/' . $reminder['linked_id'] . '/edit';
            } else {
                $reminder['url'] = '/reminders';
            }
        }
        unset($reminder);

        $all = array_merge($reminders, $bills, $expenses, $income, $debtEvents);
        usort($all, fn($a, $b) => strcmp($a['event_date'], $b['event_date']));
        return $all;
    }

    public function getUpcoming(int $userId, int $days = 7): array
    {
        $cutoff = date('Y-m-d', strtotime("+{$days} days"));
        return $this->query(
            "SELECT * FROM reminders
             WHERE user_id = ? AND remind_date <= ? AND is_dismissed = 0
             ORDER BY remind_date ASC",
            [$userId, $cutoff]
        )->fetchAll();
    }

    public function dismiss(int $id): void
    {
        $this->update($id, ['is_dismissed' => 1]);
    }

    public function markSent(int $id): void
    {
        $this->update($id, ['is_sent' => 1]);
    }

    /** Auto-generate reminders from recurring bills */
    public function syncFromBills(int $userId): void
    {
        $logs = $this->query(
            "SELECT l.id, l.due_date, b.name, b.id AS bill_id
             FROM recurring_bill_logs l
             JOIN recurring_bills b ON b.id = l.bill_id
             WHERE l.user_id = ? AND l.is_paid = 0 AND l.due_date >= CURDATE()",
            [$userId]
        )->fetchAll();

        foreach ($logs as $log) {
            $remindDate = date('Y-m-d', strtotime($log['due_date'] . ' -3 days'));
            // Skip if already exists
            $exists = $this->query(
                "SELECT id FROM reminders WHERE user_id = ? AND linked_id = ? AND linked_type = 'recurring_bill' AND remind_date = ?",
                [$userId, $log['bill_id'], $remindDate]
            )->fetch();
            if (!$exists) {
                $this->insert([
                    'user_id'            => $userId,
                    'title'              => 'Bill due: ' . $log['name'],
                    'reminder_type'      => 'bill',
                    'linked_id'          => $log['bill_id'],
                    'linked_type'        => 'recurring_bill',
                    'remind_date'        => $remindDate,
                    'remind_days_before' => 3,
                    'channels'           => 'inapp',
                ]);
            }
        }
    }
}
