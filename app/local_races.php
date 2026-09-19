<?php

declare(strict_types=1);

/**
 * Local races — import du calendrier FFA (courses "Running") pour le
 * département de la Vienne (086) dans le calendrier du club.
 *
 * Il n'existe pas d'API publique FFA : la seule source est la page HTML du
 * calendrier sur athle.fr, pilotée par des paramètres d'URL. Un admin
 * déclenche l'import ponctuellement (aperçu puis confirmation, comme
 * l'import HelloAsso) ; aucun fetch automatique/planifié.
 */

/** Département figé (Vienne) : c'est celui du club. */
const LOCAL_RACES_DEPARTMENT = '086';

/** Catégorie FFA figée : "Running" = route/trail/hors-stade (pas Cross/Marche/Salle/Stade). */
const LOCAL_RACES_TYPE = 'Running';

/**
 * Construit l'URL du calendrier FFA pour une saison donnée, département et
 * type figés (cf. constantes ci-dessus).
 *
 * @param int $season Saison FFA (ex: 2027 pour sept. 2026 → août 2027)
 *
 * @return string
 */
function local_races_courses_url(int $season): string
{
    $params = [
        'frmpostback' => 'true',
        'frmbase' => 'calendrier',
        'frmmode' => '1',
        'frmespace' => '0',
        'frmsaisonffa' => (string) $season,
        'frmdate1' => '',
        'frmdate2' => '',
        'frmtype1' => LOCAL_RACES_TYPE,
        'frmniveau' => '',
        'frmligue' => 'N-A',
        'frmdepartement' => LOCAL_RACES_DEPARTMENT,
        'frmniveaulab' => '',
        'frmepreuve' => '',
        'frmtype2' => '',
        'frmtype3' => '',
        'frmtype4' => '',
    ];

    return 'https://www.athle.fr/bases/liste.aspx?' . http_build_query($params);
}

/**
 * Récupère le HTML du calendrier FFA pour une saison donnée.
 *
 * @param int $season Saison FFA
 *
 * @return string HTML de la page
 *
 * @throws RuntimeException Si la requête échoue (réseau ou HTTP >= 400)
 */
function fetch_ffa_calendar_html(int $season): string
{
    $ch = curl_init(local_races_courses_url($season));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ComptoirUA86/1.0)',
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException('Impossible de contacter le site de la FFA : ' . $error);
    }

    if ($code >= 400) {
        throw new RuntimeException('Le site de la FFA a répondu avec une erreur HTTP ' . $code . '.');
    }

    if (!is_string($body) || $body === '') {
        throw new RuntimeException('Le site de la FFA a renvoyé une page vide.');
    }

    return $body;
}

/**
 * Parse la page calendrier FFA et retourne les courses du département
 * demandé. Fonction pure : aucun accès réseau ni base de données.
 *
 * Ignore les lignes qui ne sont pas des courses (en-têtes de mois, lignes de
 * détail dépliables), et filtre strictement sur le code département extrait
 * de chaque ligne (la FFA remonte parfois des championnats régionaux situés
 * hors département, même avec le filtre demandé côté serveur).
 *
 * @param string $html       HTML de la page calendrier FFA
 * @param string $department Code département à conserver (ex: '086')
 *
 * @return array Liste de courses : ['ffa_competition_id', 'title', 'start_date',
 *               'end_date', 'city', 'department_code', 'level', 'detail_url']
 *
 * @throws RuntimeException Si la table attendue est introuvable (structure FFA changée)
 */
