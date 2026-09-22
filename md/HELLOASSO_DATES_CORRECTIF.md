# Correctif — date de création vs date d'import HelloAsso

**Statut : corrigé et déployé en prod le 2026-09-22.**

## Le problème

Lors d'un import HelloAsso, `created_at` (`members` et `memberships`)
était fixé à la date **d'exécution de l'import**, pas à la vraie date de
la commande HelloAsso (donc de l'adhésion). Pour un import fait bien après
coup (ex: import de l'historique 2018-2024), toutes les adhésions se
retrouvaient avec la même date de création, sans rapport avec la réalité.

## Le fix (permanent)

`helloasso_entry_created_at()` dans `app/helloasso.php` récupère la vraie
date de commande HelloAsso (`order.date`, déjà extraite dans
`$entry['date']` par `helloasso_build_entry()`) et l'utilise comme
`created_at` lors de l'import (`helloasso_run_import()`). Une nouvelle
colonne `imported_at` (migration `database/migrations/2026-09-22_helloasso_imported_at.sql`)
trace séparément la date réelle d'exécution de l'import.

Désormais, pour toute adhésion importée :
- `created_at` = date de la commande HelloAsso (date réelle de l'adhésion)
- `imported_at` = date à laquelle l'import a été exécuté

## Le backfill (ponctuel, déjà exécuté)

Les adhésions déjà en base avant ce correctif avaient `created_at` =
ancienne date d'import (le bug lui-même). Une page temporaire
(`/correction-dates-helloasso`, supprimée après usage) a permis de
corriger a posteriori chaque campagne, en repassant par l'API HelloAsso
pour retrouver la vraie date de commande de chaque adhésion déjà
importée. L'ancien `created_at` (= l'ancienne date d'import) a été
récupéré dans `imported_at` avant d'être écrasé, plutôt que perdu.

Logique de la page (et du script CLI équivalent, utilisable en local via
Docker, supprimés du repo après usage — voir historique git si besoin de
les retrouver) :
- Pas d'accès SSH sur l'hébergement Ionos mutualisé → une page admin a
  remplacé le script CLI initialement prévu, traitant une campagne par
  clic pour rester dans les limites de temps d'une requête web.
- `members.created_at` n'était abaissé que s'il était postérieur à la
  date d'adhésion la plus ancienne trouvée pour ce membre (jamais relevé),
  pour ne jamais écraser une date de création légitimement antérieure à
  HelloAsso (ex: adhésions manuelles/gratuites, coach...).

Résultat sur les campagnes locales testées : 442/448 adhésions corrigées.
Les 6 restantes : 2 comptes de test (seed `schema.sql`), 4 adhésions
manuelles/gratuites (ex: coach) sans commande HelloAsso correspondante —
comportement attendu, rien à corriger.

## Fichiers concernés

- `app/helloasso.php` : `helloasso_entry_created_at()`, `helloasso_run_import()`
- `database/schema.sql`, `database/migrations/2026-09-22_helloasso_imported_at.sql`
