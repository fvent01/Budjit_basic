<?php
// app/controllers/PaystubController.php

class PaystubController extends Controller
{
    private PaystubModel $stubs;

    public function __construct()
    {
        $this->stubs = new PaystubModel();
    }

    // ── GET /paystub ──────────────────────────────────────────
    public function index(): void
    {
        Auth::requireLogin();
        $stubs   = $this->stubs->getForUser(Auth::id());
        $sources = (new IncomeSourceModel())->getActiveForUser(Auth::id());
        $this->view('paystub_import.index', compact('stubs', 'sources'));
    }

    // ── POST /paystub/upload ──────────────────────────────────
    public function upload(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $file   = $_FILES['stub_pdf'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flashError('Upload failed. Please try again or use the manual form.');
            $this->redirect('paystub');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $this->flashError('Only PDF files are supported for automatic parsing.');
            $this->redirect('paystub');
            return;
        }

        // Store the file
        $uploadDir = STORAGE_PATH . '/uploads/paystubs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'stub_' . $userId . '_' . time() . '.pdf';
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->flashError('Could not save uploaded file.');
            $this->redirect('paystub');
            return;
        }

        // Parse
        try {
            $parsed = $this->stubs->parsePdf($dest);
        } catch (Exception $e) {
            error_log('Paystub parse error: ' . $e->getMessage());
            $this->flashError('Could not parse the PDF: ' . $e->getMessage() . '. Please use the manual entry form below.');
            $this->redirect('paystub/manual');
            return;
        }

        // Check YTD anomalies against prior stubs
        $parsed['ytd_flag_reason'] = implode('; ', $this->stubs->checkYtdAnomaly($userId, $parsed));
        $parsed['ytd_flag']        = !empty($parsed['ytd_flag_reason']) ? 1 : 0;

        // Save
        $stubId = $this->stubs->saveStub($userId, $parsed, 'pdf', $file['name']);

