<?php
// ============================================================
//  Budjit — Front Controller
// ============================================================

define('BUDJIT', true);

require_once dirname(__DIR__) . '/config/config.php';

// ── Composer autoloader ───────────────────────────────────────
$_vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($_vendorAutoload)) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/install.php');
    exit;
}
require_once $_vendorAutoload;
unset($_vendorAutoload);

// Core classes
require_once CORE_PATH . '/database/Database.php';
require_once CORE_PATH . '/database/Model.php';
require_once CORE_PATH . '/auth/Auth.php';
require_once CORE_PATH . '/router/Controller.php';
require_once CORE_PATH . '/router/Router.php';
require_once CORE_PATH . '/plugins/PluginLoader.php';

// Autoloader — finds models and controllers by class name
spl_autoload_register(function (string $class): void {
    $search = [
        APP_PATH  . '/models/'      . $class . '.php',
        APP_PATH  . '/controllers/' . $class . '.php',
        CORE_PATH . '/plugins/'     . $class . '.php',
    ];
    foreach ($search as $file) {
        if (file_exists($file)) { require_once $file; return; }
    }
});

Auth::startSession();

$router = new Router();

// ── Auth ──────────────────────────────────────────────────────
$router->get( '/auth/login',    'AuthController@loginForm');
$router->post('/auth/login',    'AuthController@login');
$router->get( '/auth/register', 'AuthController@registerForm');
$router->post('/auth/register', 'AuthController@register');
$router->get( '/auth/logout',   'AuthController@logout');

// ── Core pages ────────────────────────────────────────────────
$router->get('/',          'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');

$router->get( '/budgets',                'BudgetController@index');
$router->get( '/budgets/create',         'BudgetController@create');
$router->post('/budgets',                'BudgetController@store');
$router->get( '/budgets/{id}',           'BudgetController@show');
$router->get( '/budgets/{id}/edit',      'BudgetController@edit');
$router->post('/budgets/{id}/update',    'BudgetController@update');
$router->post('/budgets/{id}/delete',    'BudgetController@delete');
$router->post('/budgets/{id}/archive',   'BudgetController@archive');
$router->post('/budgets/{id}/duplicate', 'BudgetController@duplicate');

$router->get( '/expenses',             'ExpenseController@index');
$router->get( '/expenses/create',      'ExpenseController@create');
$router->post('/expenses',             'ExpenseController@store');
$router->get( '/expenses/{id}/edit',   'ExpenseController@edit');
$router->post('/expenses/{id}/update', 'ExpenseController@update');
$router->post('/expenses/{id}/delete', 'ExpenseController@delete');
$router->post('/expenses/{id}/pay',    'ExpenseController@markPaid');

$router->get( '/income',               'IncomeController@index');
$router->get( '/income/create',        'IncomeController@create');
$router->post('/income',               'IncomeController@store');
$router->get( '/income/{id}/edit',     'IncomeController@edit');
$router->post('/income/{id}/update',   'IncomeController@update');
$router->post('/income/{id}/delete',   'IncomeController@delete');
$router->post('/income/sources',       'IncomeController@storeSource');

// ── Financial Import (unified — replaces bank-import + excel-budget-import) ───
// Routes are registered by the financial-import plugin via PluginLoader::boot().
// Legacy /bank-import and /excel-import paths redirect to the new /import endpoint.
$router->get( '/bank-import',  'FinancialImportController@index');
$router->get( '/excel-import', 'FinancialImportController@index');

// ── Analytics
$router->get('/analytics',        'AnalyticsController@index');
$router->get('/analytics/export',  'AnalyticsController@export');

// ── Settings
$router->get( '/settings',                         'SettingsController@index');
$router->get( '/settings/general',                 'SettingsController@general');
$router->post('/settings/general',                 'SettingsController@saveGeneral');
$router->get( '/settings/appearance',              'SettingsController@appearance');
$router->post('/settings/appearance',              'SettingsController@saveAppearance');
$router->get( '/settings/profile',                 'SettingsController@profile');
$router->post('/settings/profile',                 'SettingsController@saveProfile');
$router->post('/settings/password',                'SettingsController@savePassword');
$router->get( '/settings/users',                   'SettingsController@users');
$router->post('/settings/users/create',            'SettingsController@createUser');
$router->post('/settings/users/{id}/update',       'SettingsController@updateUser');
$router->post('/settings/users/{id}/delete',       'SettingsController@deleteUser');
$router->post('/settings/users/toggle',            'SettingsController@toggleUser');
$router->post('/settings/users/role',              'SettingsController@changeRole');

// ── Categories
$router->get( '/settings/categories',                         'CategoryController@index');
$router->get( '/api/categories',                              'CategoryController@list');
$router->post('/api/categories',                              'CategoryController@store');
$router->post('/api/categories/reorder',                      'CategoryController@reorder');
$router->post('/api/categories/{id}/update',                  'CategoryController@update');
$router->post('/api/categories/{id}/delete',                  'CategoryController@destroy');
$router->post('/api/categories/{id}/toggle-visibility',       'CategoryController@toggleVisibility');

// ── Plugin admin ──────────────────────────────────────────────
$router->get( '/plugins',        'PluginController@index');
$router->post('/plugins/toggle', 'PluginController@toggle');

// ── Boot plugins (registers their routes + hooks) ─────────────
PluginLoader::boot($router);

// ── Dispatch ──────────────────────────────────────────────────
$router->dispatch();
