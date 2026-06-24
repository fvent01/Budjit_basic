<?php
// app/services/ChimePdfParser.php
//
// Parses Chime (Bancorp) checking account PDF statements exported from the
// Chime app.  Requires pdftotext (poppler-utils) to be installed on the server.
//
// Usage:
//   $parser = new ChimePdfParser();
//   $result = $parser->parse('/path/to/statement.pdf');
//   // $result['transactions'] — array of normalised transaction rows
//   // $result['meta']        — account holder, account number, period, summary
//   // $result['errors']      — any non-fatal warnings

class ChimePdfParser
{
    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Parse a Chime PDF statement file.
     *
     * @param  string $pdfPath  Absolute path to the uploaded PDF.
     * @return array{
     *   transactions: array,
     *   meta: array,
     *   errors: array
     * }
     * @throws RuntimeException  If the file cannot be read or pdftotext is missing.
     */
    public function parse(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException("PDF file not found: {$pdfPath}");
        }

        $text = $this->extractText($pdfPath);
        if (empty(trim($text))) {
            throw new RuntimeException(
                'Could not extract text from PDF. ' .
                'Make sure pdftotext (poppler-utils) is installed on the server.'
            );
        }

        $meta         = $this->parseMeta($text);
        $transactions = $this->parseTransactions($text, $meta);
        $errors       = $this->validate($transactions, $meta);

