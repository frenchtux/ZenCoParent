-- Migration 025 : Plan free = tous les modules (équivalent mode Community)
-- Le SubscriptionService retourne true pour tous les modules si plan.name='free',
-- mais on met aussi la DB à jour pour cohérence (ex: affichage admin des plans).
UPDATE plans
SET modules = '{"expenses":true,"photos":true,"messages":true,"medical":true}',
    description = 'Accès complet en mode gratuit — fonctionnalités identiques à la version Community.',
    updated_at = NOW()
WHERE name = 'free';
