# StudySys — Student Budget + Schedule + Calendar System

## Setup (Laragon)

1. Copy the whole `studysys` folder into `C:\laragon\www\`.
2. Open **Laragon** and click **Start All** (this starts Apache and MySQL together).
3. Click Laragon's **Database** button (or go to `http://localhost/phpmyadmin`), click **Import**, and upload `sql/schema.sql`. This creates the `studysys` database and all tables.
4. Laragon's default MySQL user is `root` with **no password**, same as XAMPP — so `config/db.php` should work as-is. If you changed your Laragon MySQL root password, update `$DB_PASS` in that file.
5. Visit `http://localhost/studysys/` in your browser (or right-click the project in Laragon's sidebar → **Open in browser**). It'll redirect you to the sign-up page.

> Laragon also gives you a nice shortcut: `http://studysys.test/` if you enable Laragon's auto virtual hosts (usually on by default) — no port or folder path needed.

## Setup (XAMPP) — if you switch later

1. Copy the whole `studysys` folder into `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/xamppfiles/htdocs/` (Mac).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin`, click **Import**, and upload `sql/schema.sql`.
4. If your MySQL has a root password set, update `config/db.php` (`$DB_PASS`).
5. Visit `http://localhost/studysys/` in your browser.

## What's built so far (Stage 1 — Auth + Dashboard shell)

- `signup.php` / `login.php` / `logout.php` — account creation and session-based auth (passwords hashed with `password_hash`)
- `dashboard.php` — home page showing this month's income/expenses/balance, today's classes, and upcoming calendar events (all pulling live from the database, currently empty until you add data)
- `includes/sidebar.php` — shared nav so every page looks consistent
- `budget.php`, `schedule.php`, `calendar.php` — placeholder pages, wired into the nav, ready for the next build stages

## Next steps (per our plan)

1. **Budget tracker** — CRUD for `transactions` and `budget_categories` on `budget.php`
2. **Schedule manager (manual entry)** — CRUD for `schedules` on `schedule.php`
3. **Calendar view** — render `calendar_events` as a month/week grid
4. **OCR schedule scanner** — upload registrar photo → Tesseract → parse → confirm → save

## Folder structure

```
studysys/
├── config/db.php          → database connection
├── includes/
│   ├── auth.php           → register/login/logout/session functions
│   └── sidebar.php        → shared nav component
├── api/                   → (empty for now — AJAX endpoints go here later)
├── uploads/schedules/     → (empty for now — scanned schedule images go here later)
├── assets/css/style.css   → dark theme styling for the whole app
├── sql/schema.sql         → full database schema
├── index.php, login.php, signup.php, logout.php, dashboard.php,
│   budget.php, schedule.php, calendar.php
```
