# Smoke Test Checklist — Routing Hotfix V1

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**PHP:** 8.2 · **DB:** MySQL 8 · **Environment:** Docker

Run P0 tests first. If any P0 fails, stop and investigate before proceeding to P1.

---

## Setup

```bash
# Tail PHP error log during tests — notices here indicate regressions
tail -f /var/log/php_errors.log

# Confirm display_errors state (on = harder to miss header issues, but use with care)
php -r "echo ini_get('display_errors');"

# Confirm ob_start is in effect for routing
grep -n "ob_start" index.php
# Expected: line 3 — ob_start();
```

---

## P0 — App Unusable If These Fail

These tests confirm the app is alive and the hotfix did not break the critical path.

---

### P0-01 — Login

```
POST portal/ajax.php
requestMethod=login
username=<valid>
password=<valid>
```

- [ ] Response: `{"response":true,"message":"Successfully Login!."}`
- [ ] Session cookie set in browser
- [ ] Browser redirects to `portal/dashboard.php`
- [ ] No JSON parse error in browser console

---

### P0-02 — Auth guard blocks unauthenticated AJAX

```
POST portal/ajax.php
requestMethod=fetchAll
(no session cookie)
```

- [ ] Response: `{"response":false,"message":"Unauthorized"}`
- [ ] HTTP 200 with JSON body (not a redirect)
- [ ] No PHP error in response body

---

### P0-03 — Dashboard loads after login

```
GET portal/dashboard.php (with valid session)
```

- [ ] Page renders without PHP errors
- [ ] DataTable makes AJAX call to ajax.php?requestMethod=fetchAll
- [ ] Table shows rows (or empty state if no data)
- [ ] No "headers already sent" errors in PHP log

---

### P0-04 — Routing: valid slug redirects to destination

```
GET /?oid=<active-slug>
```

- [ ] HTTP 302 response
- [ ] `Location:` header points to the sub_url (not 404.php)
- [ ] `Location:` URL is well-formed (no double `??`, no PHP warning text prepended)
- [ ] PHP error log: no `Warning: Cannot modify header information` for this request
- [ ] `tbl_click` gains a new row (click logging still works)

---

### P0-05 — Routing: invalid slug returns 404

```
GET /?oid=this-slug-does-not-exist
```

- [ ] HTTP 302 response
- [ ] `Location:` header points to `404.php`
- [ ] No PHP error output in response body

---

### P0-06 — Routing: missing oid parameter (no query string) — RG-01 fix

```
GET /
(no query string at all)
```

- [ ] HTTP 302 response to `404.php`
- [ ] PHP error log: **NO** `Warning: Undefined array key "query"` for this request
- [ ] PHP error log: **NO** `Deprecated: Passing null to parameter #1 ($string) of type string`
- [ ] **No** `Cannot modify header information - headers already sent` error
- [ ] This is the key regression guard for RG-01 (ob_start + `$parsed['query'] ?? ''`)

---

### P0-07 — Routing: click-log failure does NOT block redirect — C-01 fix

**Setup:** Temporarily lock or rename `tbl_click` to force INSERT failure.

```sql
-- Method 1: rename table
RENAME TABLE tbl_click TO tbl_click_disabled;
```

```
GET /?oid=<active-slug>
```

- [ ] HTTP 302 response to the sub_url destination (NOT 404.php)
- [ ] After restoring tbl_click, normal click logging resumes

```sql
RENAME TABLE tbl_click_disabled TO tbl_click;
```

---

## P1 — Business Logic Broken If These Fail

---

### P1-01 — Click logging works on success

```
GET /?oid=<active-slug>&click_id=TRK_SMOKE_001
```

- [ ] `tbl_click` contains a row with `click_id = 'TRK_SMOKE_001'`
- [ ] `offer_id` and `sub_offer_id` are non-null integers
- [ ] `ip_address` matches request IP
- [ ] `created_at` = today's date

---

### P1-02 — click_id=0 stored correctly — L-01 fix

```
GET /?oid=<active-slug>&click_id=0
```

- [ ] `tbl_click` row: `click_id = '0'` (not NULL, not empty string)
- [ ] Routing still redirects correctly

---

### P1-03 — click_id absent defaults to '0' — L-01 fix

```
GET /?oid=<active-slug>
(no click_id parameter)
```

- [ ] `tbl_click` row: `click_id = '0'`

---

### P1-04 — Passthrough parameters forwarded correctly

```
GET /?oid=<active-slug>&utm_source=smoke&utm_medium=test&affid=999
```

- [ ] `Location:` header = `<sub_url>?utm_source=smoke&utm_medium=test&affid=999`
- [ ] `oid` is NOT in the forwarded params
- [ ] `click_id` is NOT in the forwarded params (it is not set in this request)

---

### P1-05 — Archived offer routes to 404

```
GET /?oid=<archived-slug>
```

- [ ] HTTP 302 → `404.php`
- [ ] No PHP error

---

### P1-06 — Null guard for zero-weight URLs — H-05 fix

**Setup:** Create test offer with all active sub-URLs having weight=0.

```
GET /?oid=<zero-weight-offer-slug>
```

- [ ] HTTP 302 → `404.php`
- [ ] PHP error log: **NO** `Warning: Attempt to read property "sub_url" on null`
- [ ] PHP error log: **NO** `Warning: Undefined array key 0` for `$get_site_id[0]`
- [ ] No second DB query issued (H-05 early return)

