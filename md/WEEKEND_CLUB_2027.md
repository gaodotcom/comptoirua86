# Week-end club Lozère Trail 2027 — Préinscriptions

## Objectif

Page ponctuelle et hors menu permettant aux adhérents connectés de se
préinscrire au week-end club organisé par Ultramical86 au Lozère Trail
(15-16 mai 2027). Les inscriptions définitives sont ensuite faites
groupées par le club directement auprès de l'organisateur (réduction de
10%) — cette page ne sert qu'à recenser les intentions et les infos
nécessaires, il n'y a **aucun paiement** dans l'application.

À supprimer (page + routes + tables) une fois l'événement passé, si elle
n'est pas réutilisée pour une future édition.

## Accès

- **Adhérent connecté** : `/week-end-club-2027` — formulaire de
  préinscription pour soi-même uniquement (pas d'inscription pour un
  tiers). Modifiable tant que les préinscriptions ne sont pas closes.
- **Adhérent connecté** : `/week-end-club-2027-inscrits` — liste des
  inscrits (avatar, nom, prénom, course(s) uniquement). L'export CSV,
  les infos complémentaires (formule solo/duo, bivouac, taille de
  maillot, hébergement...) et la clôture/réouverture des préinscriptions
  sont réservés aux admins (`is_admin()`, vérifié aussi bien dans le
  contrôleur — y compris pour l'export CSV — que dans le twig).

Ces deux pages ne sont **volontairement pas présentes dans le menu**
(`templates/components/navbar.twig`) : elles ne sont accessibles que par
leur URL directe.

## Structure technique

| Rôle | Fichier |
|---|---|
| Logique métier (catalogue, validation, sauvegarde, export) | `app/weekend_club_2027.php` |
| Contrôleur formulaire adhérent | `controllers/weekend-2027/weekend-club-2027.php` |
| Contrôleur liste des inscrits (export/clôture réservés aux admins) | `controllers/weekend-2027/weekend-club-2027-inscrits.php` |
| Vue formulaire | `templates/pages/weekend-2027/weekend-club-2027.twig` |
| Vue liste des inscrits | `templates/pages/weekend-2027/weekend-club-2027-inscrits.twig` |
| Schéma des tables (nouvelles installations) | `database/schema.sql` |
| Migration (bases déjà déployées) | `database/migrations/2026-09-18_weekend_club_2027.sql` |
| Logo de l'épreuve | `public/img/weekend-club-2027-logo.png` |

### Tables

- `weekend_2027_registrations` : une ligne par adhérent (clé unique sur
  `member_id`), avec toutes les infos du formulaire (formule solo/duo,
  coéquipier, bivouac, taille de maillot, contact d'urgence, hébergement,
  numéro de licence/PPS).
- `weekend_2027_registration_courses` : table de jointure — une ligne par
  course choisie (un adhérent peut avoir plusieurs courses, ex : Skyrace +
  une course du dimanche).
- `weekend_2027_settings` : ligne unique (id=1) qui stocke l'état
  ouvert/clos des préinscriptions.

### Catalogue des courses

Défini en dur dans `weekend_2027_courses()` (`app/weekend_club_2027.php`),
5 courses fixes : `ultra`, `trail2r`, `trail27`, `salta`, `skyrace`.

## Règles métier (validées côté serveur dans `validate_weekend_2027_payload()`)

- Au moins une course doit être choisie.
- **Ultra Lozère** est exclusive : elle ne peut pas être combinée avec une
  autre course. Si choisie, la formule (solo/duo) est obligatoire ; en
  duo, le coéquipier doit être choisi parmi les adhérents. L'option
  bivouac n'existe que pour l'Ultra.
- Une seule course du **dimanche** peut être choisie (2 Rivières, Lozère
  Trail ou Salta Bartas), éventuellement combinée avec la **Skyrace** du
  samedi.
- Taille de maillot, contact d'urgence complet, choix d'hébergement et
  numéro de licence/PPS sont toujours obligatoires.

## Administration

- **Liste des inscrits** : grille façon trombinoscope, visible par tout
  adhérent connecté. Avatar, nom, prénom et badges des courses choisies
  pour tout le monde ; les infos pratiques (formule solo/duo, bivouac,
  taille de maillot, hébergement) ne s'affichent que pour les admins.
- **Export CSV** (bouton « Exporter en CSV ») : une ligne par adhérent,
  avec ses coordonnées (nom, prénom, téléphone, email) **et** tous les
  champs du formulaire, même ceux non applicables (colonnes toujours
  présentes). Pas de dépendance XLSX ajoutée au projet — le fichier CSV
  s'ouvre directement dans Excel (BOM UTF-8 inclus pour les accents).
- **Clôture manuelle** : un admin peut clôturer les préinscriptions à
  tout moment (bouton bascule). Une fois closes, le formulaire devient en
  lecture seule pour les adhérents (leur préinscription reste visible,
  non modifiable) ; les adhérents sans préinscription voient un message
  "closes". Pas de date limite codée en dur.

## Évolutions

- **2026-09-18** : numéro de PPS/licence et contact d'urgence rendus
  facultatifs (plus obligatoires ni côté formulaire, ni côté validation
  serveur). Ajout d'un bouton « Voir les inscrits » sur le formulaire
  adhérent, visible uniquement pour les admins, vers la page admin.
  Regroupement visuel : Ultra Lozère et les autres courses sont
  maintenant dans un seul encadré (`border rounded`) avec une séparation
  interne, au lieu de deux encadrés distincts. Bouton « Retour au
  formulaire » ajouté sur la page admin.
- **2026-09-18 (suite)** : le formulaire adhérent est maintenant scindé
  en deux `card` distinctes avec `card-header` (« Votre course » et
  « Vos informations »), au lieu d'un seul bloc avec des sous-titres
  `h2`. Bouton d'envoi sorti de la card « Vos informations » (il
  concerne l'ensemble du formulaire). La liste des coéquipiers possibles
  pour le duo Ultra Lozère est restreinte aux adhérents d'une saison
  active (réutilise `get_active_member_ids()` de `app/active_years.php`),
  excluant les comptes génériques — la validation serveur applique la
  même règle.
- **2026-09-18 (suite)** : la case « Ultra Lozère » se désactive
  maintenant côté client dès qu'une autre course est sélectionnée
  (Skyrace, ou une course du dimanche différente de « Aucune »), et
  inversement (comportement déjà existant). Purement une aide visuelle :
  la règle d'exclusivité de l'Ultra reste de toute façon appliquée côté
  serveur dans `validate_weekend_2027_payload()`.
- **2026-09-18 (suite)** : la liste des inscrits (admin) utilise des
  cards plus petites (`col-6 col-md-3 col-lg-2` au lieu de
  `col-12 col-md-6 col-lg-4`, plus par ligne). Téléphone et email ne
  sont plus affichés sur les cards (juste nom, prénom, courses, taille
  de maillot, hébergement) — ils restent présents dans l'export CSV.
- **2026-09-18 (suite)** : le contact d'urgence n'a plus qu'un seul
  champ texte « Nom et prénom » (`emergency_contact_name`) au lieu de
  deux champs nom/prénom séparés. Colonne DB fusionnée en conséquence
  (`emergency_contact_last_name` + `emergency_contact_first_name` →
  `emergency_contact_name VARCHAR(200)`).
- **2026-09-18 (suite)** : sur les cards admin, badge de course avec
  picto `bi-signpost-2`, et icône de taille de maillot selon le genre
  de l'adhérent (`bi-person-standing` / `bi-person-standing-dress`,
  via `m.gender` désormais récupéré par `get_all_weekend_2027_registrations()`).
  Libellé « Avec Ultramical86 » (au lieu de « Avec le groupe ») dans
  l'admin et le CSV pour l'hébergement groupé — le formulaire adhérent
  garde son libellé complet inchangé.
