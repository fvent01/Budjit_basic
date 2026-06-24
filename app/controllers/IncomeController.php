<?php
// app/controllers/IncomeController.php

class IncomeController extends Controller
{
    private IncomeEntryModel  $entries;
    private IncomeSourceModel $sources;
    private BudgetModel       $budgets;

    public function __construct()
    {
        $this->entries  = new IncomeEntryModel();
        $this->sources  = new IncomeSourceModel();
        $this->budgets  = new BudgetModel();
    }

    // ── GET /income ───────────────────────────────────────────

    public function index(): void
    {
        Auth::requireLogin();
        $userId  = Auth::id();
        $entries = $this->entries->getForUser($userId);
        $sources = $this->sources->getActiveForUser($userId);
        $this->view('income.index', compact('entries', 'sources'));
    }

    // ── GET /income/create ────────────────────────────────────

    public function create(): void
    {
        Auth::requireWriteAccess();
        $userId  = Auth::id();
        $sources = $this->sources->getActiveForUser($userId);
        $budgets = $this->budgets->getForUser($userId);
        $this->view('income.create', compact('sources', 'budgets'));
    }

    // ── POST /income ──────────────────────────────────────────

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $data   = $this->validateEntryInput();

        if (isset($data['errors'])) {
            $sources = $this->sources->getActiveForUser($userId);
            $budgets = $this->budgets->getForUser($userId);
            $this->view('income.create', ['errors' => $data['errors'], 'sources' => $sources, 'budgets' => $budgets]);
            return;
        }

        $this->entries->addEntry($userId, $data);
        $this->flashSuccess('Income entry added.');
        $this->redirect('income');
    }

    // ── GET /income/{id}/edit ─────────────────────────────────

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $entry   = $this->ownedEntry((int)$id);
        $sources = $this->sources->getActiveForUser(Auth::id());
        $budgets = $this->budgets->getForUser(Auth::id());
        $this->view('income.edit', compact('entry', 'sources', 'budgets'));
    }

    // ── POST /income/{id}/update ──────────────────────────────

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $entry  = $this->ownedEntry((int)$id);
        $data   = $this->validateEntryInput();

        if (isset($data['errors'])) {
            $sources = $this->sources->getActiveForUser(Auth::id());
            $budgets = $this->budgets->getForUser(Auth::id());
            $this->view('income.edit', ['errors' => $data['errors'], 'entry' => $entry, 'sources' => $sources, 'budgets' => $budgets]);
            return;
        }

        $this->entries->update($entry['id'], $data);
        $this->flashSuccess('Income entry updated.');
        $this->redirect('income');
    }

    // ── POST /income/{id}/delete ──────────────────────────────

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $entry = $this->ownedEntry((int)$id);
        $this->entries->delete($entry['id']);
        $this->flashSuccess('Income entry deleted.');
        $this->redirect('income');
    }

    // ── POST /income/sources ──────────────────────────────────  (add a source)

    public function storeSource(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $name   = $this->sanitize($this->input('name', ''));
        $type   = $this->input('source_type', 'other');
        $freq   = $this->input('frequency', 'one_time');
        $amount = (float)$this->input('default_amount', 0);
        $recur  = (int)(bool)$this->input('is_recurring', 0);

        $validTypes = ['salary','freelance','side_job','benefit','child_support','other'];
        $validFreqs = ['weekly','biweekly','monthly','one_time'];

        if (strlen($name) < 1 || !in_array($type, $validTypes) || !in_array($freq, $validFreqs)) {
            $this->flashError('Invalid source data.');
            $this->redirect('income');
            return;
        }

        $this->sources->insert([
            'user_id'        => $userId,
            'name'           => $name,
            'source_type'    => $type,
            'is_recurring'   => $recur,
            'frequency'      => $freq,
            'default_amount' => $amount,
        ]);

        $this->flashSuccess("Income source \"{$name}\" added.");
        $this->redirect('income');
    }

    // ── Helpers ───────────────────────────────────────────────

    private function ownedEntry(int $id): array
    {
        $entry = $this->entries->find($id);
        if (!$entry || $entry['user_id'] !== Auth::id()) {
            http_response_code(403);
            die('Entry not found or access denied.');
        }
        return $entry;
    }

    private function validateEntryInput(): array
    {
        $data = [
            'description'      => $this->sanitize($this->input('description', '')),
            'amount'           => (float)$this->input('amount', 0),
            'received_date'    => $this->input('received_date', ''),
            'income_source_id' => $this->input('income_source_id') ?: null,
            'budget_id'        => $this->input('budget_id') ?: null,
            'is_recurring'     => (int)(bool)$this->input('is_recurring', 0),
            'notes'            => $this->sanitize($this->input('notes', '')),
        ];

        $errors = [];
        if (strlen($data['description']) < 1) $errors[] = 'Description is required.';
        if ($data['amount'] <= 0)             $errors[] = 'Amount must be greater than zero.';
        if (!$data['received_date'])          $errors[] = 'Date is required.';

        return $errors ? ['errors' => $errors] : $data;
    }
}
