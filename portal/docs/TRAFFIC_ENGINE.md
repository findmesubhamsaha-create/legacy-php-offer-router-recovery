# Traffic Distribution Engine

## Purpose

When an affiliate click arrives at the system, the traffic engine must:
1. Look up the offer by slug
2. Select one destination sub-URL using weighted random selection
3. Record the click event
4. Return the selected URL so the caller can redirect the browser

---

## Entry Point

**File:** `ajax.php`  
**Trigger:** HTTP request with `requestMethod=postBack`

```
GET/POST ajax.php?requestMethod=postBack&oid=<slug>&ip=<ip>&click_id=<uuid>
```

| Parameter | Source | Description |
|-----------|--------|-------------|
| `oid` | Query string | The offer's `slug_name` value |
| `ip` | Query string | Visitor's IP address (caller-supplied) |
| `click_id` | Query string | Caller-supplied unique click identifier |

---

## Primary Implementation: `library/Postback.php`

### `rotateUrl($params)`

**Step 1 — Fetch active sub-URLs**

```sql
SELECT s.sub_url, s.weight
FROM tbl_sub_offer_url s
JOIN tbl_offer_url m ON s.main_offer_id = m.id
WHERE m.slug_name = '<oid>'
  AND m.offer_status = '1'
  AND s.status = 'yes'
ORDER BY s.id ASC
```

Only returns rows where:
- The parent offer is active (`offer_status = 1`)
- The sub-URL is enabled (`status = 'yes'`)
- `deleted_status` is implicitly handled by `status = 'yes'` (deleted rows are also set to `status = 'no'`)

**Step 2 — Weighted random selection**

Calls `get_link_to_display($sub_urls)`:

```php
public function get_link_to_display($sites) {
    $rand = rand(0, 100 - 1);   // integer 0..99
    foreach ($sites as $site => $weight) {
        $rand -= $weight['weight'];
        if ($rand < 0) break;
    }
    return $weight['sub_url'];
}
```

**Algorithm walkthrough:**

Given weights: `[URL_A: 70, URL_B: 20, URL_C: 10]`

- `rand(0, 99)` → e.g. `55`
- Subtract URL_A weight: `55 - 70 = -15` → negative → select URL_A
- If rand was `75`: `75 - 70 = 5` → subtract URL_B weight: `5 - 20 = -15` → select URL_B
- If rand was `92`: `92 - 70 = 22`, `22 - 20 = 2`, `2 - 10 = -8` → select URL_C

This is the standard weighted random selection pattern. Distribution accuracy depends on:
- Weights being integers (decimal weights work but reduce precision)
- All active weights summing to exactly 100
- Sub-URLs being ordered consistently (ORDER BY s.id ASC)

**Step 3 — Look up IDs for click recording**

```sql
SELECT s.main_offer_id, s.id
FROM tbl_sub_offer_url s
JOIN tbl_offer_url m ON s.main_offer_id = m.id
WHERE s.sub_url = '<selected_url>'
  AND m.offer_status = '1'
  AND s.status = 'yes'
```

Retrieves `offer_id` and `sub_offer_id` for the selected URL.

**Step 4 — Record the click**

```php
$this->db->save_data('tbl_click', [
    'click_id'     => $params['click_id'],
    'offer_id'     => $offer_id,
    'sub_offer_id' => $sub_offer_id,
    'ip_address'   => $params['ip'],
    'created_at'   => date('Y-m-d')
]);
```

**Step 5 — Return selected URL**

The `rotateUrl` method returns the selected `sub_url` string. `ajax.php` echoes it back as JSON. The caller's JavaScript performs the redirect.

---

## Alternative Implementation: `library/PostbackBeta.php`

A second routing class exists with the same interface but minor implementation variations. It is referenced experimentally and not the default routing class used in production `ajax.php`. The core algorithm is identical.

---

## Weight Invariant

The system requires that active sub-URL weights for any single offer sum to exactly 100.

**Enforced at:**
- `library/Offer.php` — on offer create/edit via the dashboard
- `library/Upload.php` — on CSV import validation

**Not enforced at:**
- The routing layer itself — if weights don't sum to 100, routing behavior is undefined:
  - If sum < 100: `rand` may never go negative → last URL in the list gets selected for the "overflow" range, effectively getting more traffic than its weight specifies
  - If sum > 100: the final URLs may never be reached

**Validation tolerance:** `abs($total_weight - 100) > 0.0001` triggers a validation error. This allows for minor floating-point imprecision.

---

## Click Data Flow

```
Inbound click request
    │
    ▼
ajax.php receives oid + ip + click_id
    │
    ▼
Postback::rotateUrl()
    │
    ├── SQL: fetch active sub-URLs for slug
    │
    ├── Weighted random selection → chosen sub_url
    │
    ├── SQL: resolve offer_id + sub_offer_id
    │
    ├── INSERT into tbl_click
    │       (click_id, offer_id, sub_offer_id, ip_address, created_at)
    │
    └── Return chosen sub_url
         │
         ▼
    ajax.php echoes JSON → JS redirects browser
```

---

## Daily Aggregation: `library/cron.php`

Raw click data in `tbl_click` is aggregated once per day by the cron script:

```sql
SELECT C.offer_id, O.offer, COUNT(C.id) AS clicks, C.created_at
FROM tbl_click C
JOIN tbl_offer_url O ON O.id = C.offer_id
WHERE C.created_at = CURDATE()
GROUP BY C.created_at, C.offer_id
```

Then for each row:

```sql
INSERT INTO tbl_report (main_offer_id, main_offer_url, offer_clicks, report_date)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE offer_clicks = ?
```

The `UNIQUE KEY (main_offer_id, report_date)` constraint in `tbl_report` ensures the cron is idempotent — re-running it on the same day overwrites the count rather than duplicating it.

---

## Routing URL Format

The public redirect URL format (shown in the dashboard "Get Link" modal):

```
https://efbhalvbhdsurl.com/?oid=<slug_name>&tag=<tag_name>&affid=<network_name>
```

| Parameter | Mapped to |
|-----------|-----------|
| `oid` | `tbl_offer_url.slug_name` — primary routing key |
| `tag` | `tbl_tag.tag_name` — informational only (not used in routing logic) |
| `affid` | `tbl_network.network_name` — informational only (not used in routing logic) |

Only `oid` drives the routing decision. `tag` and `affid` are passed through but are not read by `Postback.php`.

---

## Edge Cases and Gaps

### No offer found for slug
If `slug_name` does not match any active offer, the sub-URL query returns no rows. `get_link_to_display([])` would iterate over an empty array — `$weight['sub_url']` would be undefined. The behavior is a PHP notice/warning and an empty or null return value. No explicit 404 handling exists in the routing path.

### Expired offers (start_date / end_date)
`tbl_offer_url` has `start_date` and `end_date` columns. The routing query does **not** filter on these dates — an offer past its `end_date` continues routing traffic as long as `offer_status = 1`. Date enforcement must be done manually by archiving/deleting the offer.

### No deduplication
The same `click_id` can be inserted multiple times. There is no `UNIQUE` constraint on `tbl_click.click_id`. Duplicate counting is possible if the caller retries the request.

### IP address is caller-supplied
The `ip` parameter comes from the query string, not from `$_SERVER['REMOTE_ADDR']`. A caller can pass any IP string. No validation is performed.

### No redirect in PHP
The routing layer returns the URL as JSON data — it does not perform a server-side HTTP redirect (`header("Location: ...")`). The redirect happens client-side in JavaScript. This means routing does not work for non-JS clients (bots, curl, etc.).
