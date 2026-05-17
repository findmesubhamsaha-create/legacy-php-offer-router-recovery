# Modernization Plan

## Guiding Principle

This plan describes how to modernize the system's infrastructure, security, and maintainability **without altering any business logic**. The weighted routing algorithm, offer lifecycle (Active/Archived/Deleted), weight invariant (sum = 100), click recording model, and CSV import/export format must be preserved exactly.

Modernization is separated into phases so each can be reviewed and deployed independently.

---

## Phase 0: Immediate Risk Reduction (No Refactor Required)

These changes reduce active security risk with minimal code touch.

### 0.1 — Hash existing passwords

Run once against the live database:

```sql
UPDATE tbl_user SET password = SHA2(password, 256);
```

Then update `User.php` to compare hashed input:

```php
$password_hash = hash('sha256', $params['password']);
// pass $password_hash into the query instead of plaintext
```

Longer term: migrate to `password_hash()` / `password_verify()` with bcrypt. This is a one-file change in `library/User.php`.

### 0.2 — Move credentials out of source code

Create a `config.php` (excluded from git via `.gitignore`) that defines the DB constants, and `require` it from `Settings.php`. No other file changes needed:

```php
// config.php (not committed)
define('DB_HOST',     'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'KDms@jY7Gw');
define('DB_NAME',     'efbhalvbhdsurl');

// Settings.php
require_once __DIR__ . '/../config.php';
```

### 0.3 — Add session check to unprotected endpoints

`ajax.php`, `import.php`, `export/index.php`, and `export/download.php` currently have no authentication gate. Add `session_start()` + the same guard from `dashboard.php` to each.

### 0.4 — Regenerate session ID on login

Add one line to `ajax.php` after setting `$_SESSION["is_login"]`:

```php
session_regenerate_id(true);
```

### 0.5 — Clean up backup files

Move `backup/`, dated PHP files (`ajax(bkp-*.php)`, etc.), and `test.php` out of the web root or into a `.gitignore`-d directory. These expose old code and credentials to anyone who can guess a filename.

---

## Phase 1: Database Security (Preserves All Logic)

### 1.1 — Replace string-concatenated queries with prepared statements

The `Database` class (`library/database/Database.php`) is the single place where all queries are built. Rewriting its internal methods to use MySQLi prepared statements fixes SQL injection across the entire application without touching any business logic:

- `fetch_data()` → use `bind_param()`
- `save_data()` → use `bind_param()`
- `update_data()` → use `bind_param()`
- `join_query()` / `filter_query()` — these accept raw SQL strings; callers in `Offer.php` and `Postback.php` must be updated to pass parameterized versions

No method signatures need to change externally. This is an internal rewrite of one class.

### 1.2 — Add missing database indexes

```sql
ALTER TABLE tbl_offer_url     ADD INDEX idx_slug   (slug_name);
ALTER TABLE tbl_offer_url     ADD INDEX idx_status (offer_status);
ALTER TABLE tbl_click         ADD INDEX idx_offer_date (offer_id, created_at);
ALTER TABLE tbl_sub_offer_url ADD INDEX idx_main   (main_offer_id, status, deleted_status);
```

No code changes. Routing query performance improves significantly at scale.

### 1.3 — Add UNIQUE constraint on click_id

```sql
ALTER TABLE tbl_click ADD UNIQUE KEY uq_click_id (click_id);
```

Prevents double-counting from retry storms. Application code may need a try/catch on the INSERT, but the routing logic itself is unchanged.

---

## Phase 2: Code Quality and Maintainability

### 2.1 — Introduce Composer autoloading

Add a `composer.json` with PSR-4 autoloading. Map `Library\` to `library/`. Replace all explicit `require` chains in `ajax.php` with a single `require 'vendor/autoload.php'`. No class logic changes.

```json
{
    "autoload": {
        "psr-4": {
            "Library\\": "library/"
        }
    }
}
```

### 2.2 — Environment-based configuration

Replace `library/Settings.php` constants with a `.env` file read by `vlucas/phpdotenv`:

```
DB_HOST=localhost
DB_USERNAME=admin
DB_PASSWORD=secret
DB_NAME=efbhalvbhdsurl
```

```php
// bootstrap.php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('DB_HOST',     $_ENV['DB_HOST']);
// ...
```

The constant names remain the same — all other code is untouched.

### 2.3 — Consolidate duplicate files

`Postback.php` and `PostbackBeta.php` implement the same routing algorithm with minor variations. Determine which is canonical and remove (or formally deprecate) the other. Keep the algorithm identical.

### 2.4 — CSRF protection on all POST actions

Add a token to every form and AJAX POST. `ajax.php` verifies the token before dispatching:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit;
    }
}
```

