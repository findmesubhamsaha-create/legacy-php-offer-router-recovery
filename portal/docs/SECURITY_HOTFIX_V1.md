# Security Hotfix V1

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Scope:** Session fixation guard, AJAX auth enforcement, page-level auth guards, password storage audit. No SQL, routing, auth architecture, or business logic was changed.

---

## SHV1-01 — Session fixation prevention

| Field | Detail |
|---|---|
| **Issue** | After a successful login, `ajax.php` set `$_SESSION["is_login"] = true` while keeping the same PHP session ID that was assigned to the anonymous (pre-login) visitor. An attacker who can observe or set the victim's session cookie before login can reuse that same ID post-login and gain an authenticated session without supplying credentials (session fixation). |
| **File changed** | `portal/ajax.php` — `userLogin()` function |
| **Fix** | Added `session_regenerate_id(true)` immediately before `$_SESSION["is_login"] = true`. The `true` argument deletes the old session file so the pre-login ID is invalidated. |
| **Risk** | None. `session_regenerate_id` is a standard PHP countermeasure. The new ID is set in the response `Set-Cookie` header automatically; no client-side changes are required. |
| **Rollback** | Remove the `session_regenerate_id(true)` line from `userLogin()`. |

---

## SHV1-02 — AJAX endpoint authentication enforcement

| Field | Detail |
|---|---|
| **Issue** | `ajax.php` handled every `requestMethod` — including `fetchAll`, `addNewOffer`, `editOffer`, `deleteOffer`, `importOffer`, and all others — without checking whether the caller had an authenticated session. Any unauthenticated HTTP client that could reach the server could read all offer data, mutate records, or import arbitrary CSV data. |
| **File changed** | `portal/ajax.php` — immediately after `$requestMethod` is assigned |
| **Fix** | Added an auth guard that allows only `login` and `resetPassword` through without a session check. All other methods require `$_SESSION['is_login'] === true`; missing or false session returns `{"response":false,"message":"Unauthorized"}` and exits. `session_start()` was already present (added by RC-09). |
| **Risk** | None for authenticated users. `login` and `resetPassword` remain public as they are the pre-auth entry points. |
| **Rollback** | Remove the eight-line `$public_methods` / `if (!in_array(...))` block. |

---

## SHV1-03 — Page-level authentication guards

### dashboard.php

| Field | Detail |
|---|---|
| **Issue** | The existing guard (`if(!isset($_SESSION["is_login"])){ header("Location: index.php"); }`) was missing `exit` after the redirect. PHP continues executing the remainder of the file after `header()`. An unauthenticated request that follows the redirect passively still receives the full dashboard HTML, including all embedded offer data and JavaScript. |
| **File changed** | `portal/dashboard.php` |
| **Fix** | Added `exit` after `header(...)`. Also tightened the condition to `!isset(...) || $_SESSION['is_login'] !== true` to guard against the session variable being present but set to a non-true value. |
| **Rollback** | Remove `exit` and revert condition to `!isset($_SESSION["is_login"])`. |

### import.php

| Field | Detail |
|---|---|
| **Issue** | `import.php` had no `session_start()` call and no auth check. The import UI — and the CSV upload mechanism it contains — was fully accessible without login. |
| **File changed** | `portal/import.php` |
| **Fix** | Prepended a PHP block before the `<!DOCTYPE html>` declaration: `session_start()` + the same `is_login` guard with `header('Location: index.php')` and `exit`. |
| **Rollback** | Remove the seven-line `<?php ... ?>` block at the top of the file. |

### export/index.php

| Field | Detail |
|---|---|
| **Issue** | `export/index.php` had no `session_start()` and no auth check. The export UI was publicly accessible. |
| **File changed** | `portal/export/index.php` |
| **Fix** | Same prepended PHP auth block; redirect target is `../index.php` to account for the subdirectory. |
| **Rollback** | Remove the seven-line `<?php ... ?>` block at the top of the file. |

### export/download.php

| Field | Detail |
|---|---|
| **Issue** | `export/download.php` opens a direct MySQLi connection and streams offer data as a CSV download. It had no auth check — any unauthenticated client with network access could download the full offer database as a CSV file. |
| **File changed** | `portal/export/download.php` |
| **Fix** | Added `session_start()` + auth guard at the very top of the file, before `require Settings.php`. Redirect target is `../index.php`. |
| **Rollback** | Remove the seven-line auth block (lines 2–8) from `export/download.php`. |

---

## Password Storage Audit (no code changes)

### Current mechanism

`User::login()` in `portal/library/User.php` authenticates by querying the `tbl_user` table with both `user_name` and `password` as equality conditions:

