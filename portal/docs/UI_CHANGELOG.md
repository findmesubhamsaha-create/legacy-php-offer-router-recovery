# UI Changelog — Offer Router Platform

**Branch:** `ui-modernization-v1`  
**Date:** 2026-05-23  
**Based on:** `routing-integrity-v1`

---

## Summary

Full visual modernization toward a Stripe/Vercel-quality SaaS dashboard. Zero changes to business logic, AJAX, routing, or database layer.

---

## Files Changed

### NEW: `portal/assets/css/modern.css`
Full design-system overlay. Loaded after `dashboard_style.css` — takes precedence via specificity/order.

**Covers:**
- CSS custom properties (design tokens) on `:root`
- Inter font enforcement across all elements
- `#111827` dark sidebar with brand header pseudo-element
- White sticky topbar with border-bottom shadow
- Light `#f8fafc` main background with white table card
- DataTables: modern search input, length select, info text
- Pagination: pill buttons, blue active state, disabled opacity
- Table: uppercase muted headers, row hover state, tight padding
- Action icon buttons (`cmn_icon`): 30×30 hover tiles
- Three-dot dropdown: white card with shadow, clean item hover
- Offer name cell: blue link color with hover underline
- Modals: `border-radius: 16px`, `box-shadow: xl`, gray modal header
- Form controls: consistent border, focus ring (primary alpha)
- Sub-URL row cards: `#f9fafb` background with border
- Loading indicator: blurred backdrop, rounded white card
- Toast notifications: bottom-right, slide-in, type color left-border
- Import/Export pages: white card with border and shadow
- Select2 dropdowns: matched to design system
- jQuery-Confirm dialogs: matched to design system
- Responsive breakpoints: tablet (≤991px), mobile (≤575px)

---

### EDITED: `portal/dashboard.php`

#### `<head>` changes
| Change | Detail |
|---|---|
| Font replaced | Lato → Inter (300/400/500/600/700) |
| CSS added | `<link rel="stylesheet" href="assets/css/modern.css">` |

#### Script cleanup
| Removed | Reason |
|---|---|
| `cdn.datatables.net/1.10.8/js/jquery.dataTables.min.js` | Legacy vestigial — DataTables 2.0.8 already loaded |
| `cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js` | Bootstrap 3 extension for old DT — irrelevant |
| `twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js` (Twitter CDN) | Duplicate of Bootstrap 5.3.3 already loaded; also loaded before jQuery — wrong order |

#### HTML changes
| Element | Change |
|---|---|
| `<textarea style="">` (Notes field) | Removed empty `style=""` attribute (CSS lint warning) |
| `#clickexampleModal` | Removed `data-bs-backdrop="static"` and `data-bs-keyboard="false"` → backdrop click + ESC now close |
| `#offerhovereModal` | Same + added `fade` class → smooth animation |
| Toast container | Added `<div id="m-toast-container">` before loading indicator |

#### JavaScript changes

**Added: `showToast(type, title, body)` helper**
```
type: 'success' | 'warning' | 'danger' | 'info'
```
Uses Bootstrap 5 Toast API. Bottom-right, 4.5s auto-dismiss. Appends to `#m-toast-container`, self-removes on `hidden.bs.toast`.

**Replaced: `$.alert()` → `showToast()`**

| Call site | Old | New |
|---|---|---|
| URL validation | `$.alert({title:'Warning!', content:'enter valid sub URL'})` | `showToast('warning','Warning','Enter a valid sub URL')` |
| Duplicate URL | `$.alert({title:'Warning!', content:'Sub Url already added!'})` | `showToast('warning','Warning','Sub URL already added!')` |
| Form validation | `$.alert({title:'Warning!', content: error})` | `showToast('warning','Validation Error', error)` |
| Save success | `$.alert({title:'Success!', content:'Data added successfully!'})` | `showToast('success','Success','Data added successfully!')` |
| Save failure | `$.alert({title:'Warning!', content: data.message})` | `showToast('warning','Warning', data.message)` |
| Copy URL | `$.confirm({title:'Copied!', content: url})` | `showToast('success','Copied!','URL copied to clipboard')` |
| Archive success | `$.alert('Offer Archived!')` | `showToast('success','Archived','Offer archived successfully')` |
| Delete success | `$.alert('Offer Deleted!')` | `showToast('success','Deleted','Offer deleted successfully')` |
| Reset result | `$.alert(data.message)` | `showToast('info','Reset', data.message)` |

