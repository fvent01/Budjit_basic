<?php
// app/controllers/CategoryController.php

class CategoryController extends Controller
{
    private CategoryModel $model;

    // Tabler icon identifiers allowed for categories (40 options presented in the picker).
    // Backend validates against this whitelist so arbitrary CSS classes cannot be injected.
    private const ALLOWED_ICONS = [
        'ti-wallet',           'ti-home',             'ti-car',
        'ti-shopping-cart',    'ti-tools',            'ti-heart',
        'ti-medical-cross',    'ti-shield',           'ti-bolt',
        'ti-building',         'ti-gas-station',      'ti-plane',
        'ti-device-gamepad-2', 'ti-piggy-bank',       'ti-baby-carriage',
        'ti-dots',             'ti-cash',             'ti-gift',
        'ti-dog',              'ti-shirt',            'ti-school',
        'ti-coffee',           'ti-music',            'ti-dumbbell',
        'ti-book',             'ti-phone',            'ti-bus',
        'ti-scissors',         'ti-camera',           'ti-chart-bar',
        'ti-tree',             'ti-sun',              'ti-moon',
        'ti-star',             'ti-trophy',           'ti-crown',
        'ti-diamond',          'ti-flame',            'ti-leaf',
        'ti-brightness',
    ];

    public function __construct()
    {
        $this->model = new CategoryModel();
    }

    // ── Page view ─────────────────────────────────────────────

    /**
     * GET /settings/categories
     */
    public function index(): void
    {
        Auth::requireLogin();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $categories = $isAdmin
            ? $this->model->getAll()
            : $this->model->getAllForUser($userId);

        $system  = array_values(array_filter($categories, fn($c) => (int) $c['is_system'] === 1));
        $custom  = array_values(array_filter($categories, fn($c) => (int) $c['is_system'] === 0));
        $icons   = self::ALLOWED_ICONS;

        $this->view('settings.categories', compact('system', 'custom', 'isAdmin', 'icons'));
    }

    // ── API: list ─────────────────────────────────────────────

    /**
     * GET /api/categories
     */
    public function list(): void
    {
        Auth::requireLogin();

        $userId     = Auth::id();
        $isAdmin    = Auth::isAdmin();
        $categories = $isAdmin
            ? $this->model->getAll()
            : $this->model->getAllForUser($userId);

        $this->json(['ok' => true, 'data' => $categories]);
    }

    // ── API: create ───────────────────────────────────────────

    /**
     * POST /api/categories
     */
    public function store(): void
    {
        Auth::requireLogin();
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $name     = $this->sanitize($this->input('name', ''));
        $icon     = $this->sanitize($this->input('icon', 'ti-wallet'));
        $color    = trim($this->input('color', '#1D9E75'));
        $isSystem = $isAdmin && filter_var($this->input('is_system', '0'), FILTER_VALIDATE_BOOLEAN);

        $errors = $this->validateFields($name, $icon, $color);
        if ($isSystem && !$isAdmin) {
            $errors[] = 'Only admins can create system categories.';
        }

        if ($errors) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }

        $sortOrder = $this->model->getNextSortOrder($isSystem);
        $id = $this->model->insert([
            'user_id'    => $isSystem ? null : $userId,
            'name'       => $name,
            'icon'       => $icon,
            'color'      => strtolower($color),
            'sort_order' => $sortOrder,
            'is_active'  => 1,
            'is_system'  => (int) $isSystem,
            'is_hidden'  => 0,
        ]);

        $category                  = $this->model->find($id);
        $category['expense_count'] = 0;

