<?php
// app/models/AnalyticsModel.php

class AnalyticsModel extends Model
{
    protected string $table = 'expenses'; // not used directly — we use raw queries

    // ── Date range helpers ────────────────────────────────────

    public static function rangeForPeriod(string $period, ?string $from = null, ?string $to = null): array
    {
        switch ($period) {
            case 'week':
                return [
                    date('Y-m-d', strtotime('monday this week')),
                    date('Y-m-d', strtotime('sunday this week')),
                ];
            case 'month':
                return [
                    date('Y-m-01'),
                    date('Y-m-t'),
                ];
            case 'year':
                return [
                    date('Y-01-01'),
                    date('Y-12-31'),
                ];
            case 'custom':
                return [$from ?? date('Y-m-01'), $to ?? date('Y-m-d')];
            default:
                return [date('Y-m-01'), date('Y-m-d')];
        }
    }

    // ── 1. Income vs Expenses over time ───────────────────────

    /** Daily totals for a date range — used for line/bar chart */
    public function incomeVsExpensesDaily(int $userId, string $from, string $to): array
    {
        // Build a full date spine so missing days show as 0
        $dates  = $this->dateSeries($from, $to);
        $inc    = $this->query(
            "SELECT received_date AS d, COALESCE(SUM(amount),0) AS total
             FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?
             GROUP BY received_date",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $exp = $this->query(
            "SELECT expense_date AS d, COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?
             GROUP BY expense_date",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $rows = [];
        foreach ($dates as $date) {
            $rows[] = [
                'date'    => $date,
                'label'   => date('M j', strtotime($date)),
                'income'  => (float)($inc[$date]  ?? 0),
                'expense' => (float)($exp[$date]  ?? 0),
                'net'     => (float)($inc[$date]  ?? 0) - (float)($exp[$date] ?? 0),
            ];
        }
        return $rows;
    }

    /** Weekly totals — for longer ranges */
    public function incomeVsExpensesWeekly(int $userId, string $from, string $to): array
    {
        $inc = $this->query(
            "SELECT YEARWEEK(received_date,1) AS wk,
                    MIN(received_date) AS week_start,
                    COALESCE(SUM(amount),0) AS total
             FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?
             GROUP BY wk ORDER BY wk",
            [$userId, $from, $to]
        )->fetchAll();

        $exp = $this->query(
            "SELECT YEARWEEK(expense_date,1) AS wk,
                    COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?
             GROUP BY wk ORDER BY wk",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $rows = [];
        foreach ($inc as $row) {
            $wk = $row['wk'];
            $incTotal = (float)$row['total'];
            $expTotal = (float)($exp[$wk] ?? 0);
            $rows[] = [
                'label'   => date('M j', strtotime($row['week_start'])),
                'income'  => $incTotal,
                'expense' => $expTotal,
                'net'     => $incTotal - $expTotal,
            ];
        }
        return $rows;
    }

    /** Monthly totals — for year view */
    public function incomeVsExpensesMonthly(int $userId, string $from, string $to): array
    {
        $inc = $this->query(
            "SELECT DATE_FORMAT(received_date,'%Y-%m') AS mo,
                    COALESCE(SUM(amount),0) AS total
             FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?
             GROUP BY mo ORDER BY mo",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $exp = $this->query(
            "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS mo,
                    COALESCE(SUM(amount),0) AS total
             FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?
             GROUP BY mo ORDER BY mo",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $months = array_unique(array_merge(array_keys($inc), array_keys($exp)));
        sort($months);

        $rows = [];
        foreach ($months as $mo) {
            $incTotal = (float)($inc[$mo] ?? 0);
            $expTotal = (float)($exp[$mo] ?? 0);
            $rows[] = [
                'label'   => date('M Y', strtotime($mo . '-01')),
                'month'   => $mo,
                'income'  => $incTotal,
                'expense' => $expTotal,
                'net'     => $incTotal - $expTotal,
            ];
        }
        return $rows;
    }

    // ── 2. Spending by category ───────────────────────────────

    public function spendingByCategory(int $userId, string $from, string $to): array
    {
        $rows = $this->query(
            "SELECT bc.id, bc.name, bc.color, bc.icon,
                    COALESCE(SUM(e.amount),0) AS total,
                    COUNT(e.id) AS txn_count
             FROM budget_categories bc
             LEFT JOIN expenses e ON e.category_id=bc.id
               AND e.user_id=? AND e.expense_date BETWEEN ? AND ?
             WHERE bc.is_active=1
             GROUP BY bc.id, bc.name, bc.color, bc.icon
             HAVING total > 0
             ORDER BY total DESC",
            [$userId, $from, $to]
        )->fetchAll();

        $grandTotal = array_sum(array_column($rows, 'total'));
        foreach ($rows as &$row) {
            $row['percent'] = $grandTotal > 0 ? round(($row['total'] / $grandTotal) * 100, 1) : 0;
        }
        return ['rows' => $rows, 'total' => $grandTotal];
    }

    /** Category spending over multiple months — for trend lines */
    public function categoryTrendMonthly(int $userId, string $from, string $to, int $limit = 5): array
    {
        // Top N categories by total spend
        $topCats = $this->query(
            "SELECT bc.id, bc.name, bc.color
             FROM budget_categories bc
             JOIN expenses e ON e.category_id=bc.id
             WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?
             GROUP BY bc.id, bc.name, bc.color
             ORDER BY SUM(e.amount) DESC
             LIMIT ?",
            [$userId, $from, $to, $limit]
        )->fetchAll();

        if (empty($topCats)) return [];

        $catIds = implode(',', array_column($topCats, 'id'));
        $monthly = $this->query(
            "SELECT category_id,
                    DATE_FORMAT(expense_date,'%Y-%m') AS mo,
                    COALESCE(SUM(amount),0) AS total
             FROM expenses
             WHERE user_id=? AND expense_date BETWEEN ? AND ?
               AND category_id IN ({$catIds})
             GROUP BY category_id, mo
             ORDER BY mo",
            [$userId, $from, $to]
        )->fetchAll();

        // Pivot: categories as series
        $byMonth = [];
        foreach ($monthly as $row) {
            $byMonth[$row['mo']][$row['category_id']] = (float)$row['total'];
        }

        $months = array_keys($byMonth);
        sort($months);

        return [
            'categories' => $topCats,
            'months'     => array_map(fn($m) => date('M Y', strtotime($m . '-01')), $months),
            'month_keys' => $months,
            'by_month'   => $byMonth,
        ];
    }

    // ── 3. Budget vs Actual ───────────────────────────────────

    public function budgetVsActual(int $userId, string $from, string $to): array
    {
        // Find budgets overlapping the date range
        $budgets = $this->query(
            "SELECT * FROM budgets
             WHERE user_id=? AND start_date<=? AND end_date>=? AND status='active'
             ORDER BY start_date DESC",
            [$userId, $to, $from]
        )->fetchAll();

        $result = [];
        foreach ($budgets as $budget) {
            $items = $this->query(
                "SELECT bi.category_id, bi.allocated,
                        bc.name, bc.color, bc.icon,
                        COALESCE(SUM(e.amount),0) AS spent
                 FROM budget_items bi
                 JOIN budget_categories bc ON bc.id=bi.category_id
                 LEFT JOIN expenses e ON e.category_id=bi.category_id
                   AND e.budget_id=bi.budget_id
                 WHERE bi.budget_id=?
                 GROUP BY bi.category_id, bi.allocated, bc.name, bc.color, bc.icon
                 ORDER BY bc.sort_order",
                [$budget['id']]
            )->fetchAll();

            foreach ($items as &$item) {
                $item['remaining'] = $item['allocated'] - $item['spent'];
                $item['percent']   = $item['allocated'] > 0
                    ? min(100, round(($item['spent'] / $item['allocated']) * 100))
                    : 0;
                $item['status'] = $item['percent'] >= 100 ? 'over'
                    : ($item['percent'] >= 80 ? 'warning' : 'ok');
            }

            $result[] = [
                'budget' => $budget,
                'items'  => $items,
                'total_allocated' => array_sum(array_column($items, 'allocated')),
                'total_spent'     => array_sum(array_column($items, 'spent')),
            ];
        }

        return $result;
    }

    // ── 4. Month-over-month trends ────────────────────────────

    public function monthOverMonth(int $userId, int $months = 12): array
    {
        $from = date('Y-m-01', strtotime("-{$months} months"));
        $to   = date('Y-m-t');

        $income = $this->query(
            "SELECT DATE_FORMAT(received_date,'%Y-%m') AS mo, SUM(amount) AS total
             FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?
             GROUP BY mo",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $expenses = $this->query(
            "SELECT DATE_FORMAT(expense_date,'%Y-%m') AS mo, SUM(amount) AS total
             FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?
             GROUP BY mo",
            [$userId, $from, $to]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $rows = [];
        $allMonths = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $allMonths[] = date('Y-m', $cur);
            $cur = strtotime('+1 month', $cur);
        }

        $prevInc = null;
        $prevExp = null;
        foreach ($allMonths as $mo) {
            $inc = (float)($income[$mo]   ?? 0);
            $exp = (float)($expenses[$mo] ?? 0);
            $rows[] = [
                'month'          => $mo,
                'label'          => date('M Y', strtotime($mo . '-01')),
                'income'         => $inc,
                'expenses'       => $exp,
                'net'            => $inc - $exp,
                'savings_rate'   => $inc > 0 ? round((($inc - $exp) / $inc) * 100, 1) : 0,
                'inc_change_pct' => $prevInc > 0 ? round((($inc - $prevInc) / $prevInc) * 100, 1) : null,
                'exp_change_pct' => $prevExp > 0 ? round((($exp - $prevExp) / $prevExp) * 100, 1) : null,
            ];
            $prevInc = $inc;
            $prevExp = $exp;
        }

        return $rows;
    }

    // ── 5. Debt payoff progress ───────────────────────────────

    public function debtProgress(int $userId): array
    {
        $debts = $this->query(
            "SELECT d.*,
                    COALESCE(SUM(p.amount),0) AS total_paid,
                    COUNT(p.id) AS payment_count
             FROM debts d
             LEFT JOIN debt_payments p ON p.debt_id=d.id
             WHERE d.user_id=?
             GROUP BY d.id
             ORDER BY d.is_paid_off ASC, d.balance ASC",
            [$userId]
        )->fetchAll();

        // Monthly payment history across all debts
        $monthly = $this->query(
            "SELECT DATE_FORMAT(p.paid_date,'%Y-%m') AS mo,
                    SUM(p.amount) AS total,
                    COUNT(p.id) AS payments
             FROM debt_payments p
             JOIN debts d ON d.id=p.debt_id
             WHERE d.user_id=?
             GROUP BY mo ORDER BY mo",
            [$userId]
        )->fetchAll();

        $totalOriginal = array_sum(array_column($debts, 'original_balance'));
        $totalRemaining = array_sum(array_column(
            array_filter($debts, fn($d) => !$d['is_paid_off']),
            'balance'
        ));
        $totalPaid = $totalOriginal - $totalRemaining;

        return [
            'debts'          => $debts,
            'monthly'        => $monthly,
            'total_original' => $totalOriginal,
            'total_remaining'=> $totalRemaining,
            'total_paid'     => $totalPaid,
            'paid_off_count' => count(array_filter($debts, fn($d) => $d['is_paid_off'])),
            'active_count'   => count(array_filter($debts, fn($d) => !$d['is_paid_off'])),
            'percent_paid'   => $totalOriginal > 0
                ? round(($totalPaid / $totalOriginal) * 100, 1) : 0,
        ];
    }

    // ── Summary KPIs (top of analytics page) ─────────────────

    public function kpis(int $userId, string $from, string $to): array
    {
        $inc = (float)$this->query(
            "SELECT COALESCE(SUM(amount),0) FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchColumn();

        $exp = (float)$this->query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchColumn();

        $txnCount = (int)$this->query(
            "SELECT COUNT(*) FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?",
            [$userId, $from, $to]
        )->fetchColumn();

        $topCat = $this->query(
            "SELECT bc.name, SUM(e.amount) AS total
             FROM expenses e JOIN budget_categories bc ON bc.id=e.category_id
             WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?
             GROUP BY bc.id ORDER BY total DESC LIMIT 1",
            [$userId, $from, $to]
        )->fetch();

        // Compare to previous period of same length
        $days    = max(1, (strtotime($to) - strtotime($from)) / 86400);
        $prevTo  = date('Y-m-d', strtotime($from) - 86400);
        $prevFrom= date('Y-m-d', strtotime($prevTo) - ($days * 86400));

        $prevInc = (float)$this->query(
            "SELECT COALESCE(SUM(amount),0) FROM income_entries WHERE user_id=? AND received_date BETWEEN ? AND ?",
            [$userId, $prevFrom, $prevTo]
        )->fetchColumn();

        $prevExp = (float)$this->query(
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id=? AND expense_date BETWEEN ? AND ?",
            [$userId, $prevFrom, $prevTo]
        )->fetchColumn();

        return [
            'income'       => $inc,
            'expenses'     => $exp,
            'net'          => $inc - $exp,
            'savings_rate' => $inc > 0 ? round((($inc - $exp) / $inc) * 100, 1) : 0,
            'txn_count'    => $txnCount,
            'top_category' => $topCat['name']  ?? '—',
            'top_cat_amt'  => $topCat['total'] ?? 0,
            'inc_vs_prev'  => $prevInc > 0 ? round((($inc - $prevInc) / $prevInc) * 100, 1) : null,
            'exp_vs_prev'  => $prevExp > 0 ? round((($exp - $prevExp) / $prevExp) * 100, 1) : null,
            'period_days'  => (int)$days,
        ];
    }

    // ── CSV export ────────────────────────────────────────────

    public function exportCsv(int $userId, string $from, string $to, string $type): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="budjit_' . $type . '_' . $from . '_' . $to . '.csv"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        switch ($type) {
            case 'income_vs_expenses':
                fputcsv($out, ['Date', 'Income', 'Expenses', 'Net']);
                foreach ($this->incomeVsExpensesDaily($userId, $from, $to) as $row) {
                    fputcsv($out, [$row['date'], $row['income'], $row['expense'], $row['net']]);
                }
                break;

            case 'by_category':
                fputcsv($out, ['Category', 'Total Spent', 'Transactions', '% of Total']);
                $data = $this->spendingByCategory($userId, $from, $to);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, [$row['name'], $row['total'], $row['txn_count'], $row['percent'] . '%']);
                }
                break;

            case 'month_over_month':
                fputcsv($out, ['Month', 'Income', 'Expenses', 'Net', 'Savings Rate %', 'Income Change %', 'Expense Change %']);
                foreach ($this->monthOverMonth($userId, 24) as $row) {
                    fputcsv($out, [
                        $row['label'], $row['income'], $row['expenses'],
                        $row['net'], $row['savings_rate'],
                        $row['inc_change_pct'] ?? '', $row['exp_change_pct'] ?? '',
                    ]);
                }
                break;

            case 'debt_progress':
                fputcsv($out, ['Debt Name', 'Type', 'Original Balance', 'Current Balance', 'Total Paid', 'APR %', 'Status']);
                $data = $this->debtProgress($userId);
                foreach ($data['debts'] as $d) {
                    fputcsv($out, [
                        $d['name'], $d['debt_type'], $d['original_balance'],
                        $d['balance'], $d['total_paid'], $d['interest_rate'],
                        $d['is_paid_off'] ? 'Paid off' : 'Active',
                    ]);
                }
                break;

            case 'transactions':
            default:
                fputcsv($out, ['Date', 'Description', 'Category', 'Amount', 'Paid']);
                $rows = $this->query(
                    "SELECT e.expense_date, e.description, bc.name AS category, e.amount, e.is_paid
                     FROM expenses e JOIN budget_categories bc ON bc.id=e.category_id
                     WHERE e.user_id=? AND e.expense_date BETWEEN ? AND ?
                     ORDER BY e.expense_date DESC",
                    [$userId, $from, $to]
                )->fetchAll();
                foreach ($rows as $row) {
                    fputcsv($out, [$row['expense_date'], $row['description'], $row['category'], $row['amount'], $row['is_paid'] ? 'Yes' : 'No']);
                }
                break;
        }

        fclose($out);
        exit;
    }

    // ── Utility ───────────────────────────────────────────────

    private function dateSeries(string $from, string $to): array
    {
        $dates = [];
        $cur   = strtotime($from);
        $end   = strtotime($to);
        $limit = 366; // safety cap
        while ($cur <= $end && $limit-- > 0) {
            $dates[] = date('Y-m-d', $cur);
            $cur = strtotime('+1 day', $cur);
        }
        return $dates;
    }
}
