<?php
// plugins/savings-goals/plugin.php

defined('BUDJIT') or die;

// Register routes
$router->get( '/savings-goals',                    'SavingsGoalsController@index');
$router->get( '/savings-goals/create',             'SavingsGoalsController@create');
$router->post('/savings-goals',                    'SavingsGoalsController@store');
$router->get( '/savings-goals/{id}/edit',          'SavingsGoalsController@edit');
$router->post('/savings-goals/{id}/update',        'SavingsGoalsController@update');
$router->post('/savings-goals/{id}/delete',        'SavingsGoalsController@delete');
$router->post('/savings-goals/{id}/contribute',    'SavingsGoalsController@contribute');
$router->post('/savings-goals/reorder',            'SavingsGoalsController@reorder');

// Dashboard widget
PluginLoader::register('dashboard_widgets', function() {
    ob_start();
    require PLUGIN_PATH . '/savings-goals/views/widget.php';
    return ob_get_clean();
});

// Sidebar nav item
PluginLoader::register('nav_items', function($items) {
    $items[] = [
        'label' => 'Savings Goals',
        'url'   => BASE_URL . '/savings-goals',
        'icon'  => 'ti-piggy-bank',
        'match' => 'savings-goals',
    ];
    return $items;
});
