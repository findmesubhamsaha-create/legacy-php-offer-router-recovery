# Modernization Roadmap

**Date:** 2026-05-18  
**Status:** Recovered · Stabilized · Validated  
**Constraint:** Preserve weighted routing behavior, DB schema, URL structure, and production behavior exactly.  
**Approach:** Incremental. Each phase is independently shippable. No phase requires the next.

---

## System Inventory (pre-modernization baseline)

| File | Role | Key Risk |
|------|------|----------|
| `library/Settings.php` | DB constants | Plaintext credentials committed |
| `library/database/Database.php` | DB abstraction | No prepared statements anywhere |
| `library/User.php` | Auth | Plaintext password comparison |
| `library/Offer.php` | Offer CRUD + routing data | SQL injection via string concat |
| `library/Postback.php` | **Weighted URL rotation** | Critical — must not change |
| `library/Report.php` | Click reporting | String interpolation in WHERE |
| `library/Filter.php` | Filter dropdown data | String injection in LIKE |
| `library/Upload.php` | CSV import | No prepared statements |
| `library/cron.php` | Daily click aggregation | Web-accessible, no auth |
| `ajax.php` | Single AJAX endpoint | No CSRF, no input validation |
| `export/index.php` | Batch export UI | **Separate hardcoded credentials** |
| `export/download.php` | CSV download | **Separate hardcoded credentials** |
| `library/database/connect.php` | Debug connectivity test | `echo` statements, web-accessible |

### The protected algorithm

`Postback::get_link_to_display()` — weighted random URL rotation.  
This is the core production behavior. Every phase must leave it byte-for-byte identical.

```
$rand = rand(0, 100-1);
foreach($sites as $site => $weight) {
    $rand -= $weight['weight'];
    if ($rand < 0) break;
}
return $weight['sub_url'];
```

---

## Phase 1 — Immediate Low-Risk Improvements

**Risk: LOW**  
**Estimated effort: 1–2 days**  
**Rollback: Trivially reversible — all changes are config-layer only**

### 1.1 `.env` Support + Config Centralization

**Problem:** Credentials appear in three separate places with different values:

| File | Host | User | Password |
|------|------|------|----------|
| `Settings.php` | localhost | root | *(empty)* |
| `export/index.php` | localhost | admin | KDms@jY7Gw |
| `export/download.php` | localhost | admin | KDms@jY7Gw |

The export files bypass `Settings.php` entirely and carry different credentials, which means they connect to a different user/permission context. All three must be unified under one source of truth.

