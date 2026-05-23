# Regression Test Matrix — Routing Hotfix V1

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**PHP:** 8.2 · **DB:** MySQL 8 · **Environment:** Docker  
**Scope:** Full regression impact of changes to `index.php`, `portal/library/Offer.php`, `portal/library/Postback.php`

---

## Dependency Map

```
index.php
  └── Postback::rotateUrl()          [Postback.php]
        ├── Database::join_query()   [Database.php] — Query 1: slug → sub_url list
        ├── get_link_to_display()    [Postback.php] — weighted selection
        ├── Database::join_query()   [Database.php] — Query 2: sub_url → offer_id
        └── Database::save_data()   [Database.php] — INSERT tbl_click

portal/ajax.php
  ├── Offer::checkSlug()             [Offer.php]    — slug uniqueness check
  ├── Offer::addNewOffer()           [Offer.php]    — create / edit offer
  ├── Offer::fetchAll()              [Offer.php]    — DataTables server-side
  ├── Offer::archiveOffer()          [Offer.php]    — status → 2
  ├── Offer::deleteOffer()           [Offer.php]    — status → 3
  ├── Offer::resetOffer()            [Offer.php]    — status → 1
  ├── Offer::editOffer()             [Offer.php]    — load offer into form
  ├── Offer::getSubOffers()          [Offer.php]    — hover modal data
  ├── Postback::rotateUrl()          [Postback.php] — ajax postBack case (legacy)
  └── Upload::uploadOffer()          [Upload.php]   — CSV import
```

**Changed nodes and blast radius:**

| Changed | Touches | Indirect |
|---|---|---|
| `index.php` (L-01, M-04, RG-01) | Postback::rotateUrl() | tbl_click write, redirect headers |
| `Postback.php` (C-01, H-05, RG-02) | rotateUrl(), get_link_to_display() | tbl_click INSERT, routing return value |
| `Offer.php` (M-01) | checkSlug() only | ajax.php `checkSlug` case, addNewOffer slug guard |

---

## Test Matrix

### Authentication

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Login — valid credentials | `POST portal/ajax.php` `requestMethod=login` | `{"response":true}` + session set + redirect to dashboard.php | None — not in changed files | P0 |
| Login — invalid credentials | `POST portal/ajax.php` `requestMethod=login` | `{"response":false}` + no session | None | P0 |
| Session persistence | `GET portal/dashboard.php` with valid session | Dashboard loads, no redirect | None | P0 |
| Session expiry / no session | `GET portal/dashboard.php` without session | Redirect to `portal/index.php` | None | P0 |
| Unauthenticated AJAX call | `POST portal/ajax.php` `requestMethod=fetchAll` without session | `{"response":false,"message":"Unauthorized"}` | None — auth guard unchanged | P0 |
| Redirect after login | Browser submits login form | After success, `window.location.href = "dashboard.php"` | None | P1 |

---

### Routing — Core Path

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Valid slug — normal routing | `GET /?oid=<active-slug>&click_id=<id>` | HTTP 302 → sub_url + passthrough params. Row inserted in tbl_click | **C-01, H-05 changed this path.** Verify redirect still happens AND click is logged | **P0** |
| Valid slug — click-log INSERT fails | `GET /?oid=<active-slug>` with tbl_click locked | HTTP 302 → sub_url (C-01 fix). Previously would 404 | **C-01 directly fixes this.** Confirm redirect, not 404 | **P0** |
| Invalid / unknown slug | `GET /?oid=nonexistent` | HTTP 302 → `404.php` | L-01 / M-04 touched surrounding code. Verify 404 path | **P0** |
| Missing `oid` parameter | `GET /` (no query string) | HTTP 302 → `404.php`. No PHP 8 Notices in error log. No "headers already sent" | **RG-01 directly fixes this** (ob_start + `$parsed['query']` null guard). Verify clean redirect | **P0** |
| Missing `oid` only (other params present) | `GET /?click_id=abc` | HTTP 302 → `404.php?click_id=abc`. No notices | **L-01 + RG-01** | P0 |
| `exit` after redirect | `GET /?oid=valid-slug` | No PHP output after Location header. postBack function declaration does not leak HTML | **M-04** | P1 |
| Passthrough params forwarded | `GET /?oid=slug&utm_source=test&utm_medium=cpc` | Redirect URL contains `?utm_source=test&utm_medium=cpc` | Unchanged logic, but L-01 reassigns `$params` — verify `$final_query` still computed before reassignment | **P1** |
| No passthrough params | `GET /?oid=slug` | Redirect to `https://destination.com/landing?` (trailing `?` — known L-02 issue, not in this hotfix) | None new | P2 |
| Archived offer slug | `GET /?oid=<archived-slug>` | HTTP 302 → `404.php` (offer_status ≠ 1) | None — query filter unchanged | P1 |
| Deleted offer slug | `GET /?oid=<deleted-slug>` | HTTP 302 → `404.php` | None | P1 |
| All active sub-URLs weight=0 | `GET /?oid=<zero-weight-slug>` | HTTP 302 → `404.php`. No PHP notices. No second DB query attempted | **H-05 directly fixes this path** | P1 |
| All sub-URLs inactive (`status=no`) | `GET /?oid=<no-active-urls>` | HTTP 302 → `404.php` | None — empty result from Query 1, early null return unchanged | P1 |
| Weighted routing distribution | `GET /?oid=<slug>` × N requests | Sub-URLs receive traffic proportional to weights. Not uniform. | **Not changed — `get_link_to_display()` untouched** | P1 |

