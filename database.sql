-- ============================================================
-- SCREAMINGFORWEB - Database Schema
-- Engine: MySQL / MariaDB
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS screamingforweb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE screamingforweb;

-- ------------------------------------------------------------
-- projects: Anagrafica progetti cliente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- scan_sessions: Istanza di scansione per un progetto
-- url_found / url_ok / url_broken = KPI cache per dashboard
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scan_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    root_url    VARCHAR(2048) NOT NULL,
    status      ENUM('in_progress','completed','failed') NOT NULL DEFAULT 'in_progress',
    failed_reason TEXT NULL DEFAULT NULL COMMENT 'Motivo del fallimento se status=failed',
    url_found   INT UNSIGNED NOT NULL DEFAULT 0,
    url_ok      INT UNSIGNED NOT NULL DEFAULT 0,
    url_broken  INT UNSIGNED NOT NULL DEFAULT 0,
    scanned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_sessions_project (project_id),
    INDEX idx_sessions_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- scan_queue: Coda di crawling asincrono
-- UNIQUE(session_id, url(191)) = evita URL duplicati nella coda
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scan_queue (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  INT NOT NULL,
    url         TEXT NOT NULL,
    status      ENUM('pending','processing','completed','error') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES scan_sessions(id) ON DELETE CASCADE,
    INDEX idx_queue_session_status (session_id, status),
    UNIQUE KEY uq_session_url (session_id, url(191))
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- scan_results: Risultati delle pagine scansionate
-- UNIQUE(session_id, url(191)) = evita doppioni nei risultati
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scan_results (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  INT NOT NULL,
    url         TEXT NOT NULL,
    status_code INT NULL,
    title       VARCHAR(500) NULL,
    description TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES scan_sessions(id) ON DELETE CASCADE,
    INDEX idx_results_session (session_id),
    UNIQUE KEY uq_result_session_url (session_id, url(191))
) ENGINE=InnoDB;
