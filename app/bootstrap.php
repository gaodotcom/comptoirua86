<?php

declare(strict_types=1);

/**
 * Bootstrap — initialisation globale de l'application
 *
 * Charge la configuration, démarre la session, fournit la connexion PDO
 * et les fonctions utilitaires transversales (échappement, URL, redirections,
 * flash messages, tokens CSRF).
 *
 * Ce fichier doit être require en premier par index.php.
 */

/**
 * Récupère la configuration de l'application (tableau issu de config.php).
 * Le résultat est mis en cache statiquement.
 *
 * @return array Configuration de l'application
 */
function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = include __DIR__ . '/config.php';
    }

    return $config;
}

// Initialisation du fuseau horaire et de la session
$config = app_config();
date_default_timezone_set($config['timezone']);

// Détecte si la requête courante est servie en HTTPS.
// Prend en compte les reverse-proxies (Ionos) qui positionnent X-Forwarded-Proto.
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// Démarrage de session avec des cookies durcis (HttpOnly + SameSite, Secure en HTTPS).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => is_https(),
    ]);
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

// En-têtes de défense en profondeur : protègent contre le clickjacking
// (formulaires admin embarqués dans une iframe tierce), le MIME-sniffing,
// et forcent HTTPS une fois qu'on sait que la requête l'utilise déjà.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Gestionnaire global d'erreurs : sans lui, une exception non interceptée
// (ex: PDOException) affiche sa stack trace complète (chemins serveur,
// requête SQL) aux visiteurs si display_errors est actif côté hébergeur.
// Le détail reste journalisé côté serveur via error_log().
set_exception_handler(function (Throwable $e): void {
    error_log('Exception non interceptée : ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (function_exists('twig_render')) {
        twig_render('pages/error-500.twig', ['title' => 'Erreur']);
    } else {
        echo 'Une erreur est survenue. Merci de réessayer plus tard.';
    }
});

/**
 * Retourne l'instance PDO partagée (singleton).
 * Configure le mode d'erreur en exception et le fetch associatif par défaut.
 *
 * @return PDO Instance PDO partagée
 */
function app_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['pass'],
        [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

/**
 * Échappe une valeur pour l'affichage HTML.
 *
 * @param mixed $value Valeur à échapper
 *
 * @return string Valeur échappée
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Construit une URL absolue à partir de la base_url configurée.
 *
 * @param string $path Chemin relatif (ex: '/events', '/members/profile')
 *
 * @return string URL absolue
 */
function base_url(string $path = ''): string
{
    $base = app_config()['base_url'];

    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    if ($base === '') {
        return $path;
    }

    return $base . (str_starts_with($path, '/') ? $path : '/' . $path);
}

/**
 * Construit l'URL web d'un asset (image, CSS, JS...).
 *
 * Ajoute automatiquement un paramètre de version basé sur la date de
 * modification du fichier (ex: ?v=1758193200). Ainsi, à chaque déploiement
 * d'un fichier CSS/JS modifié, l'URL change d'elle-même et le navigateur va
 * forcément rechercher la nouvelle version — plus besoin de vider son cache
 * manuellement (Ctrl+Maj+R) après une mise à jour, pour personne.
 *
 * @param string $path Chemin relatif de l'asset (ex: '/public/css/theme.css')
 *
 * @return string URL web absolue de l'asset, versionnée si le fichier existe
 */
function asset_url(string $path): string
{
    $relative = ltrim($path, '/');
    $url = base_url('/' . $relative);

    $fsPath = __DIR__ . '/../' . $relative;

    if (is_file($fsPath)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($fsPath);
    }

    return $url;
}

/**
 * Redirige vers une page interne et termine l'exécution.
 *
 * @param string $page   Nom de la route (ex: 'home', 'profile')
 * @param array  $params Paramètres de query string additionnels
 *
 * @return never
 */
function redirect_to(string $page, array $params = []): never
{
    $url = page_url($page, $params);
    header('Location: ' . $url);
    exit;
}

/**
 * Enregistre un message flash (affiché une fois sur la prochaine page).
 *
 * @param string $type    Type de message ('success', 'danger', 'warning', 'info')
 * @param string $message Contenu du message
 *
 * @return void
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Récupère et efface tous les messages flash en attente.
 *
 * @return array Liste des messages flash
 */
function pull_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

/**
 * Génère ou récupère le token CSRF de la session courante.
 *
 * @return string Token CSRF
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // 24 octets aléatoires = 48 caractères hexadécimaux, à stocker en session.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Vérifie la validité d'un token CSRF soumis par formulaire.
 *
 * @param string|null $token Token soumis par le formulaire
 *
 * @return bool True si le token est valide
 */
function verify_csrf_token(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    // Comparaison en temps constant pour limiter les attaques par timing.
    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

/**
 * Vérifie le token CSRF soumis en POST ; en cas d'échec, pose un flash
 * "Session expirée" et redirige (sans retour) vers la page donnée.
 * Factorise le bloc dupliqué dans la quasi-totalité des contrôleurs POST.
 *
 * @param string $page   Page vers laquelle rediriger en cas d'échec
 * @param array  $params Paramètres de redirection (ex: ['id' => $id])
 *
 * @return void
 */
function require_valid_csrf(string $page, array $params = []): void
{
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to($page, $params);
    }
}
