<?php
// plugins/financial-import/plugin.php
// Unified financial import plugin — replaces bank-import and excel-budget-import.
defined('BUDJIT') or die;

// ── Routes ────────────────────────────────────────────────────
$router->get( '/import',                        'FinancialImportController@index');
$router->get( '/import/review',                 'FinancialImportController@review');
$router->get( '/import/history',                'FinancialImportController@history');
$router->post('/import/plaid/link-token',       'FinancialImportController@plaidLinkToken');
$router->post('/import/plaid/connect',          'FinancialImportController@plaidConnect');
$router->post('/import/plaid/sync',             'FinancialImportController@plaidSync');
$router->post('/import/plaid/disconnect',       'FinancialImportController@plaidDisconnect');
$router->post('/import/upload',                 'FinancialImportController@upload');
$router->post('/import/confirm',                'FinancialImportController@confirm');
$router->post('/import/skip-all',               'FinancialImportController@skipAll');

// ── Navigation ────────────────────────────────────────────────
PluginLoader::register('nav_items', function($items) {
    $items[] = [
        'label' => 'Import',
        'url'   => BASE_URL . '/import',
        'icon'  => 'ti-file-import',
        'match' => 'import',
    ];
    return $items;
});

// ── Dashboard widget ──────────────────────────────────────────
PluginLoader::register('dashboard_widgets', function() {
    ob_start();
    require PLUGIN_PATH . '/financial-import/views/widget.php';
    return ob_get_clean();
});
