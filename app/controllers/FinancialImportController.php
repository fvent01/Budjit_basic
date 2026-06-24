<?php
// app/controllers/FinancialImportController.php
// Unified import controller — handles Plaid, CSV, and Excel imports.
// All sources go through the shared FinancialImportPipeline.
// Replaces BankImportController and ExcelBudgetImportController entirely.

class FinancialImportController extends Controller
{
    private PlaidAccountModel       $accounts;
    private PlaidSyncService        $sync;
    private FinancialImportPipeline $pipeline;
    private FinancialImportModel    $importModel;

    public function __construct()
    {
        $this->accounts    = new PlaidAccountModel();
        $this->sync        = new PlaidSyncService();
        $this->pipeline    = new FinancialImportPipeline();
        $this->importModel = new FinancialImportModel();
    }

    // ── GET /import ───────────────────────────────────────────

    /**
     * Main import dashboard: linked Plaid accounts + upload form + recent sessions.
     */
    public function index(): void
    {
        Auth::requireLogin();
        $userId          = Auth::id();
        $accounts        = $this->accounts->getForUser($userId);
        $pendingCount    = $this->importModel->countPendingReview($userId);
        $recentSessions  = $this->importModel->getSessionsForUser($userId, 10);
        $plaidConfigured = defined('PLAID_CLIENT_ID') && PLAID_CLIENT_ID !== '' && PLAID_CLIENT_ID !== 'your_client_id_here';

        $this->view('financial_import.index', compact(
            'accounts', 'pendingCount', 'recentSessions', 'plaidConfigured'
        ));
    }

    // ── POST /import/plaid/link-token ─────────────────────────

    /**
     * Create a Plaid Link token. Called via AJAX from the frontend.
     * Returns JSON {link_token} or {error}.
     */
    public function plaidLinkToken(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        // Optional: re-auth mode when account_id supplied
        $accountId   = $this->input('account_id') ? (int)$this->input('account_id') : null;
        $accessToken = null;

        if ($accountId) {
            $account = $this->accounts->find($accountId);
            if ($account && (int)$account['user_id'] === Auth::id()) {
                try {
                    $accessToken = $this->accounts->getAccessToken($accountId);
                } catch (Exception $e) {
                    $this->json(['error' => 'Could not retrieve account token.'], 400);
                    return;
                }
            }
        }

        $response = PlaidService::createLinkToken(Auth::id(), $accessToken);
        if (isset($response['error'])) {
            $this->json(['error' => $response['error']['error_message'] ?? 'Could not create link token'], 400);
            return;
        }

        $this->json(['link_token' => $response['link_token']]);
    }

    // ── POST /import/plaid/connect ────────────────────────────