---

### P1-07 — Create offer: slug that is substring of existing slug now allowed — M-01 fix

**Setup:** Ensure offer with slug `my-offer` exists and is active.

**Action:** Create new offer with slug `offer`.

- [ ] `checkSlug` AJAX returns `{"response":false,"message":"not found!"}` (slug "offer" not blocked by "my-offer")
- [ ] `addNewOffer` succeeds
- [ ] New offer appears in dashboard
- [ ] Routing `/?oid=offer` routes to the new offer's sub-URL (not "my-offer")

---

### P1-08 — Create offer: exact duplicate slug still blocked

**Setup:** Ensure offer with slug `my-offer` exists and is active.

**Action:** Try to create another offer with slug `my-offer`.

- [ ] `checkSlug` AJAX returns `{"response":true,"message":"success"}` (slug in use)
- [ ] `addNewOffer` returns `{"response":false, "message":"Slug already is in Use!..."}`
- [ ] No duplicate offer created

---

### P1-09 — Weighted routing: proportional distribution

**Setup:** Offer with two active sub-URLs: weight=70 and weight=30.

```
Send 200 requests: GET /?oid=<slug>
Count redirects to each sub_url
```

- [ ] Sub-URL A receives ~70% of traffic (±10% tolerance)
- [ ] Sub-URL B receives ~30% of traffic (±10% tolerance)
- [ ] Neither URL receives 0% (no total fallthrough)

---

### P1-10 — AJAX JSON responses not corrupted

For each AJAX call below, confirm the response body is valid JSON (parse it):

- [ ] `requestMethod=fetchAll` → valid JSON with `draw`, `recordsTotal`, `data[]`
- [ ] `requestMethod=editOffer&row=<id>` → `{"response":true,"message":[...]}`
- [ ] `requestMethod=archiveOffer&oid=<id>` → `{"response":true}`
- [ ] `requestMethod=deleteOffer&oid=<id>` → `{"response":true}`
- [ ] `requestMethod=checkSlug&slug=<slug>` → `{"response":true|false}`

---

### P1-11 — Session persists across navigation

- [ ] Login → navigate to dashboard.php → session still valid
- [ ] Open second tab → dashboard.php loads without re-login
- [ ] Close tab, reopen → session persists until browser session ends (no remember-me implemented)

---

## P2 — Edge Cases / Operational Hardening

---

### P2-01 — PHP error log clean under normal operation

Run P0 + P1 tests. Grep error log for hotfix-related noise:

```bash
grep -E "(Undefined array key|headers already sent|Cannot modify|Deprecated.*null)" /var/log/php_errors.log
```

- [ ] No `Undefined array key "query"` from index.php (RG-01 fix)
- [ ] No `Undefined array key 0` from Postback.php (RG-02 fix)
- [ ] No `Cannot modify header information` from index.php

---

### P2-02 — exit after redirect — no post-header output

```bash
curl -i "http://localhost/?oid=<valid-slug>" 2>&1 | head -30
```

- [ ] Response contains exactly one `Location:` header
- [ ] Response body is empty (exit prevents any body output)
- [ ] No PHP function declaration or warning text in body

---

### P2-03 — Filter by Status: All / Active / Archived / Deleted

- [ ] Status=All → all offers regardless of offer_status
- [ ] Status=Active → only offer_status=1
- [ ] Status=Archived → only offer_status=2
- [ ] Status=Deleted → only offer_status=3

---

### P2-04 — CSV Import

- [ ] Upload valid CSV → offers created with correct sub-URLs
- [ ] Upload CSV with weight sum ≠ 100 for active URLs → rejected
- [ ] Upload CSV with duplicate slug → handled gracefully

---

### P2-05 — Restore (reset) archived/deleted offer

- [ ] Restore offer with unique name+slug → `{"response":true}`
- [ ] Attempt to restore offer whose slug is now used by another active offer → `{"response":false, "message":"...same slug..."}`

---

### P2-06 — Report modal

- [ ] Click View Report on an offer → report DataTable loads with click data
- [ ] DataTable AJAX returns valid JSON `{"data":[...]}`

---

### P2-07 — Sub-offer hover modal

- [ ] Hover over offer name row → sub-offer modal appears
- [ ] Modal DataTable shows sub-URLs, weights, status for that offer

---

## Regression Flags to Watch

The following are confirmed pre-existing issues that this hotfix did NOT fix. Do not fail the build on these, but note them in the test report.

| Ref | Issue | Symptom |
|---|---|---|
| C-03 | SQL injection via `oid` | `/?oid=test'--` does not crash but may return unexpected result |
| C-02 | Click attributed to wrong offer (global URL lookup) | Click `offer_id` in tbl_click may be wrong if URL shared across offers |
| H-01 | Weight algorithm uses rand(0,99) | With weights ≠ 100 sum, distribution will be skewed |
| L-02 | Trailing `?` in redirect | `GET /?oid=slug` (no other params) → Location has trailing `?` |
| ajax.php:68 | postBack AJAX case missing `??` | PHP 8 notice if called without oid parameter (behind auth) |

---

## Sign-off

| Test Group | Pass / Fail | Tester | Date |
|---|---|---|---|
| P0 — Critical path | | | |
| P1 — Business logic | | | |
| P2 — Edge cases | | | |
