<?php
// app/models/PlaidAccountModel.php

class PlaidAccountModel extends Model
{
    protected string $table = 'plaid_accounts';

    // ── Accounts ──────────────────────────────────────────────

    public function getForUser(int $userId): array
    {
        return $this->query(
            "SELECT * FROM plaid_accounts WHERE user_id = ? AND is_active = 1 ORDER BY created_at ASC",
            [$userId]
        )->fetchAll();
    }

    public function saveAccount(int $userId, string $accessToken, array $item, array $account): int
    {
        $encrypted = PlaidService::encryptToken($accessToken);

        // Upsert — same account_id = update token/cursor
        $this->query(
            "INSERT INTO plaid_accounts
             (user_id, item_id, access_token_enc, institution_id, institution_name,
              account_id, account_name, account_mask, account_type, account_subtype, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE
               access_token_enc  = VALUES(access_token_enc),
               item_id           = VALUES(item_id),
               institution_name  = VALUES(institution_name),
               account_name      = VALUES(account_name),
               account_mask      = VALUES(account_mask),
               is_active         = 1,
               error_code        = NULL,
               error_message     = NULL",
            [
                $userId,
                $item['item_id'],
                $encrypted,
                $item['institution_id']   ?? '',
                $item['institution_name'] ?? '',
                $account['account_id'],
                $account['name'],
                $account['mask']    ?? '',
                $account['type']    ?? '',
                $account['subtype'] ?? '',
            ]
        );

        $row = $this->query(
            "SELECT id FROM plaid_accounts WHERE user_id = ? AND account_id = ?",
            [$userId, $account['account_id']]
        )->fetch();

        return (int)($row['id'] ?? 0);
    }

    public function getAccessToken(int $accountId): string
    {
        $row = $this->query(
            "SELECT access_token_enc FROM plaid_accounts WHERE id = ?",
            [$accountId]
        )->fetch();
        if (!$row) throw new RuntimeException("Account {$accountId} not found.");
        return PlaidService::decryptToken($row['access_token_enc']);
    }

    public function updateCursor(int $accountId, string $cursor): void
    {
        // 'cursor' is a MariaDB reserved word — must be backtick-quoted.
        $this->query(
            "UPDATE plaid_accounts SET `cursor` = ? WHERE id = ?",
            [$cursor, $accountId]
        );
    }

    public function updateLastSynced(int $accountId): void
    {
        $this->query(
            "UPDATE plaid_accounts SET last_synced = NOW(), error_code = NULL, error_message = NULL WHERE id = ?",
            [$accountId]
        );
    }

    public function setFirstSynced(int $accountId): void
    {
        $this->query(
            "UPDATE plaid_accounts SET first_synced = NOW() WHERE id = ? AND first_synced IS NULL",
            [$accountId]
        );
    }

    public function setError(int $accountId, string $code, string $message): void
    {
        $this->query(
            "UPDATE plaid_accounts SET error_code = ?, error_message = ? WHERE id = ?",
            [$code, $message, $accountId]
        );
    }

    public function disconnect(int $accountId, int $userId): void
    {
        // Soft delete — keep history
        $this->query(
            "UPDATE plaid_accounts SET is_active = 0 WHERE id = ? AND user_id = ?",
            [$accountId, $userId]
        );
    }

    // ── Transactions ──────────────────────────────────────────

    public function upsertTransaction(array $data): bool
    {
        // ON DUPLICATE KEY on plaid_txn_id — silently skip duplicates
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO plaid_transactions
             (user_id, plaid_account_id, plaid_txn_id, amount, `date`, `name`,
              merchant_name, plaid_category, plaid_category_id,
              payment_channel, pending, category_id, mapped_as, budget_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        return $stmt->execute([
            $data['user_id'],
            $data['plaid_account_id'],
            $data['plaid_txn_id'],
            $data['amount'],
            $data['date'],
            $data['name'],
            $data['merchant_name']    ?? null,
            $data['plaid_category']   ?? null,
            $data['plaid_category_id']?? null,
            $data['payment_channel']  ?? null,
            $data['pending']          ? 1 : 0,
            $data['category_id'],
            $data['mapped_as'],
            $data['budget_id']        ?? null,
        ]);
    }

    public function removeTransaction(string $plaidTxnId): void
    {
        $this->query(
            "UPDATE plaid_transactions SET status = 'skipped' WHERE plaid_txn_id = ?",
            [$plaidTxnId]
        );
    }

    public function getPendingReview(int $userId, int $limit = 100, int $offset = 0): array
    {
        return $this->query(
            "SELECT t.*, pa.institution_name, pa.account_name, pa.account_mask,
                    bc.name AS category_name, bc.color, bc.icon
             FROM plaid_transactions t
             JOIN plaid_accounts pa ON pa.id = t.plaid_account_id
             LEFT JOIN budget_categories bc ON bc.id = t.category_id
             WHERE t.user_id = ? AND t.status = 'pending_review' AND t.pending = 0
             ORDER BY t.date DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        )->fetchAll();
    }

    public function countPendingReview(int $userId): int
    {
        return (int)$this->query(
            "SELECT COUNT(*) FROM plaid_transactions WHERE user_id = ? AND status = 'pending_review' AND pending = 0",
            [$userId]
        )->fetchColumn();
    }

    public function getImported(int $userId, int $limit = 50): array
    {
        return $this->query(
            "SELECT t.*, pa.institution_name, pa.account_name,
                    bc.name AS category_name, bc.color
             FROM plaid_transactions t
             JOIN plaid_accounts pa ON pa.id = t.plaid_account_id
             LEFT JOIN budget_categories bc ON bc.id = t.category_id
             WHERE t.user_id = ? AND t.status = 'imported'
             ORDER BY t.date DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    // ── Sync log ──────────────────────────────────────────────

    public function logSync(int $userId, ?int $accountId, string $trigger, int $added, int $modified, int $removed, ?string $error, int $durationMs): void
    {
        $this->query(
            "INSERT INTO plaid_sync_log (user_id, account_id, trigger_type, added, modified, removed, error, duration_ms)
             VALUES (?,?,?,?,?,?,?,?)",
            [$userId, $accountId, $trigger, $added, $modified, $removed, $error, $durationMs]
        );
    }

    public function getRecentLogs(int $userId, int $limit = 10): array
    {
        return $this->query(
            "SELECT l.*, pa.institution_name, pa.account_name
             FROM plaid_sync_log l
             LEFT JOIN plaid_accounts pa ON pa.id = l.account_id
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }
}