```php
$check_user = $this->db->fetch_data(
    DB_USER_TABLE,
    ['user_name' => $params['username'], 'password' => $params['password']],
    1
);
```

`Database::fetch_data()` builds `WHERE user_name = '$username' AND password = '$password'`. The submitted password is compared directly against the stored value in the database — no hashing, no `password_verify()`, no constant-time comparison. This means **passwords are stored as plaintext** in `tbl_user`.

There is no evidence of any hash prefix (e.g. `$2y$`, `$argon2`) in the codebase. The commented-out alternate `login()` implementation also fetches by `user_name` alone with no hash step, confirming this is not an oversight in the active code path.

### Risk

| Risk | Severity |
|---|---|
| Database read (SQL injection, direct access, backup leak) exposes all user passwords in cleartext | Critical |
| Password reuse across other services is immediately compromised | Critical |
| No timing-safe comparison — timing oracle is possible in theory | Low (single-user system) |
| `resetPassword` stores the new password using the same plaintext mechanism | High |

### Migration approach (when ready)

This is a documentation-only entry. No code changes are made by this hotfix.

**Recommended migration — three-phase:**

**Phase 1 — hash on next login (transparent, zero downtime)**  
In `User::login()`, after a successful plaintext match, immediately re-store the password using `password_hash($raw, PASSWORD_BCRYPT)` and update the row. On subsequent logins, detect a hashed value (starts with `$2y$`) and use `password_verify()` instead of the equality query. Users who have not logged in since migration retain plaintext passwords; add a forced-reset notice in the UI.

**Phase 2 — force reset for remaining plaintext accounts**  
After a transition window, query for any `tbl_user` rows whose `password` column does not start with `$2y$` (bcrypt) or `$argon2` and force a password reset on their next login.

**Phase 3 — remove plaintext path**  
Once all accounts are hashed, remove the `WHERE password = ...` branch from `fetch_data` and the plaintext fallback from `login()`. At this point the login query becomes `WHERE user_name = ?` with a follow-up `password_verify()` call.

**Key constraints for implementation:**  
- `Database::fetch_data()` currently builds the `WHERE` clause from an associative array — the `password` field must be removed from that array for Phase 1 (fetch by username only, then verify in PHP).  
- Do not reuse `fetch_data()` for the post-migration login path; the plaintext comparison SQL must be removed, not left as a fallback.  
- Prepared statements should be introduced alongside this migration (separate task — not in scope here).

---

## Files changed

| File | Change |
|---|---|
| `portal/ajax.php` | SHV1-01: `session_regenerate_id(true)` in `userLogin()`; SHV1-02: `$public_methods` auth guard after `$requestMethod` assignment |
| `portal/dashboard.php` | SHV1-03: added `exit` after redirect; tightened session condition |
| `portal/import.php` | SHV1-03: prepended `session_start()` + auth guard |
| `portal/export/index.php` | SHV1-03: prepended `session_start()` + auth guard |
| `portal/export/download.php` | SHV1-03: added `session_start()` + auth guard before `require Settings.php` |

---

## Verification steps

1. **Session fixation**: Open DevTools → Application → Cookies. Note the `PHPSESSID` value before login. After a successful login, confirm the cookie value has changed.
2. **AJAX auth guard**: While logged out, open DevTools console and run:
   ```javascript
   fetch('ajax.php', {method:'POST', body: new URLSearchParams({requestMethod:'fetchAll', draw:1, start:0, length:10, 'search[value]':''})}).then(r=>r.json()).then(console.log)
   ```
   Expect: `{"response":false,"message":"Unauthorized"}`.
3. **AJAX login still works**: Submit the login form. Expect successful redirect to `dashboard.php`.
4. **Dashboard redirect**: Clear cookies, navigate directly to `dashboard.php`. Expect redirect to `index.php` with no dashboard HTML in the response body (verify in DevTools Network tab that the response body is empty / `0 bytes` before the redirect).
5. **Import redirect**: Clear cookies, navigate to `import.php`. Expect redirect to `index.php`.
6. **Export redirect**: Clear cookies, navigate to `export/index.php` and `export/download.php`. Expect redirect to `../index.php` for both.
7. **Normal authenticated flow**: Log in → dashboard loads → import works → export download works.

---

## Rollback

To revert the entire hotfix:

```
git diff HEAD -- portal/ajax.php portal/dashboard.php portal/import.php portal/export/index.php portal/export/download.php
git checkout HEAD -- portal/ajax.php portal/dashboard.php portal/import.php portal/export/index.php portal/export/download.php
```
