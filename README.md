# SCREAMINGFORWEB

> Internal SEO Spider / Web Crawler — lightweight, self-hosted, asynchronous.

SCREAMINGFORWEB is a PHP + MySQL web crawler that analyzes websites for URL discovery and HTTP status validation. It uses an **AJAX recursive polling loop** to bypass PHP execution time limits, crawling even large sites without cron jobs or workers.

---

## Features

- **Async crawling** via AJAX polling loop (no cron, no workers)
- **Project-based organization** with scan history
- **Automatic sitemap discovery** — follows internal `href` links
- **KPI dashboard** — Total URLs, OK (2xx/3xx), Broken counters
- **Searchable, sortable results table** with HTTP status filtering
- **CSV export** with UTF-8 BOM (semicolon separator, filterable by status)
- **Neo-Brutalism UI** via Tailwind CSS CDN
- **Guided installation wizard** (`install.php`, 4 steps)
- **Comprehensive security** — `.htaccess` with auth templates, file protection, PHP hardening

---

## Requirements

| Component | Required |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PHP Extensions | `pdo`, `pdo_mysql`, `curl`, `dom`, `xml`, `mbstring` |
| Apache | `mod_rewrite`, `mod_headers` (recommended) |

---

## Quick Install

```bash
# 1. Upload files to your web server

# 2. Set permissions
chown -R www-data:www-data /var/www/html/
chmod 755 /var/www/html/

# 3. Open browser and navigate to:
#    http://your-server/install.php

# 4. Follow the 4-step guided wizard
```

The installer will:
1. Check PHP extensions
2. Verify directory writability
3. Ask for MySQL credentials
4. Create the database, tables, and `config.php` automatically

---

## Architecture

```
User Browser                  Web Server (PHP)                   MySQL
     │                                │                              │
     │── index.php (Dashboard) ──────→│                              │
     │←──── HTML + JS ────────────────│                              │
     │                                │                              │
     │── AJAX: ajax.php?action=start-scan →│── INSERT session+queue ────→│
     │←──── { session_id } ────────────────│                              │
     │                                     │                              │
     │── AJAX: ajax.php?action=crawl-batch →│── SELECT pending URLs ─────→│
     │  (recursive loop, 300ms delay)      │── cURL each URL ─────────────│ (external HTTP)
     │                                     │── Parse DOM (title, meta,   │
     │                                     │   links)                    │
     │                                     │── INSERT results + queue ───→│
     │←──── { has_more, processed } ───────│                              │
     │                                     │                              │
     │ (repeat until has_more=false)       │                              │
```

### Crawl Flow

1. User submits URL → `start-scan` inserts session + root URL in queue
2. Frontend calls `crawl-batch` in a loop (`pollQueue`)
3. Backend dequeues a batch (2 URLs), crawls via cURL, extracts links
4. New links normalized and inserted into queue as `pending`
5. Loop repeats until queue is empty → session marked `completed`
6. Page auto-reloads to show scan history

---

## Project Structure

```
/
├── .htaccess            # Apache security, access control, PHP settings
├── .gitignore           # Git exclusion rules
├── .gitattributes       # Git attributes
├── LICENSE              # MIT license
├── README.md            # This file
├── CHANGELOG.md         # Changelog & technical specifications
├── INSTALL.md           # Detailed installation guide
├── FILTER_EXPORT_GUIDE.md  # Filter & export feature documentation
├── robots.txt           # Disallow all crawlers
├── index.php            # Dashboard — project list & creation
├── project.php          # Project workspace — scan history & start scan
├── scan-details.php     # Analytics — KPI cards, results table, CSV export
├── install.php          # Guided installation wizard (4 steps)
├── migrate.php          # One-time migration helper
├── ajax.php             # Centralized AJAX handler + crawler engine
├── database.sql         # Schema reference
├── migration.sql        # Schema migration script
├── css/
│   └── app.css          # Neo-Brutalism custom styles
└── js/
    └── app.js           # Frontend — API calls, pollQueue, table controls
```

---

## Usage

1. **Create a project** — enter project and client name
2. **Start a scan** — enter the root URL (e.g. `https://example.com`)
3. **Monitor progress** — the AJAX polling loop updates the progress bar in real time
4. **View analytics** — KPI cards show total URLs, OK, and broken counts
5. **Filter & search** — use the search box and HTTP status filter buttons
6. **Export CSV** — export all results or filter by status code

---

## Security

- No built-in login — protect via server-level `.htaccess` Basic Auth or IP whitelisting
- `robots.txt` with `Disallow: /`
- `X-Robots-Tag: noindex, nofollow` on all pages
- `config.php` blocked from direct HTTP access
- All SQL queries use prepared statements with PDO
- PHP error messages not exposed to client

---

## License

This project is open source software licensed under the [MIT License](LICENSE).

---

## Copyright

Software sviluppato con licenza open source in Lecce da **Marco De Sangro**.
