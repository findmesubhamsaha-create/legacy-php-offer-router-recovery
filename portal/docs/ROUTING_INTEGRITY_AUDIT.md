# Routing Engine Integrity Audit

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**Scope:** Full trace of the offer routing path, weight algorithm, edge-case coverage, and failure modes. No fixes applied. No SQL changed. No architecture changed.

---

## Routing Flow Summary

```
HTTP GET /?oid=<slug>&click_id=<id>&<passthrough params>
  │
  ├── index.php (root)
  │     Parses REQUEST_URI → extracts all query params
  │     Strips oid → builds $final_query (passthrough params)
  │     Calls postBack(['oid'=>$_REQUEST['oid'], 'ip'=>REMOTE_ADDR, 'click_id'=>$_REQUEST['click_id']])
  │
  └── Postback::rotateUrl()          [Postback.php:9]
        │
        ├── QUERY 1: JOIN tbl_sub_offer_url + tbl_offer_url
        │     WHERE m.slug_name = <oid>
        │       AND m.offer_status = '1'          ← active offers only
        │       AND s.status = 'yes'              ← active sub-URLs only
        │     ORDER BY s.id ASC
        │     → returns array of [sub_url, weight] rows
        │
        ├── if empty → return nothing → index.php → redirect to 404.php
        │
        ├── get_link_to_display($get_sub_offers)  [Postback.php:45]
        │     rand(0, 99)
        │     foreach sub-offer: rand -= weight; if rand < 0 break
        │     return $weight['sub_url'] ?? null
        │
        ├── QUERY 2: JOIN tbl_sub_offer_url + tbl_offer_url
        │     WHERE s.sub_url = <selected URL>    ← NOT scoped to slug
        │       AND m.offer_status = '1'
        │       AND s.status = 'yes'
        │     ORDER BY s.id ASC
        │     → returns [main_offer_id, id] for click log
        │
        ├── save_data('tbl_click', [click_id, offer_id, sub_offer_id, ip, date])
        │
        └── if save_data returns truthy → return $site
              else                       → return nothing → index.php → redirect to 404.php

index.php
  ├── if $getoffer set → header("Location: $getoffer?$final_query")
  └── else             → header("Location: 404.php?$final_query")
```

**Tables touched per request:**
- `tbl_offer_url` — read (offer_status check)
- `tbl_sub_offer_url` — read twice (URL list, then ID lookup)
- `tbl_click` — write (every routed request)

---

## Issues — Critical

### C-01 — Routing entirely blocked if click-log INSERT fails

| Field | Detail |
|---|---|
| **Severity** | Critical |
| **File / Line** | `portal/library/Postback.php:38–40` |
| **Code** | `if($add_click){ return $site; }` |

**Description**  
The routing return value — the destination URL — is gated behind the success of writing a row to `tbl_click`. `save_data()` returns `0` on any INSERT failure (DB down, table locked, constraint violation, full disk). When it returns `0`, `$add_click` is falsy and `rotateUrl()` returns `null`. `index.php` then redirects every visitor to `404.php` instead of the offer destination.

**Reproduction**  
Temporarily DROP or LOCK `tbl_click`, then make a routing request: `/?oid=<valid-slug>`. Expected: redirect to destination. Actual: redirect to 404.

**Impact**  
100% routing failure whenever the click-logging write path has any problem. A single DB hiccup takes the entire routing service down. Traffic loss is silent — no error is surfaced.

**Proposed fix**  
Decouple click logging from the routing return. Return `$site` unconditionally once a valid URL is selected; log the click regardless of its success:

```php
// after save_data call
return $site;  // move outside the if($add_click) guard
```

**Risk**  
None for routing correctness. Click counts may under-count if the INSERT fails, but that is the correct trade-off: availability over perfect click attribution.

---

### C-02 — Click attributed to wrong offer when same URL appears in multiple offers

| Field | Detail |
|---|---|
| **Severity** | Critical |
| **File / Line** | `portal/library/Postback.php:22–27` |

**Description**  
After `get_link_to_display()` selects a `sub_url`, a second DB query fetches the `offer_id` and `sub_offer_id` for click logging. That query searches by `sub_url` globally across all offers — it is not scoped back to the original `slug_name`:

```sql
SELECT s.main_offer_id, s.id
FROM tbl_sub_offer_url s
JOIN tbl_offer_url m ON s.main_offer_id = m.id
WHERE s.sub_url = '<selected URL>'        -- no slug_name filter
  AND m.offer_status = '1'
  AND s.status = 'yes'
ORDER BY s.id ASC
```

`$get_site_id[0]` takes the lowest `id` row — the offer that first registered that URL. If the same destination URL is used across two or more offers (which the DB permits and import does not prevent across different offers), every click on both offers is attributed to the oldest one.

**Reproduction**  
1. Create two offers with overlapping `sub_url` values, e.g., both pointing to `https://example.com/landing`.
2. Route traffic through the second offer's slug.
3. Check `tbl_click` — `offer_id` will be the first offer's ID, not the second's.

**Impact**  
Click analytics are wrong. Revenue attribution is wrong. Any report or commission calculation based on `tbl_click` is corrupted when URL reuse exists. The routing itself delivers the correct URL; only the attribution is broken.

**Proposed fix**  
Carry the `main_offer_id` from Query 1 through to the click record without a second lookup:

```php
// In Query 1, also select s.id and m.id:
"SELECT s.sub_url, s.weight, s.id AS sub_id, m.id AS offer_id
 FROM tbl_sub_offer_url s
 JOIN tbl_offer_url m ON s.main_offer_id = m.id
 WHERE m.slug_name = '...' ..."

// Pass the full row array to get_link_to_display, which returns the selected row.
// Use $selected_row['offer_id'] and $selected_row['sub_id'] directly.
```

**Risk**  
Low. Query 1 already returns the necessary rows; this is a data-passing change, not a logic change. Eliminates the second DB round-trip as a bonus.

---

### C-03 — SQL injection via `oid` parameter

| Field | Detail |
|---|---|
| **Severity** | Critical |
| **File / Line** | `portal/library/Postback.php:15`, `index.php:15` |

**Description**  
`$params['oid']` originates from `$_REQUEST['oid']` (index.php:15) and is interpolated directly into the SQL string in Query 1:

```php
"... WHERE m.slug_name = '".$params['oid']."' AND ..."
```

No sanitisation, no prepared statement. An attacker who can send HTTP requests to the router can inject arbitrary SQL. The same applies to Query 2 via `$site` (which can be null or a crafted string if the first query was manipulated).

**Reproduction**  
```
GET /?oid=anything' UNION SELECT sub_url,weight FROM tbl_sub_offer_url-- -
```
Depending on column counts and types, data from any table readable by the DB user can be extracted.

**Impact**  
Full read access to the database over an unauthenticated public endpoint. The router is publicly accessible by design. An attacker with no credentials can enumerate offers, extract user credentials from `tbl_user`, or manipulate routing results.

**Proposed fix**  
Use prepared statements with `mysqli_stmt`:

```php
$stmt = $this->db->conn->prepare(
    "SELECT s.sub_url, s.weight FROM tbl_sub_offer_url s
     JOIN tbl_offer_url m ON s.main_offer_id = m.id
     WHERE m.slug_name = ? AND m.offer_status = '1' AND s.status = 'yes'
     ORDER BY s.id ASC"
);
$stmt->bind_param('s', $params['oid']);
$stmt->execute();
```

**Risk**  
Medium. Requires refactoring `join_query()` or bypassing it for this call. Must also address all other injection points (C-03 is the most exposed since it is unauthenticated).

---

## Issues — High

### H-01 — Weight algorithm uses hardcoded rand(0,99); breaks when weights ≠ 100

| Field | Detail |
|---|---|
| **Severity** | High |
| **File / Line** | `portal/library/Postback.php:45–53` |

**Description**  
`get_link_to_display()` implements cumulative-weight selection with a hardcoded random range of `rand(0, 99)`:

```php
$rand = rand(0, 100-1);
foreach($sites as $site => $weight) {
    $rand -= $weight['weight'];
    if ($rand < 0) break;
}
return $weight['sub_url'] ?? null;
```

This is only correct when the active weights sum to exactly 100. If the sum differs:

