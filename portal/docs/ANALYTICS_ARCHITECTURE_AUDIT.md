# Analytics Architecture Audit
## Offer Router Platform — Pre-Redesign Observability Assessment

**Branch:** `product-ui-v2`  
**Date:** 2026-05-23  
**Scope:** Routing engine, click tracking, DB schema, reporting pipeline, KPI gaps  
**Files Traced:** `index.php`, `Postback.php`, `PostbackBeta.php`, `Offer.php`, `Report.php`, `Filter.php`, `cron.php`, `ajax.php`, `Database.php`, `Settings.php`

---

## 1. Routing Flow

### Entry Point

Every piece of traffic hits `index.php` with a URL of the form:

```
GET /?oid=<slug>&click_id=<affiliate_id>&tag=<tag>&affid=<network>
```

### Step-by-step execution

```
index.php
  ├── ob_start()                                          # buffer prevents header-after-output errors
  ├── parse_url() + parse_str() to extract query params
  ├── unset $params['oid']                                # removes oid from passthrough params
  ├── Postback::rotateUrl(['oid'=>slug, 'ip'=>REMOTE_ADDR, 'click_id'=>click_id])
  │     │
  │     ├── QUERY 1 — fetch active sub-offers by slug
  │     │   SELECT s.sub_url, s.weight
  │     │   FROM tbl_sub_offer_url s
  │     │   JOIN tbl_offer_url m ON s.main_offer_id = m.id
  │     │   WHERE m.slug_name = '<slug>'
  │     │     AND m.offer_status = '1'
  │     │     AND s.status = 'yes'
  │     │   ORDER BY s.id ASC
  │     │
  │     ├── get_link_to_display($results)                # weighted random selection → $winning_url
  │     │
  │     ├── QUERY 2 — resolve winning URL to IDs
  │     │   SELECT s.main_offer_id, s.id
  │     │   FROM tbl_sub_offer_url s
  │     │   JOIN tbl_offer_url m ON s.main_offer_id = m.id
  │     │   WHERE s.sub_url = '<winning_url>'
  │     │     AND m.offer_status = '1'
  │     │     AND s.status = 'yes'
  │     │
  │     └── QUERY 3 — log click event
  │         INSERT INTO tbl_click
  │           (click_id, offer_id, sub_offer_id, ip_address, created_at)
  │         VALUES (...)
  │
  ├── if $winning_url → header("Location: $winning_url?$passthrough_params")
  └── else            → header("Location: 404.php?$passthrough_params")
```

**Every redirect costs exactly 3 sequential DB round-trips.** There is no caching layer.

### Weighted Random Selection Algorithm

Located in `Postback::get_link_to_display()` (`Postback.php:48–56`):

```php
$rand = rand(0, 100 - 1);            // generates 0–99
foreach ($sites as $site => $weight) {
    $rand -= $weight['weight'];
    if ($rand < 0) break;
}
return $weight['sub_url'];           // variable shadowing: $weight reused as loop variable
```

**How it works:** Traverses sub-offers in `id ASC` order, subtracting each weight from a random number in `[0, 99]`. First sub-offer whose running subtraction pushes `$rand` below zero wins.

**Ordering dependency:** Results are sorted `ORDER BY s.id ASC`. The first sub-offer in insertion order has a slight probability advantage at the edge case where `$rand = 0`.

**Edge cases:**
| Scenario | Effect |
|---|---|
| Sum of active weights = 100 | Works correctly |
| Sum < 100 (e.g., 90) | Last sub-offer gets last `100 - sum` = 10% phantom traffic |
| Sum > 100 (e.g., 110) | Last sub-offers may never be selected; `$rand` never reaches < 0 on them |
| All sub-offers inactive | `$get_sub_offers` is empty → `get_link_to_display()` returns `null` → no redirect |

**Race condition window:** There is a gap between Query 1 (pick a URL) and Query 3 (log the click). If a sub-offer is deactivated between those two queries, the click is logged with the correct `sub_offer_id` but that sub-offer is already disabled. This is a minor data consistency issue, not a crash.

---

## 2. Click Architecture

### What is tracked on each redirect

Every successful redirect inserts one row into `tbl_click`:

| Column | Source | Notes |
|---|---|---|
| `click_id` | `$_REQUEST['click_id']` | Affiliate network's tracking token. Stored. Never surfaced. |
| `offer_id` | Resolved from winning URL → `tbl_offer_url.id` | Per-offer attribution |
| `sub_offer_id` | Resolved from winning URL → `tbl_sub_offer_url.id` | Per-sub-offer attribution |
| `ip_address` | `$_SERVER['REMOTE_ADDR']` | Raw IP. Stored. Never queried. |
| `created_at` | `date('Y-m-d')` | **DATE only. No time component.** |

