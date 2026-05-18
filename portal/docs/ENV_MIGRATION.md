# ENV Migration — Phase 1.1

**Date:** 2026-05-18  
**Branch:** modernization-phase-1  
**Risk:** LOW — config-layer only, no logic or schema changes.

---

## What Changed

### Problem (before)

Credentials existed in three separate places with two different values:

| File | DB User | DB Password | Source |
|------|---------|-------------|--------|
| `library/Settings.php` | `root` | *(empty)* | Hardcoded constants |
| `export/index.php` | `admin` | `KDms@jY7Gw` | Hardcoded variables |
| `export/download.php` | `admin` | `KDms@jY7Gw` | Hardcoded variables |

All three were committed to git in plaintext.

---

### After

| File | What changed |
|------|-------------|
| `library/Settings.php` | Replaced hardcoded `define(...)` lines with an inline `.env` parser. Constant names (`DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`, `DB_USER_TABLE`, `DB_OFFER_TABLE`) are unchanged — all callers work without modification. |
| `export/index.php` | Removed 5 hardcoded credential lines. Added `require ... Settings.php`. Changed `new mysqli($host, $user, $pass, $db)` → `new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME)`. |
| `export/download.php` | Same as above. |
| `.env` (new, **gitignored**) | Local development credentials. Never committed. |
| `.env.example` (new, committed) | Template with placeholder values. Copy to `.env` to set up. |
| `.gitignore` (new) | Excludes `.env` and `vendor/` (Composer, future Phase 2.1). |

---

### How the `.env` Parser Works

`Settings.php` runs an anonymous function (IIFE) that:

1. Resolves `.env` from `[project_root]/.env` — **one level above `portal/`**, outside the Apache/Nginx document root, so it cannot be served directly via HTTP.
2. Parses `KEY=VALUE` lines, skipping `#` comments and blank lines.
3. Handles values wrapped in single or double quotes.
4. Splits on the first `=` only — passwords containing `=` are safe.
5. Falls back to the original hardcoded defaults (`localhost`, `root`, `''`, `efbhalvbhdsurl`) if a key is absent from the file — so a partial `.env` never silently breaks an environment.
6. Terminates with a clear error message if `.env` does not exist at all.

The `if (!defined('DB_HOST'))` guard prevents re-definition if `Settings.php` is included more than once.

---

## How to Run Locally

```bash
# 1. Clone / enter the project root
cd legacy-php-offer-router-recovery

# 2. Create your local .env from the template
cp .env.example .env

# 3. Edit .env with your local DB credentials
#    For a default XAMPP/WAMP install:
#      DB_HOST=localhost
#      DB_USERNAME=root
#      DB_PASSWORD=
#      DB_NAME=efbhalvbhdsurl

# 4. Import the schema (first time only)
mysql -u root -p < portal/database/schema.sql
mysql -u root -p < portal/database/seed.sql

# 5. Point Apache DocumentRoot to portal/ and visit http://localhost/
```

The rest of the system — `ajax.php`, `Database.php`, `User.php`, `Offer.php`, `Postback.php`, and the cron — all consume the DB_* constants from `Settings.php` and are unaffected by this change.

---

## Production Deployment Checklist

- [ ] Create `.env` on the server with production credentials.
- [ ] Confirm `.env` is **not** inside the web root (it lives one directory above `portal/`).
- [ ] Confirm the web server does not have a `portal/..` traversal route that could expose `.env`.
- [ ] Add the production DB user with least-privilege grants:
      ```sql
      GRANT SELECT, INSERT, UPDATE ON efbhalvbhdsurl.* TO 'appuser'@'localhost';
      FLUSH PRIVILEGES;
      ```
- [ ] Do **not** copy `.env.example` as-is to production — it contains placeholder values.

---

## Rollback Steps

If you need to revert this change completely:

**Option A — git revert (recommended)**

```bash
git revert <commit-hash-of-this-change>
```

This restores the original hardcoded values in all three files and removes the `.env` dependency. The `.env` file itself is gitignored and will remain on disk — it has no effect after rollback.

**Option B — manual**

Restore `library/Settings.php` to:
```php
<?php
if (!defined('BASEPATH'))
define('BASEPATH', dirname(dirname(__FILE__)));

/* DB  Credentials  */

define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'efbhalvbhdsurl');
define('DB_USER_TABLE', 'tbl_user');
define('DB_OFFER_TABLE', 'tbl_offer_url');

?>
```

Restore `export/index.php` lines 41–49 to:
```php
<?php
// Database connection
$host = 'localhost';
$db = 'efbhalvbhdsurl';
$user = 'admin';
$pass = 'KDms@jY7Gw';

$mysqli = new mysqli($host, $user, $pass, $db);
```

Restore `export/download.php` lines 1–9 to:
```php
<?php
//echo 'in'; die();
$host = 'localhost';
$db = 'efbhalvbhdsurl';
$user = 'admin';
$pass = 'KDms@jY7Gw';

$mysqli = new mysqli($host, $user, $pass, $db);
```

---

## Manual Verification Steps

After deploying this change, verify the following in order.

### 1. Settings.php loads correctly

Open `portal/library/database/connect.php` in a browser (**local dev only**).  
Expected: `"connection success"` (or the connection error from the DB layer if DB isn't running).  
If you see `.env file not found`, the `.env` path is wrong — check that `.env` exists one directory above `portal/`.

### 2. Login works

Navigate to `http://localhost/portal/` (or your configured URL).  
Enter valid credentials and click **Sign In**.  
Expected: redirect to `dashboard.php`.  
A broken `.env` would cause a white screen or "Configuration error" message at this step.

### 3. Dashboard loads offer list

After login, the DataTables offer list should populate.  
This confirms `Database.php` → `DB_*` constants → MySQL connection is intact.

### 4. Export page works

Navigate to `http://localhost/portal/export/index.php`.  
Expected: a list of "Download Batch N" links.  
A credential mismatch would show "Connection failed: Access denied for user ...".

### 5. Download a CSV batch

Click any batch link from the export page.  
Expected: browser downloads a `.csv` file with offer data.  
An empty file or "Connection failed" confirms the credential path is broken.

### 6. Confirm `.env` is not web-accessible

```bash
curl -I http://localhost/.env
```
Expected: `403 Forbidden` or `404 Not Found`.  
If you get `200 OK`, the `.env` is inside or directly served from the web root — relocate it or add an Apache deny rule:
```apache
<Files ".env">
    Require all denied
</Files>
```

---

## What Was NOT Changed

| Item | Status |
|------|--------|
| Constant names `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME` | Unchanged |
| `DB_USER_TABLE`, `DB_OFFER_TABLE` constants | Unchanged |
| `Database.php` constructor | Unchanged |
| `ajax.php` require chain | Unchanged |
| `Postback::get_link_to_display()` routing algorithm | Unchanged |
| DB schema / tables | Unchanged |
| Login session behavior | Unchanged |
| AJAX response JSON shape | Unchanged |
| URL structure | Unchanged |