**Sum < 100 (e.g., weights [30, 50]):**  
`rand` values 80–99 exhaust the loop without breaking. PHP `foreach` leaves `$weight` pointing to the last iterated item. The last URL always receives the overflow probability. With weights [30, 50], the intended distribution is 37.5%/62.5%; the actual distribution is 30%/70%.

**Sum > 100 (e.g., weights [60, 60]):**  
The total weight exceeds the rand range. The second URL's rand range starts at 60, which maps to only 40 values (60–99), not 60. Actual distribution becomes 60%/40% instead of 50%/50%.

**Sum = 0 (all zero-weight active URLs):**  
`rand -= 0` never produces a negative value. Loop exhausts. Last item is always returned. 100% traffic to one URL regardless of intended weights.

The CSV import enforces `sum = 100` for `status = 'yes'` URLs, but the manual add/edit form (Offer.php) has **no server-side weight validation**. Any offer created or edited through the admin UI can have an arbitrary weight sum.

**Reproduction**  
Create an offer via the admin UI with two active URLs: weight=30 and weight=50 (sum=80). Route 1000 requests. Observe the second URL receives ~70% of traffic instead of ~62.5%.

**Impact**  
Incorrect traffic distribution for any offer whose weights do not sum to 100. In an advertising context, affiliates are over- or under-served relative to contracted ratios.

**Proposed fix**  
Compute the total weight dynamically and use it as the rand ceiling:

```php
public function get_link_to_display($sites) {
    $total = array_sum(array_column($sites, 'weight'));
    if ($total <= 0) return null;
    $rand = mt_rand(1, $total);
    $selected = end($sites);  // fallback to last
    foreach ($sites as $site => $weight) {
        $rand -= $weight['weight'];
        if ($rand <= 0) { $selected = $weight; break; }
    }
    return $selected['sub_url'] ?? null;
}
```

**Risk**  
Low. Self-contained function with no callers other than `rotateUrl()`. The change corrects distribution without altering the call signature.

---

### H-02 — Zero-weight active URLs receive traffic via loop fallthrough

| Field | Detail |
|---|---|
| **Severity** | High |
| **File / Line** | `portal/library/Postback.php:45–52` |

**Description**  
Query 1 selects sub-offers where `s.status = 'yes'` but applies no `weight > 0` filter. A URL with `weight = 0` and `status = 'yes'` enters the candidate pool.

In the weight loop, `$rand -= 0` leaves `$rand` unchanged. A zero-weight item can never satisfy `$rand < 0` on its own. However:

1. If the zero-weight item is the **last** entry and `$rand` reaches it still ≥ 0 (because total weights < rand ceiling of 99), the loop exhausts and PHP leaves `$weight` pointing to the zero-weight item. It is returned as the selected URL.
2. If **all** items have weight 0, the last item is always selected (100% of traffic).

Both cases violate the operator's intent: a weight-0 URL was explicitly given zero share.

**Reproduction**  
Create an offer with URLs: weight=[50, 50, 0]. The third URL has status='yes', weight=0. With the hardcoded rand(0,99), when `$rand` is 0-49 → first URL; 50-99 → second URL. The third URL is unreachable in this case (weights sum to 100). But change weights to [40, 40, 0] (sum=80) — rand values 80-99 fall through to the third URL.

**Impact**  
Zero-weight URLs receive traffic. An operator who sets weight=0 expecting no traffic (perhaps staging a URL) receives live traffic proportional to the weight deficit.

**Proposed fix**  
Filter zero-weight items from Query 1, or skip them in the loop:

```sql
-- In Query 1, add:
AND s.weight > 0
```

Or, as part of the H-01 fix: use `$total = array_sum(...)` — if `$total = 0`, return `null` immediately.

**Risk**  
Low. Filtering weight=0 items changes query results only for the edge case that currently produces wrong behavior. No valid traffic path is removed.

---

### H-03 — start_date / end_date fields never checked during routing

| Field | Detail |
|---|---|
| **Severity** | High |
| **File / Line** | `portal/library/Postback.php:15` |

