# TEST PLAN — Legacy Offer Router Recovery
**Environment:** Local XAMPP (`http://localhost/legacy-php-offer-router-recovery/`)  
**Goal:** Verify recovered system behaves exactly like production  
**Scope:** Manual black-box verification. No automated harness.  
**DB:** `efbhalvbhdsurl` on `localhost` (root / no password for portal; admin / KDms@jY7Gw for export module)

---

## Prerequisites

- XAMPP running (Apache + MySQL)
- Database `efbhalvbhdsurl` imported from `portal/database/schema.sql` then `portal/database/seed.sql`
- At least one row in `tbl_user` with known username and **plaintext** password (system stores/compares plain text — no hashing)
- Browser dev tools open (Network tab) for all tests
- MySQL client open for DB assertion queries

---

## Test Case Index

| # | Area | Priority |
|---|------|----------|
| T01 | Login | Critical |
| T02 | Add Offer | Critical |
| T03 | Add Sub URLs | Critical |
| T04 | Weight Validation | Critical |
| T05 | Edit Offer | Critical |
| T06 | Archive | Important |
| T07 | Delete | Important |
| T08 | Reset (Restore to Active) | Important |
| T09 | Public Routing | Critical |
| T10 | Weighted Distribution Accuracy | Critical |
| T11 | Click Recording | Critical |
| T12 | Reports | Important |
| T13 | CSV Import | Important |
| T14 | CSV Export | Optional |

---

## T01 — Login

**Priority:** Critical

### Steps
1. Navigate to `http://localhost/legacy-php-offer-router-recovery/portal/index.php`
2. Leave both fields blank. Click **Sign In**.
3. Observe the alert.
4. Enter a username that does not exist in `tbl_user`. Enter any password. Click **Sign In**.
5. Observe the response.
6. Enter the correct username and password from `tbl_user`. Click **Sign In**.

### Expected Result
- Step 2: jQuery-confirm modal appears with validation messages "Please enter your user name" and "Please enter your password". No AJAX request is fired.
- Step 4: AJAX POST to `ajax.php` with `requestMethod=login`. Response JSON: `{"response":false,"message":"Please check once your provided information!."}`. Modal alert shown. Page stays on `index.php`.
- Step 6: Response JSON: `{"response":true,"message":"Successfully Login!."}`. Browser redirects to `dashboard.php`. Session variable `is_login` is set.

### DB Tables Affected
- `tbl_user` — read only (SELECT by `user_name` + `password`)

### Failure Symptoms
- Redirect to `dashboard.php` with wrong credentials → session guard not working
- Blank page after login → PHP session_start() issue or include path broken
- "Invalid JSON" in Network tab → PHP warning emitted before JSON output (check error_reporting in Settings.php)
- Loop back to `index.php` after correct login → session not persisting (check session.save_path on XAMPP)

---

## T02 — Add Offer

**Priority:** Critical

### Steps
1. Log in and navigate to `dashboard.php`.
2. Click the **Add Offer** button to open the modal.
3. Fill in:
   - Offer Name: `Test Offer Alpha`
   - Slug Name: `test-offer-alpha`
   - Tag: `qa-tag` (new tag, not in `tbl_tag`)
   - Network: `qa-network` (new network, not in `tbl_network`)
   - Note: `QA test offer`
   - Start Date / End Date: today
   - At least one Sub URL (see T03)
4. Submit the form.
5. Verify the offer appears in the DataTable.
6. Repeat step 2–4 using the **same Offer Name** (`Test Offer Alpha`). Attempt to submit.
7. Repeat again using a **different Offer Name** but the **same Slug** (`test-offer-alpha`). Attempt to submit.

### Expected Result
- Step 4: AJAX POST `requestMethod=addNewOffer`. Response: `{"response":true,"message":<new_id>}`. Modal closes. DataTable refreshes and shows the new offer.
- Step 5: New row visible in dashboard with correct name, slug, tag, network.
- Step 6: Response: `{"response":false,"message":"Offer Name is in Use, Please enter another offer name!"}`. Nothing inserted.
- Step 7: Response: `{"response":false,"message":"Slug already is in Use! Please use another slag name"}`. Nothing inserted.

### DB Tables Affected
- `tbl_offer_url` — INSERT (new offer row)
- `tbl_tag` — INSERT if tag is new; SELECT if tag exists
- `tbl_network` — INSERT if network is new; SELECT if network exists
- `tbl_sub_offer_url` — INSERT (one row per sub URL)

