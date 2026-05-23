# Product UI Vision — Traffic Routing Intelligence Platform

**Branch:** `analytics-product-v2`  
**Date:** 2026-05-23  
**Based on:** `routing-integrity-v1` + UI modernization + analytics dashboard  
**Design layer:** `design-system-v2.css` loaded after `modern.css`

---

## 1. Brand & Positioning

| | Before | After |
|---|---|---|
| Product name | Offer Router (admin panel) | Traffic Routing Intelligence Platform |
| Short name | Offer Router | Traffic Router |
| Audience | Internal ops | SaaS-ready, multi-user |
| Reference quality | Bootstrap admin | Stripe / Vercel / Linear |

The sidebar brand text changes from `'Offer Router'` → `'Traffic Router'` via CSS `::before` override in `design-system-v2.css`.

---

## 2. CSS Layer Architecture

```
Bootstrap 5.3.3 (CDN)
    └── dashboard_style.css   ← legacy base (preserved, not modified)
        └── modern.css        ← ui-modernization-v1 design tokens (--m-*)
            └── design-system-v2.css  ← THIS RELEASE: active nav, skeletons, badges, animations
```

`design-system-v2.css` never redefines what `modern.css` already owns. It only adds new selectors and new components.

---

## 3. Design System V2 — New Components

### 3.1 Skeleton Loaders
| Class | Use case |
|---|---|
| `.ds2-skel` | Base shimmer block (configure size inline or with modifier) |
| `.ds2-skel-sm / -md / -val / -full` | Pre-sized skeleton lines |
| `.ds2-skel-chart` | Full-width 240px chart placeholder |
| `.ds2-skel-kpi` | KPI card skeleton layout wrapper |
| `.ds2-skel-row` | Table row skeleton |

Animation: 1.4s `ds2-shimmer` gradient sweep left→right.

### 3.2 Badge System
`.ds2-badge` + color modifier: `green | blue | yellow | red | gray | purple | orange`

Offer status aliases: `.ds2-status-active | -archived | -deleted`

### 3.3 Delta Indicators (KPI trend)
`.ds2-delta` + direction: `ds2-delta-up | down | flat | new`

Renders: `▲ +12% vs yesterday` / `▼ 8% vs yesterday` / `— same as yesterday` / `● New today`

### 3.4 Anomaly Badges
`.ds2-anomaly` — warning-level (orange border)  
`.ds2-anomaly-critical` — critical-level (red border)  
`.ds2-anomaly-ok` — healthy state (green border)

### 3.5 Health Dots
`.ds2-health-dot` + `.ok | .warn | .danger` — 7px pulse-ring dot for at-a-glance status.

### 3.6 Route Preview Hover Card
`.ds2-route-card` — absolute-positioned card that fades in when hovering an `offer_name` cell. Shows slug, sub-URL count, active route count, all-time clicks. Populated by JS on DataTables `createdRow`.

### 3.7 Empty State
`.ds2-empty` > `.ds2-empty-icon` + `.ds2-empty-title` + `.ds2-empty-body` — centered empty message for tables/panels with no data.

### 3.8 Topbar Enhancements
`.ds2-topbar-title` — current page name.  
`.ds2-topbar-live` + `.ds2-live-dot` — pulsing green "Live" badge (dashboard + analytics).

### 3.9 Animations
| Name | Duration | Applied to |
|---|---|---|
| `ds2-shimmer` | 1.4s infinite | skeleton loaders |
| `ds2-fadeRow` | 0.22s | DataTable rows on draw |
| `ds2-pulse` | 2s infinite | live topbar dot |
| modal slide-down | 0.22s cubic | Bootstrap modals (`.modal.fade .modal-dialog`) |

---

## 4. Sidebar Navigation — Final Structure

All pages share this sidebar nav. The active link gets `.ds2-active` added by inline JS matching `window.location.pathname`.

| Icon | Label | href | Active on |
|---|---|---|---|
| `fas fa-th-list` | Offers | `dashboard.php` | dashboard.php |
| `fas fa-chart-bar` | Analytics | `analytics.php` | analytics.php |
| `fas fa-file-import` | Import | `import.php` | import.php |
| `fas fa-file-export` | Export | `export/` | export/index.php |

**Active state logic (inline JS at end of `<body>`):**
```javascript
(function () {
  var path = window.location.pathname;
  document.querySelectorAll('.sideMenu_ottr a').forEach(function (a) {
    if (a.href && path.endsWith(a.pathname.replace(/^\//, ''))) {
      a.classList.add('ds2-active');
    }
  });
})();
```

---

## 5. Page-by-Page Changes

### 5.1 `portal/index.php` — Login
| Element | Before | After |
|---|---|---|
| Font | Lato (Google) | Inter (Google) |
| Icons | FontAwesome 4.7 | FontAwesome 5.10.2 |
| Base CSS | `style.css` (Bootstrap 4-era) | Removed; Bootstrap 5 inline |
| Layout | Centered `.login-wrap` card | `ds2-login-card` centered on gradient bg |
| Logo | FA user icon | Brand icon square (blue gradient) + product name |
| Error display | `$.alert()` popup | Inline `#ds2-login-error` div |
| JS logic | Unchanged (`submitForm`, AJAX to `ajax.php`) | Unchanged |
| Form fields | `username`, `password`, `requestMethod` | Unchanged |