**Action:**
1. Add `vlucas/phpdotenv` via Composer (or a minimal hand-rolled `.env` parser if Composer is not yet in scope).
2. Create `.env` (gitignored):
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=efbhalvbhdsurl
   APP_DOMAIN=efbhalvbhdsurl.com
   ```
3. Update `Settings.php` to load `.env` and define constants from it.
4. Update `export/index.php` and `export/download.php` to require `Settings.php` and use the shared constants — removing the hardcoded credential blocks.
5. Add `.env` to `.gitignore`. Provide `.env.example` with placeholder values.

**Do not change:** Table names, constant names (`DB_HOST`, `DB_USERNAME`, etc.), or the order in which `Settings.php` defines them — other files depend on those constant names.

---

### 1.2 Remove `connect.php` from Web-Accessible Path

**Problem:** `library/database/connect.php` has live `echo "connection success"` output and is web-accessible. Any visitor can probe database connectivity.

**Action:**  
Delete or move outside the web root. If kept for local debugging, add a `CLI_ONLY` guard at the top:
```php
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
```

**Risk:** None. The file is not `require`d by any production code path.

---

### 1.3 Secure `cron.php`

**Problem:** `library/cron.php` is a web-accessible PHP file with `echo` output and no authentication. Anyone can trigger it manually, causing duplicate click aggregation or inflated report data.

**Action:**
1. Add CLI-only guard at the top (same pattern as 1.2).
2. Move the echo output behind a verbose flag, or replace with a return value.
3. Document the correct cron invocation: `php /path/to/library/cron.php`

**Risk:** None — behavior is identical when invoked from cron; HTTP access is simply blocked.

---

### 1.4 PHP 8.x Compatibility Audit

**Problem:** Several patterns have undefined-variable or deprecated-behavior risks on PHP 8.x:

| Location | Issue |
|----------|-------|
| `Database::join_query()` | `$return` is never initialized; returns `null` (not `[]`) when query has 0 rows — callers assume array |
| `Database::filter_query()` | Same — `$return` uninitialized |
| `Offer.php` (multiple loops) | `count()` on potentially null input — PHP 8 warns |
| `library/cron.php:8` | `echo '<pre>'` — visible HTML in cron output |

**Action:**
1. Initialize `$return = [];` at the top of `join_query()` and `filter_query()`.
2. Add `?? []` guards before `count()` calls where the input may be null.
3. Add `declare(strict_types=1)` to Settings.php and the Database class (library files only — not ajax.php, which receives loose-typed `$_REQUEST` values).

**Risk:** LOW. Fixes are defensive and do not alter happy-path behavior.

---

### 1.5 Improve Error Handling

**Problem:** `Database::__construct()` calls `die("Connection failed: " . $this->conn->connect_error)` — exposes internal error details to the browser. Same pattern in export files.

**Action:**
1. Replace bare `die(...)` in the Database constructor with `throw new RuntimeException('Database connection failed')` — callers (ajax.php) catch this and return a JSON error.
2. In `ajax.php`, wrap the top-level dispatch in a try/catch that returns `{"response":false,"message":"Internal error"}` on exception.
3. In export files, show a user-friendly message instead of the raw MySQLi error.

**Do not change:** The behavior on successful connection, or any query logic.

---

## Phase 2 — Structural Improvements

**Risk: MEDIUM**  
**Estimated effort: 3–5 days**  
**Note:** Do one sub-item at a time. Each is independently shippable.

### 2.1 Composer + PSR-4 Autoloading

**Current state:** All classes are manually `require`d at the top of `ajax.php` in a fixed order. Adding a new class means editing the require list.

**Action:**
1. `composer init` in the `portal/` directory.
2. Define PSR-4 autoload: `"App\\": "library/"`.
3. Replace the require block in `ajax.php` with `require 'vendor/autoload.php'`.
4. Add `vendor/` to `.gitignore`.
5. Add `composer.json` and `composer.lock` to version control.

**Constraint:** Do NOT rename files or classes in this step. PSR-4 loading can work with the existing class names — just configure the namespace map accordingly.

---

### 2.2 Namespaces

**Current state:** All classes live in the global namespace. Class name collision risk grows with the codebase.

**Action (after 2.1):**
1. Add `namespace App;` at the top of each library class.
2. Add `use App\Database;` in classes that instantiate it.
3. Update `ajax.php` to `use App\Offer`, `use App\Postback`, etc.

**Do not change:** Class names, method names, or method signatures. Namespacing is purely additive.

---

### 2.3 Service Layer Extraction

**Current state:** `ajax.php` is a 450-line switch statement that mixes HTTP dispatch, business logic calls, and JSON encoding. `Offer.php::addNewOffer()` has inline `echo json_encode(); die()` for validation errors — mixing model and HTTP response concerns.

**Action:**
1. Extract the per-case logic from the switch into named service functions (they already exist as standalone functions in `ajax.php` — move them to a `Service/OfferService.php` class).
2. Replace the inline `echo json_encode(); die()` in `Offer.php::addNewOffer()` with a thrown `ValidationException` or a returned error array. The HTTP response stays in `ajax.php`.

**Constraint:** The `Postback::rotateUrl()` / `get_link_to_display()` logic must not be touched. Extract it into a `RoutingService` that is a thin wrapper if needed, but do not alter the algorithm.

---

### 2.4 Repository Pattern for Database Access

**Current state:** `Database.php` is a generic query builder that all classes use directly. Every class constructs `Database` in its own `__construct()`. SQL strings are scattered across `Offer.php`, `Report.php`, `Filter.php`, etc.

**Action:**
1. Create `repository/OfferRepository.php`, `repository/ClickRepository.php`, etc.
2. Move the raw SQL strings from `Offer.php` into the repository methods.
3. `Offer.php` calls `$this->offerRepo->findBySlug()` instead of `$this->db->join_query('...')`.

**Do not change:** The SQL queries themselves (functional behavior), or the DB schema.

---

### 2.5 Dependency Injection

**Current state:** Every class does `$this->db = new Database()` in its constructor — tight coupling, untestable.

**Action:**
1. Accept `Database $db` as a constructor parameter (with a default of `new Database()` to preserve backward compatibility during transition).
2. In `ajax.php`, construct `Database` once at the top and pass it in:
   ```php
   $db = new Database();
   $offer = new Offer($db);
   ```

**Constraint:** Do not introduce a DI container framework in this step — manual wiring is sufficient and keeps the change minimal.

---

## Phase 3 — Reliability

**Risk: LOW–MEDIUM**  
**Estimated effort: 3–7 days**

### 3.1 Structured Logging (PSR-3 / Monolog)

**Current state:** No logging anywhere. Errors surface only as blank pages or broken JSON.

**Action:**
1. Add `monolog/monolog` via Composer.
2. Create a single logger instance passed into services.
3. Log: DB connection failures, AJAX dispatch errors, postback (click record) failures.
4. **Do not log:** Click data payloads (may contain PII IPs).

**Special note:** `cron.php` currently echoes its output. Replace with `$logger->info('Click aggregated: ...')` so output goes to a log file, not stdout.

---

### 3.2 Unit and Integration Tests

**Priority order for test coverage:**

| Test | Type | Reason |
|------|------|--------|
| `Postback::get_link_to_display()` | Unit | **Must not regress — statistical correctness** |
| `Offer::archiveOffer()` / `deleteOffer()` | Integration | Previously broken (stabilization fix) |
| `Upload::uploadOffer()` | Unit | Complex weight-validation logic |
| `Offer::addNewOffer()` slug collision | Unit | Early-exit logic is subtle |
| `Database::join_query()` zero-row return | Unit | Confirmed PHP 8 bug (Phase 1.4) |

**Weighted routing test strategy:**  
Run `get_link_to_display()` 10,000 times with a known weight distribution and assert the distribution is within ±5% of expected. This is a statistical characterization test — it documents current behavior so any future change is caught.

**Framework:** PHPUnit. No mocking of Database in routing tests — use a real SQLite in-memory DB or a MySQL test schema.

---

### 3.3 Docker

**Action:**
1. `Dockerfile` based on `php:8.2-apache` with `mysqli` and `pdo_mysql` extensions.
2. `docker-compose.yml` with:
   - `app` service (PHP + Apache)
   - `db` service (MySQL 8)
   - Volume for `portal/` source
   - `.env` file mounting
3. `docker-compose up` brings up a fully working local environment.

**Constraint:** The container must reproduce the exact URL structure. Apache rewrite rules must match current behavior.

---

### 3.4 CI/CD

**Action:**
1. GitHub Actions workflow: lint → test → build Docker image.
2. `composer validate` + `phpcs` (PSR-12) in the lint step.
3. PHPUnit test suite in the test step.
4. No auto-deploy until Phase 5 (demo environment) is ready.

---

## Phase 4 — Security

**Risk: MEDIUM–HIGH**  
**Read carefully — some items require data migration.**

### 4.1 Password Hashing ⚠ Data Migration Required

**Current state:** `User.php:12` does a direct DB lookup with the plaintext password as a WHERE condition. Passwords are stored as plaintext in `tbl_user`.

**Migration path:**
1. Add a migration script that reads each existing user row and updates `password` to `password_hash($plaintext, PASSWORD_BCRYPT)`.
2. Change `User::login()` to:
   - Fetch the user by `user_name` only.
   - Verify with `password_verify($input, $stored_hash)`.
3. Run migration before deploying the new login code.

**Risk:** HIGH if deployed without migration. The migration script must run on existing data first.

**Do not change:** `DB_USER_TABLE` constant, `user_name` column name, or session behavior after successful login.

---

### 4.2 Prepared Statements Everywhere ⚠ SQL Injection

**Current state:** `Database::save_data()` builds: `"INSERT INTO ... VALUES ('" . implode("', '", $val) . "')"` — any value in `$val` with a single quote breaks the query or allows injection. `join_query()` and `filter_query()` accept raw SQL strings from callers.

**Affected callsites with user input:**
- `Offer.php::fetchAll()` — `$searchValue` from `$_REQUEST` interpolated into LIKE clause
- `Offer.php::fetchAll()` — `$params['filterValue']` interpolated into WHERE
- `Postback.php:13` — `$params['oid']` (the slug) interpolated directly into JOIN query
- `Filter.php::filterType()` — result queries (low risk — no user input in WHERE)
- `Report.php::getReport()` — `$params['oid']` interpolated into WHERE

**Action:**
1. Replace `save_data()`, `update_data()`, `fetch_data()` with prepared statement equivalents.
2. The `join_query()` / `filter_query()` methods accept raw SQL — keep them but add a parameter-binding overload: `join_query($sql, $types, $params)`.
3. The callers in `Offer.php::fetchAll()` that build LIKE clauses must use `?` placeholders.

**Constraint:** The SQL query structure (JOINs, GROUP BY, ORDER BY, LIMIT/OFFSET pattern) must not change — only parameterize the variable parts.

---

### 4.3 CSRF Protection

**Current state:** All AJAX actions (archive, delete, addNewOffer, editOffer) have no CSRF token. The `ajax.php` switch accepts any POST with a known `requestMethod`.

**Action:**
1. On page load (dashboard), generate a session CSRF token: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`.
2. Embed it in a `<meta>` tag or JS variable.
3. All AJAX calls include it as a header or POST field.
4. `ajax.php` validates it at the top of the switch before dispatching any mutating action.
5. `login` and read-only actions (`fetchAll`, `fetchReport`, `getFilterType`) can be excluded.

