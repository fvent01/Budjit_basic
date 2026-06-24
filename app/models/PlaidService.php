<?php
// app/models/PlaidService.php
// Handles all communication with the Plaid API

class PlaidService
{
    // ── Category mapping: Plaid → Budjit category IDs ─────
    // Plaid category strings: https://plaid.com/documents/transactions-personal-finance-category-taxonomy.pdf
    private static array $categoryMap = [
        // Food & Drink → Food (3)
        'Food and Drink'                       => 3,
        'Restaurants'                          => 3,
        'Fast Food'                            => 3,
        'Coffee Shop'                          => 3,
        'Groceries'                            => 3,
        'Food And Drink'                       => 3,

        // Travel / Gas → Fuel (4)
        'Gas Stations'                         => 4,
        'Gas'                                  => 4,
        'Fuel'                                 => 4,
        'Auto'                                 => 4,

        // Housing → Housing (1)
        'Rent'                                 => 1,
        'Mortgage'                             => 1,
        'Real Estate'                          => 1,
        'Home'                                 => 1,

        // Utilities → Utilities (2)
        'Utilities'                            => 2,
        'Electric'                             => 2,
        'Internet Services'                    => 2,
        'Telecommunication Services'           => 2,
        'Phone'                                => 2,
        'Water'                                => 2,

        // Entertainment → Entertainment (6)
        'Entertainment'                        => 6,
        'Recreation'                           => 6,
        'Arts and Entertainment'               => 6,
        'Subscription'                         => 6,
        'Service'                              => 6,
        'Streaming'                            => 6,

        // Kids → Kids (7)
        'Kids'                                 => 7,
        'Child Care'                           => 7,
        'Education'                            => 7,
        'Schools'                              => 7,

        // Healthcare → Healthcare (9)
        'Medical'                              => 9,
        'Healthcare'                           => 9,
        'Pharmacies'                           => 9,
        'Dentists'                             => 9,
        'Hospitals'                            => 9,

        // Transfer / Income → Income
        'Payroll'                              => null, // handled as income
        'Deposit'                              => null,
        'Credit'                               => null,

        // Savings → Savings (5)
        'Transfer'                             => 5,
        'Savings'                              => 5,
        'Investment'                           => 5,
    ];

    // ── Encryption helpers ────────────────────────────────────

