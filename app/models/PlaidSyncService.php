<?php
// app/models/PlaidSyncService.php
// Orchestrates Plaid transaction syncing via the unified FinancialImportPipeline.
// All transactions from Plaid go through the shared pipeline — no duplicate paths.

class PlaidSyncService
{
    private PlaidAccountModel       $accountModel;
    private FinancialImportPipeline $pipeline;
    private FinancialImportModel    $importModel;

    public function __construct()
    {
        $this->accountModel = new PlaidAccountModel();
        $this->pipeline     = new FinancialImportPipeline();
        $this->importModel  = new FinancialImportModel();
    }

    /**
     * Sync all active accounts for a user.
     * Returns total counts across all accounts.
     *
     * @param  int    $userId
     * @param  string $trigger  'login' | 'manual'
     * @return array  {added, modified, removed, errors[]}
     */
    public function syncAllForUser(int $userId, string $trigger = 'manual'): array
    {
        $accounts = $this->accountModel->getForUser($userId);
        $totals   = ['added' => 0, 'modified' => 0, 'removed' => 0, 'errors' => []];

        foreach ($accounts as $account) {
            try {
                $result = $this->syncAccount((int)$account['id'], $userId, $trigger);
                $totals['added']    += $result['added'];
                $totals['modified'] += $result['modified'];
                $totals['removed']  += $result['removed'];
            } catch (Exception $e) {
                $errMsg = $account['institution_name'] . ': ' . $e->getMessage();
                $totals['errors'][] = $errMsg;
                $this->accountModel->setError($account['id'], 'SYNC_ERROR', $e->getMessage());
                error_log("[PlaidSyncService] Account {$account['id']} sync error: " . $e->getMessage());
            }
        }

        return $totals;
    }

    /**
     * Sync a single Plaid account using the cursor-based /transactions/sync endpoint.
     * On first sync (no cursor), falls back to /transactions/get for the initial window.
     *
     * @param  int    $accountId  plaid_accounts.id
     * @param  int    $userId
     * @param  string $trigger
     * @return array  {added, modified, removed}
     * @throws RuntimeException on Plaid API error or access denial
     */
    public function syncAccount(int $accountId, int $userId, string $trigger = 'manual'): array
    {
        $start   = microtime(true);
        $account = $this->accountModel->find($accountId);

        if (!$account || (int)$account['user_id'] !== $userId) {
            throw new RuntimeException("Account not found or access denied.");
        }

        $accessToken = $this->accountModel->getAccessToken($accountId);
        $cursor      = $account['cursor'] ?: null;
        $isFirstSync = empty($account['first_synced']);

        // Create a session to log this sync event in the unified sessions table
        $sessionId = $this->importModel->createSession($userId, 'plaid', null);

        $added    = 0;
        $modified = 0;
        $removed  = 0;
        $error    = null;

        try {
            if ($isFirstSync) {
                [$added] = $this->initialSync($accountId, $userId, $accessToken, $sessionId);
                $this->accountModel->setFirstSynced($accountId);
            } else {
                [$added, $modified, $removed, $cursor] = $this->incrementalSync(
                    $accountId, $userId, $accessToken, $cursor, $sessionId
                );
                $this->accountModel->updateCursor($accountId, $cursor);
            }

            $this->accountModel->updateLastSynced($accountId);
            $this->importModel->finalizeSession($sessionId, $added, 0, 0, 'complete');

        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->importModel->finalizeSession($sessionId, $added, 0, 1, 'failed', $error);
            throw $e;
        } finally {
            $durationMs = (int)((microtime(true) - $start) * 1000);
            $this->accountModel->logSync($userId, $accountId, $trigger, $added, $modified, $removed, $error, $durationMs);
        }

        return ['added' => $added, 'modified' => $modified, 'removed' => $removed];
    }

    // ── Initial sync — /transactions/get with pagination ─────

