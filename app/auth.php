<?php

declare(strict_types=1);

/**
 * Auth — authentification, session, permissions
 *
 * Gère le cycle de vie de l'utilisateur connecté : login, logout,
 * récupération de l'utilisateur courant, vérification des permissions,
 * changement de mot de passe.
 */

/**
 * Récupère l'utilisateur connecté depuis la session.
 * Le résultat est mis en cache ; passer true à $refresh pour forcer le rechargement.
 *
 * @return array|null Données du membre ou null
 */
function current_user(bool $refresh = false): ?array
{
    static $cached = false;
    static $user = null;

    if ($refresh) {
        $cached = false;
        $user = null;
    }

    if ($cached) {
        return $user;
    }

    $cached = true;
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId === null) {
        return null;
    }

    $stmt = app_pdo()->prepare('SELECT * FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => (int) $userId]);
    $result = $stmt->fetch();

    if ($result === false) {
        unset($_SESSION['user_id']);
        return null;
    }

    $user = $result;

    return $user;
}

/**
 * Connecte un utilisateur (régénère l'ID de session, stocke l'ID en session).
 *
 * @return void
 */
function login_user(int $userId, bool $mustSetPassword): void
{
    // Régénération de l'ID de session pour éviter le fixation de session lors du login.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['must_set_password'] = $mustSetPassword;
    // Recharge le cache current_user avec les nouvelles données.
    current_user(true);
}

/**
 * Déconnecte l'utilisateur courant et détruit la session.
 *
 * @return void
 */
function logout_user(): void
{
    clear_remember_me_token();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

/* ============================================================================
   Connexion mémorisée ("Rester connecté")
   ============================================================================ */

/** Nom du cookie de connexion mémorisée. */
const REMEMBER_COOKIE_NAME = 'remember_me';

/** Durée de vie glissante du "Rester connecté" (en secondes) : 90 jours. */
const REMEMBER_TOKEN_LIFETIME = 90 * 24 * 60 * 60;

/**
 * Crée un nouveau token "Rester connecté" pour un membre : enregistre en
 * base le sélecteur (non secret, sert de clé de recherche) et le hash du
 * validateur (jamais le secret en clair), et pose le cookie correspondant.
 * Utilisée à la connexion (si la case est cochée) et à chaque renouvellement
 * glissant, cf. attempt_remember_me_login().
 *
 * @param int $memberId Identifiant de l'adhérent
 *
 * @return void
 */
function issue_remember_me_token(int $memberId): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_TOKEN_LIFETIME);

    $stmt = app_pdo()->prepare(
        'INSERT INTO remember_tokens (member_id, selector, validator_hash, expires_at)
         VALUES (:member_id, :selector, :validator_hash, :expires_at)'
    );
    $stmt->execute([
        'member_id' => $memberId,
        'selector' => $selector,
        'validator_hash' => hash('sha256', $validator),
        'expires_at' => $expiresAt,
    ]);

    setcookie(REMEMBER_COOKIE_NAME, $selector . ':' . $validator, [
        'expires' => time() + REMEMBER_TOKEN_LIFETIME,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Supprime le token "Rester connecté" courant (base + cookie), à la déconnexion.
 *
 * @return void
 */
function clear_remember_me_token(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';

    if (is_string($cookie) && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        app_pdo()->prepare('DELETE FROM remember_tokens WHERE selector = :selector')
            ->execute(['selector' => $selector]);
    }

    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

/**
 * Reconnecte automatiquement un membre via son cookie "Rester connecté" si
 * aucune session active n'existe. À appeler une seule fois, tôt dans le
 * routeur, avant le premier appel à current_user().
 *
 * Fait glisser l'expiration à chaque utilisation : l'ancien sélecteur est
 * consommé (supprimé) et un nouveau token est émis, ce qui prolonge la
 * fenêtre de 90 jours tant que le site est utilisé régulièrement. Si le
 * sélecteur est connu mais le validateur ne correspond pas (rejeu possible
 * d'un cookie déjà consommé/volé), tous les tokens du membre sont invalidés
 * par précaution.
 *
 * @return void
 */
function attempt_remember_me_login(): void
{
    if (!empty($_SESSION['user_id'])) {
        return;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? null;

    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);

    $stmt = app_pdo()->prepare(
        'SELECT rt.id, rt.member_id, rt.validator_hash, m.password_hash
         FROM remember_tokens rt
         INNER JOIN members m ON m.id = rt.member_id
         WHERE rt.selector = :selector AND rt.expires_at > NOW() AND m.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['selector' => $selector]);
    $token = $stmt->fetch();

    if ($token === false) {
        clear_remember_me_token();
        return;
    }

    if (!hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
        app_pdo()->prepare('DELETE FROM remember_tokens WHERE member_id = :member_id')
            ->execute(['member_id' => $token['member_id']]);
        clear_remember_me_token();
        return;
    }

    app_pdo()->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $token['id']]);

    $memberId = (int) $token['member_id'];
    login_user($memberId, empty($token['password_hash']));
    issue_remember_me_token($memberId);
}

/**
 * Recherche un membre par nom d'utilisateur ou email (non supprimé).
 * Utilisé par attempt_login().
 *
 * @return array|null Données du membre ou null
 */
function find_member_by_identifier(string $identifier): ?array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return null;
    }

    // Recherche insensible à la casse sur le username OU l'email.
    $stmt = app_pdo()->prepare(
        'SELECT *
         FROM members
         WHERE deleted_at IS NULL
           AND (LOWER(username) = LOWER(:username_identifier)
            OR LOWER(email) = LOWER(:email_identifier))
         LIMIT 1'
    );
    $stmt->execute(
        [
        'username_identifier' => $identifier,
        'email_identifier' => $identifier,
        ]
    );

    $member = $stmt->fetch();

    return $member === false ? null : $member;
}

