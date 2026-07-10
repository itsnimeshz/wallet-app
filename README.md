# Cashbook — Personal Wallet App

A simple personal income/expense tracker with a live Cash + Bank cashbook balance.
Built with plain PHP, SQLite, HTML/CSS/JS — no framework, no build step.

## Features
- Login with 2 hardcoded accounts (config/users.php)
- Add income / expense / transfer (cash ⇄ bank) records
- All records fully editable
- Soft delete (Trash) and permanent delete
- Full audit log (create, update, delete, restore, category changes)
- Live, dynamic Cash / Bank / Total balance shown on every page
- Monthly report and Yearly report with category breakdown
- Income & expense categories stored in `data/categories.json` (editable in-app under "Categories")
- Single database table for records (`records`) + one for the audit log (`audit_log`), SQLite file at `data/wallet.sqlite`

## Requirements
- PHP 7.4+ (tested on 8.3) with the `pdo_sqlite` extension enabled

## Run it
From the `wallet-app` folder:

```bash
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

To run under Apache/Nginx instead, point the document root at this folder. Make sure
the `data/` folder is writable by the web server user (it stores the SQLite database
and categories.json).

## Login
Default accounts (change these — see below):

| Username | Password  |
|----------|-----------|
| owner1   | cash2026  |
| owner2   | bank2026  |

### Changing passwords
Generate a new bcrypt hash:

```bash
php -r "echo password_hash('yourNewPassword', PASSWORD_BCRYPT).PHP_EOL;"
```

Paste the result into `config/users.php` for the relevant account.

## How balances work
- **Income** adds to the chosen account (Cash or Bank).
- **Expense** subtracts from the chosen account.
- **Transfer** moves money between Cash and Bank — it is *never* counted as
  income or expense. The "from" account drops, the "to" account rises by the
  same amount.
- The balance strip at the top of every page recalculates live from all
  non-deleted records, so it's always accurate and updates immediately after
  any add/edit/delete/restore.

## Deleting records
- The **Delete** button on the Records page is a **soft delete** — the record
  moves to Trash (toggle "Show Trash" to see it) and is excluded from balances
  and reports.
- From Trash you can **Restore** a record, or **Delete forever** (permanent,
  irreversible — only available once a record is already in Trash, as a
  safety net against accidental clicks).
- Every action is written to the Audit Log with who did it, when, and the
  before/after values.

## Project structure
```
wallet-app/
  config/         config.php, users.php (hardcoded accounts)
  data/           categories.json, wallet.sqlite (created on first run)
  includes/       db.php, auth.php, functions.php, header/footer
  api/            JSON endpoints used by the front-end JS
  assets/         css/style.css, js/app.js
  index.php       Dashboard (quick add + recent activity)
  records.php     Full records list, filters, edit, delete, trash
  monthly_report.php
  yearly_report.php
  audit_log.php
  settings.php    Manage categories.json
  login.php / logout.php
```

## Security notes for personal/self-hosted use
- Sessions are HttpOnly + SameSite=Lax; CSRF tokens are required on all
  write (POST) API calls.
- `config/`, `includes/`, and `data/` each ship with an `.htaccess` denying
  direct web access (effective under Apache; if you deploy elsewhere, make
  sure those folders aren't served directly).
- This app has no rate-limiting or account lockout — it's designed for
  private, personal use on a machine/server you control, not public hosting.