### Granularity summary

| Level | Tracked? | Surfaced in Dashboard? |
|---|---|---|
| Per offer (main) | Yes | Yes — `COUNT(d.offer_id)` in `Offer::fetchAll()` |
| Per sub-offer | Yes — `sub_offer_id` in `tbl_click` | **No — never joined in any report query** |
| Per route (slug) | Implicit via offer_id | No separate route table |
| Per affiliate click_id | Yes — stored in `tbl_click.click_id` | **No** |
| Per IP address | Yes — stored in `tbl_click.ip_address` | **No** |
| Intraday / hourly | **No** — DATE field, no time | N/A |
| Per network | Possible via offer→network join | No query built |
| Per tag | Possible via offer→tag join | No query built |

---

## 3. Database Tables (Inferred Schema)

No schema migration files were found. The following is inferred entirely from query patterns across all PHP files.

### `tbl_offer_url` — Main offers

| Column | Type (inferred) | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | |
| `offer` | VARCHAR | Offer display name |
| `slug_name` | VARCHAR | Routing key — matched in Query 1 of every redirect |
| `tag_id` | INT FK → `tbl_tag.id` | |
| `note` | TEXT | Free-text note |
| `network_id` | INT FK → `tbl_network.id` | |
| `start_date` | DATE | Not enforced in routing logic |
| `end_date` | DATE | Not enforced in routing logic |
| `offer_status` | TINYINT | 1=Active, 2=Archived, 3=Deleted |
| `status_updated_at` | DATE | Written on archive/delete/reset |

Constant: `DB_OFFER_TABLE = 'tbl_offer_url'` (`Settings.php:46`).

### `tbl_sub_offer_url` — Sub-offers (destination URLs)

| Column | Type (inferred) | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | Insertion order affects routing (ORDER BY id ASC) |
| `main_offer_id` | INT FK → `tbl_offer_url.id` | |
| `sub_url` | VARCHAR | Destination URL — the actual redirect target |
| `weight` | INT | Routing weight (target: sum of active = 100) |
| `status` | ENUM('yes','no') | 'yes' = participates in routing |
| `deleted_status` | ENUM('yes','no') | Soft-delete flag |

### `tbl_click` — Raw click events (primary analytics table)

| Column | Type (inferred) | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | |
| `click_id` | VARCHAR | Affiliate network's click tracking token |
| `offer_id` | INT FK → `tbl_offer_url.id` | |
| `sub_offer_id` | INT FK → `tbl_sub_offer_url.id` | **Tracked but never reported** |
| `ip_address` | VARCHAR | Raw string. No INET_ATON. No geo lookup. |
| `created_at` | DATE | **Date only. Time is lost.** |

### `tbl_report` — Daily click rollup (reporting table)

| Column | Type (inferred) | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | |
| `main_offer_id` | INT FK → `tbl_offer_url.id` | |
| `main_offer_url` | VARCHAR | **Misleading name — stores offer NAME, not a URL** |
| `offer_clicks` | INT | Aggregated click count for the day |
| `report_date` | DATE | One row per offer per day |

### `tbl_tag`

| Column | Type (inferred) |
|---|---|
| `id` | INT PK AUTO_INCREMENT |
| `tag_name` | VARCHAR |
| `created_at` | DATE |

### `tbl_network`

| Column | Type (inferred) |
|---|---|
| `id` | INT PK AUTO_INCREMENT |
| `network_name` | VARCHAR |
| `created_at` | DATE |

### `tbl_user`

Referenced as constant `DB_USER_TABLE = 'tbl_user'`. Schema not traced — used only by `User.php`.

### Notable absence: No `tbl_conversion` table

Despite the class being named `Postback`, no conversion/postback event is ever fired to an upstream affiliate network. The name is a misnomer — `Postback::rotateUrl()` is the routing + click-logging engine, not a postback handler.

---

## 4. Reporting Pipeline

### Dashboard click counts (real-time via DataTable)

`Offer::fetchAll()` (`Offer.php:335–338`) uses a LEFT JOIN directly on `tbl_click`:

```sql
SELECT a.*, b.tag_name, c.network_name, COUNT(d.offer_id) clicks
FROM tbl_offer_url a
LEFT JOIN tbl_tag b ON a.tag_id = b.id
LEFT JOIN tbl_network c ON a.network_id = c.id
LEFT JOIN tbl_click d ON a.id = d.offer_id
WHERE a.offer_status = 1
GROUP BY a.id
ORDER BY a.id ASC
LIMIT <start>, <length>
```

**This is an all-time total click count.** No date filter. No per-sub-offer breakdown. The `COUNT(d.offer_id)` counts every row in `tbl_click` for that offer, regardless of date.

### Report view (per-offer history)

`Report::getReport()` (`Report.php:15`):

```sql
SELECT main_offer_id, main_offer_url, offer_clicks, report_date
FROM tbl_report
WHERE main_offer_id = '<oid>'
ORDER BY id ASC
```

Returns one row per day that the cron ran. There is no date-range filter, no GROUP BY, no aggregation — it returns the raw daily rollup rows.

**The `main_offer_url` column stores the offer name (copied from `tbl_offer_url.offer` at cron time), not any URL.** This is a naming bug that will confuse any new engineer.

### Cron aggregation job (`cron.php`)

```
Triggered:    Manually or via system cron (schedule unknown — not defined in code)
Connects:     Direct mysqli connection (bypasses Database.php ORM)
Logic:        SELECT COUNT from tbl_click WHERE created_at = TODAY, GROUP BY offer_id
              → For each offer with clicks today:
                  IF row exists in tbl_report for today → UPDATE offer_clicks
                  ELSE → INSERT new row
Output:       echo '<pre>' + text (not suitable for headless cron — HTML output will corrupt logs)
```

**Critical observation:** `tbl_report` is only as fresh as the last cron execution. If the cron hasn't run today, `tbl_report` for today does not exist. The dashboard's "View Report" modal shows zero for today until cron runs.

### Comments in Report.php reveal a planned-but-abandoned feature

```php
// SELECT main_offer_url, SUM(sub_offer_clicks) sub_offer_clicks, report_date
// FROM tbl_report WHERE main_offer_id=27 GROUP BY report_date
```

The column `sub_offer_clicks` does not exist in the current `tbl_report` schema. This was a planned per-sub-offer rollup that was never implemented.

---

## 5. Available KPIs (What Exists Today)

| KPI | Source | Accuracy | Limitations |
|---|---|---|---|
| All-time clicks per offer | `COUNT(tbl_click.offer_id)` in `fetchAll` | Live | No date filter; grows unbounded |
| Daily clicks per offer | `tbl_report.offer_clicks` | Day-old | Only updated by cron; no intraday |
| Offer count by status | `COUNT(*) from tbl_offer_url WHERE offer_status=N` | Live | |
| Sub-offer list per offer | `tbl_sub_offer_url` | Live | No click breakdown per sub-offer |
| Offers per network | `GROUP BY network_id` | Live | Not surfaced as a KPI widget |
| Offers per tag | `GROUP BY tag_id` | Live | Not surfaced |
| Offer date range | `start_date`, `end_date` in `tbl_offer_url` | Static | Not enforced in routing |
| Top domains in rotation | `Filter::filterType('Domain')` | Live | Used for filtering only, not analytics |

---

## 6. Missing KPIs

### Routing & distribution

| KPI | Why it Matters | Blocker |
|---|---|---|
| Clicks per sub-offer | See if weight distribution is working as intended | `sub_offer_id` in `tbl_click` — query not built |
| Configured weight vs actual routing % | Validate the algorithm; catch drift | Requires aggregation of `tbl_click.sub_offer_id` |
| Sub-offer click share over time | Identify if one destination is dominating | Requires date range query on `tbl_click.sub_offer_id` |

### Traffic quality & fraud

| KPI | Why it Matters | Blocker |
|---|---|---|
| Duplicate clicks per IP | Detect bot/proxy fraud | `ip_address` in `tbl_click` — never queried |
| Unique vs total click ratio | Traffic quality signal | Requires DISTINCT on ip_address |
| Clicks per click_id (affiliate token) | Detect replayed or recycled tokens | `click_id` in `tbl_click` — never queried |

### Time-series analysis

| KPI | Why it Matters | Blocker |
|---|---|---|
| Clicks by hour of day | Peak traffic windows, fraud spikes | `created_at` is DATE — **time is permanently lost** |
| Click velocity (last 60 min) | Real-time monitoring | Same as above |
| Weekly / monthly trends | Campaign performance over time | Data exists in `tbl_report` — no query built |
| Click trends chart per offer | Visual campaign health | Data exists — no query or UI built |

### Attribution & revenue