function parse_ffa_calendar_html(string $html, string $department = LOCAL_RACES_DEPARTMENT): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $table = $xpath->query('//table[@id="ctnCalendrier"]')->item(0);

    if ($table === null) {
        throw new RuntimeException(
            'Structure de la page FFA inattendue (table du calendrier introuvable) : le site a peut-être changé.'
        );
    }

    $races = [];

    foreach ($xpath->query('.//tr', $table) as $row) {
        $cells = $xpath->query('.//td', $row);

        if ($cells->length < 6) {
            continue;
        }

        $dateLink = $xpath->query('.//a', $cells->item(0))->item(0);

        if ($dateLink === null) {
            continue;
        }

        // Seules les vraies lignes de course ont ce titre ; les lignes de
        // détail dépliables et les en-têtes de mois n'en ont pas.
        if (!preg_match('/num.ro\s*:\s*(\d+)/ui', $dateLink->getAttribute('title'), $idMatch)) {
            continue;
        }

        $query = [];
        parse_str((string) parse_url($dateLink->getAttribute('href'), PHP_URL_QUERY), $query);
        // La FFA mélange les casses ("Frmdate_m1" vs "frmdate_m2") : on normalise avant lecture.
        $query = array_change_key_case($query, CASE_LOWER);

        $startDate = local_races_date_from_query($query, '1');

        if ($startDate === null) {
            continue;
        }

        // Les paramètres frmdate_j2/m2/a2 du lien répètent toujours la date de
        // début côté FFA (constaté), même quand le libellé affiché est une plage
        // ("03-04 octobre") : on complète donc avec ce texte plutôt que l'URL.
        $endDate = local_races_end_date_from_label(trim((string) $dateLink->textContent), $startDate);

        $locationCell = $cells->item(2);
        $deptLink = $xpath->query('.//a', $locationCell)->item(0);
        $departmentCode = $deptLink !== null ? trim($deptLink->textContent) : null;

        if ($departmentCode !== $department) {
            continue;
        }

        $cityNode = $locationCell->childNodes->item(0);
        $city = $cityNode !== null ? trim($cityNode->textContent) : null;

        $detailLink = $xpath->query('.//a[@target="_blank"]', $cells->item(6))->item(0);
        $detailUrl = $detailLink !== null ? local_races_absolute_url($detailLink->getAttribute('href')) : null;

        $competitionId = (int) $idMatch[1];

        // Une même compétition peut avoir plusieurs lignes (une par distance/épreuve) :
        // on ne garde que la première rencontrée (la plus complète, constaté sur les
        // doublons : les lignes suivantes ont un niveau vide et pas de lien fiche).
        if (isset($races[$competitionId])) {
            continue;
        }

        $races[$competitionId] = [
            'ffa_competition_id' => $competitionId,
            'title' => trim((string) $cells->item(1)->textContent),
            'start_date' => $startDate,
            'end_date' => $endDate !== $startDate ? $endDate : null,
            'city' => $city,
            'department_code' => $departmentCode,
            'level' => $cells->length > 4 ? trim((string) $cells->item(4)->textContent) : null,
            'detail_url' => $detailUrl,
        ];
    }

    $races = array_values($races);

    return $races;
}

/**
 * Construit une date ISO (Y-m-d) à partir des paramètres frmdate_j/m/a<suffix>
 * d'un lien du calendrier FFA (déjà normalisés en minuscule).
 *
 * @param array  $query  Paramètres de requête normalisés en minuscule
 * @param string $suffix '1' pour la date de début, '2' pour la date de fin
 *
 * @return string|null Date au format 'Y-m-d', ou null si absente/invalide
 */
function local_races_date_from_query(array $query, string $suffix): ?string
{
    $day = $query['frmdate_j' . $suffix] ?? null;
    $month = $query['frmdate_m' . $suffix] ?? null;
    $year = $query['frmdate_a' . $suffix] ?? null;

    if ($day === null || $month === null || $year === null) {
        return null;
    }

    if (!checkdate((int) $month, (int) $day, (int) $year)) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
}

/**
 * Détecte une plage de jours dans le libellé affiché par la FFA (ex:
 * "03-04 octobre") et retourne la date de fin correspondante, dans le même
 * mois/année que la date de début. Ne gère pas les plages à cheval sur deux
 * mois (le libellé FFA ne suit pas de format fiable pour ce cas) : on reste
 * alors sur une course d'un seul jour plutôt que de deviner.
 *
 * @param string $label     Texte du lien de date (ex: "03-04 octobre" ou "04 septembre")
 * @param string $startDate Date de début déjà résolue (format 'Y-m-d')
 *
 * @return string|null Date de fin 'Y-m-d', ou null si le libellé n'indique pas de plage
 */
function local_races_end_date_from_label(string $label, string $startDate): ?string
{
    if (!preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\s+\S+$/u', $label, $m)) {
        return null;
    }

    $startDay = (int) $m[1];
    $endDay = (int) $m[2];

    // Cohérence avec la date de début déjà résolue (sinon on ne comprend pas ce libellé).
    if ($startDay !== (int) substr($startDate, 8, 2)) {
        return null;
    }

    $yearMonth = substr($startDate, 0, 7);

    if (!checkdate((int) substr($yearMonth, 5, 2), $endDay, (int) substr($yearMonth, 0, 4))) {
        return null;
    }

    return $yearMonth . '-' . sprintf('%02d', $endDay);
}

/**
 * Résout un lien de fiche compétition FFA en URL absolue.
 *
 * @param string $href Lien tel qu'extrait de la page (relatif ou absolu)
 *
 * @return string URL absolue
 */
function local_races_absolute_url(string $href): string
{
    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
        return $href;
    }

    return 'https://www.athle.fr' . (str_starts_with($href, '/') ? '' : '/') . $href;
}

/**
 * Compare la version en base d'une course déjà importée avec la version
 * fraîchement récupérée sur la FFA, et retourne les champs qui ont changé.
 *
 * @param array $dbRace     Ligne actuelle en base ('title', 'start_date', 'end_date', 'city')
 * @param array $fetchedRace Course fraîchement parsée
 *
 * @return array<string, array{db: mixed, new: mixed}> Champs modifiés uniquement, vide si identique
 */
