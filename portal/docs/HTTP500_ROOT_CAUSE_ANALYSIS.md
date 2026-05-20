# HTTP 500 Root Cause Analysis

**Branch:** php82-stability-v1  
**Date:** 2026-05-20  
**Scope:** `portal/ajax.php` → `requestMethod=importOffer` and all dashboard filter paths (`All`, `Active`, `Archived`, `Deleted`, `Network`, `Domain`)  
**Status:** Audit only — no auto-fixes applied.

> **Instrumentation note** — temporary `error_log('[DEBUG]...')` probes have been added to
> `Database.php`, `ajax.php`, `Upload.php`, and `Offer.php`.
> Read logs with:  `docker logs <container> 2>&1 | grep '\[DEBUG\]'`
> **Remove all `[DEBUG-TEMP]` blocks before merging to main.**

---

## Summary table

| # | ID | Severity | Path | Issue | Status |
|---|---|---|---|---|---|
| 1 | RC-01 | **CRITICAL** | `resetPassword` | `User::resetPassword` static call → method does not exist → PHP fatal → 500 | Not fixed |
| 2 | RC-02 | **HIGH** | `fetchAll` / Network filter | `$get_network_id[0]["id"]` undefined when network not found → malformed SQL | Pre-H01: 500 via `count(null)`; post-H01: degraded (wrong count) |
| 3 | RC-03 | **HIGH** | `fetchAll` / Domain filter | Empty `IN ()` clause when no domain match → MySQL syntax error | Pre-H01: 500; post-H01: 0 rows (no crash) |
| 4 | RC-04 | **HIGH** | `importOffer` | `save_data()` raw SQL — apostrophes in field values corrupt INSERT → silent failure, partial inserts | Not fixed |
| 5 | RC-05 | **HIGH** | `importOffer` | No DB transaction — partial inserts leave orphaned `tbl_offer_url` rows with no `tbl_sub_offer_url` | Not fixed |
| 6 | RC-06 | **MEDIUM** | `importOffer` | `$row_has_error` not reset between rows inside the same offer → all rows of an offer rejected on any single row error | Not fixed |
| 7 | RC-07 | **MEDIUM** | `importOffer` | `$start_date`/`$end_date` set by first validation loop; used in second insert loop via closure — last-iteration value only | Not fixed |
| 8 | RC-08 | **MEDIUM** | `importOffer` | PHP `post_max_size` / `max_input_vars` — large CSV silently truncated; PHP returns 500 with empty body | Not fixed |
| 9 | RC-09 | **MEDIUM** | all paths | No `ob_start()` in `ajax.php` — any upstream `echo`/`die()` corrupts JSON before output | Not fixed |
| 10 | RC-10 | **MEDIUM** | `fetchAll` / Network | `$get_network_id[0]["id"]` also used verbatim in `$searchQuery` else-branch; undefined offset → malformed SQL for the main data query | Not fixed |
| 11 | RC-11 | **LOW** | `importOffer` JS | Naive `split(",")` CSV parser breaks on quoted values with embedded commas | Not fixed |
| 12 | RC-12 | **LOW** | all paths | No `Content-Type: application/json` header set in `ajax.php` | Not fixed |
| 13 | RC-13 | **LOW** | `importOffer` | `uploadOffer()` signature declares `$params = array()` but receives a string; misleading, not a crash | Not fixed |
| 14 | RC-14 | **LOW** | startup | `Settings.php` calls `die()` with plain-text message if `.env` is absent — non-JSON output → client parse error | Not fixed |

---

## Detailed findings

---

### RC-01 — `User::resetPassword` static call to undefined method

| Field | Detail |
|---|---|
| **File / line** | `portal/ajax.php:450` |
| **Reproduction** | POST `ajax.php` with `requestMethod=resetPassword` |
| **Severity** | CRITICAL — guaranteed PHP fatal error → HTTP 500 |

**Execution path**

```
ajax.php → switch('resetPassword')
  → resetUserPassword()
    → User::resetPassword(...)   // ← static call
```

`User.php` defines only one non-static method `login()`. There is no `resetPassword` method on the class, static or otherwise.

```php
// ajax.php line 450
$resetpass = User::resetPassword(
    $data = array('username'=>$params['username'],'password'=>$params['password'])
);
```

PHP 8.x: `Fatal error: Call to undefined method User::resetPassword()` → process exits → HTTP 500.

**IDE diagnostic confirmed:** `P1013 Undefined method 'resetPassword'` at line 450.