---

### 4.4 Session Hardening

**Current state:** `session_start()` with no additional options. Session ID is not regenerated after login. No HttpOnly or Secure cookie flags.

**Action:**
```php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => true,   // HTTPS only in production
]);
session_regenerate_id(true);     // After successful login
```

---

### 4.5 XSS Review

**Current state:** `Offer.php::fetchAll()` builds HTML action buttons by interpolating database values directly into strings:
```php
'value="' . $final_offer_list[$i]["id"] . '"'
```
Offer IDs are integers from the DB, so this is low risk in practice. However, the slug name is also embedded in a hidden input value:
```php
'href="https://efbhalvbhdsurl.com/?oid=' . $final_offer_list[$i]['slug_name'] . '...'
```
Slug names are user-entered strings.

**Action:**
1. Wrap all DB-sourced strings embedded in HTML with `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
2. Wrap URL components with `rawurlencode()`.
3. The `wordwrap()` output in the DataTables cells should also be escaped.

---

### 4.6 Export Files Auth Gate

**Current state:** `export/index.php` and `export/download.php` have no session check. Anyone with the URL can export all offer data and sub-URLs without logging in.

**Action:**  
Add the session check at the top of both files:
```php
session_start();
if (empty($_SESSION['is_login'])) {
    http_response_code(403);
    exit('Unauthorized');
}
```
After Phase 1.4 unifies their DB credentials with `Settings.php`, also remove the hardcoded credential blocks from these files.

---

## Phase 5 — Portfolio Readiness

**Risk: LOW**  
**Estimated effort: 2–3 days**

### 5.1 README

Write a `README.md` at the repo root covering:
- What the system does (offer routing, weighted URL distribution, click tracking)
- Architecture overview (one paragraph)
- Local setup: `docker-compose up`, seed DB, login
- How to add an offer, set weights, verify routing
- How to run tests: `composer test`
- How to run the cron: `php library/cron.php`

---

### 5.2 Architecture Diagram

A simple ASCII or Mermaid diagram showing:
```
Browser → index.php (login)
        → dashboard.php + ajax.php (offer management)
        → export/index.php + download.php (CSV export)

