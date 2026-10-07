<?php

declare(strict_types=1);

/**
 * Races — CRUD des courses et gestion des réponses des adhérents
 *
 * Les courses sont partagées par n'importe quel adhérent connecté.
 * Les autres adhérents peuvent indiquer qu'ils sont intéressés ou inscrits.
 */

/**
 * Valide les données d'une course et retourne les champs nettoyés.
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function validate_race_payload(array $payload): array
{
    $data = [
        'title' => trim((string) ($payload['title'] ?? '')),
        'start_date' => trim((string) ($payload['start_date'] ?? '')),
        'end_date' => trim((string) ($payload['end_date'] ?? '')),
        'location' => trim((string) ($payload['location'] ?? '')),
        'distances' => trim((string) ($payload['distances'] ?? '')),
        'website_url' => trim((string) ($payload['website_url'] ?? '')),
        'registration_info' => trim((string) ($payload['registration_info'] ?? '')),
        'created_by' => isset($payload['created_by']) ? (int) $payload['created_by'] : null,
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors[] = 'Le nom de la course est obligatoire.';
    }

    if ($data['start_date'] === '' || !is_iso_date($data['start_date'])) {
        $errors[] = 'La date de début est invalide.';
    }

    if ($data['end_date'] !== '' && !is_iso_date($data['end_date'])) {
        $errors[] = 'La date de fin est invalide.';
    }

    if ($data['end_date'] !== '' && $data['end_date'] < $data['start_date']) {
        $errors[] = 'La date de fin ne peut pas être antérieure à la date de début.';
    }

    if ($data['distances'] === '') {
        $errors[] = 'Les distances sont obligatoires.';
    }

    if ($data['website_url'] !== '' && !preg_match('#^https?://#i', $data['website_url'])) {
        $errors[] = 'Le lien doit commencer par http:// ou https://';
    }

    return [$data, $errors];
}

/**
 * Télécharge une ressource distante (page HTML ou icône) avec des garde-fous :
 * http(s) uniquement, timeouts courts, taille maximale, et refus des adresses
 * privées/réservées (y compris après redirection) pour éviter qu'un lien saisi
 * dans le formulaire ne fasse interroger le réseau interne du serveur.
 *
 * @param string $url      URL à télécharger
 * @param int    $maxBytes Taille maximale acceptée du contenu
 *
 * @return array{body: string, url: string}|null Contenu et URL finale (après redirections), ou null
 */
function fetch_remote_resource(string $url, int $maxBytes): ?array
{
    $scheme = parse_url($url, PHP_URL_SCHEME);

    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    // Certains sites refusent les requêtes sans User-Agent.
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Comptoir-UA86 favicon fetcher)');
    // Abandon dès que la taille reçue dépasse le maximum.
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, static function ($ch, $dlTotal, $dlNow) use ($maxBytes): int {
        return $dlNow > $maxBytes ? 1 : 0;
    });

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $ip = (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);

    if ($errno !== 0 || $code >= 400 || !is_string($body) || $body === '' || strlen($body) > $maxBytes) {
        return null;
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return null;
    }

    return ['body' => $body, 'url' => $finalUrl];
}

/**
 * Transforme une URL éventuellement relative (trouvée dans une page) en URL absolue.
 *
 * @param string $href    Valeur de l'attribut href
 * @param string $pageUrl URL absolue de la page qui la contient
 *
 * @return string|null URL absolue, ou null si non résolvable
 */
function resolve_page_relative_url(string $href, string $pageUrl): ?string
{
    $href = trim($href);
    $parts = parse_url($pageUrl);

    if ($href === '' || $parts === false || !isset($parts['scheme'], $parts['host'])) {
        return null;
    }

    if (preg_match('#^https?://#i', $href) === 1) {
        return $href;
    }

    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

    if (str_starts_with($href, '//')) {
        return $parts['scheme'] . ':' . $href;
    }

    if (str_starts_with($href, '/')) {
        return $origin . $href;
    }

    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
        return null; // data:, javascript:, etc.
    }

    $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

    return $origin . $dir . $href;
}