### Verification Query
```sql
SELECT o.id, o.offer, o.slug_name, o.offer_status,
       t.tag_name, n.network_name
FROM tbl_offer_url o
JOIN tbl_tag t ON o.tag_id = t.id
JOIN tbl_network n ON o.network_id = n.id
WHERE o.offer = 'Test Offer Alpha';
```

### Failure Symptoms
- Empty response / blank response → `print_r` or `die()` debug code still active in `ajax.php`
- Duplicate offer accepted → uniqueness check in `Offer::addNewOffer()` bypassed
- Tag/network not auto-created → `tbl_tag` or `tbl_network` INSERT failing (check DB permissions)
- DataTable does not refresh → JS reload not triggered after successful response

---

## T03 — Add Sub URLs

**Priority:** Critical

### Steps
1. Open the Add Offer modal.
2. Add three sub URLs:
   - `https://sub1.example.com` / weight `50` / status **Active** (checkbox checked)
   - `https://sub2.example.com` / weight `50` / status **Active** (checkbox checked)
   - `https://sub3.example.com` / weight `0` / status **Inactive** (checkbox unchecked)
3. Submit with a valid offer name and slug.
4. Open **Settings** for the saved offer and verify all three sub URLs are listed.

### Expected Result
- Three rows in `tbl_sub_offer_url` for the new `main_offer_id`.
- `sub3` has `status='no'`, `weight=0`.
- `sub1` and `sub2` have `status='yes'`, `weight=50`.

### DB Tables Affected
- `tbl_sub_offer_url` — INSERT (one row per sub URL)

### Verification Query
```sql
SELECT sub_url, weight, status, deleted_status
FROM tbl_sub_offer_url
WHERE main_offer_id = <new_offer_id>
ORDER BY id ASC;
```

### Failure Symptoms
- Fewer rows than expected → `count($param['url'])` loop not iterating correctly
- All statuses `'yes'` regardless of checkbox → checkbox-to-status mapping broken in `ajax.php::addNewOffer()`
- `deleted_status` not `'no'` on new rows → DB DEFAULT not set correctly

---

## T04 — Weight Validation

**Priority:** Critical

### Steps
1. Open the Add Offer modal.
2. Add two sub URLs both with status **Active**:
   - `https://sub1.example.com` / weight `60`
   - `https://sub2.example.com` / weight `30`
   (total = 90, not 100)
3. Attempt to submit.
4. Correct to weights `50` / `50` and submit.
5. Try one active URL at weight `100` and one inactive at weight `0`. Submit.

### Expected Result
- Step 3: Submission blocked or error returned. No rows inserted into DB. (Note: weight validation currently lives in `Upload::uploadOffer()` for CSV import. For the manual form, check whether the dashboard JS validates weight before submitting — if no client-side validation exists, this is a **known gap** to document.)
- Step 4: Offer saved successfully. Active sub URLs sum to exactly 100.
- Step 5: Offer saved. Routing will only use the one active URL.

### DB Tables Affected
- `tbl_sub_offer_url` — INSERT only on successful validation

### Failure Symptoms
- Offer accepted with weights summing to ≠ 100 via the manual form → client-side weight validation absent (document as gap; CSV import does enforce this)
- Routing sends all traffic to one URL despite equal weights → float precision issue in `get_link_to_display()`

---

## T05 — Edit Offer

**Priority:** Critical

### Steps
1. Find an existing active offer in the DataTable.
2. Click its **Settings** (gear) icon. Verify the modal populates with existing data: offer name, slug, tag, note, network, start/end date, all sub URLs with their weights and statuses.
3. Change the offer name to `Test Offer Alpha Updated`.
4. Change one sub URL's weight from `50` to `40` and another from `50` to `60`.
5. Add a new sub URL: `https://sub4.example.com` / weight `0` / status **Inactive**.
6. Submit.
7. Verify the DataTable updates.

### Expected Result
- Step 2: `editOffer` AJAX call returns `{"response":true,"message":[...]}` with correct pre-populated values.
- Step 6: `addNewOffer` AJAX call (same endpoint, with `offerId` set) returns success. `tbl_offer_url` row updated. Existing sub URL weights updated in `tbl_sub_offer_url`. New sub URL inserted.
- Step 7: DataTable shows updated name.

