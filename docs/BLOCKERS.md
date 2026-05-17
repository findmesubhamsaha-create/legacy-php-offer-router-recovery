# Blockers

All issues preventing or degrading first local run, ranked by severity.  
**Do not fix yet** — this document is analysis only.

Severity levels:
- **CRITICAL** — prevents the application from starting or makes a core flow non-functional
- **HIGH** — a major feature is broken or a serious security/data risk exists
- **MEDIUM** — a specific feature fails; the rest of the app still works
- **LOW** — cosmetic, configuration, or potential future issue

---

## CRITICAL

---

### C-1: Database does not exist — no schema, no seed data

**Location:** N/A (missing infrastructure)  
**Finding:** There are no SQL migration files in the repository. The database `efbhalvbhdsurl` must be created manually before any page can load. `ajax.php` includes `Database.php` which immediately calls `new mysqli(...)` in `__construct()`. If the database does not exist, every request to `ajax.php` dies with:

```
Connection failed: Unknown database 'efbhalvbhdsurl'
```

This includes the login request. The application cannot start at all without the database.

**Impact:** Entire application — zero functionality.  
**File generated:** `database/schema.sql`, `database/seed.sql`

---

### C-2: `archiveOffer()` and `deleteOffer()` always die with raw debug output

**Location:** `ajax.php` lines 282 and 297  
**Finding:** Both functions contain an unreachable `print_r(); die()` debug statement immediately after the business call, before the JSON response block:

```php
// archiveOffer (line 280–289):
function archiveOffer($params = array()){
    $offer = new Offer();
    $archive_status = $offer->archiveOffer($params);

    print_r($archive_status); die();   // ← NEVER REMOVED DEBUG LINE

    if(!empty($archive_status)){
        echo json_encode(array('response'=>true, 'message'=>'Offer Archived !'));
    }
    ...
}

// deleteOffer (line 293–304): identical pattern
    print_r($delete_status); die();    // ← NEVER REMOVED DEBUG LINE
```

**Impact:** Clicking **Archive** or **Delete** on any offer in the dashboard sends a raw PHP value to the browser (e.g. `1` or `0`) instead of JSON. The JavaScript AJAX handler expects JSON — it will silently fail or throw a parse error. The status change **does** execute in the DB (the UPDATE runs before the die()), but no success/error feedback is ever returned to the UI. The dashboard does not refresh. The DataTable does not update.  

**Consequence:** Archive and Delete are effectively broken from the dashboard UI perspective.

---

### C-3: No admin user — cannot log in without seed data

**Location:** `tbl_user` (must be populated manually)  
**Finding:** There is no user registration flow. The only way to create a user is a direct `INSERT INTO tbl_user` SQL statement. With a fresh schema and no seed data, the login form will always return `{"response":false,"message":"Please check once your provided information!."}` regardless of what credentials are entered.

**Impact:** Dashboard is inaccessible without manually inserting a user row.  
**Resolution path:** Run `database/seed.sql` which inserts `admin` / `admin123`.

---

## HIGH

---

### H-1: `join_query()` and `filter_query()` in Database.php do not initialize `$return`

**Location:** `library/database/Database.php` lines 209–226 (`join_query`) and 229–246 (`filter_query`)  
**Finding:** The `$return` variable is used inside the `if($result)` block but never initialized before it:

```php
public function join_query($sql){
    $result = $this->conn->query($sql);
    if($result)
    {
        if($result->num_rows > 0)
        {
            while($row = $result->fetch_assoc())
            {
                $return[] = $row;   // $return never initialized
            }
        }
    }
    return $return;   // undefined if 0 rows returned
}
```

**Impact:** When a query legitimately returns 0 rows (e.g., no offers exist yet, no clicks for a date), PHP emits a `Notice: Undefined variable: return` and the function returns `null`. Code callers check `if(!empty($get_sub_offers))` in Postback.php (line 15) — this handles the null case safely. But callers in `Offer.php::fetchAll` call `count($final_offer_list)` where `$final_offer_list` could be `null`, throwing a PHP 8+ `TypeError` (in PHP 7 `count(null)` emits a warning). On a fresh database with no offers, the dashboard DataTable will break.

**Impact scope:** Dashboard offer table with empty DB; any filter query returning 0 rows.

---

### H-2: Hardcoded production domain throughout the codebase

**Location:**
- `library/Offer.php` line 339: `https://efbhalvbhdsurl.com/?oid=...`
- `export/index.php` line 28: `https://efbhalvbhdsurl.com/portal/dashboard.php`
- `export/download.php` lines 4–7: DB credentials hardcoded (bypasses Settings.php)

**Finding:** The "Get Link" feature in the dashboard (copy-to-clipboard button) constructs the click URL using the production domain hardcoded in PHP:

```php
$link = 'https://efbhalvbhdsurl.com/?oid='.$final_offer_list[$i]['slug_name']
       .'&tag='.$final_offer_list[$i]['tag_name']
       .'&affid='.$final_offer_list[$i]['network_name'];
```

Running locally, this link will point to the production server, not `localhost`. Testing the routing flow locally using these generated links will hit production.

**Impact:** "Get Link" modal shows wrong URL in local environment. Direct local routing test must be done manually (see RECOVERY_STEPS.md Step 8).

---

### H-3: Passwords stored and compared as plaintext

**Location:** `library/User.php` line 12; `tbl_user.password` column  
**Finding:** Login query:

```php
$check_user = $this->db->fetch_data(
    DB_USER_TABLE,
    ['user_name'=>$params['username'], 'password'=>$params['password']],
    1
);
```

This builds: `SELECT * FROM tbl_user WHERE user_name = 'x' AND password = 'x'`  
The password column is stored as-is. No hashing of any kind (MD5, SHA, bcrypt, Argon2).

**Impact:** Anyone with SELECT access to `tbl_user` has immediate credential access. Combined with the SQL injection vulnerability (H-4), a single injection attack yields all credentials.

---

### H-4: SQL injection across all database operations

**Location:** `library/database/Database.php` — all methods (`save_data`, `fetch_data`, `fetch_data_new`, `fetch_clicks`, `update_data`, `join_query`, `filter_query`)  
**Finding:** Every method builds SQL by string concatenation. Example from `fetch_data` (line 44):

```php
$where[] = "$key = '$val'";
```

No parameterized queries. No `bind_param`. No escaping. Raw `$_REQUEST` values flow from `ajax.php` through dispatch functions directly into these methods.

**Impact for first run:** Not a functional blocker. The application runs. However, any user who can reach `ajax.php` can inject arbitrary SQL. Combined with no session checks on `ajax.php` (see H-5), this is externally exploitable.

---

### H-5: No authentication guard on `ajax.php`, `import.php`, `export/index.php`, `export/download.php`

**Location:** Top of `ajax.php` (line 1–17), `import.php`, `export/index.php`, `export/download.php`  
**Finding:** `ajax.php` calls `session_start()` but never checks `$_SESSION["is_login"]`. Any unauthenticated request to `ajax.php?requestMethod=fetchAll` will return the full offer list. Any request to `ajax.php?requestMethod=postBack` will route clicks and insert click records — no login required.

**Impact for first run:** Not a functional blocker locally. In any internet-accessible deployment, the entire API surface is publicly accessible.

---

## MEDIUM

---

### M-1: `resetUserPassword()` calls a non-existent static method

**Location:** `ajax.php` lines 448–458; `library/User.php`  
**Finding:**

```php
function resetUserPassword($params = array())
{
    $resetpass = User::resetPassword(...);  // static call
    ...
}
```

`User` class has only one method: `login()` (instance, not static). `resetPassword()` does not exist anywhere in the codebase.  

**Impact:** If `ajax.php?requestMethod=resetPassword` is ever called, PHP throws a fatal error:  
`Call to undefined method User::resetPassword()`  
The reset password case is in the switch — it can be triggered by anyone.

---

### M-2: `export/download.php` — `bind_param()` on a query with no placeholders

**Location:** `export/download.php` lines 75–83  
**Finding:** The SQL query uses PHP string interpolation for `$limit` and `$offset`:

```php
$query = "SELECT * FROM ( ... ) AS subquery LIMIT $limit OFFSET $offset;";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('ii', $limit, $offset);  // no '?' in $query
$stmt->execute();
```

`prepare()` succeeds (the query is valid SQL with the values already embedded), but `bind_param('ii', ...)` will throw:  
`mysqli_stmt::bind_param(): Number of bind variables doesn't match number of fields`  

The statement will not execute. CSV export will produce an empty file or a PHP fatal error.

---

### M-3: `library/database/connect.php` is web-accessible and outputs "connection success"

**Location:** `library/database/connect.php`  
**Finding:** This is a debug connection test file. It connects to the database and echoes `connection success`. It has no BASEPATH guard, no session check, and no removal of the output:

```php
echo "connection success";
```

When accessed at `http://localhost/legacy-php-offer-router-recovery/library/database/connect.php`, it confirms to any visitor that the database connection is working. On a public server, this is an information disclosure.

**Impact locally:** Useful as a connection test (see RECOVERY_STEPS.md Step 6). Not a functional blocker.

---

### M-4: MySQL version requirement for `ROW_NUMBER()` window function

**Location:** `export/index.php` line 60; `export/download.php` line 75  
**Finding:** Both export files use `ROW_NUMBER() OVER (ORDER BY ...)`. This window function requires:
- MySQL 8.0+ (released April 2018), OR
- MariaDB 10.2+ (released May 2017)

