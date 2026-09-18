<?php

declare(strict_types=1);

/**
 * Fonctions utilitaires transversales.
 * URL & navigation, formatage (dates, montants, téléphone), avatars et
 * transformation de texte brut en HTML sécurisé.
 */

/* ============================================================================
   Aide validation
   ============================================================================ */

/**
 * Valide qu'une chaîne est une date au format ISO AAAA-MM-JJ.
 *
 * @param string $date Date à valider
 *
 * @return bool
 */
function is_iso_date(string $date): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

/**
 * Valide qu'une chaîne est une année scolaire au format AAAA-AAAA
 * avec deux années consécutives.
 *
 * @param string $year Année scolaire à valider
 *
 * @return bool
 */
function is_school_year(string $year): bool
{
    if (preg_match('/^\d{4}-\d{4}$/', $year) !== 1) {
        return false;
    }

    [$start, $end] = array_map('intval', explode('-', $year));

    return $end === $start + 1;
}

/* ============================================================================
   Aide URL & navigation
   ============================================================================ */

/**
 * Construit l'URL d'une page avec d'éventuels paramètres de query string.
 * Utilise des URLs propres (/events au lieu de /index.php?page=events).
 *
 * @param string $page   Nom de la route (ex: 'home', 'profile')
 * @param array  $params Paramètres de query string optionnels
 *
 * @return string URL propre
 */
function page_url(string $page, array $params = []): string
{
    $slug = route_to_slug($page);

    if ($params === []) {
        return base_url('/' . $slug);
    }

    return base_url('/' . $slug . '?' . http_build_query($params));
}

/* ============================================================================
   Aide au formatage
   ============================================================================ */

/**
 * Formate une date du format Y-m-d vers d/m/Y.
 *
 * @return string
 */
function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '-';
    }

    $obj = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if ($obj === false) {
        return $date;
    }

    return $obj->format('d/m/Y');
}

/**
 * Renvoie le libellé du rôle au bureau adapté au genre du membre.
 *
 * @return string
 */
function bureau_role_label(?string $bureauRole, string $gender = ''): string
{
    if ($bureauRole === null || $bureauRole === '') {
        return 'Membre du bureau';
    }

    $labels = [
        'Président(e)' => ['M' => 'Président', 'F' => 'Présidente'],
        'Vice-président(e)' => ['M' => 'Vice-président', 'F' => 'Vice-présidente'],
        'Trésorier(ère)' => ['M' => 'Trésorier', 'F' => 'Trésorière'],
        'Trésorier(ère) adjoint(e)' => ['M' => 'Trésorier adjoint', 'F' => 'Trésorière adjointe'],
        'Secrétaire' => ['M' => 'Secrétaire', 'F' => 'Secrétaire'],
        'Secrétaire adjoint(e)' => ['M' => 'Secrétaire adjoint', 'F' => 'Secrétaire adjointe'],
    ];

    return $labels[$bureauRole][$gender] ?? $bureauRole;
}

/**
 * Génère des options d'années scolaires à partir de la date courante.
 * À partir du 1er août, l'année scolaire courante commence l'année en cours.
 *
 * @return array Liste d'années scolaires au format AAAA-AAAA
 */
function school_year_options(int $count = 5): array
{
    $now = new DateTimeImmutable();
    $month = (int) $now->format('n');
    $year = (int) $now->format('Y');

    // À partir du 1er août, on bascule sur l'année scolaire courante (ex: 2025-2026).
    $startYear = $month >= 8 ? $year : $year - 1;

    $options = [];
    // On remonte $count années scolaires en arrière à partir de l'année courante.
    for ($i = 0; $i < $count; $i++) {
        $y = $startYear - $i;
        $options[] = $y . '-' . ($y + 1);
    }

    return $options;
}

/**
 * Formate un montant avec la devise euro.
 *
 * @return string
 */