/**
 * Liste, par ordre de préférence, les URLs d'icônes déclarées par une page
 * (<link rel="icon"> d'abord, puis apple-touch-icon).
 *
 * @param string $html    Contenu HTML de la page
 * @param string $pageUrl URL finale de la page (pour résoudre les liens relatifs)
 *
 * @return array<int, string>
 */
function extract_page_icon_urls(string $html, string $pageUrl): array
{
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $icons = [];
    $touchIcons = [];

    foreach ($dom->getElementsByTagName('link') as $link) {
        $rels = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [];
        $url = resolve_page_relative_url($link->getAttribute('href'), $pageUrl);

        if ($url === null) {
            continue;
        }

        if (in_array('icon', $rels, true)) {
            $icons[] = $url;
        } elseif (in_array('apple-touch-icon', $rels, true)) {
            $touchIcons[] = $url;
        }
    }

    return array_values(array_unique(array_merge($icons, $touchIcons)));
}

/**
 * Version sans dépendance de is_blank_favicon() pour les .ico dont toutes les
 * images sont en BMP 32 bits (cas des icônes par défaut de Wix, etc.) : vide si
 * chaque image est quasi transparente (opacité max < 5 %) ou d'une seule couleur.
 * Toute image d'un autre format (PNG intégré, palette...) est supposée non vide.
 *
 * @param string $body Contenu binaire du fichier .ico
 *
 * @return bool true si toutes les images de l'icône sont vides
 */
function is_blank_ico_32bit(string $body): bool
{
    if (strlen($body) < 6) {
        return false;
    }

    $count = unpack('v', substr($body, 4, 2))[1];

    if ($count < 1) {
        return false;
    }

    for ($i = 0; $i < $count; $i++) {
        $entry = substr($body, 6 + $i * 16, 16);

        if (strlen($entry) < 16) {
            return false;
        }

        $dir = unpack('Vsize/Voffset', substr($entry, 8, 8));
        $header = substr($body, $dir['offset'], 40);

        if (strlen($header) < 40) {
            return false;
        }

        $bmp = unpack('VheaderSize/Vwidth/Vheight/vplanes/vbits', $header);

        // Pas un BMP 32 bits (p. ex. PNG intégré) : on ne peut pas juger.
        if ($bmp['headerSize'] !== 40 || $bmp['bits'] !== 32) {
            return false;
        }

        $width = $bmp['width'];
        // La hauteur d'un BMP d'icône compte l'image + le masque (x2).
        $height = intdiv($bmp['height'], 2);
        $pixels = substr($body, $dir['offset'] + 40, $width * $height * 4);

        if ($width < 1 || $height < 1 || strlen($pixels) < $width * $height * 4) {
            return false;
        }

        $firstColor = null;

        for ($p = 0; $p < $width * $height; $p++) {
            $alpha = ord($pixels[$p * 4 + 3]);

            if ($alpha < 13) {
                continue;
            }

            // Mélange sur fond blanc pour comparer l'apparence réelle.
            $color = '';
            for ($c = 0; $c < 3; $c++) {
                $color .= chr((int) round((ord($pixels[$p * 4 + $c]) * $alpha + 255 * (255 - $alpha)) / 255));
            }

            if ($firstColor === null) {
                $firstColor = $color;
            } elseif ($color !== $firstColor) {
                return false;
            }
        }

        // Image visible d'une seule couleur ou non : seule une image tout unie est vide.
        // (firstColor null = entièrement transparente)
    }

    return true;
}

/**
 * Indique si une image est vide : entièrement transparente ou d'une seule
 * couleur unie (certains sites, ou DuckDuckGo, renvoient un « favicon » vide).
 * Sans Imagick, seuls les .ico en 32 bits sont analysés (voir is_blank_ico_32bit()) ;
 * les autres images sont alors conservées.
 *
 * @param string $body Contenu binaire de l'image
 *
 * @return bool true si l'image est vide
 */