    public static function encryptToken(string $token): string
    {
        $key   = base64_decode(PLAID_ENCRYPTION_KEY);
        $iv    = random_bytes(16);
        $cipher= openssl_encrypt($token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decryptToken(string $encrypted): string
    {
        $key  = base64_decode(PLAID_ENCRYPTION_KEY);
        $data = base64_decode($encrypted);
        $iv   = substr($data, 0, 16);
        $cipher = substr($data, 16);
        return openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    // ── Core Plaid HTTP client ────────────────────────────────

    public static function call(string $endpoint, array $payload): array
    {
        if (!defined('PLAID_CLIENT_ID') || !defined('PLAID_SECRET')) {
            return ['error' => ['error_message' => 'Plaid credentials not configured in config.php']];
        }

        $payload['client_id'] = PLAID_CLIENT_ID;
        $payload['secret']    = PLAID_SECRET;

        $url = PLAID_BASE_URL . $endpoint;
        $ch  = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Plaid-Version: 2020-09-14',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        unset($ch);

        if ($curlErr) {
            return ['error' => ['error_message' => 'cURL error: ' . $curlErr]];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['error' => ['error_message' => "Invalid JSON response (HTTP {$httpCode})"]];
        }

        return $data;
    }

    // ── Link token (step 1 of Link flow) ─────────────────────

    public static function createLinkToken(int $userId, ?string $accessToken = null): array
    {
        $payload = [
            'user'          => ['client_user_id' => (string)$userId],
            'client_name'   => defined('APP_NAME') ? APP_NAME : 'Budjit',
            'products'      => ['transactions'],
            'country_codes' => ['US'],
            'language'      => 'en',
        ];

        // If re-authenticating an existing item
        if ($accessToken) {
            $payload['access_token'] = $accessToken;
            unset($payload['products']);
        }

        return self::call('/link/token/create', $payload);
    }

    // ── Exchange public token (step 2 of Link flow) ───────────

    public static function exchangePublicToken(string $publicToken): array
    {
        return self::call('/item/public_token/exchange', [
            'public_token' => $publicToken,
        ]);
    }

    // ── Get accounts for an item ──────────────────────────────

    public static function getAccounts(string $accessToken): array
    {
        return self::call('/accounts/get', [
            'access_token' => $accessToken,
        ]);
    }

    // ── Get institution details ───────────────────────────────

    public static function getInstitution(string $institutionId): array
    {
        return self::call('/institutions/get_by_id', [
            'institution_id' => $institutionId,
            'country_codes'  => ['US'],
        ]);
    }

    // ── Transactions sync (cursor-based, most efficient) ──────

    public static function syncTransactions(string $accessToken, ?string $cursor = null): array
    {
        $payload = ['access_token' => $accessToken];
        if ($cursor) $payload['cursor'] = $cursor;

        return self::call('/transactions/sync', $payload);
    }

    // ── Historical transactions (first sync only) ─────────────

    public static function getTransactions(string $accessToken, string $startDate, string $endDate, int $offset = 0): array
    {
        return self::call('/transactions/get', [
            'access_token' => $accessToken,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'options'      => [
                'count'  => 500,
                'offset' => $offset,
            ],
        ]);
    }

    // ── Remove item (disconnect) ──────────────────────────────

    public static function removeItem(string $accessToken): array
    {
        return self::call('/item/remove', [
            'access_token' => $accessToken,
        ]);
    }

    // ── Category auto-mapping ─────────────────────────────────

    public static function mapCategory(array $plaidTxn): ?int
    {
        // Credits / income types → mark as income, no category
        $name = strtolower($plaidTxn['name'] ?? '');
        if (
            str_contains($name, 'payroll') ||
            str_contains($name, 'direct dep') ||
            str_contains($name, 'adp') ||
            ($plaidTxn['amount'] ?? 0) < 0  // negative = credit in Plaid
        ) {
            return null; // will be mapped_as = income
        }

        // Try Plaid's personal_finance_category first (newer API)
        if (!empty($plaidTxn['personal_finance_category']['primary'])) {
            $pfcPrimary = $plaidTxn['personal_finance_category']['primary'];
            foreach (self::$categoryMap as $key => $catId) {
                if (stripos($pfcPrimary, $key) !== false) return $catId;
            }
        }

        // Fall back to legacy categories array
        $cats = $plaidTxn['category'] ?? [];
        foreach ($cats as $cat) {
            if (isset(self::$categoryMap[$cat])) {
                return self::$categoryMap[$cat];
            }
            // Partial match
            foreach (self::$categoryMap as $key => $catId) {
                if (stripos($cat, $key) !== false) return $catId;
            }
        }

        // Keyword match on merchant name
        $merchant = strtolower($plaidTxn['merchant_name'] ?? $plaidTxn['name'] ?? '');
        $keywords = [
            'walmart|kroger|aldi|publix|whole foods|trader joe|food lion' => 3,
            'mcdonald|chick-fil|burger|pizza|domino|subway|taco|kfc'     => 3,
            'netflix|spotify|hulu|disney|amazon prime|youtube|apple'     => 6,
            'shell|bp|chevron|exxon|pilot|wawa|murphy|marathon'         => 4,
            'cvs|walgreens|rite aid|pharmacy|hospital|clinic|dentist'    => 9,
            'at&t|verizon|t-mobile|comcast|xfinity|spectrum|cox'        => 2,
            'daycare|day care|preschool|childcare|kids'                  => 7,
        ];

        foreach ($keywords as $pattern => $catId) {
            if (preg_match('/' . $pattern . '/i', $merchant)) return $catId;
        }

        return 10; // Other
    }

    public static function mapAs(array $plaidTxn): string
    {
        // Negative amount in Plaid = money coming in (credit)
        if (($plaidTxn['amount'] ?? 0) < 0) return 'income';
        $name = strtolower($plaidTxn['name'] ?? '');
        if (str_contains($name, 'payroll') || str_contains($name, 'direct dep')) return 'income';
        return 'expense';
    }

    // ── Transfer ignore list ──────────────────────────────────

    /**
     * Transaction names (case-insensitive, substring match) that should be
     * silently skipped during Plaid import — internal transfers that add no
     * budgeting value.
     */
    private static array $transferIgnorePatterns = [
        'transfer to credit builder',
        'transfer to chime savings account',
        'transfer from checking account',
        'transfer from savings account',
        'transfer to checking account',
        'transfer to savings account',
        'transfer from checking',
        'transfer from savings',
        'transfer to checking',
        'transfer to savings',
        'payment to credit builder',

    ];

    /**
     * Returns true if the transaction name matches a known internal transfer
     * that should be ignored on import.
     */
    public static function shouldIgnore(array $plaidTxn): bool
    {
        $name = mb_strtolower(trim($plaidTxn['name'] ?? ''));
        foreach (self::$transferIgnorePatterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }
        return false;
    }
}