Business logic in `Offer.php`, `User.php`, etc. is untouched.

---

## Phase 3: Architecture Separation

### 3.1 — Introduce a front controller

Move all routing from scattered includes in `ajax.php` into a single `index.php` front controller with a proper dispatch table. All handler functions remain as-is; only the dispatch mechanism changes.

```
Before: ajax.php uses a long if/elseif chain on $_REQUEST['requestMethod']
After:  Router class maps request method strings → handler callables
```

### 3.2 — Separate HTML templates from PHP logic

`dashboard.php` mixes PHP session checking, HTML, inline JavaScript, and data queries. Extract the HTML into a `templates/` directory. PHP files become controllers that set variables and `include` templates.

### 3.3 — Add a migration system

Use [Phinx](https://phinx.org/) or a minimal custom migration runner. Convert the inferred DDL from `DATABASE_INFERENCE.md` into versioned migration files. Enables reproducible database setup on any environment.

---

## Phase 4: Testing

### 4.1 — Unit tests for the routing algorithm

`Postback::get_link_to_display()` is pure logic. Write PHPUnit tests that verify:
- Single URL with weight 100 always returns that URL
- Weight distribution matches expected statistical ranges across N iterations
- Empty input is handled without PHP warnings
- Weights not summing to 100 produce defined behavior

No changes to production code needed for this phase.

### 4.2 — Integration tests for offer CRUD

Write tests that hit a test database and verify:
- Creating an offer stores correct rows in `tbl_offer_url` and `tbl_sub_offer_url`
- Archiving an offer excludes it from routing queries
- Weight validation rejects invalid distributions

### 4.3 — End-to-end test for click routing

Simulate a routing request and verify:
- The correct offer is looked up by slug
- A sub-URL is returned
- A row is inserted into `tbl_click`

---

## Phase 5: Infrastructure (Production-Ready Deployment)

### 5.1 — Containerize with Docker

```
Dockerfile:     PHP 8.2-apache + mysqli extension
docker-compose: php-app + mysql:8 services
                Mounts config via environment variables
```

The application code is unchanged. The container sets environment variables that `Settings.php` reads.

### 5.2 — Serve redirects server-side

The current routing returns a URL via JSON and relies on JavaScript to redirect. Replace with a server-side `header("Location: $url", true, 302)` in `ajax.php`'s `postBack` handler. The routing algorithm in `Postback.php` is untouched — only the response type changes. This makes the redirect work for non-JS clients and bots.

### 5.3 — Rate limiting on the routing endpoint

Add rate limiting to `ajax.php?requestMethod=postBack` at the web server or reverse proxy level (nginx `limit_req`, Cloudflare rules, etc.). No application code change required.

### 5.4 — Structured logging

Replace silent failures with a PSR-3 compatible logger (e.g., Monolog). Log:
- Failed login attempts (with IP)
- Routing requests (slug, selected URL, click_id)
- Cron aggregation runs (rows processed, errors)

---

## Modernization Priority Matrix

| Phase | Risk | Effort | Business Impact |
|-------|------|--------|-----------------|
| 0 — Immediate risk reduction | Critical | Low | None |
| 1 — DB security | Critical | Medium | None |
| 2 — Code quality | Medium | Medium | None |
| 3 — Architecture | Low | High | None |
| 4 — Testing | Medium | Medium | Prevents regressions |
| 5 — Infrastructure | Low | High | Enables scaling |

Phases 0 and 1 should be completed before any public exposure of the system. Phases 2–5 can be done incrementally in any order.

---

## What Must Never Change

The following behaviors define the product and must be preserved exactly across all modernization work:

| Behavior | Location | Reason |
|----------|----------|--------|
| Weighted random URL selection | `Postback::get_link_to_display()` | Core traffic distribution logic |
| Weight sum = 100 invariant | `Offer.php`, `Upload.php` | Business constraint |
| Offer status codes (1/2/3) | Throughout | Status semantics drive routing and UI |
| Click recording schema | `tbl_click` columns | Reporting depends on this structure |
| CSV import column format | `Upload.php` | External systems may generate these files |
| Slug-based routing key | `tbl_offer_url.slug_name` | External click links use `?oid=<slug>` |
| Daily aggregation model | `cron.php` + `tbl_report` | Report display logic depends on this |
