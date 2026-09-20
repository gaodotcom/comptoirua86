<?php

declare(strict_types=1);

/**
 * Configuration de l'application.
 * Charge les variables depuis le fichier .env puis construit le tableau de
 * configuration retourné à l'ensemble de l'application via app_config().
 */

/**
 * Charge le fichier .env au démarrage (nécessaire sur serveurs mutualisés comme Ionos
 * où les variables d'environnement ne sont pas injectées par le serveur).
 * Le résultat est mis en cache statiquement pour ne relire le fichier qu'une fois.
 *
 * @return array Tableau associatif KEY => VALUE des variables d'environnement
 */
function load_env(): array
{
    static $env = null;

    if ($env !== null) {
        return $env;
    }

    $env = [];
    $envPath = __DIR__ . '/../.env';

    if (file_exists($envPath)) {
        $envContent = file_get_contents($envPath);
        foreach (explode("\n", $envContent) as $line) {
            $line = trim($line);
            // Ignore les lignes vides et les commentaires (commençant par #)
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            // Parse KEY=VALUE (la valeur peut être entourée de guillemets, retirés ensuite)
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, ' "\'');
                if (!empty($key)) {
                    $env[$key] = $value;
                }
            }
        }
    }

    return $env;
}

// Chargement effectif des variables d'environnement.
$_env = load_env();

// Tableau de configuration consolidé : valeurs applicatives, SMTP, base de données,
// et paramètres HelloAsso. Chaque clé retombe sur une valeur par défaut raisonnable.
return [
    'app_name' => $_env['APP_NAME'] ?? 'App',
    // base_url sert à construire les URL absolues (utile quand l'app n'est pas à la racine du domaine).
    'base_url' => rtrim($_env['BASE_URL'] ?? '', '/'),
    'facebook_url' => $_env['FACEBOOK_URL'] ?? '',
    'instagram_url' => $_env['INSTAGRAM_URL'] ?? '',
    'helloasso_url' => $_env['HELLOASSO_URL'] ?? '',
    'web_url' => $_env['WEB_URL'] ?? '',
    'mail_contact' => $_env['MAIL_CONTACT'] ?? '',
    'mail_noreply' => $_env['MAIL_NOREPLY'] ?? '',
    'home_welcome_text' => $_env['HOME_WELCOME_TEXT'] ?? 'Au menu du Comptoir :
        les entraînements du coach, tes projets de course, le trombinoscope des adhérents,
        le calendrier et des infos sur la vie d\'Ultramical86.',
    'smtp' => [
        'host' => $_env['SMTP_HOST'] ?? '',
        'port' => (int) ($_env['SMTP_PORT'] ?? 465),
        'user' => $_env['SMTP_USER'] ?? '',
        'pass' => $_env['SMTP_PASSWORD'] ?? '',
        'secure' => $_env['SMTP_SECURE'] ?? 'ssl', // ssl (465), tls (587), ou vide
    ],
    'timezone' => $_env['APP_TIMEZONE'] ?? 'Europe/Paris',
    // Taille maximale d'upload en octets (0 = aucune limite applicative, on se fie à php.ini).
    'upload_max_size' => (int) ($_env['UPLOAD_MAX_SIZE'] ?? 0),
    // Racine filesystem et racine web des uploads : les chemins stockés en base sont relatifs au web.
    'uploads_fs_root' => __DIR__ . '/../public/uploads',
    'uploads_web_root' => 'public/uploads',
    'db' => [
        'host' => $_env['DB_HOST'] ?? '',
        'port' => (int) ($_env['DB_PORT'] ?? 3306),
        'name' => $_env['DB_NAME'] ?? '',
        'user' => $_env['DB_USER'] ?? '',
        'pass' => $_env['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ],
    'helloasso' => [
        'client_id' => $_env['HELLOASSO_CLIENT_ID'] ?? '',
        'client_secret' => $_env['HELLOASSO_CLIENT_SECRET'] ?? '',
        'org_slug' => $_env['HELLOASSO_ORG_SLUG'] ?? '',
        'api_base' => 'https://api.helloasso.com/v5',
    ],
];
