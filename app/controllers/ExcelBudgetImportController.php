<?php
// app/controllers/ExcelBudgetImportController.php
// DEPRECATED — replaced by FinancialImportController.
// Kept as a redirect stub so any stale bookmarks/routes land gracefully.

class ExcelBudgetImportController extends Controller
{
    public function index(): void
    {
        $this->redirect('import');
    }

    public function __call(string $method, array $args): void
    {
        $this->redirect('import');
    }
}
