-- Expand the event type CHECK constraint to include new types.
--
-- PostgreSQL: ALTER TABLE approach with known constraint name.
-- SQLite: no ALTER TABLE DROP CONSTRAINT support — handled via
--         the rewriteForSqlite() function in migrate_sqlite.php
--         which regenerates the table with expanded types.

ALTER TABLE events
    DROP CONSTRAINT IF EXISTS events_type_check;

ALTER TABLE events
    ADD CONSTRAINT events_type_check
    CHECK (type IN ('custody', 'activity', 'medical', 'rendezvous', 'activite', 'vacances', 'autre'));