    /**
     * Pull up to PLAID_INITIAL_DAYS days of history on first connection.
     * All transactions go through the shared pipeline.
     *
     * @return array [added]
     */
    private function initialSync(int $accountId, int $userId, string $accessToken, int $sessionId): array
    {
        $startDate = date('Y-m-d', strtotime('-' . PLAID_INITIAL_DAYS . ' days'));
        $endDate   = date('Y-m-d');
        $offset    = 0;
        $added     = 0;

        do {
            $response = PlaidService::getTransactions($accessToken, $startDate, $endDate, $offset);

            if (isset($response['error'])) {
                $this->handlePlaidError($response['error'], $accountId);
            }

            $transactions = $response['transactions'] ?? [];
            $total        = $response['total_transactions'] ?? 0;

            if (!empty($transactions)) {
                [$batchAdded] = $this->pipeline->runPlaidBatch($transactions, $accountId, $userId, $sessionId);
                $added += $batchAdded;
            }

            $offset += count($transactions);
        } while ($offset < $total && count($transactions) > 0);

        return [$added];
    }

    // ── Incremental sync — /transactions/sync cursor ──────────

    /**
     * Pull only new/changed/removed transactions since last cursor.
     *
     * @return array [added, modified, removed, new_cursor]
     */
    private function incrementalSync(int $accountId, int $userId, string $accessToken, ?string $cursor, int $sessionId): array
    {
        $added    = 0;
        $modified = 0;
        $removed  = 0;
        $hasMore  = true;

        while ($hasMore) {
            $response = PlaidService::syncTransactions($accessToken, $cursor);

            if (isset($response['error'])) {
                $this->handlePlaidError($response['error'], $accountId);
            }

            // Added transactions → pipeline
            $addedTxns = $response['added'] ?? [];
            if (!empty($addedTxns)) {
                [$batchAdded] = $this->pipeline->runPlaidBatch($addedTxns, $accountId, $userId, $sessionId);
                $added += $batchAdded;
            }

            // Modified — update pending_review rows in place
            foreach ($response['modified'] ?? [] as $txn) {
                $this->applyModified($txn, $userId);
                $modified++;
            }

            // Removed — soft-delete: mark as skipped so history is preserved
            foreach ($response['removed'] ?? [] as $txn) {
                $this->applyRemoved($txn['transaction_id'] ?? '', $userId);
                $removed++;
            }

            $cursor  = $response['next_cursor'] ?? $cursor;
            $hasMore = !empty($response['has_more']);
        }

        return [$added, $modified, $removed, $cursor];
    }

    // ── Modified transaction handler ──────────────────────────

    /**
     * Update a modified Plaid transaction that is still pending_review.
     * Imported rows are NOT overwritten to avoid corrupting confirmed data.
     */
    private function applyModified(array $txn, int $userId): void
    {
        $amount = abs((float)($txn['amount'] ?? 0));
        Database::getInstance()->prepare(
            "UPDATE transactions
             SET amount=?, `date`=?, `name`=?, merchant=?, pending=?, updated_at=NOW()
             WHERE external_id=? AND user_id=? AND `status`='pending_review'"
        )->execute([
            $amount,
            $txn['date'],
            mb_substr(trim($txn['name'] ?? ''), 0, 255),
            mb_substr(trim($txn['merchant_name'] ?? ''), 0, 255) ?: null,
            !empty($txn['pending']) ? 1 : 0,
            'plaid:' . $txn['transaction_id'],
            $userId,
        ]);
    }

    // ── Removed transaction handler ───────────────────────────

    /**
     * Soft-delete a removed Plaid transaction.
     * Only affects pending_review rows — confirmed imports are preserved.
     */
    private function applyRemoved(string $plaidTxnId, int $userId): void
    {
        Database::getInstance()->prepare(
            "UPDATE transactions SET status='skipped', updated_at=NOW()
             WHERE external_id=? AND user_id=? AND status='pending_review'"
        )->execute(['plaid:' . $plaidTxnId, $userId]);
    }

    // ── Error handling ────────────────────────────────────────

    /**
     * Translate Plaid error responses into exceptions.
     * Marks account as needing re-auth when token has expired.
     *
     * @throws RuntimeException
     */
    private function handlePlaidError(array $error, int $accountId): void
    {
        $code    = $error['error_code']    ?? 'UNKNOWN';
        $message = $error['error_message'] ?? 'Unknown Plaid error';

        // Token expired / item needs re-auth
        if (in_array($code, ['ITEM_LOGIN_REQUIRED', 'INVALID_ACCESS_TOKEN', 'ITEM_NOT_FOUND'], true)) {
            $this->accountModel->setError($accountId, $code, $message);
        }

        throw new RuntimeException("[Plaid {$code}] {$message}");
    }
}
