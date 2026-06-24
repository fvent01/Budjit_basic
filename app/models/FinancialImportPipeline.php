<?php
// app/models/FinancialImportPipeline.php
// Shared import pipeline used by ALL sources.
//
// Flow: Import Source → parse() → normalize() → validate() → deduplicate() → save()
//
// All sources MUST produce a normalized Transaction before reaching the DB.
// No source-specific logic may bypass this pipeline.

class FinancialImportPipeline
{
    private FinancialImportModel $model;

    /** Maximum allowed file size for uploads (10 MB). */
    const MAX_FILE_BYTES = 10 * 1024 * 1024;

    /** Allowed MIME types by extension. */
    const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls', 'xlsm'];

    /** Flexible column header synonyms — order matters (first match wins). */
    const HEADER_SYNONYMS = [
        'date'        => ['date', 'transaction date', 'trans date', 'posted date', 'week date', 'posting date', 'value date'],
        'description' => ['description', 'memo', 'payee', 'name', 'details', 'narrative', 'particulars', 'transaction'],
        'amount'      => ['amount', 'debit', 'credit', 'transaction amount', 'value', 'sum', 'total'],
        'type'        => ['type', 'transaction type', 'credit/debit', 'debit/credit', 'kind', 'dr/cr'],
        'merchant'    => ['merchant', 'merchant name', 'vendor', 'store'],
        'category'    => ['category', 'classification', 'expense type'],
    ];

    public function __construct()
    {
        $this->model = new FinancialImportModel();
    }

    // ── Public entry points ───────────────────────────────────

    /**
     * Run a full CSV/Excel import for a user.
     * Returns a structured result array.
     *
     * @param  int    $userId
     * @param  array  $file       $_FILES entry
     * @return array  {success, session_id, imported, duplicates, failed, errors[]}
     */
    public function runFileImport(int $userId, array $file): array
    {
        $errors = [];

        // ── 1. Validate file ──────────────────────────────────
        $fileError = $this->validateFile($file);
        if ($fileError) {
            return $this->result(false, 0, 0, 0, 0, [$fileError]);
        }

        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $source    = ($ext === 'csv') ? 'csv' : 'excel';
        $sessionId = $this->model->createSession($userId, $source, $file['name']);

        // ── 2. Store file ─────────────────────────────────────
        $storedPath = $this->storeUploadedFile($file, $userId, $ext);
        if (!$storedPath) {
            $this->model->finalizeSession($sessionId, 0, 0, 1, 'failed', 'Could not save uploaded file.');
            return $this->result(false, $sessionId, 0, 0, 1, ['Could not save uploaded file.']);
        }

        // ── 3. Parse ──────────────────────────────────────────
        try {
            $rawRows = ($source === 'csv')
                ? $this->parseCsv($storedPath)
                : $this->parseExcel($storedPath);
        } catch (Exception $e) {
            error_log("[FinancialImportPipeline] Parse error: " . $e->getMessage());
            $this->model->finalizeSession($sessionId, 0, 0, 1, 'failed', $e->getMessage());
            return $this->result(false, $sessionId, 0, 0, 1, ['Parse failed: ' . $e->getMessage()]);
        }

        if (empty($rawRows)) {
            $this->model->finalizeSession($sessionId, 0, 0, 0, 'failed', 'No data rows found.');
            return $this->result(false, $sessionId, 0, 0, 0, ['No data rows found in the uploaded file.']);
        }

        $this->model->setSessionRowCount($sessionId, count($rawRows));

        // ── 4. Normalize ──────────────────────────────────────
        $normalized = $this->normalizeRows($rawRows, $userId, $sessionId, $source, null, $errors);

        // ── 5. Validate ───────────────────────────────────────
        [$valid, $invalid] = $this->validateRows($normalized, $errors);

        // ── 6. Deduplicate ────────────────────────────────────
        [$unique, $duplicates] = $this->deduplicateRows($valid, $userId);

        // ── 7. Save ───────────────────────────────────────────
        [$inserted, $dbDuplicates] = $this->model->bulkInsert($unique);
        $totalDuplicates = count($duplicates) + $dbDuplicates;

        $errorSummary = empty($errors) ? null : implode(' | ', array_slice($errors, 0, 20));
        $this->model->finalizeSession($sessionId, $inserted, $totalDuplicates, count($invalid), 'complete', $errorSummary);

        error_log("[FinancialImportPipeline] Session {$sessionId}: inserted={$inserted}, duplicates={$totalDuplicates}, invalid=" . count($invalid));

        return $this->result(true, $sessionId, $inserted, $totalDuplicates, count($invalid), $errors);
    }