        $this->flashSuccess('Pay stub parsed successfully. Review the details below before importing.');
        $this->redirect("paystub/{$stubId}/review");
    }

    // ── GET /paystub/manual ───────────────────────────────────
    public function manualForm(): void
    {
        Auth::requireWriteAccess();
        $sources = (new IncomeSourceModel())->getActiveForUser(Auth::id());
        $budgets = (new BudgetModel())->getForUser(Auth::id());
        $this->view('paystub_import.manual', compact('sources', 'budgets'));
    }

    // ── POST /paystub/manual ──────────────────────────────────
    public function manualStore(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $data   = $this->collectFormData();
        $errors = $this->validateStubData($data);

        if ($errors) {
            $sources = (new IncomeSourceModel())->getActiveForUser($userId);
            $budgets = (new BudgetModel())->getForUser($userId);
            $this->view('paystub_import.manual', ['errors' => $errors, 'data' => $data, 'sources' => $sources, 'budgets' => $budgets]);
            return;
        }

        // Run anomaly checks
        $flags = $this->stubs->checkYtdAnomaly($userId, $data);
        $data['ytd_flag']        = !empty($flags) ? 1 : 0;
        $data['ytd_flag_reason'] = implode('; ', $flags);

        $stubId = $this->stubs->saveStub($userId, $data, 'manual');
        $this->flashSuccess('Pay stub saved. Review before importing.');
        $this->redirect("paystub/{$stubId}/review");
    }

    // ── GET /paystub/{id}/review ──────────────────────────────
    public function review(string $id): void
    {
        Auth::requireLogin();
        $stub    = $this->ownedStub((int)$id);
        $sources = (new IncomeSourceModel())->getActiveForUser(Auth::id());
        $budgets = (new BudgetModel())->getForUser(Auth::id());
        $current = (new BudgetModel())->getCurrentBudget(Auth::id());
        $other   = json_decode($stub['other_earnings_json'] ?: '{}', true);
        $this->view('paystub_import.review', compact('stub', 'sources', 'budgets', 'current', 'other'));
    }

    // ── POST /paystub/{id}/confirm ────────────────────────────
    public function confirm(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $stub   = $this->ownedStub((int)$id);
        $userId = Auth::id();

        $overrides = [
            'net_pay'          => (float)$this->input('net_pay',          $stub['net_pay']),
            'pay_date'         => $this->input('pay_date',                 $stub['pay_date']),
            'description'      => $this->sanitize($this->input('description', '')),
            'income_source_id' => $this->input('income_source_id')  ?: null,
            'budget_id'        => $this->input('budget_id')         ?: null,
        ];

        try {
            $this->stubs->confirmImport($stub['id'], $userId, $overrides);
            $this->flashSuccess('Pay stub imported — net pay of $' . number_format($overrides['net_pay'], 2) . ' added to income.');
        } catch (Exception $e) {
            $this->flashError('Import failed: ' . $e->getMessage());
        }

        $this->redirect('paystub');
    }

    // ── POST /paystub/{id}/skip ───────────────────────────────
    public function skip(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $stub = $this->ownedStub((int)$id);
        $this->stubs->update($stub['id'], ['status' => 'skipped']);
        $this->flashInfo('Pay stub marked as skipped.');
        $this->redirect('paystub');
    }

    // ── GET /paystub/{id}/edit ────────────────────────────────
    public function edit(string $id): void
    {
        Auth::requireWriteAccess();
        $stub    = $this->ownedStub((int)$id);
        $sources = (new IncomeSourceModel())->getActiveForUser(Auth::id());
        $budgets = (new BudgetModel())->getForUser(Auth::id());
        $other   = json_decode($stub['other_earnings_json'] ?: '{}', true);
        $this->view('paystub_import.edit', compact('stub', 'sources', 'budgets', 'other'));
    }

    // ── POST /paystub/{id}/update ─────────────────────────────
    public function update(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $stub   = $this->ownedStub((int)$id);
        $data   = $this->collectFormData();
        $errors = $this->validateStubData($data);

        if ($errors) {
            $sources = (new IncomeSourceModel())->getActiveForUser(Auth::id());
            $budgets = (new BudgetModel())->getForUser(Auth::id());
            $other   = json_decode($stub['other_earnings_json'] ?: '{}', true);
            $this->view('paystub_import.edit', ['errors' => $errors, 'stub' => array_merge($stub, $data), 'sources' => $sources, 'budgets' => $budgets, 'other' => $other]);
            return;
        }

        $this->stubs->update($stub['id'], $data);
        $this->flashSuccess('Pay stub updated.');
        $this->redirect("paystub/{$stub['id']}/review");
    }

    // ── POST /paystub/{id}/delete ─────────────────────────────
    public function delete(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $stub = $this->ownedStub((int)$id);
        $this->stubs->delete($stub['id']);
        $this->flashSuccess('Pay stub deleted.');
        $this->redirect('paystub');
    }

    // ── Helpers ───────────────────────────────────────────────

    private function ownedStub(int $id): array
    {
        $stub = $this->stubs->find($id);
        if (!$stub || $stub['user_id'] !== Auth::id()) {
            http_response_code(403); die('Access denied.');
        }
        return $stub;
    }

    private function collectFormData(): array
    {
        return [
            'employer_name'    => $this->sanitize($this->input('employer_name', '')),
            'employee_name'    => $this->sanitize($this->input('employee_name', '')),
            'period_beginning' => $this->input('period_beginning', ''),
            'period_ending'    => $this->input('period_ending',    ''),
            'pay_date'         => $this->input('pay_date',         ''),
            'gross_pay'        => (float)$this->input('gross_pay',         0),
            'net_pay'          => (float)$this->input('net_pay',           0),
            'regular_hours'    => (float)$this->input('regular_hours',     0),
            'regular_amount'   => (float)$this->input('regular_amount',    0),
            'overtime_hours'   => (float)$this->input('overtime_hours',    0),
            'overtime_amount'  => (float)$this->input('overtime_amount',   0),
            'other_earnings'   => (float)$this->input('other_earnings',    0),
            'federal_tax'      => (float)$this->input('federal_tax',       0),
            'social_security'  => (float)$this->input('social_security',   0),
            'medicare'         => (float)$this->input('medicare',          0),
            'state_tax'        => (float)$this->input('state_tax',         0),
            'local_tax'        => (float)$this->input('local_tax',         0),
            'other_deductions' => (float)$this->input('other_deductions',  0),
            'total_deductions' => (float)$this->input('total_deductions',  0),
            'ytd_gross'        => (float)$this->input('ytd_gross',         0),
            'other_earnings_json' => '{}',
        ];
    }

    private function validateStubData(array $data): array
    {
        $errors = [];
        if (!$data['period_beginning']) $errors[] = 'Period beginning date is required.';
        if (!$data['period_ending'])    $errors[] = 'Period ending date is required.';
        if (!$data['pay_date'])         $errors[] = 'Pay date is required.';
        if ($data['net_pay'] <= 0)      $errors[] = 'Net pay must be greater than zero.';
        return $errors;
    }
}
