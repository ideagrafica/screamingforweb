-- ============================================================
-- SCREAMINGFORWEB — Migration: add failed_reason column
-- Execute this ONLY if you already have the database installed
-- ============================================================

ALTER TABLE scan_sessions
    ADD COLUMN failed_reason TEXT NULL DEFAULT NULL
    AFTER status;
