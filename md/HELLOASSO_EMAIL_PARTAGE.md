# Couples partageant une seule adresse email

**Statut : bug de rapprochement corrigé le 2026-09-22. Limitation
résiduelle (pas de compte utilisable pour le 2e adhérent) connue et
acceptée, à traiter au cas par cas.**

## Le contexte

Certains couples adhérents utilisent une seule et même adresse email pour
les deux (ex: `ludovic.gransagne@laposte.net` pour Ludovic **et**
Madely Gransagne). Or `members.email` est UNIQUE en base
(`database/schema.sql`) : un seul des deux peut détenir cette adresse.
Cas découverts en prod : familles Gransagne et Joyeux.

## Conséquence 1 — connexion impossible pour le 2e adhérent

`find_member_by_identifier()` (`app/auth.php`) authentifie uniquement par
`username` OU `email`. L'adhérent dont la fiche n'a ni l'un ni l'autre
(l'email étant pris par son/sa conjoint·e) ne peut **pas se connecter** —
aucun identifiant possible à saisir.

**Traitement retenu : au cas par cas.** Pas d'automatisation (trop peu de
cas pour le justifier — 2 au 2026-09-22) : l'admin attribue simplement un
`username` (ex: le prénom) à la fiche concernée depuis la page de
modification d'un adhérent. Vérifié le 2026-09-22 : après correction
manuelle des 2 cas connus, plus aucun membre actif n'est dans cette
situation (requête de contrôle : `members` avec `email IS NULL AND
username IS NULL`).

Si le cas se reproduit, même remède : donner un `username` à la fiche.

## Conséquence 2 — mauvais rapprochement lors de l'import (corrigé)

`helloasso_match_member()` (`app/helloasso.php`) rapprochait d'abord par
email, puis par nom en repli. Comme l'email du couple n'est enregistré
que sur une seule fiche, l'item HelloAsso du/de la conjoint·e sans email
propre se rattachait **à tort** à l'autre fiche (ex: l'adhésion de Madely
attribuée à Ludovic), simplement parce que son email y figurait.

**Fix appliqué** : avant de faire confiance à un match par email, on
vérifie que le nom de la fiche trouvée correspond bien au nom de l'item
HelloAsso. En cas de désaccord (cas du couple), on préfère le
rapprochement par nom ; à défaut de nom trouvé, on garde le match par
email (comportement précédent, pour ne pas régresser sur des cas plus
ambigus comme un changement de nom).

Testé en local le 2026-09-22 : suppression puis ré-import réel des 4
adhésions 2025-2026 concernées (Gransagne, Joyeux) → chacun matche
désormais sur sa propre fiche, avec sa propre date de commande.

## Fichiers concernés

- `app/helloasso.php` : `helloasso_match_member()`
- `app/auth.php` : `find_member_by_identifier()` (mécanisme de connexion, non modifié)