/**
 * Vérifie le mot de passe par défaut basé sur la date de naissance.
 * Formats acceptés : dmY, d/m/Y, Y-m-d.
 *
 * @return bool
 */
function verify_default_birth_password(array $member, string $password): bool
{
    $birthDate = $member['date_of_birth'] ?? null;

    if ($birthDate === null || $birthDate === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $birthDate);

    if ($date === false) {
        return false;
    }

    $candidate = trim($password);
    $acceptedFormats = [
        $date->format('dmY'),
        $date->format('d/m/Y'),
        $date->format('Y-m-d'),
    ];

    return in_array($candidate, $acceptedFormats, true);
}

/**
 * Tente de connecter un utilisateur.
 * Si le compte n'a pas de mot de passe défini, utilise la date de naissance
 * comme mot de passe par défaut et marque le compte comme devant le changer.
 *
 * @return bool
 */
function attempt_login(string $identifier, string $password): bool
{
    $member = find_member_by_identifier($identifier);

    if ($member === null) {
        return false;
    }

    $hash = $member['password_hash'] ?? '';
    $mustSetPassword = false;
    $isValid = false;

    // Deux stratégies de mot de passe : si un hash est défini en base, on s'y réfère ;
    // sinon, on tente la date de naissance comme mot de passe par défaut (première connexion).
    if (is_string($hash) && $hash !== '') {
        $isValid = password_verify($password, $hash);
    } else {
        $isValid = verify_default_birth_password($member, $password);
        // Connexion via date de naissance : on forcera la définition d'un mot de passe personnel.
        $mustSetPassword = $isValid;
    }

    if (!$isValid) {
        return false;
    }

    login_user((int) $member['id'], $mustSetPassword);

    return true;
}

/**
 * Vérifie qu'un utilisateur est connecté, sinon redirige vers login.
 * Vérifie également que l'utilisateur connecté est toujours actif
 * (adhésion dans une année active pour les adhérents). Un inactif est
 * déconnecté et redirigé vers login avec un message.
 *
 * @return void
 */
function require_login(): void
{
    $user = current_user();

    if ($user === null) {
        set_flash('warning', 'Merci de vous connecter pour accéder a cette page.');
        $target = safe_internal_redirect_path($_SERVER['REQUEST_URI'] ?? null);
        redirect_to('login', $target !== null ? ['redirect' => $target] : []);
    }

    // Les pages change-password et logout ne sont pas bloquées par l'inactivité.
    $page = $GLOBALS['_current_page'] ?? '';
    if (in_array($page, ['change-password', 'logout'], true)) {
        return;
    }

    if (!is_member_active((int) $user['id'])) {
        // Déconnecte sans détruire la session (pour préserver le flash message).
        unset($_SESSION['user_id'], $_SESSION['must_set_password']);
        current_user(true);
        set_flash('danger', 'Votre adhésion n\'est plus active. Merci de contacter le bureau pour renouveler.');
        redirect_to('login');
    }
}

