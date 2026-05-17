# Architecture Overview

## Project Identity

**Type:** Legacy PHP affiliate offer routing and traffic distribution system  
**Purpose:** Manage redirect offers, distribute inbound traffic across multiple destination URLs using weighted random selection, track clicks, and report analytics via an admin dashboard.  
**Stack:** PHP (no framework), MySQLi, MySQL/MariaDB, jQuery + Bootstrap 5 frontend  
**Deployment target:** Apache on XAMPP (local) — references production domain `efbhalvbhdsurl.com`

---

## Entry Points

| File | Role | Access |
|------|------|--------|
| `index.php` | Login page (HTML form) | Public |
| `dashboard.php` | Admin panel (session-gated) | Authenticated |
| `ajax.php` | Single AJAX endpoint — all client↔server calls route here | Internal (called by JS) |
| `import.php` | CSV batch-import interface | Authenticated (implicit) |
| `export/index.php` | CSV batch-export interface | Authenticated (implicit) |
| `export/download.php` | Streams CSV file to browser | Authenticated (implicit) |
| `library/cron.php` | Daily report aggregation — invoked by system cron | CLI / cron |
| `404.php` | Error page | Public |

---

## Folder Structure

```
/
├── index.php                   Login page
├── dashboard.php               Admin dashboard (Bootstrap + DataTables)
├── ajax.php                    Unified AJAX request dispatcher
├── url.php                     Partial HTML template — sub-URL form row
├── import.php                  CSV import UI
├── export/
│   ├── index.php               Export listing page
│   └── download.php            CSV download handler
├── 404.php                     Error page
├── datatable.php               DataTable HTML partial
├── table.php                   Legacy table partial (superseded)
├── test.php                    Development scratch file
│
├── library/
│   ├── Settings.php            All configuration constants (DB creds, table names)
│   ├── database/
│   │   ├── Database.php        MySQLi abstraction class (core DB layer)
│   │   └── connect.php         Deprecated direct-connection script
│   ├── User.php                Login validation
│   ├── Offer.php               Core offer CRUD + business validation (~650 lines)
│   ├── Postback.php            Traffic routing — weighted URL selection (primary)
│   ├── PostbackBeta.php        Alternate routing implementation (experimental)
│   ├── Report.php              Click report retrieval
│   ├── Filter.php              Dashboard filter option generation
│   ├── Upload.php              CSV import validation and insertion (~394 lines)
│   └── cron.php                Daily click aggregation into tbl_report
│
├── assets/
│   ├── css/
│   │   ├── style.css           Login page styles
│   │   ├── dashboard_style.css Dashboard styles
│   │   └── dashboard_style_bkp.css Backup (unused)
│   └── images/                 UI icons
│
├── backup/                     Historical backup copies (not active code)
└── docs/                       This documentation
```

---

## Request Lifecycle

### Admin UI request

```
Browser
  → dashboard.php (loads HTML shell, starts session check)
  → JS fires XHR to ajax.php?requestMethod=<action>
  → ajax.php includes library chain, routes to handler function
  → Handler calls appropriate class method
  → Class method uses Database.php
  → JSON response returned to browser
  → DataTables / modal updates UI
```

### Traffic routing (click inbound)

```
External click link: https://efbhalvbhdsurl.com/?oid=<slug>&tag=<tag>&affid=<network>
  → ajax.php?requestMethod=postBack&oid=<slug>&ip=<ip>&click_id=<uuid>
  → postBack() function
  → Postback::rotateUrl($params)
  → Database JOIN query fetches active sub-URLs + weights
  → Weighted random selection picks one destination URL
  → Click recorded in tbl_click
  → Selected URL returned → browser redirected
```

---

## Class / File Responsibilities

### `library/Settings.php`
Defines all constants. No logic. Every other file depends on this first.

```php
DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME
DB_USER_TABLE     = 'tbl_user'
DB_OFFER_TABLE    = 'tbl_offer_url'
```

### `library/database/Database.php`
MySQLi wrapper. Methods: `save_data`, `fetch_data`, `fetch_data_new`, `fetch_clicks`, `update_data`, `join_query`, `filter_query`. All build SQL via string concatenation — no prepared statements.