    /**
     * Exchange Plaid public_token for access_token, save accounts, and run initial sync.
     * Returns JSON result.
     */
    public function plaidConnect(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        $publicToken = $this->input('public_token', '');
        if (!$publicToken) {
            $this->json(['error' => 'Missing public_token.'], 400);
            return;
        }

        // Step 1: Exchange public token
        $exchange = PlaidService::exchangePublicToken($publicToken);
        if (isset($exchange['error'])) {
            $this->json(['error' => $exchange['error']['error_message'] ?? 'Token exchange failed.'], 400);
            return;
        }

        $accessToken = $exchange['access_token'];
        $itemId      = $exchange['item_id'];
        $userId      = Auth::id();

        // Step 2: Get item + institution details
        $itemResp = PlaidService::call('/item/get', ['access_token' => $accessToken]);
        $item     = $itemResp['item'] ?? [];
        if (!empty($item['institution_id'])) {
            $instResp                 = PlaidService::getInstitution($item['institution_id']);
            $item['institution_name'] = $instResp['institution']['name'] ?? 'Your Bank';
        }

        // Step 3: Get accounts for this item
        $accountsResp = PlaidService::getAccounts($accessToken);
        if (isset($accountsResp['error'])) {
            $this->json(['error' => $accountsResp['error']['error_message'] ?? 'Could not fetch accounts.'], 400);
            return;
        }

        // Step 4: Persist each account (encrypted token stored per account)
        $savedCount = 0;
        foreach ($accountsResp['accounts'] ?? [] as $account) {
            $this->accounts->saveAccount($userId, $accessToken, [
                'item_id'          => $itemId,
                'institution_id'   => $item['institution_id']   ?? '',
                'institution_name' => $item['institution_name'] ?? 'Bank',
            ], $account);
            $savedCount++;
        }

        // Step 5: Initial sync through the pipeline
        try {
            $result = $this->sync->syncAllForUser($userId, 'manual');
            $this->json([
                'ok'       => true,
                'accounts' => $savedCount,
                'added'    => $result['added'],
                'message'  => "Connected {$savedCount} account(s). {$result['added']} transactions synced.",
            ]);
        } catch (Exception $e) {
            error_log("[FinancialImportController] Initial sync failed: " . $e->getMessage());
            $this->json([
                'ok'       => true,
                'accounts' => $savedCount,
                'warning'  => 'Accounts connected but initial sync failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ── POST /import/plaid/sync ───────────────────────────────

    /**
     * Manual Plaid sync. Supports syncing all accounts or a specific account_id.
     * Redirects back to /import with a flash message.
     */
    public function plaidSync(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $acctId = $this->input('account_id') ? (int)$this->input('account_id') : null;

        try {
            $result = $acctId
                ? $this->sync->syncAccount($acctId, $userId, 'manual')
                : $this->sync->syncAllForUser($userId, 'manual');

            $msg = "Sync complete — {$result['added']} new";
            if (!empty($result['modified'])) $msg .= ", {$result['modified']} updated";
            if (!empty($result['removed']))  $msg .= ", {$result['removed']} removed";
            $this->flashSuccess($msg . '.');
        } catch (Exception $e) {
            $this->flashError('Sync failed: ' . $e->getMessage());
        }

        $this->redirect('import');
    }

    // ── POST /import/plaid/disconnect ─────────────────────────

    /**
     * Disconnect a Plaid account: remove the item from Plaid, soft-delete locally.
     */
    public function plaidDisconnect(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $accountId = (int)$this->input('account_id', 0);
        $userId    = Auth::id();
        $account   = $this->accounts->find($accountId);

        if (!$account || (int)$account['user_id'] !== $userId) {
            $this->flashError('Account not found.');
            $this->redirect('import');
            return;
        }

        // Best-effort remove from Plaid; log but do not block on failure
        try {
            $accessToken = $this->accounts->getAccessToken($accountId);
            PlaidService::removeItem($accessToken);
        } catch (Exception $e) {
            error_log('[FinancialImportController] Plaid removeItem error: ' . $e->getMessage());
        }

        $this->accounts->disconnect($accountId, $userId);
        $this->flashSuccess('Bank account disconnected.');
        $this->redirect('import');
    }

    // ── POST /import/upload ───────────────────────────────────

    /**
     * Handle CSV or Excel file upload through the unified pipeline.
     * Returns structured result. On success, redirects to review page.
     */
    public function upload(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId = Auth::id();
        $file   = $_FILES['import_file'] ?? null;

        if (!$file) {
            $this->flashError('No file selected.');
            $this->redirect('import');
            return;
        }

        $result = $this->pipeline->runFileImport($userId, $file);

        if (!$result['success']) {
            $firstError = $result['errors'][0] ?? 'Unknown error.';
            $this->flashError('Import failed: ' . $firstError);
            $this->redirect('import');
            return;
        }

        $msg = "{$result['imported']} transactions imported";
        if ($result['duplicates'] > 0) $msg .= ", {$result['duplicates']} duplicates skipped";
        if ($result['failed'] > 0)     $msg .= ", {$result['failed']} rows had errors";
        $msg .= '.';

        // Surface row-level errors as a non-blocking info notice (max 5 shown)
        if (!empty($result['errors'])) {
            $shown = array_slice($result['errors'], 0, 5);
            $this->flashInfo('Row issues: ' . implode(' | ', $shown));
        }

        if ($result['imported'] > 0) {
            $this->flashSuccess($msg);
            $this->redirect('import/review');
        } else {
            $this->flashInfo($msg . ' Nothing new to review.');
            $this->redirect('import');
        }
    }

    // ── GET /import/review ────────────────────────────────────

    /**
     * Review pending transactions: assign categories, budgets, skip, or confirm.
     */
    public function review(): void
    {
        Auth::requireLogin();
        $userId = Auth::id();
        $page   = max(1, (int)$this->input('page', 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $transactions = $this->importModel->getPendingReview($userId, $limit, $offset);
        $total        = $this->importModel->countPendingReview($userId);
        $pages        = (int)ceil($total / $limit);
        $categories   = (new CategoryModel())->getForUser($userId);
        $budgets      = (new BudgetModel())->getForUser($userId);
        $current      = (new BudgetModel())->getCurrentBudget($userId);

        // Group by date for display
        $byDate = [];
        foreach ($transactions as $txn) {
            $byDate[$txn['date']][] = $txn;
        }

        $this->view('financial_import.review', compact(
            'byDate', 'total', 'page', 'pages', 'categories', 'budgets', 'current'
        ));
    }

    // ── POST /import/confirm ──────────────────────────────────

    /**
     * Confirm selected pending transactions: promote to expenses or income_entries.
     * Bad rows are reported but do NOT abort the entire import.
     * Returns structured result via flash + redirect.
     */
    public function confirm(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId    = Auth::id();
        $txnIds    = $_POST['txn_ids']     ?? [];
        $mappedAs  = $_POST['mapped_as']   ?? [];
        $catIds    = $_POST['category_id'] ?? [];
        $budgetIds = $_POST['budget_id']   ?? [];
        $budgetId  = $this->input('default_budget_id') ?: null;
        $expModel  = new ExpenseModel();
        $incModel  = new IncomeEntryModel();
        $db        = Database::getInstance();

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        $db->beginTransaction();
        try {
            foreach ($txnIds as $rawId) {
                $txnId  = (int)$rawId;
                $mapped = $mappedAs[$txnId]  ?? 'expense';
                $catId  = $catIds[$txnId]    ?? null;
                $bId    = $budgetIds[$txnId] ?? $budgetId;

                // Re-fetch to confirm ownership and current status
                $txn = $this->importModel->findForUser($txnId, $userId);
                if (!$txn || $txn['status'] !== 'pending_review') {
                    continue;
                }

                if ($mapped === 'skip') {
                    $this->importModel->skip($txnId);
                    $skipped++;
                    continue;
                }

                if ($mapped === 'expense') {
                    if (!$catId) {
                        $errors[] = "Transaction #{$txnId} skipped — no category selected.";
                        $skipped++;
                        continue;
                    }
                    $expModel->addExpense($userId, [
                        'budget_id'    => $bId ?: null,
                        'category_id'  => (int)$catId,
                        'description'  => $txn['merchant'] ?: $txn['name'],
                        'amount'       => $txn['amount'],
                        'expense_date' => $txn['date'],
                        'is_paid'      => 1,
                        'notes'        => ucfirst($txn['source']) . ' import: ' . $txn['name'],
                    ]);
                    $this->importModel->markImported($txnId, (int)$catId, $bId ?: null, 'expense');
                    $imported++;

                } elseif ($mapped === 'income') {
                    $incModel->addEntry($userId, [
                        'budget_id'     => $bId ?: null,
                        'description'   => $txn['merchant'] ?: $txn['name'],
                        'amount'        => $txn['amount'],
                        'received_date' => $txn['date'],
                        'notes'         => ucfirst($txn['source']) . ' import: ' . $txn['name'],
                    ]);
                    $this->importModel->markImported($txnId, null, $bId ?: null, 'income');
                    $imported++;
                }
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log('[FinancialImportController] Confirm error: ' . $e->getMessage());
            $this->flashError('Import failed: ' . $e->getMessage());
            $this->redirect('import/review');
            return;
        }

        $msg = "{$imported} transaction(s) imported";
        if ($skipped > 0) $msg .= ", {$skipped} skipped";
        $msg .= '.';
        $this->flashSuccess($msg);

        if (!empty($errors)) {
            $this->flashInfo(implode(' | ', array_slice($errors, 0, 5)));
        }

        $remaining = $this->importModel->countPendingReview($userId);
        $this->redirect($remaining > 0 ? 'import/review' : 'import');
    }

    // ── POST /import/skip-all ─────────────────────────────────

    /**
     * Skip all pending_review transactions for the current user.
     */
    public function skipAll(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $skipped = $this->importModel->skipAllPending(Auth::id());
        $this->flashInfo("{$skipped} pending transaction(s) skipped.");
        $this->redirect('import');
    }

    // ── GET /import/history ───────────────────────────────────

    /**
     * Show the full import session history with counts and status.
     */
    public function history(): void
    {
        Auth::requireLogin();
        $userId   = Auth::id();
        $sessions = $this->importModel->getSessionsForUser($userId, 50);
        $recent   = $this->importModel->getImported($userId, 100);

        $this->view('financial_import.history', compact('sessions', 'recent'));
    }
}
