# INSTRUCTION MANUAL & TECHNICAL SPECIFICATIONS: SCREAMINGFORWEB

## 1. PROJECT OVERVIEW & CONTEXT
* **Project Name:** SCREAMINGFORWEB
* **Target Role:** Expert PHP/MySQL Full-Stack Developer (DeepSeek AI)
* **Environment:** Internal demo server, strict single-user utility, completely hidden from public access and search engine bots.
* **Stack Rules:** Pure PHP (Vanilla/Procedural or lightweight MVC without heavy frameworks), MySQL/MariaDB (PDO only), HTML5, Vanilla JavaScript, and Tailwind CSS for a clean, layout-efficient Neo-Brutalism/Startup styling.

---

## 2. STRICT SECURITY & ENVIRONMENT CONSTRAINTS
This application is strictly for internal use. Security and privacy must be handled at the server and application levels without a complex user login system.
1. **Robots Exclusion:** * Every HTML page must include: `<meta name="robots" content="noindex, nofollow">`.
   * A `robots.txt` file must be created in the root directory containing:
     ```text
     User-agent: *
     Disallow: /
     ```
2. **Access Control Instructions:** Provide a template for a server-level protection block (e.g., `.htaccess` for Apache with Basic Authentication or IP whitelisting) to ensure the application is accessible only when explicitly visited via the correct internal configuration.

---

## 3. DATABASE ARCHITECTURE (MySQL)
All database interactions must use PHP Data Objects (PDO) with strict prepared statements. 

```sql
CREATE DATABASE IF NOT EXISTS screamingforweb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE screamingforweb;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scan_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    root_url VARCHAR(255) NOT NULL,
    status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scan_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    url TEXT NOT NULL,
    status_code INT NULL,
    title VARCHAR(500) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES scan_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

---

## 4. FUNCTIONAL FLOW & USER INTERFACE

The UI must be extremely lightweight, compact, and optimized for fast workflow task-efficiency.
Screen 1: Dashboard (index.php)

    Project Creation Form: Simple card containing Project Name and Client Name inputs with a "Create Project" button.

    Projects Directory Table: Lists all saved projects.

        Columns: Project Name, Client, Creation Date, Actions (View Project / Delete).

Screen 2: Project Workspace (project.php?id={id})

    Header: Displays active Project Name and Client Name.

    Crawler Module Input: A single text field to enter the Root URL (e.g., https://example.com) and an "Start Scan" button.

    Scan History List: Displays past crawl sessions for this specific project, showing the date, target URL, status (In Progress / Completed), and a button to view details.

Screen 3: Scan Analytics Dashboard (scan-details.php?id={session_id})

    KPI Metrics Counters: Total URLs found, 200 OK status codes, and non-200/broken links counters.

    Main Data Table:

        Columns: URL (clickable), Status Code, Title, Description.

        Features: Client-side search/filter box and ascending/descending sorting for all columns.

    Export Action: Prominent button to "Export to CSV".

---

## 5. CORE CRAWLER ENGINE LOGIC

The system must crawl all public internal URLs starting from the Root URL recursively without hard-coded structural depth limits.
Execution Blueprint:

    Queue Initialization: When a scan starts, log the event in scan_sessions as in_progress.

    Asynchronous/Batch Processing Loop: To prevent PHP max_execution_time timeouts on large sites, do not run the entire crawl in a single PHP execution thread. Use an AJAX-based recursive loop from the frontend that requests the backend to process URLs in small batches, or implement a database-driven queue system.

    HTTP Extraction & Parsing:

        Fetch URLs using cURL in PHP.

        Extract the HTTP Status Code.

        If the header Content-Type is text/html, parse the DOM via DOMDocument and DOMXPath.

        Extract <title>, <meta name="description">, and all <a href="..."> links.

    URL Normalization Rules:

        Keep only internal links matching the root URL's host.

        Strip out fragments (#anchor), mailto:, tel:, and direct links to non-HTML attachments (PDF, ZIP, image extensions).

        If an internal link is unique to the current session, push it into the scanning queue.

    Immediate Persistence: Commit results directly into scan_results row-by-row as they are processed. Mark the session as completed once the queue is exhausted.

--- 

## 6. DATA EXPORT SPECIFICATIONS (CSV)

    Filename Syntax: screamingforweb-export-[project-name]-[session-id].csv

    Encoding: Must prepend the UTF-8 BOM (\xEF\xBB\xBF) to ensure perfect display of accented characters inside Microsoft Excel.

    Structure: Separated by a semi-colon ; or comma ,. Fields must include: URL, Status Code, Title, Description.