    /**
     * Normalize a batch of Plaid transactions into the unified schema and save.
     * Called by PlaidSyncService for both initial and incremental syncs.
     *
     * @param  array  $plaidTxns   Raw Plaid transaction objects
     * @param  int    $accountId   plaid_accounts.id
     * @param  int    $userId
     * @param  int    $sessionId   financial_import_sessions.id
     * @return array  [inserted, duplicates]
     */
    public function runPlaidBatch(array $plaidTxns, int $accountId, int $userId, int $sessionId): array
    {
        if (empty($plaidTxns)) {
            return [0, 0];
        }

        $normalized = [];
        foreach ($plaidTxns as $txn) {
            if (PlaidService::shouldIgnore($txn)) {
                continue; // silently drop internal transfers
            }
            $normalized[] = $this->normalizePlaidTransaction($txn, $accountId, $userId, $sessionId);
        }

        [$unique, $duplicates] = $this->deduplicateRows($normalized, $userId);
        [$inserted, $dbDuplicates] = $this->model->bulkInsert($unique);

        return [$inserted, count($duplicates) + $dbDuplicates];
    }

    // ── File validation ───────────────────────────────────────

    /**
     * Validate an uploaded file: presence, error code, size, extension, MIME.
     *
     * @return string|null  Error message, or null on success.
     */
    public function validateFile(array $file): ?string
    {
        if (empty($file) || !isset($file['tmp_name'])) {
            return 'No file was uploaded.';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            return $msgs[$file['error']] ?? 'Upload failed (code ' . $file['error'] . ').';
        }

        if ($file['size'] > self::MAX_FILE_BYTES) {
            return 'File exceeds the 10 MB size limit.';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return 'Unsupported file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS) . '.';
        }

        // Verify the tmp file is a real uploaded file (prevents path traversal)
        if (!is_uploaded_file($file['tmp_name'])) {
            return 'Invalid upload source.';
        }

        // MIME check for CSV
        if ($ext === 'csv') {
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['text/plain', 'text/csv', 'application/csv', 'text/comma-separated-values'];
            if ($mime && !in_array($mime, $allowed, true) && !str_starts_with($mime, 'text/')) {
                return 'File MIME type does not match CSV.';
            }
        }

        return null;
    }

    // ── Parse ─────────────────────────────────────────────────

