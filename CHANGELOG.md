# SCREAMINGFORWEB — Changelog & Specifications

**Internal SEO Spider / Web Crawler** · PHP + MySQL + AJAX

---

## Table of Contents

- [System Overview](#system-overview)
- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [Installation](#installation)
- [Security](#security)
- [Project Structure](#project-structure)
- [Changelog](#changelog)

---

## System Overview

SCREAMINGFORWEB is a lightweight, self-hosted SEO Spider that crawls websites for URL analysis. It uses an **asynchronous AJAX polling loop** to bypass PHP's execution time limits: each backend request completes in seconds while the frontend recursively calls the backend until the crawl queue is exhausted.

### Key Features

- Async crawling via AJAX recursive polling (no cron, no workers)
- Project-based organization with scan history
- Automatic sitemap-like discovery (follows internal `href` links)
- KPI dashboard (Total URLs, OK, Broken)
- Searchable, sortable results table
- CSV export with UTF-8 BOM
- Neo-Brutalism UI via Tailwind CSS CDN
- Guided installation wizard

### Constraints

| Constraint | Implementation |
|---|---|
| Single-user internal tool | Server-level auth (`.htaccess`), no login system |
| SEO protection | `robots.txt` Disallow, `X-Robots-Tag: noindex, nofollow` |
| No frameworks | Vanilla PHP, vanilla JS, Tailwind CSS CDN |
| No `api/` or `assets/` directories | Consolidated into `ajax.php` → `css/` `js/` |
| PHP timeout bypass | Async AJAX polling loops |

---

## Architecture

```
┌─────────────────┐     POST / ajax.php     ┌──────────────────┐
│   index.php      │ ←── create-project ──→  │                  │
│   project.php    │ ←── delete-project ──→  │    MySQL DB      │
│   scan-details   │ ←── delete-session ──→  │  (PDO via        │
│                  │ ←── start-scan ──────→  │   getDB())       │
│                  │ ←── crawl-batch ─────→  │                  │
│                  │ ←── export-csv ─────→   │                  │
└────────┬────────┘                          └──────────────────┘
         │
         │ JS: pollQueue() recursive loop
         │ └→ tick() → api('crawl-batch') → tick() or reload
         │
         ▼
   User Browser
```

### Crawl Flow

1. User submits URL → `start-scan` inserts session + root URL in queue
2. Frontend calls `crawl-batch` in a loop (`pollQueue`)
3. Backend dequeues batch (default: 2 URLs), crawls via cURL, extracts links
4. New links normalized & inserted into queue as `pending`
5. Loop repeats until queue is empty → session marked `completed`
6. Page auto-reloads to show scan history

### Error Handling

- **Root URL failure**: Session immediately marked `failed` with error reason shown inline
- **All-batch failure** (non-root): Session marked `failed`
- **Network errors**: Up to 5 retries with 2s delay, then page reload
- **PHP exceptions**: Session marked `failed`, generic error returned (details logged server-side)

---

## Database Schema

### `projects`

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| project_name | VARCHAR(255) | |
| client_name | VARCHAR(255) | |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

### `scan_sessions`

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| project_id | INT FK → projects(id) | CASCADE DELETE |
| root_url | VARCHAR(2048) | Target URL |
| status | ENUM('in_progress','completed','failed') | |
| failed_reason | TEXT NULL | Error detail for failed scans |
| url_found | INT UNSIGNED | Total discovered URLs |
| url_ok | INT UNSIGNED | HTTP 2xx/3xx |
| url_broken | INT UNSIGNED | HTTP 4xx/5xx+errors |
| scanned_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

Indexes: `(project_id)`, `(status)`

### `scan_queue`

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| session_id | INT FK → scan_sessions(id) | CASCADE DELETE |
| url | TEXT | |
| status | ENUM('pending','processing','completed','error') | |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

Indexes: `(session_id, status)`, UNIQUE `(session_id, url(191))`

### `scan_results`

| Column | Type | Notes |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| session_id | INT FK → scan_sessions(id) | CASCADE DELETE |
| url | TEXT | |
| status_code | INT NULL | null = error |
| title | VARCHAR(500) | From `<title>` |
| description | TEXT | From `<meta name="description">` or `og:description` |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |

Indexes: `(session_id)`, UNIQUE `(session_id, url(191))`

---

## Installation

1. Upload all files to your web server
2. Access `install.php` in browser
3. Step 1: Verify PHP extensions (`pdo`, `pdo_mysql`, `curl`, `dom`, `xml`, `mbstring`)
4. Step 2: Enter MySQL credentials (DB created automatically)
5. Step 3: Installation runs — tables created, `config.php` written
6. Step 4: Done — access dashboard at `index.php`

### Requirements

- PHP 8.0+ with extensions: PDO, pdo_mysql, cURL, DOM, XML, mbstring
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite`, `mod_headers` (recommended)
- Write permission on root directory (for `config.php`)

---

## Security

### Authentication & Access Control

No built-in login. Protect via server-level `.htaccess`:

```apache
AuthType Basic
AuthName "SCREAMINGFORWEB — Accesso riservato"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

### Search Engine Protection

- `robots.txt`: `Disallow: /`
- `.htaccess`: `X-Robots-Tag: noindex, nofollow` via `mod_headers`
- All pages: `<meta name="robots" content="noindex, nofollow">`

### File Access

| File | Protection |
|---|---|
| `config.php` | Deny all via `<Files>` |
| `*.sql`, `*.md`, `*.log`, `.ht*` | Deny all via `<FilesMatch>` |
| `install.php` | Redirects to dashboard if config exists |
| Directory listing | `Options -Indexes` |

### PHP Hardening (via .htaccess)

- `expose_php: Off`
- `max_execution_time: 30`
- `memory_limit: 256M`
- `display_errors: Off`
- `log_errors: On`

### SQL Injection Prevention

- All queries use prepared statements with PDO
- `PDO::ATTR_EMULATE_PREPARES => false` (real prepared statements)
- Installer escapes backticks in DB name for `CREATE DATABASE`
- Installer escapes single quotes/dollar signs in `config.php` heredoc

### Error Disclosure Prevention

- SQL/PHP error messages not exposed to client (generic messages returned)
- All errors logged server-side via `error_log()`
- Raw request body not echoed in error responses

---

## Project Structure

```
/
├── .htaccess          # Apache security, access control, PHP settings
├── robots.txt         # Disallow all crawlers
├── index.php          # Dashboard — project list & creation
├── project.php        # Project workspace — scan history & start scan
├── scan-details.php   # Analytics — KPI cards, results table, CSV export
├── install.php        # Guided installation wizard (4 steps)
├── migrate.php        # One-time migration helper (adds columns)
├── ajax.php           # Centralized AJAX handler + crawler engine
├── database.sql       # Schema reference
├── config.php         # DB credentials (generated by install.php)
├── CHANGELOG.md       # This file
├── INSTALL.md         # Installation guide
├── css/
│   └── app.css        # Neo-Brutalism custom styles
└── js/
    └── app.js         # Frontend — API calls, pollQueue, table controls
```

---

## Changelog

### v1.1.0 (2026-07-03)

#### Added
- `README.md` — comprehensive project documentation for GitHub
- `LICENSE` — MIT open source license
- `.gitignore` — exclusion rules for config, OS metadata, IDE files
- `.gitattributes` — text/binary normalization for Git
- Copyright footer on all pages: "Software sviluppato con licenza open source in Lecce da Marco De Sangro"

---

### v1.0.0 (2026-07-03)

#### Added
- Project CRUD (create, delete)
- Scan sessions with async AJAX crawling loop (`pollQueue`)
- cURL-based crawler with retry-once logic, charset detection
- Link extraction from `<a href>` (same-domain only)
- File extension & protocol filtering in `normalizeUrl()`
- KPI dashboard (Total, OK, Broken counters)
- Searchable, sortable results table
- CSV export with UTF-8 BOM and semicolon separator
- Guided installation wizard (`install.php`, 4 steps)
- Migration helper (`migrate.php`)
- Neo-Brutalism UI via Tailwind CSS CDN
- `.htaccess` with comprehensive security (auth, file protection, PHP hardening)
- `robots.txt` with `Disallow: /`

#### Fixed
- **Critical**: `encodeURIComponent` bug in AJAX URL construction — `&` and `=` were encoded inside `action` parameter, breaking `session_id` parsing. Fixed by separating `extraParams` from action string.
- **Critical**: `install.php` SQL injection in `CREATE DATABASE` / `USE` queries — `$name` now backtick-escaped.
- **Critical**: `install.php` PHP code injection in `config.php` heredoc — values now escape `'`, `\`, `$` before interpolation.
- **Critical**: `ob_start`/`ob_clean` added to all API endpoints to prevent JSON corruption from accidental PHP output.
- **High**: Exception messages leaked to client — replaced with generic "Check server logs" messages across all `ajax.php` endpoints.
- **High**: Batch failure detection used `$batchSize` (2) instead of actual `count($batch)`, causing all-failure detection to never trigger.
- **High**: `Promise.race` caused unhandled promise rejections — replaced with `AbortController` + `fetch.finally()`.
- **High**: Relative `ajax.php` URL broke in subdirectory contexts — now auto-detects base path via `window.location.pathname`.
- **High**: Progress bar showed `NaN%` when API returned 0 total — added guard `if (total > 0)`.
- **Medium**: Empty `catch` blocks swallowed DB errors — added `error_log()` calls.
- **Medium**: `parseInt()` without radix — added `, 10`.
- **Medium**: Race condition in session completion (theoretical) — removed redundant `status = 'completed'` from empty batch path.
- **Medium**: `ob_clean()` without `ob_get_level()` guard — wrapped in `ob_clean_safe()`.
- **Medium**: `project.php` status badge and dot classes not HTML-escaped.
- **Medium**: CSV export `session_id` not URL-encoded in JS.
- **Low**: `getJsonInput()` echoed raw request body in error response — removed.
- **Low**: `console.log` for errors → `console.warn`/`console.error`.
- **Low**: Redundant `classList.remove('animate-pulse')` removed.
- **Low**: Duplicate DOM updates in pollQueue `complete` path merged.

#### Changed
- Consolidated all API actions from `api/` directory into single `ajax.php?action=...` (server was blocking `api/` path).
- Moved `assets/` to `css/` and `js/` (server was blocking `assets/` path).
- Batch size reduced from 5 to 2 for faster per-request feedback.
- cURL timeout increased from 15s to 30s.
- Chrome 120 user-agent for better compatibility.
- `failed_reason` column added to `scan_sessions` with migration script.
- `config.php` permission set to `0640` after creation.

#### Removed
- `api/` directory (was blocked by server).
- `assets/` directory (was blocked by server).

---

## Technical Notes

### Crawler Details

- **cURL**: 30s timeout, 10s connect timeout, 10 max redirects, SSL verification disabled (internal use)
- **Retry**: 1 automatic retry with 500ms delay on failure
- **Charset detection**: From `Content-Type` header + `mb_convert_encoding` to UTF-8
- **Link normalization**: Strips fragments, filters protocols (`mailto:`, `tel:`, etc.), filters file extensions (PDF, images, etc.), restricts to same hostname
- **Batch size**: 2 URLs per request (configurable in `ajax.php`)

### Frontend

- **pollQueue()**: Recursive `setTimeout` loop with 300ms delay between batches
- **Timeout**: 120s per `crawl-batch` request (aborted via `AbortController`)
- **Retry**: Up to 5 retries with 2s delay on network error, then page reload
- **Working timer**: Shows elapsed time in "Working... (Xm Ys)" format

### Database

- InnoDB engine, CASCADE deletes on FK relationships
- `INSERT IGNORE` for deduplication via UNIQUE keys
- Transactions used in `start-scan` and `crawl-batch` dequeue