| KPI | Why it Matters | Blocker |
|---|---|---|
| Clicks per affiliate network | Which networks send the most traffic | Requires join `tbl_click → tbl_offer_url → tbl_network` — not built |
| Conversions / postback events | Actual revenue signal | **No `tbl_conversion` table exists** |
| Click-to-conversion rate | Core ad-tech metric | Same — requires conversion table |
| Revenue per offer | P&L per campaign | No financial columns anywhere |

### Operational

| KPI | Why it Matters | Blocker |
|---|---|---|
| Last cron run timestamp | Know if `tbl_report` is stale | Cron does not write a heartbeat timestamp |
| Offer status change history | Audit trail for archived/deleted | `status_updated_at` exists but only one value — no history table |
| Sub-offer availability / uptime | Know if a destination URL is down | No health-check mechanism |
| Slug collision audit | Archived slugs can share name with active ones | Logic exists (`offer_status=1` filter) but no report |

---

## 7. Suggested DB Enhancements

These are **schema-level changes only**. They do not change routing logic.

### Priority 1 — Fix data loss (immediate impact)

**1. Add DATETIME to `tbl_click`**

```sql
ALTER TABLE tbl_click
  ADD COLUMN clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
```

Keeps `created_at DATE` for backward compatibility. New `clicked_at DATETIME` enables every hourly/realtime query.

**2. Add sub-offer click column to `tbl_report`**

```sql
ALTER TABLE tbl_report
  ADD COLUMN sub_offer_id INT NULL,
  ADD COLUMN sub_offer_clicks INT NOT NULL DEFAULT 0,
  ADD COLUMN sub_offer_url VARCHAR(2048) NULL;
```

Enables per-sub-offer daily rollup. Cron query would need a corresponding `GROUP BY offer_id, sub_offer_id` variant.

### Priority 2 — Fix misleading naming

**3. Rename `tbl_report.main_offer_url` to `main_offer_name`**

```sql
ALTER TABLE tbl_report
  CHANGE main_offer_url main_offer_name VARCHAR(255);
```

The column stores `tbl_offer_url.offer` (a name like "Campaign Alpha"), not a URL.

### Priority 3 — Indexes for performance

Currently, every page load runs:
```sql
LEFT JOIN tbl_click d ON a.id = d.offer_id   -- full scan of tbl_click on each offer list load
```

And every redirect runs:
```sql
WHERE m.slug_name = '<slug>'                  -- lookup on tbl_offer_url without confirmed index
```

**4. Add covering indexes**

```sql
-- Routing query performance (hit on every redirect)
ALTER TABLE tbl_offer_url
  ADD INDEX idx_slug_status (slug_name, offer_status);

-- Click aggregation (hit on every dashboard load)
ALTER TABLE tbl_click
  ADD INDEX idx_offer_date (offer_id, created_at);

-- Sub-offer click analysis
ALTER TABLE tbl_click
  ADD INDEX idx_sub_offer (sub_offer_id);

-- Cron rollup query
ALTER TABLE tbl_report
  ADD INDEX idx_report_lookup (main_offer_id, report_date);
```

### Priority 4 — New tables for missing features

**5. Conversion / postback events table**

```sql
CREATE TABLE tbl_conversion (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  click_id      VARCHAR(255),               -- affiliate network click token
  offer_id      INT,
  sub_offer_id  INT,
  ip_address    VARCHAR(45),
  payout        DECIMAL(10,4) DEFAULT 0.00,
  converted_at  DATETIME NOT NULL,
  source        VARCHAR(50),                -- 'postback', 'pixel', 'api'
  raw_payload   TEXT,                       -- original callback body
  INDEX (click_id),
  INDEX (offer_id, converted_at)
);
```

**6. Offer status history table**

```sql
CREATE TABLE tbl_offer_status_history (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  offer_id    INT NOT NULL,
  old_status  TINYINT,
  new_status  TINYINT,
  changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (offer_id)
);
```

**7. Cron heartbeat / run log**

```sql
CREATE TABLE tbl_cron_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  job_name   VARCHAR(50),
  started_at DATETIME,
  ended_at   DATETIME,
  rows_processed INT DEFAULT 0,
  status     ENUM('success','error') DEFAULT 'success',
  message    TEXT
);
```

---

## 8. Suggested Dashboard Metrics (for Product Redesign)

### Summary tiles (top of dashboard)