function local_races_diff(array $dbRace, array $fetchedRace): array
{
    $diffs = [];

    foreach (['title', 'start_date', 'end_date', 'city'] as $field) {
        $dbValue = $dbRace[$field] ?? null;
        $newValue = $fetchedRace[$field] ?? null;

        if ($dbValue !== $newValue) {
            $diffs[$field] = ['db' => $dbValue, 'new' => $newValue];
        }
    }

    return $diffs;
}

/**
 * Construit l'aperçu (dry-run) d'un import : récupère et parse la page FFA,
 * sépare les courses déjà en base de celles qui seraient nouvelles. Aucune
 * écriture en base.
 *
 * @param int $season Saison FFA à importer
 *
 * @return array{season: int, total: int, new: array, existing: array,
 *               existing_changed: int, removed: array, has_changes: bool}
 *
 * @throws RuntimeException Si le fetch ou le parsing échoue
 */
function build_local_races_import_plan(int $season): array
{
    $html = fetch_ffa_calendar_html($season);
    $races = parse_ffa_calendar_html($html);
    $fetchedIds = array_column($races, 'ffa_competition_id');

    // Toutes les courses déjà en base pour cette saison (pas seulement celles
    // qui matchent le fetch courant) : sert à détecter nouveau/existant, et
    // celles absentes du fetch courant seront proposées à la suppression
    // (course retirée du calendrier FFA, ex: annulation).
    $stmt = app_pdo()->prepare(
        'SELECT ffa_competition_id, title, start_date, end_date, city
         FROM local_races
         WHERE season = :season'
    );
    $stmt->execute(['season' => $season]);
    $inDb = $stmt->fetchAll();
    $inDbIds = array_map('intval', array_column($inDb, 'ffa_competition_id'));

    $inDbById = [];
    foreach ($inDb as $dbRace) {
        $inDbById[(int) $dbRace['ffa_competition_id']] = $dbRace;
    }

    $new = [];
    $existing = [];

    foreach ($races as $race) {
        if (in_array($race['ffa_competition_id'], $inDbIds, true)) {
            $race['diffs'] = local_races_diff($inDbById[$race['ffa_competition_id']], $race);
            $existing[] = $race;
        } else {
            $new[] = $race;
        }
    }

    $removed = [];
    foreach ($inDb as $dbRace) {
        if (!in_array((int) $dbRace['ffa_competition_id'], $fetchedIds, true)) {
            $removed[] = $dbRace;
        }
    }

    $existingChanged = count(array_filter($existing, static fn (array $r): bool => $r['diffs'] !== []));

    return [
        'season' => $season,
        'total' => count($races),
        'new' => $new,
        'existing' => $existing,
        'existing_changed' => $existingChanged,
        'removed' => $removed,
        // true s'il y a au moins une action réelle (ajout, modification ou suppression) :
        // sert à désactiver la confirmation d'import quand tout est déjà à jour.
        'has_changes' => $new !== [] || $existingChanged > 0 || $removed !== [],
    ];
}

/**
 * Exécute réellement l'import d'un plan construit par build_local_races_import_plan() :
 * upsert de chaque course par ffa_competition_id (ré-import idempotent), et
 * supprime celles qui ont disparu du calendrier FFA (ex: course annulée) —
 * cf. plan['removed'].
 *
 * @param array $plan    Plan retourné par build_local_races_import_plan()
 * @param int   $adminId Identifiant de l'admin qui déclenche l'import
 *
 * @return array{created: int, updated: int, deleted: int}
 */
function run_local_races_import(array $plan, int $adminId): array
{
    $pdo = app_pdo();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO local_races
                (ffa_competition_id, title, start_date, end_date, city, department_code, level, detail_url, season, imported_by)
             VALUES
                (:ffa_competition_id, :title, :start_date, :end_date, :city, :department_code, :level, :detail_url, :season, :imported_by)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                city = VALUES(city),
                department_code = VALUES(department_code),
                level = VALUES(level),
                detail_url = VALUES(detail_url),
                season = VALUES(season),
                imported_by = VALUES(imported_by)'
        );

        foreach (array_merge($plan['new'], $plan['existing']) as $race) {
            $stmt->execute([
                'ffa_competition_id' => $race['ffa_competition_id'],
                'title' => $race['title'],
                'start_date' => $race['start_date'],
                'end_date' => $race['end_date'],
                'city' => $race['city'],
                'department_code' => $race['department_code'],
                'level' => $race['level'],
                'detail_url' => $race['detail_url'],
                'season' => $plan['season'],
                'imported_by' => $adminId,
            ]);
        }

        if ($plan['removed'] !== []) {
            $removedIds = array_column($plan['removed'], 'ffa_competition_id');
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $params = array_merge([$plan['season']], $removedIds);
            $pdo->prepare(
                "DELETE FROM local_races WHERE season = ? AND ffa_competition_id IN ($placeholders)"
            )->execute($params);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'created' => count($plan['new']),
        'updated' => count($plan['existing']),
        'deleted' => count($plan['removed']),
    ];
}
