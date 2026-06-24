<?php $pageTitle = 'Enter Pay Stub Manually'; ?>
<div class="page-header">
  <div><h1 class="page-title">Enter pay stub manually</h1><p class="page-sub">Use this form if PDF parsing failed or you prefer manual entry.</p></div>
  <a href="<?= BASE_URL ?>/paystub" class="btn">← Back</a>
</div>
<?php if (!empty($errors)): ?>
  <div class="alert alert-error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<div class="form-layout">
  <form method="POST" action="<?= BASE_URL ?>/paystub/manual">
    <?= Auth::csrfField() ?>
    <div class="card form-card">
      <h2 class="form-section-title">Employer & Period</h2>
      <div class="form-row">
        <div class="form-group"><label>Employer name</label><input type="text" name="employer_name" value="<?= htmlspecialchars($data['employer_name'] ?? 'Bluegrass Ingredients Inc') ?>"></div>
        <div class="form-group"><label>Employee name</label><input type="text" name="employee_name" value="<?= htmlspecialchars($data['employee_name'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Period beginning</label><input type="date" name="period_beginning" value="<?= htmlspecialchars($data['period_beginning'] ?? '') ?>" required></div>
        <div class="form-group"><label>Period ending</label><input type="date" name="period_ending" value="<?= htmlspecialchars($data['period_ending'] ?? '') ?>" required></div>
      </div>
      <div class="form-group"><label>Pay date</label><input type="date" name="pay_date" value="<?= htmlspecialchars($data['pay_date'] ?? date('Y-m-d')) ?>" required></div>
    </div>

    <div class="card form-card">
      <h2 class="form-section-title">Earnings</h2>
      <div class="form-row">
        <div class="form-group"><label>Regular hours</label><input type="number" name="regular_hours" step="0.01" min="0" value="<?= htmlspecialchars($data['regular_hours'] ?? '0') ?>"></div>
        <div class="form-group"><label>Regular pay ($)</label><input type="number" name="regular_amount" step="0.01" min="0" value="<?= htmlspecialchars($data['regular_amount'] ?? '0') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Overtime hours</label><input type="number" name="overtime_hours" step="0.01" min="0" value="<?= htmlspecialchars($data['overtime_hours'] ?? '0') ?>"></div>
        <div class="form-group"><label>Overtime pay ($)</label><input type="number" name="overtime_amount" step="0.01" min="0" value="<?= htmlspecialchars($data['overtime_amount'] ?? '0') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Other earnings ($) <span class="label-hint">Holiday, shift diff, PRE50 etc.</span></label><input type="number" name="other_earnings" step="0.01" min="0" value="<?= htmlspecialchars($data['other_earnings'] ?? '0') ?>"></div>
        <div class="form-group"><label>Gross pay ($)</label><input type="number" name="gross_pay" step="0.01" min="0" value="<?= htmlspecialchars($data['gross_pay'] ?? '0') ?>" id="gross-pay"></div>
      </div>
    </div>

    <div class="card form-card">
      <h2 class="form-section-title">Deductions</h2>
      <div class="form-row">
        <div class="form-group"><label>Federal income tax ($)</label><input type="number" name="federal_tax" step="0.01" min="0" value="<?= htmlspecialchars($data['federal_tax'] ?? '0') ?>" class="deduction-input"></div>
        <div class="form-group"><label>Social Security ($)</label><input type="number" name="social_security" step="0.01" min="0" value="<?= htmlspecialchars($data['social_security'] ?? '0') ?>" class="deduction-input"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Medicare ($)</label><input type="number" name="medicare" step="0.01" min="0" value="<?= htmlspecialchars($data['medicare'] ?? '0') ?>" class="deduction-input"></div>
        <div class="form-group"><label>KY State income tax ($)</label><input type="number" name="state_tax" step="0.01" min="0" value="<?= htmlspecialchars($data['state_tax'] ?? '0') ?>" class="deduction-input"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Glasgow income tax ($)</label><input type="number" name="local_tax" step="0.01" min="0" value="<?= htmlspecialchars($data['local_tax'] ?? '0') ?>" class="deduction-input"></div>
        <div class="form-group"><label>Other deductions ($)</label><input type="number" name="other_deductions" step="0.01" min="0" value="<?= htmlspecialchars($data['other_deductions'] ?? '0') ?>" class="deduction-input"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Total deductions ($) <span class="label-hint">auto-calculated</span></label><input type="number" name="total_deductions" step="0.01" min="0" value="<?= htmlspecialchars($data['total_deductions'] ?? '0') ?>" id="total-deductions" readonly style="background:var(--bg-secondary);"></div>
        <div class="form-group"><label>Net pay ($) <span class="label-hint">what hits your bank</span></label><input type="number" name="net_pay" step="0.01" min="0.01" value="<?= htmlspecialchars($data['net_pay'] ?? '0') ?>" required id="net-pay" style="font-weight:600; font-size:15px;"></div>
      </div>
    </div>

    <div class="card form-card">
      <h2 class="form-section-title">Year-to-Date</h2>
      <div class="form-row">
        <div class="form-group"><label>YTD gross pay ($)</label><input type="number" name="ytd_gross" step="0.01" min="0" value="<?= htmlspecialchars($data['ytd_gross'] ?? '0') ?>"></div>
        <div class="form-group"><label>YTD federal tax ($)</label><input type="number" name="ytd_federal_tax" step="0.01" min="0" value="<?= htmlspecialchars($data['ytd_federal_tax'] ?? '0') ?>"></div>
      </div>
    </div>

    <div class="form-actions">
      <a href="<?= BASE_URL ?>/paystub" class="btn">Cancel</a>
      <button type="submit" class="btn btn-primary">Save & Review</button>
    </div>
  </form>
</div>

<script>
// Auto-sum deductions and calculate net pay
const deductionInputs = document.querySelectorAll('.deduction-input');
const totalEl         = document.getElementById('total-deductions');
const netEl           = document.getElementById('net-pay');
const grossEl         = document.getElementById('gross-pay');

function recalc() {
  let total = 0;
  deductionInputs.forEach(i => total += parseFloat(i.value) || 0);
  totalEl.value = total.toFixed(2);
  const gross = parseFloat(grossEl.value) || 0;
  if (gross > 0) netEl.value = Math.max(0, gross - total).toFixed(2);
}

deductionInputs.forEach(i => i.addEventListener('input', recalc));
grossEl.addEventListener('input', recalc);
</script>