**Description**  
`tbl_offer_url` has `start_date` and `end_date` columns. Both are written during offer creation and import. The routing query only checks `offer_status = '1'` — it does not filter by date:

```sql
WHERE m.slug_name = '...'
  AND m.offer_status = '1'
  -- start_date and end_date are not referenced
```

**Effects:**
- An offer with `end_date = yesterday` continues routing traffic indefinitely.
- An offer with `start_date = next_month` routes traffic immediately after creation.
- An operator who sets date bounds expects them to be honoured; the system silently ignores them.

**Reproduction**  
Create an offer with `end_date = '2020-01-01'` (past). Make a routing request. Traffic is routed normally despite the expired date.

**Impact**  
Expired affiliate offers receive traffic. Budget overruns, commission disputes, and broken destination URLs are all possible. Future-dated offers go live immediately, violating scheduling expectations.

**Proposed fix**  
Add date bounds to the routing query:

```sql
WHERE m.slug_name = ?
  AND m.offer_status = '1'
  AND (m.start_date IS NULL OR m.start_date <= CURDATE())
  AND (m.end_date IS NULL OR m.end_date >= CURDATE())
```

**Risk**  
Medium. Adding date filtering changes routing behaviour for any offer with non-null dates. Existing active offers with `end_date < today` would stop routing. Confirm all intended-active offers have correct dates before enabling.

---

### H-04 — No server-side weight validation in manual add/edit

| Field | Detail |
|---|---|
| **Severity** | High |
| **File / Line** | `portal/library/Offer.php:442–623` (addNewOffer) |

**Description**  
CSV import (`Upload.php:171`) enforces that active-URL weights sum to 100 ± 0.0001 before inserting. The manual add/edit path (`Offer.php::addNewOffer`) stores whatever weight values are submitted with no validation at all:

```php
$sub_url_record = array(
    'main_offer_id' => $offerId,
    'sub_url'       => $param['suburl']['url'][$x],
    'weight'        => $param['suburl']['weight'][$x],  // no sum check
    'status'        => $param['suburl']['status'][$x]
);
$add_sub_offer = $this->db->save_data('tbl_sub_offer_url', $sub_url_record);
```

An operator can create or edit an offer via the dashboard with weights [10, 10, 10] (sum=30) or [60, 60] (sum=120). Both result in incorrect routing distributions as described in H-01.

**Reproduction**  
Via the admin UI, create an offer with two active URLs, each with weight=30 (sum=60). Save. The offer is accepted and stored. Routing will over-serve the last URL.

**Impact**  
Silently incorrect distribution for any manually-managed offer. There is no user-visible error, no server error, and no logging. The routing engine has no mechanism to detect invalid weight sums at query time.

**Proposed fix**  
Add server-side validation in `addNewOffer()` before the sub-offer insert loop:

```php
$active_weight_sum = 0;
for ($x = 0; $x < count($param['suburl']['url']); $x++) {
    if (strtolower($param['suburl']['status'][$x]) === 'yes') {
        $active_weight_sum += (float)$param['suburl']['weight'][$x];
    }
}
if (abs($active_weight_sum - 100) > 0.01) {
    echo json_encode(['status' => false,
        'message' => 'Active URL weights must sum to 100. Current sum: ' . $active_weight_sum]);
    die();
}
```

**Risk**  
Low for new creates. For edits: operators who already have offers with non-100 sums will get an error they didn't previously see. Consider allowing edit to save with a warning rather than a hard block.

---

### H-05 — Null return from get_link_to_display proceeds to second DB lookup unchecked

| Field | Detail |
|---|---|
| **Severity** | High |
| **File / Line** | `portal/library/Postback.php:18–27` |