/**
 * Vérifie si l'utilisateur courant est administrateur.
 *
 * @return bool
 */
function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Vérifie si l'utilisateur courant peut gérer les entraînements (coach ou admin).
 *
 * @return bool
 */
function can_manage_trainings(): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    return in_array($user['role'], ['coach', 'admin'], true);
}

/**
 * Vérifie si l'utilisateur courant peut voir la liste détaillée des adhérents
 * (membre du bureau ou admin).
 *
 * @return bool
 */
function can_view_detailed_members(): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    return in_array($user['role'], ['bureau', 'admin'], true);
}

/**
 * Niveaux d'accès disponibles pour les pages :
 * - 'guest'     : réservé aux non-connectés (login, mot de passe oublié)
 * - 'connected' : tout utilisateur connecté et actif
 * - 'coach'     : coach ou admin (can_manage_trainings)
 * - 'bureau'    : membre du bureau ou admin (can_view_detailed_members)
 * - 'admin'     : administrateur uniquement
 *
 * Les pages non listées ici retombent sur 'connected' (et donc require_login).
 */

/**
 * Cartographie page → niveau d'accès requis.
 * Source de vérité unique pour les permissions par page.
 *
 * @return string Niveau d'accès : 'guest'|'connected'|'coach'|'bureau'|'admin'
 */
function page_access_level(string $page): string
{
    $map = [
        // Pages réservées aux non-connectés (redirigent vers l'accueil si déjà connecté).
        'login' => 'guest',
        'forgot-password' => 'guest',
        'reset-password' => 'guest',
        // Pages accessibles à tout adhérent connecté et actif.
        'home' => 'connected',
        'trombinoscope' => 'connected',
        'profile' => 'connected',
        'change-password' => 'connected',
        'trainings' => 'connected',
        'news' => 'connected',
        'events' => 'connected',
        'races' => 'connected',
        'race-form' => 'connected',
        'calendar' => 'connected',
        // Pages réservées au coach (ou admin).
        'training-form' => 'coach',
        'training-edit' => 'coach',
        // Pages réservées au bureau (ou admin).
        'inactive-members' => 'bureau',
        // Pages réservées à l'administrateur.
        'admin-guide' => 'admin',
        'event-form' => 'admin',
        'news-form' => 'admin',
        'member-add' => 'admin',
        'member-edit' => 'admin',
        'active-years' => 'admin',
        'helloasso-campaigns' => 'admin',
        'helloasso-import' => 'admin',
        'local-races-import' => 'admin',
    ];

    return $map[$page] ?? 'connected';
}

/**
 * Applique le contrôle d'accès centralisé d'une page :
 * - 'guest'     : redirige vers l'accueil si l'utilisateur est déjà connecté.
 * - les autres  : exige un utilisateur connecté et actif (require_login),
 *                 puis vérifie le rôle requis selon le niveau.
 *
 * À appeler depuis index.php avant le rendu de la page.
 *
 * @param string $page Nom de la route courante
 *
 * @return void
 */
function enforce_page_access(string $page): void
{
    $level = page_access_level($page);

    // Pages réservées aux non-connectés : on renvoie les connectés vers l'accueil.
    if ($level === 'guest') {
        if (current_user() !== null) {
            redirect_to('home');
        }
        return;
    }

    // Toutes les autres pages : utilisateur connecté + actif.
    require_login();

    // Vérification du rôle requis au-delà de la simple connexion.
    if ($level === 'admin' && !is_admin()) {
        set_flash('danger', 'Accès réservé à l\'administrateur.');
        redirect_to('home');
    } elseif ($level === 'bureau' && !can_view_detailed_members()) {
        set_flash('danger', 'Accès réservé.');
        redirect_to('home');
    } elseif ($level === 'coach' && !can_manage_trainings()) {
        set_flash('danger', 'Accès réservé.');
        redirect_to('home');
    }
    // 'connected' : déjà validé par require_login().
}

