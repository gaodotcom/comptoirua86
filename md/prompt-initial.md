Je souhaite créer un espace adhérents pour mon association de course à pied. Espace nécessitant une connexion, soit avec "mot de passe=date de naissance", soit avec la possibilité pour chaque adhérent de créer son mot de passe (nom d'utilisateur = prénom ou adresse email).

Il y aurait 4 rôles :
- adhérent
- coach
- bureau
- administrateur


L'adhérent peut accéder à la liste de tous les adhérents (trombinoscope), photo, nom, prénom.
Il peut ajouter/modifier sa propre photo.
Il peut également accéder aux 3 dernières semaines d'entraînement proposées par le coach.

Le coach, en plus des droits des adhérents, peut ajouter des contenus de type "entraînement". Contenu hebdomadaire, avec les séances proposées chaques semaines : un texte de présentation + une image. Et éventuellement la possibilité d'ajouter un commentaire en cas de précisions complémentaires à ajouter.

Le "bureau" a les mêmes droits que l'adhérent, et peut également accéder à la liste plus détaillée des adhérents :
nom, prénom, adresse, code postal, ville, date de naissance, téléphone, email, inscription ou non au groupe WhatsApp, et un historique des adhésions (tarif + don, adhésion par année scolaire, 1er septembre jusqu'au 31 août), photo

L'administrateur a tou les droits : ceux de l'adhérent, du coach, du bureau.
En plus il peut gérer la liste des adhérents, ajout, modification, suppression

Sur la page d'accueil on pourrait avoir :
- les 3 dernières "semaines d'entraînement" proposées par le coach
- un lien vers le trombinoscope
- les 10 prochains anniversaires des adhérents (donc les anniversaires du jour)
- un lien vers les pages Facebook et Instagram

je souhaite un outil relativement "simple". ça sera hébergé sur un serveur mutualisé.
PHP pur avec MariaDB ? Symfony, j'ai un doute sur la possibilité de l'hébergé en mutualisé ? un CMS type Drupal ?
Un framework CSS type Bootstrap.

Pour le développement en local il faudrait prévoir un container docker...