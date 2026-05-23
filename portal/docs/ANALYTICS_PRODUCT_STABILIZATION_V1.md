# Analytics Product Stabilization V1

**Branch:** `analytics-product-stabilization-v1`  
**Date:** 2026-05-23  
**Based on:** `analytics-product-v2` post-redesign  
**Scope:** UI regression fixes — zero routing/backend changes

---

## Summary

Four regressions introduced by the `analytics-product-v2` redesign are resolved in this stabilization pass. No routing logic, AJAX endpoints, DB schema, or business rules were modified.

---

## Issue 1 — Dashboard Action Menu Clipped by Table Overflow

**Symptom:** The 3-dot action dropdown (Archive / Delete / Edit) was visually clipped at the edge of the table wrapper and rendered partially or completely invisible.

**Root cause:** `modern.css` line 273 sets `.table-responsive { overflow: hidden; }`. The DataTable is wrapped in a `.table-responsive` div, so any `position: absolute` child — including the action dropdown — was clipped to that container's bounds.

**Fix applied:** `portal/assets/css/design-system-v2.css` (appended)
```css
.table-responsive { overflow: visible !important; }
@media (max-width: 767px) {
  .table-responsive { overflow-x: auto !important; overflow-y: visible !important; }
}
.dropdown-content { z-index: 9000 !important; overflow: visible !important; }
```
The mobile rule restores horizontal scrolling while keeping vertical overflow visible for the dropdown layer.

**Affected file:** `portal/assets/css/design-system-v2.css`

**Regression prevention:** Never set `overflow: hidden` on a container that holds absolutely-positioned interactive children. If horizontal scroll is needed on mobile, use `overflow-x: auto` + `overflow-y: visible`, not the shorthand `overflow: hidden`.

---

## Issue 2 — Analytics Health Panel Infinite Skeleton Loader

**Symptom:** The "Offer Health Issues" skeleton rows in `analytics.php` never resolved to real content. The panel remained in skeleton state indefinitely on any AJAX error or malformed JSON response.

**Root cause (two bugs):**

1. **`ajaxPost` error handler did not call `callback`.**  
   The error path only logged to `console.error` and returned without invoking `callback(null)`. Any network error, 403, or timeout left every loading state permanently frozen.

2. **`ajaxPost` success handler had no try-catch around `JSON.parse`.**  
   If the server returned non-JSON (e.g., a PHP warning prepended to output), `JSON.parse` threw synchronously, preventing `callback` from being called.

3. **Health callback used `!issues.length` without `Array.isArray` guard.**  
   When `callback(null)` or `callback({error: "..."})` was received, `null.length` / `{...}.length` evaluated to `undefined`, and the `if (!undefined)` branch was entered — crashing or silently skipping rendering.

**Fix applied:** `portal/analytics.php`

`ajaxPost` now wraps `JSON.parse` in try-catch and always calls `callback` (with `null` on error):
```javascript
success: function (data) {
   var parsed;
   try {
      parsed = (typeof data === 'string') ? JSON.parse(data) : data;
   } catch (e) {
      console.error('[analytics] JSON parse failed for', requestMethod, e);
      parsed = null;
   }
   callback(parsed);
},
error: function (xhr, status, err) {
   console.error('[analytics] Request failed for', requestMethod, status, err);
   callback(null);
}
```

Health callback now checks `Array.isArray(issues)` first and renders a user-visible error empty state on `null`:
```javascript
if (!Array.isArray(issues)) {
   wrap.innerHTML = '<div class="ds2-empty">...</div>';
   return;
}
```

**Affected file:** `portal/analytics.php`

**Regression prevention:** Any AJAX callback that renders UI must tolerate `null` / non-array responses from `ajaxPost`. Always guard with `Array.isArray` before calling `.length` or `.map` on data from `ajaxPost`. The `ajaxPost` contract is now: callback is always called, argument is either the parsed object/array or `null`.

---

## Issue 3 — Route Preview Hover Card Overflow + Viewport-Edge Positioning

**Symptom:** The route preview hover card on offer name cells was clipped by the table container and, when an offer appeared in the right half of the viewport, the card extended past the right screen edge making it unreadable.

**Root cause (two causes):**

1. **Same `overflow: hidden` bug as Issue 1.** The `.table-responsive` wrapper clipped the absolutely-positioned `.ds2-route-card`.

2. **Fixed left-anchor positioning.** The card was always anchored to the left edge of its parent `td`. For rows near the right side of the screen, this pushed the card off-screen.

**Fix applied:**

