<?php
// plugins/debt-tracker/plugin.php
defined('BUDJIT') or die;

$router->get( '/debt-tracker',               'DebtTrackerController@index');
$router->get( '/debt-tracker/create',        'DebtTrackerController@create');
$router->post('/debt-tracker',               'DebtTrackerController@store');
$router->get( '/debt-tracker/{id}/edit',     'DebtTrackerController@edit');
$router->post('/debt-tracker/{id}/update',   'DebtTrackerController@update');
$router->post('/debt-tracker/{id}/delete',   'DebtTrackerController@delete');
$router->post('/debt-tracker/{id}/payment',  'DebtTrackerController@addPayment');

PluginLoader::register('dashboard_widgets', function() {
    ob_start();
    require PLUGIN_PATH . '/debt-tracker/views/widget.php';
    return ob_get_clean();
});

PluginLoader::register('nav_items', function($items) {
    $items[] = ['label' => 'Debt Tracker', 'url' => BASE_URL . '/debt-tracker', 'icon' => 'ti-credit-card-off', 'match' => 'debt-tracker'];
    return $items;
});
