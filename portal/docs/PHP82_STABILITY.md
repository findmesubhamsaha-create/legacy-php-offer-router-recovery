# PHP 8.2 Runtime Stability — Phase A Fixes

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Scope:** Runtime warnings, JSON corruption, empty-state failures. No routing, auth, SQL, import logic, or business logic was modified.

---

## H-01 — Database methods return undefined variable

| Field | Detail |
|---|---|
| **Issue** | `join_query()` and `filter_query()` never initialise `$return`. When the query returns zero rows the variable is never assigned, causing a PHP 8.x `Undefined variable` warning and returning `null` instead of `[]`. Callers that pass the result to `count()`, `array_column()`, or a `for` loop then emit secondary warnings or crash. |
| **Files changed** | `portal/library/database/Database.php` |
| **Fix** | Added `$return = [];` as the first statement inside both methods, before the `$this->conn->query()` call. |
| **Risk** | None. Callers already expect an array; returning an empty array instead of `null` is the correct contract. |
| **Rollback** | Remove the two `$return = [];` lines. |

---

## H-02 — Error display directives in ajax.php

| Field | Detail |
|---|---|
| **Issue** | `ini_set('display_errors', 1)`, `ini_set('display_startup_errors', 1)`, and `error_reporting(E_ALL)` were present at the top of `ajax.php`. These override Docker / `php.ini` configuration, leak PHP warnings and stack traces into AJAX JSON responses, and corrupt the JSON payload that DataTables receives. |
| **Files changed** | `portal/ajax.php` |
| **Fix** | Removed the three runtime directives. The commented-out precursor lines (`// error_reporting(E_ALL)`, `// ini_set('display_errors', true)`) were left in place as they were already inactive. PHP error logging (to Docker stderr / `php-fpm` log) is unaffected. |
| **Risk** | None. Error visibility in production should be controlled by `php.ini` / Docker env, not application code. |
| **Rollback** | Re-add the three `ini_set` / `error_reporting` lines before `session_start()`. |

---

## H-03 — Unguarded $_REQUEST['requestMethod']

| Field | Detail |
|---|---|
| **Issue** | Line 21 of `ajax.php` accessed `$_REQUEST['requestMethod']` directly without an `isset()` check. Any request that omits the parameter (health checks, preflight requests, direct browser hits) emits an `Undefined array key` warning in PHP 8.x and then falls through to the `default:` switch case silently. |
| **Files changed** | `portal/ajax.php` |
| **Fix** | Added an `isset()` guard immediately before the assignment. Missing key now exits early with a structured JSON error response: `{"response":false,"message":"Missing requestMethod"}`. |
| **Risk** | None. Only affects requests that were already broken (no `requestMethod` supplied). |
| **Rollback** | Remove the four-line `if (!isset(...)) { ... exit; }` block. |

---

## M-08 — DataTables empty-state JSON corruption

| Field | Detail |
|---|---|
| **Issue** | The `else` branches in `fetchAllData()`, `fetchReport()`, and `getSubOffers()` returned `{"data":""}` (a string) when no records exist. DataTables requires `data` to be a JSON array (`[]`). A string value causes DataTables to throw a client-side error and renders the table permanently broken on empty state. |
| **Files changed** | `portal/ajax.php` |
| **Fix** | Changed `"data" => ''` to `"data" => []` in all three empty-state output arrays. |
| **Risk** | None. DataTables parses `[]` correctly as zero rows. |
| **Rollback** | Revert `[]` back to `''` in the three affected `else` branches. |

---

## PHP 8.2-01 — Postback: undefined `$weight` after foreach

| Field | Detail |
|---|---|
| **Issue** | `Postback::get_link_to_display()` used `$weight` (the foreach value variable) after the loop ended to return `$weight['sub_url']`. If `$sites` is empty the variable is never assigned, producing an `Undefined variable` warning and a `TypeError` on `null['sub_url']`. |
| **Files changed** | `portal/library/Postback.php` |
| **Fix** | Initialised `$weight = null;` before the loop. Changed the return to `$weight['sub_url'] ?? null` so a missing sub_url degrades gracefully instead of crashing. |
| **Risk** | Minimal. `rotateUrl()` only calls this method inside `if(!empty($get_sub_offers))`, so `$sites` is never empty in practice. The null return is handled by the existing `if($add_click)` guard in the caller. |
| **Rollback** | Remove `$weight = null;` and revert return to `return $weight['sub_url'];`. |