- **Overflow:** Resolved by the Issue 1 CSS fix (same `.table-responsive` override).
- **Viewport-edge flip:** Added `draw.dt` event handler in `portal/dashboard.php` that runs after every DataTable render and measures each `td.offer_name` against the viewport midpoint:
```javascript
dataTable.on('draw.dt', function () {
   $('#example').find('td.offer_name').each(function () {
      var card = $(this).find('.ds2-route-card');
      if (!card.length) return;
      var tdRect = this.getBoundingClientRect();
      if (tdRect.left > window.innerWidth * 0.5) {
         card.css({ left: 'auto', right: '0' });
      } else {
         card.css({ left: '0', right: 'auto' });
      }
   });
});
```

**Affected files:** `portal/assets/css/design-system-v2.css`, `portal/dashboard.php`

**Regression prevention:** Hover cards injected into DataTable cells must have their positioning evaluated after every `draw.dt` event, not once at init. The `createdRow` callback runs per-row at draw time and does not know final layout position; the `draw.dt` event is the correct hook for post-render DOM measurements.

---

## Issue 4 — Analytics Identity Collision (Cartesian Product in `getOfferAnalytics`)

**Symptom:** The Offer Analytics table showed inflated `active_routes` and `total_clicks` counts. An offer with 3 sub-URLs and 10 clicks would display 20 active routes and 30 total clicks.

**Root cause:** `getOfferAnalytics()` in `Analytics.php` joined both `tbl_sub_offer_url` (N rows per offer) and `tbl_click` (M rows per offer) directly to `tbl_offer_url` before the `GROUP BY`, producing N×M combined rows per offer. Aggregates then operated on this inflated row set:

- `SUM(CASE WHEN s.status='yes'...)` counted every sub-URL row once per matching click row → N × clicks
- `COUNT(c.id)` counted every click row once per matching sub-URL row → clicks × N

Only `COUNT(DISTINCT s.id)` was immune because DISTINCT collapsed duplicates, but `active_routes` and `total_clicks` were both wrong.

**Fix applied:** `portal/library/Analytics.php` — replaced the double-join with correlated subqueries, each operating independently on a single table:
```sql
SELECT o.id, o.offer, o.slug_name, t.tag_name, n.network_name,
       (SELECT COUNT(*) FROM tbl_sub_offer_url s
        WHERE s.main_offer_id = o.id AND s.deleted_status = 'no') AS sub_offer_count,
       (SELECT COUNT(*) FROM tbl_sub_offer_url s
        WHERE s.main_offer_id = o.id AND s.status = 'yes' AND s.deleted_status = 'no') AS active_routes,
       (SELECT COUNT(*) FROM tbl_click c
        WHERE c.offer_id = o.id) AS total_clicks
FROM tbl_offer_url o
LEFT JOIN tbl_tag t ON o.tag_id = t.id
LEFT JOIN tbl_network n ON o.network_id = n.id
WHERE o.offer_status = 1
ORDER BY total_clicks DESC
```

No schema change. MySQL 5.7 compatible. Query plan: three index lookups per offer row (indexed on `main_offer_id` and `offer_id`).

**Affected file:** `portal/library/Analytics.php`

**Regression prevention:** Never join two independent one-to-many relationships (`s` and `c`) to the same parent in the same FROM/JOIN clause and then aggregate. Each one-to-many relationship that needs a COUNT or SUM must use a correlated subquery or a pre-aggregated derived table/CTE to avoid the Cartesian product. The safe diagnostic: if `GROUP BY o.id` is the only deduplication mechanism and two LEFT JOINs fan out independently, the query will produce inflated counts.

---

## Files Changed

| File | Change type | Issues fixed |
|---|---|---|
| `portal/assets/css/design-system-v2.css` | Appended | 1, 3 |
| `portal/library/Analytics.php` | Query rewrite | 4 |
| `portal/analytics.php` | JS defensive guards | 2 |
| `portal/dashboard.php` | JS draw.dt handler | 3 |

---

## Files NOT Changed

- `index.php` (root router) — untouched
- `portal/ajax.php` — untouched
- `portal/ajax_analytics.php` — untouched
- `portal/library/Postback.php` — untouched
- `portal/library/Offer.php` — untouched
- `portal/library/Database.php` — untouched
- `portal/library/Settings.php` — untouched
- `portal/assets/css/dashboard_style.css` — untouched
- `portal/assets/css/modern.css` — untouched (root cause of Issues 1+3 is in this file, but override in design-system-v2.css is the safe approach)
- All routing logic, DB schema, AJAX endpoints — untouched

---

## Rollback

```bash
# Revert all stabilization changes
git checkout HEAD -- portal/assets/css/design-system-v2.css portal/library/Analytics.php portal/analytics.php portal/dashboard.php

# Remove this doc (optional)
del portal\docs\ANALYTICS_PRODUCT_STABILIZATION_V1.md
```
