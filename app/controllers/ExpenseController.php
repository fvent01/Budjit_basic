<?php
// app/controllers/ExpenseController.php

class ExpenseController extends Controller
{
    private ExpenseModel  $expenses;
    private CategoryModel $categories;
    private BudgetModel   $budgets;

    public function __construct()
    {
        $this->expenses   = new ExpenseModel();
        $this->categories = new CategoryModel();
        $this->budgets    = new BudgetModel();
    }

    // ── GET /expenses ─────────────────────────────────────────

    public function index(): void
    {
        Auth::requireLogin();
        $userId   = Auth::id();
        $page     = max(1, (int)$this->input('page', 1));
        $limit    = 25;
        $offset   = ($page - 1) * $limit;
        $expenses = $this->expenses->getForUser($userId, $limit, $offset);
        $total    = $this->expenses->count(['user_id' => $userId]);
        $pages    = (int)ceil($total / $limit);
        $this->view('expenses.index', compact('expenses', 'page', 'pages', 'total'));
    }

    // ── GET /expenses/create ──────────────────────────────────

    public function create(): void
    {
        Auth::requireWriteAccess();
        $userId     = Auth::id();
        $categories = $this->categories->getForUser($userId);
        $budgets    = $this->budgets->getForUser($userId);
        $budget     = $this->budgets->getCurrentBudget($userId);
        $this->view('expenses.create', compact('categories', 'budgets', 'budget'));
    }

    // ── POST /expenses ────────────────────────────────────────

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $data   = $this->validateExpenseInput();

        if (isset($data['errors'])) {
            $categories = $this->categories->getForUser($userId);
            $budgets    = $this->budgets->getForUser($userId);
            $this->view('expenses.create', ['errors' => $data['errors'], 'categories' => $categories, 'budgets' => $budgets]);
            return;
        }

        $this->expenses->addExpense($userId, $data);
        $this->flashSuccess('Expense added.');
        $this->redirect('expenses');
    }

    // ── GET /expenses/{id}/edit ───────────────────────────────

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $expense    = $this->ownedExpense((int)$id);
        $categories = $this->categories->getForUser(Auth::id());
        $budgets    = $this->budgets->getForUser(Auth::id());
        $this->view('expenses.edit', compact('expense', 'categories', 'budgets'));
    }

    // ── POST /expenses/{id}/update ────────────────────────────

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $expense = $this->ownedExpense((int)$id);
        $data    = $this->validateExpenseInput();

        if (isset($data['errors'])) {
            $categories = $this->categories->getForUser(Auth::id());
            $budgets    = $this->budgets->getForUser(Auth::id());
            $this->view('expenses.edit', ['errors' => $data['errors'], 'expense' => $expense, 'categories' => $categories, 'budgets' => $budgets]);
            return;
        }

        $this->expenses->update($expense['id'], $data);
        $this->flashSuccess('Expense updated.');
        $this->redirect('expenses');
    }

    // ── POST /expenses/{id}/delete ────────────────────────────

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $expense = $this->ownedExpense((int)$id);
        $this->expenses->delete($expense['id']);
        $this->flashSuccess('Expense deleted.');
        $this->redirect('expenses');
    }

    // ── POST /expenses/{id}/pay ───────────────────────────────

    public function markPaid(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $expense = $this->ownedExpense((int)$id);
        $this->expenses->markPaid($expense['id']);
        $this->flashSuccess('Marked as paid.');
        $this->back();
    }

    // ── Helpers ───────────────────────────────────────────────

    private function ownedExpense(int $id): array
    {
        $expense = $this->expenses->find($id);
        if (!$expense || $expense['user_id'] !== Auth::id()) {
            http_response_code(403);
            die('Expense not found or access denied.');
        }
        return $expense;
    }

    private function validateExpenseInput(): array
    {
        $data = [
            'description'  => $this->sanitize($this->input('description', '')),
            'amount'       => (float)$this->input('amount', 0),
            'expense_date' => $this->input('expense_date', ''),
            'category_id'  => (int)$this->input('category_id', 0),
            'budget_id'    => $this->input('budget_id') ?: null,
            'is_paid'      => (int)(bool)$this->input('is_paid', 0),
            'is_recurring' => (int)(bool)$this->input('is_recurring', 0),
            'notes'        => $this->sanitize($this->input('notes', '')),
        ];

        $errors = [];
        if (strlen($data['description']) < 1) $errors[] = 'Description is required.';
        if ($data['amount'] <= 0)             $errors[] = 'Amount must be greater than zero.';
        if (!$data['expense_date'])           $errors[] = 'Date is required.';
        if ($data['category_id'] < 1)        $errors[] = 'Please select a category.';

        return $errors ? ['errors' => $errors] : $data;
    }
}
