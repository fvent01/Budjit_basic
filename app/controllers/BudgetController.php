<?php
// app/controllers/BudgetController.php

class BudgetController extends Controller
{
    private BudgetModel    $budgets;
    private BudgetItemModel $items;
    private CategoryModel  $categories;

    public function __construct()
    {
        $this->budgets    = new BudgetModel();
        $this->items      = new BudgetItemModel();
        $this->categories = new CategoryModel();
    }

    // ── GET /budgets ──────────────────────────────────────────

    public function index(): void
    {
        Auth::requireLogin();
        $userId  = Auth::id();
        $active  = $this->budgets->getForUser($userId, 'active');
        $archived = $this->budgets->getForUser($userId, 'archived');
        $this->view('budget.index', compact('active', 'archived'));
    }

    // ── GET /budgets/create ───────────────────────────────────

    public function create(): void
    {
        Auth::requireWriteAccess();
        $categories = $this->categories->getForUser(Auth::id());

        // Suggest this week's Mon–Sun as defaults
        $defaults = [
            'start_date' => date('Y-m-d', strtotime('monday this week')),
            'end_date'   => date('Y-m-d', strtotime('sunday this week')),
        ];

        $this->view('budget.create', compact('categories', 'defaults'));
    }

    // ── POST /budgets ─────────────────────────────────────────

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $data   = $this->validateBudgetInput();

        if (isset($data['errors'])) {
            $categories = $this->categories->getForUser($userId);
            $this->view('budget.create', ['errors' => $data['errors'], 'categories' => $categories]);
            return;
        }

        $budgetId = $this->budgets->createBudget($userId, $data);

        // Save category allocations
        $this->saveAllocations($budgetId);
        $this->budgets->recalculateTotals($budgetId);

        $this->flashSuccess('Budget created successfully!');
        $this->redirect("budgets/{$budgetId}");
    }

    // ── GET /budgets/{id} ─────────────────────────────────────

    public function show(string $id): void
    {
        Auth::requireLogin();
        $budget = $this->ownedBudget((int)$id);

        $expenseModel  = new ExpenseModel();
        $incomeModel   = new IncomeEntryModel();

        $expenses      = $expenseModel->getForBudget($budget['id']);
        $income        = $incomeModel->getForBudget($budget['id']);
        $vsData        = $expenseModel->getVsBudget($budget['id']);
        $totalIncome   = $incomeModel->getTotalForBudget($budget['id']);
        $totalExpenses = $expenseModel->getTotalForBudget($budget['id']);

        $this->view('budget.show', compact(
            'budget', 'expenses', 'income', 'vsData', 'totalIncome', 'totalExpenses'
        ));
    }

    // ── GET /budgets/{id}/edit ────────────────────────────────

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $budget     = $this->ownedBudget((int)$id);
        $categories = $this->categories->getForUser(Auth::id());
        $this->view('budget.edit', compact('budget', 'categories'));
    }

    // ── POST /budgets/{id}/update ─────────────────────────────

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $budget = $this->ownedBudget((int)$id);
        $data   = $this->validateBudgetInput();

        if (isset($data['errors'])) {
            $categories = $this->categories->getForUser(Auth::id());
            $this->view('budget.edit', ['errors' => $data['errors'], 'budget' => $budget, 'categories' => $categories]);
            return;
        }

        $this->budgets->update($budget['id'], $data);
        $this->items->deleteByBudget($budget['id']);
        $this->saveAllocations($budget['id']);
        $this->budgets->recalculateTotals($budget['id']);

        $this->flashSuccess('Budget updated.');
        $this->redirect("budgets/{$budget['id']}");
    }

    // ── POST /budgets/{id}/delete ─────────────────────────────

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $budget = $this->ownedBudget((int)$id);
        $this->budgets->delete($budget['id']);
        $this->flashSuccess('Budget deleted.');
        $this->redirect('budgets');
    }

    // ── POST /budgets/{id}/archive ────────────────────────────

    public function archive(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $budget = $this->ownedBudget((int)$id);
        $this->budgets->archive($budget['id']);
        $this->flashSuccess('Budget archived.');
        $this->redirect('budgets');
    }

    // ── POST /budgets/{id}/duplicate ──────────────────────────

    public function duplicate(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $budget = $this->ownedBudget((int)$id);

        // New period: one week after original
        $newStart = date('Y-m-d', strtotime($budget['end_date'] . ' +1 day'));
        $newEnd   = date('Y-m-d', strtotime($newStart . ' +6 days'));

        $newId = $this->budgets->duplicate($budget['id'], $newStart, $newEnd);
        $this->flashSuccess('Budget duplicated for the next week.');
        $this->redirect("budgets/{$newId}/edit");
    }

    // ── Helpers ───────────────────────────────────────────────

    private function ownedBudget(int $id): array
    {
        $budget = $this->budgets->getWithItems($id);
        if (!$budget || $budget['user_id'] !== Auth::id()) {
            http_response_code(403);
            die('Budget not found or access denied.');
        }
        return $budget;
    }

    private function validateBudgetInput(): array
    {
        $data = [
            'title'        => $this->sanitize($this->input('title', '')),
            'period_type'  => in_array($this->input('period_type'), ['weekly','monthly']) ? $this->input('period_type') : 'weekly',
            'start_date'   => $this->input('start_date', ''),
            'end_date'     => $this->input('end_date',   ''),
            'total_income' => (float)$this->input('total_income', 0),
            'notes'        => $this->sanitize($this->input('notes', '')),
        ];

        $errors = [];
        if (strlen($data['title']) < 2)          $errors[] = 'Title must be at least 2 characters.';
        if (!$data['start_date'])                $errors[] = 'Start date is required.';
        if (!$data['end_date'])                  $errors[] = 'End date is required.';
        if ($data['end_date'] < $data['start_date']) $errors[] = 'End date must be after start date.';

        return $errors ? ['errors' => $errors] : $data;
    }

    private function saveAllocations(int $budgetId): void
    {
        $allocations = $_POST['allocations'] ?? [];
        foreach ($allocations as $categoryId => $amount) {
            $amount = (float)$amount;
            if ($amount > 0) {
                $this->items->upsert($budgetId, (int)$categoryId, $amount);
            }
        }
    }
}
