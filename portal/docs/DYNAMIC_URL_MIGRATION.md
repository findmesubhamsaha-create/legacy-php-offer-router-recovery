# Dynamic URL Migration

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**Scope:** Remove all self-referential hardcoded `efbhalvbhdsurl.com` URLs; replace with runtime-resolved `BASE_URL` constant

---

## Problem

Four active files referenced the production domain `efbhalvbhdsurl.com` as a literal string. Any deployment to a different host — Docker, staging, a renamed production domain — would silently generate broken offer tracking links and broken portal navigation.

### Hardcoded URLs Found (active code only)

| File | Line | Hardcoded URL | Purpose |
|---|---|---|---|
| `portal/library/Offer.php` | 349 | `https://efbhalvbhdsurl.com/?oid=…` | Offer tracking link in DataTable |
| `portal/dashboard.php` | 204 | `https://efbhalvbhdsurl.com/portal/import.php` | Sidebar → Import nav link |
| `portal/dashboard.php` | 209 | `https://efbhalvbhdsurl.com/portal/export/` | Sidebar → Export nav link |
| `portal/export/index.php` | 36 | `https://efbhalvbhdsurl.com/portal/dashboard.php` | Back to dashboard link |

### Patterns Not Changed

| Pattern | Location | Reason |
|---|---|---|
| `localhost` in `DB_HOST` fallback | Settings.php:41 | DB host — not a URL |
| `efbhalvbhdsurl` in `DB_NAME` fallback | Settings.php:44 | DB name — not a URL |
| Commented-out code | Offer.php:133 | Not executed |
| Backup files (`Offer(bkp-…).php`, `backup/`) | Various | Not in active code path |
| External CDN links (jQuery, Bootstrap, fonts) | Various HTML files | Third-party assets, not self-referential |
| Sub-URL destination values (`tbl_sub_offer_url.sub_url`) | DB / routing | User-configured destination URLs — must stay as-is |
| `$actual_link` in `index.php:9` | index.php | Already dynamic via `$_SERVER` |

---

## Solution

### Architecture

A single `BASE_URL` constant is defined in `portal/library/Settings.php` (the project-wide configuration bootstrap). All files that already include Settings.php transitively receive `BASE_URL` at no extra cost. Files that did not previously include Settings.php (`dashboard.php`, `export/index.php`) were updated to require it before any HTML output.

### Resolution Priority

```
1. APP_URL in .env       (explicit override — production, behind-proxy, CDN)
2. protocol + HTTP_HOST  (auto-detected from the current HTTP request)
3. http://localhost       (CLI fallback — cron/script context with no HTTP_HOST)
```

---

## Files Changed

### 1. `portal/library/Settings.php`

**Change:** Added `BASE_URL` definition inside the existing configuration IIFE.

```php
// BEFORE — no BASE_URL constant existed

// AFTER (inside the IIFE, after DB constants):
if (isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('BASE_URL', $env['APP_URL'] ?? ($scheme . '://' . $_SERVER['HTTP_HOST']));
} else {
    define('BASE_URL', $env['APP_URL'] ?? 'http://localhost');
}
```

`APP_URL` is read from `.env` via the same loop that reads DB credentials — no new file I/O.

---

### 2. `portal/library/Offer.php`

**Change:** `fetchAll()` — offer tracking link `$link` construction (line 349).

```php
// BEFORE:
$link = 'https://efbhalvbhdsurl.com/?oid='.($final_offer_list[$i]['slug_name'] ?? '')
    .'&tag='.($final_offer_list[$i]['tag_name'] ?? '')
    .'&affid='.($final_offer_list[$i]['network_name'] ?? '');

// AFTER:
// DU-01: BASE_URL replaces hardcoded domain — resolves to current host at runtime
$link = BASE_URL.'/?oid='.($final_offer_list[$i]['slug_name'] ?? '')
    .'&tag='.($final_offer_list[$i]['tag_name'] ?? '')
    .'&affid='.($final_offer_list[$i]['network_name'] ?? '');
```

`BASE_URL` is available because `ajax.php` requires `Settings.php` at line 7 before any `Offer` method is called.

---

### 3. `portal/dashboard.php`

**Changes:**
1. Added `require dirname(__FILE__) . '/library/Settings.php';` after the session auth block (before HTML output).
2. Replaced two sidebar nav hrefs.

```php
// BEFORE (auth block):
<?php
session_start();
if (!isset($_SESSION['is_login'])...) { ... exit; }
?>
<!doctype html>

// AFTER:
<?php
session_start();
if (!isset($_SESSION['is_login'])...) { ... exit; }
// DU-01: load BASE_URL for dynamic link generation
require dirname(__FILE__) . '/library/Settings.php';
?>
<!doctype html>
```

```html
<!-- BEFORE: -->
<a href="https://efbhalvbhdsurl.com/portal/import.php">Import</a>
<a href="https://efbhalvbhdsurl.com/portal/export/">Export</a>

<!-- AFTER: -->
<a href="<?= BASE_URL ?>/portal/import.php">Import</a>
<a href="<?= BASE_URL ?>/portal/export/">Export</a>
```

---

### 4. `portal/export/index.php`

