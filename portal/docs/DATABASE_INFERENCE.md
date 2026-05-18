# Database Inference

No SQL migration files exist in this repository. The schema below is **entirely inferred** from:

- SQL strings embedded in `library/Offer.php`, `library/Postback.php`, `library/Report.php`, `library/Filter.php`, `library/Upload.php`, `library/cron.php`, and `export/download.php`
- Column names referenced in PHP array keys passed to `Database::save_data()` and `Database::update_data()`
- Validation logic in `library/Upload.php` that describes accepted field types and constraints
- Constant definitions in `library/Settings.php`

---

## Database Name

```
efbhalvbhdsurl
```

Defined in `library/Settings.php` as `DB_NAME`.

---

## Tables

### `tbl_user`

Authentication table. One row per admin account.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_name | VARCHAR(255) UNIQUE | Login username |
| password | VARCHAR(255) | Stored in plaintext — no hashing |

**Evidence:** `library/User.php` queries `WHERE user_name = ? AND password = ?`; `DB_USER_TABLE` constant in Settings.php.

---

### `tbl_tag`

Lookup table for offer tags / categories.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| tag_name | VARCHAR(255) | Display name |
| created_at | DATE | Insertion date |

**Evidence:** `Offer.php` — `INSERT INTO tbl_tag (tag_name, created_at)` when a new tag string is encountered; `SELECT id FROM tbl_tag WHERE tag_name = ?` to check existence before inserting.

---

### `tbl_network`

Lookup table for advertiser networks / traffic sources.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| network_name | VARCHAR(255) | Display name |
| created_at | DATE | Insertion date |

**Evidence:** Same pattern as `tbl_tag` in `Offer.php`. `Filter.php` — `SELECT DISTINCT(network_name) FROM tbl_network`.

---

### `tbl_offer_url`

Primary offer table. One row per logical offer.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| offer | VARCHAR(255) | Human-readable offer name |
| slug_name | VARCHAR(255) | URL-safe identifier used in routing (`?oid=<slug>`) |
| tag_id | INT FK → tbl_tag | |
| note | TEXT | Free-form notes |
| network_id | INT FK → tbl_network | |
| offer_status | TINYINT | 1 = Active, 2 = Archived, 3 = Deleted |
| status_updated_at | DATE | Last status-change date |
| start_date | DATE | Campaign start (optional) |
| end_date | DATE | Campaign end (optional) |

**Status semantics:**
- `1` (Active) — visible in dashboard by default, participates in traffic routing
- `2` (Archived) — hidden from default view, excluded from routing, restorable
- `3` (Deleted) — excluded from routing, soft-deleted (row kept in DB)

**Evidence:** `Offer.php::addNewOffer`, `archiveOffer`, `deleteOffer`, `resetOffer`; routing guard `WHERE m.offer_status = '1'` in `Postback.php`; upload validation in `Upload.php`.

**Uniqueness rule (from code):** `offer` and `slug_name` must be unique **among active offers only** (`offer_status = 1`). Archived/deleted offers may share names with new offers.

---

### `tbl_sub_offer_url`

Child table — each row is one destination URL for an offer. An offer has 1–N sub-URLs.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| main_offer_id | INT FK → tbl_offer_url | Parent offer |
| sub_url | TEXT | Full redirect destination URL |
| weight | DECIMAL(7,4) | Traffic share percentage (0–100); all active rows must sum to 100 |
| status | ENUM('yes','no') | Whether this URL participates in routing |
| deleted_status | ENUM('yes','no') | Soft-delete flag |

**Weight invariant (from Upload.php and Offer.php):** Sum of `weight` where `status = 'yes'` AND `deleted_status = 'no'` must equal exactly 100.0 (±0.0001 floating-point tolerance).

**Evidence:** `Postback.php` — `WHERE s.status = 'yes'`; `Offer.php` — `UPDATE tbl_sub_offer_url SET deleted_status = 'yes', weight = 0, status = 'no'`; export query joins on `deleted_status = 'no'`.

