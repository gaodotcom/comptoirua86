<?php

declare(strict_types=1);

/**
 * HelloAsso — import des adhésions depuis l'API HelloAsso v5
 *
 * Récupère les adhésions d'une campagne (formSlug Membership) et prépare
 * un plan d'import : création des adhérents manquants et ajout de leur
 * adhésion pour l'année scolaire concernée. Les adhérents déjà présents
 * en base (retrouvés par nom) ne sont pas recréés : on ajoute simplement
 * leur adhésion.
 *
 * Les montants HelloAsso sont en centimes ; la base stocke fee/donation
 * en euros entiers (voir memberships.fee).
 */

/**
 * Retourne la configuration HelloAsso.
 * Lève une exception si les identifiants ne sont pas renseignés.
 *
 * @return array Configuration HelloAsso
 *
 * @throws RuntimeException
 */
function helloasso_config(): array
{
    $cfg = app_config()['helloasso'] ?? [];

    if (empty($cfg['client_id']) || empty($cfg['client_secret']) || empty($cfg['org_slug'])) {
        throw new RuntimeException('HelloAsso : identifiants manquants (HELLOASSO_CLIENT_ID, CLIENT_SECRET, ORG_SLUG).');
    }

    return $cfg;
}

/**
 * Indique si HelloAsso est configuré (sans lever d'exception).
 *
 * @return bool
 */
function helloasso_is_configured(): bool
{
    try {
        helloasso_config();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Obtient un token d'accès OAuth2 (client_credentials).
 * Mis en cache statiquement pour la durée de la requête.
 *
 * @return string Token d'accès
 *
 * @throws RuntimeException
 */
function helloasso_fetch_access_token(): string
{
    static $token = null;

    if ($token !== null) {
        return $token;
    }

    $cfg = helloasso_config();

    $body = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
    ]);

    $response = helloasso_http_request('https://api.helloasso.com/oauth2/token', $body, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $data = json_decode($response, true);

    if (!is_array($data) || empty($data['access_token'])) {
        throw new RuntimeException('HelloAsso : échec d\'authentification (token non reçu).');
    }

    $token = (string) $data['access_token'];

    return $token;
}

/**
 * Effectue une requête HTTP POST/GET via cURL et retourne le corps.
 *
 * @param string $url     URL complète
 * @param string|null $body Corps de la requête (POST) ou null (GET)
 * @param array  $headers Headers HTTP
 *
 * @return string Corps de la réponse
 *
 * @throws RuntimeException
 */
function helloasso_http_request(string $url, ?string $body = null, array $headers = []): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    if ($headers !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('HelloAsso : erreur réseau (' . $errno . ') ' . $error);
    }

    if ($code >= 400) {
        throw new RuntimeException('HelloAsso : erreur HTTP ' . $code . ' — ' . substr((string) $response, 0, 300));
    }

    return (string) $response;
}

/**
 * Effectue un GET authentifié sur l'API v5.
 *
 * @param string $path  Chemin après /v5 (ex: '/organizations/{slug}/forms/Membership/{form}/items')
 * @param array  $params Paramètres de query string
 *
 * @return array Réponse JSON décodée
 *
 * @throws RuntimeException
 */
function helloasso_api_get(string $path, array $params = []): array
{
    $cfg = helloasso_config();
    $token = helloasso_fetch_access_token();

    $url = rtrim($cfg['api_base'], '/') . '/' . ltrim($path, '/');

    if ($params !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    $response = helloasso_http_request($url, null, [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ]);

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException('HelloAsso : réponse JSON invalide.');
    }

    return $data;
}

/**
 * Récupère tous les items d'adhésion d'une campagne, en parcourant
 * la pagination par continuationToken jusqu'à épuisement.
 *
 * @param string $membershipSlug Slug de la campagne HelloAsso
 *
 * @return array Liste des items (clé `data` concaténée)
 *
 * @throws RuntimeException
 */
function helloasso_fetch_all_items(string $membershipSlug): array
{
    $cfg = helloasso_config();
    $path = sprintf(
        '/organizations/%s/forms/Membership/%s/items',
        $cfg['org_slug'],
        $membershipSlug
    );

    $all = [];
    $continuationToken = null;

    // Limite de sécurité : 50 pages de 100 items.
    for ($page = 0; $page < 50; $page++) {
        $params = ['pageSize' => 100];
        if ($continuationToken !== null) {
            $params['continuationToken'] = $continuationToken;
        }

        $res = helloasso_api_get($path, $params);
        $items = $res['data'] ?? [];

        if ($items === []) {
            break;
        }

        foreach ($items as $item) {
            $all[] = $item;
        }

        $continuationToken = $res['pagination']['continuationToken'] ?? null;

        // Moins d'items que la taille de page : dernière page.
        if (count($items) < 100 || $continuationToken === null) {
            break;
        }
    }

    return $all;
}