    /**
     * Parse a CSV file into raw row arrays.
     * Skips empty rows; never throws for row-level issues.
     */
    public function parseCsv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("CSV file not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open CSV file.");
        }

        // Detect and skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [];
        }

        $header  = array_map(fn($h) => mb_strtolower(trim($h)), $header);
        $colMap  = $this->detectColumns($header);
        $rows    = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn($v) => trim($v) !== '')) < 2) {
                continue; // skip effectively empty rows
            }
            $rows[] = $this->extractRow($row, $colMap);
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Parse an Excel (.xlsx/.xls) file into raw row arrays.
     * Uses PhpSpreadsheet if available; falls back to native ZIP/XML reader.
     */
    public function parseExcel(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Excel file not found: {$filePath}");
        }

        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return $this->parseExcelWithLibrary($filePath);
        }

        return $this->parseXlsxNative($filePath);
    }

    // ── Normalize ─────────────────────────────────────────────

    /**
     * Convert raw parsed rows into the unified Transaction schema.
     * Row-level errors are appended to $errors; bad rows are skipped, not thrown.
     *
     * @param  array  $rawRows   Output of parseCsv() or parseExcel()
     * @param  int    $userId
     * @param  int    $sessionId
     * @param  string $source    'csv' | 'excel'
     * @param  int|null $accountId
     * @param  array  &$errors   Accumulated error messages (passed by ref)
     * @return array  Normalized transaction rows
     */
    public function normalizeRows(array $rawRows, int $userId, int $sessionId, string $source, ?int $accountId, array &$errors): array
    {
        $normalized = [];

        foreach ($rawRows as $i => $row) {
            $lineNum = $i + 2; // 1-indexed + header row

            // Date
            $dateStr = trim($row['date'] ?? '');
            if ($dateStr === '') {
                $errors[] = "Row {$lineNum}: missing date — skipped.";
                continue;
            }
            $ts = strtotime($dateStr);
            if (!$ts) {
                $errors[] = "Row {$lineNum}: unrecognized date '{$dateStr}' — skipped.";
                continue;
            }
            $date = date('Y-m-d', $ts);

            // Amount
            $amtRaw = trim($row['amount'] ?? '');
            if ($amtRaw === '') {
                $errors[] = "Row {$lineNum}: missing amount — skipped.";
                continue;
            }
            $amount = (float)str_replace(['$', ',', ' ', "\xC2\xA3", "\xE2\x82\xAC"], '', $amtRaw);

            // Name / description
            $name = trim($row['description'] ?? '');
            if ($name === '') {
                $name = 'Imported transaction';
            }
            // Sanitize: strip tags, limit length
            $name = htmlspecialchars(strip_tags(mb_substr($name, 0, 255)), ENT_QUOTES, 'UTF-8');

            // Determine mapped_as (income vs expense)
            $type     = strtolower(trim($row['type'] ?? ''));
            $mappedAs = $this->determineMappedAs($amount, $type);
            $amount   = abs($amount); // always store positive

            // Merchant
            $merchant = mb_substr(trim($row['merchant'] ?? ''), 0, 255) ?: null;

            // Category
            $rawCategory = trim($row['category'] ?? '') ?: null;
            $categoryId  = $rawCategory ? $this->mapCategoryByName($rawCategory) : $this->suggestCategory($name);

            // External ID: hash for CSV/Excel (no stable ID from source)
            $externalId = 'file:' . hash('sha256', $source . '|' . $userId . '|' . $date . '|' . $name . '|' . $amount);

            $normalized[] = [
                'user_id'           => $userId,
                'import_session_id' => $sessionId,
                'account_id'        => $accountId,
                'external_id'       => $externalId,
                'source'            => $source,
                'amount'            => round($amount, 2),
                'date'              => $date,
                'name'              => $name,
                'merchant'          => $merchant,
                'category'          => $rawCategory,
                'category_id'       => $categoryId,
                'pending'           => false,
                'mapped_as'         => $mappedAs,
            ];
        }

        return $normalized;
    }

    /**
     * Normalize a single Plaid transaction into the unified schema.
     */
    public function normalizePlaidTransaction(array $txn, int $accountId, int $userId, int $sessionId): array
    {
        // Plaid: positive amount = money out (debit), negative = money in (credit)
        $amount   = abs((float)($txn['amount'] ?? 0));
        $mappedAs = PlaidService::mapAs($txn);

        $plaidCategory = '';
        if (!empty($txn['personal_finance_category']['primary'])) {
            $plaidCategory = $txn['personal_finance_category']['primary'];
        } elseif (!empty($txn['category']) && is_array($txn['category'])) {
            $plaidCategory = implode(' > ', $txn['category']);
        }

        return [
            'user_id'           => $userId,
            'import_session_id' => $sessionId,
            'account_id'        => $accountId,
            'external_id'       => 'plaid:' . $txn['transaction_id'],
            'source'            => 'plaid',
            'amount'            => round($amount, 2),
            'date'              => $txn['date'],
            'name'              => mb_substr(trim($txn['name'] ?? 'Unknown'), 0, 255),
            'merchant'          => mb_substr(trim($txn['merchant_name'] ?? ''), 0, 255) ?: null,
            'category'          => $plaidCategory ?: null,
            'category_id'       => PlaidService::mapCategory($txn),
            'pending'           => !empty($txn['pending']),
            'mapped_as'         => $mappedAs,
        ];
    }

    // ── Validate ──────────────────────────────────────────────

    /**
     * Validate a batch of normalized rows.
     * Returns [valid_rows[], error_messages[]].
     * Invalid rows are excluded but do NOT abort the import.
     */
    public function validateRows(array $rows, array &$errors): array
    {
        $valid   = [];
        $invalid = [];

        foreach ($rows as $i => $row) {
            $rowErrors = [];

            if (empty($row['user_id'])) {
                $rowErrors[] = 'missing user_id';
            }
            if (empty($row['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
                $rowErrors[] = 'invalid date format';
            }
            if (!isset($row['amount']) || !is_numeric($row['amount']) || $row['amount'] < 0) {
                $rowErrors[] = 'invalid amount';
            }
            if (empty($row['name'])) {
                $rowErrors[] = 'missing name/description';
            }
            if (!in_array($row['source'] ?? '', ['plaid', 'csv', 'excel'], true)) {
                $rowErrors[] = 'invalid source';
            }
            if (!in_array($row['mapped_as'] ?? '', ['expense', 'income'], true)) {
                $rowErrors[] = 'invalid mapped_as';
            }

            if (!empty($rowErrors)) {
                $lineNum = $i + 2;
                $errors[] = "Row {$lineNum}: " . implode(', ', $rowErrors) . ' — skipped.';
                $invalid[] = $row;
            } else {
                $valid[] = $row;
            }
        }

        return [$valid, $invalid];
    }

    // ── Deduplicate ───────────────────────────────────────────

    /**
     * Remove duplicates from a normalized batch BEFORE inserting.
     *
     * A row is a duplicate if:
     *   (a) external_id already exists for this user+source in transactions, OR
     *   (b) date + amount + name + account_id match an existing row.
     *
     * Also deduplicates within the current batch itself.
     *
     * Returns [unique_rows[], skipped_rows[]].
     */
    public function deduplicateRows(array $rows, int $userId): array
    {
        $unique   = [];
        $skipped  = [];
        $seenKeys = []; // within-batch dedup

        foreach ($rows as $row) {
            // Within-batch dedup by external_id
            $batchKey = $row['external_id'];
            if (isset($seenKeys[$batchKey])) {
                $skipped[] = $row;
                continue;
            }
            $seenKeys[$batchKey] = true;

            // DB-level dedup: external_id match
            if ($this->model->externalIdExists($row['external_id'], $userId, $row['source'])) {
                $skipped[] = $row;
                continue;
            }

            // DB-level fuzzy dedup: date + amount + name + account_id
            // Only for file imports (Plaid handles this via external_id / INSERT IGNORE)
            if ($row['source'] !== 'plaid') {
                if ($this->model->fuzzyDuplicateExists(
                    $row['date'],
                    $row['amount'],
                    $row['name'],
                    $userId,
                    $row['account_id']
                )) {
                    $skipped[] = $row;
                    continue;
                }
            }

            $unique[] = $row;
        }

        return [$unique, $skipped];
    }

    // ── Private helpers ───────────────────────────────────────

    /**
     * Store an uploaded file securely under STORAGE_PATH/uploads/imports/.
     * Returns the stored path, or null on failure.
     */
    private function storeUploadedFile(array $file, int $userId, string $ext): ?string
    {
        $dir = STORAGE_PATH . '/uploads/imports/';
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return null;
        }

        // Rename to prevent guessing; strip original name
        $safeName = 'import_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = $dir . $safeName;

        return move_uploaded_file($file['tmp_name'], $dest) ? $dest : null;
    }

    /**
     * Build a column-index map from flexible header synonyms.
     * Returns ['date' => 0, 'description' => 2, ...].
     */
    private function detectColumns(array $headers): array
    {
        $map = [];
        foreach (self::HEADER_SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $syn) {
                $idx = array_search(mb_strtolower($syn), $headers, true);
                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Extract a raw row using the detected column map.
     */
    private function extractRow(array $row, array $colMap): array
    {
        return [
            'date'        => isset($colMap['date'])        ? ($row[$colMap['date']]        ?? '') : '',
            'description' => isset($colMap['description']) ? ($row[$colMap['description']] ?? '') : '',
            'amount'      => isset($colMap['amount'])      ? ($row[$colMap['amount']]      ?? '') : '',
            'type'        => isset($colMap['type'])        ? ($row[$colMap['type']]        ?? '') : '',
            'merchant'    => isset($colMap['merchant'])    ? ($row[$colMap['merchant']]    ?? '') : '',
            'category'    => isset($colMap['category'])    ? ($row[$colMap['category']]    ?? '') : '',
        ];
    }

    /**
     * Determine whether a transaction is income or expense.
     */
    private function determineMappedAs(float $amount, string $type): string
    {
        if ($amount < 0) {
            return 'expense'; // negative = debit = money out
        }
        if (str_contains($type, 'credit') || str_contains($type, 'deposit') || str_contains($type, 'cr')) {
            return 'income';
        }
        if (str_contains($type, 'debit') || str_contains($type, 'payment') || str_contains($type, 'dr')) {
            return 'expense';
        }
        // Default: positive with no type hint = income (e.g. bank statement credits)
        return 'income';
    }

    /**
     * Suggest a budget_categories.id based on description keywords.
     * Returns null if no match — user assigns manually during review.
     */
    private function suggestCategory(string $description): ?int
    {
        static $map = [
            'grocery|kroger|walmart|aldi|publix|whole foods|trader joe|food lion|sprouts|phillips|family dollar|iga|houchen\'s' => 3,
            'mcdonald|chick-fil|burger|dairy queen|juicy seafood|captain|popeyes|pizza|domino|hardee\'s|subway|taco bell|kfc|wendy\'s|wendy|chipotle|panera|365 market|five star' => 3,
            'netflix|spotify|hulu|disney|amazon prime|youtube|apple tv|peacock|paramount|streaming' => 6,
            'shell|chevron|exxon|pilot|wawa|murphy|marathon|fuel|gas station' => 4,
            'rent|mortgage|apartment|lease|hoa' => 1,
            'electric|water|internet|comcast|at&t|verizon|t-mobile|spectrum|cox|utility' => 2,
            'cvs|walgreens|rite aid|pharmacy|hospital|clinic|dentist|doctor|medical|dental' => 9,
            'daycare|day care|preschool|childcare|school|tuition' => 7,
            'amazon|bestbuy|target|costco|sam\'s club|bp' => 10,
            'car|auto|uber|lyft|taxi|transportation|bus|train|flight|airline' => 12,
            'advance|loan|cash|payday|check cash|pawn' => 15,
            'fee|charge|overdraft|late|penalty|interest' => 14,
            'mypay|repayment' => 15,
        ];

        $desc = mb_strtolower($description);
        foreach ($map as $pattern => $categoryId) {
            if (preg_match('/' . $pattern . '/i', $desc)) {
                return $categoryId;
            }
        }

        return null;
    }

    /**
     * Map a raw category string (from CSV header) to a budget_categories.id.
     */
    private function mapCategoryByName(string $rawCategory): ?int
    {
        static $nameMap = [
            'housing'       => 1,
            'utilities'     => 2,
            'food'          => 3,
            'grocery'       => 3,
            'groceries'     => 3,
            'fuel'          => 4,
            'gas'           => 4,
            'savings'       => 5,
            'entertainment' => 6,
            'kids'          => 7,
            'emergency'     => 8,
            'healthcare'    => 9,
            'health'        => 9,
            'medical'       => 9,
            'other'         => 10,
        ];

        $key = mb_strtolower(trim($rawCategory));
        foreach ($nameMap as $pattern => $id) {
            if (str_contains($key, $pattern)) {
                return $id;
            }
        }

        return null;
    }

    // ── Excel parsers ─────────────────────────────────────────

    /**
     * Parse Excel using PhpSpreadsheet (when available).
     * Reads all sheets, auto-detects header row on first sheet.
     */
    private function parseExcelWithLibrary(string $filePath): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $rows        = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $ws = $spreadsheet->getSheetByName($sheetName);
            if (!$ws) continue;

            // Find the first row that looks like a header
            $maxRow    = $ws->getHighestRow();
            $headerRow = null;
            $colMap    = [];

            for ($r = 1; $r <= min(10, $maxRow); $r++) {
                $cells = [];
                for ($c = 1; $c <= $ws->getHighestColumn(null, true); $c++) {
                    $cells[] = mb_strtolower((string)$ws->getCellByColumnAndRow($c, $r)->getValue());
                }
                $testMap = $this->detectColumns($cells);
                if (isset($testMap['date']) && isset($testMap['amount'])) {
                    $headerRow = $r;
                    $colMap    = $testMap;
                    break;
                }
            }

            if ($headerRow === null) {
                continue; // sheet has no recognizable headers
            }

            for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
                $rawRow = [];
                $highestCol = $ws->getHighestColumn(null, true);
                for ($c = 1; $c <= $highestCol; $c++) {
                    $cell  = $ws->getCellByColumnAndRow($c, $r);
                    $value = $cell->getValue();

                    if ($value instanceof \PhpOffice\PhpSpreadsheet\Shared\Date
                        || (is_numeric($value) && (int)$value > 40000 && (int)$value < 60000)) {
                        try {
                            $dt    = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                            $value = $dt->format('Y-m-d');
                        } catch (Exception $e) {
                            // keep raw value
                        }
                    }

                    $rawRow[] = (string)$value;
                }

                $extracted = $this->extractRow($rawRow, $colMap);
                if (trim($extracted['date']) === '' && trim($extracted['amount']) === '') {
                    continue;
                }
                $rows[] = $extracted;
            }
        }

        return $rows;
    }

    /**
     * Native XLSX parser using PHP's ZipArchive + SimpleXML.
     * No library dependencies required.
     */
    private function parseXlsxNative(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Cannot open XLSX file — file may be corrupted.");
        }

        // Load shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = @simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    $text = '';
                    foreach ($si->r ?? [$si] as $r) {
                        $text .= (string)($r->t ?? '');
                    }
                    if ($text === '') $text = (string)($si->t ?? '');
                    $sharedStrings[] = $text;
                }
            }
        }

        // Map sheet names to file paths
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if (!$wbXml) { $zip->close(); return []; }

        $wb = @simplexml_load_string($wbXml);
        if (!$wb) { $zip->close(); return []; }

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $relMap  = [];
        if ($relsXml) {
            $rels = @simplexml_load_string($relsXml);
            if ($rels) {
                foreach ($rels->Relationship as $rel) {
                    $relMap[(string)$rel['Id']] = (string)$rel['Target'];
                }
            }
        }

        $sheetPaths = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $rNs  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $rId  = (string)$sheet->attributes($rNs)['id'];
            $path = 'xl/' . ltrim($relMap[$rId] ?? '', '/');
            if ($path !== 'xl/') {
                $sheetPaths[] = $path;
            }
        }

        $rows = [];

        foreach ($sheetPaths as $sheetPath) {
            $xml = $zip->getFromName($sheetPath);
            if (!$xml) continue;

            $sheet = @simplexml_load_string($xml);
            if (!$sheet) continue;

            // Collect all rows as [rowNum => [colIdx => value]]
            $rowData = [];
            foreach ($sheet->sheetData->row as $row) {
                $rowNum = (int)$row['r'];
                foreach ($row->c as $cell) {
                    $ref       = (string)$cell['r'];
                    $colLetter = preg_replace('/[0-9]/', '', $ref);
                    $colIdx    = $this->colLetterToIndex($colLetter);
                    $type      = (string)$cell['t'];
                    $value     = (string)$cell->v;

                    if ($type === 's') {
                        $value = $sharedStrings[(int)$value] ?? '';
                    } elseif (is_numeric($value) && $value !== '' && (float)$value > 40000 && (float)$value < 60000) {
                        // Likely an Excel serial date
                        $ts = ((float)$value - 25569) * 86400;
                        if ($ts > 0) {
                            $value = gmdate('Y-m-d', (int)$ts);
                        }
                    }

                    $rowData[$rowNum][$colIdx] = $value;
                }
            }

            if (empty($rowData)) continue;

            // Find header row
            $headerRow  = null;
            $colMap     = [];
            ksort($rowData);

            foreach ($rowData as $rNum => $cells) {
                ksort($cells);
                $cellValues = array_map('mb_strtolower', $cells);
                // Re-index to be sequential
                $reindexed = array_values($cellValues);
                $testMap   = $this->detectColumns($reindexed);
                if (isset($testMap['date']) && isset($testMap['amount'])) {
                    $headerRow = $rNum;
                    // Store mapping from field → column index
                    $headerCols = array_keys($cells);
                    foreach ($testMap as $field => $seqIdx) {
                        if (isset($headerCols[$seqIdx])) {
                            $colMap[$field] = $headerCols[$seqIdx];
                        }
                    }
                    break;
                }
            }

            if ($headerRow === null) continue;

            foreach ($rowData as $rNum => $cells) {
                if ($rNum <= $headerRow) continue;

                $extracted = [
                    'date'        => isset($colMap['date'])        ? ($cells[$colMap['date']]        ?? '') : '',
                    'description' => isset($colMap['description']) ? ($cells[$colMap['description']] ?? '') : '',
                    'amount'      => isset($colMap['amount'])      ? ($cells[$colMap['amount']]      ?? '') : '',
                    'type'        => isset($colMap['type'])        ? ($cells[$colMap['type']]        ?? '') : '',
                    'merchant'    => isset($colMap['merchant'])    ? ($cells[$colMap['merchant']]    ?? '') : '',
                    'category'    => isset($colMap['category'])    ? ($cells[$colMap['category']]    ?? '') : '',
                ];

                if (trim($extracted['date']) === '' && trim($extracted['amount']) === '') {
                    continue;
                }

                $rows[] = $extracted;
            }
        }

        $zip->close();
        return $rows;
    }

    private function colLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index   = 0;
        $len     = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $index - 1; // 0-based
    }

    // ── Result builder ────────────────────────────────────────

    /**
     * Build a structured result array.
     */
    private function result(bool $success, int $sessionId, int $imported, int $duplicates, int $failed, array $errors): array
    {
        return [
            'success'    => $success,
            'session_id' => $sessionId,
            'imported'   => $imported,
            'duplicates' => $duplicates,
            'failed'     => $failed,
            'errors'     => $errors,
        ];
    }
}