### DB Tables Affected
- `tbl_offer_url` — UPDATE (offer, slug_name, tag_id, note, network_id, start_date, end_date)
- `tbl_sub_offer_url` — UPDATE (weight, status for existing URLs); INSERT (for new URLs); UPDATE `deleted_status='yes'` for removed URLs

### Verification Query
```sql
SELECT sub_url, weight, status, deleted_status
FROM tbl_sub_offer_url
WHERE main_offer_id = <offer_id>
ORDER BY id ASC;
```

### Failure Symptoms
- Modal opens empty → `editOffer` AJAX failing; check Network tab for response
- Removed sub URLs still routing traffic → `deleted_status` not set to `'yes'`; `Postback::rotateUrl()` filters by `s.status = 'yes'` but NOT by `deleted_status` — verify this filter is in the JOIN query
- Duplicate offer/slug rejection when editing same record → `offerId` not passed correctly, causing self-conflict in uniqueness check

---

## T06 — Archive

**Priority:** Important

### Steps
1. Find an active offer (status = 1) in the DataTable.
2. Open its three-dot dropdown. Click **Archive**.
3. Confirm any prompt if shown.
4. Verify the offer disappears from the default "Active" DataTable view.
5. Switch filter to **Status → Archived** and verify the offer appears.

### Expected Result
- AJAX POST `requestMethod=archiveOffer` with `oid=<id>`. Response should be `{"response":true,"message":"Offer Archived !"}`.
- `tbl_offer_url` row: `offer_status=2`, `status_updated_at=<today>`.
- Offer absent from active list; present in archived filter.

**Known Issue:** `ajax.php::archiveOffer()` line 282 contains `print_r($archive_status); die();` which causes the response to be a raw PHP string, not JSON. The DataTable AJAX call will receive non-JSON and may silently fail or show an error. This is a **blocker** — confirm whether this line is present in the recovered version and document accordingly.

### DB Tables Affected
- `tbl_offer_url` — UPDATE (`offer_status=2`, `status_updated_at`)

### Verification Query
```sql
SELECT id, offer, offer_status, status_updated_at
FROM tbl_offer_url WHERE id = <offer_id>;
-- Expected: offer_status=2
```

### Failure Symptoms
- No visible change after clicking Archive → `print_r/die` debug code blocking JSON response (see Known Issue above)
- Offer still appears in active list → DataTable not refreshing, or status update failed
- `status_updated_at` is NULL → UPDATE array not including the field

---

## T07 — Delete

**Priority:** Important

### Steps
1. Find an active offer in the DataTable.
2. Open the three-dot dropdown. Click **Delete**.
3. Confirm any prompt.
4. Verify the offer disappears from the active DataTable view.
5. Switch filter to **Status → Deleted** and verify the offer appears.

### Expected Result
- AJAX POST `requestMethod=deleteOffer` with `oid=<id>`. Response: `{"response":true,"message":"Offer Deleted"}`.
- `tbl_offer_url`: `offer_status=3`, `status_updated_at=<today>`.

**Known Issue:** Same `print_r($delete_status); die();` debug line in `ajax.php::deleteOffer()` line 297 — same blocker as T06.

### DB Tables Affected
- `tbl_offer_url` — UPDATE (`offer_status=3`, `status_updated_at`)

### Verification Query
```sql
SELECT id, offer, offer_status, status_updated_at
FROM tbl_offer_url WHERE id = <offer_id>;
-- Expected: offer_status=3
```

### Failure Symptoms
- Identical to T06 — `print_r/die` will break the JSON response
- Sub URL records in `tbl_sub_offer_url` still have `deleted_status='no'` — this is expected; delete sets parent status only

---

## T08 — Reset (Restore to Active)

**Priority:** Important

### Steps
1. Switch the dashboard filter to **Status → Archived** or **Status → Deleted**.
2. Find an archived or deleted offer. Click the **undo** (reset) icon.
3. Verify the offer returns to the active list.
4. Attempt to reset a second offer that has the **same offer name or slug** as an already-active offer.

### Expected Result
- Step 2–3: AJAX POST `requestMethod=resetOffer` with `oid=<id>`. Response: `{"response":true,"message":"Offer moved to active!"}`. `offer_status` becomes `1`.
- Step 4: Response: `{"response":false,"message":"There is an active offer with the same name already exists!"}` or slug variant. No DB change.

