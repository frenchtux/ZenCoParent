-- Migration 025 SQLite : Plan free = tous les modules
UPDATE plans
SET modules = '{"expenses":true,"photos":true,"messages":true,"medical":true}',
    description = 'Accès complet en mode gratuit — fonctionnalités identiques à la version Community.',
    updated_at = datetime('now')
WHERE name = 'free';
