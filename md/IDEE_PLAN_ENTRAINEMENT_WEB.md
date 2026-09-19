# Idée : plan d'entraînement saisi sur le site (au lieu d'une image Excel)

Statut : idée à cadrer avec le coach, pas encore développée.

## Constat actuel

Le coach prépare son plan de la semaine dans Excel (une ligne par jour, une
colonne date + une colonne séance), fait une image du fichier, puis l'upload
sur le site.

Table existante `trainings` (`database/schema.sql`) : une ligne par semaine
(`week_start` UNIQUE), avec `title`, `presentation_text`, `comment_text` et
`image_path` (l'image Excel uploadée). Contrôleurs/vues :
`app/trainings.php`, `controllers/trainings/`, `templates/pages/trainings/`.

## Idée

Remplacer (ou compléter) l'upload d'image par une saisie structurée sur le
site : 7 champs (un par jour) pour la séance du jour. Le site affiche alors
le plan directement en HTML, et propose un bouton pour générer une image à
partir de cet affichage, que le coach peut ensuite envoyer par mail, réseaux
sociaux, etc. — même usage final qu'aujourd'hui, mais sans passer par Excel.

## Faisabilité technique

Question posée : est-ce possible sachant que la PROD tourne sur un
hébergement mutualisé Ionos (capacités serveur limitées/inconnues) ?

Deux approches pour générer l'image :

1. **Côté serveur** (PHP GD, Imagick, binaire type `wkhtmltoimage`...) —
   dépend fortement de ce qui est activé/installable sur le mutualisé Ionos
   (extensions PHP, exécution de binaires externes souvent bloquée ou
   limitée). Voie fragile, à éviter.
2. **Côté navigateur** (JS, ex. `html2canvas` ou `dom-to-image`) — le plan
   est affiché en HTML/CSS classique (tableau 7 lignes), et un bouton
   "Générer l'image" capture ce bloc en PNG directement dans le navigateur
   du coach, qui télécharge ensuite le fichier. **Aucune dépendance
   serveur** : un simple fichier JS statique, sur le modèle des fichiers déjà
   présents dans `public/js/` (ex. `calendar.js`). Fonctionne à l'identique
   en local et en PROD sur Ionos.

**Conclusion : oui, c'est possible, via la génération côté navigateur (voie
2), qui évite complètement les incertitudes liées à l'hébergement
mutualisé.**

Point d'attention pour cette voie : le rendu de l'image dépend du CSS de la
page affichée (soigner la mise en page du bloc capturé — largeur fixe, pas
d'éléments interactifs visibles dans le résultat).

## Prochaine étape

Voir avec le coach ce qui est le plus pratique pour lui (saisie structurée
vs. garder l'upload d'image en option, champs exacts par jour, etc.) avant
de cadrer un plan de développement.
