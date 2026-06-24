<?php
// app/controllers/DashboardController.php

class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();

        $budgetModel  = new BudgetModel();
        $expenseModel = new ExpenseModel();
        $incomeModel  = new IncomeEntryModel();

        // Current budget (this week / month)
        $budget = $budgetModel->getCurrentBudget($userId);

        // Date range — default to current week (Mon–Sun)
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime('sunday this week'));

        // Key metrics
        $totalIncome   = $incomeModel->getTotalForDateRange($userId, $weekStart, $weekEnd);
        $totalExpenses = $expenseModel->getTotalForDateRange($userId, $weekStart, $weekEnd);
        $remaining     = $totalIncome - $totalExpenses;
        $savingsRate   = $totalIncome > 0
            ? round(($remaining / $totalIncome) * 100, 1)
            : 0;

        // Chart data — daily income/expense for the week
        $dailyIncome   = $incomeModel->getDailyTotals($userId, $weekStart, $weekEnd);
        $dailyExpenses = $expenseModel->getDailyTotals($userId, $weekStart, $weekEnd);

        // Index by date for easy lookup in the view
        $incomeByDay  = array_column($dailyIncome,   'total', 'day');
        $expenseByDay = array_column($dailyExpenses, 'total', 'day');

        // Build a full 7-day array (fill zeros for missing days)
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date       = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
            $days[$date] = [
                'label'   => date('D', strtotime($date)),
                'income'  => (float)($incomeByDay[$date]  ?? 0),
                'expense' => (float)($expenseByDay[$date] ?? 0),
            ];
        }

        // Category totals for donut chart
        $categoryTotals = $expenseModel->getCategoryTotals($userId, $weekStart, $weekEnd);

        // Recent expenses + unpaid count
        $recentExpenses = $expenseModel->getRecent($userId, 5);
        $unpaidCount    = $expenseModel->countUnpaid($userId);

        // Upcoming bills (unpaid expenses in the next 30 days)
        $upcomingBills = $expenseModel->getUnpaid($userId);

        // Budget vs actual (if budget exists)
        $vsData = $budget ? $expenseModel->getVsBudget($budget['id']) : [];

        // Over-budget alerts
        $alerts = [];
        foreach ($vsData as $row) {
            if ($row['allocated'] > 0 && $row['spent'] > 0) {
                $pct = ($row['spent'] / $row['allocated']) * 100;
                if ($pct >= 90) {
                    $alerts[] = [
                        'category' => $row['name'],
                        'percent'  => round($pct),
                        'color'    => $row['color'],
                    ];
                }
            }
        }

        $this->view('dashboard.index', compact(
            'budget',
            'totalIncome',
            'totalExpenses',
            'remaining',
            'savingsRate',
            'days',
            'categoryTotals',
            'recentExpenses',
            'unpaidCount',
            'upcomingBills',
            'vsData',
            'alerts',
            'weekStart',
            'weekEnd'
        ));
    }
}