| Widget | Query Basis |
|---|---|
| Total clicks today | `SELECT COUNT(*) FROM tbl_click WHERE created_at = CURDATE()` |
| Total active offers | `SELECT COUNT(*) FROM tbl_offer_url WHERE offer_status = 1` |
| Active sub-URLs in rotation | `SELECT COUNT(*) FROM tbl_sub_offer_url WHERE status='yes' AND deleted_status='no'` |
| Unique IPs today | `SELECT COUNT(DISTINCT ip_address) FROM tbl_click WHERE created_at = CURDATE()` |

### Offer table enhancements

| Metric | Query Addition |
|---|---|
| Clicks today | Add `WHERE d.created_at = CURDATE()` branch |
| Clicks this week | Add 7-day rolling window |
| Sub-offer count | `COUNT(s.id)` from `tbl_sub_offer_url` |
| Active sub-offer count | Filter `status='yes'` |
| Weight validity flag | Check if SUM(weight) WHERE status='yes' = 100 |

### Sub-offer performance panel (per offer drill-down)

| Metric | Source |
|---|---|
| Clicks per sub-offer (all time) | `GROUP BY sub_offer_id` on `tbl_click` |
| Actual routing % vs configured weight | `(clicks/total_clicks)*100` vs `weight` |
| Sub-offer click trend (daily) | `GROUP BY sub_offer_id, created_at` |
| Enabled / disabled status | `tbl_sub_offer_url.status` |

### Time-series chart (requires `clicked_at DATETIME` column)

| Chart | Grouping |
|---|---|
| Hourly clicks today | `GROUP BY HOUR(clicked_at)` |
| Daily clicks last 30 days | `GROUP BY DATE(clicked_at)` (or use `tbl_report`) |
| Weekly trend last 12 weeks | `GROUP BY YEARWEEK(clicked_at)` |

### Traffic quality panel

| Metric | Source |
|---|---|
| Duplicate click rate (same IP, same offer) | `COUNT(*) / COUNT(DISTINCT ip_address)` |
| Top 10 IPs by click volume | `GROUP BY ip_address ORDER BY COUNT DESC` |
| Clicks with blank `click_id` | `WHERE click_id = '' OR click_id IS NULL` |

### Network breakdown panel

| Metric | Query Basis |
|---|---|
| Clicks per network | JOIN `tbl_click → tbl_offer_url → tbl_network`, GROUP BY network_name |
| Active offers per network | COUNT from `tbl_offer_url` GROUP BY network_id |

---

## 9. Known Defects Observed (Non-Routing, Observability-Affecting)

| ID | File | Issue | Impact |
|---|---|---|---|
| A-01 | `tbl_click` | `created_at` is DATE, not DATETIME | All intraday analytics permanently impossible on historical data |
| A-02 | `tbl_report` | `main_offer_url` stores offer name not a URL | Misleads any engineer writing report queries |
| A-03 | `cron.php` | `echo '<pre>'` in cron body | Corrupts cron logs; not suitable for headless execution |
| A-04 | `cron.php` | No schedule, no lock file, no heartbeat table | Cannot tell when cron last ran or if it's running twice |
| A-05 | `Postback.php` | Two-query pattern (fetch URL, then re-fetch IDs) | Race condition window; adds one DB RTT per redirect |
| A-06 | `Postback.php` | `rand(0, 100-1)` assumes weights sum to exactly 100 | Weight drift causes silent distribution errors |
| A-07 | `Report.php` | `tbl_report` queried with no date range | Report panel loads all historical rows; unbounded as data grows |
| A-08 | `Offer.php` | `start_date`/`end_date` stored but not checked in routing | Expired offers keep routing traffic silently |
| A-09 | `Postback.php` | No actual HTTP postback fired to affiliate network | Class name "Postback" is misleading for entire codebase |
| A-10 | `tbl_click` | `click_id` (affiliate token) stored but never surfaced | Attribution chain to conversion is broken at the DB query layer |

---

## Summary

The routing engine is functional and the click data model is structurally sound — `tbl_click` captures the right foreign keys (`offer_id`, `sub_offer_id`, `click_id`, `ip_address`). The core problem is that **the data is collected but not exposed**: sub-offer clicks, affiliate tokens, and IP signals all exist in the database and are never queried.

The single highest-leverage change before a dashboard redesign is **adding a `DATETIME` column to `tbl_click`**. Without it, all historical click data is permanently limited to daily granularity and cannot support time-of-day analysis regardless of any future UI changes.

The second highest-leverage change is **building a query layer for `tbl_click.sub_offer_id`** — this unlocks weight-vs-actual routing distribution reporting, which is the core operational insight for a traffic routing platform.
