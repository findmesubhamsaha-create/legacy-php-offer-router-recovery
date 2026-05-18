# Recovery Steps

Exact commands to bring the project to a first successful local run.  
Environment: Windows 10, XAMPP. All commands run in XAMPP Shell or PowerShell unless noted.

---

## Prerequisites Checklist

- [ ] XAMPP installed (Apache + MySQL components)
- [ ] Internet connection (all JS/CSS loaded from CDN — no local vendor)
- [ ] Project files at `C:\xampp\htdocs\legacy-php-offer-router-recovery\`

---

## Step 1 — Start XAMPP Services

Open **XAMPP Control Panel** and click **Start** for both:
- Apache
- MySQL

Verify both show green status before continuing.

---

## Step 2 — Create the Database and User

Open phpMyAdmin:

```
http://localhost/phpmyadmin
```

Click **SQL** tab and run the following:

```sql
CREATE DATABASE IF NOT EXISTS `efbhalvbhdsurl`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'admin'@'localhost' IDENTIFIED BY 'KDms@jY7Gw';
GRANT ALL PRIVILEGES ON `efbhalvbhdsurl`.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```

> If MySQL already has a user named `admin`, check its password matches `KDms@jY7Gw`.  
> If it does not and you cannot change it, edit `library/Settings.php` — see Step 4.

---

## Step 3 — Import Schema

In phpMyAdmin, select the `efbhalvbhdsurl` database from the left panel, then:

**Option A — phpMyAdmin UI:**
1. Click **Import**
2. Choose file: `C:\xampp\htdocs\legacy-php-offer-router-recovery\database\schema.sql`
3. Click **Go**

**Option B — MySQL command line (XAMPP Shell):**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u admin -pKDms@jY7Gw efbhalvbhdsurl < "C:\xampp\htdocs\legacy-php-offer-router-recovery\database\schema.sql"
```

Expected output: no errors. Verify with:

```sql
USE efbhalvbhdsurl;
SHOW TABLES;
```

Expected tables: `tbl_user`, `tbl_tag`, `tbl_network`, `tbl_offer_url`, `tbl_sub_offer_url`, `tbl_click`, `tbl_report`

---

## Step 4 — Verify Settings.php (Update If Needed)

Open `library/Settings.php` and confirm the constants match your local setup:

```php
define('DB_HOST',     'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', 'KDms@jY7Gw');
define('DB_NAME',     'efbhalvbhdsurl');
```

If your MySQL credentials differ, edit this file with the correct values.  
**Also update `export/index.php` (line ~43) and `export/download.php` (lines 4–7)** — these files have the credentials hardcoded separately and do not read from `Settings.php`.

---

## Step 5 — Import Seed Data

**Option A — phpMyAdmin UI:**
1. Select `efbhalvbhdsurl` database
2. Click **Import**
3. Choose file: `C:\xampp\htdocs\legacy-php-offer-router-recovery\database\seed.sql`
4. Click **Go**

**Option B — MySQL command line:**

```bash
"C:\xampp\mysql\bin\mysql.exe" -u admin -pKDms@jY7Gw efbhalvbhdsurl < "C:\xampp\htdocs\legacy-php-offer-router-recovery\database\seed.sql"
```

Verify the seed worked:

```sql
USE efbhalvbhdsurl;
SELECT user_name, password FROM tbl_user;
SELECT SUM(weight) AS weight_total
FROM tbl_sub_offer_url
WHERE main_offer_id = 1 AND status = 'yes';
-- Expected: 100.0000
```

---

## Step 6 — Test the Database Connection

Open in browser:

```
http://localhost/legacy-php-offer-router-recovery/library/database/connect.php
```

Expected output: `connection success`

If you see a connection error, revisit Step 2 and Step 4.

> **Note:** This file is web-accessible and prints a success message — it is a developer test utility. Do not leave it exposed in production. See BLOCKERS.md.

---

## Step 7 — Access the Login Page

```
http://localhost/legacy-php-offer-router-recovery/
```

This loads `index.php`. The login form should appear.

**Credentials from seed data:**
- Username: `admin`
- Password: `admin123`

On successful login, you will be redirected to `dashboard.php`.

---

## Step 8 — Test Traffic Routing Endpoint

The routing endpoint can be tested directly in a browser or with curl. Using the seed offer (slug: `sample-offer`):

```
http://localhost/legacy-php-offer-router-recovery/ajax.php?requestMethod=postBack&oid=sample-offer&ip=127.0.0.1&click_id=test-click-001
```

Expected JSON response:
```json
{"response":true,"message":"https://www.example-destination-a.com/landing"}
```

(The exact URL will vary — weighted random selection distributes across the three seed sub-URLs.)

Verify the click was recorded:

```sql
USE efbhalvbhdsurl;
SELECT * FROM tbl_click WHERE click_id = 'test-click-001';
```

---

## Step 9 — Run the Cron Job Manually (First Time)

The daily report aggregation script must be run at least once to populate `tbl_report`. Without this, the "View Report" modal on the dashboard will show no data.

**XAMPP Shell or PowerShell:**

```bash
"C:\xampp\php\php.exe" "C:\xampp\htdocs\legacy-php-offer-router-recovery\library\cron.php"
```

Expected output (if today has clicks):
```
New Row Inserted
```

Or if a row already exists for today (from seed data):
```
Old Row Updated
```

To schedule this to run daily automatically:

1. Open **Windows Task Scheduler**
2. Create a Basic Task
3. Trigger: Daily at 00:05
4. Action: Start a Program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\legacy-php-offer-router-recovery\library\cron.php`

---

## Step 10 — Test CSV Export (Optional)

Navigate to:

```
http://localhost/legacy-php-offer-router-recovery/export/
```

> **Warning:** This page has hardcoded credentials (`export/index.php` and `export/download.php`) and a hardcoded link back to `https://efbhalvbhdsurl.com/portal/dashboard.php`. The "Back to dashboard" sidebar link will not work locally.  
> Also: the export uses `ROW_NUMBER()` window function which requires **MySQL 8.0+** or **MariaDB 10.2+**. Check your XAMPP MySQL version first:

```sql
SELECT VERSION();
```

---

## Quick Reference — All URLs

| Purpose | URL |
|---------|-----|
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Login page | `http://localhost/legacy-php-offer-router-recovery/` |
| Dashboard | `http://localhost/legacy-php-offer-router-recovery/dashboard.php` |
| CSV Import | `http://localhost/legacy-php-offer-router-recovery/import.php` |
| CSV Export | `http://localhost/legacy-php-offer-router-recovery/export/` |
| DB connection test | `http://localhost/legacy-php-offer-router-recovery/library/database/connect.php` |
| Routing endpoint (test) | `http://localhost/legacy-php-offer-router-recovery/ajax.php?requestMethod=postBack&oid=sample-offer&ip=127.0.0.1&click_id=test-001` |

---

## Known Broken Features (Do Not Attempt Yet)

The following features have confirmed code bugs and will not work even after a clean setup. See BLOCKERS.md for details.

| Feature | Location | Bug |
|---------|----------|-----|
| Archive offer button | `ajax.php` line 282 | `print_r(); die()` debug statement — JSON never returned |
| Delete offer button | `ajax.php` line 297 | `print_r(); die()` debug statement — JSON never returned |
| CSV export download | `export/download.php` line 82 | `bind_param('ii',...)` on a query with no `?` placeholders |
| Reset password | `ajax.php` line 450 | Calls `User::resetPassword()` — method does not exist |
