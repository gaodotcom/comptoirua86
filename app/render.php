<?php

declare(strict_types=1);

/**
 * Render — résolution des pages et rendu via Twig
 *
 * Le routeur index.php appelle render_page() qui charge le fichier
 * contrôleur PHP (logique POST, accès, préparation des données).
 * Le contrôleur appelle twig_render() qui affiche le template Twig.
 */

/**
 * Mapping des routes vers leur sous-dossier de vues.
 *
 * @param string $pageName Nom de la route
 *
 * @return string Sous-dossier de la page, ou chaîne vide si à la racine
 */
function page_subdir(string $pageName): string
{
    $mapping = [
        'login' => 'auth',
        'forgot-password' => 'auth',
        'reset-password' => 'auth',
        'change-password' => 'auth',
        'trombinoscope' => 'members',
        'profile' => 'members',
        'member-edit' => 'members',
        'member-add' => 'members',
        'helloasso-import' => 'members',
        'helloasso-campaigns' => 'members',
        'active-years' => 'members',
        'inactive-members' => 'members',
        'season-members' => 'members',
        'admin-guide' => '',
        'trainings' => 'trainings',
        'training-form' => 'trainings',
        'training-edit' => 'trainings',
        'event-form' => 'events',
        'events' => 'events',
        'news' => 'news',
        'news-form' => 'news',
        'races' => 'races',
        'race-form' => 'races',
    ];

    return $mapping[$pageName] ?? '';
}

/**
 * Résout le chemin du fichier contrôleur PHP d'une page.
 *
 * @param string $pageName Nom de la route
 *
 * @return string Chemin complet du fichier PHP à inclure
 */
function resolve_page_path(string $pageName): string
{
    $subdir = page_subdir($pageName);
    $pagesDir = __DIR__ . '/../controllers';

    // On cherche d'abord dans le sous-dossier dédié (ex: controllers/members/profile.php).
    if ($subdir !== '') {
        $path = $pagesDir . '/' . $subdir . '/' . $pageName . '.php';
        if (file_exists($path)) {
            return $path;
        }
    }

    // Sinon à la racine des contrôleurs (ex: controllers/home.php).
    $path = $pagesDir . '/' . $pageName . '.php';
    if (file_exists($path)) {
        return $path;
    }

    // Aucun contrôleur trouvé : on tombe sur la page d'erreur 404.
    return $pagesDir . '/error.php';
}

/**
 * Rend une page : charge le contrôleur PHP qui appelle twig_render().
 *
 * @param string $pageName Nom de la route (ex: 'home', 'profile')
 *
 * @return void
 */
function render_page(string $pageName): void
{
    // Mémorise la page courante pour la navbar (classe active) et Twig.
    $GLOBALS['_current_page'] = $pageName;

    $config = app_config();
    $user = current_user();
    $page = $pageName;

    // Inclusion du contrôleur : il exécute la logique POST/accès puis appelle twig_render().
    $pagePath = resolve_page_path($pageName);
    include $pagePath;
}