---

### Click Logging

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Successful click INSERT | `GET /?oid=valid-slug&click_id=TRK123` | Row in tbl_click with correct offer_id, sub_offer_id, ip, date | C-01 preserves INSERT attempt. Verify row is created | **P1** |
| Click INSERT failure — routing unblocked | DB write fail condition | Routing redirect still issued (C-01). Click row absent | **C-01 core change** | P1 |
| click_id=0 explicit | `GET /?oid=slug&click_id=0` | click_id stored as `'0'`. L-01 `??` correctly passes `'0'` (was ternary falsy before) | **L-01 edge case** | P1 |
| click_id absent | `GET /?oid=slug` (no click_id) | click_id stored as `'0'` | L-01: `?? '0'` | P1 |
| click_id empty string | `GET /?oid=slug&click_id=` | click_id stored as `''` (L-01 change: old code stored `'0'` for empty string via falsy ternary) | **L-01 behavioural edge case** — empty string now passes through. If DB has NOT NULL and no default, INSERT may fail. C-01 means routing continues regardless. | P2 |

---

### Offers — Create

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Create new offer — valid | `POST portal/ajax.php` `requestMethod=addNewOffer` | `{"response":true}`. Offer created. Slug stored exactly as submitted | None — addNewOffer unchanged | P1 |
| Create offer — duplicate slug (exact) | Submit slug that already exists with `offer_status=1` | `{"response":false, "message":"Slug already is in Use!"}` | None — `addNewOffer` uses its own `fetch_data_new` exact match, independent of `checkSlug()` | P1 |
| Create offer — slug substring of existing (e.g., "offer" when "my-offer" exists) | `POST` with `slug=offer` when `slug=my-offer` exists | **Before M-01:** `checkSlug` returned blocked (false positive). **After M-01:** `checkSlug` returns not-found, `addNewOffer` proceeds. Offer created successfully | **M-01 directly changes this** | **P1** |
| checkSlug AJAX check | `POST portal/ajax.php` `requestMethod=checkSlug&slug=offer` | `{"response":false,"message":"not found!"}` when only "my-offer" exists. `{"response":true}` only when exact "offer" exists | **M-01 changes semantics** | P1 |
| checkSlug — exact duplicate | `POST portal/ajax.php` `requestMethod=checkSlug&slug=my-offer` when `my-offer` exists | `{"response":true,"message":"success"}` | M-01: still catches exact duplicates | P1 |
| Weight validation (frontend) | dashboard.php form submit with weights ≠ 100 | JS blocks submit: "Weight must be 100 in total!" | None — frontend JS unchanged | P1 |

---

### Offers — Edit / Archive / Delete / Restore

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Edit offer — load form | `POST portal/ajax.php` `requestMethod=editOffer&row=<id>` | `{"response":true, "message":[...offer data...]}` | None | P1 |
| Edit offer — save | `POST portal/ajax.php` `requestMethod=addNewOffer` with offerId set | Offer updated. Sub-URLs updated. | None — addNewOffer update path unchanged | P1 |
| Edit offer — slug to substring of existing | Edit slug to "offer" when "my-offer" exists | After M-01: slug accepted (no false-positive block). addNewOffer's own check verifies no active exact duplicate | **M-01** | P1 |
| Archive offer | `POST portal/ajax.php` `requestMethod=archiveOffer&oid=<id>` | `{"response":true}`. offer_status=2 | None | P2 |
| Delete offer | `POST portal/ajax.php` `requestMethod=deleteOffer&oid=<id>` | `{"response":true}`. offer_status=3 | None | P2 |
| Restore offer | `POST portal/ajax.php` `requestMethod=resetOffer&oid=<id>` | `{"response":true}` if no name/slug conflict. offer_status=1 | None | P2 |
| Clone offer | dashboard.php clone button | Form pre-filled with "-copy" suffix on name and slug. Save creates new offer | None | P2 |

