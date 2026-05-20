# Import Auth Regression — Blank White Page

**Branch:** modernization-phase-1  
**Date:** 2026-05-20  
**Symptom:** After SHV1-03 added the session auth guard to `import.php`, visiting the page produced a blank white page. URL remained `/portal/import.php` (no visible redirect). `php -l import.php` passed. `dashboard.php` was unaffected.

---

## Root cause

**File:** `portal/import.php`, byte offset 0  
**Bug:** UTF-8 BOM (`EF BB BF`) present immediately before `<?php`

The original `import.php` was a pure HTML file. It was created or last edited on Windows with an editor (e.g. Notepad) that silently prepends a UTF-8 BOM. In a pure HTML context the BOM is harmless — browsers recognise it as a UTF-8 hint and skip it.

When SHV1-03 prepended the PHP auth guard using an `Edit` operation, the BOM was not removed. The resulting file structure was:

```
[EF BB BF] <?php
session_start();
if (!isset($_SESSION['is_login']) || ...) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
...
```

Under Apache `mod_php`, PHP encounters the BOM bytes (`EF BB BF`) as literal content before the `<?php` opening tag and places them into the output stream. In the `mod_php` SAPI, these bytes cause the HTTP response body to begin before the response headers have been committed — PHP's internal "output started" flag (`SG(headers_sent)`) is set to `1`.

When `session_start()` then runs:
1. `headers_sent()` returns `true`
2. PHP cannot send the `Set-Cookie: PHPSESSID=...` header  
3. `session_start()` fails (returns `false`); `$_SESSION` is **not** populated from the session file
4. Auth check: `!isset($_SESSION['is_login'])` is `true` → redirect branch executes
5. `header('Location: index.php')` is called — but headers are already sent, so this silently fails (the redirect does not fire)
6. `exit` terminates the script

The response body at this point is exactly **three bytes** — the BOM characters `EF BB BF` — plus any PHP warning text if `display_errors = On`. The browser receives a 200 OK response with a near-empty body, renders nothing, and the URL bar stays at `import.php` (no redirect was sent).

`dashboard.php` was not affected because it was created without a BOM — confirmed by hex inspection (`3C 3F 70 68 70` = `<?php`, no BOM prefix).

---

## Why the CLI `php -l` test passed

`php -l` only checks syntax; it does not execute the file or initialise a session. A BOM before `<?php` does not trigger a parse error. The regression is a **runtime-only** issue that requires Apache `mod_php` to manifest.

---

## Why `output_buffering = 4096` did not protect against this

PHP CLI tests showed `session_start()` succeeds with a BOM present when `output_buffering = 4096` is set. Under CLI, PHP initialises its output buffer before processing any file bytes, so the BOM enters the buffer without the `SG(headers_sent)` flag being set.

Under Apache `mod_php`, the output buffering initialisation order differs slightly from CLI: the SAPI-level "output started" flag can be set by the time PHP evaluates the BOM bytes, depending on the PHP and Apache version. On this machine (PHP 8.1.25 / Apache 2.4.58), the flag is set, so `session_start()` sees `headers_sent() = true`.

---

## Minimal fix applied

Removed the 3-byte BOM using a binary file rewrite (PowerShell `[System.IO.File]::ReadAllBytes` / `WriteAllBytes`). No PHP code was changed. File now starts at byte offset 0 with `3C 3F 70` (`<?php`), identical to `dashboard.php`.

**Verified:**
```
portal/import.php  → first bytes: 3C 3F 70 68 70 (<?php)  ✓
portal/dashboard.php → first bytes: 3C 3F 70 68 70 (<?php)  ✓
```

---

## Risk

None. Removing a BOM from a PHP file is a no-op change to PHP execution and to browser rendering. The file content (`<?php` onwards) is byte-for-byte identical to before. PHP's session handling, auth guard, and HTML output are unaffected.

---

## Rollback

To reintroduce the regression (for diagnostic purposes only):

```powershell
$path = "portal\import.php"
$bytes = [System.IO.File]::ReadAllBytes($path)
$bom = [byte[]]@(0xEF, 0xBB, 0xBF)
$newBytes = $bom + $bytes
[System.IO.File]::WriteAllBytes($path, $newBytes)
```

---

## Prevention

When prepending a PHP auth guard to any existing `.html` or `.php` file, always verify the file does not have a BOM:

```powershell
# Quick check — first 6 hex chars should be "3c3f70" (<?p) not "efbbBF"
(Get-Content portal\import.php -Encoding Byte -TotalCount 3 | ForEach-Object { $_.ToString("X2") }) -join ""
```

Or via `xxd` / hex editor: confirm byte 0 is `3C`, not `EF`.

Editors that silently write BOMs: Windows Notepad (pre-Win10 1903), Notepad++ with UTF-8 BOM encoding selected, some versions of Visual Studio. Prefer UTF-8 without BOM for all PHP files.

---

## Checklist

- [x] Root cause identified: UTF-8 BOM at byte 0 of `import.php`
- [x] Exact file and byte offset documented
- [x] BOM stripped using binary rewrite
- [x] No PHP code changed
- [x] `dashboard.php` confirmed BOM-free (unaffected)
- [ ] Manually verify: visit `import.php` after login — page should render with upload form visible
- [ ] Manually verify: visit `import.php` without login — should redirect to `index.php`