External traffic → url.php → ajax.php (postBack)
                           → Postback::rotateUrl()
                           → get_link_to_display() ← CRITICAL
                           → tbl_click (record)
                           → redirect to winning URL

Cron → cron.php → tbl_click → tbl_report (daily aggregation)
```

---

### 5.3 Deployment Guide

Document:
- LAMP stack requirements (PHP 8.2+, MySQL 8+, Apache)
- `.env` setup from `.env.example`
- Database setup: `mysql < database/schema.sql && mysql < database/seed.sql`
- Apache VirtualHost config with correct `DocumentRoot`
- Cron job entry for `cron.php`
- How to add the first user (until a registration flow exists)

---

### 5.4 Demo Environment

Options in order of preference:
1. **Docker Compose** (Phase 3.3) + `make demo` target that seeds sample data
2. **Railway / Render** free tier PHP + MySQL deployment with a sample dataset
3. **Screencast** (Loom) if live demo infra is not feasible

**Constraint:** Demo data must not include real production slugs, URLs, or click IDs.

---

### 5.5 Screenshots

Capture and commit to `docs/screenshots/`:
- Login page
- Dashboard with offer list (DataTables, pagination, search)
- Add/Edit offer modal with sub-URL + weight configuration
- Archive/Delete confirmation flow
- Report modal
- Filter by Network / Domain / Status
- CSV import flow
- Export batch download page

---

## Sequencing Summary

| Phase | Risk | Blocks | Can parallelize with |
|-------|------|--------|---------------------|
| 1 — Config + low-risk fixes | LOW | Nothing | Can start immediately |
| 2.1 Composer | LOW | Phase 1 | — |
| 2.2 Namespaces | LOW | 2.1 | — |
| 2.3 Service layer | MEDIUM | 2.2 | 3.1 Logging |
| 2.4 Repository | MEDIUM | 2.3 | 4.2 Prepared stmts |
| 2.5 DI | LOW | 2.4 | — |
| 3.1 Logging | LOW | 2.1 | 2.3 |
| 3.2 Tests | MEDIUM | 1.4, 2.5 | 3.3 Docker |
| 3.3 Docker | LOW | Phase 1 | 3.2 |
| 3.4 CI/CD | LOW | 3.2, 3.3 | — |
| 4.1 Password hashing | HIGH | 3.2 | — |
| 4.2 Prepared statements | MEDIUM | 2.4 | 4.3 CSRF |
| 4.3 CSRF | MEDIUM | 2.3 | 4.4 Sessions |
| 4.4 Sessions | LOW | Phase 1 | — |
| 4.5 XSS | LOW | 2.3 | 4.3 |
| 4.6 Export auth | LOW | 1.1 | 4.3 |
| 5.x Portfolio | LOW | Phase 3 | — |

---

## What Must Never Change

| Item | Why |
|------|-----|
| `Postback::get_link_to_display()` algorithm | Core production routing — statistical distribution behavior |
| `tbl_offer_url.slug_name` as the routing key | Matches live traffic URLs (`?oid=<slug>`) |
| `offer_status` values (1/2/3) | All filter logic, archive/delete behavior depends on these |
| `tbl_sub_offer_url.weight` semantics | Weights must sum to 100 for active URLs — enforced in Upload and UI |
| Session key `is_login` | Dashboard and export auth gate check this exact key |
| AJAX `requestMethod` dispatch names | Frontend JavaScript calls these by name |
| Response JSON shape `{"response": true/false, "message": ...}` | Frontend JavaScript parses these exact keys |