/**
 * Indique si l'utilisateur courant doit encore définir son mot de passe.
 *
 * @return bool
 */
function must_set_password(): bool
{
    return !empty($_SESSION['must_set_password']);
}

/**
 * Efface le flag "doit définir son mot de passe".
 *
 * @return void
 */
function clear_must_set_password_flag(): void
{
    $_SESSION['must_set_password'] = false;
}

/**
 * Change le mot de passe de l'utilisateur courant.
 * Lève une RuntimeException si le mot de passe fait moins de 8 caractères.
 *
 * @return void
 */
function update_current_user_password(string $password): void
{
    $user = current_user();

    if ($user === null) {
        throw new RuntimeException('Utilisateur non connecte.');
    }

    if (strlen($password) < 8) {
        throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = app_pdo()->prepare('UPDATE members SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute(
        [
        'password_hash' => $hash,
        'id' => (int) $user['id'],
        ]
    );

    // Un mot de passe volé/deviné ne doit plus permettre de rester connecté
    // via un cookie "rester connecté" émis avant le changement.
    app_pdo()->prepare('DELETE FROM remember_tokens WHERE member_id = :member_id')
        ->execute(['member_id' => (int) $user['id']]);

    clear_must_set_password_flag();
    current_user(true);
}

/**
 * Change le mot de passe d'un utilisateur par ID.
 * Utilisé pour la réinitialisation de mot de passe.
 *
 * @param int    $userId   ID du membre
 * @param string $password Nouveau mot de passe (minimum 8 caractères)
 *
 * @return void Lève RuntimeException si erreur
 */
function update_user_password_by_id(int $userId, string $password): void
{
    if (strlen($password) < 8) {
        throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = app_pdo()->prepare('UPDATE members SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute(
        [
        'password_hash' => $hash,
        'id' => $userId,
        ]
    );

    // Un mot de passe volé/deviné ne doit plus permettre de rester connecté
    // via un cookie "rester connecté" émis avant la réinitialisation.
    app_pdo()->prepare('DELETE FROM remember_tokens WHERE member_id = :member_id')
        ->execute(['member_id' => $userId]);
}

/**
 * Demande une réinitialisation de mot de passe pour un email.
 * Génère un token aléatoire, l'enregistre en base (valide 30 min),
 * et envoie un email au destinataire.
 *
 * @param string $email Email du membre
 *
 * @return bool true si email trouvé et email envoyé, false sinon
 */
function request_password_reset(string $email): bool
{
    $email = strtolower(trim($email));

    $stmt = app_pdo()->prepare(
        'SELECT id, first_name, email FROM members WHERE LOWER(email) = :email AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $member = $stmt->fetch();

    if ($member === false) {
        return false;
    }

    // Générer un token aléatoire de 32 bytes (64 caractères hex)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Insérer ou remplacer le token (UNIQUE KEY sur member_id)
    $stmt = app_pdo()->prepare(
        'INSERT INTO password_reset_tokens (member_id, token, expires_at) 
         VALUES (:member_id, :token, :expires_at)
         ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()'
    );
    $stmt->execute(
        [
        'member_id' => (int) $member['id'],
        'token' => $token,
        'expires_at' => $expiresAt,
        ]
    );

    // Construire l'URL de réinitialisation
    $config = app_config();
    $baseUrl = $config['base_url'] ?? 'http://localhost:8080';
    $resetUrl = $baseUrl . '/reset-password?token=' . $token;

    // Envoyer l'email
    $sent = send_password_reset_email(
        $member['email'],
        $member['first_name'],
        $resetUrl
    );

    return $sent;
}

/**
 * Vérifie si un "bucket" (ex: "login:jdupont", "password-reset:x@y.fr") a
 * dépassé le nombre de tentatives autorisées sur la fenêtre de temps donnée.
 * Protection anti brute-force sur la connexion et le mot de passe oublié.
 *
 * @return bool true si la limite est dépassée (la tentative doit être refusée)
 */
function rate_limit_exceeded(string $bucket, int $maxAttempts, int $windowSeconds): bool
{
    // La fenêtre est calculée côté MySQL (DATE_SUB(NOW(), ...)) plutôt qu'en
    // PHP : l'horloge/fuseau du serveur PHP peut diverger de celle de la
    // base, ce qui rendrait le calcul de "since" en PHP peu fiable.
    $stmt = app_pdo()->prepare(
        'SELECT COUNT(*) FROM rate_limit_attempts
         WHERE bucket = :bucket AND created_at > DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)'
    );
    $stmt->bindValue('bucket', $bucket, PDO::PARAM_STR);
    $stmt->bindValue('window_seconds', $windowSeconds, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() >= $maxAttempts;
}

/**
 * Enregistre une tentative pour un bucket (anti brute-force). Purge
 * occasionnellement (1 fois sur 50) les tentatives de plus d'un jour pour
 * ne pas faire grossir la table indéfiniment.
 *
 * @return void
 */
function record_rate_limit_attempt(string $bucket): void
{
    app_pdo()->prepare('INSERT INTO rate_limit_attempts (bucket) VALUES (:bucket)')
        ->execute(['bucket' => $bucket]);

    if (random_int(1, 50) === 1) {
        app_pdo()->query('DELETE FROM rate_limit_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}

/**
 * Vérifie qu'un token de réinitialisation est valide (exists, non expiré).
 *
 * @param string $token Token à vérifier
 *
 * @return int|null ID du membre si token valide, null sinon
 */
function verify_password_reset_token(string $token): ?int
{
    $token = trim($token);

    // Le token fait 64 caractères hex (32 octets aléatoires) : on rejette les longueurs anormales tôt.
    if ($token === '' || strlen($token) !== 64) {
        return null;
    }

    $stmt = app_pdo()->prepare(
        'SELECT member_id FROM password_reset_tokens 
         WHERE token = :token 
           AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    return $row !== false ? (int) $row['member_id'] : null;
}

/**
 * Applique la réinitialisation du mot de passe (token + nouveau mot de passe).
 * Supprime le token après succès.
 *
 * @param string $token    Token valide
 * @param string $password Nouveau mot de passe
 *
 * @return bool true si succès, false si token invalide
 */
function apply_password_reset(string $token, string $password): bool
{
    $userId = verify_password_reset_token($token);

    if ($userId === null) {
        return false;
    }

    try {
        update_user_password_by_id($userId, $password);
    } catch (Throwable $e) {
        throw $e;
    }

    // Supprimer le token
    $stmt = app_pdo()->prepare('DELETE FROM password_reset_tokens WHERE member_id = :member_id');
    $stmt->execute(['member_id' => $userId]);

    return true;
}

/**
 * Met à jour le nom d'utilisateur de l'utilisateur courant.
 * Vérifie l'unicité du nouveau username.
 *
 * @param string $newUsername Nouveau nom d'utilisateur
 *
 * @return void Lève RuntimeException si erreur
 */
function update_current_user_username(string $newUsername): void
{
    $user = current_user();

    if ($user === null) {
        throw new RuntimeException('Utilisateur non connecte.');
    }

    $newUsername = trim($newUsername);

    if ($newUsername === '') {
        throw new RuntimeException('Le nom d\'utilisateur ne peut pas etre vide.');
    }

    if (strlen($newUsername) < 3) {
        throw new RuntimeException('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
    }

    if (strlen($newUsername) > 30) {
        throw new RuntimeException('Le nom d\'utilisateur ne peut pas depasser 30 caractères.');
    }

    // Vérifier l'unicité
    $stmt = app_pdo()->prepare(
        'SELECT id FROM members WHERE LOWER(username) = LOWER(:username) AND id != :id LIMIT 1'
    );
    $stmt->execute(['username' => $newUsername, 'id' => (int) $user['id']]);

    if ($stmt->fetch() !== false) {
        throw new RuntimeException('Ce nom d\'utilisateur est deja pris.');
    }

    // Mettre à jour
    $stmt = app_pdo()->prepare('UPDATE members SET username = :username WHERE id = :id');
    $stmt->execute(['username' => $newUsername, 'id' => (int) $user['id']]);

    current_user(true);
}