---

## PHP 8.2-02 — Offer: `explode()` on null `sub_url`

| Field | Detail |
|---|---|
| **Issue** | `Offer::addNewOffer()` calls `fetch_data_new()` with `GROUP_CONCAT(sub_url,"")` to retrieve existing sub-offer URLs. MySQL returns one row with a `NULL` value when no sub-offers match. Passing `null` to `explode()` is a `TypeError` in PHP 8.x (argument 2 must be `string`). This crashed the entire edit-offer flow for new offers with no pre-existing sub-URLs. |
| **Files changed** | `portal/library/Offer.php` |
| **Fix** | Applied `?? ''` to both `$check_sub_offer_del[0]['sub_url']` and `$check_sub_offer[0]['sub_url']` before passing to `explode()`. An empty string produces `['']` from `explode`, and `array_diff` / `in_array` against an array of real URLs treats it as a non-match — preserving existing behaviour. |
| **Risk** | Very low. The `?? ''` only activates when `sub_url` is `NULL` (no rows existed), which was already a crash path. |
| **Rollback** | Remove `?? ''` from both `explode()` calls. |

---

## PHP 8.2-03 — Upload: undefined column variables

| Field | Detail |
|---|---|
| **Issue** | In `Upload::uploadOffer()`, `$slug_name_column`, `$start_date_column`, and `$end_date_column` were set only inside `elseif` branches of a header-scan loop. If the CSV contained no matching column heading, the variables were never assigned. `$slug_name_column` is then passed to `empty()` on line 47, producing an `Undefined variable` warning in PHP 8.x. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | Initialised all three variables to `''` alongside the other column variables before the header-scan loop. |
| **Risk** | None. The existing `empty()` check on line 47 already rejects uploads that are missing required columns; this only removes the warning that preceded that rejection. |
| **Rollback** | Remove the three initialisation lines. |

---

## PHP 8.2-04 — Upload: uninitialised accumulator arrays

| Field | Detail |
|---|---|
| **Issue** | Inside the `foreach ($offer_correct_rows as $valid_row)` loop, `$urls`, `$weights`, `$statuses`, and `$outputArray` were appended to without prior initialisation. In PHP 8.x, appending to an undeclared variable emits an `Undefined variable` warning. More critically, the variables were never reset between loop iterations, causing URL, weight, and status data from one offer to bleed into the next offer's insert when multiple offers appeared in a single CSV upload. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | Added `$urls = []; $weights = []; $statuses = []; $outputArray = [];` at the top of the `else` block that precedes the `foreach ($valid_row ...)` inner loop, so each offer's data starts clean. |
| **Risk** | Low for single-offer CSVs (common case). For multi-offer CSVs this is a correctness fix — data no longer cross-contaminates between offers. |
| **Rollback** | Remove the four initialisation lines added inside the `else` block. |

---

---

## FILTER-BUG — "All" status filter causes 500

**Root cause**

`dashboard.php` line 180 has a hardcoded `<option value="All">All</option>` in `#status_menu`. When selected while `#filter_menu` is on "Status" (its default), the JS handler sends `filterType=Status, filterValue=All` to `ajax.php → Offer::fetchAll()`.

Inside the `Status` branch of `fetchAll()`:

```php
$offer_status = ["Active"=>1, "Archived"=>2, "Deleted"=>3];
// 'All' is not a key → PHP 8.x Warning → null
$totalRecordsQuery = $this->db->filter_query(
    'SELECT COUNT(*) AS total FROM tbl_offer_url WHERE offer_status= ' . $offer_status['All'] . ''
);
// SQL = "...WHERE offer_status= "  ← MySQL syntax error
// filter_query returned null (pre H-01) → count(null) → PHP 8.2 TypeError → 500
```

**Changed lines** — `portal/library/Offer.php`, Status branch of `fetchAll()` (was lines 261–295)

Replaced the three direct `$offer_status[$params['filterValue']]` accesses with a single resolved variable at the top of the branch:

```php
$status_id    = $offer_status[$params['filterValue']] ?? null;
$status_where = ($status_id !== null) ? ' WHERE offer_status=' . $status_id : '';
$status_and   = ($status_id !== null) ? " AND a.offer_status='" . $status_id . "'" : '';
```