**Description**  
`get_link_to_display()` returns `null` when:
- All weights are zero (loop fallthrough, `$weight` is last item, but weight-0 URL is a different scenario — see H-02)
- The `$sites` array is somehow empty (shouldn't reach this path, but defensive)
- The `??` null coalescing returns `null`

After that return, `rotateUrl()` proceeds unconditionally:

```php
$site = $this->get_link_to_display($get_sub_offers);
// $site is null here in edge cases — not checked
$get_site_id = $this->db->join_query(
    "... WHERE s.sub_url= '".$site."' ..."
    // null cast to '' in string context → WHERE s.sub_url = ''
);
$get_main_id = $get_site_id[0]['main_offer_id'];  // PHP 8 notice if $get_site_id is []
$get_sub_id  = $get_site_id[0]['id'];             // PHP 8 notice if $get_site_id is []
```

The second query runs against an empty string URL, likely returning no rows. `$get_site_id[0]` on an empty array throws a PHP 8 Notice (`Undefined array key 0`) and produces null values. A click record with `offer_id=null` and `sub_offer_id=null` is then attempted. If the DB has NOT NULL constraints on those columns, the INSERT fails, `$add_click = 0`, and routing returns null → 404.

**Reproduction**  
Create an offer where all active sub-URLs have weight=0. Route a request through its slug. Observe the PHP notice in the error log and the 404 response.

**Impact**  
PHP notices logged for every such request. Potentially invalid rows inserted into `tbl_click` (null offer/sub IDs). Routing returns 404 due to the click-log failure cascading with C-01.

**Proposed fix**  
Add a null guard immediately after `get_link_to_display()`:

```php
$site = $this->get_link_to_display($get_sub_offers);
if ($site === null) {
    return null;  // no valid URL selected; caller handles 404
}
```

**Risk**  
None. This is purely defensive; it converts implicit undefined behaviour into an explicit early exit.

---

## Issues — Medium

### M-01 — checkSlug() uses LIKE, causing false-positive slug collision

| Field | Detail |
|---|---|
| **Severity** | Medium |
| **File / Line** | `portal/library/Offer.php:25` |

**Description**  
`checkSlug()` is used to validate uniqueness before creating or editing an offer slug. It uses a LIKE pattern:

```php
$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,
    'slug_name like "%'.strtolower($param).'%"', 1);
```

A slug `"abc"` will match any existing slug that contains the substring `"abc"`: `"abc"`, `"abcdef"`, `"xyzabc"`, `"my-abc-offer"`, etc.

An operator trying to create slug `"offer"` would be blocked if any existing offer has a slug containing the string `"offer"` (e.g., `"special-offer"`, `"offer2024"`).

The routing query at `Postback.php:15` uses exact match (`=`), so two distinct slugs like `"offer"` and `"offer2"` would both route independently — the LIKE check is therefore more restrictive than the routing engine requires.

**Reproduction**  
1. Create offer with slug `"my-offer"`.
2. Try to create a new offer with slug `"offer"`.
3. `checkSlug()` returns a match (because `"my-offer"` contains `"offer"`).
4. New offer creation is blocked even though routing `/?oid=offer` would work fine.

**Impact**  
Valid slugs are rejected. Operators must choose slugs with no substring overlap with existing ones, which is unintuitive and undocumented.

**Proposed fix**  
Use exact match in `checkSlug()`:

```php
$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,
    ['slug_name' => strtolower($param)], 1);
```

**Risk**  
Low. Makes the check less restrictive (correct), aligning it with the actual routing lookup semantics.

---

### M-02 — All URLs inactive: silent 404, no operational visibility

| Field | Detail |
|---|---|
| **Severity** | Medium |
| **File / Line** | `portal/library/Postback.php:15–41` |

**Description**  
The routing query filters `s.status = 'yes'`. If an offer exists and is active (`offer_status = 1`) but every one of its sub-URLs has `status = 'no'` (or they are all `deleted_status = 'yes'`), Query 1 returns an empty array. The `if(!empty($get_sub_offers))` branch is skipped, `rotateUrl()` returns nothing, and the visitor sees `404.php`.

There is no logging, no alert, and no way to distinguish this state from:
- An unknown slug
- An archived offer
- A deleted offer
- A DB error

**Impact**  
An operator who deactivates all sub-URLs on an active offer will route all traffic to 404 with no indication in any log that this is happening. The offer appears "active" in the admin dashboard but delivers no traffic.

**Proposed fix**  
Log the distinguishable failure cases at a minimum:

```php
if (empty($get_sub_offers)) {
    error_log("[router] no active sub-URLs for oid=" . $params['oid']);
    return null;
}
```

Longer term: separate the query into two steps — first check if the slug/offer exists at all, then check for active URLs — so distinct HTTP responses (or at least log entries) can be produced for each failure mode.

**Risk**  
None. Logging is additive and does not change routing behaviour.

---

### M-03 — SQL injection via click_id parameter

| Field | Detail |
|---|---|
| **Severity** | Medium |
| **File / Line** | `index.php:15`, `portal/library/database/Database.php:29` |

**Description**  
`click_id` is taken from `$_REQUEST['click_id']` and passed to `save_data('tbl_click', ...)`. `save_data()` builds the INSERT by direct string interpolation:

```php
$sql = "INSERT INTO ".$table." (" . implode(', ', $key) . ") "
     . "VALUES ('" . implode("', '", $val) . "')";
```

A crafted `click_id` value such as `'); DROP TABLE tbl_click; --` would be interpolated verbatim. While this is a write-path injection rather than a read-path one (like C-03), it can corrupt click data or cause destructive DDL if the DB user has sufficient privileges.

**Reproduction**  
```
GET /?oid=valid-slug&click_id=test'),('0','1','1','127.0.0.1','2020-01-01
```
This injects an additional row into `tbl_click`.

**Impact**  
Click data can be poisoned. If the DB user has DDL privileges, tables can be truncated or dropped. Severity is lower than C-03 because click_id is a write path, not a read path.

**Proposed fix**  
Use a prepared statement for the click INSERT, or at minimum cast `click_id` to an integer/validate it before use:

```php
$click_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_REQUEST['click_id'] ?? '');
```

**Risk**  
Low. Input sanitisation on a specific field does not affect the routing logic.

---

### M-04 — No exit after header() in index.php

| Field | Detail |
|---|---|
| **Severity** | Medium |
| **File / Line** | `index.php:20, 25` |

**Description**  
Neither redirect path in `index.php` calls `exit` after `header("Location: ...")`:

```php
if(isset($getoffer)){
    header("Location: ".$getoffer.'?'.$final_query);
    // no exit
}
else{
    header("Location: 404.php?".$final_query);
    // no exit
}
function postBack(...) { ... }
```

PHP honours the `Location` header but continues executing the script body. Currently there is no code after the if/else, so in practice nothing harmful runs. However, the `function postBack()` declaration that follows is parsed every request regardless; if code is ever added between the if/else and the function definition, it will execute after the redirect header is sent, potentially leaking output.

**Impact**  
Low immediate risk. Latent architectural issue: any future code added after the closing brace will execute on every request, even redirected ones.

**Proposed fix**  
Add `exit` after each `header()` call:

```php
header("Location: ".$getoffer.'?'.$final_query);
exit;
```

**Risk**  
None. `exit` is the PHP idiom for "response is complete".

---

### M-05 — Redirect loop if sub_url is set to the router's own domain

| Field | Detail |
|---|---|
| **Severity** | Medium |
| **File / Line** | `portal/library/Postback.php:15`, `index.php:20` |

**Description**  
There is no check that a selected `sub_url` differs from the router's own base URL. If an operator stores `https://<router-domain>/?oid=same-slug` as a sub-offer URL (e.g., due to a misconfiguration or import error), the router will redirect to itself indefinitely. The browser will terminate the loop (typically after 10–20 redirects and display a "too many redirects" error), but every hop logs a click.

**Reproduction**  
Create an offer with slug `loop-test` and one sub-URL of `https://<router-domain>/?oid=loop-test`. Request `/?oid=loop-test`. Observe the browser redirect loop.

**Impact**  
User sees an error. Click table is flooded with loop-generated entries. No data loss, but the offer is effectively unreachable.

**Proposed fix**  
In `index.php`, before issuing the redirect, verify the destination host does not match `$_SERVER['HTTP_HOST']`:

```php
$dest = parse_url($getoffer, PHP_URL_HOST);
if ($dest && $dest === $_SERVER['HTTP_HOST']) {
    header("Location: 404.php");
    exit;
}
```

**Risk**  
Low. Affects only misconfigured offers. Correct offers are unaffected.

---

## Issues — Low

### L-01 — Undefined index notices for oid and click_id in PHP 8

| Field | Detail |
|---|---|
| **Severity** | Low |
| **File / Line** | `index.php:15` |

**Description**  
```php
$getoffer = postBack($params = array(
    'oid'      => $_REQUEST['oid'],
    'ip'       => $_SERVER['REMOTE_ADDR'],
    'click_id' => $_REQUEST['click_id'] ? $_REQUEST['click_id'] : '0'
));
```

In PHP 8, accessing `$_REQUEST['oid']` when `oid` is absent generates a `Notice: Undefined array key "oid"`. `$_REQUEST['click_id']` has the same issue AND uses a falsy ternary (`? :`) rather than null coalescing (`??`), so a `click_id=0` passed explicitly would also default to `'0'` (coincidentally the same value, but the intent is different).

Additionally, `$_REQUEST['click_id'] ? ... : '0'` — the ternary evaluates the string `'0'` as falsy, meaning an explicitly supplied `click_id=0` would be treated as missing.

**Reproduction**  
`GET /` with no `oid` parameter. Check the PHP error log for Notice messages.

**Impact**  
Notice spam in error logs. If `display_errors = On`, notices may appear in the HTTP response body before the redirect header, potentially causing header-already-sent warnings (analogous to the BOM regression from the previous hotfix).

**Proposed fix**  
```php
'oid'      => $_REQUEST['oid']      ?? '',
'click_id' => $_REQUEST['click_id'] ?? '0',
```

**Risk**  
None.

---

### L-02 — Trailing `?` appended to redirect URL when no passthrough params

| Field | Detail |
|---|---|
| **Severity** | Low |
| **File / Line** | `index.php:20` |

**Description**  
```php
header("Location: ".$getoffer.'?'.$final_query);
```

When the only query param in the original request is `oid` (e.g., `/?oid=slug`), after `unset($params['oid'])`, `$params` is empty. `http_build_query([])` returns `''`. The redirect becomes:

```
Location: https://destination.com/landing?
```

The trailing `?` is syntactically valid but visually noisy in server logs, affiliate dashboards, and analytics tools, and some stricter servers may reject it.

**Proposed fix**  
```php
$location = $getoffer . ($final_query !== '' ? '?' . $final_query : '');
header("Location: " . $location);
```

**Risk**  
None.

---

### L-03 — DB query failures are indistinguishable from "not found"

| Field | Detail |
|---|---|
| **Severity** | Low |
| **File / Line** | `portal/library/database/Database.php:212–228` |

**Description**  
`join_query()` (and all other Database methods) return an empty array `[]` on both "no rows found" and "query failed". There is no error logging and no exception thrown:

```php
public function join_query($sql) {
    $return = [];
    $result = $this->conn->query($sql);
    if ($result) { ... }
    // silently returns [] on failure
    return $return;
}
```

At the routing layer, a DB error in Query 1 is processed identically to "no offer found for this slug" — both result in a 404.

**Impact**  
Operational blindness. DB problems manifest as routing failures with no log evidence at the DB layer. Only `tbl_click` absence would hint at the problem.

**Proposed fix**  
Log query failures:

```php
if (!$result) {
    error_log("[db] join_query failed: " . $this->conn->error . " | SQL: " . substr($sql, 0, 200));
}
```

**Risk**  
None. Additive logging.

---

### L-04 — HTTP_HOST used directly in URL construction without validation

| Field | Detail |
|---|---|
| **Severity** | Low |
| **File / Line** | `index.php:7` |

**Description**  
```php
$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https')
             . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
```

`HTTP_HOST` is supplied by the client. A malicious or misconfigured proxy can set it to an arbitrary value. In this code, `$actual_link` is only used for `parse_url` to extract query params. The extracted params are re-encoded by `http_build_query` before being appended to the redirect, so the injection surface is limited. However, if the code is ever extended to use `$actual_link` directly (e.g., for logging or self-referencing), a forged `HTTP_HOST` could cause misdirection.

**Impact**  
No immediate exploitability in the current code path, but a latent host-header injection risk.

**Proposed fix**  
Use `$_SERVER['QUERY_STRING']` directly instead of reconstructing the URL:

```php
parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
```

**Risk**  
None. Simpler and avoids the HTTP_HOST dependency entirely.

---

## Summary Table

| ID | Severity | File | Description |
|---|---|---|---|
| C-01 | **Critical** | Postback.php:38 | Routing blocked when click-log INSERT fails |
| C-02 | **Critical** | Postback.php:22 | Click attributed to wrong offer (global URL lookup) |
| C-03 | **Critical** | Postback.php:15 | SQL injection via `oid` parameter (unauthenticated) |
| H-01 | High | Postback.php:46 | Weight algorithm uses hardcoded rand(0,99); breaks when sum ≠ 100 |
| H-02 | High | Postback.php:45 | Zero-weight active URLs receive traffic via loop fallthrough |
| H-03 | High | Postback.php:15 | start_date / end_date never checked; expired/future offers always route |
| H-04 | High | Offer.php:442 | No server-side weight validation in manual add/edit |
| H-05 | High | Postback.php:18 | Null from get_link_to_display() proceeds unchecked to second DB lookup |
| M-01 | Medium | Offer.php:25 | checkSlug() uses LIKE — false-positive blocks valid slugs |
| M-02 | Medium | Postback.php:15 | All-inactive URLs: silent 404, no log distinction from missing slug |
| M-03 | Medium | index.php:15 | SQL injection via click_id parameter |
| M-04 | Medium | index.php:20 | No exit after header() redirect |
| M-05 | Medium | Postback.php:15 | Redirect loop if sub_url points back to router domain |
| L-01 | Low | index.php:15 | PHP 8 undefined index notices for oid / click_id |
| L-02 | Low | index.php:20 | Trailing `?` in redirect URL when no passthrough params |
| L-03 | Low | Database.php:212 | DB query failures silent — indistinguishable from not-found |
| L-04 | Low | index.php:7 | HTTP_HOST used in URL construction without validation |

---

## Fix Priority Order

Recommended order based on severity and implementation coupling:

1. **C-01** — Decouple routing from click-log success. One line change. No dependencies.
2. **H-05** — Add null guard after `get_link_to_display()`. Two line change. Prevents L-01 cascading.
3. **L-01** — `??` null coalescing on `$_REQUEST` accesses. Prevents notice-triggered header corruption.
4. **M-04** — Add `exit` after `header()`. Zero risk, best practice.
5. **H-01 + H-02** — Fix weight algorithm together (compute total dynamically, filter zero-weights). Self-contained function change.
6. **C-02** — Scope second lookup to `slug_name`. Requires passing `main_offer_id` from Query 1.
7. **H-04** — Add server-side weight validation to `addNewOffer()`. Prevents future H-01 instances.
8. **M-01** — Fix `checkSlug()` to use exact match. One-line change.
9. **H-03** — Add date range filter to routing query. Needs operator confirmation that date fields are populated correctly.
10. **C-03 + M-03** — Prepared statements. Requires Database.php refactor. Highest effort, critical priority.
11. **M-02, L-02, L-03, L-04, M-05** — Operational hardening and defensive logging.

---

## No-Change Findings

The following were inspected and found to be correct or handled:

- **Archived offers (offer_status=2)**: Correctly excluded from routing by `offer_status='1'` filter. ✓
- **Deleted offers (offer_status=3)**: Correctly excluded from routing. ✓
- **Deleted sub-URLs (deleted_status='yes')**: Not returned by Query 1 (no `deleted_status` filter needed because `status='yes'` is already required; a deleted URL is also set to `status='no'` and `weight=0` by the edit path). Confirmed: `addNewOffer()` sets `deleted_status='yes', weight=0, status='no'` for removed URLs. ✓
- **resetOffer() slug collision check**: Correctly checks for existing active offers with the same name or slug before reactivating. ✓
- **Import weight validation**: Upload.php enforces `abs(sum - 100) < 0.0001` for `status='yes'` URLs before inserting. ✓
- **Deterministic vs random**: Routing is intentionally non-deterministic (weighted random). No infinite loop is possible within `get_link_to_display()` itself — the `foreach` always terminates. ✓
- **Empty `tbl_sub_offer_url`**: If an offer has no sub-URLs at all, Query 1 returns empty → early return → 404. No crash. ✓
