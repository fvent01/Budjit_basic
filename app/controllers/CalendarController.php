<?php
// app/controllers/CalendarController.php

class CalendarController extends Controller
{
    private ReminderModel $reminders;

    public function __construct()
    {
        $this->reminders = new ReminderModel();
    }

    // ── GET /calendar ─────────────────────────────────────────
    public function index(): void
    {
        Auth::requireLogin();
        $year  = (int)date('Y');
        $month = (int)date('m');
        $this->renderCalendar($year, $month);
    }

    // ── GET /calendar/{year}/{month} ──────────────────────────
    public function month(string $year, string $month): void
    {
        Auth::requireLogin();
        $year  = max(2020, min(2035, (int)$year));
        $month = max(1,    min(12,   (int)$month));
        $this->renderCalendar($year, $month);
    }

    private function renderCalendar(int $year, int $month): void
    {
        $userId = Auth::id();

        // Auto-sync reminders from bills
        $this->reminders->syncFromBills($userId);

        $events    = $this->reminders->getMonthEvents($userId, $year, $month);
        $reminders = $this->reminders->getForUser($userId);

        // Group events by date for the calendar grid
        $byDate = [];
        foreach ($events as $ev) {
            $byDate[$ev['event_date']][] = $ev;
        }

        // Calendar metadata
        $firstDay    = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDay);
        $startDow    = (int)date('N', $firstDay); // 1=Mon … 7=Sun
        $monthName   = date('F Y', $firstDay);

        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear  = $month === 1 ? $year - 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextYear  = $month === 12 ? $year + 1 : $year;

        $this->view('calendar.index', compact(
            'year', 'month', 'monthName', 'daysInMonth', 'startDow',
            'byDate', 'reminders', 'prevMonth', 'prevYear', 'nextMonth', 'nextYear'
        ));
    }

    // ── GET /reminders ────────────────────────────────────────
    public function reminders(): void
    {
        Auth::requireLogin();
        $reminders = $this->reminders->getForUser(Auth::id(), true);
        $this->view('calendar.reminders', compact('reminders'));
    }

    // ── POST /reminders ───────────────────────────────────────
    public function storeReminder(): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();

        $userId   = Auth::id();
        $title    = $this->sanitize($this->input('title', ''));
        $date     = $this->input('remind_date', '');
        $channels = $_POST['channels'] ?? ['inapp'];

        if (!$title || !$date) {
            $this->flashError('Title and date are required.');
            $this->redirect('calendar');
            return;
        }

        $validChannels = array_intersect($channels, ['inapp', 'email', 'push']);
        $channelStr    = implode(',', $validChannels ?: ['inapp']);

        $this->reminders->insert([
            'user_id'            => $userId,
            'title'              => $title,
            'reminder_type'      => 'custom',
            'remind_date'        => $date,
            'remind_days_before' => (int)$this->input('remind_days_before', 3),
            'channels'           => $channelStr,
            'notes'              => $this->sanitize($this->input('notes', '')),
        ]);

        $this->flashSuccess('Reminder set for ' . date('M j, Y', strtotime($date)) . '.');
        $this->redirect('calendar');
    }

    // ── POST /reminders/{id}/dismiss ──────────────────────────
    public function dismiss(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $r = $this->ownedReminder((int)$id);
        $this->reminders->dismiss($r['id']);
        $this->json(['ok' => true]);
    }

    // ── POST /reminders/{id}/delete ───────────────────────────
    public function deleteReminder(string $id): void
    {
        Auth::requireWriteAccess();
        Auth::verifyCsrf();
        $r = $this->ownedReminder((int)$id);
        $this->reminders->delete($r['id']);
        $this->flashSuccess('Reminder deleted.');
        $this->back();
    }

    // ── POST /reminders/push-subscribe ───────────────────────
    public function pushSubscribe(): void
    {
        Auth::requireLogin();
        Auth::verifyCsrf();

        $payload  = json_decode(file_get_contents('php://input'), true);
        $endpoint = $payload['endpoint']          ?? '';
        $p256dh   = $payload['keys']['p256dh']    ?? '';
        $auth     = $payload['keys']['auth']       ?? '';

        if (!$endpoint || !$p256dh || !$auth) {
            $this->json(['error' => 'Invalid subscription data'], 400);
            return;
        }

        $db = Database::getInstance();
        $db->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE p256dh_key = VALUES(p256dh_key), auth_key = VALUES(auth_key)"
        )->execute([Auth::id(), $endpoint, $p256dh, $auth]);

        $this->json(['ok' => true]);
    }

    private function ownedReminder(int $id): array
    {
        $r = $this->reminders->find($id);
        if (!$r || $r['user_id'] !== Auth::id()) { http_response_code(403); die(); }
        return $r;
    }
}