- `filterValue='All'` → `$status_id=null` → `$status_where=''` → `SELECT COUNT(*) AS total FROM tbl_offer_url` (all records)
- `filterValue='Active'` → `$status_id=1` → SQL identical to before
- Same for `Archived`→2, `Deleted`→3

Also added `?? 0` to both `$totalRecords` and `$totalFilteredRecords` assignments to prevent undefined-offset warnings if a query fails.

**Verification steps**

1. Load dashboard, select Status → All → table should show **all offers** across all statuses.
2. Select Status → Active → table shows only `offer_status=1` records (unchanged).
3. Select Status → Archived → only `offer_status=2`.
4. Select Status → Deleted → only `offer_status=3`.
5. With any status selected, type in the DataTables search box → rows should filter by the search term AND the selected status (or all statuses for "All").
6. Confirm no PHP errors/warnings in `docker logs` for any of the above steps.

---

## Modified files summary (Phase A)

| File | Changes |
|---|---|
| `portal/library/database/Database.php` | H-01: `$return = []` in `join_query()` and `filter_query()` |
| `portal/ajax.php` | H-02: removed error display directives; H-03: `requestMethod` guard; M-08: `data:[]` empty state |
| `portal/library/Postback.php` | PHP 8.2-01: `$weight = null` init, `?? null` return |
| `portal/library/Offer.php` | PHP 8.2-02: `?? ''` on both `explode()` sub_url calls; FILTER-BUG: All-status filter fix |
| `portal/library/Upload.php` | PHP 8.2-03: init `$slug_name_column`, `$start_date_column`, `$end_date_column`; PHP 8.2-04: init `$urls`, `$weights`, `$statuses`, `$outputArray` |

---

---

# Batch 2A + 2B — Import Atomicity, Per-Row Reset, JSON Safety

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Scope:** RC-05, RC-06, RC-07, RC-09, RC-12. No routing, auth, business logic, SQL injection surface, or import structure was modified.

---

## RC-05 — DB transaction for importOffer

| Field | Detail |
|---|---|
| **Issue** | `Upload::uploadOffer()` called `save_data(DB_OFFER_TABLE, ...)` for the main offer then immediately looped over sub-URL inserts with no transaction guard. If any sub-URL insert failed (duplicate key, DB error), the main offer row was already committed. The DB was left in a partial state: an offer with no sub-URLs, which causes 500s in the routing layer on the next request for that offer. |
| **Files changed** | `portal/library/database/Database.php`, `portal/library/Upload.php` |
| **Fix** | Added `begin_transaction()`, `commit()`, and `rollback()` methods to `Database` wrapping `$this->conn->begin_transaction()` etc. In `Upload::uploadOffer()`, the main-offer insert and the sub-URL insert loop are now wrapped in `begin_transaction() / try { ... commit() } catch (\Exception $e) { rollback() }`. On any failure the entire offer (main + subs) is rolled back and an entry is added to `$errors[]`. |
| **Risk** | Low. MySQLi autocommit is `ON` by default; `begin_transaction()` temporarily disables it for the scope of the try block. Existing single-row paths (non-CSV add/edit) do not call these methods and are unaffected. |
| **Rollback** | Remove the three methods from `Database`. In `Upload.php`, replace the try/catch block with the original `$add_offer = ...; if(!empty($add_offer)){ ... }` pattern. |

---

## RC-06 — Reset `$row_has_error` per row in second validation loop

| Field | Detail |
|---|---|
| **Issue** | `Upload::uploadOffer()` uses two successive `foreach` loops over `$offer_rows`. The second loop checks `$row_has_error` at the end to decide whether to stage the row for insertion. The two reset lines (`$row_has_error = false; $error_reason = [];`) at the top of the loop body were **commented out**. This meant that once any row triggered an error, `$row_has_error` stayed `true` for all subsequent rows in the same offer group — causing every valid sub-URL row after the first bad one to be incorrectly discarded. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | Uncommented `$row_has_error = false;` and `$error_reason = [];` at the top of the second `foreach ($offer_rows as $row_info)` loop. Each row now starts with a clean error state. |
| **Risk** | Low. The fix only affects offers with multiple sub-URL rows where one row has an invalid/duplicate URL. Valid rows after a bad row are now correctly staged rather than silently dropped. |
| **Rollback** | Re-comment both reset lines. |

