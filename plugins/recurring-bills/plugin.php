<?php
// plugins/recurring-bills/plugin.php
defined('BUDJIT') or die;

$router->get( '/recurring-bills',                 'RecurringBillsController@index');
$router->get( '/recurring-bills/create',          'RecurringBillsController@create');
$router->post('/recurring-bills',                 'RecurringBillsController@store');
$router->get( '/recurring-bills/{id}/edit',       'RecurringBillsController@edit');
$router->post('/recurring-bills/{id}/update',     'RecurringBillsController@update');
$router->post('/recurring-bills/{id}/delete',     'RecurringBillsController@delete');
$router->post('/recurring-bills/{id}/pay',        'RecurringBillsController@markPaid');

PluginLoader::register('dashboard_widgets', function() {
    ob_start();
    require PLUGIN_PATH . '/recurring-bills/views/widget.php';
    return ob_get_clean();
});

PluginLoader::register('nav_items', function($items) {
    $items[] = ['label' => 'Recurring Bills', 'url' => BASE_URL . '/recurring-bills', 'icon' => 'ti-refresh', 'match' => 'recurring-bills'];
    return $items;
});
