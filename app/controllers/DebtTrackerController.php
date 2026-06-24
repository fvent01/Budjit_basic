<?php
// app/controllers/DebtTrackerController.php

class DebtTrackerController extends Controller
{
    private DebtModel $debts;

    public function __construct() { $this->debts = new DebtModel(); }

    public function index(): void
    {
        Auth::requireLogin();
        $userId     = Auth::id();
        $debts      = $this->debts->getForUser($userId);
        $totalDebt  = $this->debts->getTotalDebt($userId);
        $extra      = (float)($this->input('extra', 0));
        $projection = $this->debts->snowballProjection($userId, $extra);
        $this->view('debt_tracker.index', compact('debts', 'totalDebt', 'projection', 'extra'));
    }

    public function create(): void
    {
        Auth::requireWriteAccess();
        $this->view('debt_tracker.create');
    }

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $data = $this->validateDebtInput();
        if (isset($data['errors'])) { $this->view('debt_tracker.create', ['errors' => $data['errors']]); return; }
        $data['original_balance'] = $data['balance'];
        $this->debts->insert(['user_id' => Auth::id()] + $data);
        $this->flashSuccess('Debt added.');
        $this->redirect('debt-tracker');
    }

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $debt = $this->ownedDebt((int)$id);
        $payments = $this->debts->getPayments($debt['id']);
        $this->view('debt_tracker.edit', compact('debt', 'payments'));
    }

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $debt = $this->ownedDebt((int)$id);
        $data = $this->validateDebtInput();
        if (isset($data['errors'])) { $this->view('debt_tracker.edit', ['errors' => $data['errors'], 'debt' => $debt]); return; }
        $this->debts->update($debt['id'], $data);
        $this->flashSuccess('Debt updated.');
        $this->redirect('debt-tracker');
    }

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $debt = $this->ownedDebt((int)$id);
        $this->debts->delete($debt['id']);
        $this->flashSuccess('Debt removed.');
        $this->redirect('debt-tracker');
    }

    public function addPayment(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $debt   = $this->ownedDebt((int)$id);
        $amount = (float)$this->input('amount', 0);
        $note   = $this->sanitize($this->input('note', ''));
        $date   = $this->input('paid_date', date('Y-m-d'));
        if ($amount <= 0) { $this->flashError('Payment must be greater than zero.'); $this->redirect('debt-tracker'); return; }
        $this->debts->addPayment($debt['id'], Auth::id(), $amount, $note, $date);
        $this->flashSuccess('Payment of $' . number_format($amount, 2) . ' recorded.');
        $this->redirect('debt-tracker');
    }

    private function ownedDebt(int $id): array
    {
        $debt = $this->debts->find($id);
        if (!$debt || $debt['user_id'] !== Auth::id()) { http_response_code(403); die(); }
        return $debt;
    }

    private function validateDebtInput(): array
    {
        $types = ['credit_card','student_loan','auto','medical','personal','other'];
        $data = [
            'name'            => $this->sanitize($this->input('name', '')),
            'debt_type'       => in_array($this->input('debt_type'), $types) ? $this->input('debt_type') : 'other',
            'balance'         => (float)$this->input('balance', 0),
            'interest_rate'   => (float)$this->input('interest_rate', 0),
            'minimum_payment' => (float)$this->input('minimum_payment', 0),
            'due_day'         => $this->input('due_day') ? (int)$this->input('due_day') : null,
            'notes'           => $this->sanitize($this->input('notes', '')),
        ];
        $errors = [];
        if (!$data['name'])          $errors[] = 'Name is required.';
        if ($data['balance'] <= 0)   $errors[] = 'Balance must be greater than zero.';
        return $errors ? ['errors' => $errors] : $data;
    }
}