---

## RC-07 — Fix start/end date scope leakage across offer rows

| Field | Detail |
|---|---|
| **Issue** | `$start_date` and `$end_date` were extracted from `$values['data']` **inside** the first `foreach ($offer_rows ...)` validation loop. After the loop, both variables held the values from the **last** row processed. When multiple offers were present in one CSV, the last row of offer N's dates were silently used as the dates for every offer. For single-offer CSVs the leakage did not manifest. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | Removed `$start_date` / `$end_date` extraction and validation from inside the first loop. Added an explicit extraction block **before** both loops, reading from `$offer_rows[0]['data']` (the first row of each offer group). Date validation errors are added directly to `$errors[]` as offer-level entries (matching the pattern already used for the weight-total check), so they are not subject to the per-row reset introduced by RC-06. |
| **Risk** | Low. Behaviour for single-offer CSVs is identical. Multi-offer CSVs now correctly use each offer's first-row dates. Empty dates still default to `date('Y-m-d')` as before. |
| **Rollback** | Remove the pre-loop date block. Inside the first `foreach` loop, re-add `$start_date = $values['data']['start date'] ?? '';` and `$end_date = $values['data']['end date'] ?? '';` along with the original date validation block that sets `$row_has_error`. |

---

## RC-09 — Output buffering for JSON-safe AJAX responses

| Field | Detail |
|---|---|
| **Issue** | PHP `E_WARNING` / `E_NOTICE` messages (e.g. from `Database::__construct` `die()`, or any stray `echo` inside required files) could be emitted to the output stream before `ajax.php` called `echo json_encode(...)`. The result was a response body like `Warning: mysqli ...{"response":true,...}` — valid JSON prefixed with plain text — which the browser's `JSON.parse()` throws a `SyntaxError` on, causing the DataTables error modal and form submissions to silently fail. |
| **Files changed** | `portal/ajax.php`, `portal/library/database/Database.php` |
| **Fix** | Added `ob_start()` immediately after `session_start()` in `ajax.php`. All output is now buffered; `echo json_encode(...)` writes into the buffer and PHP flushes it cleanly on script exit. Also replaced the `die("Connection failed: ...")` in `Database::__construct` with a JSON `echo` + `exit` (discarding any partial buffer first via `ob_end_clean()`) so DB connection failures produce valid JSON rather than a plain-text fatal. |
| **Risk** | Very low. `ob_start()` is transparent to all existing echo/print calls. The buffer is auto-flushed by PHP on script end; no explicit `ob_end_flush()` is required. Existing `exit` calls in the requestMethod guard also flush correctly. |
| **Rollback** | Remove `ob_start()` from `ajax.php`. Revert `Database::__construct` connection error back to `die("Connection failed: ...")`. |

---

## RC-12 — Content-Type: application/json header

| Field | Detail |
|---|---|
| **Issue** | `ajax.php` returned JSON with no `Content-Type` header. Browsers and XHR clients received `text/html` (PHP default), causing some frameworks / fetch polyfills to attempt HTML parsing and reject the response, and making DevTools response inspection require manual interpretation. |
| **Files changed** | `portal/ajax.php` |
| **Fix** | Added `header('Content-Type: application/json; charset=utf-8')` immediately after `ob_start()` (before any `require` statements). With `ob_start()` active, the header is set before the response body is flushed, regardless of output order. |
| **Risk** | None. JSON clients that already parse the body correctly are unaffected. |
| **Rollback** | Remove the `header(...)` line. |

---

## Verification checklist (Batch 2A+2B)

- [ ] Import a CSV with one offer and two sub-URLs: both rows appear in the DB together, or neither does (transaction atomicity).
- [ ] Import a CSV where sub-URL 2 of 3 has an invalid URL: the offer is skipped (rolled back); `error_rows` in the response names row 2 specifically.
- [ ] Import a CSV where offer A has a bad URL on row 1: offer A is rejected; offer B in the same CSV is still processed correctly (per-row reset).
- [ ] Import a multi-offer CSV with different start/end dates per offer: each offer uses its own first-row dates, not the previous offer's dates.
- [ ] In DevTools Network tab, `ajax.php` responses show `Content-Type: application/json; charset=utf-8`.
- [ ] Disconnect MySQL, hit any dashboard AJAX call: response is `{"response":false,"message":"Database connection failed"}` (not a PHP fatal or blank).
- [ ] All five status filter combinations (All / Active / Archived / Deleted / search term) still work without 500.