**Recommended fix**

```php
// Add to User.php
public static function resetPassword(array $params): string {
    $db = new Database();
    $result = $db->update_data(
        DB_USER_TABLE,
        ['password' => $params['password']],
        ['user_name' => $params['username']]
    );
    return $result ? 'ok' : 'fail';
}
```

Or, if the feature is unused, remove the `resetPassword` case from the switch entirely.

---

### RC-02 — Network filter: undefined `$get_network_id[0]["id"]`

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Offer.php:213, 222` (post Phase-A numbering) |
| **Reproduction** | Dashboard → Filter By: Network → select any network value |
| **Severity** | HIGH — pre-H01 caused PHP 8.2 `TypeError` → 500; post-H01 returns wrong `recordsTotal` |

**Execution path**

```
ajax.php → fetchAllData($_REQUEST)
  → Offer::fetchAll(['filterType'=>'Network', 'filterValue'=>'...'])
    → fetch_data_new('tbl_network','id',['network_name'=>filterValue],1)
      → returns [] if network not in DB
    → $get_network_id[0]["id"]   ← PHP 8.x Warning: Undefined offset 0
```

**Pre-H01 crash chain**

```
filter_query(malformed SQL) → returns null
$totalRecords = null[0]['total']  → Warning chain
$final_offer_list = null (pre-H01)
count(null) → PHP 8.2 TypeError: count(): Argument #1 must be Countable|array  → FATAL → 500
```

**Remaining issue (post-H01)**

Even with the `filter_query` always returning `[]`, the SQL produced by the else-branch searchQuery still references `$get_network_id[0]["id"]` directly:

```php
// Offer.php ~line 222
$searchQuery = ' WHERE a.offer_status=1 AND a.network_id='.$get_network_id[0]["id"].'';
```

This still produces `network_id=` (no value), causing a MySQL syntax error on the main data query. `join_query` returns `[]` and `recordsTotal` is reported as 0 even when the network exists in the database (if `fetch_data_new` returns an unexpected structure).

**Recommended fix (patch example)**

```php
$get_network_id = $this->db->fetch_data_new('tbl_network','id',['network_name'=>$params['filterValue']],1);

if (empty($get_network_id)) {
    // Network not found — return empty DataTables response immediately
    return ['draw'=>intval($draw),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]];
}
$network_id = (int)$get_network_id[0]['id'];

$totalRecordsQuery = $this->db->filter_query(
    'SELECT COUNT(*) AS total FROM tbl_offer_url WHERE offer_status=1 AND network_id=' . $network_id
);
```

---

### RC-03 — Domain filter: empty `IN ()` clause

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Offer.php:248` (post Phase-A numbering) |
| **Reproduction** | Dashboard → Filter By: Domain → select a domain that exists in no sub-offer URL |
| **Severity** | HIGH — pre-H01: 500; post-H01: MySQL error on `filter_query`, 0 rows returned |

**Execution path**

```
Offer::fetchAll(['filterType'=>'Domain', 'filterValue'=>'example.com'])
  → filter_query('SELECT DISTINCT(main_offer_id)...LIKE "%example.com%"')
    → returns [] (no match)
  → $offer_ids = array_column([], 'main_offer_id')  = []
  → $final_ids = implode(',', [])  = ""
  → filter_query('...WHERE offer_status=1 AND id in ()')
    → MySQL ERROR 1064: syntax error near ')'
```

**Remaining issue (post-H01)**

The MySQL error is silently swallowed (`filter_query` returns `[]`). However, the `$totalRecords` access `[][0]['total']` still triggers PHP 8.x warnings. The main data query also uses `AND a.id in ()` → second MySQL error.

**Recommended fix (patch example)**

```php
$get_offer_id = $this->db->filter_query('SELECT DISTINCT(main_offer_id)...');
$offer_ids = array_column($get_offer_id, 'main_offer_id');

if (empty($offer_ids)) {
    return ['draw'=>intval($draw),'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]];
}
$final_ids = implode(',', array_map('intval', $offer_ids));
```

---

### RC-04 — `save_data()` raw SQL: apostrophes cause silent INSERT failure

| Field | Detail |
|---|---|
| **File / line** | `portal/library/database/Database.php:27` |
| **Reproduction** | Import CSV with offer name `McDonald's Offer` or any value containing `'` |
| **Severity** | HIGH — MySQL syntax error → `save_data` returns 0 → offer not inserted but no exception thrown; `$inserted_rows` stays 0; response says success |