**Changes:**
1. Moved `require Settings.php` from inside the `<body>` (line 50) to before `<!DOCTYPE html>`.
2. Replaced the Back to dashboard href.
3. Removed the now-duplicate `require Settings.php` from the PHP block in the body.

```php
// BEFORE — Settings.php was required mid-body:
<?php session_start(); ... ?>
<!DOCTYPE html>
<html>
  <body>
    <a href="https://efbhalvbhdsurl.com/portal/dashboard.php">Back to dashboard</a>
    ...
    <?php
    require dirname(__FILE__) . '/../library/Settings.php';  // ← was here
    $mysqli = new mysqli(DB_HOST, ...);
    ...
    ?>

// AFTER — Settings.php required before HTML; no duplicate:
<?php
session_start();
...
// DU-01: load BASE_URL before HTML output so template can use it
require dirname(__FILE__) . '/../library/Settings.php';
?>
<!DOCTYPE html>
<html>
  <body>
    <a href="<?= BASE_URL ?>/portal/dashboard.php">Back to dashboard</a>
    ...
    <?php
    $mysqli = new mysqli(DB_HOST, ...);   // DB_* constants already defined
    ...
    ?>
```

---

### 5. `.env.example`

**Change:** Documented the new optional `APP_URL` key (replaced the stale `APP_DOMAIN` comment).

```ini
# BEFORE:
# APP_DOMAIN=yourdomain.com

# AFTER:
# APP_URL sets BASE_URL used for offer tracking links and portal navigation.
# Leave unset to auto-detect from the HTTP request (recommended for local/Docker).
# Set explicitly for production, behind-proxy, or CDN deployments.
# Include scheme, no trailing slash. Example: https://yourdomain.com
# APP_URL=https://yourdomain.com
```

---

## Runtime Behaviour by Environment

| Environment | `APP_URL` in .env | `HTTP_HOST` | `BASE_URL` resolves to |
|---|---|---|---|
| Docker local | *(unset)* | `localhost:8080` | `http://localhost:8080` |
| XAMPP local | *(unset)* | `localhost` | `http://localhost` |
| Staging (HTTP) | *(unset)* | `staging.example.com` | `http://staging.example.com` |
| Production (HTTPS) | *(unset)* | `yourdomain.com` | `https://yourdomain.com` |
| Behind reverse proxy | `https://yourdomain.com` | *proxy header* | `https://yourdomain.com` (explicit wins) |
| CLI / cron | *(unset)* | *(not set)* | `http://localhost` |
| CLI / cron | `https://yourdomain.com` | *(not set)* | `https://yourdomain.com` |

---

## Verification

### Automated (curl)

```bash
# 1. Offer tracking links in fetchAll — must use current host
curl -s -b <session-cookie> -X POST "http://localhost:8080/portal/ajax.php" \
  -d "requestMethod=fetchAll&draw=1&start=0&length=3" | grep -o '"http[^"]*' | head -3
# Expected: "http://localhost:8080/?oid=...

# 2. Dashboard sidebar links — must use current host
curl -s -b <session-cookie> "http://localhost:8080/portal/dashboard.php" | \
  grep -E "portal/(import|export)"
# Expected: href="http://localhost:8080/portal/import.php"
#           href="http://localhost:8080/portal/export/"

# 3. Export back-link — must use current host
curl -s -b <session-cookie> "http://localhost:8080/portal/export/" | \
  grep "portal/dashboard"
# Expected: href="http://localhost:8080/portal/dashboard.php"

# 4. Confirm zero remaining hardcoded domain in active files
grep -rn "efbhalvbhdsurl\.com" portal/library/ portal/dashboard.php \
  portal/export/index.php portal/ajax.php index.php
# Expected: no output (only backup files / commented lines remain)
```

### Live Results (executed 2026-05-20)

```
fetchAll link:     "http://localhost:8080/?oid=sample-offer&tag=Sample Tag&affid=Sample Network"
Dashboard import:  <a href="http://localhost:8080/portal/import.php">
Dashboard export:  <a href="http://localhost:8080/portal/export/">
Export back-link:  <a href="http://localhost:8080/portal/dashboard.php">
Grep active files: (no output — zero remaining hardcoded URLs)
```

### For Production Deployment

```bash
# .env (production):
APP_URL=https://yourdomain.com

# Then verify:
curl -s https://yourdomain.com/portal/ajax.php \
  -d "requestMethod=login&username=admin&password=..." -c /tmp/jar
curl -s -b /tmp/jar -X POST https://yourdomain.com/portal/ajax.php \
  -d "requestMethod=fetchAll&draw=1&start=0&length=1" | grep -o '"https://yourdomain[^"]*'
# Expected: "https://yourdomain.com/?oid=...
```

---

## Not Changed — Out of Scope

| Item | Reason |
|---|---|
| `portal/backup/` files | Not in active code path |
| `portal/library/Offer(bkp-23-09-2024).php` | Backup file — not required anywhere |
| `portal/export/download-bkp.php`, `index-bkp.php` | Backup files |
| Destination URLs in `tbl_sub_offer_url` | User-configured routing targets — must remain as stored |
| DB connection `localhost` fallback | Database host — not an application URL |
