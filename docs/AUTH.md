# Authentication & Session Management

## Overview

Authentication is minimal: a single admin login protects the dashboard. There is no role system, no multi-user differentiation, and no public registration. All protected pages rely on a single PHP session flag.

---

## Login Flow

### 1. Login page

**File:** `index.php`  
Renders a standalone HTML form. No PHP session check here — it is publicly accessible.

Form submits via AJAX (POST) to `ajax.php` with:
```
requestMethod = login
username      = <input value>
password      = <input value>
```

### 2. AJAX dispatcher

**File:** `ajax.php`  
Receives the POST, instantiates `User`, calls `userLogin()`.

```php
function userLogin() {
    global $user;
    $params = ['username' => $_POST['username'], 'password' => $_POST['password']];
    $check_user = $user->login($params);
    if ($check_user) {
        $_SESSION["is_login"] = true;
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
}
```

### 3. User class

**File:** `library/User.php`

```php
class User {
    public function login($params = array()) {
        $check_user = $this->db->fetch_data(
            DB_USER_TABLE,
            ['user_name' => $params['username'], 'password' => $params['password']],
            1
        );
        return $check_user;
    }
}
```

`fetch_data` builds a `SELECT * FROM tbl_user WHERE user_name = '...' AND password = '...'` query. If any row is returned, login is considered successful.

### 4. Session flag

On success, `$_SESSION["is_login"] = true` is set and the JS redirects the browser to `dashboard.php`.

### 5. Dashboard gate

**File:** `dashboard.php` (top of file)

```php
session_start();
if (!isset($_SESSION["is_login"])) {
    header("Location: index.php");
    exit;
}
```

Only the presence of the key is checked — its value and the identity of the logged-in user are not stored or verified beyond this point.

### 6. Logout

No explicit logout mechanism was found in the codebase. Closing the browser or session expiry (default PHP session timeout) is the only termination path.

---

## Session Details

| Property | Value |
|----------|-------|
| Session variable | `$_SESSION["is_login"]` |
| Session storage | PHP default (file-based, `C:\xampp\tmp`) |
| Session ID | Managed by PHP via `PHPSESSID` cookie |
| Session timeout | PHP default (1440 seconds / 24 minutes of inactivity) |
| Session regeneration | None — same session ID from before login to after |
| User identity stored | No — only a boolean presence flag |
| Logout route | Not implemented |

---

## Security Issues

The following issues are documented here for awareness — not fixed in this recovery effort.

### 1. Plaintext password storage

Passwords in `tbl_user` are stored and compared as plaintext strings. There is no hashing (MD5, bcrypt, Argon2, or otherwise). Anyone with read access to the database can immediately read all credentials.

**Impact:** Full admin access to anyone who can read the database.

### 2. SQL injection in login query

`Database::fetch_data()` builds the WHERE clause via string concatenation:

```php
// Simplified from Database.php
$where[] = "user_name = '" . $user_name . "'";
$where[] = "password = '" . $password . "'";
```

No prepared statements. A username of `admin' --` would bypass the password check on a vulnerable MySQL configuration (though MySQLi's default multi-statement mode may partially mitigate this).

### 3. No session regeneration after login

`session_start()` is called but `session_regenerate_id(true)` is never called after successful authentication. This leaves the session vulnerable to session fixation attacks.

### 4. No CSRF protection

The login form (and all dashboard forms) submit to `ajax.php` without any CSRF token. Any page on the internet could forge a POST request to `ajax.php?requestMethod=login` on behalf of a logged-in user.

### 5. No account lockout

There is no rate limiting, failed-attempt counter, or lockout after N failed logins. Brute-force attacks are unrestricted.

### 6. Session flag only — no identity binding

`$_SESSION["is_login"]` is a boolean. The session does not record which user logged in, their IP, or their user agent. Session hijacking (via stolen `PHPSESSID` cookie) would grant full access with no auditability.

### 7. No `httponly` / `secure` flags on session cookie

PHP's default session cookie does not set `HttpOnly` or `Secure` flags unless configured in `php.ini`. On HTTP (non-TLS) connections, the session cookie is transmitted in cleartext.

---

## Protected Pages

| Page | Guard mechanism |
|------|-----------------|
| `dashboard.php` | `if (!isset($_SESSION["is_login"])) header("Location: index.php")` |
| `ajax.php` | No session check — relies on obscurity of endpoint URL |
| `import.php` | No session check found |
| `export/index.php` | No session check found |
| `export/download.php` | No session check found |
| `library/cron.php` | No HTTP access (CLI only — if web-accessible, no auth) |

**Critical gap:** `ajax.php`, `import.php`, `export/index.php`, and `export/download.php` have no session validation. Any unauthenticated user who knows the URL can invoke any AJAX action, import CSV data, or download the full offer list.

---

## Inferred `tbl_user` Schema

```sql
CREATE TABLE tbl_user (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(255) NOT NULL UNIQUE,
    password  VARCHAR(255) NOT NULL
);
```

There is no `email`, `role`, `last_login`, `created_at`, or `is_active` column referenced anywhere in the codebase.