function is_blank_favicon(string $body): bool
{
    if (!extension_loaded('imagick')) {
        return str_starts_with($body, "\x00\x00\x01\x00") && is_blank_ico_32bit($body);
    }

    try {
        $frames = new Imagick();
        // Imagick ne reconnaît pas toujours un .ico depuis un blob sans indice de format.
        if (str_starts_with($body, "\x00\x00\x01\x00")) {
            $frames->setFormat('ico');
        }

        $frames->readImageBlob($body);

        // Une icône peut contenir plusieurs tailles : une seule non vide suffit.
        foreach ($frames as $frame) {
            $alpha = clone $frame;
            $alpha->setImageAlphaChannel(Imagick::ALPHACHANNEL_EXTRACT);
            $alphaStats = $alpha->getImageChannelStatistics();

            // Transparence quasi totale (opacité maximale sous 5 %) : image invisible.
            if ($alphaStats[Imagick::CHANNEL_RED]['maxima'] < Imagick::getQuantum() * 0.05) {
                continue;
            }

            $flat = clone $frame;
            $flat->setImageBackgroundColor('white');
            $flat->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $stats = $flat->getImageChannelStatistics();

            foreach ([Imagick::CHANNEL_RED, Imagick::CHANNEL_GREEN, Imagick::CHANNEL_BLUE] as $channel) {
                if ($stats[$channel]['standardDeviation'] > 0) {
                    return false;
                }
            }
        }
    } catch (ImagickException) {
        return false;
    }

    return true;
}

/**
 * Vérifie qu'un contenu est bien une image acceptée et l'enregistre localement.
 *
 * @param string $body Contenu binaire téléchargé
 *
 * @return string|null Chemin web relatif du fichier enregistré, ou null
 */
function store_race_favicon(string $body): ?string
{
    // On vérifie le contenu réel du fichier plutôt que de faire confiance au service distant.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($body);

    $allowedMimes = [
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimes[$mime]) || is_blank_favicon($body)) {
        return null;
    }

    $filename = 'race_' . bin2hex(random_bytes(4)) . '.' . $allowedMimes[$mime];
    $fsRoot = rtrim(app_config()['uploads_fs_root'], '/');
    $targetDirectory = $fsRoot . '/races';

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        return null;
    }

    if (file_put_contents($targetDirectory . '/' . $filename, $body) === false) {
        return null;
    }

    $webRoot = trim(app_config()['uploads_web_root'], '/');

    return $webRoot . '/races/' . $filename;
}

/**
 * Télécharge et enregistre localement le favicon du site d'une course, une
 * seule fois côté serveur au moment de l'enregistrement plutôt qu'à chaque
 * affichage de la page (évite la dépendance à un service tiers à chaque visite).
 *
 * Sources essayées dans l'ordre : le service DuckDuckGo (rapide, mais qui
 * n'a pas toutes les icônes), puis les icônes déclarées par la page du site
 * elle-même, puis /favicon.ico.
 *
 * Échoue toujours silencieusement (retourne null) : un favicon indisponible
 * n'empêche jamais l'enregistrement d'une course.
 *
 * @param string $websiteUrl URL du site de la course
 *
 * @return string|null Chemin web relatif du fichier enregistré, ou null
 */
function fetch_race_favicon(string $websiteUrl): ?string
{
    // Garde-fou de taille : une icône ne devrait jamais peser autant.
    $maxIconBytes = 200_000;
    $host = parse_url($websiteUrl, PHP_URL_HOST);

    if (!is_string($host) || $host === '') {
        return null;
    }

    $candidates = ['https://icons.duckduckgo.com/ip3/' . rawurlencode($host) . '.ico'];
    $tried = [];

    // Les URLs de la page ne sont cherchées que si DuckDuckGo échoue.
    $page = null;
    $pageLoaded = false;

    while (($url = array_shift($candidates)) !== null) {
        if (isset($tried[$url])) {
            continue;
        }
        $tried[$url] = true;

        $resource = fetch_remote_resource($url, $maxIconBytes);
        $path = $resource !== null ? store_race_favicon($resource['body']) : null;

        if ($path !== null) {
            return $path;
        }

        if (!$pageLoaded && $candidates === []) {
            $pageLoaded = true;
            $page = fetch_remote_resource($websiteUrl, 2_000_000);
            $pageIcons = $page !== null ? extract_page_icon_urls($page['body'], $page['url']) : [];
            $origin = $page !== null ? resolve_page_relative_url('/favicon.ico', $page['url']) : null;
            $candidates = array_merge($pageIcons, $origin !== null ? [$origin] : []);
        }
    }

    return null;
}