### DB Tables Affected
- `tbl_offer_url` — SELECT (conflict check), UPDATE (`offer_status=1`, `status_updated_at`)

### Verification Query
```sql
SELECT id, offer, offer_status FROM tbl_offer_url WHERE id = <offer_id>;
-- Expected: offer_status=1
```

### Failure Symptoms
- Reset rejected even when no conflict → conflict-check query comparing wrong field
- Offer restored despite name/slug collision → `resetOffer()` conflict checks not executing

---

## T09 — Public Routing

**Priority:** Critical

### Steps
1. Ensure an offer exists with `slug_name='test-alpha'`, `offer_status=1`, and at least one sub URL with `status='yes'` and `deleted_status='no'`.
2. In a browser (or curl), send:
   `http://localhost/legacy-php-offer-router-recovery/index.php?oid=test-alpha&click_id=click001&affid=netA`
3. Observe the redirect destination.
4. Check that the destination is one of the active sub URLs for `test-alpha`.
5. Send a request with a slug that does not exist:
   `http://localhost/legacy-php-offer-router-recovery/index.php?oid=nonexistent-slug&click_id=click002&affid=netA`

### Expected Result
- Step 3–4: HTTP 302 redirect to one of the active sub URLs. Query parameters from the original request (excluding `oid`) are appended: e.g., `https://sub1.example.com?click_id=click001&affid=netA`.
- Step 5: HTTP 302 redirect to `404.php?click_id=click002&affid=netA`.

### DB Tables Affected
- `tbl_offer_url` — SELECT (by `slug_name`, `offer_status=1`)
- `tbl_sub_offer_url` — SELECT (active sub URLs + weights)
- `tbl_click` — INSERT (click recorded)

### Failure Symptoms
- Blank page / no redirect → `Postback::rotateUrl()` returning null; check that sub URLs exist with `status='yes'`
- Redirects to wrong domain → `oid` slug mismatch or wrong offer resolved
- Query string not forwarded → `index.php` `$final_query` construction broken (check `unset($params['oid'])` and `http_build_query`)
- 404 redirect includes `oid` in query string → `unset($params['oid'])` not executing

---

## T10 — Weighted Distribution Accuracy

**Priority:** Critical

### Steps
1. Create an offer `weight-test` with three active sub URLs:
   - `https://sub-a.example.com` weight `70`
   - `https://sub-b.example.com` weight `20`
   - `https://sub-c.example.com` weight `10`
2. Send 200 requests to `index.php?oid=weight-test&click_id=X` (use a browser loop, curl, or a simple script).
3. Query `tbl_click` and count hits per `sub_offer_id`.
4. Verify approximate distribution matches weights.

### Expected Result
- Roughly 70% of clicks routed to sub-a, 20% to sub-b, 10% to sub-c. At n=200, expect ±8% variance due to randomness.
- The `get_link_to_display()` method uses `rand(0, 99)` minus cumulative weight — this is a correct weighted random selection as long as weights sum to exactly 100.

### DB Tables Affected
- `tbl_click` — INSERT (200 rows)
- `tbl_sub_offer_url` — SELECT only

### Verification Query
```sql
SELECT s.sub_url, COUNT(c.id) AS hits,
       ROUND(COUNT(c.id) / 200 * 100, 1) AS pct
FROM tbl_click c
JOIN tbl_sub_offer_url s ON c.sub_offer_id = s.id
JOIN tbl_offer_url o ON c.offer_id = o.id
WHERE o.slug_name = 'weight-test'
GROUP BY s.sub_url
ORDER BY hits DESC;
```

