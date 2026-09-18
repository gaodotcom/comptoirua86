<?php

declare(strict_types=1);

/**
 * Twig — configuration de l'environnement de templates
 *
 * Crée l'instance Twig, enregistre les fonctions PHP accessibles
 * dans les templates, et fournit twig_render() pour le rendu.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Retourne l'instance Twig partagée (singleton).
 *
 * @return \Twig\Environment
 */
function twig_instance(): \Twig\Environment
{
    static $twig = null;

    if ($twig !== null) {
        return $twig;
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $twig = new \Twig\Environment(
        $loader,
        [
        'cache' => false,
        'autoescape' => 'html',
        'strict_variables' => false,
        ]
    );

    // Enregistrer les fonctions PHP utilisables dans les templates Twig
    $functions = [
        // URL & navigation
        'page_url',
        'base_url',
        'asset_url',
        'route_to_slug',
        'slug_to_route',
        // Auth & permissions
        'current_user',
        'is_admin',
        'can_manage_trainings',
        'can_view_detailed_members',
        'must_set_password',
        'role_label',
        'is_feminine',
        'bureau_role_label',
        // Formatting
        'format_date',
        'format_money',
        'format_phone',
        'school_year_options',
        'get_all_school_years',
        'text_to_html',
        // CSRF
        'csrf_token',
        // Avatars
        'render_avatar',
        'race_favicon_url',
        // Week-end club Lozère Trail 2027
        'weekend_2027_courses',
        'weekend_2027_course_label',
        // News count for navbar
        'get_published_news',
        // Events count for navbar
        'get_upcoming_events_count',
    ];

    foreach ($functions as $funcName) {
        $twig->addFunction(new \Twig\TwigFunction($funcName, $funcName));
    }

    // Filtre pour rendre du contenu riche (liens + nl2br)
    $twig->addFilter(new \Twig\TwigFilter('text_to_html', 'text_to_html'));

    // Variable globale : page courante (pour navbar active)
    $twig->addGlobal('current_page', $GLOBALS['_current_page'] ?? '');

    return $twig;
}

/**
 * Rend un template Twig avec les variables communes (config, user, flashes).
 *
 * @param string $template Nom du template (ex: 'pages/home.twig')
 * @param array  $vars     Variables spécifiques à la page
 *
 * @return never
 */
function twig_render(string $template, array $vars = []): never
{
    $twig = twig_instance();

    // Variables communes injectées sur toutes les pages (config, utilisateur, flash).
    $common = [
        'config' => app_config(),
        'user' => current_user(),
        'flashes' => pull_flash_messages(),
    ];

    // Les variables de page priment sur les variables communes en cas de conflit.
    echo $twig->render($template, array_merge($common, $vars));
    exit;
}
