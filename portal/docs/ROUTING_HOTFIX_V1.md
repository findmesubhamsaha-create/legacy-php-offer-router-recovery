# Routing Hotfix V1

**Branch:** routing-integrity-v1  
**Date:** 2026-05-20  
**Scope:** Five targeted fixes from ROUTING_INTEGRITY_AUDIT.md. No architecture changes. No SQL style changes. No weight algorithm changes. No prepared statements. Database.php untouched.

---

## Items Implemented

| ID | Severity | Description |
|---|---|---|
| C-01 | Critical | Decouple routing return from click-log INSERT success |
| H-05 | High | Null guard after `get_link_to_display()` |
| L-01 | Low | PHP 8 undefined index notices for `oid` / `click_id` |
| M-04 | Medium | `exit` after `header()` redirects |
| M-01 | Medium | `checkSlug()` LIKE → exact match |

**Not implemented:** H-01 (weight algorithm rewrite) — deferred per task instructions.

---

## Changed Files

### `index.php`

**L-01 — PHP 8 undefined index notices** (line 15)

Before:
```php
$getoffer = postBack($params = array('oid'=>$_REQUEST['oid'], 'ip'=>$_SERVER['REMOTE_ADDR'], 'click_id'=>$_REQUEST['click_id'] ? $_REQUEST['click_id'] : '0'));
```

After:
```php
// RI-HOTFIX-V1 L-01: ?? avoids PHP 8 Undefined index notices; fixes falsy '0' ternary
$getoffer = postBack($params = array('oid'=>$_REQUEST['oid'] ?? '', 'ip'=>$_SERVER['REMOTE_ADDR'], 'click_id'=>$_REQUEST['click_id'] ?? '0'));
```

Rationale: `$_REQUEST['oid']` and `$_REQUEST['click_id']` generate `Notice: Undefined array key` in PHP 8 when absent. The original ternary `$_REQUEST['click_id'] ? ... : '0'` also mishandled an explicit `click_id=0` (falsy string, defaulted to `'0'` anyway — coincidentally correct but semantically wrong). `??` is the correct idiom for both.

---

**M-04 — No exit after header()** (lines 20–31)

Before:
```php
if(isset($getoffer)){
    header("Location: ".$getoffer.'?'.$final_query);
}
else{
    header("Location: 404.php?".$final_query);
}
```

After:
```php
if(isset($getoffer)){
    header("Location: ".$getoffer.'?'.$final_query);
    // RI-HOTFIX-V1 M-04: exit prevents post-redirect code execution
    exit;
}
else{
    header("Location: 404.php?".$final_query);
    // RI-HOTFIX-V1 M-04: exit prevents post-redirect code execution
    exit;
}
```

Rationale: PHP continues executing script body after `header()`. The function declaration below is harmless today, but any future code inserted after the if/else would execute on every request including redirected ones. `exit` is the standard PHP idiom.

---

### `portal/library/Postback.php`

**H-05 — Null guard after get_link_to_display()** (lines 19–22)

Before:
```php
$site = $this->get_link_to_display($get_sub_offers);
// return $site;
// die();
$get_site_id = $this->db->join_query("... WHERE s.sub_url= '".$site."' ...");
```

After:
```php
$site = $this->get_link_to_display($get_sub_offers);
// RI-HOTFIX-V1 H-05: null guard — prevents second DB lookup on empty URL
if ($site === null) {
    return null;
}
// return $site;
// die();
$get_site_id = $this->db->join_query("... WHERE s.sub_url= '".$site."' ...");
```

Rationale: `get_link_to_display()` returns `null` when the `??` null-coalesce fires (empty weight array or all-null sub_url values). Without this guard, `$site = null` is cast to `''` in the second SQL string, the query returns empty, `$get_site_id[0]` generates a PHP 8 Notice, and the null `offer_id` / `sub_offer_id` cause the click INSERT to fail — which (before C-01) cascades into routing returning null → 404.

---

**C-01 — Decouple routing return from click-log success** (line 41–42)

Before:
```php
$add_click = $this->db->save_data('tbl_click',$click_record);

if($add_click){
    return $site;
}
```

After:
```php
$add_click = $this->db->save_data('tbl_click',$click_record);
// RI-HOTFIX-V1 C-01: return URL regardless of click-log success
return $site;
```

Rationale: `save_data()` returns `0` on any INSERT failure (table locked, DB down, constraint violation, full disk). The old guard made the destination URL hostage to the click-log write path. Any transient DB hiccup silently redirected 100% of traffic to 404. Routing availability must not depend on analytics writes. Under-counting clicks on failure is the correct trade-off.

