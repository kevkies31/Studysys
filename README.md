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

## Flutter mobile app

The `mobile/` Flutter project provides sign-in/registration and mobile screens for the dashboard, budget, weekly class schedule, and calendar. It uses the existing PHP/MySQL database through JSON endpoints in `api/`. Authentication tokens and the selected light/dark theme are stored with the platform's secure storage. Use the sun/moon button in the app bar (or sign-in screen) to switch themes.

### One-time database setup

After importing `sql/schema.sql`, import `sql/mobile_api.sql` into the same MySQL database. This adds the token table used by mobile sign-in. Existing web accounts and data are reused.

### Run on an Android emulator (XAMPP)

1. Start Apache and MySQL in XAMPP and make sure the site is reachable at `http://localhost/Studier/`.
2. In PowerShell, run:

   ```powershell
   Set-Location mobile
   flutter pub get
   flutter run --dart-define=API_BASE_URL=http://10.0.2.2/Studier/api
   ```

`10.0.2.2` is the Android emulator's route to the host computer. For a physical Android device, use the computer's LAN IP instead (both devices must be on the same network and Apache must allow the connection). The Android app permits cleartext HTTP only in debug builds; use HTTPS for production. For iOS, configure and use an HTTPS endpoint.

The API base URL can be changed at build/run time with `--dart-define=API_BASE_URL=...`; do not ship an emulator or local-network URL in a production build.

### Current mobile coverage

- Sign in, registration, persistent bearer-token sessions, and sign out
- Dashboard totals, today's classes, and upcoming events
- Add/edit/delete transactions, manage categories and monthly limits
- Add/edit/delete weekly classes with overlap validation
- View month events and recurring classes; add/delete calendar events
- Existing web-based schedule image/OCR scanning remains available in the website and is not yet part of the Flutter app

## Folder structure

```
studysys/
├── config/db.php          → database connection
├── includes/
│   ├── auth.php           → register/login/logout/session functions
│   └── sidebar.php        → shared nav component
├── api/                   → JSON API for mobile authentication and app data
├── mobile/                → Flutter Android/iOS application
├── uploads/schedules/     → (empty for now — scanned schedule images go here later)
├── assets/css/style.css   → dark theme styling for the whole app
├── sql/schema.sql         → full database schema
├── sql/mobile_api.sql     → bearer-token storage table for the mobile API
├── index.php, login.php, signup.php, logout.php, dashboard.php,
│   budget.php, schedule.php, calendar.php
```