/**
 * Déduit l'année scolaire d'un slug de campagne (ex: 'adhesion-2026-2027' -> '2026-2027').
 * Retourne l'année scolaire courante si le slug ne contient pas le pattern.
 *
 * @param string $slug Slug de la campagne
 *
 * @return string Année scolaire (ex: '2026-2027')
 */
function helloasso_school_year_from_slug(string $slug): string
{
    if (preg_match('/(\d{4}-\d{4})/', $slug, $m)) {
        return $m[1];
    }

    $now = new DateTimeImmutable();
    $year = (int) $now->format('Y');
    $startYear = (int) $now->format('n') >= 8 ? $year : $year - 1;

    return $startYear . '-' . ($startYear + 1);
}

/**
 * Normalise un nom pour la comparaison : minuscules, sans accents,
 * tirets et apostrophes convertis en espaces, espaces regroupés.
 *
 * @param string $s Nom à normaliser
 *
 * @return string Nom normalisé
 */
function helloasso_normalize_name(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strip_accents($s);
    // Tirets et apostrophes -> espaces, puis on ne garde que les lettres et espaces.
    $s = str_replace(['-', "'", '_'], ' ', $s);
    $s = preg_replace('/[^a-z ]/', '', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    return trim($s);
}

/**
 * Supprime les accents d'une chaîne (diacritiques Unicode).
 *
 * @param string $s Chaîne à traiter
 *
 * @return string Chaîne sans accents
 */
function strip_accents(string $s): string
{
    return str_replace(
        ['à', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', 'ñ'],
        ['a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', 'n'],
        $s
    );
}

/**
 * Récupère le détail d'un item (article) avec ses customFields.
 * Endpoint : GET /v5/items/{itemId}?withDetails=true
 *
 * @param int $itemId Identifiant de l'item
 *
 * @return array Item détaillé (contient customFields)
 *
 * @throws RuntimeException
 */
function helloasso_fetch_item_detail(int $itemId): array
{
    $res = helloasso_api_get('/items/' . $itemId, ['withDetails' => 'true']);

    return $res;
}

/**
 * Normalise un libellé de champ pour la comparaison : minuscules, sans
 * accents, espaces regroupés. Utilisée pour reconnaître les customFields
 * quel que soit le libellé exact choisi par l'association.
 *
 * @param string $s Libellé à normaliser
 *
 * @return string Libellé normalisé
 */
function helloasso_normalize_label(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strip_accents($s);
    $s = preg_replace('/\s+/', ' ', $s);

    return trim($s);
}

/**
 * Parse une date depuis un customField (formats d/m/Y ou Y-m-d).
 *
 * @param string $answer Réponse brute du customField
 *
 * @return string|null Date au format Y-m-d, ou null si non reconnue
 */
function helloasso_parse_date(string $answer): ?string
{
    foreach (['d/m/Y', 'Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $answer);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Nettoie et valide un email depuis un customField.
 *
 * @param string $answer Réponse brute du customField
 *
 * @return string|null Email valide, ou null
 */
function helloasso_parse_email_answer(string $answer): ?string
{
    $cleaned = str_replace(' ', '', $answer);

    return filter_var($cleaned, FILTER_VALIDATE_EMAIL) ? $cleaned : null;
}

/**
 * Détermine le nom du champ de données correspondant à un customField,
 * à partir de son type et de son libellé normalisé.
 *
 * @param string $type Type du customField (Date, Phone, Zipcode, YesNo...)
 * @param string $name Libellé normalisé du customField
 *
 * @return string|null Nom du champ (ex: 'date_of_birth') ou null si non reconnu
 */
function helloasso_match_custom_field(string $type, string $name): ?string
{
    if ($type === 'Date' || str_contains($name, 'naissance')) {
        return 'date_of_birth';
    }

    if ($type === 'Phone' || str_contains($name, 'telephone')) {
        return 'phone';
    }

    if ($type === 'YesNo' || str_contains($name, 'whatsapp')) {
        return 'whatsapp_opt_in';
    }

    if (str_contains($name, 'email') || str_contains($name, 'mail') || str_contains($name, 'courriel')) {
        return 'email';
    }

    // Adresse postale (vérifiée avant "code postal" car "adresse postale"
    // contient le mot "postal").
    if (str_contains($name, 'adresse') || str_contains($name, 'address')) {
        return 'address';
    }

    if ($type === 'Zipcode' || str_contains($name, 'code postal')) {
        return 'postal_code';
    }

    if (str_contains($name, 'ville') || str_contains($name, 'commune')) {
        return 'city';
    }

    return null;
}

/**
 * Convertit la valeur d'un customField selon le champ ciblé.
 *
 * @param string $field  Nom du champ (de helloasso_match_custom_field)
 * @param string $answer Réponse brute du customField
 *
 * @return string|int|null Valeur convertie
 */
function helloasso_parse_field_value(string $field, string $answer): string|int|null
{
    if ($field === 'date_of_birth') {
        return helloasso_parse_date($answer);
    }

    if ($field === 'email') {
        return helloasso_parse_email_answer($answer);
    }

    if ($field === 'whatsapp_opt_in') {
        return in_array(strtolower($answer), ['oui', 'yes', 'o', 'true', '1'], true) ? 1 : 0;
    }

    return $answer;
}

/**
 * Extrait les données d'un adhérent depuis les customFields d'un item
 * (date de naissance, téléphone, adresse, code postal, ville, email
 * propre à l'adhérent, opt-in WhatsApp).
 *
 * @param array $customFields Tableau des customFields de l'item
 *
 * @return array Données structurées du membre
 */
function helloasso_extract_member_data(array $customFields): array
{
    $data = [
        'date_of_birth' => null,
        'phone' => null,
        'address' => null,
        'postal_code' => null,
        'city' => null,
        'email' => null,
        'whatsapp_opt_in' => 0,
    ];

    foreach ($customFields as $cf) {
        $answer = trim((string) ($cf['answer'] ?? ''));

        if ($answer === '') {
            continue;
        }

        $type = (string) ($cf['type'] ?? '');
        $name = helloasso_normalize_label((string) ($cf['name'] ?? ''));
        $field = helloasso_match_custom_field($type, $name);

        if ($field === null) {
            continue;
        }

        $data[$field] = helloasso_parse_field_value($field, $answer);
    }

    return $data;
}

/**
 * Calcule le don cumulé d'une commande (items sans user).
 *
 * @param array $siblings Items de la même commande
 *
 * @return int Montant du don en centimes
 */
function helloasso_sum_donations(array $siblings): int
{
    $total = 0;

    foreach ($siblings as $sibling) {
        $sfn = trim((string) ($sibling['user']['firstName'] ?? ''));
        $sln = trim((string) ($sibling['user']['lastName'] ?? ''));

        if ($sfn === '' && $sln === '') {
            $total += (int) ($sibling['amount'] ?? 0);
        }
    }

    return $total;
}

/**
 * Construit l'entrée du plan d'import à partir d'un item HelloAsso
 * et de ses données membre extraites.
 *
 * @param array $it         Item HelloAsso
 * @param array $memberData Données extraites des customFields
 * @param int   $donationCents Montant du don en centimes
 *
 * @return array Entrée du plan
 */
function helloasso_build_entry(array $it, array $memberData, int $donationCents): array
{
    $userFn = trim((string) ($it['user']['firstName'] ?? ''));
    $userLn = trim((string) ($it['user']['lastName'] ?? ''));

    return [
        'first_name' => $userFn,
        'last_name' => $userLn,
        'date_of_birth' => $memberData['date_of_birth'],
        'phone' => $memberData['phone'],
        'address' => $memberData['address'],
        'postal_code' => $memberData['postal_code'],
        'city' => $memberData['city'],
        'email' => null,
        'adherent_email' => $memberData['email'],
        'email_note' => '',
        'whatsapp_opt_in' => $memberData['whatsapp_opt_in'],
        'payer_first_name' => trim((string) ($it['payer']['firstName'] ?? '')),
        'payer_last_name' => trim((string) ($it['payer']['lastName'] ?? '')),
        'payer_email' => trim((string) ($it['payer']['email'] ?? '')),
        'fee' => (int) ($it['amount'] ?? 0),
        'donation' => $donationCents,
        'date' => substr((string) ($it['order']['date'] ?? ''), 0, 10),
        'order_id' => (int) ($it['order']['id'] ?? 0),
    ];
}

/**
 * Rapproche un adhérent HelloAsso avec les membres existants en base.
 * D'abord par email de l'adhérent, puis par nom normalisé.
 *
 * @param string|null $adherentEmail Email de l'adhérent (customFields)
 * @param string      $nameKey       Nom normalisé pour la comparaison
 * @param array       $byEmail      Index des membres par email
 * @param array       $byName       Index des membres par nom normalisé
 *
 * @return int|null ID du membre trouvé, ou null
 */
function helloasso_match_member(?string $adherentEmail, string $nameKey, array $byEmail, array $byName): ?int
{
    if ($adherentEmail !== null && isset($byEmail[mb_strtolower($adherentEmail)])) {
        return $byEmail[mb_strtolower($adherentEmail)];
    }

    return $byName[$nameKey] ?? null;
}

/**
 * Calcule les différences entre les données HelloAsso et la base
 * pour les champs téléphone, date de naissance et email.
 *
 * @param array $memberData Données extraites de HelloAsso
 * @param array $dbM        Données du membre en base
 *
 * @return array Tableau des différences [champ => ['db' => ..., 'ha' => ...]]
 */
function helloasso_compute_field_diffs(array $memberData, array $dbM): array
{
    $diffs = [];

    $haPhone = $memberData['phone'];
    $dbPhone = (string) ($dbM['phone'] ?? '');
    if ($haPhone !== null && $haPhone !== '' && str_replace(' ', '', $haPhone) !== str_replace(' ', '', $dbPhone)) {
        $diffs['phone'] = ['db' => $dbPhone, 'ha' => $haPhone];
    }

    $haDob = $memberData['date_of_birth'];
    $dbDob = (string) ($dbM['date_of_birth'] ?? '');
    if ($haDob !== null && $haDob !== $dbDob) {
        $diffs['date_of_birth'] = ['db' => $dbDob, 'ha' => $haDob];
    }

    $haMail = $memberData['email'];
    $dbMail = (string) ($dbM['email'] ?? '');
    if ($haMail !== null && $haMail !== '' && mb_strtolower($haMail) !== mb_strtolower($dbMail)) {
        $diffs['email'] = ['db' => $dbMail, 'ha' => $haMail];
    }

    return $diffs;
}

/**
 * Finalise une entrée pour un membre déjà existant en base :
 * calcule les diffs d'adhésion et de fiche, détermine si l'import est nécessaire.
 *
 * @param array   $entry        Entrée du plan (modifiée par référence)
 * @param int     $matchedId    ID du membre rapproché
 * @param array   $dbById       Données des membres en base (indexées par ID)
 * @param array   $dbMemberships Adhésions existantes pour l'année cible
 * @param array   $memberData   Données extraites des customFields
 *
 * @return bool True si l'entrée doit être ajoutée à existing[], false si déjà importée
 */
function helloasso_finalize_existing_entry(array &$entry, int $matchedId, array $dbById, array $dbMemberships, array $memberData): bool
{
    $dbM = $dbById[$matchedId] ?? [];
    $haFee = intdiv((int) $entry['fee'], 100);
    $haDon = intdiv((int) $entry['donation'], 100);
    $dbFee = isset($dbMemberships[$matchedId]) ? $dbMemberships[$matchedId][0] : null;
    $dbDon = isset($dbMemberships[$matchedId]) ? $dbMemberships[$matchedId][1] : null;

    $entry['db_fee'] = $dbFee;
    $entry['db_donation'] = $dbDon;
    $entry['membership_exists'] = isset($dbMemberships[$matchedId]);
    $entry['membership_will_change'] = $entry['membership_exists'] ? ($dbFee !== $haFee || $dbDon !== $haDon) : true;
    $entry['field_diffs'] = helloasso_compute_field_diffs($memberData, $dbM);

    // Si l'adhésion existe déjà et n'a pas changé, on ne la réimporte pas.
    return !$entry['membership_exists'] || $entry['membership_will_change'];
}

/**
 * Tente d'attribuer l'email de l'adhérent à une nouvelle entrée,
 * en vérifiant l'unicité (base + déjà attribués dans ce lot).
 *
 * @param array $entry      Entrée du plan (modifiée par référence)
 * @param array $memberData Données extraites des customFields
 * @param array $byEmail    Index des emails déjà en base
 * @param array $usedEmails  Emails déjà attribués dans ce lot (modifié par référence)
 *
 * @return void
 */
function helloasso_assign_unique_email(array &$entry, array $memberData, array $byEmail, array &$usedEmails): void
{
    $adherentEmail = $memberData['email'];

    if ($adherentEmail === null) {
        return;
    }

    $emailKey = mb_strtolower($adherentEmail);

    if (isset($byEmail[$emailKey]) || isset($usedEmails[$emailKey])) {
        $entry['email_note'] = 'Email déjà utilisé par un autre membre ; non attribué.';
        return;
    }

    $entry['email'] = $adherentEmail;
    $usedEmails[$emailKey] = true;
}

/**
 * Charge les index de membres existants (par ID, nom, email) et les
 * adhésions déjà enregistrées pour l'année scolaire cible.
 *
 * @param string $schoolYear Année scolaire cible
 *
 * @return array{0: array, 1: array, 2: array, 3: array} [$dbById, $byName, $byEmail, $dbMemberships]
 */
function helloasso_load_member_indexes(string $schoolYear): array
{
    $dbMembers = app_pdo()->query(
        'SELECT id, first_name, last_name, email, phone, date_of_birth, address, postal_code, city, whatsapp_opt_in
         FROM members WHERE deleted_at IS NULL'
    )->fetchAll();

    $byName = [];
    $byEmail = [];
    $dbById = [];

    foreach ($dbMembers as $m) {
        $mid = (int) $m['id'];
        $dbById[$mid] = $m;
        $key = helloasso_normalize_name((string) $m['first_name'] . ' ' . (string) $m['last_name']);
        if ($key !== '') {
            $byName[$key] = $mid;
        }
        if (!empty($m['email'])) {
            $byEmail[mb_strtolower(trim((string) $m['email']))] = $mid;
        }
    }

    // Adhésions déjà enregistrées pour l'année scolaire ciblée.
    $dbMemberships = [];
    $msStmt = app_pdo()->prepare('SELECT member_id, fee, donation FROM memberships WHERE school_year = :sy');
    $msStmt->execute([':sy' => $schoolYear]);
    foreach ($msStmt->fetchAll() as $row) {
        $dbMemberships[(int) $row['member_id']] = [(int) $row['fee'], (int) $row['donation']];
    }

    return [$dbById, $byName, $byEmail, $dbMemberships];
}

/**
 * Construit le plan d'import à partir des items HelloAsso et de l'état de la base.
 *
 * Chaque item adhésion (champ `user` renseigné) devient une entrée du plan.
 * Les dons (items avec `user` vide) sont rattachés à l'adhésion de la même
 * commande (même `order.id`) et cumulés dans `donation`.
 *
 * Pour chaque adhésion, le détail de l'item est récupéré (withDetails=true)
 * afin d'extraire les customFields remplis par l'adhérent : date de
 * naissance, téléphone, adresse, code postal, ville, email propre à
 * l'adhérent et opt-in WhatsApp.
 *
 * Le rapprochement avec les membres existants se fait d'abord par l'email
 * de l'adhérent (customFields), puis par nom normalisé. Les adhérents déjà
 * présents ne sont pas recréés : on ajoute simplement leur adhésion (sans
 * écraser leur fiche existante).
 *
 * Si plusieurs items HelloAsso de la campagne se rapprochent du même adhérent
 * (double inscription, correction...), ils sont fusionnés en une seule entrée
 * de `existing` : le tarif le plus élevé est conservé comme référence, les
 * dons sont cumulés, plutôt que d'afficher plusieurs lignes contradictoires.
 *
 * @param string $slug        Slug de la campagne HelloAsso
 * @param string $mode        Mode d'import : 'complet' (crée les membres manquants)
 *                            ou 'adhesion' (ignore les non-trouvés)
 *
 * @return array{items: int, memberships: int, donations: int, orders: int,
 *               new: array, existing: array, ignored: array, school_year: string,
 *               slug: string, mode: string}
 *
 * @throws RuntimeException
 */
function helloasso_build_import_plan(string $slug, string $mode = 'complet'): array
{
    $items = helloasso_fetch_all_items($slug);

    $schoolYear = helloasso_school_year_from_slug($slug);

    // Regroupement par commande pour rattacher les dons.
    $byOrder = [];
    foreach ($items as $it) {
        $byOrder[$it['order']['id']][] = $it;
    }

    // Index des membres existants et adhésions déjà enregistrées.
    [$dbById, $byName, $byEmail, $dbMemberships] = helloasso_load_member_indexes($schoolYear);

    $new = [];
    $existing = [];
    $ignored = [];
    $alreadyImported = [];
    $donationCount = 0;
    $usedEmails = []; // Emails déjà attribués dans ce lot (clé normalisée).

    // Adhérents déjà rapprochés dans cette même campagne, en attente de finalisation
    // (member_id => ['entry' => ..., 'memberData' => ...]). Un même adhérent peut
    // correspondre à plusieurs items HelloAsso (double inscription, correction...) :
    // on les fusionne au lieu d'afficher plusieurs lignes contradictoires pour la
    // même personne (voir fusion plus bas).
    $matchedByMemberId = [];

    foreach ($items as $it) {
        $userFn = trim((string) ($it['user']['firstName'] ?? ''));
        $userLn = trim((string) ($it['user']['lastName'] ?? ''));

        // Les dons (user vide) ne sont pas des adhésions.
        if ($userFn === '' && $userLn === '') {
            continue;
        }

        // Détail de l'item pour récupérer les customFields.
        $detail = helloasso_fetch_item_detail((int) ($it['id'] ?? 0));
        $memberData = helloasso_extract_member_data($detail['customFields'] ?? []);

        // Don cumulé de la même commande (items avec user vide).
        $donationCents = helloasso_sum_donations($byOrder[$it['order']['id']] ?? []);
        if ($donationCents > 0) {
            $donationCount++;
        }

        $entry = helloasso_build_entry($it, $memberData, $donationCents);

        // Rapprochement : d'abord par email de l'adhérent, puis par nom.
        $nameKey = helloasso_normalize_name($userFn . ' ' . $userLn);
        $matchedId = helloasso_match_member($memberData['email'], $nameKey, $byEmail, $byName);

        if ($matchedId !== null) {
            $entry['member_id'] = (int) $matchedId;

            if (isset($matchedByMemberId[$matchedId])) {
                // Fusion : on garde le tarif le plus élevé comme entrée principale
                // (probablement le vrai paiement, l'autre item pouvant être une
                // correction ou un doublon à 0€), et on cumule les dons.
                $primary = $matchedByMemberId[$matchedId]['entry'];
                $primaryData = $matchedByMemberId[$matchedId]['memberData'];

                if ((int) $entry['fee'] >= (int) $primary['fee']) {
                    $entry['donation'] = (int) $entry['donation'] + (int) $primary['donation'];
                    $matchedByMemberId[$matchedId] = ['entry' => $entry, 'memberData' => $memberData];
                } else {
                    $primary['donation'] = (int) $primary['donation'] + (int) $entry['donation'];
                    $matchedByMemberId[$matchedId] = ['entry' => $primary, 'memberData' => $primaryData];
                }
            } else {
                $matchedByMemberId[$matchedId] = ['entry' => $entry, 'memberData' => $memberData];
            }

            continue;
        }

        // Nouvel adhérent : en mode adhesion, on ignore ; en mode complet, on crée.
        if ($mode === 'adhesion') {
            $ignored[] = $entry;
            continue;
        }

        // Attribution de l'email de l'adhérent (UNIQUE).
        helloasso_assign_unique_email($entry, $memberData, $byEmail, $usedEmails);

        $new[] = $entry;
    }

    // Finalisation des adhérents rapprochés (diffs, statut) une fois les
    // éventuels doublons de la même campagne fusionnés en une seule entrée.
    foreach ($matchedByMemberId as $matchedId => $match) {
        $entry = $match['entry'];
        $needsImport = helloasso_finalize_existing_entry($entry, (int) $matchedId, $dbById, $dbMemberships, $match['memberData']);

        if (!$needsImport) {
            $alreadyImported[] = $entry;
            continue;
        }

        $existing[] = $entry;
    }

    return [
        'items' => count($items),
        'memberships' => count($new) + count($existing),
        'donations' => $donationCount,
        'orders' => count($byOrder),
        'new' => $new,
        'existing' => $existing,
        'ignored' => $ignored,
        'already_imported' => $alreadyImported,
        'school_year' => $schoolYear,
        'slug' => $slug,
        'mode' => $mode,
    ];
}

/**
 * Prépare les paramètres d'exécution d'une entrée du plan d'import
 * en convertissant les valeurs nulles/vides pour PDO.
 *
 * @return array Paramètres prêts pour PDO
 */
function helloasso_entry_to_nullify(array $entry): array
{
    return [
        'first_name' => $entry['first_name'],
        'last_name' => $entry['last_name'],
        'email' => ($entry['email'] ?? null) !== null && $entry['email'] !== '' ? $entry['email'] : null,
        'date_of_birth' => $entry['date_of_birth'] !== null && $entry['date_of_birth'] !== '' ? $entry['date_of_birth'] : null,
        'phone' => $entry['phone'] !== null && $entry['phone'] !== '' ? $entry['phone'] : null,
        'address' => $entry['address'] !== null && $entry['address'] !== '' ? $entry['address'] : null,
        'postal_code' => $entry['postal_code'] !== null && $entry['postal_code'] !== '' ? $entry['postal_code'] : null,
        'city' => $entry['city'] !== null && $entry['city'] !== '' ? $entry['city'] : null,
        'whatsapp_opt_in' => (int) $entry['whatsapp_opt_in'],
    ];
}

/**
 * Exécute le plan d'import : crée les nouveaux adhérents (avec toutes les
 * données issues des customFields) et ajoute l'adhésion (school_year) pour
 * tous (nouveaux + existants). Pour les existants, seule l'adhésion est
 * ajoutée/mise à jour : la fiche existante n'est pas modifiée.
 * Tout se fait dans une transaction.
 *
 * @param array $plan Plan produit par helloasso_build_import_plan()
 *
 * @return array{created: int, updated: int, errors: array}
 */
function helloasso_run_import(array $plan): array
{
    $pdo = app_pdo();
    $schoolYear = $plan['school_year'];

    $insertMember = $pdo->prepare(
        'INSERT INTO members (role, first_name, last_name, email, date_of_birth, phone, address, postal_code, city, whatsapp_opt_in)
         VALUES (:role, :first_name, :last_name, :email, :date_of_birth, :phone, :address, :postal_code, :city, :whatsapp_opt_in)'
    );

    $upsertMembership = $pdo->prepare(
        'INSERT INTO memberships (member_id, school_year, fee, donation)
         VALUES (:member_id, :school_year, :fee, :donation)
         ON DUPLICATE KEY UPDATE fee = VALUES(fee), donation = VALUES(donation)'
    );

    $created = 0;
    $updated = 0;
    $errors = [];

    $pdo->beginTransaction();
    try {
        // 1) Création des nouveaux adhérents + leur adhésion.
        foreach ($plan['new'] as $entry) {
            try {
                $params = helloasso_entry_to_nullify($entry);
                $params['role'] = 'adherent';
                $insertMember->execute($params);
                $memberId = (int) $pdo->lastInsertId();
                // Montants HelloAsso en centimes -> euros entiers (intdiv par 100).
                $upsertMembership->execute([
                    'member_id' => $memberId,
                    'school_year' => $schoolYear,
                    'fee' => intdiv((int) $entry['fee'], 100),
                    'donation' => intdiv((int) $entry['donation'], 100),
                ]);
                $created++;
            } catch (Throwable $e) {
                $errors[] = sprintf('%s %s : %s', $entry['first_name'], $entry['last_name'], $e->getMessage());
            }
        }

        // 2) Adhésions des adhérents déjà existants (fiche inchangée, seule l'adhésion est posée).
        foreach ($plan['existing'] as $entry) {
            try {
                $upsertMembership->execute([
                    'member_id' => (int) $entry['member_id'],
                    'school_year' => $schoolYear,
                    'fee' => intdiv((int) $entry['fee'], 100),
                    'donation' => intdiv((int) $entry['donation'], 100),
                ]);
                $updated++;
            } catch (Throwable $e) {
                $errors[] = sprintf('%s %s : %s', $entry['first_name'], $entry['last_name'], $e->getMessage());
            }
        }

        // En cas d'erreur, on annule tout (rollback) : l'import est atomique.
        if ($errors !== []) {
            $pdo->rollBack();
        } else {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Erreur transaction : ' . $e->getMessage();
    }

    // Marque la campagne comme importée si succès.
    if ($errors === [] && !empty($plan['slug'])) {
        $stmt = $pdo->prepare('UPDATE helloasso_campaigns SET last_imported_at = CURRENT_TIMESTAMP WHERE slug = :slug');
        $stmt->execute([':slug' => $plan['slug']]);
    }

    return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
}

/* ============================================================================
   CRUD des campagnes HelloAsso
   ============================================================================ */

/**
 * Récupère toutes les campagnes enregistrées, triées par année scolaire décroissante.
 *
 * @return array Liste des campagnes
 */
function helloasso_get_campaigns(): array
{
    return app_pdo()->query(
        'SELECT id, slug, school_year, url, last_imported_at, created_at
         FROM helloasso_campaigns
         ORDER BY school_year DESC'
    )->fetchAll();
}

/**
 * Récupère une campagne par son ID.
 *
 * @param int $id Identifiant de la campagne
 *
 * @return array|null Données de la campagne ou null
 */
function helloasso_get_campaign(int $id): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT id, slug, school_year, url, last_imported_at, created_at
         FROM helloasso_campaigns
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Valide les données d'une campagne HelloAsso.
 *
 * @param array      $data Données (slug, school_year, url)
 * @param int|null  $excludeId ID à exclure de la vérification d'unicité (en édition)
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function helloasso_validate_campaign_data(array $data, ?int $excludeId = null): array
{
    $slug = trim((string) ($data['slug'] ?? ''));
    $schoolYear = trim((string) ($data['school_year'] ?? ''));
    $url = trim((string) ($data['url'] ?? ''));

    $errors = [];

    if ($slug === '') {
        $errors[] = 'Le slug de la campagne est obligatoire.';
    }

    if (!is_school_year($schoolYear)) {
        $errors[] = 'L\'année scolaire doit être au format AAAA-AAAA.';
    }

    // Vérifie l'unicité du slug.
    if ($slug !== '') {
        $sql = 'SELECT id FROM helloasso_campaigns WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = app_pdo()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch() !== false) {
            $errors[] = $excludeId !== null
                ? 'Une autre campagne utilise déjà ce slug.'
                : 'Une campagne avec ce slug existe déjà.';
        }
    }

    return [compact('slug', 'schoolYear', 'url'), $errors];
}

/**
 * Crée une nouvelle campagne.
 *
 * @param array $data Données (slug, school_year, url)
 *
 * @return array{0: bool, 1: array} [succès, liste d'erreurs]
 */
function helloasso_create_campaign(array $data): array
{
    [$validated, $errors] = helloasso_validate_campaign_data($data);

    if ($errors !== []) {
        return [false, $errors];
    }

    $stmt = app_pdo()->prepare(
        'INSERT INTO helloasso_campaigns (slug, school_year, url) VALUES (:slug, :school_year, :url)'
    );
    $stmt->execute([
        ':slug' => $validated['slug'],
        ':school_year' => $validated['school_year'],
        ':url' => $validated['url'] !== '' ? $validated['url'] : null,
    ]);

    return [true, []];
}

/**
 * Modifie une campagne existante.
 *
 * @param int   $id   Identifiant de la campagne
 * @param array $data Données (slug, school_year, url)
 *
 * @return array{0: bool, 1: array} [succès, liste d'erreurs]
 */
function helloasso_update_campaign(int $id, array $data): array
{
    $existing = helloasso_get_campaign($id);
    if ($existing === null) {
        return [false, ['Campagne introuvable.']];
    }

    [$validated, $errors] = helloasso_validate_campaign_data($data, $id);

    if ($errors !== []) {
        return [false, $errors];
    }

    $stmt = app_pdo()->prepare(
        'UPDATE helloasso_campaigns SET slug = :slug, school_year = :school_year, url = :url WHERE id = :id'
    );
    $stmt->execute([
        ':slug' => $validated['slug'],
        ':school_year' => $validated['school_year'],
        ':url' => $validated['url'] !== '' ? $validated['url'] : null,
        ':id' => $id,
    ]);

    return [true, []];
}

/**
 * Supprime une campagne.
 *
 * @param int $id Identifiant de la campagne
 *
 * @return void
 */
function helloasso_delete_campaign(int $id): void
{
    $stmt = app_pdo()->prepare('DELETE FROM helloasso_campaigns WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

/**
 * Déduit le slug de campagne depuis une URL HelloAsso.
 * Ex: 'https://www.helloasso.com/associations/ultramical86/adhesions/adhesion-2026-2027'
 *     -> 'adhesion-2026-2027'
 *
 * @param string $url URL de la page d'adhésion HelloAsso
 *
 * @return string Slug déduit, ou chaîne vide si non reconnu
 */
function helloasso_slug_from_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $path = parse_url($url, PHP_URL_PATH);
    if ($path === false) {
        return '';
    }

    $segments = explode('/', trim($path, '/'));

    return (string) end($segments);
}
