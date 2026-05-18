# Stabilization Report

**Date:** 2026-05-18  
**Branch:** restore-production-structure  
**Scope:** Bug fixes only — no refactoring, no schema changes, no routing changes.

---

## Fixes Applied

### 1. `portal/ajax.php` — `archiveOffer()` (line ~282)

**Problem:** Live `print_r($archive_status); die();` was the first statement executed after calling `$offer->archiveOffer()`. It terminated the request immediately, printing raw PHP output instead of a JSON response. The `if/else` block that emits `json_encode(...)` was dead code.

**Fix:** Removed `print_r($archive_status); die();`.

**Impact:** `archiveOffer` AJAX calls now return valid JSON to the frontend instead of crashing with a raw-output HTTP 200 that the JS JSON parser rejects.

---

### 2. `portal/ajax.php` — `deleteOffer()` (line ~297)

**Problem:** Same pattern as above — `print_r($delete_status); die();` short-circuited the function before the JSON response.

**Fix:** Removed `print_r($delete_status); die();`.

**Impact:** `deleteOffer` AJAX calls now return valid JSON.

---

### 3. `portal/export/download.php` — `bind_param()` / SQL mismatch (line ~75)

**Problem:** The SQL string used PHP variable interpolation for LIMIT and OFFSET (`LIMIT $limit OFFSET $offset`), leaving no `?` placeholders in the prepared statement. The subsequent `$stmt->bind_param('ii', $limit, $offset)` call tried to bind two parameters against a statement that had zero, causing a fatal error (`Warning: mysqli_stmt::bind_param(): Number of elements in type definition string doesn't match number of bind variables`).

**Fix:** Replaced the interpolated `$limit` / `$offset` at the end of the SQL string with `?` placeholders. The `bind_param('ii', $limit, $offset)` call is now correct — both values are already cast to `int` at lines 21–22 so injection is not a concern either way.

---

## Debug Statement Audit — Full Codebase

### Safe to Remove (already commented out)

All items below are `// comment` style — they do **not** affect runtime. They can be deleted during a future cleanup pass without any risk.

| File | Pattern | Notes |
|------|---------|-------|
| `portal/ajax.php:16` | `//print_r($_REQUEST); die();` | Commented, harmless |
| `portal/ajax.php:24` | `//echo $requestMethod; die();` | Commented, harmless |
| `portal/ajax.php:132` | `//echo "<pre>"; print_r($fetch_data); die();` | Commented, harmless |
| `portal/ajax.php:141` | `//echo "<pre>"; print_r($output); die();` | Commented, harmless |
| `portal/ajax.php:180` | `//print_r($filter_params); die();` | Commented, harmless |
| `portal/ajax.php:330` | `//echo "<pre>"; print_r($filter_type); die();` | Commented, harmless |
| `portal/ajax.php:360` | `//echo "<pre>"; print_r($fetch_sub_url_data); die();` | Commented, harmless |
| `portal/ajax.php:385` | `//echo "<pre>"; print_r($params); die();` | Commented, harmless |
| `portal/export/download.php:2,12` | `//echo 'in'; die();` | Commented, harmless |
| `portal/export/index.php:52,62,66` | `//echo 'in'; die();` / `//echo $result_count; die();` etc. | Commented, harmless |
| `portal/library/database/Database.php:60,104,144,191,230` | `//echo $sql; die();` | Commented, harmless |
| `portal/library/Postback(bkp-17-07-2024).php:6` | `//echo 'in'; die();` | Backup file, irrelevant |
| `portal/library/PostbackBeta.php:6` | `//echo 'in'; die();` | Commented, harmless |

### Do NOT Remove — Functional Code

| File | Line | Pattern | Reason |
|------|------|---------|--------|
| `portal/library/Offer.php:450` | `echo json_encode(...); die();` | **This is business logic**, not debug. It is the early-exit JSON response when a slug collision is detected during offer editing. Removing it would silently allow duplicate slugs. |

### Already Fixed in This Session

| File | Line | Pattern | Action |
|------|------|---------|--------|
| `portal/ajax.php:282` | `print_r($archive_status); die();` | **Removed** — was live, broke JSON |
| `portal/ajax.php:297` | `print_r($delete_status); die();` | **Removed** — was live, broke JSON |

---

## No Architecture Changes Made

- DB schema: unchanged  
- Routing: unchanged  
- Class structure: unchanged  
- Business logic: unchanged (only removed dead debug calls that short-circuited valid code paths)