---

## Modified files summary (Batch 2A+2B)

| File | Changes |
|---|---|
| `portal/library/database/Database.php` | RC-05: added `begin_transaction()`, `commit()`, `rollback()`; RC-09: `die()` → JSON exit with `ob_end_clean()` |
| `portal/library/Upload.php` | RC-05: transaction wrap around main+sub insert block; RC-06: uncomment per-row `$row_has_error`/`$error_reason` reset; RC-07: date extraction moved before loops, validation errors go to `$errors[]` directly |
| `portal/ajax.php` | RC-09: `ob_start()` after `session_start()`; RC-12: `Content-Type: application/json` header |

---

---

# Batch 3 — importOffer Stability: Empty Slot Guard + Frontend JSON Fix

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Scope:** Blank url/weight/status values from optional CSV columns reaching the DB; frontend double-JSON-parse after RC-12 added `Content-Type: application/json`. No routing, auth, or non-import logic modified.

---

## IMP-01 — Empty URL slot guard in validation loop

| Field | Detail |
|---|---|
| **Issue** | The CSV template contains 3 URL slot columns (`url1/weight1/status1`, `url2/weight2/status2`, `url3/weight3/status3`). When an offer uses only 2 URLs, the third slot columns are empty strings. The validation loop iterated all columns unconditionally, adding empty strings to `$offer_urls[]` (causing false duplicate-URL matches on subsequent rows) and passing `null` weight values through `(float)null`. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | Inside the `for ($i = ...)` loop: trimmed all three values on read; added a guard — `if ($url === '' && $weight === '' && $status === '') { continue; }` — to skip entirely-empty optional slots. Changed `$offer_urls[] = $url` to only push when `$url !== ''` to prevent empty-string false duplicates. |
| **Risk** | None. Valid slots (non-empty URL) pass the guard unchanged. Weight total calculation is unaffected: empty-status slots never contributed to `$offer_weight_total`. |
| **Rollback** | Remove the `trim()` casts on the three variables, the empty-slot `continue` guard, and restore `$offer_urls[] = $url` unconditionally. |

---

## IMP-02 — Empty URL slot guard in insert loop (`status ENUM` truncation)

| Field | Detail |
|---|---|
| **Issue** | After validation passes, the insert loop (`for ($x = 0; $x < count($outputArray['url']); $x++)`) iterated all collected URL/weight/status values including those from empty optional slots. Inserting `status = ''` into a `status ENUM('yes','no')` column caused MySQL error `Data truncated for column 'status'`, which threw a `RuntimeException`, rolled back the transaction, and returned `{"response":true,"message":"{\"status\":\"partial_success\",...,\"error\":\"Database error: ...\"}"}`— a successful HTTP response with an embedded error. |
| **Files changed** | `portal/library/Upload.php` |
| **Fix** | At the top of the insert `for ($x = ...)` loop body: extract trimmed `$u` (url), `$s` (status), `$w` (weight). Added guard — `if ($u === '' || $s === '' || $w === '') { continue; }` — so any slot missing url, status, or weight is silently skipped. Used the trimmed values directly in `$sub_url_record` instead of re-accessing `$outputArray`. |
| **Risk** | None for normal CSVs. If a future CSV intentionally has url+weight but no status, the slot is skipped rather than erroring. This matches the ENUM constraint requirement. |
| **Rollback** | Remove the three trim lines and the `continue` guard; restore the original `$sub_url_record` array using direct `$outputArray` access. |

---

## IMP-03 — Frontend double-JSON-parse after Content-Type header

