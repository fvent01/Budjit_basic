<?php
// app/models/SettingsModel.php

class SettingsModel extends Model
{
    protected string $table = 'settings';

    private static array $cache = [];

    // ── Global settings ───────────────────────────────────────

    public function getAll(): array
    {
        if (!empty(self::$cache)) return self::$cache;
        $rows = $this->query("SELECT setting_key, value FROM settings")->fetchAll();
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = $row['value'];
        }
        return self::$cache;
    }

    public function get(string $key, string $default = ''): string
    {
        $all = $this->getAll();
        return $all[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->query(
            "INSERT INTO settings (setting_key, value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$key, $value]
        );
        self::$cache[$key] = $value;
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, (string)$value);
        }
    }

    // ── User preferences ──────────────────────────────────────

    public function getUserPref(int $userId, string $key, string $default = ''): string
    {
        $row = $this->query(
            "SELECT value FROM user_preferences WHERE user_id = ? AND pref_key = ?",
            [$userId, $key]
        )->fetch();
        return $row ? $row['value'] : $default;
    }

    public function setUserPref(int $userId, string $key, string $value): void
    {
        $this->query(
            "INSERT INTO user_preferences (user_id, pref_key, value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$userId, $key, $value]
        );
    }

    public function getAllUserPrefs(int $userId): array
    {
        $rows = $this->query(
            "SELECT pref_key, value FROM user_preferences WHERE user_id = ?",
            [$userId]
        )->fetchAll();
        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['pref_key']] = $row['value'];
        }
        return $prefs;
    }

    // ── Helpers ───────────────────────────────────────────────

    public function getThemeForUser(int $userId): string
    {
        $userTheme = $this->getUserPref($userId, 'theme', '');
        if ($userTheme) return $userTheme;
        return $this->get('theme', 'system');
    }

    public function getTimezones(): array
    {
        return [
            'America/New_York'    => 'Eastern Time (ET)',
            'America/Chicago'     => 'Central Time (CT)',
            'America/Denver'      => 'Mountain Time (MT)',
            'America/Phoenix'     => 'Arizona (no DST)',
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'America/Anchorage'   => 'Alaska Time (AKT)',
            'Pacific/Honolulu'    => 'Hawaii Time (HT)',
            'UTC'                 => 'UTC',
        ];
    }

    public function getDateFormats(): array
    {
        $now = date_create();
        $formats = [
            'M j, Y'   => 'Jan 5, 2026',
            'F j, Y'   => 'January 5, 2026',
            'm/d/Y'    => '01/05/2026',
            'd/m/Y'    => '05/01/2026',
            'Y-m-d'    => '2026-01-05',
            'd M Y'    => '05 Jan 2026',
        ];
        // Show actual current date in each format
        $result = [];
        foreach ($formats as $fmt => $example) {
            $result[$fmt] = date($fmt) . ' (' . $example . ')';
        }
        return $result;
    }

    public function getCurrencies(): array
    {
        return [
            'USD' => ['symbol' => '$',  'name' => 'US Dollar'],
            'EUR' => ['symbol' => '€',  'name' => 'Euro'],
            'GBP' => ['symbol' => '£',  'name' => 'British Pound'],
            'CAD' => ['symbol' => 'CA$','name' => 'Canadian Dollar'],
            'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar'],
            'ZAR' => ['symbol' => 'R',  'name' => 'South African Rand'],
        ];
    }

    public function getMonths(): array
    {
        return [
            '01' => 'January', '02' => 'February', '03' => 'March',
            '04' => 'April',   '05' => 'May',       '06' => 'June',
            '07' => 'July',    '08' => 'August',    '09' => 'September',
            '10' => 'October', '11' => 'November',  '12' => 'December',
        ];
    }
}