### Failure Symptoms
- All traffic to one URL → weights not summing to 100; `rand(0,99)` never subtracts enough for second or third entry
- Distribution completely uniform → weight values not being read; check `$weight['weight']` key in `get_link_to_display()`
- Function returns last URL always → `rand` result always > cumulative weight until the last item (weights don't sum to 100)

---

## T11 — Click Recording

**Priority:** Critical

### Steps
1. Use the offer and request from T09.
2. After a successful redirect, query `tbl_click`.
3. Verify the recorded fields.
4. Send a second request with the same `click_id`. Verify it creates a second row (system does NOT deduplicate click_id).
5. Send a request for an inactive sub URL offer (all sub URLs set to `status='no'`). Verify no click is recorded.

### Expected Result
- Step 2–3: One row in `tbl_click` with:
  - `click_id` = value from query string (or `'0'` if omitted)
  - `offer_id` = correct `tbl_offer_url.id`
  - `sub_offer_id` = correct `tbl_sub_offer_url.id` of the served URL
  - `ip_address` = your machine's local IP
  - `created_at` = today's date (`Y-m-d`)
- Step 4: Two rows with identical `click_id` — no uniqueness constraint exists.
- Step 5: No row inserted; redirect goes to `404.php`.

### DB Tables Affected
- `tbl_click` — INSERT

### Verification Query
```sql
SELECT c.id, c.click_id, c.offer_id, c.sub_offer_id,
       c.ip_address, c.created_at, s.sub_url
FROM tbl_click c
JOIN tbl_sub_offer_url s ON c.sub_offer_id = s.id
ORDER BY c.id DESC LIMIT 5;
```

### Failure Symptoms
- Click recorded but `sub_offer_id` is NULL → `get_site_id` query returned no rows; sub URL lookup failing
- `ip_address` is `::1` → expected on localhost IPv6 loopback; not a bug
- No row inserted despite successful redirect → `$add_click` INSERT failing silently; check DB write permissions

---

## T12 — Reports

**Priority:** Important

### Steps
1. Ensure the cron job (`portal/library/cron.php`) has been run at least once, or manually insert a row into `tbl_report` for a known `main_offer_id`.
2. On the dashboard DataTable, click the **View Report** (eye) icon for that offer.
3. Verify the report modal/table opens and shows data.
4. Click View Report for an offer that has no rows in `tbl_report`.

### Expected Result
- Step 3: AJAX POST `requestMethod=fetchReport` with `oid=<offer_id>`. Response: `{"data":[[offer_id, sub_url, click_count, date], ...]}`. DataTable inside modal renders the rows.
- Step 4: Response: `{"data":[]}`. Modal shows empty table — no error.

### DB Tables Affected
- `tbl_report` — SELECT only
- `tbl_click` — not read by Report class directly (reports come from pre-aggregated `tbl_report`)

### Verification Query
```sql
SELECT main_offer_id, main_offer_url, offer_clicks, report_date
FROM tbl_report
WHERE main_offer_id = <offer_id>
ORDER BY report_date DESC;
```

### Failure Symptoms
- Empty modal for offer with known click data → cron has not run; `tbl_report` is not populated in real-time from clicks
- Report shows wrong sub URLs → `main_offer_url` in `tbl_report` stores the sub URL at time of aggregation; it may be stale if the sub URL was later edited
- DataTable "Invalid JSON" in modal → `fetchReport` returning non-JSON (check for PHP warnings)

---

## T13 — CSV Import

**Priority:** Important

### Setup
Prepare a CSV file with the following exact column headers (case-insensitive match used by `Upload::uploadOffer()`):

```
offer name, slug name, tag name, notes, network, start date, end date, url1, weight1, status1, url2, weight2, status2
```

### Steps
1. Navigate to `portal/import.php`.
2. Upload a CSV where active sub URL weights sum to exactly 100:

   | offer name | slug name | tag name | notes | network | start date | end date | url1 | weight1 | status1 | url2 | weight2 | status2 |
   |---|---|---|---|---|---|---|---|---|---|---|---|---|
   | Import Test | import-test | qa | test | netB | 2025-01-01 | 2025-12-31 | https://a.example.com | 60 | yes | https://b.example.com | 40 | yes |

3. Click import. Observe the response.
4. Verify the offer appears in the dashboard.
5. Now upload a row where weights sum to 90 (not 100). Observe the error response.
6. Upload a row with a duplicate offer name (already active). Observe the error.
7. Upload a row with a malformed URL (e.g., `not-a-url`). Observe the error.

### Expected Result
- Step 3: Response JSON `{"status":"success","message":"All Success.","inserted_rows":1}`.
- Step 4: Offer `Import Test` with slug `import-test` in `tbl_offer_url`, two sub URLs in `tbl_sub_offer_url`.
- Step 5: Response `{"status":"partial_success",...,"error_rows":[{"error":"Total weight for 'yes' status must equal 100. Current total: 90"}]}`. Nothing inserted.
- Step 6: Response with error row: `"Offer/Slug Name is in Use, Please enter another offer/slug name!"`.
- Step 7: Response with error row: `"Invalid URL: not-a-url"`.

### DB Tables Affected
- `tbl_offer_url` — INSERT
- `tbl_sub_offer_url` — INSERT
- `tbl_tag` — INSERT if new
- `tbl_network` — INSERT if new

### Failure Symptoms
- Empty `response` from import → check that the CSV is parsed by the browser JS into a JSON string before POSTing (the import page converts CSV client-side)
- `Missing required columns` error on valid CSV → column header casing mismatch; `stripos` is used but check for BOM or trailing spaces in the CSV header row
- Partial insert (some rows in DB, some not) on weight error → per-offer grouping logic; an offer's rows are inserted only after all its rows pass validation
- Offers inserted even with `partial_success` → check `$offer_correct_rows` scope is not leaking across offers

---

## T14 — CSV Export

**Priority:** Optional

### Steps
1. Navigate to `portal/export/index.php`.
   - Note: this page uses **hardcoded credentials** (`admin` / `KDms@jY7Gw`) separate from the main database connection. Ensure the local DB has this user, or the export will fail with "Connection failed".
2. Verify the page lists download batch links based on total record count (batches of 100 rows).
3. Click **Download Batch 1**.
4. Verify a CSV file downloads.
5. Open the CSV and verify column headers:
   `Serial Number, Offer Name, Slug Name, Notes, Tag Name, Network Name, URLs, Weight, Is Active`
6. Verify each row corresponds to an active offer (`offer_status=1`) with `deleted_status='no'` on the sub URL.

### Expected Result
- Step 2: Number of batch links = `ceil(total_sub_url_rows / 100)` where rows come from the JOIN query (one row per sub URL per offer).
- Step 3–4: Browser downloads `exported_data_batch_1.csv`.
- Step 5–6: Headers match exactly. Data matches DB.

**Known Issue:** `export/download.php` uses `$stmt->bind_param('ii', $limit, $offset)` but the SQL query has no `?` placeholders (values are interpolated directly into the string). This means `bind_param` will fail with a "Number of variables doesn't match number of parameters" error. Export is currently broken. Document as a known blocker.

### DB Tables Affected
- `tbl_offer_url` — SELECT (status=1)
- `tbl_sub_offer_url` — SELECT (deleted_status='no')
- `tbl_tag` — JOIN
- `tbl_network` — JOIN

### Failure Symptoms
- "Connection failed" → DB user `admin` does not exist locally; create it or update credentials in `export/index.php` and `export/download.php`
- "Number of variables doesn't match" fatal error → `bind_param` bug (see Known Issue above)
- Empty CSV (headers only) → no active offers with sub URLs, or JOIN finds no matches

---

## Known Blockers (Do Not Mark as Passing Until Resolved)

| ID | File | Line | Issue | Affects |
|----|------|------|-------|---------|
| B01 | `portal/ajax.php` | 282 | `print_r($archive_status); die();` — breaks JSON response | T06 Archive |
| B02 | `portal/ajax.php` | 297 | `print_r($delete_status); die();` — breaks JSON response | T07 Delete |
| B03 | `portal/export/download.php` | 81–83 | `bind_param('ii',...)` with no `?` placeholders — fatal error | T14 Export |
| B04 | `portal/export/index.php` | 28 | Back link hardcoded to production domain `efbhalvbhdsurl.com` | T14 Export nav |
| B05 | `portal/library/User.php` | 12 | Password stored and compared in plaintext | T01 Login |

---

## DB State Cleanup Between Runs

To reset to a clean state between test cycles:

```sql
-- Remove test data only; leave seed users intact
DELETE FROM tbl_click WHERE offer_id IN (
    SELECT id FROM tbl_offer_url WHERE offer LIKE 'Test Offer%' OR offer LIKE 'Import Test%' OR slug_name LIKE 'weight-test%'
);
DELETE FROM tbl_sub_offer_url WHERE main_offer_id IN (
    SELECT id FROM tbl_offer_url WHERE offer LIKE 'Test Offer%' OR offer LIKE 'Import Test%' OR slug_name LIKE 'weight-test%'
);
DELETE FROM tbl_report WHERE main_offer_id IN (
    SELECT id FROM tbl_offer_url WHERE offer LIKE 'Test Offer%' OR offer LIKE 'Import Test%' OR slug_name LIKE 'weight-test%'
);
DELETE FROM tbl_offer_url WHERE offer LIKE 'Test Offer%' OR offer LIKE 'Import Test%' OR slug_name LIKE 'weight-test%';
```
