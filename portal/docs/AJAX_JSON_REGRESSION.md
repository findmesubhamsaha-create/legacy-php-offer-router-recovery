# AJAX JSON Corruption — Root Cause & Fix

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**Symptom:** DataTables shows "Invalid JSON response" on dashboard load. Login succeeds but dashboard transition hangs.

---

## Symptoms Observed

| Endpoint | Symptom |
|---|---|
| `POST ajax.php?requestMethod=login` | Returns valid JSON `{"response":true,"message":"Successfully Login!."}` — works |
| `POST ajax.php?requestMethod=fetchAll` | Returns corrupted body — `JSON.parse()` throws, DataTables shows "Invalid JSON response" |
| `GET portal/dashboard.php` | Page loads but DataTable never populates |

---

## Root Cause

### Why fetchAll corrupts JSON but login does not

`ob_start()` in `portal/ajax.php` (line 5, added by RC-09) buffers all output — including PHP notices — before it is sent to the client. If a PHP Deprecated notice fires inside the buffered request, it is prepended to the JSON output. The final response body becomes:

```
Deprecated: strlen(): Passing null to parameter #1 ($string) of type string is deprecated in .../Offer.php on line 421
{"draw":1,"recordsTotal":5,"recordsFiltered":5,"data":[...]}
```

`JSON.parse()` fails on this string. DataTables cannot recover.

**Login does not trigger this** because `User::login()` only runs a simple DB lookup with no null string operations. `fetchAll()` processes result rows from a LEFT JOIN and calls `strlen()` on fields that can be `NULL`.

### Offending code — `portal/library/Offer.php`

**Line 348** — implicit null-to-string concatenation:

```php
// BEFORE (broken):
$link = 'https://efbhalvbhdsurl.com/?oid='.$final_offer_list[$i]['slug_name']
    .'&tag='.$final_offer_list[$i]['tag_name']
    .'&affid='.$final_offer_list[$i]['network_name'];
```

`tag_name` comes from `LEFT JOIN tbl_tag` and `network_name` from `LEFT JOIN tbl_network`. If an offer has no tag or network, both are `NULL`. PHP 8.2 emits:

```
Deprecated: Implicitly converting NULL to string is deprecated
```

**Lines 419–423** — `strlen(null)`:

```php
// BEFORE (broken):
$sub_array[] = strlen($final_offer_list[$i]['tag_name']) > 15
    ? substr($final_offer_list[$i]['tag_name'],0,15)."..."
    : $final_offer_list[$i]['tag_name'];
// (same pattern for note, network_name)
```

PHP 8.2 emits:

```
Deprecated: strlen(): Passing null to parameter #1 ($string) of type string is deprecated
```

Both deprecations fire for every row in the result set that has a NULL `tag_name`, `note`, or `network_name`. On a dashboard with 5 rows and 3 NULL fields each, this can produce 15 notice lines before the JSON object begins.

### Secondary factor — `portal/ajax.php` display_errors

PHP 8.2 Docker dev images ship with `display_errors = On`. The commented-out `// ini_set('display_errors', true);` at line 2 of `ajax.php` was never the active cause — the php.ini default was. Without explicitly suppressing it, any Deprecated/Warning that PHP generates will flow into `ob_start()`'s buffer and corrupt the response.

---

## Fixes Applied

### Fix AJ-01 — `portal/ajax.php`

Added `ini_set('display_errors', '0')` immediately after `ob_start()`:

```php
// BEFORE:
ob_start();  // RC-09
header('Content-Type: application/json; charset=utf-8');

// AFTER:
ob_start();  // RC-09
ini_set('display_errors', '0');  // RI-HOTFIX-V1 AJ-01: prevent PHP 8.2 Deprecated notices from leaking into JSON responses
header('Content-Type: application/json; charset=utf-8');
```

This is the safety net. Even if a future code path introduces a new null operation, it will not corrupt the JSON response in production or test.

### Fix AJ-02 — `portal/library/Offer.php` line 348

Added `?? ''` to all three nullable fields in the `$link` construction:

```php
// BEFORE:
$link = 'https://efbhalvbhdsurl.com/?oid='.$final_offer_list[$i]['slug_name']
    .'&tag='.$final_offer_list[$i]['tag_name']
    .'&affid='.$final_offer_list[$i]['network_name'];

// AFTER:
$link = 'https://efbhalvbhdsurl.com/?oid='.($final_offer_list[$i]['slug_name'] ?? '')
    .'&tag='.($final_offer_list[$i]['tag_name'] ?? '')
    .'&affid='.($final_offer_list[$i]['network_name'] ?? '');
```

### Fix AJ-03 — `portal/library/Offer.php` lines 419–423

Added `?? ''` to all five `strlen()` calls and their corresponding else-branch returns:

```php
// BEFORE:
$sub_array[] = strlen($final_offer_list[$i]['tag_name']) > 15
    ? substr($final_offer_list[$i]['tag_name'],0,15)."..."
    : $final_offer_list[$i]['tag_name'];

// AFTER:
$sub_array[] = strlen($final_offer_list[$i]['tag_name'] ?? '') > 15
    ? substr($final_offer_list[$i]['tag_name'],0,15)."..."
    : ($final_offer_list[$i]['tag_name'] ?? '');
```

Same pattern applied to: `offer`, `slug_name`, `tag_name`, `note`, `network_name`.

---

## Why These Are the Only Changes Needed

| Option | Verdict |
|---|---|
| `ob_clean()` before `echo json_encode()` | Would work but changes control flow in every AJAX case — broader diff |
| `error_reporting(0)` globally | Silences all errors — hides real bugs |
| `ini_set('display_errors', '0')` in ajax.php | Targeted: suppresses output only for AJAX responses, does not affect error_log |
| `?? ''` on nullable fields | Fixes the actual null — correct regardless of display_errors setting |

Both AJ-01 (suppression) and AJ-02/AJ-03 (null guards) are applied because the null guards are the correct fix, and AJ-01 is defense-in-depth for any future null path not yet guarded.

---

## Why `ob_start()` Did Not Protect the Response

`ob_start()` buffers output — it does not filter it. It captures warnings/notices into the same buffer as the JSON output. When `echo json_encode($output)` runs, both the notice text and the JSON are in the buffer together. They are sent to the client as a single concatenated body. The HTTP `Content-Type: application/json` header is correct, but the body is not valid JSON.

---

## Verification

After applying these fixes:

```bash
# 1. POST fetchAll without session → should return Unauthorized JSON cleanly
curl -s -X POST "http://localhost/portal/ajax.php" -d "requestMethod=fetchAll" | python -m json.tool

# 2. Login then fetchAll with session cookie
curl -s -c /tmp/jar -X POST "http://localhost/portal/ajax.php" -d "requestMethod=login&username=<u>&password=<p>"
curl -s -b /tmp/jar -X POST "http://localhost/portal/ajax.php" -d "requestMethod=fetchAll&draw=1&start=0&length=10" | python -m json.tool
# Expected: {"draw":1,"recordsTotal":N,"recordsFiltered":N,"data":[[...]]}

# 3. PHP error log should be clean (no Deprecated notices from fetchAll)
grep "strlen.*null\|Implicitly converting" /var/log/php_errors.log
```

- [ ] `fetchAll` response parses as valid JSON
- [ ] `draw`, `recordsTotal`, `recordsFiltered`, `data` keys present
- [ ] Dashboard DataTable renders rows without "Invalid JSON response" warning
- [ ] PHP error log: no `Deprecated: strlen()` from Offer.php
- [ ] PHP error log: no `Deprecated: Implicitly converting NULL` from Offer.php

---

## Pre-existing Issues Not Fixed Here

| Issue | Location | Notes |
|---|---|---|
| `click_id=''` empty string stored literally | index.php L-01 | Known L-01 edge case documented in REGRESSION_TEST_MATRIX.md |
| SQL injection via `oid` (C-03) | Postback.php:15 | Out of scope per task rules |
| `ajax.php:68` postBack missing `??` | ajax.php | Pre-existing PHP 8 notice, behind auth |
