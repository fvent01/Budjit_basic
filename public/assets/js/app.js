/* Budjit — app.js */

(function () {
  'use strict';

  // ── Auto-dismiss flash messages after 5s ─────────────────
  document.querySelectorAll('.flash').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 420);
    }, 5000);
  });

  // ── Confirm delete forms (fallback for inline onsubmit) ──
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Allocation total calculator ───────────────────────────
  var allocInputs = document.querySelectorAll('.alloc-input');
  var totalEl     = document.getElementById('alloc-total');
  if (allocInputs.length && totalEl) {
    function recalc() {
      var sum = 0;
      allocInputs.forEach(function (i) { sum += parseFloat(i.value) || 0; });
      totalEl.textContent = '$' + sum.toFixed(2);
    }
    allocInputs.forEach(function (i) { i.addEventListener('input', recalc); });
    recalc();
  }

  // ── Mark-paid quick action (AJAX optional upgrade path) ──
  // Currently uses normal form POST — no JS needed.

})();
