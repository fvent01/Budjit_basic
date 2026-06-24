# 💰 Budjit

> **Take control of your family's finances — together.**

Budjit is a self-hosted, full-featured family budgeting application built on PHP + MySQL. Track income and expenses, set budgets, monitor savings goals, manage debt, and import real bank data — all from a clean, private dashboard that lives on *your* server.

---

## ✨ Features

| Category | What You Get |
|---|---|
| 📊 **Dashboard** | Customizable widget layout — drag, drop, done |
| 💸 **Budgets** | Monthly budget envelopes with live progress tracking |
| 🧾 **Expenses** | Log and categorize every purchase |
| 💵 **Income** | Track multiple income sources across family members |
| 🏦 **Bank Import** | Connect via Plaid for automatic transaction sync |
| 📈 **Analytics** | Spending trends, category breakdowns, month-over-month |
| 🎯 **Savings Goals** | Visual progress toward what matters most |
| 🏋️ **Debt Tracker** | Payoff planning with interest calculations |
| 🔁 **Recurring Bills** | Never miss a subscription or utility payment |
| 🗓️ **Reminders** | Calendar-based financial alerts |
| 📥 **Excel Import** | Bulk-import transactions from spreadsheets |
| 📄 **Pay Stub Parser** | Auto-extract income from PDF pay stubs |

---

## 🚀 Quick Start

### Requirements

- **XAMPP** (or any LAMP stack) with:
  - PHP 8.1+
  - MySQL 8 / MariaDB 10.4+
  - Apache with `mod_rewrite` enabled
- **Composer** — [getcomposer.org](https://getcomposer.org/download/) (one-click Windows installer)
- **PHP extensions:** `openssl`, `curl`, `mbstring`, `json`, `gmp` (all included in standard XAMPP)

---

### 1 · Copy the files

Drop the project into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\Budjit\
```

---

### 2 · Create the database

1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create a new database named **`Budjit`**
   - Collation: `utf8mb4_unicode_ci`
3. Go to the **Import** tab → select `config/schema.sql` → click **Go**

---

### 3 · Configure the app

Edit `config/config.php` with your local settings:

```php
define('BASE_URL', 'http://localhost/Budjit/public');
define('DB_HOST',  'localhost');
define('DB_NAME',  'Budjit');
define('DB_USER',  'root');
define('DB_PASS',  '');   // blank for XAMPP default
```

---

### 4 · Install PHP dependencies

Budjit uses [Composer](https://getcomposer.org) to manage PHP libraries (web-push encryption, etc.).

**Option A — Web installer (recommended):**

Navigate to: **http://localhost/Budjit/public/install.php**

The installer runs pre-flight checks and installs all dependencies with one click. It redirects you to the app when done.

**Option B — Command line:**

```bash
cd C:\\xampp\\htdocs\\Budjit
composer install
```

> If `composer` is not on your PATH, download `composer.phar` from [getcomposer.org](https://getcomposer.org/download/), place it in the project root, and the web installer will detect it automatically.

---

### 5 · Enable mod_rewrite

Open `C:\xampp\apache\conf\httpd.conf` and ensure this line is **uncommented**:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Find the `<Directory "C:/xampp/htdocs">` block and set:

```apache
AllowOverride All
```

Then **restart Apache** from the XAMPP Control Panel.

---

### 6 · Launch 🎉

Navigate to: **http://localhost/Budjit/public**

You'll land on the login screen. Click **"Create account"** to register the first admin user.

---

### Keeping dependencies up to date

After pulling updates, visit **http://localhost/Budjit/public/update.php** to:

- Re-run `composer install` if new packages were added
- Check for missing config constants (VAPID keys, etc.)
- Verify storage directories and database connectivity
- See a full health report of the installation

---

## 🗂️ Project Structure

```
Budjit/
├── app/
│   ├── controllers/       AuthController, DashboardController, BudgetController,
│   │                      ExpenseController, IncomeController, ErrorController
│   ├── models/            UserModel, BudgetModel, BudgetItemModel, CategoryModel,
│   │                      IncomeSourceModel, IncomeEntryModel, ExpenseModel
│   └── views/             auth/, budget/, expenses/, income/, dashboard/, layouts/
│
├── config/
│   ├── config.php         ← your DB credentials and environment settings
│   └── schema.sql         ← import this to initialize the database
│
├── core/
│   ├── auth/Auth.php
│   ├── database/          Database.php, Model.php
│   ├── router/            Router.php, Controller.php
│   └── plugins/           PluginLoader.php
│
├── plugins/               ← self-contained feature modules live here
│
├── public/
│   ├── index.php          ← front controller (all HTTP requests enter here)
│   ├── install.php        ← first-run web installer (auto-redirected if vendor/ missing)
│   ├── update.php         ← post-install health checker and dependency updater
│   ├── .htaccess
│   └── assets/            css/app.css, js/app.js
│
├── vendor/                ← Composer packages (created by composer install)
├── composer.json
├── composer.lock
│
└── storage/
    ├── backups/           ← timestamped backups of files modified by updates
    ├── cron/              ← scheduled task scripts
    └── logs/
```

---

## 🔒 Security Checklist

Before deploying to any network beyond your own machine:

- [ ] Set a **strong `DB_PASS`** — never leave it blank in production
- [ ] Set `APP_ENV` to `'production'` in `config/config.php`
- [ ] Update `BASE_URL` to your real domain (HTTPS preferred)
- [ ] Confirm `/app`, `/core`, and `/config` are blocked via `.htaccess`
- [ ] Enforce **HTTPS** on your server
- [ ] Restrict database user privileges:
  ```sql
  SHOW GRANTS FOR 'your_db_user'@'localhost';
  -- Should only have: SELECT, INSERT, UPDATE, DELETE
  ```
- [ ] Rotate your Plaid API keys and store them outside version control

---

## 🗺️ Roadmap

### Phase 2
- [ ] Reports & charts with CSV/PDF export
- [ ] Recurring expense auto-generation
- [ ] Email alerts for budget overruns

### Phase 3
- [ ] Plugin system activation
- [ ] REST API (`/api/*`) for external integrations
- [ ] Mobile PWA support

### On the Horizon
- [ ] Net Worth Tracker
- [ ] Budget Forecasting
- [ ] Spending Alerts (push/email)
- [ ] Year-over-Year Comparison
- [ ] Per-member expense attribution
- [ ] Financial Health Score
- [ ] Tax Year Summary Export

---

## 🏗️ Architecture Highlights

- **Custom MVC** — no framework overhead; a front controller + lightweight router handles all routing
- **Plugin system** — each feature module (Savings Goals, Debt Tracker, Bank Import, etc.) is fully self-contained with its own model, controller, views, and schema migrations
- **AJAX-first UI** — drag-to-reorder dashboard, inline editing, and toast notifications without full page reloads
- **Python sidecars** — compute-heavy tasks (PDF pay stub parsing via `pdfplumber`, Excel fallback via `openpyxl`) run as separate processes called from PHP
- **Plaid integration** — cursor-based incremental sync with AES-256 encrypted token storage

---

## 🤝 Contributing

Pull requests are welcome! Please make sure any contribution:

1. Passes the existing test suite
2. Follows the established MVC conventions
3. Includes appropriate input validation and CSRF protection
4. Does not introduce new direct SQL string concatenation (use prepared statements)

---

## 📄 License

MIT — do whatever you like, just don't blame us if you overspend on coffee. ☕
