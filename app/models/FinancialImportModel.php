<?php
// app/models/FinancialImportModel.php
// Unified model for the financial import system.
// Manages: financial_import_sessions, transactions (staging/history).

class FinancialImportModel extends Model
{
    protected string $table = 'financial_import_sessions';

    // ── Import Sessions ───────────────────────────────────────

    /**
     * Create a new import session and return its ID.
     */
    public function createSession(int $userId, string $source, ?string $filename = null): int
    {
        return $this->insert([
            'user_id'  => $userId,
            'source'   => $source,
            'filename' => $filename,
            'status'   => 'pending',
        ]);
    }

    /**
     * Finalize a session with row-level counts and status.
     */
    public function finalizeSession(int $sessionId, int $imported, int $duplicates, int $failed, string $status = 'complete', ?string $errors = null): void
    {
        $this->query(
            "UPDATE financial_import_sessions
             SET imported=?, duplicates=?, failed=?, status=?, errors=?, completed_at=NOW()
             WHERE id=?",
            [$imported, $duplicates, $failed, $status, $errors, $sessionId]
        );
    }

    /**
     * Update session total_rows after parsing.
     */
    public function setSessionRowCount(int $sessionId, int $total): void
    {
        $this->query(
            "UPDATE financial_import_sessions SET total_rows=?, status='processing' WHERE id=?",
            [$total, $sessionId]
        );
    }

    /**
     * Get recent sessions for a user.
     */
    public function getSessionsForUser(int $userId, int $limit = 20): array
    {
        return $this->query(
            "SELECT * FROM financial_import_sessions
             WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    // ── Transactions ──────────────────────────────────────────

    /**
     * Bulk-insert normalized transactions into the staging table.
     * Returns [inserted_count, duplicate_count].
     */
    public function bulkInsert(array $rows): array
    {
        if (empty($rows)) {
            return [0, 0];
        }

        $db        = Database::getInstance();
        $inserted  = 0;
        $duplicate = 0;

        $stmt = $db->prepare(
            "INSERT IGNORE INTO transactions
             (user_id, import_session_id, account_id, external_id, source,
              amount, `date`, `name`, merchant, category, category_id, pending, mapped_as)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );

        foreach ($rows as $row) {
            $stmt->execute([
                $row['user_id'],
                $row['import_session_id'] ?? null,
                $row['account_id']        ?? null,
                $row['external_id'],
                $row['source'],
                $row['amount'],
                $row['date'],
                $row['name'],
                $row['merchant']          ?? null,
                $row['category']          ?? null,
                $row['category_id']       ?? null,
                $row['pending']           ? 1 : 0,
                $row['mapped_as']         ?? 'expense',
            ]);

            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $duplicate++;
            }
        }

        return [$inserted, $duplicate];
    }

    /**
     * Get transactions awaiting review for a user.
     */
    public function getPendingReview(int $userId, int $limit = 100, int $offset = 0): array
    {
        return $this->query(
            "SELECT t.*, bc.name AS category_name, bc.color, bc.icon,
                    pa.institution_name, pa.account_name, pa.account_mask,
                    fis.source AS session_source
             FROM transactions t
             LEFT JOIN budget_categories bc ON bc.id = t.category_id
             LEFT JOIN plaid_accounts pa ON pa.id = t.account_id
             LEFT JOIN financial_import_sessions fis ON fis.id = t.import_session_id
             WHERE t.user_id = ? AND t.status = 'pending_review' AND t.pending = 0
             ORDER BY t.date DESC, t.id DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll();
    }

    /**
     * Count pending review transactions for a user.
     */
    public function countPendingReview(int $userId): int
    {
        return (int)$this->query(
            "SELECT COUNT(*) FROM transactions WHERE user_id = ? AND status = 'pending_review' AND pending = 0",
            [$userId]
        )->fetchColumn();
    }

    /**
     * Find a single transaction by ID, enforcing user ownership.
     */
    public function findForUser(int $txnId, int $userId): ?array
    {
        $row = $this->query(
            "SELECT * FROM transactions WHERE id = ? AND user_id = ?",
            [$txnId, $userId]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Mark a transaction as skipped.
     */
    public function skip(int $txnId): void
    {
        $this->query(
            "UPDATE transactions SET status='skipped' WHERE id=?",
            [$txnId]
        );
    }

    /**
     * Mark a transaction as imported (promoted to expenses/income).
     */
    public function markImported(int $txnId, ?int $categoryId, ?int $budgetId, string $mappedAs): void
    {
        $this->query(
            "UPDATE transactions
             SET status='imported', category_id=?, budget_id=?, mapped_as=?, updated_at=NOW()
             WHERE id=?",
            [$categoryId, $budgetId, $mappedAs, $txnId]
        );
    }

    /**
     * Skip all pending_review transactions for a user.
     */
    public function skipAllPending(int $userId): int
    {
        $stmt = $this->query(
            "UPDATE transactions SET status='skipped'
             WHERE user_id=? AND status='pending_review'",
            [$userId]
        );
        return $stmt->rowCount();
    }

    /**
     * Get imported transaction history for a user.
     */
    public function getImported(int $userId, int $limit = 100): array
    {
        return $this->query(
            "SELECT t.*, bc.name AS category_name, bc.color,
                    pa.institution_name, pa.account_name
             FROM transactions t
             LEFT JOIN budget_categories bc ON bc.id = t.category_id
             LEFT JOIN plaid_accounts pa ON pa.id = t.account_id
             WHERE t.user_id = ? AND t.status = 'imported'
             ORDER BY t.date DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    /**
     * Check whether an external_id already exists for this user+source
     * (used during deduplication before inserting a batch).
     */
    public function externalIdExists(string $externalId, int $userId, string $source): bool
    {
        $count = (int)$this->query(
            "SELECT COUNT(*) FROM transactions
             WHERE external_id=? AND user_id=? AND source=?",
            [$externalId, $userId, $source]
        )->fetchColumn();
        return $count > 0;
    }

    /**
     * Check for a fuzzy duplicate: same date + amount + name + account_id.
     */
    public function fuzzyDuplicateExists(string $date, float $amount, string $name, int $userId, ?int $accountId): bool
    {
        $count = (int)$this->query(
            "SELECT COUNT(*) FROM transactions
             WHERE `date`=? AND amount=? AND `name`=? AND user_id=?
             AND (account_id=? OR (account_id IS NULL AND ? IS NULL))",
            [$date, $amount, $name, $userId, $accountId, $accountId]
        )->fetchColumn();
        return $count > 0;
    }
}