**Execution path**

```
Upload::uploadOffer()
  → save_data('tbl_offer_url', ['offer'=>"McDonald's Offer", ...])
    → SQL: INSERT INTO tbl_offer_url (offer,...) VALUES ('McDonald's Offer',...)
                                                                    ↑ unescaped apostrophe
    → MySQL ERROR 1064
    → $this->conn->query() returns false
    → $insert_id = 0  (silently)
    → returns 0
  → if (!empty(0)) → FALSE — sub-offers never inserted
  → $inserted_rows stays 0
  → Final response: {"status":"success","inserted_rows":0}
```

The debug probe at `[DEBUG][DB::save_data] INSERT FAILED` will now surface this in logs.

**Recommended fix (patch example)**

```php
public function save_data($table, $info) {
    $insert_id = 0;
    $key = array_keys($info);
    $val = array_values($info);
    // Use real_escape_string on every value
    $escaped = array_map([$this->conn, 'real_escape_string'], array_map('strval', $val));
    $sql = "INSERT INTO " . $table . " (" . implode(', ', $key) . ") "
         . "VALUES ('" . implode("', '", $escaped) . "')";
    if ($this->conn->query($sql) === TRUE)
        $insert_id = $this->conn->insert_id;
    return $insert_id;
}
```

Note: the same raw-concatenation issue exists in `fetch_data`, `fetch_data_new`, and `update_data` for condition values.

---

### RC-05 — No DB transaction wrapping in import flow

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Upload.php:270-281` |
| **Reproduction** | Import CSV → main offer INSERT succeeds → any sub-offer INSERT fails (e.g. duplicate URL, DB timeout) |
| **Severity** | HIGH — partial inserts leave orphaned rows; re-import fails with "Offer already exists" on subsequent attempt |

**Execution path**

```
save_data(DB_OFFER_TABLE, ...)    → succeeds, returns offer_id=42
save_data('tbl_sub_offer_url', {url1})  → succeeds
save_data('tbl_sub_offer_url', {url2})  → FAILS (duplicate, timeout, etc.) → returns 0
$inserted_rows++                         → offer counted as inserted
```

Result: `tbl_offer_url` row 42 exists with only 1 of 2 sub-offer rows. The offer router will behave incorrectly (weight distribution wrong).

**Recommended fix**

Wrap the per-offer block in a transaction:

```php
$this->db->begin_transaction();  // requires adding begin/commit/rollback to Database class
try {
    $add_offer = $this->db->save_data(DB_OFFER_TABLE, $main_url_record);
    if (!$add_offer) throw new RuntimeException('Main offer insert failed');
    foreach ($sub_url_records as $record) {
        if (!$this->db->save_data('tbl_sub_offer_url', $record))
            throw new RuntimeException('Sub-offer insert failed');
    }
    $this->db->commit();
    $inserted_rows++;
} catch (RuntimeException $e) {
    $this->db->rollback();
    $errors[] = ['offer' => $offer_name, 'error' => $e->getMessage()];
}
```

---

### RC-06 — `$row_has_error` not reset between rows within the same offer

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Upload.php:77, 148-159` |
| **Reproduction** | CSV with 2 rows for the same offer where row 1 fails URL validation but row 2 is valid |
| **Severity** | MEDIUM — all rows for an offer are rejected once any row sets `$row_has_error = true`; correct rows silently dropped |

**Execution path**

```
foreach ($offer_data as $offer_name => $offer_rows)
    $row_has_error = false          ← reset per offer (OK)
    
    // Validation loop (loop 1)
    foreach ($offer_rows as $key => $values)
        // validation can set $row_has_error = true
    
    // Insert loop (loop 2)
    foreach ($offer_rows as $row_info)
        // $row_has_error is NEVER reset here
        if ($row_has_error)          ← true for ALL rows if any row in loop 1 failed
            $errors[] = ...          ← row 2 (valid) incorrectly rejected
```

**Recommended fix (patch example)**

```php
foreach ($offer_rows as $row_info) {
    $row = $row_info['data'];
    $row_number = $row_info['row_number'];
    $row_has_error = false;   // ← reset per row here, not just per offer
    $error_reason  = [];
    ...
```

---

