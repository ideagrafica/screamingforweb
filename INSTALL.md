# SCREAMINGFORWEB — Installation Guide

## Overview

SCREAMINGFORWEB is a Light SEO Spider / Web Crawler for internal use.
**PHP 7.4+ / MySQL 5.7+** required. No composer dependencies — pure vanilla stack.

---

## Requirements

| Component | Required | Notes |
|---|---|---|
| PHP | 7.4+ | 8.x recommended |
| MySQL / MariaDB | 5.7+ / 10.3+ | |
| PHP Extensions | `pdo`, `pdo_mysql`, `curl`, `dom`, `xml`, `mbstring` | All mandatory |

---

## Quick Install (Guided Wizard)

### 1. Upload files

Upload the entire `SCREAMINGFORWEB/` directory to your web server's document root (or a subdirectory) via FTP/SFTP.

Example structure after upload:

```
/var/www/html/
├── .htaccess
├── robots.txt
├── install.php
├── index.php
├── project.php
├── scan-details.php
├── ajax.php          ← unified AJAX handler (all API actions)
├── config.php        ← created by installer
├── database.sql
├── INSTALL.md
├── css/
│   └── app.css
└── js/
    └── app.js
```

### 2. Set permissions

Ensure the web server user can write to the root directory (the installer creates `config.php`):

```bash
chown -R www-data:www-data /var/www/html/
chmod 755 /var/www/html/
```

### 3. Visit the installer

Open your browser and navigate to:

```
http://your-server/install.php
```

The guided wizard will:
1. Check all PHP extensions are loaded
2. Verify the directory is writable
3. Ask for MySQL credentials (host, port, database name, username, password)
4. Create the database and all tables automatically
5. Write `config.php` with the connection settings
6. Redirect you to the Dashboard

> **Note**: `config.php` is protected by `.htaccess` from direct HTTP access.  
> If `.htaccess` is not supported (e.g., NGINX), add this rule manually:
> ```nginx
> location ~ ^/config\.php$ { deny all; }
> ```

### 4. Done

The Dashboard (`index.php`) will load. Create your first project and start crawling URLs.

---

## Manual Installation (if wizard fails)

### 1. Create the database

Run `database.sql` against your MySQL server:

```bash
mysql -u root -p < database.sql
```

### 2. Create `config.php`

Copy the template below and fill in your credentials:

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'screamingforweb');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}
```

### 3. Verify file permissions

```bash
chmod 644 config.php
```

---

## Security Notes

- The application includes `<meta name="robots" content="noindex, nofollow">` on every page.
- A `robots.txt` with `Disallow: /` blocks search engine crawlers.
- The `.htaccess` file blocks direct access to `.sql`, `.env`, `.md`, `.log`, `.bak`, `.ht*` files.
- `config.php` is blocked from direct HTTP access via Apache's `<Files>` directive.
- For production, enable **Basic Authentication** or **IP whitelisting** in `.htaccess` (instructions included as comments).

---

## Architecture Overview

```
User browser                     Web Server (PHP)                   MySQL
     │                                │                              │
     │── index.php (Dashboard) ──────→│                              │
     │←──── HTML + JS ────────────────│                              │
     │                                │                              │
      │── AJAX: ajax.php?action=start-scan →│── INSERT session+queue ────→│
      │←──── { session_id } ────────────────│                              │
      │                                     │                              │
      │── AJAX: ajax.php?action=crawl-batch →│── SELECT pending URLs ─────→│
      │  (recursive loop, 400ms delay)      │── cURL each URL ────────────│ (external HTTP)
     │                                │── Parse DOM (title, meta,   │
     │                                │   links)                    │
     │                                │── INSERT results + queue ──→│
     │←──── { has_more, processed } ──│                              │
     │                                │                              │
     │ (repeat until has_more=false)  │                              │
```

The **AJAX recursive polling** pattern ensures no single PHP execution exceeds a few seconds, bypassing `max_execution_time` even on sites with thousands of URLs.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Installer shows "Directory not writable" | Run `chmod 755` on the directory and ensure the web server user owns it |
| "Call to undefined function ..." | Install missing PHP extension (`apt install php-curl php-dom php-mbstring`) |
| Blank page on API calls | Check PHP error log; ensure `config.php` has correct DB credentials |
| Crawl stops mid-way | The AJAX loop retries up to 5 times. If it persists, check `max_execution_time` in PHP config (should be ≥ 30) |
| Export CSV downloads empty file | Ensure the session has results; check that the `scan_results` table is populated |
| 500 error on `.htaccess` | If using NGINX, the `.htaccess` is ignored — move rules to NGINX config |
---

**SCREAMINGFORWEB — Internal Use Only**