- **2026-09-18 (suite)** : bouton « Supprimer ma préinscription » sur le
  formulaire adhérent (visible seulement si une préinscription existe et
  que ce n'est pas clos), avec confirmation JS. Suppression réelle en
  base (`DELETE`, cascade sur les courses associées via la contrainte FK)
  et non un simple changement de statut — `delete_weekend_2027_registration()`
  dans `app/weekend_club_2027.php`.
- **2026-09-18 (suite)** : verrouillage automatique du coéquipier de duo.
  Si un adhérent A choisit un adhérent B comme coéquipier pour l'Ultra
  Lozère en duo, et que B se préinscrit à son tour, le choix de course
  de B est automatiquement forcé sur ce même duo (Ultra, duo, partenaire
  = A, même option bivouac) et non modifiable par B — seules ses
  informations personnelles (taille de maillot, hébergement, contact
  d'urgence, licence) restent éditables. « Le premier inscrit décide » :
  A garde la main sur le choix tant qu'il ne change pas lui-même de
  coéquipier. Un même coéquipier ne peut pas être réservé par deux
  adhérents différents (erreur de validation si déjà pris).
  Nouvelles fonctions : `get_weekend_2027_duo_leader_for_member()` (app/weekend_club_2027.php)
  et verrouillage appliqué côté contrôleur
  (`controllers/weekend-2027/weekend-club-2027.php`, les champs de course
  postés par le coéquipier verrouillé sont ignorés et remplacés par ceux
  du leader avant validation/sauvegarde — jamais fait confiance au
  client).
- **2026-09-18 (suite)** : email de confirmation envoyé à l'adhérent à
  chaque enregistrement de sa préinscription (création **et**
  modification, sujet adapté selon le cas), avec récapitulatif complet
  (course(s), formule solo/duo + coéquipier, bivouac, taille de maillot,
  hébergement, contact d'urgence, licence/PPS) et un lien vers le
  formulaire. Nouvelle fonction `send_weekend_2027_confirmation_email()`
  dans `app/email.php` (suit le même modèle que
  `send_password_reset_email()`), appelée depuis
  `send_weekend_2027_registration_confirmation_email()` /
  `save_weekend_2027_registration()` dans `app/weekend_club_2027.php`.
  Un échec d'envoi n'annule jamais l'enregistrement (déjà en base à ce
  stade) ; ignoré silencieusement si l'adhérent n'a pas d'email connu.
- **2026-09-18 (suite)** : email également envoyé au/à la coéquipier·e de
  duo, dans les deux sens, à chaque création/modification par l'un des
  deux membres (pas seulement à la création) : `send_weekend_2027_duo_partner_notification_email()`
  dans `app/email.php`. Le wording (« vous avez été inscrit·e » vs
  « votre coéquipier·e a mis à jour... ») est déterminé en vérifiant si
  le/la coéquipier·e a, de son côté, déjà une préinscription pointant
  vers moi (`$partnerAlreadyKnowsDuo` dans
  `send_weekend_2027_registration_confirmation_email()`), pas en se
  basant sur mon propre historique — sinon la complétion d'infos par le
  partenaire verrouillé renvoyait à tort un message "vous avez été
  inscrit·e" au leader qui le savait déjà.
- **2026-09-18 (suite)** : gestion de la suppression d'une préinscription
  en duo (`delete_weekend_2027_registration()`) — question posée : que
  devient l'autre membre du duo ? Règle retenue, cohérente avec « le
  premier inscrit décide » :
  - Si c'est le **leader** (premier inscrit du duo) qui supprime : le
    choix de course de son/sa coéquipier·e (qui dépendait du sien) est
    remis à zéro (`team_mode`/`duo_partner_member_id`/`bivouac` remis à
    NULL/0, ses courses supprimées) — ses infos personnelles (taille de
    maillot, hébergement, contact d'urgence...) restent intactes, il/elle
    doit juste refaire un choix de course.
  - Si c'est le **coéquipier·e verrouillé·e** (inscrit·e après) qui
    supprime : la préinscription du leader n'est **pas** modifiée — il/elle
    reste seul·e maître de son choix et devra le mettre à jour lui/elle-même
    s'il/elle le souhaite (rester en solo, changer de coéquipier·e...).
  - Dans les deux cas, l'autre membre du duo est notifié par email
    (`send_weekend_2027_duo_cancellation_email()` /
    `send_weekend_2027_duo_cancellation_notification_email()`), avec un
    message adapté selon que son choix a été remis à zéro ou non. Le
    leader/suiveur est déterminé en comparant les `created_at` des deux
    préinscriptions.
- **2026-09-18 (suite)** : les emails de cette fonctionnalité (confirmation,
  notification au coéquipier·e, annulation) tutoient désormais l'adhérent
  (« tu », « ta préinscription », « ton duo »...) au lieu du vouvoiement,
  conformément à l'usage de l'association. Scope volontairement limité aux
  emails du week-end club 2027 — l'email de réinitialisation de mot de
  passe (`send_password_reset_email()`), générique au site, n'a pas été
  touché.
- **2026-09-18 (suite)** : correction du nom de l'association partout où
  il apparaissait tronqué en « Ultramical » au lieu de « Ultramical86 »
  (`<title>` de la page adhérent et les 5 mentions dans les 3 emails de
  `app/email.php`). L'admin et le CSV utilisaient déjà le bon nom.
- **2026-09-18 (suite)** : couleur du texte des boutons dans les 3 emails
  de cette fonctionnalité corrigée en ajoutant `style="color:#ffffff !important;"`
  directement sur le `<a class="button">` (la couleur blanche définie
  uniquement via la classe CSS dans `<style>` n'était pas fiable selon les
  clients mail). L'email de réinitialisation de mot de passe, qui
  fonctionnait déjà bien, n'a pas été modifié.
- **2026-09-18 (suite)** : ajout d'un email de confirmation à la personne
  qui supprime elle-même sa préinscription (jusque-là, seule la notif au
  coéquipier de duo, quand applicable, était envoyée — l'auteur de la
  suppression ne recevait rien). Nouvelle fonction
  `send_weekend_2027_deletion_email()` / `send_weekend_2027_deletion_confirmation_email()`.
- **2026-09-18 (suite)** : objet des 4 emails de cette fonctionnalité
  (confirmation/màj, annulation, notification coéquipier, annulation
  côté coéquipier) préfixé par `[WE club UA86- Lozère Trail]`, et
  suffixe « — Week-end club Lozère Trail » retiré de l'objet (gardé
  uniquement comme titre `<h2>` à l'intérieur du corps du mail, via une
  variable `$heading` désormais distincte de `$subject` pour éviter que
  le préfixe ne s'y répète).
- **2026-09-18 (suite)** : dans le récapitulatif des emails, plusieurs
  courses (ex : Skyrace + une course du dimanche) sont désormais jointes
  par « + » plutôt qu'une virgule (`app/weekend_club_2027.php`). Le CSV
  export (admin) garde la virgule, inchangé.
- **2026-09-18 (suite)** : le numéro de téléphone du contact d'urgence
  n'apparaît plus en lien cliquable dans l'email de confirmation. Il
  n'était pas un vrai `<a>` côté code (texte brut échappé) — le lien
  venait de la détection automatique des numéros par certains clients
  mail (Apple Mail notamment). Ajout de
  `<meta name="format-detection" content="telephone=no">` dans le
  `<head>` de cet email pour la désactiver.
- **2026-09-18 (suite)** : la balise `format-detection` seule ne
  suffisait pas (elle n'est respectée que par les clients Apple/WebKit —
  toujours actif sur Gmail, Outlook...). Ajout d'une obfuscation plus
  robuste : `weekend_2027_break_phone_autolink()` insère un espace de
  largeur nulle (`\u{200B}`, invisible) entre chaque chiffre du numéro
  du contact d'urgence avant de l'afficher dans l'email — visuellement
  identique, mais casse la détection automatique quel que soit le
  client. Le numéro stocké en base n'est pas affecté, seul l'affichage
  dans l'email l'est.
- **2026-09-18 (suite)** : la page « liste des inscrits » est renommée
  et ouverte à tout le monde. Route `/week-end-club-2027-admin` →
  `/week-end-club-2027-inscrits` (fichiers renommés en conséquence :
  `weekend-club-2027-inscrits.php`/`.twig`), niveau d'accès passé de
  `admin` à `connected` par défaut dans `page_access_level()`
  (`app/auth.php`). Le bouton « Voir les inscrits » sur le formulaire
  adhérent n'est plus réservé aux admins. En contrepartie, l'export CSV
  et la clôture/réouverture restent strictement réservés aux admins,
  vérifié **côté serveur** dans le contrôleur (`is_admin()` gate sur le
  bloc export et sur l'action `toggle_closed`, pas seulement caché dans
  le twig) — un non-admin qui force `?export=csv` reçoit la page HTML
  normale, pas le fichier. Les cards n'affichent les infos
  complémentaires (formule, bivouac, maillot, hébergement) que pour les
  admins ; tout le monde voit avatar + nom + prénom + course(s).

- **2026-09-19** : le bouton d'envoi/mise à jour de la préinscription
  (`weekend-club-2027.twig`) est maintenant masqué quand les
  préinscriptions sont closes, comme le bouton « Supprimer ma
  préinscription ». Avant, le bouton restait affiché mais désactivé
  (juste grisé par l'attribut `disabled` du `<fieldset>` englobant),
  ce qui prêtait à confusion.

_À compléter au fil des prochains ajustements sur cette page._