---

### Dashboard — DataTables

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Initial load (Status=Active) | `GET portal/dashboard.php` | Table renders with active offers. Server-side pagination works | None — fetchAll unchanged | P1 |
| Search | Type in DataTables search box | AJAX to `fetchAll` with `search[value]` set. Results filtered | None | P1 |
| Pagination | Navigate pages | Correct `start`/`length` passed. Results paginated | None | P1 |
| Filter by Status | Select Status → Archived/Deleted/All | DataTables reloaded with filterType=Status | None | P2 |
| Filter by Network | Select Network | DataTables reloaded with filterType=Network | None | P2 |
| Filter by Domain | Select Domain | DataTables reloaded with filterType=Domain | None | P2 |
| Report modal | Click View Report | `fetchReport` AJAX call returns click data. DataTable renders | None | P2 |
| Sub-offer hover modal | Hover over offer name | `getSubOffers` AJAX call returns sub-URL data | None | P2 |

---

### Import

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| CSV upload — valid | `POST portal/ajax.php` `requestMethod=importOffer` | Offers imported. Weight sum validated per offer | None — Upload.php unchanged | P2 |
| CSV upload — invalid weights | Upload CSV with active-URL weights not summing to 100 | Import rejected or flagged per offer | None | P2 |
| CSV upload — duplicate slug | Upload offer with slug that already exists | Import correctly detects duplicate | None | P2 |

---

### Security

| Feature | Endpoint | Expected Behaviour | Hotfix Risk | Test Priority |
|---|---|---|---|---|
| Unauthenticated portal access | `GET portal/dashboard.php` without session | Redirect to `portal/index.php` | None | P0 |
| Unauthenticated AJAX | `POST portal/ajax.php` any method except login | `{"response":false,"message":"Unauthorized"}` | None | P0 |
| Session fixation | Successful login | `session_regenerate_id(true)` called. Session ID changes post-login | None | P1 |
| Router injection (oid) | `GET /?oid=test'%20OR%20'1'='1` | Routing query returns no result or wrong result (C-03 not fixed). Visitor sees 404 or wrong redirect. No PHP crash | None new — SQL injection pre-exists, not worsened by hotfix | P1 |

---

## Risk Summary

| Change | Regression Risk | Affected Features |
|---|---|---|
| C-01 — unconditional return $site | **None** | Routing (positive: no longer blocks on click-log failure) |
| H-05 — null guard after get_link_to_display | **None** | Routing (zero-weight edge case now exits cleanly) |
| L-01 — `??` on $_REQUEST | **Low** | click_id=empty-string now stored as `''` instead of `'0'` |
| M-04 — exit after header | **None** | Routing (prevents future post-redirect code leaks) |
| M-01 — checkSlug exact match | **Low** | checkSlug AJAX endpoint semantics changed. addNewOffer unaffected (uses own check) |
| RG-01 — ob_start + `$parsed['query'] ?? ''` | **None** | Routing (prevents header-already-sent on PHP 8.2 with display_errors=On) |
| RG-02 — `$get_site_id[0] ?? null` | **None** | Postback (silences PHP 8.2 Notices, routing behaviour unchanged) |

---

## Pre-Existing Issues Not Introduced by This Hotfix

| Issue | File | Notes |
|---|---|---|
| SQL injection via `oid` (C-03) | Postback.php:15 | Unauthenticated. Not fixed in this hotfix per task rules |
| SQL injection via `click_id` (M-03) | index.php:16 | Write-path injection. Not fixed per task rules |
| Weight algorithm `rand(0,99)` (H-01) | Postback.php:48 | Deferred per task instructions |
| `ajax.php:68` postBack case missing `??` | ajax.php | Pre-existing PHP 8 notice on missing oid/click_id. Behind auth |
| Trailing `?` in redirect (L-02) | index.php | Not in this hotfix scope |
| No start_date/end_date check (H-03) | Postback.php:15 | Not in this hotfix scope |
