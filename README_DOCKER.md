# Docker Local Environment — Offer Router

PHP 8.2 + Apache + MySQL 8 stack that mirrors the production layout exactly.
Source is mounted as a live volume — file edits are reflected immediately without rebuilding.

---

## Prerequisites

| Requirement | Minimum version |
|---|---|
| Docker Desktop | 4.x |
| Docker Compose | v2 (bundled with Docker Desktop) |

Stop XAMPP (or any process using ports **80**, **8080**, or **3306**) before starting.

---

## 1. Pre-flight: Configure `.env` for Docker

The PHP app reads credentials from `.env` in the project root.
Inside Docker the MySQL service is reachable by its service name `db`, not `localhost`.

Edit `.env` so it contains:

```
DB_HOST=db
DB_USERNAME=root
DB_PASSWORD=rootpassword
DB_NAME=efbhalvbhdsurl
```

> **Restore for XAMPP:** change `DB_HOST` back to `localhost` and `DB_PASSWORD` back to `` (empty) when returning to XAMPP.

---

## 2. Build and start

```bash
docker compose up --build
```

First run pulls the base images and compiles the PHP extensions (~2–3 min).
Subsequent starts are fast:

```bash
docker compose up
```

Wait until you see the `db` health-check pass (the `app` container starts after it).

---

## 3. Import the database schema and seed data

Run these **once**, after the containers are up:

```bash
docker compose exec db mysql -u root -prootpassword efbhalvbhdsurl < portal/database/schema.sql
docker compose exec db mysql -u root -prootpassword efbhalvbhdsurl < portal/database/seed.sql
```

> Run from the project root so the relative paths resolve correctly.

---

## 4. Verification checklist

Open a browser to each URL and confirm the expected result.

| # | URL | Expected result |
|---|---|---|
| 1 | `http://localhost:8080/portal/` | Login page loads |
| 2 | Login with `admin` / `admin123` | Redirects to dashboard |
| 3 | `http://localhost:8080/portal/dashboard.php` | Dashboard loads with offer table |
| 4 | Create / edit / delete an offer | Changes persist in the DB |
| 5 | `http://localhost:8080/?oid=sample-offer` | Redirects to the sub-URL (or 404 if slug not seeded) |
| 6 | `http://localhost:8080/portal/export/` | Export page renders |

For step 5, `sample-offer` is the `slug` value inserted by `seed.sql`.
Check the actual slug with:

```bash
docker compose exec db mysql -u root -prootpassword efbhalvbhdsurl -e "SELECT slug FROM tbl_offer_url LIMIT 5;"
```

---

## 5. Stopping containers

Stop and keep volumes (DB data preserved):

```bash
docker compose stop
```

Stop and remove containers (DB volume still preserved):

```bash
docker compose down
```

---

## 6. Reset the database volume

Wipes all DB data and lets you re-import from scratch:

```bash
docker compose down -v
docker compose up -d
docker compose exec db mysql -u root -prootpassword efbhalvbhdsurl < portal/database/schema.sql
docker compose exec db mysql -u root -prootpassword efbhalvbhdsurl < portal/database/seed.sql
```

---

## 7. Rollback to XAMPP

1. `docker compose down` (or `docker compose stop`)
2. Edit `.env`: restore `DB_HOST=localhost` and `DB_PASSWORD=` (empty)
3. Start XAMPP Apache + MySQL as before

No application files are modified by Docker — the volume mount is read-write on the host side, so any PHP edits made while Docker was running are already reflected on disk.

---

## 8. Common errors

### Port already in use

```
Error response from daemon: Ports are not available: listen tcp 0.0.0.0:8080
```

XAMPP or another process holds port 8080 (or 3307 for the DB).
Either stop XAMPP or change the host port in `docker-compose.yml`:

```yaml
ports:
  - "9080:80"   # change 8080 → 9080
```

---

### DB connection refused / app starts before MySQL is ready

The `app` service waits for the `db` health-check, but if MySQL takes longer than usual on first boot the app may log connection errors. Wait 10–15 seconds and reload the page — the connection will succeed once MySQL finishes initialising.

---

### `DB_HOST=localhost` still set in .env

```
Failed to connect to MySQL: Connection refused
```

The container cannot reach `localhost` MySQL. Edit `.env` and set `DB_HOST=db`.

---

### schema.sql import fails: "Table already exists"

The volume already has data from a previous run. Reset the volume first (see section 6), then re-import.

---

### `docker compose` not found (older Docker)

Use the legacy `docker-compose` binary:

```bash
docker-compose up --build
```

---

### PHP shows "connection success" inline on pages

This is a pre-existing echo in `portal/library/database/connect.php` and is not a Docker issue. It does not affect functionality.

---

## Service reference

| Service | Internal host | External access |
|---|---|---|
| PHP / Apache | `app` | `http://localhost:8080` |
| MySQL 8 | `db:3306` | `localhost:3307` (for GUI clients) |

MySQL GUI client settings (TablePlus, DBeaver, MySQL Workbench):

```
Host:     localhost
Port:     3307
User:     root
Password: rootpassword
Database: efbhalvbhdsurl
```
