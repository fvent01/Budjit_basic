<?php
// app/controllers/AnalyticsController.php

class AnalyticsController extends Controller
{
    private AnalyticsModel $model;

    public function __construct()
    {
        $this->model = new AnalyticsModel();
    }

    // ── GET /analytics ────────────────────────────────────────
    public function index(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();

        // Period selector
        $period = $this->input('period', 'month');
        $from   = $this->input('from', '');
        $to     = $this->input('to',   '');
        [$from, $to] = AnalyticsModel::rangeForPeriod($period, $from, $to);

        // Decide granularity for income vs expense chart
        $days = max(1, (strtotime($to) - strtotime($from)) / 86400);
        if ($days <= 31) {
            $iveSeries = $this->model->incomeVsExpensesDaily($userId, $from, $to);
            $iveGranularity = 'daily';
        } elseif ($days <= 120) {
            $iveSeries = $this->model->incomeVsExpensesWeekly($userId, $from, $to);
            $iveGranularity = 'weekly';
        } else {
            $iveSeries = $this->model->incomeVsExpensesMonthly($userId, $from, $to);
            $iveGranularity = 'monthly';
        }

        $kpis         = $this->model->kpis($userId, $from, $to);
        $byCategory   = $this->model->spendingByCategory($userId, $from, $to);
        $budgetVsActual = $this->model->budgetVsActual($userId, $from, $to);
        $momTrends    = $this->model->monthOverMonth($userId, $period === 'year' ? 24 : 12);
        $catTrend     = $this->model->categoryTrendMonthly($userId, $from, $to);
        $debtProgress = $this->model->debtProgress($userId);

        $this->view('analytics.index', compact(
            'period', 'from', 'to',
            'kpis',
            'iveSeries', 'iveGranularity',
            'byCategory',
            'budgetVsActual',
            'momTrends',
            'catTrend',
            'debtProgress'
        ));
    }

    // ── GET /analytics/export ─────────────────────────────────
    public function export(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $type   = $this->input('type', 'transactions');
        $period = $this->input('period', 'month');
        $from   = $this->input('from', '');
        $to     = $this->input('to',   '');
        [$from, $to] = AnalyticsModel::rangeForPeriod($period, $from, $to);
        $this->model->exportCsv($userId, $from, $to, $type);
    }
}
