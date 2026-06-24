<?php
// app/views/settings/categories.php
$pageTitle = 'Categories';
?>
<?php require APP_PATH . '/views/settings/_tabs.php'; ?>

<style>
/* ── Category page layout ─────────────────────────────── */
.cat-wrap        { max-width: 860px; }
.cat-header      { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
.cat-header h2   { font-size:16px; font-weight:600; color:var(--text-primary); margin:0; }

/* Add form card */
.cat-add-card    { margin-bottom:20px; }
.cat-add-toggle  { display:flex; align-items:center; gap:8px; padding:10px 14px; background:var(--bg-secondary); border:0.5px solid var(--border); border-radius:var(--radius-md); cursor:pointer; font-size:13px; color:var(--text-secondary); user-select:none; transition:background 0.12s; }
.cat-add-toggle:hover { background:var(--bg-tertiary); color:var(--text-primary); }
.cat-add-toggle i.toggle-chevron { margin-left:auto; transition:transform 0.2s; }
.cat-add-toggle.open i.toggle-chevron { transform:rotate(180deg); }
.cat-add-body    { padding:16px; border:0.5px solid var(--border); border-top:none; border-radius:0 0 var(--radius-md) var(--radius-md); background:var(--bg-primary); display:none; }
.cat-add-body.open { display:block; }

/* Group sections */
.cat-group        { margin-bottom:24px; }
.cat-group-header { display:flex; align-items:center; gap:8px; font-size:11px; font-weight:600; color:var(--text-tertiary); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:10px; padding-bottom:8px; border-bottom:0.5px solid var(--border); }
.cat-group-header i { font-size:14px; }
.cat-group-empty  { font-size:13px; color:var(--text-tertiary); padding:14px 0; text-align:center; }

/* Category row */
.cat-row         { background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); margin-bottom:6px; transition:box-shadow 0.15s; }
.cat-row.hidden  { opacity:0.45; }
.cat-row-main    { display:flex; align-items:center; gap:10px; padding:10px 12px; }
.cat-drag        { cursor:grab; color:var(--text-tertiary); font-size:16px; flex-shrink:0; }
.cat-drag:active { cursor:grabbing; }
.cat-icon-dot    { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.cat-icon-dot i  { font-size:16px; color:#fff; }
.cat-name        { flex:1; font-size:13px; font-weight:500; color:var(--text-primary); }
.cat-meta        { display:flex; align-items:center; gap:14px; flex-shrink:0; }
.cat-count       { font-size:11px; color:var(--text-tertiary); min-width:50px; text-align:right; }
.cat-order-badge { font-size:10px; color:var(--text-tertiary); background:var(--bg-secondary); border:0.5px solid var(--border); border-radius:4px; padding:1px 5px; min-width:24px; text-align:center; }
.cat-hidden-badge { font-size:10px; color:var(--text-tertiary); background:var(--bg-secondary); border:0.5px dashed var(--border); border-radius:4px; padding:1px 6px; }
.cat-system-badge { font-size:10px; color:var(--blue); background:var(--blue-light); border-radius:4px; padding:1px 6px; }
.cat-actions     { display:flex; align-items:center; gap:2px; }
.cat-action-btn  { background:none; border:none; cursor:pointer; padding:5px; border-radius:var(--radius-sm); color:var(--text-tertiary); font-size:16px; display:flex; align-items:center; transition:color 0.12s, background 0.12s; }
.cat-action-btn:hover { background:var(--bg-secondary); color:var(--text-primary); }
.cat-action-btn.active { color:var(--blue); }
.cat-action-btn.danger:hover { color:var(--red); background:var(--red-light,#fee2e2); }
.cat-action-btn:disabled { opacity:0.35; cursor:not-allowed; }

/* Sortable ghost / drag state */
.sortable-ghost  { opacity:0.35; background:var(--bg-secondary) !important; }
.sortable-chosen { box-shadow:0 4px 12px rgba(0,0,0,0.12); }

/* Inline editor */
.cat-editor      { border-top:0.5px solid var(--border); padding:14px 14px 12px; background:var(--bg-secondary); border-radius:0 0 var(--radius-md) var(--radius-md); display:none; }
.cat-editor.open { display:block; }
.cat-editor-row  { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px; }
.cat-editor-row .form-group { margin-bottom:0; flex:1; min-width:140px; }
.cat-editor-actions { display:flex; gap:8px; justify-content:flex-end; }

/* Icon picker */
.icon-picker-wrap   { position:relative; }
.icon-picker-btn    { display:flex; align-items:center; gap:6px; padding:6px 10px; border:0.5px solid var(--border); border-radius:var(--radius-sm); background:var(--bg-primary); cursor:pointer; font-size:13px; color:var(--text-primary); min-width:100px; }
.icon-picker-btn i  { font-size:18px; }
.icon-picker-btn .pick-caret { margin-left:auto; font-size:12px; color:var(--text-tertiary); }
.icon-picker-drop   { position:absolute; top:calc(100% + 4px); left:0; background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,0.12); display:none; z-index:200; padding:10px; width:280px; }
.icon-picker-drop.open { display:block; }
.icon-picker-grid   { display:grid; grid-template-columns:repeat(8,1fr); gap:4px; max-height:200px; overflow-y:auto; }
.icon-picker-item   { width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:6px; cursor:pointer; font-size:18px; color:var(--text-secondary); transition:background 0.1s, color 0.1s; }
.icon-picker-item:hover  { background:var(--bg-secondary); color:var(--text-primary); }
.icon-picker-item.active { background:var(--blue-light); color:var(--blue); }

/* Color picker */
.color-picker-wrap { display:flex; align-items:center; gap:6px; }
.color-swatch      { width:28px; height:28px; border-radius:6px; border:0.5px solid var(--border); cursor:pointer; flex-shrink:0; overflow:hidden; padding:0; }
.color-swatch input[type=color] { width:100%; height:100%; border:none; padding:0; cursor:pointer; opacity:0; position:absolute; }
.color-swatch-inner { width:100%; height:100%; border-radius:6px; }

/* Inline error */
.cat-inline-error { font-size:11px; color:var(--red,#dc2626); margin-top:6px; display:none; }
.cat-inline-error.show { display:block; }

/* Toast */
#cat-toast { position:fixed; bottom:24px; right:24px; background:var(--bg-primary); border:0.5px solid var(--border); border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,0.14); padding:10px 16px; font-size:13px; display:flex; align-items:center; gap:8px; z-index:9999; transform:translateY(8px); opacity:0; transition:opacity 0.2s, transform 0.2s; pointer-events:none; max-width:340px; }
#cat-toast.show { opacity:1; transform:translateY(0); }
#cat-toast.success i { color:var(--green); }
#cat-toast.error   i { color:var(--red,#dc2626); }
</style>

<?php
// Encode PHP data for JavaScript
$jsSystem  = json_encode(array_values($system),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsCustom  = json_encode(array_values($custom),  JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsIcons   = json_encode($icons,                 JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$jsIsAdmin = $isAdmin ? 'true' : 'false';
$csrfToken = Auth::csrfToken();
$baseUrl   = BASE_URL;
?>

<div class="cat-wrap">

  <!-- ── Add Category ──────────────────────────────────────── -->
  <div class="card cat-add-card">
    <div class="cat-add-toggle" id="addToggle">
      <i class="ti ti-plus" style="font-size:16px;"></i>
      Add Category
      <i class="ti ti-chevron-down toggle-chevron"></i>
    </div>
    <div class="cat-add-body" id="addBody">
      <div class="cat-editor-row">
        <div class="form-group">
          <label style="font-size:12px;">Name <span style="color:var(--red);">*</span></label>
          <input type="text" id="addName" placeholder="e.g. Groceries" maxlength="100">
        </div>
        <div class="form-group">
          <label style="font-size:12px;">Icon</label>
          <div class="icon-picker-wrap" id="addIconWrap"></div>
        </div>
        <div class="form-group">
          <label style="font-size:12px;">Color</label>
          <div class="color-picker-wrap" id="addColorWrap"></div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="form-group" style="min-width:auto;">
          <label style="font-size:12px; display:block; margin-bottom:6px;">System-wide</label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px;">
            <input type="checkbox" id="addIsSystem" style="width:14px; height:14px;">
            <span style="color:var(--text-secondary);">Global</span>
          </label>
        </div>
        <?php endif; ?>
      </div>
      <div class="cat-inline-error" id="addError"></div>
      <div class="cat-editor-actions">
        <button class="btn btn-secondary btn-sm" id="addCancel">Cancel</button>
        <button class="btn btn-primary btn-sm" id="addSubmit">
          <i class="ti ti-plus"></i> Add Category
        </button>
      </div>
    </div>
  </div>

  <!-- ── System Categories ────────────────────────────────── -->
  <div class="cat-group" id="systemGroup">
    <div class="cat-group-header">
      <i class="ti ti-lock"></i>
      System Categories
      <span style="margin-left:auto; font-size:11px; font-weight:400; color:var(--text-tertiary);">
        <?= $isAdmin ? 'Admin-managed' : 'Read-only' ?>
      </span>
    </div>
    <div id="systemList">
      <?php if (empty($system)): ?>
        <div class="cat-group-empty" id="systemEmpty">No system categories.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Custom Categories ────────────────────────────────── -->
  <div class="cat-group" id="customGroup">
    <div class="cat-group-header">
      <i class="ti ti-user"></i>
      Custom Categories
    </div>
    <div id="customList">
      <?php if (empty($custom)): ?>
        <div class="cat-group-empty" id="customEmpty">No custom categories yet.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Toast notification -->
<div id="cat-toast">
  <i class="ti ti-circle-check"></i>
  <span id="cat-toast-msg"></span>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
  'use strict';

  // ── Config ────────────────────────────────────────────────
  const BASE    = <?= json_encode($baseUrl) ?>;
  const CSRF    = <?= json_encode($csrfToken) ?>;
  const IS_ADMIN = <?= $jsIsAdmin ?>;
  const ICONS   = <?= $jsIcons ?>;

  // ── State ─────────────────────────────────────────────────
  let systemCats = <?= $jsSystem ?>;
  let customCats = <?= $jsCustom ?>;
  let openEditorId = null; // id of the currently-open inline editor

  // ── Toast ──────────────────────────────────────────────────
  let toastTimer = null;
  function toast(msg, type = 'success') {
    const el  = document.getElementById('cat-toast');
    const ico = el.querySelector('i');
    el.querySelector('#cat-toast-msg').textContent = msg;
    el.className = 'show ' + type;
    ico.className = 'ti ' + (type === 'success' ? 'ti-circle-check' : 'ti-alert-circle');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.className = ''; }, 3000);
  }

  // ── API helpers ───────────────────────────────────────────
  async function apiFetch(url, formData) {
    formData.append('csrf_token', CSRF);
    const res  = await fetch(BASE + url, { method: 'POST', body: formData });
    const json = await res.json();
    return { ok: res.ok, status: res.status, data: json };
  }

  // ── Icon picker factory ───────────────────────────────────
  function makeIconPicker(containerId, initialIcon, onSelect) {
    const container = document.getElementById(containerId);
    if (!container) return;
    let current = initialIcon || ICONS[0];

    const wrap = document.createElement('div');
    wrap.className = 'icon-picker-wrap';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'icon-picker-btn';
    btn.innerHTML = `<i class="ti ${current}"></i><span class="pick-label">${current.replace('ti-','')}</span><i class="ti ti-chevron-down pick-caret"></i>`;

    const drop = document.createElement('div');
    drop.className = 'icon-picker-drop';
    const grid = document.createElement('div');
    grid.className = 'icon-picker-grid';

    ICONS.forEach(icon => {
      const item = document.createElement('div');
      item.className = 'icon-picker-item' + (icon === current ? ' active' : '');
      item.title = icon.replace('ti-', '');
      item.innerHTML = `<i class="ti ${icon}"></i>`;
      item.addEventListener('click', () => {
        current = icon;
        grid.querySelectorAll('.icon-picker-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        btn.querySelector('i').className = 'ti ' + icon;
        btn.querySelector('.pick-label').textContent = icon.replace('ti-', '');
        drop.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        onSelect(icon);
      });
      grid.appendChild(item);
    });

    drop.appendChild(grid);
    wrap.appendChild(btn);
    wrap.appendChild(drop);
    container.appendChild(wrap);

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = drop.classList.contains('open');
      // Close all other open pickers
      document.querySelectorAll('.icon-picker-drop.open').forEach(d => d.classList.remove('open'));
      if (!isOpen) {
        drop.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });

    return {
      getValue: () => current,
      setValue: (icon) => {
        current = icon;
        btn.querySelector('i').className = 'ti ' + icon;
        btn.querySelector('.pick-label').textContent = icon.replace('ti-', '');
        grid.querySelectorAll('.icon-picker-item').forEach(item => {
          item.classList.toggle('active', item.querySelector('i').className.includes(icon));
        });
      }
    };
  }

  // ── Color picker factory ──────────────────────────────────
  function makeColorPicker(containerId, initialColor, onSelect) {
    const container = document.getElementById(containerId);
    if (!container) return;
    let current = initialColor || '#1D9E75';

    const wrap = document.createElement('div');
    wrap.className = 'color-picker-wrap';
    wrap.style.position = 'relative';

    const swatch = document.createElement('div');
    swatch.className = 'color-swatch';
    swatch.style.cssText = `background-color:${current};position:relative;`;

    const colorInput = document.createElement('input');
    colorInput.type  = 'color';
    colorInput.value = current;
    colorInput.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:none;padding:0;';

    const hexInput = document.createElement('input');
    hexInput.type        = 'text';
    hexInput.value       = current.toUpperCase();
    hexInput.maxLength   = 7;
    hexInput.placeholder = '#RRGGBB';
    hexInput.style.cssText = 'width:80px; font-size:12px; font-family:monospace;';
    hexInput.className   = 'form-control';

    colorInput.addEventListener('input', () => {
      current = colorInput.value;
      swatch.style.backgroundColor = current;
      hexInput.value = current.toUpperCase();
      onSelect(current);
    });

    hexInput.addEventListener('input', () => {
      const v = hexInput.value.trim();
      if (/^#[0-9a-fA-F]{6}$/.test(v)) {
        current = v;
        colorInput.value = v;
        swatch.style.backgroundColor = v;
        onSelect(v);
      }
    });

    swatch.appendChild(colorInput);
    wrap.appendChild(swatch);
    wrap.appendChild(hexInput);
    container.appendChild(wrap);

    return {
      getValue: () => current,
      setValue: (color) => {
        current = color;
        colorInput.value = color;
        hexInput.value   = color.toUpperCase();
        swatch.style.backgroundColor = color;
      }
    };
  }

  // ── Close icon pickers on outside click ───────────────────
  document.addEventListener('click', () => {
    document.querySelectorAll('.icon-picker-drop.open').forEach(d => d.classList.remove('open'));
  });

  // ── Render helpers ────────────────────────────────────────

  function catExpLabel(n) {
    if (n === 0) return '<span class="cat-count">no expenses</span>';
    return `<span class="cat-count">${n} expense${n !== 1 ? 's' : ''}</span>`;
  }

  function canEditCat(cat) {
    if (IS_ADMIN) return true;
    if (parseInt(cat.is_system)) return false;
    return true; // Custom cats shown to user are always theirs
  }

  /**
   * Build a category row element (and its hidden inline editor).
   */
  function buildRow(cat) {
    const canEdit = canEditCat(cat);
    const isHidden   = parseInt(cat.is_hidden);
    const isSystem   = parseInt(cat.is_system);
    const expCount   = parseInt(cat.expense_count) || 0;

    const row = document.createElement('div');
    row.className = 'cat-row' + (isHidden ? ' hidden' : '');
    row.dataset.id = cat.id;

    // ── Main row ──
    const main = document.createElement('div');
    main.className = 'cat-row-main';

    // Drag handle (only if user can edit)
    const drag = document.createElement('span');
    drag.className = 'cat-drag';
    drag.innerHTML = canEdit ? '<i class="ti ti-grip-vertical"></i>' : '<i class="ti ti-grip-vertical" style="opacity:0.2;"></i>';
    main.appendChild(drag);

    // Icon
    const dot = document.createElement('div');
    dot.className = 'cat-icon-dot';
    dot.style.backgroundColor = cat.color;
    dot.innerHTML = `<i class="ti ${cat.icon}"></i>`;
    main.appendChild(dot);

    // Name
    const name = document.createElement('div');
    name.className = 'cat-name';
    name.textContent = cat.name;
    main.appendChild(name);

    // Meta
    const meta = document.createElement('div');
    meta.className = 'cat-meta';
    meta.innerHTML = catExpLabel(expCount);
    if (isSystem) {
      meta.innerHTML += '<span class="cat-system-badge">System</span>';
    }
    if (isHidden) {
      meta.innerHTML += '<span class="cat-hidden-badge">Hidden</span>';
    }
    meta.innerHTML += `<span class="cat-order-badge">#${cat.sort_order}</span>`;
    main.appendChild(meta);

    // Actions
    const actions = document.createElement('div');
    actions.className = 'cat-actions';

    if (canEdit) {
      // Edit
      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'cat-action-btn';
      editBtn.title = 'Edit';
      editBtn.innerHTML = '<i class="ti ti-pencil"></i>';
      editBtn.addEventListener('click', () => toggleEditor(cat.id));
      actions.appendChild(editBtn);

      // Hide/Show
      const eyeBtn = document.createElement('button');
      eyeBtn.type = 'button';
      eyeBtn.className = 'cat-action-btn' + (isHidden ? ' active' : '');
      eyeBtn.title = isHidden ? 'Show' : 'Hide';
      eyeBtn.innerHTML = isHidden ? '<i class="ti ti-eye-off"></i>' : '<i class="ti ti-eye"></i>';
      eyeBtn.addEventListener('click', () => doToggleVisibility(cat.id, row, eyeBtn));
      actions.appendChild(eyeBtn);

      // Delete
      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'cat-action-btn danger';
      delBtn.title = 'Delete';
      delBtn.innerHTML = '<i class="ti ti-trash"></i>';
      delBtn.addEventListener('click', () => doDelete(cat.id, row));
      actions.appendChild(delBtn);
    }

    main.appendChild(actions);
    row.appendChild(main);

    // ── Inline editor ──
    const editor = buildEditor(cat);
    row.appendChild(editor);

    return row;
  }

  /**
   * Build the inline editor panel for a category.
   */
  function buildEditor(cat) {
    const editor = document.createElement('div');
    editor.className = 'cat-editor';
    editor.id = 'editor-' + cat.id;

    const iconPickerId  = 'ep-icon-' + cat.id;
    const colorPickerId = 'ep-color-' + cat.id;

    editor.innerHTML = `
      <div class="cat-editor-row">
        <div class="form-group">
          <label style="font-size:12px;">Name <span style="color:var(--red);">*</span></label>
          <input type="text" id="ep-name-${cat.id}" value="${escHtml(cat.name)}" maxlength="100">
        </div>
        <div class="form-group">
          <label style="font-size:12px;">Icon</label>
          <div id="${iconPickerId}"></div>
        </div>
        <div class="form-group">
          <label style="font-size:12px;">Color</label>
          <div id="${colorPickerId}"></div>
        </div>
        <div class="form-group" style="min-width:80px;">
          <label style="font-size:12px;">Sort order</label>
          <input type="number" id="ep-order-${cat.id}" value="${cat.sort_order}" min="0" max="999" style="width:70px;">
        </div>
      </div>
      <div class="cat-inline-error" id="ep-err-${cat.id}"></div>
      <div class="cat-editor-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="ep-cancel-${cat.id}">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm"   id="ep-save-${cat.id}">
          <i class="ti ti-check"></i> Save
        </button>
      </div>
    `;

    // Must wait for DOM insertion before mounting pickers
    setTimeout(() => {
      const iconPicker  = makeIconPicker(iconPickerId,  cat.icon,  () => {});
      const colorPicker = makeColorPicker(colorPickerId, cat.color, () => {});

      document.getElementById(`ep-save-${cat.id}`).addEventListener('click', async () => {
        const name  = document.getElementById(`ep-name-${cat.id}`).value.trim();
        const order = parseInt(document.getElementById(`ep-order-${cat.id}`).value) || 0;
        const icon  = iconPicker.getValue();
        const color = colorPicker.getValue();
        const errEl = document.getElementById(`ep-err-${cat.id}`);

        if (!name) { showError(errEl, 'Name is required.'); return; }
        errEl.style.display = 'none';

        const fd = new FormData();
        fd.append('name', name);
        fd.append('icon', icon);
        fd.append('color', color);
        fd.append('sort_order', order);

        const saveBtn = document.getElementById(`ep-save-${cat.id}`);
        saveBtn.disabled = true;
        const { ok, data } = await apiFetch(`/api/categories/${cat.id}/update`, fd);
        saveBtn.disabled = false;

        if (!ok || !data.ok) {
          const msg = data.errors ? data.errors.join(' ') : (data.error || 'Save failed.');
          showError(errEl, msg);
          return;
        }

        // Update local state
        const updated = data.data;
        const arr = parseInt(updated.is_system) ? systemCats : customCats;
        const idx = arr.findIndex(c => c.id == updated.id);
        if (idx !== -1) arr[idx] = updated;

        // Re-render the row
        const listId = parseInt(updated.is_system) ? 'systemList' : 'customList';
        const newRow = buildRow(updated);
        const oldRow = document.querySelector(`.cat-row[data-id="${updated.id}"]`);
        if (oldRow) oldRow.replaceWith(newRow);

        openEditorId = null;
        toast('Category saved.');
      });

      document.getElementById(`ep-cancel-${cat.id}`).addEventListener('click', () => {
        editor.classList.remove('open');
        openEditorId = null;
      });
    }, 0);

    return editor;
  }

  function showError(el, msg) {
    el.textContent = msg;
    el.style.display = 'block';
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;');
  }

  // ── Toggle inline editor ──────────────────────────────────
  function toggleEditor(catId) {
    const editor = document.getElementById('editor-' + catId);
    if (!editor) return;
    const isOpen = editor.classList.contains('open');

    // Close any open editor
    if (openEditorId && openEditorId !== catId) {
      const prev = document.getElementById('editor-' + openEditorId);
      if (prev) prev.classList.remove('open');
    }

    editor.classList.toggle('open', !isOpen);
    openEditorId = isOpen ? null : catId;
  }

  // ── Actions ───────────────────────────────────────────────

  async function doToggleVisibility(catId, rowEl, btnEl) {
    btnEl.disabled = true;
    const fd = new FormData();
    const { ok, data } = await apiFetch(`/api/categories/${catId}/toggle-visibility`, fd);
    btnEl.disabled = false;

    if (!ok || !data.ok) {
      toast(data.error || 'Failed to update visibility.', 'error');
      return;
    }

    // Update local state
    const arr = findCatArr(catId);
    const cat = arr.find(c => c.id == catId);
    if (cat) cat.is_hidden = data.is_hidden;

    rowEl.classList.toggle('hidden', data.is_hidden === 1);
    btnEl.className = 'cat-action-btn' + (data.is_hidden ? ' active' : '');
    btnEl.title   = data.is_hidden ? 'Show' : 'Hide';
    btnEl.innerHTML = data.is_hidden ? '<i class="ti ti-eye-off"></i>' : '<i class="ti ti-eye"></i>';

    // Update hidden badge in meta
    const meta = rowEl.querySelector('.cat-meta');
    const existingBadge = meta.querySelector('.cat-hidden-badge');
    if (data.is_hidden) {
      if (!existingBadge) {
        const badge = document.createElement('span');
        badge.className = 'cat-hidden-badge';
        badge.textContent = 'Hidden';
        // Insert before order badge
        const orderBadge = meta.querySelector('.cat-order-badge');
        meta.insertBefore(badge, orderBadge);
      }
    } else {
      if (existingBadge) existingBadge.remove();
    }

    toast(data.is_hidden ? 'Category hidden.' : 'Category shown.');
  }

  async function doDelete(catId, rowEl) {
    const cat = findCat(catId);
    const expCount = parseInt(cat?.expense_count) || 0;

    const label = cat ? `"${cat.name}"` : 'this category';
    if (!confirm(`Delete ${label}? This cannot be undone.`)) return;

    const delBtn = rowEl.querySelector('.cat-action-btn.danger');
    if (delBtn) delBtn.disabled = true;

    const fd = new FormData();
    const { ok, status, data } = await apiFetch(`/api/categories/${catId}/delete`, fd);

    if (!ok || !data.ok) {
      if (delBtn) delBtn.disabled = false;
      toast(data.error || 'Delete failed.', 'error');
      return;
    }

    // Remove from state and DOM
    systemCats = systemCats.filter(c => c.id != catId);
    customCats = customCats.filter(c => c.id != catId);
    rowEl.remove();
    checkEmpty();
    toast('Category deleted.');
  }

  // ── Render lists ──────────────────────────────────────────

  function renderList(cats, listId) {
    const listEl = document.getElementById(listId);
    if (!listEl) return;

    // Clear existing rows (keep empty placeholder if present)
    listEl.querySelectorAll('.cat-row').forEach(r => r.remove());

    cats.forEach(cat => {
      listEl.appendChild(buildRow(cat));
    });

    checkEmpty();
  }

  function checkEmpty() {
    ['systemList', 'customList'].forEach(listId => {
      const listEl = document.getElementById(listId);
      if (!listEl) return;
      const hasRows = listEl.querySelectorAll('.cat-row').length > 0;
      const emptyEl = document.getElementById(listId === 'systemList' ? 'systemEmpty' : 'customEmpty');
      if (emptyEl) emptyEl.style.display = hasRows ? 'none' : 'block';
    });
  }

  // ── Drag & drop ───────────────────────────────────────────

  const sortableInstances = {};

  function initSortable(listId, isSystem) {
    const el = document.getElementById(listId);
    if (!el) return;
    // Destroy existing instance to avoid duplicate handlers
    if (sortableInstances[listId]) {
      sortableInstances[listId].destroy();
    }

    sortableInstances[listId] = Sortable.create(el, {
      handle: '.cat-drag',
      animation: 150,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      filter: '.cat-group-empty',
      onEnd: async () => {
        // Collect new order from DOM
        const rows  = el.querySelectorAll('.cat-row[data-id]');
        const items = Array.from(rows).map((r, idx) => ({
          id:         parseInt(r.dataset.id),
          sort_order: idx + 1,
        }));

        // Update local state order badges immediately
        rows.forEach((r, idx) => {
          const badge = r.querySelector('.cat-order-badge');
          if (badge) badge.textContent = '#' + (idx + 1);
        });

        // Persist
        const fd = new FormData();
        fd.append('items', JSON.stringify(items));
        const { ok, data } = await apiFetch('/api/categories/reorder', fd);
        if (!ok || !data.ok) {
          toast('Reorder failed — please refresh.', 'error');
        } else {
          // Sync local state sort_order values
          const arr = isSystem ? systemCats : customCats;
          items.forEach(item => {
            const cat = arr.find(c => c.id === item.id);
            if (cat) cat.sort_order = item.sort_order;
          });
          toast('Order saved.');
        }
      }
    });
  }

  // ── State helpers ─────────────────────────────────────────
  function findCat(id) {
    return systemCats.find(c => c.id == id) || customCats.find(c => c.id == id);
  }
  function findCatArr(id) {
    return systemCats.find(c => c.id == id) ? systemCats : customCats;
  }

  // ── Add form ──────────────────────────────────────────────

  const addToggle = document.getElementById('addToggle');
  const addBody   = document.getElementById('addBody');
  addToggle.addEventListener('click', () => {
    const open = addBody.classList.toggle('open');
    addToggle.classList.toggle('open', open);
  });
  document.getElementById('addCancel').addEventListener('click', () => {
    addBody.classList.remove('open');
    addToggle.classList.remove('open');
  });

  // Mount add-form pickers
  let addIconPicker  = makeIconPicker('addIconWrap',  'ti-wallet', () => {});
  let addColorPicker = makeColorPicker('addColorWrap', '#1D9E75',  () => {});

  document.getElementById('addSubmit').addEventListener('click', async () => {
    const name    = document.getElementById('addName').value.trim();
    const icon    = addIconPicker.getValue();
    const color   = addColorPicker.getValue();
    const errEl   = document.getElementById('addError');
    const isSystem = document.getElementById('addIsSystem');
    const systemVal = isSystem ? (isSystem.checked ? '1' : '0') : '0';

    if (!name) { showError(errEl, 'Name is required.'); return; }
    errEl.style.display = 'none';

    const fd = new FormData();
    fd.append('name',      name);
    fd.append('icon',      icon);
    fd.append('color',     color);
    fd.append('is_system', systemVal);

    const submitBtn = document.getElementById('addSubmit');
    submitBtn.disabled = true;
    const { ok, data } = await apiFetch('/api/categories', fd);
    submitBtn.disabled = false;

    if (!ok || !data.ok) {
      const msg = data.errors ? data.errors.join(' ') : (data.error || 'Failed to create category.');
      showError(errEl, msg);
      return;
    }

    const created = data.data;
    if (parseInt(created.is_system)) {
      systemCats.push(created);
    } else {
      customCats.push(created);
    }
    // Append directly — Sortable picks up new children automatically
    const listId = parseInt(created.is_system) ? 'systemList' : 'customList';
    const listEl = document.getElementById(listId);
    if (listEl) {
      const newRow = buildRow(created);
      listEl.appendChild(newRow);
      checkEmpty();
    }

    // Reset form
    document.getElementById('addName').value = '';
    addIconPicker.setValue('ti-wallet');
    addColorPicker.setValue('#1D9E75');
    if (isSystem) isSystem.checked = false;

    addBody.classList.remove('open');
    addToggle.classList.remove('open');
    toast('Category created.');
  });

  // ── Boot ──────────────────────────────────────────────────
  renderList(systemCats, 'systemList');
  renderList(customCats, 'customList');
  initSortable('systemList', true);
  initSortable('customList', false);

})();
</script>