/**
 * Met à jour le favicon stocké d'une course si nécessaire : télécharge le
 * nouveau si l'URL du site a changé (ou qu'aucun favicon n'était encore
 * enregistré), supprime l'ancien fichier une fois le nouveau en place, et
 * nettoie si le site a été retiré. N'écrit en base que s'il y a un changement.
 *
 * @param int         $raceId              Identifiant de la course
 * @param string|null $websiteUrl          Nouvelle URL du site (null/vide si aucune)
 * @param string|null $previousWebsiteUrl  URL du site avant l'enregistrement
 * @param string|null $previousFaviconPath Chemin web du favicon déjà enregistré
 *
 * @return void
 */
function refresh_race_favicon(
    int $raceId,
    ?string $websiteUrl,
    ?string $previousWebsiteUrl,
    ?string $previousFaviconPath
): void {
    $hasPreviousFavicon = $previousFaviconPath !== null && $previousFaviconPath !== '';

    if ($websiteUrl === null || $websiteUrl === '') {
        if ($hasPreviousFavicon) {
            delete_uploaded_file($previousFaviconPath);
            app_pdo()->prepare('UPDATE races SET favicon_path = NULL WHERE id = :id')->execute(['id' => $raceId]);
        }
        return;
    }

    // URL inchangée et favicon déjà présent : rien à refaire.
    if ($websiteUrl === $previousWebsiteUrl && $hasPreviousFavicon) {
        return;
    }

    $newFaviconPath = fetch_race_favicon($websiteUrl);

    if ($newFaviconPath === null) {
        return;
    }

    if ($hasPreviousFavicon) {
        delete_uploaded_file($previousFaviconPath);
    }

    app_pdo()->prepare('UPDATE races SET favicon_path = :favicon_path WHERE id = :id')
        ->execute(['favicon_path' => $newFaviconPath, 'id' => $raceId]);
}

/**
 * Crée une nouvelle course.
 *
 * @param array $payload Données du formulaire (title, start_date, end_date,
 *                       location, distances, website_url, registration_info, created_by)
 *
 * @return void
 *
 * @throws RuntimeException Si les données sont invalides
 */
function create_race(array $payload): void
{
    [$data, $errors] = validate_race_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $pdo = app_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO races (title, start_date, end_date, location, distances, website_url, registration_info, created_by)
         VALUES (:title, :start_date, :end_date, :location, :distances, :website_url, :registration_info, :created_by)'
    );

    $stmt->execute(
        [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'distances' => $data['distances'],
        'website_url' => $data['website_url'] !== '' ? $data['website_url'] : null,
        'registration_info' => $data['registration_info'] !== '' ? $data['registration_info'] : null,
        'created_by' => $data['created_by'] !== null && $data['created_by'] > 0 ? $data['created_by'] : null,
        ]
    );

    if ($data['website_url'] !== '') {
        refresh_race_favicon((int) $pdo->lastInsertId(), $data['website_url'], null, null);
    }
}

/**
 * Récupère une course par son ID (avec le nom du créateur).
 *
 * @param int $id Identifiant de la course
 *
 * @return array|null Données de la course ou null
 */
function get_race_by_id(int $id): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.*, m.first_name AS author_first_name, m.last_name AS author_last_name, m.photo_path AS author_photo_path
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         WHERE r.id = :id'
    );

    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();

    return $result === false ? null : $result;
}

/**
 * Modifie une course existante.
 *
 * @param int   $id      Identifiant de la course
 * @param array $payload Données du formulaire
 *
 * @return void
 *
 * @throws RuntimeException Si les données sont invalides
 */