        $this->json(['ok' => true, 'data' => $category], 201);
    }

    // ── API: update ───────────────────────────────────────────

    /**
     * POST /api/categories/{id}/update
     */
    public function update(int $id): void
    {
        Auth::requireLogin();
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $category = $this->model->find($id);
        if (!$category) {
            $this->json(['ok' => false, 'error' => 'Category not found.'], 404);
            return;
        }

        if (!$this->model->canEdit($category, $userId, $isAdmin)) {
            $this->json(['ok' => false, 'error' => 'Permission denied.'], 403);
            return;
        }

        $name      = $this->sanitize($this->input('name',       $category['name']));
        $icon      = $this->sanitize($this->input('icon',       $category['icon']));
        $color     = trim($this->input('color',                 $category['color']));
        $sortOrder = (int) $this->input('sort_order',           $category['sort_order']);

        $errors = $this->validateFields($name, $icon, $color);
        if ($errors) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }

        $this->model->update($id, [
            'name'       => $name,
            'icon'       => $icon,
            'color'      => strtolower($color),
            'sort_order' => $sortOrder,
        ]);

        $updated                  = $this->model->find($id);
        $updated['expense_count'] = $this->model->getExpenseCount($id);

        $this->json(['ok' => true, 'data' => $updated]);
    }

    // ── API: delete ───────────────────────────────────────────

    /**
     * POST /api/categories/{id}/delete
     */
    public function destroy(int $id): void
    {
        Auth::requireLogin();
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $category = $this->model->find($id);
        if (!$category) {
            $this->json(['ok' => false, 'error' => 'Category not found.'], 404);
            return;
        }

        if (!$this->model->canEdit($category, $userId, $isAdmin)) {
            $this->json(['ok' => false, 'error' => 'Permission denied.'], 403);
            return;
        }

        $check = $this->model->isDeletable($id);
        if (!$check['ok']) {
            $this->json(['ok' => false, 'error' => $check['message']], 409);
            return;
        }

        $this->model->delete($id);
        $this->json(['ok' => true]);
    }

    // ── API: toggle visibility ────────────────────────────────

    /**
     * POST /api/categories/{id}/toggle-visibility
     */
    public function toggleVisibility(int $id): void
    {
        Auth::requireLogin();
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $category = $this->model->find($id);
        if (!$category) {
            $this->json(['ok' => false, 'error' => 'Category not found.'], 404);
            return;
        }

        if (!$this->model->canEdit($category, $userId, $isAdmin)) {
            $this->json(['ok' => false, 'error' => 'Permission denied.'], 403);
            return;
        }

        $newHidden = (int) $category['is_hidden'] === 0 ? 1 : 0;
        $this->model->update($id, ['is_hidden' => $newHidden]);

        $this->json(['ok' => true, 'is_hidden' => $newHidden]);
    }

    // ── API: reorder ──────────────────────────────────────────

    /**
     * POST /api/categories/reorder
     *
     * Body: items = JSON string of [{id, sort_order}, ...]
     */
    public function reorder(): void
    {
        Auth::requireLogin();
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId  = Auth::id();
        $isAdmin = Auth::isAdmin();

        $raw   = $this->input('items', '[]');
        $items = json_decode($raw, true);

        if (!is_array($items)) {
            $this->json(['ok' => false, 'error' => 'Invalid reorder data.'], 422);
            return;
        }

        // Filter: only items the user owns
        $permitted = [];
        foreach ($items as $item) {
            $id    = (int) ($item['id']         ?? 0);
            $order = (int) ($item['sort_order'] ?? 0);
            if ($id <= 0) continue;

            $category = $this->model->find($id);
            if (!$category) continue;
            if (!$this->model->canEdit($category, $userId, $isAdmin)) continue;

            $permitted[] = ['id' => $id, 'sort_order' => $order];
        }

        if (!empty($permitted)) {
            $this->model->applyReorder($permitted);
        }

        $this->json(['ok' => true]);
    }

    // ── Validation helper ─────────────────────────────────────

    /**
     * @return string[]  Array of error messages; empty means valid.
     */
    private function validateFields(string $name, string $icon, string $color): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors[] = 'Color must be a valid 6-digit HEX value (e.g. #1D9E75).';
        }
        if (!in_array($icon, self::ALLOWED_ICONS, true)) {
            $errors[] = 'Invalid icon selection.';
        }
        return $errors;
    }
}
