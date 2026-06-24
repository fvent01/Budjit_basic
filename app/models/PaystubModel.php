<?php
// app/models/PaystubModel.php

class PaystubModel extends Model
{
    protected string $table = 'paystubs';

    // ── Fetch ─────────────────────────────────────────────────

    public function getForUser(int $userId): array
    {
        return $this->query(
            "SELECT * FROM paystubs WHERE user_id = ? ORDER BY pay_date DESC",
            [$userId]
        )->fetchAll();
    }

    public function getRecent(int $userId, int $limit = 5): array
    {
        return $this->query(
            "SELECT * FROM paystubs WHERE user_id = ? ORDER BY pay_date DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    // ── Parse via Python ──────────────────────────────────────

    public function parsePdf(string $filePath): array
    {
        $script  = PLUGIN_PATH . '/paystub-import/parse_paystub.py';
        $python  = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $escaped = escapeshellarg($filePath);
        $output  = shell_exec("{$python} {$script} {$escaped} 2>&1");

        if (!$output) {
            throw new RuntimeException('PDF parser returned no output. Is Python installed?');
        }

        // Find JSON in output
        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            throw new RuntimeException("Parser error: {$output}");
        }

        $data = json_decode(substr($output, $jsonStart), true);
        if (!is_array($data)) {
            throw new RuntimeException("Could not decode parser output: {$output}");
        }

        if (isset($data['error'])) {
            throw new RuntimeException($data['error']);
        }

        return $data;
    }

    // ── Save parsed/manual stub ───────────────────────────────

    public function saveStub(int $userId, array $data, string $source, string $filename = ''): int
    {
        return $this->insert([
            'user_id'             => $userId,
            'employer_name'       => $data['employer_name']      ?? '',
            'employee_name'       => $data['employee_name']      ?? '',
            'employee_address'    => $data['employee_address']   ?? '',
            'period_beginning'    => $data['period_beginning'],
            'period_ending'       => $data['period_ending'],
            'pay_date'            => $data['pay_date'],
            'gross_pay'           => (float)($data['gross_pay']        ?? 0),
            'net_pay'             => (float)($data['net_pay']          ?? 0),
            'regular_hours'       => (float)($data['regular_hours']    ?? 0),
            'regular_amount'      => (float)($data['regular_amount']   ?? 0),
            'overtime_hours'      => (float)($data['overtime_hours']   ?? 0),
            'overtime_amount'     => (float)($data['overtime_amount']  ?? 0),
            'other_earnings'      => (float)($data['other_earnings']   ?? 0),
            'other_earnings_json' => $data['other_earnings_json']      ?? '{}',
            'federal_tax'         => (float)($data['federal_tax']      ?? 0),
            'social_security'     => (float)($data['social_security']  ?? 0),
            'medicare'            => (float)($data['medicare']         ?? 0),
            'state_tax'           => (float)($data['state_tax']        ?? 0),
            'local_tax'           => (float)($data['local_tax']        ?? 0),
            'other_deductions'    => (float)($data['other_deductions'] ?? 0),
            'total_deductions'    => (float)($data['total_deductions'] ?? 0),
            'ytd_gross'           => (float)($data['ytd_gross']        ?? 0),
            'ytd_net'             => (float)($data['ytd_net']          ?? 0),
            'ytd_federal_tax'     => (float)($data['ytd_federal_tax']  ?? 0),
            'ytd_flag'            => (int)($data['ytd_flag']           ?? 0),
            'ytd_flag_reason'     => $data['ytd_flag_reason']          ?? '',
            'source'              => $source,
            'raw_filename'        => $filename,
            'status'              => 'parsed',
        ]);
    }

    // ── Confirm: write net pay to income_entries ──────────────

    public function confirmImport(int $stubId, int $userId, array $overrides): int
    {
        $stub = $this->find($stubId);
        if (!$stub) throw new RuntimeException('Stub not found.');

        $netPay    = (float)($overrides['net_pay']   ?? $stub['net_pay']);
        $payDate   = $overrides['pay_date']           ?? $stub['pay_date'];
        $desc      = $overrides['description']        ?? ('Pay: ' . ($stub['employer_name'] ?: 'Employer') . ' (' . date('M j', strtotime($stub['period_beginning'])) . '–' . date('M j', strtotime($stub['period_ending'])) . ')');
        $sourceId  = $overrides['income_source_id']   ?? null;
        $budgetId  = $overrides['budget_id']          ?? null;
        $notes     = sprintf(
            'Gross: $%s | Fed: -$%s | SS: -$%s | Medicare: -$%s | KY: -$%s | Glasgow: -$%s',
            number_format($stub['gross_pay'], 2),
            number_format($stub['federal_tax'], 2),
            number_format($stub['social_security'], 2),
            number_format($stub['medicare'], 2),
            number_format($stub['state_tax'], 2),
            number_format($stub['local_tax'], 2)
        );

        $incModel = new IncomeEntryModel();
        $entryId  = $incModel->addEntry($userId, [
            'budget_id'        => $budgetId ?: null,
            'income_source_id' => $sourceId ?: null,
            'description'      => $desc,
            'amount'           => $netPay,
            'received_date'    => $payDate,
            'is_recurring'     => 1,
            'notes'            => $notes,
        ]);

        $this->update($stubId, [
            'status'          => 'imported',
            'income_entry_id' => $entryId,
        ]);

        return $entryId;
    }

    // ── YTD anomaly check against prior stubs ─────────────────

    public function checkYtdAnomaly(int $userId, array $parsed): array
    {
        $flags = $parsed['ytd_flag_reason'] ? explode('; ', $parsed['ytd_flag_reason']) : [];

        // Compare net pay to recent average
        $recent = $this->query(
            "SELECT AVG(net_pay) AS avg_net, MAX(net_pay) AS max_net, MIN(net_pay) AS min_net
             FROM paystubs WHERE user_id = ? AND status != 'skipped'
             ORDER BY pay_date DESC LIMIT 6",
            [$userId]
        )->fetch();

        if ($recent && $recent['avg_net'] > 0) {
            $netPay  = (float)$parsed['net_pay'];
            $avgNet  = (float)$recent['avg_net'];
            $changePct = abs(($netPay - $avgNet) / $avgNet) * 100;

            if ($changePct > 30) {
                $direction = $netPay > $avgNet ? 'higher' : 'lower';
                $flags[] = sprintf(
                    'Net pay ($%s) is %.0f%% %s than your recent average ($%s)',
                    number_format($netPay, 2), $changePct, $direction, number_format($avgNet, 2)
                );
            }
        }

        return $flags;
    }
}
