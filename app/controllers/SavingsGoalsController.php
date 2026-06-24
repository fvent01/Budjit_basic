<?php
// app/controllers/SavingsGoalsController.php

class SavingsGoalsController extends Controller
{
    private SavingsGoalModel $goals;

    public function __construct()
    {
        $this->goals = new SavingsGoalModel();
    }

    public function index(): void
    {
        Auth::requireLogin();
        $goals = $this->goals->getForUser(Auth::id());
        $this->view('savings_goals.index', compact('goals'));
    }

    public function create(): void
    {
        Auth::requireWriteAccess();
        $this->view('savings_goals.create');
    }

    public function store(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $data   = $this->validateGoalInput();
        $userId = Auth::id();

        if (isset($data['errors'])) {
            $this->view('savings_goals.create', ['errors' => $data['errors']]);
            return;
        }

        // Assign next priority
        $data['priority'] = $this->goals->nextPriority($userId);
        $this->goals->insert(['user_id' => $userId] + $data);

        $this->flashSuccess('Savings goal created!');
        $this->redirect('savings-goals');
    }

    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $goal = $this->ownedGoal((int)$id);
        $this->view('savings_goals.edit', compact('goal'));
    }

    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $goal = $this->ownedGoal((int)$id);
        $data = $this->validateGoalInput();

        if (isset($data['errors'])) {
            $this->view('savings_goals.edit', ['errors' => $data['errors'], 'goal' => $goal]);
            return;
        }

        $this->goals->update($goal['id'], $data);
        $this->flashSuccess('Goal updated.');
        $this->redirect('savings-goals');
    }

    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $goal = $this->ownedGoal((int)$id);
        $this->goals->delete($goal['id']);
        $this->flashSuccess('Goal deleted.');
        $this->redirect('savings-goals');
    }

    public function contribute(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $goal   = $this->ownedGoal((int)$id);
        $amount = (float)$this->input('amount', 0);
        $note   = $this->sanitize($this->input('note', ''));
        $source = $this->input('source', 'manual');

        if ($amount <= 0) {
            $this->flashError('Contribution amount must be greater than zero.');
            $this->redirect('savings-goals');
            return;
        }

        $this->goals->addContribution($goal['id'], Auth::id(), $amount, $note, $source);
        $this->flashSuccess('$' . number_format($amount, 2) . ' added to "' . $goal['name'] . '".');
        $this->redirect('savings-goals');
    }

    public function reorder(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $order = $_POST['order'] ?? [];
        foreach ($order as $priority => $goalId) {
            $goal = $this->goals->find((int)$goalId);
            if ($goal && $goal['user_id'] === Auth::id()) {
                $this->goals->update((int)$goalId, ['priority' => (int)$priority]);
            }
        }
        $this->json(['ok' => true]);
    }

    private function ownedGoal(int $id): array
    {
        $goal = $this->goals->find($id);
        if (!$goal || $goal['user_id'] !== Auth::id()) {
            http_response_code(403); die('Not found.');
        }
        return $goal;
    }

    private function validateGoalInput(): array
    {
        $data = [
            'name'           => $this->sanitize($this->input('name', '')),
            'icon'           => $this->sanitize($this->input('icon', 'ti-piggy-bank')),
            'color'          => $this->sanitize($this->input('color', '#1D9E75')),
            'target_amount'  => (float)$this->input('target_amount', 0),
            'target_date'    => $this->input('target_date') ?: null,
            'auto_allocate'  => (int)(bool)$this->input('auto_allocate', 0),
            'auto_percent'   => (float)$this->input('auto_percent', 0),
            'notes'          => $this->sanitize($this->input('notes', '')),
        ];

        $errors = [];
        if (strlen($data['name']) < 1)     $errors[] = 'Name is required.';
        if ($data['target_amount'] <= 0)   $errors[] = 'Target amount must be greater than zero.';
        if ($data['auto_allocate'] && $data['auto_percent'] <= 0) $errors[] = 'Auto-allocate percentage must be greater than zero.';

        return $errors ? ['errors' => $errors] : $data;
    }
}