| Field | Detail |
|---|---|
| **Issue** | RC-12 added `Content-Type: application/json` to `ajax.php`. jQuery's `$.ajax()` auto-parses the response body when it sees this header, so the `success` callback receives a JavaScript object, not a raw string. The existing `JSON.parse(responseString)` then called `JSON.parse({...})` which coerces the object to `"[object Object]"` and throws a `SyntaxError`, silently swallowing every import response in the UI. |
| **Files changed** | `portal/import.php` |
| **Fix** | Renamed the callback parameter to `responseRaw`. Replaced `JSON.parse(responseString)` with `(typeof responseRaw === 'string') ? JSON.parse(responseRaw) : responseRaw`. Also made the inner message parse safe: `(typeof outerResponse.message === 'string') ? JSON.parse(outerResponse.message) : outerResponse.message`. Added `outerResponse` to the `console.error()` call for easier debugging. |
| **Risk** | None. The ternary short-circuits to the already-parsed object when jQuery auto-parses, and falls back to explicit `JSON.parse` if the response somehow arrives as a plain string. |
| **Rollback** | Restore the original `success: function(responseString)` block with `JSON.parse(responseString)` and `JSON.parse(outerResponse.message)`. |

---

## Verification checklist (Batch 3)

- [ ] Upload a CSV with 2 sub-URLs per offer (3-slot template, slot 3 empty): offer inserts with exactly 2 sub-URL rows, no `Data truncated` error.
- [ ] Upload a CSV with 1 sub-URL per offer: offer inserts with 1 sub-URL row.
- [ ] Upload a CSV where one slot has a URL but empty status: that slot is skipped, rest of offer inserts if weight total still equals 100 (or offer rejected if weight drops below 100 after skip).
- [ ] After a successful import, modal shows "Inserted Rows: N" and "All rows processed successfully."
- [ ] After a partial-success import, modal shows the error rows table — not a blank modal or JS console error.
- [ ] DevTools console shows no `SyntaxError: Unexpected token` on import responses.

---

## Modified files summary (Batch 3)

| File | Changes |
|---|---|
| `portal/library/Upload.php` | IMP-01: empty-slot guard + clean `$offer_urls` tracking in validation loop; IMP-02: empty-slot guard in insert loop, trimmed values prevent ENUM truncation |
| `portal/import.php` | IMP-03: safe JSON parse in success callback — handles both auto-parsed object and raw string |

---

---

# Pre-commit Cleanup — Temporary Instrumentation Removed

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Scope:** Removed all `[DEBUG]` / `[DEBUG-TEMP]` `error_log()` probes added during HTTP 500 investigation. Permanent fixes (transactions, RC-06, RC-07, RC-09, RC-12, import guards, JSON safety) are fully preserved.

---

## What was removed

Probe sites are distinct `error_log()` calls (and their associated `// [DEBUG-TEMP]` comment lines) that were added solely to diagnose the HTTP 500 regression and have no production value.

| File | Probe sites removed | Notes |
|---|---|---|
| `portal/ajax.php` | 7 | Entry log on missing `requestMethod`; every-request `requestMethod` log; `fetchAllData` parameter dump; `importOffer` start/end/warning logs |
| `portal/library/database/Database.php` | 14 | SQL logs and result-count logs in `save_data`, `fetch_data_new`, `update_data`, `join_query`, `filter_query`; debug-only `if/else` block in `save_data` that had no functional code |
| `portal/library/Upload.php` | 8 | Upload entry type/length log; `json_decode` result log; column-detection log; missing-columns abort log; per-offer processing log; transaction pre-insert log; transaction post-insert log; rollback log inside `catch` |
| `portal/library/Offer.php` | 5 | `fetchAll` entry parameter dump; Network branch resolution log; Domain branch matched-IDs log; Status branch resolution log; response row-count log |
| **Total** | **34** | |

## What was preserved

- `Database::begin_transaction()`, `commit()`, `rollback()` (RC-05)
- `ob_start()` and `Content-Type: application/json` header in `ajax.php` (RC-09, RC-12)
- `Database::__construct` JSON exit on connection failure (RC-09)
- `$row_has_error = false` per-row reset in `Upload::uploadOffer()` (RC-06)
- Pre-loop date extraction and `$errors[]` validation in `Upload::uploadOffer()` (RC-07)
- Empty-slot `continue` guards in validation and insert loops (IMP-01, IMP-02)
- `try/catch` with `rollback()` and `$errors[]` append in `Upload::uploadOffer()` (RC-05)
- All `?? ''`, `?? null`, `?? 0` null-coalescing guards from PHP 8.2 fixes
- All `$return = []` initialisations in `Database` (H-01)
- Status-filter null-safe resolution in `Offer::fetchAll()` (FILTER-BUG)

---

## Verification

Run `grep -r '\[DEBUG' portal/` — should return zero matches.