function format_money(int|float|string|null $value): string
{
    if ($value === null || $value === '') {
        return '0 €';
    }

    return number_format((float) $value, 0, ',', ' ') . ' €';
}

/**
 * Formate un numéro de téléphone avec un espace tous les 2 caractères.
 *
 * @return string
 */
function format_phone(?string $phone): string
{
    if ($phone === null || $phone === '') {
        return '';
    }

    // Enlever tous les espaces, tirets et points.
    $cleaned = preg_replace('/[\s\-\.]/', '', $phone);

    // Ajouter des espaces tous les 2 caractères.
    return implode(' ', str_split($cleaned, 2));
}

/* ============================================================================
   Aide avatar & profil
   ============================================================================ */

/**
 * Génère un avatar (photo ou initiales).
 *
 * @return string
 */
function render_avatar(array $member, int $size = 96): string
{
    $photoPath = $member['photo_path'] ?? null;

    if (is_string($photoPath) && $photoPath !== '') {
        $url = asset_url($photoPath);
        $style = 'width:' . $size . 'px;height:' . $size . 'px;';
        $class = 'rounded-circle object-fit-cover border';

        return '<img src="' . e($url) . '" alt="Photo" class="' . $class . '" style="' . $style . '">';
    }

    $firstName = (string) ($member['first_name'] ?? '');
    $lastName = (string) ($member['last_name'] ?? '');
    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

    $style = 'width:' . $size . 'px;height:' . $size . 'px;';
    $class = 'avatar-placeholder rounded-circle border d-inline-flex align-items-center justify-content-center';

    return '<div class="' . $class . '" style="' . $style . '">' . e($initials) . '</div>';
}

/**
 * URL d'affichage du favicon d'une course : le fichier enregistré localement
 * s'il existe (stocké une fois pour toutes à la création/modification de la
 * course, voir app/races.php), sinon un repli en direct vers le service
 * DuckDuckGo pour les courses pas encore ré-enregistrées depuis l'ajout de
 * cette fonctionnalité.
 *
 * @param array $race Ligne de la table races (clés 'favicon_path', 'website_url')
 *
 * @return string|null URL du favicon à afficher, ou null si aucune n'est disponible
 */
function race_favicon_url(array $race): ?string
{
    $stored = (string) ($race['favicon_path'] ?? '');

    if ($stored !== '') {
        return asset_url($stored);
    }

    $websiteUrl = (string) ($race['website_url'] ?? '');

    if ($websiteUrl === '') {
        return null;
    }

    $host = parse_url($websiteUrl, PHP_URL_HOST);

    if (!is_string($host) || $host === '') {
        return null;
    }

    return 'https://icons.duckduckgo.com/ip3/' . rawurlencode($host) . '.ico';
}

/* ============================================================================
    Aide texte
   ============================================================================ */

/**
 * Échappe le texte, convertit les URLs et emails en liens cliquables, puis les sauts de ligne en <br>.
 * À utiliser pour afficher du contenu utilisateur riche (actualités, commentaires).
 *
 * @param string $text Texte brut à formater
 *
 * @return string HTML sécurisé avec liens cliquables et sauts de ligne
 */
function text_to_html(string $text): string
{
    $escaped = e($text);

    // Détecter les URLs (http://, https://, www.)
    $pattern = '/(https?:\/\/[^\s<]+|www\.[^\s<]+)/i';
    $escaped = preg_replace_callback(
        $pattern,
        static function (array $matches): string {
            $url = $matches[1];
            $href = str_starts_with($url, 'www.') ? 'https://' . $url : $url;

            return '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($url) . '</a>';
        },
        $escaped
    );

    // Détecter les emails
    $escaped = preg_replace_callback(
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        static function (array $matches): string {
            return '<a href="mailto:' . e($matches[0]) . '">' . e($matches[0]) . '</a>';
        },
        $escaped
    );

    return nl2br($escaped);
}
