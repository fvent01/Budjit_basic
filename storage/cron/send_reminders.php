#!/usr/bin/env php
<?php
// storage/cron/send_reminders.php
// Run daily: 0 8 * * * php /path/to/budjit/storage/cron/send_reminders.php

define('BUDJIT', true);
require_once dirname(__DIR__, 2) . '/config/config.php';
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;
require_once CORE_PATH . '/database/Database.php';
require_once CORE_PATH . '/database/Model.php';
require_once CORE_PATH . '/auth/Auth.php';
require_once CORE_PATH . '/router/Controller.php';
require_once CORE_PATH . '/router/Router.php';
require_once CORE_PATH . '/plugins/PluginLoader.php';

spl_autoload_register(function (string $class): void {
    foreach ([APP_PATH . '/models/', APP_PATH . '/controllers/'] as $dir) {
        $f = $dir . $class . '.php';
        if (file_exists($f)) { require_once $f; return; }
    }
});

$model   = new ReminderModel();
$pending = $model->getPendingToSend();

foreach ($pending as $reminder) {
    $channels = explode(',', $reminder['channels']);
    $sent     = false;

    // ── In-app: already shown in UI, just mark sent ───────────
    if (in_array('inapp', $channels)) {
        $sent = true;
    }

    // ── Email ─────────────────────────────────────────────────
    if (in_array('email', $channels)) {
        $to      = $reminder['email'];
        $subject = 'Budjit Reminder: ' . $reminder['title'];
        $body    = "Hi {$reminder['first_name']},\n\n"
                 . "This is a reminder: {$reminder['title']}\n"
                 . "Date: " . date('F j, Y', strtotime($reminder['remind_date'])) . "\n\n"
                 . ($reminder['notes'] ? "Note: {$reminder['notes']}\n\n" : '')
                 . "Login to Budjit: " . BASE_URL . "\n";
        $headers = "From: " . APP_NAME . " <noreply@budjit.local>\r\n";

        if (mail($to, $subject, $body, $headers)) {
            $sent = true;
            echo "[email] Sent to {$to}: {$reminder['title']}\n";
        } else {
            echo "[email] FAILED to {$to}: {$reminder['title']}\n";
        }
    }

    // ── Browser push (VAPID-signed via minishlink/web-push) ──────
    if (in_array('push', $channels) && class_exists(\Minishlink\WebPush\WebPush::class)) {
        $db   = Database::getInstance();
        $subs = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $subs->execute([$reminder['user_id']]);
        $rows = $subs->fetchAll();

        if ($rows) {
            $auth = [
                'VAPID' => [
                    'subject'    => VAPID_SUBJECT,
                    'publicKey'  => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ];
            $webPush = new \Minishlink\WebPush\WebPush($auth);

            $payload = json_encode([
                'title' => 'Budjit: ' . $reminder['title'],
                'body'  => $reminder['notes'] ?: 'Tap to view in Budjit.',
                'url'   => BASE_URL . '/calendar',
            ]);

            foreach ($rows as $sub) {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint'        => $sub['endpoint'],
                    'keys'            => [
                        'p256dh' => $sub['p256dh_key'],
                        'auth'   => $sub['auth_key'],
                    ],
                ]);
                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if ($report->isSuccess()) {
                    $sent = true;
                    echo "[push] Sent push for: {$reminder['title']}\n";
                } else {
                    echo "[push] FAILED ({$report->getReason()}): {$reminder['title']}\n";
                    // Remove expired subscriptions
                    if ($report->isSubscriptionExpired()) {
                        $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                           ->execute([$endpoint]);
                    }
                }
            }
        }
    } elseif (in_array('push', $channels)) {
        echo "[push] Skipped — run `composer install` to enable push sending.\n";
    }

    if ($sent) {
        $model->markSent($reminder['id']);
    }
}

echo "Done. Processed " . count($pending) . " reminder(s).\n";