function update_race(int $id, array $payload): void
{
    [$data, $errors] = validate_race_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    // Récupéré avant l'UPDATE : sert à savoir si le site a changé et à
    // nettoyer l'ancien fichier de favicon le cas échéant.
    $previous = get_race_by_id($id);

    $createdBy = $data['created_by'];

    $sql = 'UPDATE races
             SET title = :title,
                 start_date = :start_date,
                 end_date = :end_date,
                 location = :location,
                 distances = :distances,
                 website_url = :website_url,
                 registration_info = :registration_info';

    $params = [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'distances' => $data['distances'],
        'website_url' => $data['website_url'] !== '' ? $data['website_url'] : null,
        'registration_info' => $data['registration_info'] !== '' ? $data['registration_info'] : null,
        'id' => $id,
    ];

    if ($createdBy !== null) {
        $sql .= ', created_by = :created_by';
        $params['created_by'] = $createdBy > 0 ? $createdBy : null;
    }

    $sql .= ' WHERE id = :id';

    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);

    refresh_race_favicon(
        $id,
        $data['website_url'] !== '' ? $data['website_url'] : null,
        $previous['website_url'] ?? null,
        $previous['favicon_path'] ?? null
    );
}

/**
 * Supprime définitivement une course (et ses réponses en cascade).
 *
 * @param int $id Identifiant de la course
 *
 * @return void
 */
function delete_race(int $id): void
{
    $race = get_race_by_id($id);

    $stmt = app_pdo()->prepare('DELETE FROM races WHERE id = :id');
    $stmt->execute(['id' => $id]);

    if ($race !== null && !empty($race['favicon_path'])) {
        delete_uploaded_file($race['favicon_path']);
    }
}

/**
 * Récupère toutes les courses, triées par date de début décroissante.
 * Inclut le nom du créateur.
 *
 * @return array Liste des courses
 */
function get_all_races(): array
{
    $stmt = app_pdo()->query(
        'SELECT r.*, m.first_name AS author_first_name, m.last_name AS author_last_name, m.photo_path AS author_photo_path, m.generic_account AS author_generic_account
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         ORDER BY r.start_date DESC'
    );

    return $stmt->fetchAll();
}

/**
 * Saison de courses (1er septembre → 31 août) contenant une date.
 *
 * @param DateTimeImmutable $date Date quelconque
 *
 * @return string Saison au format AAAA-AAAA (ex: '2026-2027')
 */
function race_season_for_date(DateTimeImmutable $date): string
{
    $year = (int) $date->format('Y');
    $startYear = (int) $date->format('n') >= 9 ? $year : $year - 1;

    return $startYear . '-' . ($startYear + 1);
}

/**
 * Bornes d'une saison de courses.
 *
 * @param string $season Saison au format AAAA-AAAA
 *
 * @return array{0: string, 1: string} [1er septembre, 31 août] au format Y-m-d
 */
function race_season_bounds(string $season): array
{
    $startYear = (int) substr($season, 0, 4);

    return [$startYear . '-09-01', ($startYear + 1) . '-08-31'];
}

/**
 * Saisons de courses disponibles pour les archives : celles qui ont au moins
 * une course en base (selon la date de début), plus la saison en cours.
 *
 * @return array Saisons AAAA-AAAA, de la plus récente à la plus ancienne
 */
function get_race_seasons(): array
{
    $startYears = app_pdo()->query(
        'SELECT DISTINCT YEAR(start_date) - (MONTH(start_date) < 9) AS start_year FROM races'
    )->fetchAll(PDO::FETCH_COLUMN);

    $seasons = [race_season_for_date(new DateTimeImmutable('today'))];
    foreach ($startYears as $startYear) {
        $seasons[] = (int) $startYear . '-' . ((int) $startYear + 1);
    }

    $seasons = array_unique($seasons);
    rsort($seasons);

    return $seasons;
}

