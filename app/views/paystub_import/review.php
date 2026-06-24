<?php $pageTitle = 'Review Pay Stub'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Review Pay Stub</h1>
    <p class="page-sub">
      <?= htmlspecialchars($stub['employer_name'] ?: 'Earnings Statement') ?>
      &nbsp;·&nbsp; Pay date: <?= date('M j, Y', strtotime($stub['pay_date'])) ?>
    </p>
  </div>
  <div class="header-actions">
    <a href="<?= BASE_URL ?>/paystub/<?= $stub['id'] ?>/edit" class="btn"><i class="ti ti-pencil"></i> Edit values</a>
    <a href="<?= BASE_URL ?>/paystub" class="btn">← Back</a>
  </div>
</div>

<!-- YTD anomaly flag -->
<?php if ($stub['ytd_flag'] && $stub['ytd_flag_reason']): ?>
  <div style="background:var(--amber-light); border:0.5px solid var(--amber); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:16px; display:flex; gap:10px; align-items:flex-start;">
    <i class="ti ti-alert-triangle" style="color:var(--amber-dark); font-size:20px; flex-shrink:0; margin-top:1px;"></i>
    <div>
      <div style="font-size:13px; font-weight:500; color:var(--amber-dark); margin-bottom:4px;">YTD Anomaly Detected</div>
      <?php foreach (explode('; ', $stub['ytd_flag_reason']) as $flag): ?>
        <div style="font-size:12px; color:var(--amber-dark);">• <?= htmlspecialchars($flag) ?></div>
      <?php endforeach; ?>
      <div style="font-size:11px; color:var(--amber-dark); margin-top:6px; opacity:0.8;">Review the values below before importing. You can edit any field if parsing was inaccurate.</div>
    </div>
  </div>
<?php endif; ?>