### RC-07 — `$start_date` / `$end_date` bleed from validation loop into insert loop

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Upload.php:97-110, 266-267` |
| **Reproduction** | CSV with multiple rows per offer group; rows have different `start date` / `end date` |
| **Severity** | MEDIUM — only the last row's dates are used for all inserted sub-offers |

**Execution path**

```
// Loop 1 (validation) — iterates all rows of the offer
foreach ($offer_rows as $key => $values) {
    $start_date = $values['data']['start date'] ?? '';   // overwritten each iteration
    $end_date   = $values['data']['end date'] ?? '';
}
// $start_date now holds the value from the LAST iteration

// Loop 2 (insert)
$main_url_record = ['start_date' => $start_date, 'end_date' => $end_date];  // wrong row's dates
```

**Recommended fix**

Extract dates from the first row of the offer group before the loops, not inside the validation loop:

```php
$first_row      = $offer_rows[0]['data'];
$start_date     = $first_row['start date'] ?? '';
$end_date       = $first_row['end date'] ?? '';
if (empty($start_date)) $start_date = date('Y-m-d');
if (empty($end_date))   $end_date   = date('Y-m-d');
```

---

### RC-08 — PHP `post_max_size` / `max_input_vars` silently truncates large CSV payloads

| Field | Detail |
|---|---|
| **File / line** | `portal/import.php` (client) · `portal/ajax.php` (server) |
| **Reproduction** | Upload a CSV file whose `JSON.stringify(csvObjects)` payload exceeds `post_max_size` (default 8 MB) |
| **Severity** | MEDIUM — PHP returns HTTP 500 with empty response body when POST body exceeds limit |

**What happens**

When PHP receives a POST body larger than `post_max_size`, it discards the entire body. `$_REQUEST` is empty. The H-03 guard (`!isset($_REQUEST['requestMethod'])`) fires and returns `{"response":false,"message":"Missing requestMethod"}` — a 200 response, but the client's `JSON.parse(outerResponse.message)` then fails because `message` is a string not JSON, triggering the `catch(e)` branch in `import.php:140-143`.

In cases where PHP itself closes the connection before the script starts (certain server configurations), it may emit a true HTTP 500.

**Recommended fix**

In `ajax.php`, check for the truncated-body scenario:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && $_SERVER['CONTENT_LENGTH'] > 0) {
    http_response_code(413);
    echo json_encode(['response' => false, 'message' => 'Payload too large. Reduce CSV size.']);
    exit;
}
```

Also add to `docker-compose.yml` / PHP `ini`:
```
post_max_size = 32M
max_input_vars = 10000
upload_max_filesize = 32M
```

---

### RC-09 — No output buffering; any upstream output corrupts JSON

| Field | Detail |
|---|---|
| **File / line** | `portal/ajax.php:1` |
| **Reproduction** | Any PHP warning/notice printed before `echo json_encode(...)` |
| **Severity** | MEDIUM — corrupts JSON response; DataTables shows "Invalid JSON" error |

**What happens**

Without `ob_start()`, any content sent to stdout before the `json_encode` call — PHP warnings, the `die("Connection failed: ...")` string in `Database::__construct`, or the `die('Configuration error...')` in `Settings.php` — prepends to the JSON body. The result is not valid JSON, causing client-side parse failures that can resemble a 500.

Example: `Database::__construct` calls `die("Connection failed: " . $this->conn->connect_error)` which outputs raw text with no JSON wrapper.

**Recommended fix**

```php
<?php
ob_start();                          // buffer all output
header('Content-Type: application/json');

// ... all existing code ...

$output = ob_get_clean();
// If something unexpected was buffered before JSON, log it
if (strpos($output, '{') !== 0 && strpos($output, '[') !== 0) {
    error_log('[ajax.php] NON-JSON PREAMBLE DETECTED: ' . substr($output, 0, 200));
}
echo $output;
```

---