        return compact('transactions', 'meta', 'errors');
    }

    // ── Text extraction ────────────────────────────────────────────────────

    private function extractText(string $pdfPath): string
    {
        // -layout preserves the column spacing Chime uses, which helps
        // with multi-word descriptions that span columns.
        $escaped = escapeshellarg($pdfPath);
        $cmd     = "pdftotext -layout {$escaped} -";

        $output = [];
        $code   = 0;
        exec($cmd . ' 2>/dev/null', $output, $code);

        if ($code !== 0) {
            // Fallback: try without -layout
            exec("pdftotext {$escaped} - 2>/dev/null", $output, $code);
        }

        if ($code !== 0) {
            throw new RuntimeException(
                'pdftotext failed (exit code ' . $code . '). ' .
                'Install poppler-utils: apt-get install poppler-utils'
            );
        }

        return implode("\n", $output);
    }

    // ── Meta / header parsing ──────────────────────────────────────────────

    private function parseMeta(string $text): array
    {
        $meta = [
            'account_holder'  => '',
            'account_number'  => '',
            'statement_start' => '',
            'statement_end'   => '',
            'institution'     => 'Chime / The Bancorp Bank',
            'beginning_balance' => null,
            'ending_balance'    => null,
            'total_deposits'    => null,
            'total_withdrawals' => null,
        ];

        // Account holder — first non-empty line(s) before "Checking Account Statement"
        if (preg_match('/^(.+?)\n.+?\n.+?\nChecking Account Statement/ms', $text, $m)) {
            $meta['account_holder'] = trim($m[1]);
        }

        // Account number
        if (preg_match('/Account number\s*\n\s*(\d+)/i', $text, $m)) {
            $meta['account_number'] = trim($m[1]);
        }

        // Statement period  "May 2026 (May 01, 2026 - May 31, 2026)"
        if (preg_match(
            '/Statement period\s*\n\s*[\w\s]+\((\w+ \d+, \d+)\s*[-–]\s*(\w+ \d+, \d+)\)/i',
            $text, $m
        )) {
            $meta['statement_start'] = $this->parseDate($m[1]);
            $meta['statement_end']   = $this->parseDate($m[2]);
        }

        // Summary balances
        $patterns = [
            'beginning_balance'  => '/Beginning balance[^\$]*\$([\d,.-]+)/i',
            'ending_balance'     => '/Ending balance[^\$]*\$([\d,.-]+)/i',
            'total_deposits'     => '/Deposits\s+\$([\d,.-]+)/i',
        ];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $meta[$key] = $this->parseAmount($m[1]);
            }
        }

        return $meta;
    }

    // ── Transaction parsing ────────────────────────────────────────────────

    /**
     * Chime statements have a transaction table with these columns:
     *   TRANSACTION DATE | DESCRIPTION | TYPE | AMOUNT | NET AMOUNT | SETTLEMENT DATE
     *
     * Rows can span two lines when the description has a secondary line
     * (e.g. the raw payment reference underneath the merchant name).
     */
    private function parseTransactions(string $text, array $meta): array
    {
        $transactions = [];

        // ── Step 1: isolate the Transactions section ───────────────────────
        // Everything between "Transactions" header and "Yearly Summary" (or end)
        if (!preg_match('/Transactions\s*\n(.+?)(?:Yearly Summary|Error Resolution|Page \d+ of \d+\s*$)/si', $text, $m)) {
            // Fallback: everything after the header line
            $pos = stripos($text, 'TRANSACTION DATE');
            if ($pos === false) return $transactions;
            $m[1] = substr($text, $pos);
        }

        $body = $m[1];

        // ── Step 2: strip the column-header line ──────────────────────────
        $body = preg_replace(
            '/TRANSACTION DATE\s+DESCRIPTION\s+TYPE\s+AMOUNT\s+NET AMOUNT\s+SETTLEMENT DATE\s*\n/i',
            '', $body
        );

        // ── Step 3: split into lines and walk them ─────────────────────────
        $lines = explode("\n", $body);

        // Date pattern: M/D/YYYY  (Chime uses no leading zeros)
        $datePattern = '/^(\d{1,2}\/\d{1,2}\/\d{4})\s+/';

        // Known transaction types (used to detect the TYPE column)
        $typePattern = '/\b(Purchase|Deposit|Transfer|ATM Withdrawal|Adjustment|Fee)\b/i';

        // Amount pattern (possibly negative)
        $amountPattern = '/(-?\$[\d,]+\.\d{2})/';

        $i = 0;
        while ($i < count($lines)) {
            $line = $lines[$i];

            // Must start with a date
            if (!preg_match($datePattern, $line, $dateMatch)) {
                $i++;
                continue;
            }

            $txnDate  = $this->parseDate($dateMatch[1]);
            $remainder = ltrim(substr($line, strlen($dateMatch[0])));

            // Collect continuation lines (lines that do NOT start with a date)
            $fullBlock = $remainder;
            while (
                isset($lines[$i + 1]) &&
                !preg_match($datePattern, $lines[$i + 1]) &&
                trim($lines[$i + 1]) !== ''
            ) {
                $i++;
                $fullBlock .= ' ' . trim($lines[$i]);
            }

            // Extract amounts (there are two: AMOUNT and NET AMOUNT)
            preg_match_all($amountPattern, $fullBlock, $amtMatches);
            $amounts    = $amtMatches[1] ?? [];
            $rawAmount  = isset($amounts[0]) ? $this->parseAmount($amounts[0]) : 0.0;
            // NET AMOUNT is the second match (same value, but we prefer the first)

            // Extract type
            $txnType = 'Purchase';
            if (preg_match($typePattern, $fullBlock, $typeMatch)) {
                $txnType = ucfirst(strtolower($typeMatch[1]));
            }

            // Extract settlement date (last date-like pattern in the block)
            $settlementDate = $txnDate;
            if (preg_match_all('/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/', $fullBlock, $sdm)) {
                $settlementDate = $this->parseDate(end($sdm[1]));
            }

            // Description: strip amounts, the type word, and settlement dates
            $desc = $fullBlock;
            $desc = preg_replace($amountPattern, '', $desc);           // strip amounts
            $desc = preg_replace($typePattern, '', $desc);             // strip type
            $desc = preg_replace('/\b\d{1,2}\/\d{1,2}\/\d{4}\b/', '', $desc); // strip dates
            $desc = preg_replace('/\s{2,}/', ' ', $desc);              // collapse spaces
            $desc = trim($desc, " \t\n\r,.-");

            if ($rawAmount == 0 && $desc === '') { $i++; continue; }

            // ── Determine debit / credit ───────────────────────────────────
            // Chime shows negative for outflows, positive for inflows
            $isCredit = $rawAmount > 0;
            $amount   = abs($rawAmount);

            // Transfers that go to Credit Builder or Savings are internal — flag them
            $isInternal = (bool)preg_match(
                '/transfer (to|from) (chime savings|credit builder)/i',
                $desc
            );

            $transactions[] = [
                'raw_date'        => $txnDate,
                'raw_description' => $this->cleanDescription($desc),
                'raw_amount'      => $amount,
                'raw_type'        => $isCredit ? 'credit' : 'debit',
                'txn_type'        => $txnType,          // Chime's own type label
                'settlement_date' => $settlementDate,
                'is_internal'     => $isInternal,
                'mapped_as'       => $this->suggestMappedAs($isCredit, $txnType, $desc, $isInternal),
            ];

            $i++;
        }

        // Sort ascending by date (statements list newest first)
        usort($transactions, fn($a, $b) => strcmp($a['raw_date'], $b['raw_date']));

        return $transactions;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Suggest import type based on what we know about the transaction.
     */
    private function suggestMappedAs(
        bool $isCredit, string $txnType, string $desc, bool $isInternal
    ): string {
        if ($isInternal) return 'skip';

        $lower = strtolower($desc);

        // Payroll / direct deposits → income
        if (preg_match('/payroll|direct deposit|my pay advance/i', $desc)) {
            return 'income';
        }

        // Cash deposits at stores → income
        if (preg_match('/cash deposit/i', $desc)) {
            return 'income';
        }

        // Inbound transfers from people (Venmo/PayPal) → income
        if ($isCredit && preg_match('/venmo|paypal/i', $desc)) {
            return 'income';
        }

        // ATM withdrawals, purchases → expense
        if (in_array(strtolower($txnType), ['purchase', 'atm withdrawal'])) {
            return 'expense';
        }

        // Adjustments (loan repayments, fees) → expense
        if (strtolower($txnType) === 'adjustment') {
            return 'expense';
        }

        // Remaining debits → expense
        if (!$isCredit) return 'expense';

        return 'skip'; // default: skip unclear credits
    }

    /**
     * Clean up noisy description text extracted from the PDF.
     */
    private function cleanDescription(string $desc): string
    {
        // Remove raw reference lines that are all-caps with location codes
        // e.g.  "PAYPAL*CONRAD MARY SAN JOSE CAUS"
        // We keep the first (human-readable) part if there are two lines joined
        $parts = preg_split('/\s{3,}/', $desc, 2);
        if (count($parts) === 2) {
            // First part is the human label, second is the raw reference
            $desc = trim($parts[0]);
        }

        return trim($desc);
    }

    private function parseDate(string $raw): string
    {
        $ts = strtotime(trim($raw));
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function parseAmount(string $raw): float
    {
        // Handle "-$1,025.31" and "$1,025.31"
        $clean  = str_replace(['$', ',', ' '], '', trim($raw));
        return (float)$clean;
    }

    // ── Validation ─────────────────────────────────────────────────────────

    private function validate(array $transactions, array $meta): array
    {
        $errors = [];

        if (empty($transactions)) {
            $errors[] = 'No transactions were found in this PDF. '
                      . 'Make sure you uploaded a Chime checking account statement.';
        }

        // Soft balance check — warns if totals seem off but does not abort
        if ($meta['total_deposits'] !== null) {
            $parsedDeposits = array_sum(array_column(
                array_filter($transactions, fn($t) => $t['raw_type'] === 'credit'),
                'raw_amount'
            ));
            $diff = abs($parsedDeposits - $meta['total_deposits']);
            if ($diff > 1.00) {
                $errors[] = sprintf(
                    'Deposit total mismatch: statement says $%s, parsed $%s. '
                    . 'Some transactions may be missing.',
                    number_format($meta['total_deposits'], 2),
                    number_format($parsedDeposits, 2)
                );
            }
        }

        return $errors;
    }
}