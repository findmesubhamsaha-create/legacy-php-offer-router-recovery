# Running the Project

## Prerequisites

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | 5.6+ (7.x recommended) | Uses MySQLi — no namespace or modern PHP features required |
| MySQL / MariaDB | 5.7+ | Requires `ROW_NUMBER()` window function in export queries (MySQL 8+ / MariaDB 10.2+) |
| Apache | Any | Mod_rewrite not currently required (no `.htaccess` routing found) |
| XAMPP | Any | Project was developed under XAMPP |

No Composer. No npm. No build step. Pure PHP + MySQL.

---

## Local Setup (XAMPP)

### 1. Place files

The project must live in the Apache document root:

```
C:\xampp\htdocs\legacy-php-offer-router-recovery\
```

If placed elsewhere, all hardcoded self-referencing URLs will break (see Hardcoded Values below).

### 2. Create the database

Open phpMyAdmin at `http://localhost/phpmyadmin` and run the following DDL. This is **inferred** from code — no migration script exists in the repository.

```sql
CREATE DATABASE efbhalvbhdsurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE efbhalvbhdsurl;

CREATE TABLE tbl_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE tbl_tag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_name VARCHAR(255) NOT NULL,
    created_at DATE NOT NULL
);

CREATE TABLE tbl_network (
    id INT AUTO_INCREMENT PRIMARY KEY,
    network_name VARCHAR(255) NOT NULL,
    created_at DATE NOT NULL
);

CREATE TABLE tbl_offer_url (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer VARCHAR(255) NOT NULL,
    slug_name VARCHAR(255) NOT NULL,
    tag_id INT,
    note TEXT,
    network_id INT,
    offer_status TINYINT DEFAULT 1,
    status_updated_at DATE,
    start_date DATE,
    end_date DATE,
    FOREIGN KEY (tag_id) REFERENCES tbl_tag(id),
    FOREIGN KEY (network_id) REFERENCES tbl_network(id)
);

CREATE TABLE tbl_sub_offer_url (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_offer_id INT NOT NULL,
    sub_url TEXT NOT NULL,
    weight DECIMAL(7,4) DEFAULT 0,
    status ENUM('yes','no') DEFAULT 'yes',
    deleted_status ENUM('yes','no') DEFAULT 'no',
    FOREIGN KEY (main_offer_id) REFERENCES tbl_offer_url(id)
);

CREATE TABLE tbl_click (
    id INT AUTO_INCREMENT PRIMARY KEY,
    click_id VARCHAR(255),
    offer_id INT,
    sub_offer_id INT,
    ip_address VARCHAR(45),
    created_at DATE NOT NULL
);

CREATE TABLE tbl_report (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_offer_id INT,
    main_offer_url VARCHAR(255),
    offer_clicks INT DEFAULT 0,
    report_date DATE NOT NULL,
    UNIQUE KEY uq_offer_date (main_offer_id, report_date)
);
```

See [DATABASE_INFERENCE.md](DATABASE_INFERENCE.md) for full schema rationale.

### 3. Create the database user

```sql
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'KDms@jY7Gw';
GRANT ALL PRIVILEGES ON efbhalvbhdsurl.* TO 'admin'@'localhost';
FLUSH PRIVILEGES;
```

Alternatively, edit `library/Settings.php` to use an existing user.

### 4. Create a login account

There is no user-creation UI. Insert directly:

```sql
INSERT INTO tbl_user (user_name, password) VALUES ('admin', 'yourpassword');
```

Passwords are stored and compared in plaintext. See [AUTH.md](AUTH.md) for implications.

### 5. Start Apache and MySQL via XAMPP Control Panel

```
XAMPP Control Panel → Apache: Start
XAMPP Control Panel → MySQL: Start
```

### 6. Open the application

```
http://localhost/legacy-php-offer-router-recovery/
```

This loads `index.php` (the login form).

---

## Cron Job Setup

The daily report aggregation script must be scheduled externally. Without it, the click reports shown on the dashboard will be empty.

**Script:** `library/cron.php`  
**What it does:** Reads today's raw clicks from `tbl_click`, groups by offer, writes daily totals into `tbl_report`.

### Windows Task Scheduler (XAMPP local)

Create a task that runs daily at midnight:

```
Program:   C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\legacy-php-offer-router-recovery\library\cron.php
```

### Linux/cron (production server)

```
0 0 * * * php /var/www/html/legacy-php-offer-router-recovery/library/cron.php
```

---

## Configuration

All configuration lives in `library/Settings.php`:

```php
define('DB_HOST',       'localhost');
define('DB_USERNAME',   'admin');
define('DB_PASSWORD',   'KDms@jY7Gw');
define('DB_NAME',       'efbhalvbhdsurl');
define('DB_USER_TABLE', 'tbl_user');
define('DB_OFFER_TABLE','tbl_offer_url');
```

Change these constants to point at a different database. There is no `.env` file or environment-variable support.

---

## Hardcoded Values to Be Aware Of

| File | Hardcoded Value | Purpose |
|------|-----------------|---------|
| `export/index.php` | `https://efbhalvbhdsurl.com/portal/dashboard.php` | Back-to-dashboard link |
| `library/Offer.php` | `https://efbhalvbhdsurl.com/?oid=[slug]&...` | Redirect URL template in "Get Link" modal |
| `export/download.php` | DB credentials inline | Export query bypasses `Settings.php` |

---

## CSV Import

Access at `http://localhost/legacy-php-offer-router-recovery/import.php`.

**Expected CSV format:**

```
offer name, slug name, tag name, notes, network, start date, end date,
url1, weight1, status1,
url2, weight2, status2
```

Import rules enforced:
- Offer name and slug must be unique among active offers
- All active sub-URL weights must sum to exactly 100 (tolerance: 0.0001)
- Dates must be `YYYY-MM-DD` format or empty
- URLs must pass PHP `FILTER_VALIDATE_URL`
- No duplicate URLs within the same offer row

---

## CSV Export

Access at `http://localhost/legacy-php-offer-router-recovery/export/`.

- Exports only active offers (`offer_status = 1`) with non-deleted sub-URLs
- Downloads in batches of 100 rows per file
- Uses `ROW_NUMBER()` — requires MySQL 8+ or MariaDB 10.2+

---

## Troubleshooting

| Symptom | Likely Cause |
|---------|-------------|
| Blank page on login | PHP error suppressed — check `C:\xampp\php\logs\php_error_log` |
| "Access denied for user 'admin'" | MySQL user not created or wrong password in Settings.php |
| Dashboard redirects to login | Session not started — ensure `session_start()` fires before output |
| Report shows no data | Cron job not running — run `library/cron.php` manually once |
| Export fails | MySQL < 8 or MariaDB < 10.2 — `ROW_NUMBER()` not supported |
| Weights validation error on import | Active sub-URL weights in the CSV row do not sum to 100 |