### RC-10 — Network filter: second undefined-offset use in `$searchQuery` else-branch

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Offer.php:222` |
| **Reproduction** | Same as RC-02 |
| **Severity** | MEDIUM — distinct from RC-02; this is the main data query path, not just the count query |

```php
// Else branch (no search term, just filter by network)
$searchQuery = ' WHERE a.offer_status=1 AND a.network_id='.$get_network_id[0]["id"].'';
//                                                          ↑ same undefined offset
```

The main `join_query` that populates `$final_offer_list` gets this malformed SQL. Even when `fetch_data_new` does return a result, if the result structure is ever `[['id'=>null]]`, the SQL produces `network_id=` (empty).

**Recommended fix:** Covered by RC-02 — guard `$get_network_id` with an early return.

---

### RC-11 — Naive CSV parser breaks on quoted values with embedded commas

| Field | Detail |
|---|---|
| **File / line** | `portal/import.php:101` |
| **Reproduction** | CSV with cell value `"Offer, Special"` (contains comma inside quotes) |
| **Severity** | LOW — incorrect data sent to server; may cause column-mismatch errors or silent data corruption |

```js
// import.php line 101
var rows = csvData.split("\n").map(row => row.split(","));
//                                              ↑ splits on ALL commas, ignoring quoted fields
```

A row `offer1,"Slug, A",tag1,...` is split into 9+ fragments instead of 8, shifting all subsequent column assignments.

**Recommended fix**

Replace the naive parser with a proper RFC 4180 CSV parser. A minimal approach:

```js
function parseCSVRow(row) {
    const result = [];
    let current = '', inQuotes = false;
    for (let i = 0; i < row.length; i++) {
        if (row[i] === '"') { inQuotes = !inQuotes; continue; }
        if (row[i] === ',' && !inQuotes) { result.push(current.trim()); current = ''; continue; }
        current += row[i];
    }
    result.push(current.trim());
    return result;
}
```

---

### RC-12 — Missing `Content-Type: application/json` header

| Field | Detail |
|---|---|
| **File / line** | `portal/ajax.php:1` |
| **Severity** | LOW — browsers and HTTP clients may misinterpret the response; can cause parse failures in strict clients |

**Recommended fix**

```php
<?php
header('Content-Type: application/json; charset=utf-8');
```

Add at the top of `ajax.php`, before any output.

---

### RC-13 — `uploadOffer()` parameter signature mismatch

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Upload.php:13` |
| **Severity** | LOW — misleading, not a crash |

```php
public function uploadOffer($params = array()) {   // declares array default
    $csvData = json_decode($params, true);          // but $params is used as string
```

The function always receives a JSON string but its signature implies it accepts an array. `json_decode(array(), true)` returns `null` if called with an array — not a 500 (the `isset($params)` / `$csvData && is_array($csvData)` guards prevent a crash), but the `json_decode(array)` call emits a PHP 8.x `TypeError` deprecation notice.

**Recommended fix**

```php
public function uploadOffer(string $params): string {
```

---

### RC-14 — `Settings.php` die() emits plain text on missing `.env`

| Field | Detail |
|---|---|
| **File / line** | `portal/library/Settings.php:13-18` |
| **Severity** | LOW / startup failure |

```php
die(
    'Configuration error: .env file not found.' . PHP_EOL . ...
);
```

This outputs plain text to stdout. The AJAX client receives non-JSON, `JSON.parse` throws, and the browser's `error:` callback fires with a confusing message. No HTTP 500, but the client cannot distinguish this from a 500.

**Recommended fix**

```php
header('Content-Type: application/json');
http_response_code(503);
die(json_encode(['response' => false, 'message' => 'Server configuration error. Contact admin.']));
```

---

## How to read the debug logs

```bash
# Docker — stream PHP error log in real time
docker logs -f <php-container> 2>&1 | grep '\[DEBUG\]'

# Filter by path
docker logs <php-container> 2>&1 | grep '\[DEBUG\]\[DB::save_data\]'
docker logs <php-container> 2>&1 | grep 'INSERT FAILED'
docker logs <php-container> 2>&1 | grep 'MYSQL_ERROR'
docker logs <php-container> 2>&1 | grep '\[DEBUG\]\[Upload'
docker logs <php-container> 2>&1 | grep '\[DEBUG\]\[Offer::fetchAll\]'
```

Reproducing a 500:
1. Trigger the action in the browser.
2. Note the HTTP status code in DevTools → Network.
3. Run the grep above immediately.
4. Match the `[DEBUG][ajax.php] requestMethod=` timestamp to the failing request.
5. Follow the chain: `fetchAllData` → `Offer::fetchAll` → `DB::join_query` / `DB::filter_query` → `MYSQL_ERROR`.

---

## Files with debug instrumentation added (to remove before merge)

| File | Markers added |
|---|---|
| `portal/ajax.php` | entry log, `fetchAllData` params, `importOffer` start/end |
| `portal/library/database/Database.php` | `save_data`, `fetch_data_new`, `update_data`, `join_query`, `filter_query` |
| `portal/library/Upload.php` | raw param, json_decode result, column detection, per-offer processing, insert start/fail/ok |
| `portal/library/Offer.php` | `fetchAll` entry, Network/Domain/Status branch resolution, response assembly |

Search for `[DEBUG-TEMP]` comments to locate all probes.