---

### `tbl_click`

Raw click event log. One row per inbound traffic event.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| click_id | VARCHAR(255) | Caller-supplied unique ID (UUID or external tracking ID) |
| offer_id | INT FK → tbl_offer_url | Which offer received the click |
| sub_offer_id | INT FK → tbl_sub_offer_url | Which sub-URL was selected for this click |
| ip_address | VARCHAR(45) | Visitor IP (IPv4 or IPv6) |
| created_at | DATE | Date of click (date only, no time component) |

**Evidence:** `Postback.php::rotateUrl` — `save_data('tbl_click', [...])` after URL selection; `cron.php` groups by `created_at, offer_id`; export query joins `tbl_click`.

**Note on `created_at`:** Stored as `DATE` (not `DATETIME`). Precision is day-level only. This means multiple clicks in one day are only distinguishable by `click_id` and `id`, not by timestamp.

---

### `tbl_report`

Pre-aggregated daily click totals per offer. Written by `library/cron.php`.

| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| main_offer_id | INT FK → tbl_offer_url | |
| main_offer_url | VARCHAR(255) | Denormalized offer name (snapshot at aggregation time) |
| offer_clicks | INT | Total clicks for this offer on this date |
| report_date | DATE | Aggregation date |

**Unique constraint:** `(main_offer_id, report_date)` — enforces one row per offer per day, enabling `ON DUPLICATE KEY UPDATE`.

**Evidence:** `cron.php` — `INSERT INTO tbl_report ... ON DUPLICATE KEY UPDATE offer_clicks = ?`; `Report.php::getReport` — `SELECT ... WHERE main_offer_id = ?`.

**Relationship to tbl_click:** `tbl_report` is a materialized summary of `tbl_click`. If the cron job does not run, `tbl_report` will be stale. `tbl_click` is the authoritative source.

---

## Entity Relationship Summary

```
tbl_user
  (no FK relationships — standalone auth table)

tbl_tag ─────────────┐
tbl_network ─────────┤
                     ↓
              tbl_offer_url (one offer)
                     │
                     ├──── tbl_sub_offer_url (many sub-URLs per offer)
                     │
                     └──── tbl_click (many clicks per offer)
                                │
                                └── also FK to tbl_sub_offer_url

tbl_report (aggregated from tbl_click, keyed by tbl_offer_url.id)
```

---

## Missing Configurations / Gaps Inferred

### No index definitions found
The code performs frequent lookups by `slug_name` (every routing request) and `offer_status`. These columns likely need indexes in production:

```sql
ALTER TABLE tbl_offer_url ADD INDEX idx_slug (slug_name);
ALTER TABLE tbl_offer_url ADD INDEX idx_status (offer_status);
ALTER TABLE tbl_click ADD INDEX idx_offer_date (offer_id, created_at);
ALTER TABLE tbl_report ADD INDEX idx_offer_date (main_offer_id, report_date);
```

### No foreign key enforcement verified
The code uses `save_data` with raw INSERTs. Whether FK constraints are actually enforced in the schema depends on whether the engine was InnoDB at creation time. MyISAM would silently ignore FK constraints.

### `click_id` is caller-supplied, not auto-generated
The routing endpoint receives `click_id` from the caller as a query parameter. There is no uniqueness constraint on this column and no server-side generation. Duplicate `click_id` values are possible.

### `created_at` in `tbl_click` is `DATE` not `DATETIME`
The cron script and click recorder both use `date('Y-m-d')` (PHP) and `CURDATE()` (MySQL). No time-of-day information is preserved. This is intentional for daily aggregation but prevents hourly analytics.

### `tbl_report.main_offer_url` is denormalized
The cron script copies the offer name string into `tbl_report` at aggregation time. If an offer is renamed after clicks are recorded, historical report rows will show the old name. The canonical name is always `tbl_offer_url.offer`.

### No session table
Session state is managed entirely via PHP's default file-based session handler. There is no `tbl_session` or database-backed session store.
