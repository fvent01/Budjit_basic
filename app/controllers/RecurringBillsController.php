<?php
// app/controllers/RecurringBillsController.php

class RecurringBillsController extends Controller
{
    private RecurringBillModel $bills;

    public function __construct() { $this->bills = new RecurringBillModel(); }

    public function index(): void
    {
        Auth::requireLogin();
        $userId  = Auth::id();
        $bills   = $this->bills->getForUser($userId);
        $unpaid  = $this->bills->getUpcomingUnpaid($userId, 30);
        $monthly = $this->bills->getMonthlyTotal($userId);
        $this->view('recurring_bills.index', compact('bills', 'unpaid', 'monthly'));
    }

    public function create(): void
    {
        Auth::requireWriteAccess();
        $this->view('recurring_bills.create');
    }

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $data = $this->validateInput();
        if (isset($data['errors'])) { $this->view('recurring_bills.create', ['errors' => $data['errors']]); return; }
        $id = $this->bills->insert(['user_id' => Auth::id()] + $data);
        $this->bills->generateLogs($id);
        $this->flashSuccess('Recurring bill added.');
        $this->redirect('recurring-bills');
    }

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $bill = $this->ownedBill((int)$id);
        $this->view('recurring_bills.edit', compact('bill'));
    }

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $bill = $this->ownedBill((int)$id);
        $data = $this->validateInput();
        if (isset($data['errors'])) { $this->view('recurring_bills.edit', ['errors' => $data['errors'], 'bill' => $bill]); return; }
        $this->bills->update($bill['id'], $data);
        $this->bills->generateLogs($bill['id']);
        $this->flashSuccess('Bill updated.');
        $this->redirect('recurring-bills');
    }

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $bill = $this->ownedBill((int)$id);
        $this->bills->delete($bill['id']);
        $this->flashSuccess('Bill removed.');
        $this->redirect('recurring-bills');
    }

    public function markPaid(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        // $id here is the log ID
        $this->bills->markPaid((int)$id);
        $this->flashSuccess('Bill marked as paid.');
        $this->back();
    }

    private function ownedBill(int $id): array
    {
        $bill = $this->bills->find($id);
        if (!$bill || $bill['user_id'] !== Auth::id()) { http_response_code(403); die(); }
        return $bill;
    }

    private function validateInput(): array
    {
        $freqs = ['weekly','biweekly','monthly','quarterly','annually'];
        $data = [
            'name'        => $this->sanitize($this->input('name', '')),
            'category'    => $this->sanitize($this->input('category', 'Subscription')),
            'amount'      => (float)$this->input('amount', 0),
            'frequency'   => in_array($this->input('frequency'), $freqs) ? $this->input('frequency') : 'monthly',
            'due_day'     => max(1, min(31, (int)$this->input('due_day', 1))),
            'billing_url' => $this->sanitize($this->input('billing_url', '')),
            'auto_pay'    => (int)(bool)$this->input('auto_pay', 0),
            'icon'        => $this->sanitize($this->input('icon', 'ti-refresh')),
            'color'       => $this->sanitize($this->input('color', '#378ADD')),
            'notes'       => $this->sanitize($this->input('notes', '')),
        ];
        $errors = [];
        if (!$data['name'])        $errors[] = 'Name is required.';
        if ($data['amount'] <= 0)  $errors[] = 'Amount must be greater than zero.';
        return $errors ? ['errors' => $errors] : $data;
    }
}