XAMPP ships with MySQL 5.x on older installations. Running the export on MySQL 5.7 will throw:  
`ERROR 1305 (42000): FUNCTION efbhalvbhdsurl.ROW_NUMBER does not exist`

**Impact:** The CSV export feature is broken on XAMPP with MySQL 5.x. All other features are unaffected.

**Check:**
```sql
SELECT VERSION();
```

---

### M-5: `PostbackBeta.php` has BASEPATH guard commented out — directly web-accessible

**Location:** `library/PostbackBeta.php` lines 1–4  
**Finding:**

```php
// if (!defined('BASEPATH')){
//     exit('Direct script access is not allowed!');
// }
```

Unlike all other library files, this one can be directly requested via HTTP. It defines a class and does nothing on direct access, but the path is exposed and the class definition would execute in isolation (no DB constants defined — would throw errors if methods were somehow invoked directly). Not a runtime blocker but a code hygiene issue.

---

### M-6: `ajax.php` — `addNewOffer` processes form data without validating `check_x` field names

**Location:** `ajax.php` lines 162–175  
**Finding:** The active-status array for sub-URLs is built by checking for `check_1`, `check_2`, etc. keys in `$_REQUEST`:

```php
for ($x = 1; $x <= count($param['url']); $x++) {
    if(array_key_exists("check_$x",$param)){
        array_push($isActive,"yes");
    } else {
        array_push($isActive,"no");
    }
}
```

The loop uses `count($param['url'])` but iterates with 1-based index while `url[]` arrays are 0-based. If the form sends `url[0]`, `url[1]`, etc., then `check_1` maps to index 1 (second URL), not index 0 (first URL). The first URL's checkbox is never detected. This is a pre-existing quirk — do not change it, but be aware the first sub-URL may never be marked active via the form.

---

## LOW

---

### L-1: No `.htaccess` file

**Location:** Project root (missing)  
**Finding:** No Apache rewrite rules exist. The routing endpoint is accessed as `ajax.php?requestMethod=postBack&oid=...` — ugly but functional. No clean URL routing is set up. Not a blocker for first run.

---

### L-2: `library/cron.php` outputs HTML `<pre>` tag even when run from CLI

**Location:** `library/cron.php` line 8  
**Finding:** `echo '<pre>';` is at the top of cron.php. When run via CLI (`php cron.php`), this outputs `<pre>` as literal text to stdout. Not harmful but noisy in cron logs.

---

### L-3: Multiple dated backup files in the web root

**Location:** Project root, `library/`, `backup/`  
**Finding:** Files like `ajax(bkp-10-09-2024).php`, `Offer(bkp-23-09-2024).php`, etc. are all web-accessible. They contain older versions of the code, including the same hardcoded credentials. On any public server, these are all readable. Not a blocker for local run.

---

### L-4: `library/Settings.php` defines `BASEPATH` but no file uses it for path resolution

**Location:** `library/Settings.php` lines 2–3  
**Finding:**

```php
if (!defined('BASEPATH'))
    define('BASEPATH', dirname(dirname(__FILE__)));
```

`BASEPATH` is defined but never used as a path prefix in any `require` or `include` statement. It is only checked as a defined-constant guard (`if (!defined('BASEPATH'))`). The guard works, but `BASEPATH` itself has no functional purpose in the current code.

---

## Summary Table

| ID | Severity | Description | Affects First Run |
|----|----------|-------------|-------------------|
| C-1 | CRITICAL | Database does not exist | Yes — app cannot start |
| C-2 | CRITICAL | Archive/Delete always die() | Yes — buttons broken |
| C-3 | CRITICAL | No admin user to log in | Yes — can't access dashboard |
| H-1 | HIGH | join_query/filter_query uninitialized `$return` | Yes — dashboard breaks on empty DB |
| H-2 | HIGH | Hardcoded production domain | Partial — Get Link shows wrong URL |
| H-3 | HIGH | Plaintext password storage | Security — not a functional blocker |
| H-4 | HIGH | SQL injection throughout | Security — not a functional blocker |
| H-5 | HIGH | No auth on ajax.php | Security — not a functional blocker |
| M-1 | MEDIUM | resetPassword method missing | Only if reset route triggered |
| M-2 | MEDIUM | bind_param bug in export | CSV export broken |
| M-3 | MEDIUM | connect.php web-accessible | Info disclosure — not a blocker |
| M-4 | MEDIUM | ROW_NUMBER requires MySQL 8+ | Export broken on old MySQL |
| M-5 | MEDIUM | PostbackBeta.php guard commented out | Not a blocker |
| M-6 | MEDIUM | First checkbox off-by-one in addNewOffer | Pre-existing quirk |
| L-1 | LOW | No .htaccess | Not a blocker |
| L-2 | LOW | cron.php HTML in CLI output | Not a blocker |
| L-3 | LOW | Backup files web-accessible | Security concern only |
| L-4 | LOW | BASEPATH defined but unused | Not a blocker |