---

### `portal/library/Offer.php`

**M-01 — checkSlug() LIKE → exact match** (line 25–26)

Before:
```php
$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,'slug_name like "%'.strtolower($param).'%"',1);
```

After:
```php
// RI-HOTFIX-V1 M-01: exact match aligns with routing's = lookup; LIKE caused false-positive blocks
$hasslug = $this->db->fetch_data(DB_OFFER_TABLE,['slug_name'=>strtolower($param)],1);
```

Rationale: The routing engine uses `WHERE m.slug_name = '<oid>'` (exact match). `checkSlug()` blocked creation of slug `"offer"` if any existing slug contained the substring `"offer"` (e.g. `"special-offer"`). This false-positive made slug uniqueness stricter than the routing semantics require. Exact match aligns the validation with the routing lookup.

---

## Verification Checklist

### C-01
- [ ] Lock or DROP `tbl_click`, then `GET /?oid=<valid-slug>` — confirm redirect reaches destination, not 404
- [ ] Restore `tbl_click`, confirm click rows are still written on success

### H-05
- [ ] Create offer with all active sub-URLs having `weight=0`; route through its slug — confirm clean null return (no PHP notices in error log, no 404 from click-log cascade)
- [ ] Confirm normal offers (weight > 0) are unaffected

### L-01
- [ ] `GET /` with no query string — check PHP error log for absence of `Notice: Undefined array key "oid"` and `"click_id"`
- [ ] `GET /?oid=valid-slug&click_id=0` — confirm `click_id` stored as `'0'`, not silently overridden
- [ ] `GET /?oid=valid-slug` (no click_id) — confirm `click_id` stored as `'0'`

### M-04
- [ ] `GET /?oid=valid-slug` — confirm single redirect, no double response, no PHP output after Location header
- [ ] `GET /` with no oid — confirm clean 404 redirect, no post-header output

### M-01
- [ ] Create offer with slug `"my-offer"`. Attempt to create new offer with slug `"offer"` — confirm it is now accepted (LIKE previously blocked it)
- [ ] Attempt to create duplicate slug `"my-offer"` — confirm it is still blocked (exact match still catches true duplicates)
- [ ] Route traffic through `/?oid=offer` — confirm correct offer is reached

---

## Rollback Risk

| Fix | Rollback Risk | Notes |
|---|---|---|
| C-01 | **None for routing.** Click under-count risk on INSERT failure — same as it was before the bug existed. | Revert: restore `if($add_click){ return $site; }` guard |
| H-05 | **None.** Adds an explicit early exit for an existing implicit undefined-behaviour path. | Revert: remove the `if ($site === null)` block |
| L-01 | **None.** `??` produces identical values to the old code for normal requests. The only behavioural change is `click_id=0` now correctly stores `'0'` instead of coincidentally `'0'` via the ternary. | Revert: restore `$_REQUEST['oid']` and `$_REQUEST['click_id'] ? ... : '0'` |
| M-04 | **None.** `exit` after `header()` is a no-op when no code follows — which is the current state. The `postBack` function declaration below is parsed by PHP at compile time regardless. | Revert: remove the two `exit;` lines |
| M-01 | **Low.** Makes validation less restrictive. Offers that were previously blocked by false-positive LIKE matches can now be created. Any code that depended on the LIKE over-blocking (none found) would behave differently. | Revert: restore `'slug_name like "%'.strtolower($param).'%"'` |

---

## Deferred Items

The following issues from the audit were explicitly excluded from this hotfix:

| ID | Reason deferred |
|---|---|
| H-01 | Weight algorithm rewrite — per task instructions, not in scope for V1 |
| C-02 | Requires passing `main_offer_id` from Query 1 through `get_link_to_display()` — higher-risk data-flow change |
| C-03 | Requires prepared statements — excluded per task rules |
| M-03 | Requires prepared statements or input sanitisation refactor — excluded per task rules |
| H-02 | Blocked by H-01 (zero-weight filter is cleanest as part of weight algorithm fix) |
| H-03 | Requires operator confirmation that `start_date`/`end_date` fields are correctly populated before enabling date filtering |
| H-04 | Server-side weight validation in `addNewOffer()` — medium effort, dependent on H-01 fix being agreed |
| M-02, L-02, L-03, L-04, M-05 | Operational hardening — suitable for a subsequent pass |