### 5.2 `portal/dashboard.php` — Offers
| Change | Detail |
|---|---|
| `design-system-v2.css` loaded | After `modern.css` |
| Sidebar icons | FA5 icons added to all nav links |
| Sidebar self-link | "Offers" link added (was missing) |
| Topbar title | `<span class="ds2-topbar-title">Offers</span>` + live badge |
| Row animation | `ds2-row-animate` added in `createdRow` callback |
| Route preview card | Injected into offer_name cell in `createdRow` |
| Active nav state | Inline JS at end of body |

### 5.3 `portal/analytics.php` — Analytics
| Change | Detail |
|---|---|
| `design-system-v2.css` loaded | After `modern.css` |
| KPI card skeletons | Shimmer placeholders before AJAX resolves |
| KPI trend delta | `clicks_yesterday` from extended `getKpiCards()` → delta badge |
| Chart sections | `.ds2-skel-chart` placeholder before Chart.js renders |
| Top offers skeleton | 5 skeleton rows before data |
| Health panel | `ds2-anomaly` / `ds2-anomaly-critical` / `ds2-anomaly-ok` badges + health dots |
| Empty states | `ds2-empty` component when no data |
| Active nav state | Inline JS |

### 5.4 `portal/import.php` — Import
| Change | Detail |
|---|---|
| `design-system-v2.css` loaded | After `modern.css` |
| Sidebar icons | FA5 icons on all nav links |
| Active nav state | Inline JS |

### 5.5 `portal/export/index.php` — Export
Same changes as import.php.

---

## 6. `Analytics.php` Extension

`getKpiCards()` gains two new fields:
- `clicks_yesterday` — `COUNT(*) WHERE created_at = DATE_SUB(CURDATE(), INTERVAL 1 DAY)`
- `unique_ips_yesterday` — `COUNT(DISTINCT ip_address)` for yesterday

These power the trend delta badges on the Analytics page. No schema change.

---

## 7. What Is NOT Changed

- `index.php` (root router) — untouched
- `portal/ajax.php` — untouched
- `portal/library/Postback.php` — untouched
- `portal/library/Offer.php` — untouched
- `portal/library/Database.php` — untouched
- `portal/library/Settings.php` — untouched
- `portal/assets/css/dashboard_style.css` — untouched
- `portal/assets/css/modern.css` — untouched
- All AJAX endpoints, routing logic, DB schema — untouched

---

## 8. Smoke Test Checklist

| # | Test | Expected |
|---|---|---|
| 1 | Load `/portal/index.php` | Inter font visible, gradient background, no jQuery-Confirm dependency |
| 2 | Login with empty fields | Red inline error div appears (no `$.alert()` popup) |
| 3 | Login with wrong credentials | Red inline error div shows server message |
| 4 | Dashboard loads | Sidebar shows 4 links with FA icons; "Offers" link is highlighted blue |
| 5 | Dashboard table rows | Each row fades in (0.22s) on DataTable draw |
| 6 | Hover offer name cell | Route preview card appears with slug + route counts |
| 7 | Analytics page | KPI cards show shimmer skeleton while loading, then real values |
| 8 | Analytics KPI delta | Delta badge shows ▲/▼/— comparing today vs yesterday |
| 9 | Analytics health panel | Issues show `ds2-anomaly` / `ds2-anomaly-critical` badges |
| 10 | Import page | Sidebar shows "Import" highlighted; FA icons on all links |
| 11 | Export page | Sidebar shows "Export" highlighted; FA icons on all links |
| 12 | Routing (index.php) | Redirect still works — zero impact from all UI changes |
| 13 | Add offer modal | Opens with slide-down animation (0.22s); form submit unchanged |
| 14 | Archive/delete | jQuery-Confirm dialog still appears (preserved — destructive action) |
| 15 | Toast notifications | Success/warning/info toasts appear bottom-right on offer save/archive/delete |

---

## 9. Screenshots Checklist

1. Login card — desktop (shows brand icon, gradient bg, Inter font)
2. Login error state — inline red error div
3. Dashboard — sidebar with FA icons, "Offers" active state, topbar title
4. Dashboard — offer name hover (route preview card visible)
5. Analytics — KPI cards with skeleton loading state
6. Analytics — KPI cards fully loaded with delta badges
7. Analytics — health panel with anomaly badges
8. Analytics — routing distribution bars
9. Import page — sidebar "Import" active state
10. Export page — sidebar "Export" active state
11. Mobile (375px) — responsive layout, sidebar collapsed

---

## 10. Rollback Instructions

```bash
# Revert all UI changes to their previous state
git checkout HEAD -- portal/index.php portal/dashboard.php portal/analytics.php portal/import.php portal/export/index.php portal/library/Analytics.php

# Remove new CSS file
del portal\assets\css\design-system-v2.css

# Remove this doc (optional)
del portal\docs\PRODUCT_UI_VISION.md
```

**Zero routing impact:** `index.php` (router), `ajax.php`, `Postback.php`, `Offer.php`, and all DB logic are untouched. Removing `design-system-v2.css` restores the `modern.css` baseline exactly.

---

## 11. Implementation Sequence

- [x] `portal/docs/PRODUCT_UI_VISION.md` — this file
- [x] `portal/assets/css/design-system-v2.css` — full design system v2 layer
- [x] `portal/library/Analytics.php` — add `clicks_yesterday` / `unique_ips_yesterday` to `getKpiCards()`
- [x] `portal/index.php` — login page redesign
- [x] `portal/dashboard.php` — sidebar icons, topbar title, row animation, route preview
- [x] `portal/analytics.php` — skeletons, delta badges, anomaly indicators
- [x] `portal/import.php` — sidebar icons, active nav state
- [x] `portal/export/index.php` — sidebar icons, active nav state
