<?php
// config/plugins.php
// Master list of available plugins and their default state.
// The DB table `plugins` takes precedence — this is the fallback/install manifest.

return [
    'savings-goals' => [
        'name'        => 'Savings Goals',
        'description' => 'Track progress toward financial goals with manual or automatic contributions.',
        'version'     => '1.0.0',
        'icon'        => 'ti-piggy-bank',
        'color'       => '#1D9E75',
        'url'         => 'savings-goals',
        'enabled'     => true,
    ],
    'debt-tracker' => [
        'name'        => 'Debt Payoff Tracker',
        'description' => 'Snowball strategy debt tracker — smallest balance first.',
        'version'     => '1.0.0',
        'icon'        => 'ti-credit-card-off',
        'color'       => '#E24B4A',
        'url'         => 'debt-tracker',
        'enabled'     => true,
    ],
    'recurring-bills' => [
        'name'        => 'Recurring Bills & Subscriptions',
        'description' => 'Track all recurring bills and subscriptions in one place.',
        'version'     => '1.0.0',
        'icon'        => 'ti-refresh',
        'color'       => '#378ADD',
        'url'         => 'recurring-bills',
        'enabled'     => true,
    ],
    'calendar-reminders' => [
        'name'        => 'Calendar & Bill Reminders',
        'description' => 'Monthly calendar view with in-app, email, and push notifications.',
        'version'     => '1.0.0',
        'icon'        => 'ti-calendar-event',
        'color'       => '#EF9F27',
        'url'         => 'calendar',
        'enabled'     => true,
    ],
    'financial-import' => [
        'name'        => 'Financial Import',
        'description' => 'Unified import: Plaid bank sync, CSV, and Excel uploads with deduplication.',
        'version'     => '1.0.0',
        'icon'        => 'ti-arrows-exchange',
        'color'       => '#7F77DD',
        'url'         => 'import',
        'enabled'     => true,
    ],
    'bank-import' => [
        'name'        => 'Bank Import',
        'description' => 'DEPRECATED — replaced by financial-import.',
        'version'     => '1.0.0',
        'icon'        => 'ti-building-bank',
        'color'       => '#7F77DD',
        'enabled'     => false,
    ],
    'excel-budget-import' => [
        'name'        => 'Excel Budget Import',
        'description' => 'DEPRECATED — replaced by financial-import.',
        'version'     => '1.0.0',
        'icon'        => 'ti-file-spreadsheet',
        'color'       => '#1D9E75',
        'enabled'     => false,
    ],
];
