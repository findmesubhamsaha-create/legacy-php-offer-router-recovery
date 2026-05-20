# System Audit Report — Legacy PHP Offer Router

**Date:** 2026-05-18  
**Auditor:** Senior Legacy PHP Recovery Engineer  
**Scope:** Full static analysis — no code was modified  
**PHP Target:** 8.2  
**Stack:** PHP 8.2 + Apache + MySQL 8 (Dockerized)

---

## Table of Contents

1. [Critical Issues](#1-critical-issues)
2. [High Issues](#2-high-issues)
3. [Medium Issues](#3-medium-issues)
4. [Low Issues](#4-low-issues)
5. [Technical Debt](#5-technical-debt)
6. [Test Coverage Gaps](#6-test-coverage-gaps)
7. [Edge-Case Scenario Index](#7-edge-case-scenario-index)
8. [Recommended Fix Order](#8-recommended-fix-order)

---

## 1. Critical Issues

---

### C-01 · SQL Injection Throughout Database Class

**Issue:** Every query in `Database.php` is built by raw string concatenation. No prepared statements, no escaping, no binding.

**Impact:** Full database read/write/delete from any input field, URL parameter, or CSV column. Complete data exfiltration or destruction via any AJAX endpoint.

**Reproduction:**
```
POST /portal/ajax.php
requestMethod=fetchAll&search[value]=1' UNION SELECT user(),password,'x','x','x','x','x' FROM tbl_user-- -
```
Any search box on the dashboard would trigger this path through `Offer::fetchAll()` → `Database::join_query()`.

**Files:**
- [portal/library/database/Database.php](../library/database/Database.php) — `save_data()` line 27, `fetch_data()` line 41, `update_data()` line 171, `fetch_data_new()` line 79, `join_query()` line 210
- [portal/library/cron.php](../library/cron.php) — lines 15, 19, 21, 30

**Note:** `portal/export/download.php` correctly uses `prepare()` + `bind_param()`. That file is the only one in the codebase that does so.

**Suggested fix:** Replace all query-building methods in `Database.php` with `mysqli` prepared statements using `?` placeholders and `bind_param()`. The `save_data()` and `update_data()` helpers need to be rewritten to accept typed parameters.

**Risk level:** CRITICAL

---

### C-02 · Plaintext Password Storage and Comparison

**Issue:** User passwords are stored as plaintext in `tbl_user.password` and compared directly by the database query. No hashing.

**Impact:** Full credential exposure on any SQL injection (C-01 above) or direct DB access. Every user password readable in a single `SELECT`.

**Reproduction:**
```sql
SELECT user_name, password FROM tbl_user;
-- Returns: admin | admin123
```
Login comparison via `portal/library/User.php:12`:
```php
$this->db->fetch_data(DB_USER_TABLE, ['user_name'=>$username, 'password'=>$password], 1)
```

**Files:**
- [portal/library/User.php](../library/User.php) — line 12
- [portal/database/schema.sql](../database/schema.sql) — `tbl_user` table definition
- [portal/database/seed.sql](../database/seed.sql) — plaintext seed credential

**Suggested fix:** Hash passwords with `password_hash($password, PASSWORD_BCRYPT)` on creation. Verify with `password_verify()` at login. Fetch user by username only, then verify hash in PHP.

**Risk level:** CRITICAL

---

### C-03 · No Authentication Guard on AJAX Endpoints

**Issue:** `portal/ajax.php` processes all actions — add offer, delete offer, archive, import, fetch reports — without checking whether the caller is authenticated. `dashboard.php` has a session guard; `ajax.php` does not.

**Impact:** Any unauthenticated attacker with network access can invoke any mutation (add/delete/archive/import) or read all offers and reports by posting to `ajax.php` directly.

**Reproduction:**
```bash
curl -X POST http://localhost:8080/portal/ajax.php \
  -d "requestMethod=deleteOffer&oid=1"
# Returns: {"response":true,"message":"Offer Deleted"}  — no session required
```

**Files:**
- [portal/ajax.php](../ajax.php) — entire file; no `$_SESSION` check before line 21
- [portal/dashboard.php](../dashboard.php) — lines 2–5 shows the correct guard pattern that is absent in ajax.php

**Suggested fix:** Add at the top of `ajax.php`, after `session_start()`:
```php
if (!isset($_SESSION["is_login"])) {
    http_response_code(401);
    echo json_encode(['response' => false, 'message' => 'Unauthorized']);
    exit;
}
```

**Risk level:** CRITICAL

---

### C-04 · Fatal Error: resetUserPassword Calls Non-Existent Static Method

**Issue:** `ajax.php:449` calls `User::resetPassword()` as a static method. No such method exists anywhere in `User.php`.

**Impact:** Any request with `requestMethod=resetPassword` triggers a fatal `Error: Call to undefined method User::resetPassword()`. The reset password feature is completely non-functional and will crash the PHP process, potentially leaking a stack trace to the client (given `display_errors` is enabled).

**Reproduction:**
```bash
curl -X POST http://localhost:8080/portal/ajax.php \
  -d "requestMethod=resetPassword&username=admin&password=newpass"
# PHP Fatal error: Call to undefined method User::resetPassword()
```

**Files:**
- [portal/ajax.php](../ajax.php) — line 449
- [portal/library/User.php](../library/User.php) — no `resetPassword` static method defined

**Suggested fix:** Either implement `User::resetPassword()` as a static method, or instantiate `$user = new User()` and call `$user->resetPassword(...)` as an instance method consistent with the rest of the file.

**Risk level:** CRITICAL

---

## 2. High Issues

---

### H-01 · join_query() and filter_query() Return Undefined Variable

**Issue:** Both `Database::join_query()` and `Database::filter_query()` only define `$return` inside the `if ($result && $result->num_rows > 0)` block. If the query returns zero rows or the query fails, `$return` is never assigned. PHP 8.2 emits `Warning: Undefined variable $return` and the function returns `null`.

**Impact:** Any caller that calls `count()` on the returned null will throw `TypeError: count(): Argument #1 ($array) must be of type Countable|array, null given` in PHP 8.x. This crashes loops in `Offer.php` and `Report.php` that use `for ($i=0; $i < count($result); $i++)`.

**Reproduction:**
1. Clear all offers from the database
2. Load dashboard → `fetchAll()` → `join_query()` returns null → `count(null)` → TypeError → 500 error

**Files:**
- [portal/library/database/Database.php](../library/database/Database.php) — `join_query()` lines 209–226, `filter_query()` lines 229–246

**Suggested fix:** Initialize `$return = [];` before the `if ($result)` block in both methods, so they always return an array.

**Risk level:** HIGH

---

### H-02 · ajax.php Re-enables display_errors, Overriding Docker PHP Config

**Issue:** `ajax.php` lines 4–6 explicitly call `ini_set('display_errors', 1)` and `error_reporting(E_ALL)`. The `Dockerfile` sets `display_errors = Off` in `custom.ini`, but `ini_set()` at runtime takes precedence.

**Impact:** Every PHP notice, warning, or deprecated message is prepended to the JSON response body. This corrupts JSON (e.g., `Warning: Undefined array key 'requestMethod' {"response":false,...}`), causing `JSON.parse()` to throw on the client. Every affected endpoint silently fails in the frontend as if the server returned garbage.

**Reproduction:**
1. Make a request missing `requestMethod`: `POST /portal/ajax.php` (no body)
2. Response starts with `Warning: Undefined array key 'requestMethod'` followed by nothing
3. Frontend JS `JSON.parse()` throws `SyntaxError`

**Files:**
- [portal/ajax.php](../ajax.php) — lines 4–6

**Suggested fix:** Remove the three `ini_set`/`error_reporting` lines from `ajax.php`. The Dockerfile's `custom.ini` handles error reporting for production. If debug logging is needed, write to `error_log()` instead of stdout.

**Risk level:** HIGH

---

### H-03 · Missing isset on $_REQUEST['requestMethod'] Crashes All Requests

**Issue:** `ajax.php:21`: `$requestMethod = $_REQUEST['requestMethod']` — no `isset()` guard. If `requestMethod` is absent from the request, PHP 8.2 emits a warning and `$requestMethod` is null. The switch falls through to `default` (no-op), returning empty output instead of an error.

**Impact:** Combined with H-02 (display_errors on), the warning text is prepended to the empty response, breaking the JSON layer for every malformed request. In isolation, missing requestMethod silently does nothing — no error reported to the client.

**Reproduction:**
```bash
curl -X POST http://localhost:8080/portal/ajax.php   # no body
# Response: "Warning: Undefined array key 'requestMethod'..."
```

**Files:**
- [portal/ajax.php](../ajax.php) — line 21

**Suggested fix:**
```php
if (!isset($_REQUEST['requestMethod'])) {
    echo json_encode(['response' => false, 'message' => 'Missing requestMethod']);
    exit;
}
$requestMethod = $_REQUEST['requestMethod'];
```

**Risk level:** HIGH

---

### H-04 · Double JSON Encoding in importOffer Response

**Issue:** `Upload::uploadOffer()` returns `json_encode([...])` — a JSON string. `ajax.php:388` then wraps this in another `json_encode(array('response'=>true, 'message'=>$upload_offer))`. The `message` field in the outer envelope is a JSON-encoded string, not a JSON object.

**Impact:** The client receives `{"response":true,"message":"{\"status\":\"success\",\"inserted_rows\":3}"}`. The frontend must `JSON.parse(response.message)` a second time to read the import result. If the frontend assumes `message` is an object (as it is for all other endpoints), import status is silently misread — partial errors are never surfaced.

**Reproduction:**
1. Upload a valid CSV via the portal import
2. `console.log(response.message)` — shows a string, not an object
3. `response.message.status` → `undefined`

**Files:**
- [portal/ajax.php](../ajax.php) — line 388 (importOffer function)
- [portal/library/Upload.php](../library/Upload.php) — `uploadOffer()` return statements

**Suggested fix:** Change `uploadOffer()` to return a PHP array instead of a JSON string, and let `ajax.php` do the single `json_encode()`.

**Risk level:** HIGH

---

### H-05 · Session Fixation: No Session Regeneration After Login

**Issue:** `ajax.php::userLogin()` sets `$_SESSION["is_login"] = true` but never calls `session_regenerate_id(true)`. An attacker can pre-set a known session ID, wait for the victim to log in, and then use that session ID.

**Impact:** Session fixation attack — attacker authenticates as the victim without knowing credentials.

**Reproduction:**
1. Attacker crafts `PHPSESSID=known_value` cookie
2. Victim logs in; the server reuses the same session ID
3. Attacker uses `PHPSESSID=known_value` to access the dashboard

**Files:**
- [portal/ajax.php](../ajax.php) — `userLogin()` function, lines 81–95

**Suggested fix:** Call `session_regenerate_id(true)` immediately before setting the session variable:
```php
session_regenerate_id(true);
$_SESSION["is_login"] = true;
```

**Risk level:** HIGH

---

### H-06 · Weight Distribution Bias When Weights Don't Sum to 100

**Issue:** `Postback::get_link_to_display()` generates `rand(0, 99)` then iterates over active URLs subtracting each weight. If the sum of active weights is less than 100, `$rand` may never go below zero — the `break` is never hit — and the function returns the last URL in the array (`$weight['sub_url']` retains its last loop value).

**Impact:** Silent bias toward the last URL when weight totals are < 100. When all weights sum to exactly 100, behavior is correct. If an admin deactivates a URL without rebalancing, traffic distribution silently becomes incorrect with no error.

**Reproduction:**
1. Create an offer with 3 URLs: weights 40, 40, 10 (total 90, one URL deactivated)
2. Generate ~1000 requests; URL #3 (weight 10) receives ~20% of traffic instead of ~11%

**Files:**
- [portal/library/Postback.php](../library/Postback.php) — `get_link_to_display()` lines 43–50

**Suggested fix:** Normalize weights to the actual total, or use `rand(1, array_sum($weights))` bounded to the real sum. Alternatively, validate weight sum = 100 before insert.

**Risk level:** HIGH

---

### H-07 · export/index.php Has No Authentication Check

**Issue:** `portal/export/index.php` has no `session_start()` or `$_SESSION["is_login"]` check. `dashboard.php` correctly guards behind a session; the export page does not.

**Impact:** Unauthenticated users who know or guess the URL can access the export page and download all offer data in bulk.

**Reproduction:**
```bash
curl http://localhost:8080/portal/export/
# Returns full download links list without any authentication
```

**Files:**
- [portal/export/index.php](../export/index.php) — no auth guard
- [portal/dashboard.php](../dashboard.php) — lines 2–5 (correct guard pattern)

**Suggested fix:** Add session check at the top of `portal/export/index.php` and `portal/export/download.php` matching the pattern in `dashboard.php`.

**Risk level:** HIGH

---

## 3. Medium Issues

---

### M-01 · $slug_name_column Undefined Variable in CSV Upload

**Issue:** `Upload::uploadOffer()` initializes `$offer_name_column = ''` before the header-detection loop, but `$slug_name_column` is only assigned inside the loop when a matching header is found. If no `slug name` column exists in the CSV, `$slug_name_column` is undefined at line 45.

**Impact:** PHP 8.2 emits `Warning: Undefined variable $slug_name_column`. With `display_errors` on, this warning corrupts the JSON response. `empty()` on the undefined variable evaluates to true, which happens to return the correct error message, but the *reason* for the error is "missing column" rather than "undefined variable" — so this masks the actual failure mode.

**Reproduction:**
1. Upload CSV with columns: `offer name`, `url1`, `weight1`, `status1` (missing `slug name`)
2. Server returns partial error response prepended by PHP warning text

**Files:**
- [portal/library/Upload.php](../library/Upload.php) — lines 24 (missing init), 45 (use before init)

**Suggested fix:** Add `$slug_name_column = '';` alongside the other column variable initializations before the foreach loop.

**Risk level:** MEDIUM

---

### M-02 · $urls / $weights / $statuses Arrays Not Reset Between CSV Offers

**Issue:** In `Upload::uploadOffer()`, the `$urls`, `$weights`, and `$statuses` arrays are built via `$urls[] = $value` inside a nested loop, but never reset between the outer offer-level iterations.

**Impact:** When a CSV contains multiple offers, the second offer's sub-URL arrays will contain all URLs from the first offer prepended. DB inserts for offer #2 will include offer #1's URLs, causing incorrect offer-to-URL mappings.

**Reproduction:**
1. Upload CSV with Offer A (URL1, URL2) and Offer B (URL3)
2. Offer B in DB ends up with URL1, URL2, URL3 instead of just URL3

**Files:**
- [portal/library/Upload.php](../library/Upload.php) — lines 217–231 (inside the `foreach ($offer_correct_rows as $valid_row)` block)

**Suggested fix:** Reset `$urls = []; $weights = []; $statuses = []; $outputArray = [];` at the start of each `$valid_row` iteration.

**Risk level:** MEDIUM

---

### M-03 · $start_date / $end_date Leak Across CSV Rows

**Issue:** `$start_date` and `$end_date` are assigned in the first `foreach ($offer_rows as $key => $values)` loop (validation pass), then used in the second `foreach ($offer_rows as $row_info)` loop (insert pass). For a multi-row offer, only the LAST row's dates are retained and applied to ALL sub-URL inserts.

**Impact:** An offer with 3 rows may have different intended dates per row. The last row's date silently overwrites all others. If the last row's date is also empty (defaulting to `date('Y-m-d')`), all sub-URLs get today's date regardless of what was specified.

**Files:**
- [portal/library/Upload.php](../library/Upload.php) — lines 80–110 (assignment), 239–240 (usage)

**Suggested fix:** Capture `$start_date` and `$end_date` per row within the insert loop rather than relying on the variable from the earlier validation pass.

**Risk level:** MEDIUM

---

### M-04 · Enum Status Value Not Validated Before Database Insert

**Issue:** The CSV `status` column value is passed directly into the `tbl_sub_offer_url.status` ENUM('yes','no') field. No normalization occurs in PHP.

**Impact:** CSV values like `Yes`, `YES`, `true`, `1`, `Active` will fail the enum constraint. In MySQL strict mode (`STRICT_TRANS_TABLES`, which is MySQL 8 default), this generates error 1265 (`Data truncated for column 'status'`). The insert fails silently (returns false/0) — the offer is created but without any sub-URLs. In non-strict mode, the empty string `''` is inserted as the first enum value.

**Edge case:**
```
Case:     Upload CSV with status column = "Active" instead of "yes"
Expected: Handled gracefully with informative error message
Current:  MySQL enum crash / silent empty-string insert
Severity: Medium
```

**Files:**
- [portal/library/Upload.php](../library/Upload.php) — line 250 (status inserted from CSV as-is)
- [portal/database/schema.sql](../database/schema.sql) — `tbl_sub_offer_url.status ENUM('yes','no')`

**Suggested fix:** Normalize the status value before insert:
```php
$status = strtolower(trim($outputArray['status'][$x])) === 'yes' ? 'yes' : 'no';
```

**Risk level:** MEDIUM

---

### M-05 · No Database Transactions for Multi-Step Writes

**Issue:** `Offer::addNewOffer()` performs two distinct write operations: (1) insert into `tbl_offer_url`, (2) insert N rows into `tbl_sub_offer_url`. These are separate, independent query calls with no transaction wrapping.

**Impact:** If the sub-URL insert loop fails at any point (DB error, lost connection, enum constraint), the parent offer record exists without any sub-URLs. This orphaned offer will appear in the dashboard (fetchAll) but will never route any traffic (no active URLs). It cannot be edited to add URLs through the normal UI flow.

**Reproduction:**
1. Create an offer with a sub-URL containing a single quote in the URL (triggers SQL injection in save_data)
2. Main offer row inserted successfully; sub-URL insert fails
3. Dashboard shows the offer; routing for it returns 404

**Files:**
- [portal/library/Offer.php](../library/Offer.php) — `addNewOffer()` lines 598–613
- [portal/library/Upload.php](../library/Upload.php) — lines 243–256 (CSV bulk import)

**Suggested fix:** Wrap multi-step writes in explicit `mysqli` transactions using `$conn->begin_transaction()`, `$conn->commit()`, and `$conn->rollback()` on failure.

**Risk level:** MEDIUM

---

### M-06 · Hard-Coded Production Domain in Export and Dashboard

**Issue:** Multiple files embed the production domain `efbhalvbhdsurl.com` as a hard-coded literal.

**Impact:** In Docker, staging, or any non-production environment, all generated offer links and the export page's "Back to dashboard" link point to the production server. This causes incorrect link generation in the dashboard table and can send test clicks to live production.

**Affected literals:**
- `portal/export/index.php:28` — `<a href="https://efbhalvbhdsurl.com/portal/dashboard.php">`
- `portal/library/Offer.php:340` — `$link = 'https://efbhalvbhdsurl.com/?oid='...`

**Files:**
- [portal/export/index.php](../export/index.php) — line 28
- [portal/library/Offer.php](../library/Offer.php) — line 340

**Suggested fix:** Add `APP_DOMAIN` to `.env` (the placeholder already exists in `.env.example`) and consume it as a constant in `Settings.php`. Replace all hard-coded domain references with `APP_DOMAIN`.

**Risk level:** MEDIUM

---

### M-07 · Export INNER JOIN Silently Excludes Orphaned Offers

**Issue:** Both `portal/export/index.php` and `portal/export/download.php` use `JOIN tbl_tag ... JOIN tbl_network` (inner joins). Offers with `NULL tag_id` or `NULL network_id` are silently excluded from the export.

**Impact:** If the schema allows `NULL` for `tag_id`/`network_id` (which it does per `schema.sql`), any offer without a tag or network is invisible in the export — no warning, no count discrepancy shown. Export is silently incomplete.

**Reproduction:**
1. Create an offer leaving tag and/or network blank
2. Run export — offer does not appear in any batch

**Files:**
- [portal/export/index.php](../export/index.php) — line 55 (`JOIN tbl_tag`, `JOIN tbl_network`)
- [portal/export/download.php](../export/download.php) — line 71 (same joins)

**Suggested fix:** Replace `JOIN` with `LEFT JOIN` on both `tbl_tag` and `tbl_network` to include all offers.

**Risk level:** MEDIUM

---

### M-08 · DataTable Empty Response Returns String Instead of Array

**Issue:** When `Offer::fetchAll()` returns falsy (empty result), `ajax.php::fetchAllData()` returns `{"data": ""}` — data is an empty string. DataTables 2.x expects `{"data": []}` (an array).

**Impact:** On first load of a clean/empty database, the DataTable throws a JavaScript error (`DataTables warning: table id=... - Requested unknown parameter '0'...`) and fails to render. The dashboard appears broken on new installations.

**Edge case:**
```
Case:     Open dashboard on fresh DB (no offers imported yet)
Expected: Empty table renders with "No data available"
Current:  DataTables JS error; table never renders
Severity: Medium
```

**Files:**
- [portal/ajax.php](../ajax.php) — `fetchAllData()` lines 149–155

**Suggested fix:** Return `{"data": []}` instead of `{"data": ""}` on empty result.

**Risk level:** MEDIUM

---

### M-09 · No CSRF Protection on Any Mutation Endpoint

**Issue:** No CSRF token is generated, stored in session, or validated on any POST handler in `ajax.php`. All mutation endpoints (add, edit, delete, archive, import) are CSRF-vulnerable.

**Impact:** An attacker can craft a webpage that silently submits a form to `ajax.php` when a logged-in admin visits it. All offer data can be deleted, modified, or replaced.

**Files:**
- [portal/ajax.php](../ajax.php) — all POST handlers

**Suggested fix:** Generate a CSRF token on session start, embed it in the dashboard HTML, and verify it in `ajax.php` before processing any state-changing request.

**Risk level:** MEDIUM

---

## 4. Low Issues

---

### L-01 · Missing isset for $_REQUEST['click_id'] in Root Router

**Issue:** `index.php:15` uses `$_REQUEST['click_id'] ? ... : '0'` without an `isset()` check. PHP 8.2 emits `Warning: Undefined array key 'click_id'`.

**Impact:** Every standard offer routing request (without a `click_id` parameter) triggers a PHP warning. With `display_errors` on in any environment, this warning appears in the response before the redirect header. Apache may emit `headers already sent` if the warning is printed first, causing the redirect to fail.

**Files:**
- [index.php](../../index.php) — line 15

**Suggested fix:** `'click_id' => isset($_REQUEST['click_id']) ? $_REQUEST['click_id'] : '0'`

**Risk level:** LOW

---

### L-02 · $parsed['query'] Undefined When URL Has No Query String

**Issue:** `index.php:9`: `$query = $parsed['query']` — `parse_url()` does not include a `query` key if the URL has no query string. PHP 8.2 emits `Warning: Undefined array key 'query'`.

**Impact:** Hitting `http://localhost:8080/` with no query string at all causes a PHP warning before any redirect. `$_REQUEST['oid']` will also be undefined, causing another warning and a redirect to `404.php?`.

**Files:**
- [index.php](../../index.php) — line 9

**Suggested fix:** `$query = $parsed['query'] ?? '';`

**Risk level:** LOW

---

### L-03 · cron.php Outputs Debug Strings Unsuitable for Cron Execution

**Issue:** `cron.php` outputs `echo '<pre>';` on line 8 and `echo 'Old Row Updated'` / `echo 'New Row Inserted'` during normal execution. There is no `--quiet` mode or log-file option.

**Impact:** If run as a system cron job (`php cron.php`), stdout is sent as email to the cron user. High-frequency execution creates email flooding. HTML tags (`<pre>`) in the output are meaningless in a terminal context. The file has no executable path, no error exit code, and no success/failure signaling.

**Files:**
- [portal/library/cron.php](../library/cron.php) — lines 8, 23–37

**Suggested fix:** Replace echo statements with `error_log()` calls. Add a shebang and exit codes.

**Risk level:** LOW

---

### L-04 · connect.php Always Echoes "connection success"

**Issue:** `portal/library/database/connect.php:20` always echoes `"connection success"`. Any page that includes this file will have that text prepended to its output.

**Impact:** Currently no production page includes `connect.php` (it appears to be a standalone debug file), but if ever included by mistake, it will corrupt page output silently.

**Files:**
- [portal/library/database/connect.php](../library/database/connect.php) — line 20

**Suggested fix:** Remove the echo statement or delete the file (it duplicates `Database.php` functionality).

**Risk level:** LOW

---

### L-05 · Trailing ? in 404 Redirect When No Extra Parameters

**Issue:** `index.php:26`: `header("Location: 404.php?".$final_query)` always appends `?` even when `$final_query` is empty.

**Impact:** Redirects to `404.php?` instead of `404.php`. Technically valid but cosmetically incorrect and may confuse analytics or URL logging tools.

**Files:**
- [index.php](../../index.php) — line 26

**Suggested fix:** `header("Location: 404.php" . (!empty($final_query) ? '?' . $final_query : ''));`

**Risk level:** LOW

---

### L-06 · No Session Lifetime or Cookie Security Configuration

**Issue:** No `session.cookie_lifetime`, `session.cookie_httponly`, `session.cookie_secure`, or `session.gc_maxlifetime` are configured. PHP defaults apply (session dies when browser closes; cookies accessible to JavaScript).

**Impact:** XSS vulnerability (if introduced later) can steal session cookies. Session cookies are sent over HTTP in Docker local dev without `secure` flag — acceptable locally but a risk if the same config reaches staging/production.

**Files:**
- [portal/ajax.php](../ajax.php) — `session_start()` line 7 (no options)
- [portal/dashboard.php](../dashboard.php) — `session_start()` line 2 (no options)

**Suggested fix:**
```php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);
```

**Risk level:** LOW

---

## 5. Technical Debt

| # | Item | Files |
|---|---|---|
| TD-01 | Extensive commented-out dead code throughout (old fetchAll, commented AJAX handlers, old export queries) | Offer.php, ajax.php, export/download.php |
| TD-02 | Backup files committed to the repository (`portal/backup/*.php`, `portal/library/Offer - bkp.php`, `Offer(bkp-*).php`) | portal/backup/, portal/library/ |
| TD-03 | Database class methods silently return `0`/`[]` on error with no error logging | portal/library/database/Database.php |
| TD-04 | Schema was reverse-engineered from PHP source — column types and sizes may be incorrect | portal/database/schema.sql |
| TD-05 | `$_REQUEST` used throughout instead of specifically `$_POST`/`$_GET`, making CSRF harder to defend | portal/ajax.php |
| TD-06 | No autoloader — all class files manually required at the top of each entry point | portal/ajax.php, portal/dashboard.php |
| TD-07 | `Database::save_data()` does not escape single quotes in values — any string with `'` silently breaks the INSERT | portal/library/database/Database.php:27 |
| TD-08 | Mixed connection styles: `Database` class for business logic; raw `mysqli` in `export/index.php`, `export/download.php`, and `cron.php` | Multiple files |
| TD-09 | `foreach` loop variable `$weight` retained after loop and used as return value in `get_link_to_display()` — fragile PHP-ism | portal/library/Postback.php:43-49 |
| TD-10 | No logging system — no audit trail for offer mutations, login attempts, or routing decisions | Entire codebase |

---

## 6. Test Coverage Gaps

| # | Area | Missing Coverage |
|---|---|---|
| TC-01 | **Routing engine** | Weight distribution statistical correctness; edge case where weights don't sum to 100; empty URL set; non-existent slug; archived/deleted slug |
| TC-02 | **CSV import** | Valid multi-offer upload; duplicate offer name; duplicate slug; invalid URL format; status column with wrong casing; missing required columns; weights not summing to 100; empty CSV; malformed JSON from frontend CSV parser |
| TC-03 | **Authentication** | Login with wrong credentials; login with SQL injection payload; session expiry; concurrent session behavior |
| TC-04 | **AJAX endpoints** | Each endpoint called without session; each endpoint called with missing parameters; each endpoint called with oversized input |
| TC-05 | **Offer lifecycle** | Create → Edit → Archive → Reset → Delete flow; reset of offer with duplicate slug conflict |
| TC-06 | **Export** | Export with offers that have NULL tag/network; export with zero active offers; pagination correctness for batch boundaries |
| TC-07 | **Database layer** | Empty result handling from all Database methods; connection failure behavior; multi-byte/UTF-8 characters in offer names |
| TC-08 | **Docker** | First-boot (no DB imported); DB already imported (idempotency); container restart without data loss |

---

## 7. Edge-Case Scenario Index

| Case | Expected | Current | Severity |
|---|---|---|---|
| CSV upload with `status` column = `"Active"` | Error: invalid status value | MySQL enum crash / silent `''` insert | Medium |
| CSV upload with 2 offers, offer B processed second | Offer B has only its own URLs | Offer B inherits all of offer A's URLs (M-02) | Medium |
| Dashboard loaded on fresh empty DB | Empty table, "No data available" | DataTable JS error; table fails to render (M-08) | Medium |
| `/portal/ajax.php` called with no body | `{"response":false,"message":"Missing requestMethod"}` | PHP warning + empty response → SyntaxError in client | High |
| Request `requestMethod=resetPassword` | Password reset form | Fatal Error: undefined method User::resetPassword() | Critical |
| Offer with active URLs totaling 90% weight | Traffic distributed proportionally | Last URL silently receives ~10% extra traffic | High |
| Offer sub-URL insert fails mid-loop | Rollback, no orphan | Orphaned parent offer in DB; offer shows in dashboard but never routes | Medium |
| Direct access to `/portal/export/` without login | Redirect to login | Full export page rendered with download links | High |
| Direct access to `/portal/ajax.php?requestMethod=deleteOffer&oid=1` without login | 401 Unauthorized | Offer deleted successfully | Critical |
| Offer with `tag_id=NULL` exported | Appears in export | Silently excluded by INNER JOIN | Medium |

---

## 8. Recommended Fix Order

This order prioritizes security impact and systemic blast radius over effort.

### Immediate (before any production exposure)

1. **C-03** — Add authentication guard to `ajax.php`
2. **C-01** — Migrate Database class to prepared statements
3. **C-02** — Hash passwords with `password_hash` / `password_verify`
4. **H-02** — Remove `display_errors` / `ini_set` from `ajax.php`
5. **H-07** — Add auth guard to `portal/export/index.php` and `download.php`

### Short-term (next sprint)

6. **C-04** — Implement or fix `User::resetPassword()` method
7. **H-01** — Initialize `$return = []` in `join_query()` / `filter_query()`
8. **H-03** — Add `isset()` guard for `requestMethod` in `ajax.php`
9. **H-04** — Fix double JSON encoding in `importOffer`
10. **H-05** — Add `session_regenerate_id(true)` after login
11. **M-09** — Implement CSRF token validation
12. **M-05** — Wrap multi-step writes in transactions

### Medium-term (hardening pass)

13. **H-06** — Fix weight distribution algorithm
14. **M-01** — Initialize `$slug_name_column = ''` in Upload.php
15. **M-02** — Reset `$urls`/`$weights`/`$statuses` between offer iterations
16. **M-03** — Fix `$start_date`/`$end_date` scope in CSV import
17. **M-04** — Normalize enum values before DB insert
18. **M-06** — Replace hard-coded domain with `APP_DOMAIN` constant
19. **M-07** — Change INNER JOIN to LEFT JOIN in export queries
20. **M-08** — Return `[]` instead of `""` for empty DataTable responses

### Low-priority (cleanup)

21. **L-01** through **L-06** — PHP 8.2 warning suppressions, session config, cosmetic fixes
22. **TD-01**, **TD-02** — Remove commented-out dead code and backup files from repo
23. **TD-10** — Add structured logging for audit trail
