<?php
// plugins/calendar-reminders/plugin.php
defined('BUDJIT') or die;

$router->get( '/calendar',                         'CalendarController@index');
$router->get( '/calendar/{year}/{month}',          'CalendarController@month');
$router->get( '/reminders',                        'CalendarController@reminders');
$router->post('/reminders',                        'CalendarController@storeReminder');
$router->post('/reminders/{id}/dismiss',           'CalendarController@dismiss');
$router->post('/reminders/{id}/delete',            'CalendarController@deleteReminder');
$router->post('/reminders/push-subscribe',         'CalendarController@pushSubscribe');

PluginLoader::register('dashboard_widgets', function() {
    ob_start();
    require PLUGIN_PATH . '/calendar-reminders/views/widget.php';
    return ob_get_clean();
});

PluginLoader::register('nav_items', function($items) {
    $items[] = ['label' => 'Calendar', 'url' => BASE_URL . '/calendar', 'icon' => 'ti-calendar-event', 'match' => 'calendar'];
    return $items;
});