### `library/User.php`
Single method: `login($params)`. Queries `tbl_user` for matching `user_name` + `password`. Returns row on match.

### `library/Offer.php`
Core domain logic. Key methods:
- `checkMainUrl` / `checkSlug` — uniqueness guards
- `addNewOffer` — create or update offer + sub-URLs (handles add/update/soft-delete of sub-URL rows)
- `editOffer` — fetch offer + sub-URLs for modal form
- `fetchAll` — paginated list with JOIN to tag/network/click counts
- `archiveOffer` / `deleteOffer` / `resetOffer` — status transitions

### `library/Postback.php`
Two methods:
- `rotateUrl($params)` — main routing function
- `get_link_to_display($sites)` — weighted random algorithm

### `library/Report.php`
Reads pre-aggregated rows from `tbl_report` for a given offer ID.

### `library/Filter.php`
Generates `<option>` sets for network, domain, and status dropdowns on the dashboard.

### `library/Upload.php`
Parses JSON-encoded CSV rows, validates each field (offer name, slug, weights, dates, URL format), then bulk-inserts offers and sub-URLs.

### `library/cron.php`
Standalone CLI script. Queries `tbl_click` for today's counts grouped by offer, then upserts into `tbl_report`.

---

## Include / Require Chain

```
ajax.php
  ├── require 'library/Settings.php'
  ├── require 'library/database/Database.php'
  ├── require 'library/User.php'
  ├── require 'library/Offer.php'
  ├── require 'library/Postback.php'
  ├── require 'library/Report.php'
  ├── require 'library/Filter.php'
  └── require 'library/Upload.php'

dashboard.php
  └── (Settings.php referenced for config — minimal server-side PHP)

export/download.php
  └── Direct DB credentials hardcoded inline (bypasses Settings.php)

library/cron.php
  └── require 'Settings.php'
```

---

## External Dependencies (CDN — no local vendor)

| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.3.3 | UI layout, modals, buttons |
| jQuery | 3.4.1 | AJAX, DOM manipulation |
| DataTables | 2.0.8 | Server-side offer table |
| Select2 | 4.1.0-rc.0 | Styled dropdown selects |
| jQuery Confirm | 3.3.4 | Confirm dialogs (archive/delete) |
| Font Awesome | 5.10.2 | Icons |
| Google Fonts | — | Lato font |

No `composer.json`. No `package.json`. No vendor directory. All dependencies loaded from CDN at runtime.

---

## AJAX Dispatch Table (`ajax.php`)

| `requestMethod` value | Handler function | Class called |
|----------------------|-----------------|--------------|
| `login` | `userLogin()` | `User` |
| `fetchAll` | `fetchAllData()` | `Offer` |
| `addNewOffer` | `addNewOffer()` | `Offer` |
| `editOffer` | `editOffer()` | `Offer` |
| `checkMainOffer` | `checkMainOffer()` | `Offer` |
| `checkSlug` | `checkSlug()` | `Offer` |
| `postBack` | `postBack()` | `Postback` |
| `fetchReport` | `fetchReport()` | `Report` |
| `archiveOffer` | `archiveOffer()` | `Offer` |
| `deleteOffer` | `deleteOffer()` | `Offer` |
| `resetOffer` | `resetOffer()` | `Offer` |
| `getFilterType` | `getFilterType()` | `Filter` |
| `getSubOffers` | `getSubOffers()` | `Offer` |
| `importOffer` | `importOffer()` | `Upload` |

---

## Backup / Dead Files

The following files exist but are **not active code** — they are timestamped backups made before version control was adopted:

- `dashboard(bkp-10-09-2024).php`, `dashboard(bkp-23-09-2024).php`
- `ajax(bkp-10-09-2024).php`, `ajax(bkp-23-09-2024).php`
- `Offer(bkp-10-09-2024).php`, `Offer(bkp-23-09-2024).php`
- `Report(bkp-10-09-2024).php`
- `table(bkp).php`, `table2.php`, `table-beta.php`
- `library/cron-bkp.php`
- `backup/` directory (older iterations)

These should not be confused with production logic. The canonical active files are the ones without date suffixes.