/**
 * Récupère les courses terminées (date de fin, ou de début à défaut, antérieure à aujourd'hui)
 * qui commencent entre deux dates, de la plus ancienne à la plus récente.
 * Inclut le nom du créateur.
 *
 * @param string $from Premier jour (Y-m-d)
 * @param string $to   Dernier jour (Y-m-d)
 *
 * @return array Liste des courses
 */
function get_past_races_between(string $from, string $to): array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.*, m.first_name AS author_first_name, m.last_name AS author_last_name, m.photo_path AS author_photo_path, m.generic_account AS author_generic_account
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         WHERE r.start_date BETWEEN :from AND :to
           AND COALESCE(r.end_date, r.start_date) < CURDATE()
         ORDER BY r.start_date ASC, r.title ASC'
    );
    $stmt->execute(['from' => $from, 'to' => $to]);

    return $stmt->fetchAll();
}

/**
 * Récupère les prochaines courses (date de début >= aujourd'hui),
 * triées par date croissante, limitées à $limit résultats.
 *
 * @param int $limit Nombre maximum de courses à retourner
 *
 * @return array Liste des courses
 */
function get_upcoming_races(int $limit = 2): array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.id, r.title, r.start_date, r.end_date, r.location, r.distances, r.website_url, r.favicon_path
         FROM races r
         WHERE COALESCE(r.end_date, r.start_date) >= CURDATE()
         ORDER BY r.start_date ASC
         LIMIT ' . (int) $limit
    );
    // (int) $limit garantit que la valeur injectée dans la requête est un entier sûr.
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Récupère les réponses (intéressés et inscrits) pour une course donnée.
 *
 * @param int $raceId Identifiant de la course
 *
 * @return array Tableau avec deux clés : 'interested' et 'registered', chacune contenant un tableau de membres
 */
function get_race_responses(int $raceId): array
{
    $stmt = app_pdo()->prepare(
        'SELECT rr.status, m.id, m.first_name, m.last_name, m.photo_path, m.gender
         FROM race_responses rr
         INNER JOIN members m ON m.id = rr.member_id
         WHERE rr.race_id = :race_id
         ORDER BY m.first_name ASC, m.last_name ASC'
    );

    $stmt->execute(['race_id' => $raceId]);

    $result = ['interested' => [], 'registered' => []];

    foreach ($stmt->fetchAll() as $row) {
        $result[$row['status']][] = $row;
    }

    return $result;
}

/**
 * Récupère la réponse d'un membre pour une course donnée.
 *
 * @param int $raceId   Identifiant de la course
 * @param int $memberId Identifiant du membre
 *
 * @return string|null 'interested', 'registered' ou null si pas de réponse
 */
function get_member_race_response(int $raceId, int $memberId): ?string
{
    $stmt = app_pdo()->prepare(
        'SELECT status FROM race_responses WHERE race_id = :race_id AND member_id = :member_id LIMIT 1'
    );

    $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId]);
    $result = $stmt->fetch();

    return $result === false ? null : (string) $result['status'];
}

/**
 * Définit ou met à jour la réponse d'un membre pour une course.
 * Si le statut est le même que la réponse actuelle, la réponse est supprimée (toggle).
 *
 * @param int    $raceId   Identifiant de la course
 * @param int    $memberId Identifiant du membre
 * @param string $status   'interested' ou 'registered'
 *
 * @return void
 */
function set_race_response(int $raceId, int $memberId, string $status): void
{
    if (!in_array($status, ['interested', 'registered'], true)) {
        return;
    }

    $current = get_member_race_response($raceId, $memberId);

    if ($current === $status) {
        // Toggle off : supprimer la réponse
        $stmt = app_pdo()->prepare('DELETE FROM race_responses WHERE race_id = :race_id AND member_id = :member_id');
        $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId]);
    } else {
        // Upsert : insérer ou mettre à jour
        $stmt = app_pdo()->prepare(
            'INSERT INTO race_responses (race_id, member_id, status)
             VALUES (:race_id, :member_id, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        );
        $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId, 'status' => $status]);
    }
}