**Preserved: `$.confirm()` for destructive actions**  
Archive offer confirm dialog — unchanged.  
Delete offer confirm dialog — unchanged.

**Fixed: Sub-offer modal UX (`#offerhovereModal`)**

| Before | After |
|---|---|
| Trigger: `mouseenter` on offer name cell | Trigger: Click (via `data-bs-toggle="modal"` already on cell) |
| Show: jQuery `.show()` directly | Show: Bootstrap Modal API (auto via `data-bs-toggle`) |
| Close: X button only | Close: X button + backdrop click + ESC key |
| Data load: inside `mouseenter` handler | Data load: inside `show.bs.modal` event (`e.relatedTarget` = clicked td) |
| Cleanup: none | Cleanup: DataTable destroyed on `hidden.bs.modal` |

```javascript
// New handler pair replaces the old mouseenter + .close-hover-modal click
$('#offerhovereModal').on('show.bs.modal', function (e) { ... });
$('#offerhovereModal').on('hidden.bs.modal', function () { ... });
```

---

### EDITED: `portal/import.php`
Added `<link rel="stylesheet" href="assets/css/modern.css">` after `dashboard_style.css`.

### EDITED: `portal/export/index.php`
Added `<link rel="stylesheet" href="../assets/css/modern.css">` after `dashboard_style.css`.

---

## What Was NOT Changed

- `ajax.php` — zero changes
- `portal/library/*.php` — zero changes
- `portal/export/download.php` — zero changes
- `index.php` (login page) — zero changes
- `portal/assets/css/dashboard_style.css` — kept intact
- `portal/assets/css/style.css` — kept intact (unused Bootstrap 4.3.1)
- All DataTables configuration — unchanged
- All AJAX calls and server-side pagination — unchanged
- All form validation logic — unchanged
- All routing and auth — unchanged

---

## Screenshots Needed

After loading in a browser, capture:

1. **Dashboard — desktop** — full page showing sidebar + table
2. **Sidebar open** — dark sidebar with nav items
3. **TopBar filters** — Select2 dropdowns, Add Record button
4. **Table row hover** — blue primary row highlight
5. **Add Offer modal** — form with new styling
6. **Sub-Offer modal** — click-triggered, shows sub-URL table
7. **Click Report modal** — click-dismissible
8. **Toast: success** — green left-border, slide-in
9. **Toast: warning** — orange left-border (trigger validation error)
10. **Mobile view (375px)** — responsive layout

---

## Rollback Instructions

```bash
# Revert all changed files to routing-integrity-v1 baseline
git checkout routing-integrity-v1 -- portal/dashboard.php portal/import.php portal/export/index.php

# Remove the new CSS file
del portal\assets\css\modern.css

# Remove the doc files (optional)
del portal\docs\UI_MODERNIZATION_PLAN.md
del portal\docs\UI_CHANGELOG.md
```

Or on this branch: `git revert HEAD` if changes were committed.

---

## Known Limitations / Follow-up

1. `dashboard_style.css` still has duplicate `body { padding: ... }` rules — harmless since `modern.css` overrides, but could be cleaned up in a future pass.
2. The login page (`index.php`) was not modernized — could be done in a follow-up.
3. `portal/assets/images/*.png` action icons (setting, delete, archive, etc.) are small raster files — could be replaced with inline SVG or Font Awesome icons for sharper rendering at HiDPI.
4. jQuery 1.11.3 is still used — a separate upgrade to jQuery 3.x would require regression testing of all event handlers.
