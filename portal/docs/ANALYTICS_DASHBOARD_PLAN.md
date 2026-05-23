# Analytics Dashboard — Implementation Plan

**Branch:** `analytics-dashboard-v1`  
**Date:** 2026-05-23  
**Based on:** `routing-integrity-v1` (+ UI modernization commit merged)  
**Author:** Senior Product Engineer  

---

## 1. Scope & Constraints

### In Scope
- Read-only analytics dashboard that exposes existing DB data
- New files: `portal/analytics.php`, `portal/ajax_analytics.php`, `portal/library/Analytics.php`
- Sidebar link additions to `dashboard.php`, `import.php`, `export/index.php`
- Plan doc: this file

### Hard Constraints
| Constraint | Reason |
|---|---|
| DO NOT modify `ajax.php` | Routing switch — any bug introduced breaks redirect engine |
| DO NOT modify `Postback.php` / `Offer.php` / `index.php` | Business logic — out of scope |
| DO NOT ALTER or CREATE DB tables | Schema freeze for this phase |
| MySQL 5.7 compatible queries only | No window functions (`OVER PARTITION BY`, etc.) |
| Filter `click_id != '0'` in quality queries | Router defaults click_id to `'0'` when not passed |
| `tbl_click.created_at` is DATE, not DATETIME | Intraday analytics are impossible on existing data |

---

## 2. Data Model Reference

| Table | Key Columns Used | Notes |
|---|---|---|
| `tbl_offer_url` | id, offer, slug_name, tag_id, network_id, offer_status, start_date, end_date | offer_status: 1=Active, 2=Archived, 3=Deleted |
| `tbl_sub_offer_url` | id, main_offer_id, sub_url, weight, status, deleted_status | status: 'yes'/'no'; deleted_status: 'yes'/'no' |
| `tbl_click` | id, offer_id, sub_offer_id, click_id, ip_address, created_at | created_at is DATE only |
| `tbl_tag` | id, tag_name | |
| `tbl_network` | id, network_name | |
| `tbl_report` | id, offer_id, clicks, created_at | Daily rollup from cron.php |

---

## 3. New Files

### `portal/library/Analytics.php`
Pure read-only class. Connects via raw `mysqli` (same pattern as `cron.php`). No side effects.

**Methods:**

| Method | Description | Key Query |
|---|---|---|
| `getKpiCards()` | 4 KPI metrics | 4 separate COUNT queries |
| `getDailyClickTrend(int $days)` | Day-by-day click counts | GROUP BY created_at |
| `getNetworkBreakdown(int $days)` | Clicks per network | JOIN offer→network→click |
| `getTopOffers(int $days)` | Top 10 offers by clicks | COUNT clicks per offer |
| `getOfferHealthIssues()` | Structural problems | Subquery-based health checks |
| `getRoutingDistribution(int $offer_id)` | Weight config vs actual traffic | JOIN sub_offer_url→click |
| `getTrafficQuality(int $days)` | Duplicate IPs / repeated click_ids | GROUP BY HAVING hits > 1 |
| `getOfferListForDropdown()` | Active offer id→name list | SELECT id, offer WHERE offer_status=1 |
| `getOfferAnalytics()` | Per-offer clicks + route counts | Multi-LEFT JOIN aggregate |

### `portal/ajax_analytics.php`
Separate auth-guarded AJAX endpoint. Only handles analytics `requestMethod` values.
Does NOT load `Offer.php`, `Postback.php`, or `Database.php` — no risk of touching routing.
Returns `Content-Type: application/json`.

**Handled requestMethods:**
- `getKpiCards`
- `getDailyClickTrend` (param: `days`)
- `getNetworkBreakdown` (param: `days`)
- `getTopOffers` (param: `days`)
- `getOfferHealthIssues`
- `getRoutingDistribution` (param: `offer_id`)
- `getTrafficQuality` (param: `days`)
- `getOfferListForDropdown`
- `getOfferAnalytics`

### `portal/analytics.php`
Full analytics dashboard page. Self-contained HTML with all required CDN links.
No dependency on routing code. Auth guard at top.

