<?php

// phpcs:ignoreFile

declare(strict_types=1);

/**
 * Routeur principal.
 * Aiguille les requêtes vers les pages correspondantes.
 */

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/routes.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/email.php';
require __DIR__ . '/app/members.php';
require __DIR__ . '/app/helloasso.php';
require __DIR__ . '/app/active_years.php';
require __DIR__ . '/app/trainings.php';
require __DIR__ . '/app/events.php';
require __DIR__ . '/app/news.php';
require __DIR__ . '/app/races.php';
require __DIR__ . '/app/calendar.php';
require __DIR__ . '/app/weekend_club_2027.php';
require __DIR__ . '/app/local_races.php';
require __DIR__ . '/app/uploads.php';
require __DIR__ . '/app/helpers.php';
require __DIR__ . '/app/twig.php';
require __DIR__ . '/app/render.php';

ensure_upload_directories();

// Reconnexion automatique via le cookie "Rester connecté" si pas de session
// active : doit s'exécuter avant le premier appel à current_user() ci-dessous.
attempt_remember_me_login();

// Détermine la page depuis l'URL propre (/entrainements) ou via le paramètre ?page=trainings
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$basePath = rtrim(str_replace('/index.php', '', parse_url($_SERVER['SCRIPT_NAME'] ?? '/', PHP_URL_PATH)), '/');
$route = trim(str_replace($basePath, '', $uri), '/');

if ($route === '' || $route === 'index.php') {
    $page = current_user() !== null ? 'home' : 'login';
} else {
    // Tente de résoudre le slug public en nom de route interne.
    $resolved = slug_to_route($route);
    $page = $resolved ?? $route;
}

// Compatibilité avec les anciennes URLs ?page=
if (isset($_GET['page']) && $_GET['page'] !== '') {
    $page = $_GET['page'];
}

// Les noms de route valides sont toujours en un seul segment kebab-case (voir
// page_access_level() et page_subdir()). On rejette tout le reste (ex: "/" ou
// caractères spéciaux) avant le contrôle d'accès : resolve_page_path() retombe
// sinon sur controllers/<page>.php à la racine, ce qui permettrait à
// "members/member-add" de résoudre vers le contrôleur admin
// controllers/members/member-add.php tout en contournant page_access_level()
// (qui ne connaît pas cette clé et retombe sur le niveau 'connected').
if (!preg_match('/^[a-z0-9-]+$/', $page)) {
    http_response_code(404);
    twig_render('pages/error.twig', ['title' => 'Page introuvable']);
}

// Rend la page courante accessible globalement (utilisé par require_login pour exempter change-password/logout)
$GLOBALS['_current_page'] = $page;

// Contrôle d'accès centralisé : redirige les non-connectés vers login,
// les invités déjà connectés vers l'accueil, et vérifie le rôle requis par page.
enforce_page_access($page);

// Redirige vers le changement de mot de passe si nécessaire
if (must_set_password() && !in_array($page, ['change-password', 'logout'], true)) {
    set_flash('warning', 'Vous utilisez encore le mot de passe par date de naissance. Merci de définir un mot de passe personnel.');
    redirect_to('change-password');
}

// Gestion de la déconnexion
if ($page === 'logout') {
    logout_user();
    header('Location: ' . page_url('login'));
    exit;
}

// Rendu de la page
render_page($page);