<div class="two-col" style="align-items:start;">

  <!-- Left: Full earnings statement -->
  <div style="display:flex; flex-direction:column; gap:14px;">

    <!-- Header info -->
    <div class="card">
      <div class="card-header"><span class="card-title">Statement Details</span></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <?php
        $details = [
          ['Employer',         $stub['employer_name']],
          ['Employee',         $stub['employee_name']],
          ['Period Beginning', date('M j, Y', strtotime($stub['period_beginning']))],
          ['Period Ending',    date('M j, Y', strtotime($stub['period_ending']))],
          ['Pay Date',         date('M j, Y', strtotime($stub['pay_date']))],
          ['Source',           ucfirst($stub['source']) . ($stub['raw_filename'] ? ' (' . htmlspecialchars($stub['raw_filename']) . ')' : '')],
        ];
        foreach ($details as [$label, $val]):
        ?>
          <div>
            <div style="font-size:10px; color:var(--text-tertiary); margin-bottom:2px;"><?= $label ?></div>
            <div style="font-size:13px; color:var(--text-primary);"><?= htmlspecialchars($val ?: '—') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Earnings breakdown -->
    <div class="card">
      <div class="card-header"><span class="card-title">Earnings This Period</span></div>
      <table class="data-table">
        <thead><tr><th>Type</th><th>Hours</th><th>Amount</th></tr></thead>
        <tbody>
          <?php if ($stub['regular_amount'] > 0): ?>
            <tr><td>Regular</td><td><?= $stub['regular_hours'] ?>h</td><td>$<?= number_format($stub['regular_amount'],2) ?></td></tr>
          <?php endif; ?>
          <?php if ($stub['overtime_amount'] > 0): ?>
            <tr><td>Overtime</td><td><?= $stub['overtime_hours'] ?>h</td><td>$<?= number_format($stub['overtime_amount'],2) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($other)): ?>
            <?php foreach ($other as $label => $amount): ?>
              <tr><td><?= htmlspecialchars($label) ?></td><td>—</td><td>$<?= number_format($amount,2) ?></td></tr>
            <?php endforeach; ?>
          <?php elseif ($stub['other_earnings'] > 0): ?>
            <tr><td>Other earnings</td><td>—</td><td>$<?= number_format($stub['other_earnings'],2) ?></td></tr>
          <?php endif; ?>
          <tr style="border-top:1px solid var(--border);">
            <td colspan="2" style="font-weight:600;">Gross Pay</td>
            <td style="font-weight:600; font-size:15px;">$<?= number_format($stub['gross_pay'],2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Deductions breakdown -->
    <div class="card">
      <div class="card-header"><span class="card-title">Deductions This Period</span></div>
      <table class="data-table">
        <thead><tr><th>Deduction</th><th>Amount</th></tr></thead>
        <tbody>
          <?php
          $deductions = [
            'Federal Income Tax'  => $stub['federal_tax'],
            'Social Security Tax' => $stub['social_security'],
            'Medicare Tax'        => $stub['medicare'],
            'KY State Income Tax' => $stub['state_tax'],
            'Glasgow Income Tax'  => $stub['local_tax'],
          ];
          foreach ($deductions as $label => $amount):
            if ($amount <= 0) continue;
          ?>
            <tr>
              <td><?= $label ?></td>
              <td style="color:var(--red-dark);">-$<?= number_format($amount,2) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="border-top:1px solid var(--border);">
            <td style="font-weight:600;">Total Deductions</td>
            <td style="font-weight:600; color:var(--red-dark);">-$<?= number_format($stub['total_deductions'],2) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- YTD summary -->
    <div class="card">
      <div class="card-header"><span class="card-title">Year-to-Date</span></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <div>
          <div style="font-size:10px; color:var(--text-tertiary);">YTD Gross</div>
          <div style="font-size:16px; font-weight:500; color:var(--text-primary);">$<?= number_format($stub['ytd_gross'],2) ?></div>
        </div>
        <div>
          <div style="font-size:10px; color:var(--text-tertiary);">YTD Federal Tax</div>
          <div style="font-size:16px; font-weight:500; color:var(--red-dark);">$<?= number_format($stub['ytd_federal_tax'],2) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Net pay summary + confirm form -->
  <div style="display:flex; flex-direction:column; gap:14px;">

    <!-- Net pay highlight -->
    <div class="card" style="border-color:var(--green);">
      <div style="text-align:center; padding:10px 0;">
        <div style="font-size:11px; color:var(--text-secondary); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.08em;">Net Pay — Direct Deposit</div>
        <div style="font-size:40px; font-weight:700; color:var(--green);">$<?= number_format($stub['net_pay'],2) ?></div>
        <div style="font-size:11px; color:var(--text-tertiary); margin-top:4px;">
          <?= round((($stub['total_deductions'] / $stub['gross_pay']) * 100), 1) ?>% deducted from $<?= number_format($stub['gross_pay'],2) ?> gross
        </div>
      </div>
    </div>

    <!-- Confirm form -->
    <?php if ($stub['status'] !== 'imported'): ?>
      <div class="card">
        <div class="card-header"><span class="card-title">Import as Income</span></div>
        <form method="POST" action="<?= BASE_URL ?>/paystub/<?= $stub['id'] ?>/confirm">
          <?= Auth::csrfField() ?>

          <div class="form-group">
            <label>Net pay to record ($)</label>
            <input type="number" name="net_pay" step="0.01" min="0.01"
                   value="<?= htmlspecialchars($stub['net_pay']) ?>" required>
          </div>

          <div class="form-group">
            <label>Pay date</label>
            <input type="date" name="pay_date" value="<?= htmlspecialchars($stub['pay_date']) ?>" required>
          </div>

          <div class="form-group">
            <label>Description <span class="label-hint">(auto-generated)</span></label>
            <input type="text" name="description"
                   value="Pay: <?= htmlspecialchars($stub['employer_name'] ?: 'Employer') ?> (<?= date('M j', strtotime($stub['period_beginning'])) ?>–<?= date('M j', strtotime($stub['period_ending'])) ?>)">
          </div>

          <div class="form-group">
            <label>Income source <span class="label-hint">(optional)</span></label>
            <select name="income_source_id">
              <option value="">— Select source —</option>
              <?php foreach ($sources as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Apply to budget <span class="label-hint">(optional)</span></label>
            <select name="budget_id">
              <option value="">— No budget —</option>
              <?php foreach ($budgets as $b): ?>
                <option value="<?= $b['id'] ?>"
                  <?= ($current && $current['id'] == $b['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['title']) ?> (<?= date('M j', strtotime($b['start_date'])) ?>–<?= date('M j', strtotime($b['end_date'])) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="flex:1;">
              <i class="ti ti-check"></i> Import net pay
            </button>
          </div>
        </form>

        <div style="margin-top:10px;">
          <form method="POST" action="<?= BASE_URL ?>/paystub/<?= $stub['id'] ?>/skip">
            <?= Auth::csrfField() ?>
            <button type="submit" class="btn" style="width:100%; font-size:12px; color:var(--text-tertiary);">
              Skip — don't import this stub
            </button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="card" style="text-align:center; padding:20px;">
        <i class="ti ti-circle-check" style="font-size:36px; color:var(--green);"></i>
        <div style="font-size:14px; font-weight:500; color:var(--text-primary); margin-top:8px;">Already imported</div>
        <div style="font-size:12px; color:var(--text-secondary); margin-top:4px;">
          $<?= number_format($stub['net_pay'],2) ?> was added to your income on <?= date('M j, Y', strtotime($stub['pay_date'])) ?>.
        </div>
        <a href="<?= BASE_URL ?>/income" class="btn btn-sm" style="margin-top:12px;">View in income →</a>
      </div>
    <?php endif; ?>
  </div>
</div>