**Page sections:**
1. **KPI Cards** — Clicks Today, Active Offers, Active Routes, Unique IPs Today
2. **Daily Click Trend** — Chart.js line chart, last 30 days
3. **Network Breakdown** — Chart.js doughnut, last 30 days
4. **Top Offers** — Table, configurable window (7/14/30 days)
5. **Routing Distribution** — Horizontal grouped bar (configured weight % vs actual traffic %), per-offer selector
6. **Traffic Quality** — Duplicate IPs table + Repeated click_id table (last 30 days)
7. **Offer Health** — Issues list (no routes, no clicks, expired but active)
8. **Offer Analytics** — Full offer table with click/route counts

---

## 4. Sidebar Changes

| File | Change |
|---|---|
| `portal/dashboard.php` | Add `<li><a href="<?= BASE_URL ?>/portal/analytics.php">Analytics</a></li>` after Export link |
| `portal/import.php` | Add Analytics link after Back to dashboard |
| `portal/export/index.php` | Add Analytics link after Back to dashboard |

---

## 5. KPI Query Details

```sql
-- Clicks today (DATE comparison, MySQL 5.7)
SELECT COUNT(*) AS c FROM tbl_click WHERE created_at = CURDATE()

-- Active offers
SELECT COUNT(*) AS c FROM tbl_offer_url WHERE offer_status = 1

-- Active routes (sub-offers)
SELECT COUNT(*) AS c FROM tbl_sub_offer_url 
WHERE status = 'yes' AND deleted_status = 'no'

-- Unique IPs today
SELECT COUNT(DISTINCT ip_address) AS c FROM tbl_click WHERE created_at = CURDATE()
```

---

## 6. Routing Distribution Query

```sql
-- Per sub-offer: configured weight + actual click count
SELECT s.id, s.sub_url, s.weight, COUNT(c.id) AS actual_clicks
FROM tbl_sub_offer_url s
LEFT JOIN tbl_click c ON c.sub_offer_id = s.id
WHERE s.main_offer_id = :offer_id AND s.deleted_status = 'no'
GROUP BY s.id
ORDER BY s.id ASC
```
PHP calculates `actual_pct = actual_clicks / total * 100` and `config_pct = weight / total_weight * 100`.

---

## 7. Traffic Quality Query

```sql
-- Duplicate IPs (30 days)
SELECT ip_address, COUNT(*) AS hits
FROM tbl_click
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND ip_address != ''
GROUP BY ip_address HAVING hits > 1
ORDER BY hits DESC LIMIT 20

-- Repeated click_ids (filtered: exclude '0' default)
SELECT click_id, COUNT(*) AS hits
FROM tbl_click
WHERE click_id != '' AND click_id != '0'
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY click_id HAVING hits > 1
ORDER BY hits DESC LIMIT 20
```

---

## 8. Offer Health Query Logic

Three checks run independently:

1. **No active sub-URLs:** Offer is active but has zero sub-offers with `status='yes' AND deleted_status='no'`
2. **No clicks (30 days):** Active offer with zero `tbl_click` rows in last 30 days
3. **Expired date still active:** `offer_status=1` AND `end_date < CURDATE()` AND `end_date` is a real date

---

## 9. Rollback Instructions

```bash
# Remove analytics files (no routing impact — these are additive)
git checkout HEAD -- portal/dashboard.php portal/import.php portal/export/index.php
git rm portal/analytics.php portal/ajax_analytics.php portal/library/Analytics.php
git rm portal/docs/ANALYTICS_DASHBOARD_PLAN.md

# Or just delete new files and revert sidebar changes:
del portal\analytics.php
del portal\ajax_analytics.php
del portal\library\Analytics.php
git checkout HEAD -- portal/dashboard.php portal/import.php portal/export/index.php
```

**Zero rollback risk:** The new files are purely additive. They do not modify any routing, 
auth, or AJAX logic. Removing them restores the original state exactly.

---

## 10. Implementation Sequence

1. [x] `portal/docs/ANALYTICS_DASHBOARD_PLAN.md` — this file
2. [x] `portal/library/Analytics.php`
3. [x] `portal/ajax_analytics.php`
4. [x] `portal/analytics.php`
5. [x] Sidebar edits: `dashboard.php`, `import.php`, `export/index.php`
