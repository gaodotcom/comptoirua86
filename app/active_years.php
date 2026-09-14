<?php

declare(strict_types=1);

/**
 * Active school years — gestion des années actives et de l'activité des membres
 *
 * Un membre est « actif » s'il a accès au site et apparaît dans le trombinoscope
 * et la liste des adhérents. La règle :
 * - Les non-adhérents (admin, coach, bureau) et les comptes génériques sont
 *   toujours actifs.
 * - Un adhérent est actif s'il a au moins une adhésion dans une année scolaire
 *   marquée comme active.
 *
 * Désactiver une année (décocher) désactive immédiatement tous les adhérents
 * qui n'ont renouvelé dans aucune autre année active. Un adhérent désactivé
 * qui reprend une adhésion (via import HelloAsso) redevient actif automatiquement.
 */

/**
 * Récupère la liste des années scolaires actives.
 *
 * @return array Liste triée par ordre décroissant (ex: ['2026-2027', '2025-2026'])
 */
function get_active_school_years(): array
{
    $rows = app_pdo()->query('SELECT school_year FROM active_school_years ORDER BY school_year DESC')->fetchAll(PDO::FETCH_COLUMN);

    return array_map('strval', $rows);
}

/**
 * Indique si une année scolaire est active.
 *
 * @param string $schoolYear Année scolaire (ex: '2026-2027')
 *
 * @return bool
 */
function is_school_year_active(string $schoolYear): bool
{
    $stmt = app_pdo()->prepare('SELECT 1 FROM active_school_years WHERE school_year = :sy LIMIT 1');
    $stmt->execute([':sy' => $schoolYear]);

    return $stmt->fetch() !== false;
}

/**
 * Remplace l'ensemble des années actives par la liste fournie.
 * Toutes les années non présentes dans la liste sont désactivées.
 *
 * @param array $years Liste d'années scolaires à activer
 *
 * @return void
 */
function set_active_school_years(array $years): void
{
    $pdo = app_pdo();
    // Transaction : on remplace intégralement le jeu d'années actives d'un seul coup,
    // de façon atomique (soit tout réussit, soit rien ne change).
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM active_school_years');

        if ($years !== []) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO active_school_years (school_year) VALUES (?)');
            foreach ($years as $year) {
                $year = trim((string) $year);
                // On n'insère que les années au format AAAA-AAAA (les autres sont ignorées).
                if (preg_match('/^\d{4}-\d{4}$/', $year)) {
                    $stmt->execute([$year]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Récupère toutes les années scolaires connues en base : celles présentes
 * dans les adhésions ET dans les campagnes HelloAsso.
 *
 * @return array Liste triée par ordre décroissant
 */
function get_all_school_years(): array
{
    $sql = '
        SELECT DISTINCT school_year FROM (
            SELECT school_year FROM memberships
            UNION
            SELECT school_year FROM helloasso_campaigns
        ) AS combined
        ORDER BY school_year DESC
    ';

    $rows = app_pdo()->query($sql)->fetchAll(PDO::FETCH_COLUMN);

    return array_map('strval', $rows);
}

/**
 * Récupère le nombre d'adhérents actifs pour une année scolaire donnée
 * (adhérents ayant une adhésion cette année-là, role='adherent', generic_account=0).
 *
 * @param string $schoolYear Année scolaire
 *
 * @return int
 */
function count_members_for_school_year(string $schoolYear): int
{
    $sql = '
        SELECT COUNT(DISTINCT m.id)
        FROM members m
        INNER JOIN memberships ms ON ms.member_id = m.id
        WHERE m.deleted_at IS NULL
          AND m.generic_account = 0
          AND ms.school_year = :sy
    ';
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute([':sy' => $schoolYear]);

    return (int) $stmt->fetchColumn();
}

/**
 * Détermine si un membre est actif (pour le contrôle d'accès au site).
 *
 * Règle :
 * - null/supprimé → inactif
 * - role != 'adherent' (admin, coach, bureau) → toujours actif (peut gérer le site)
 * - generic_account = 1 → toujours actif
 * - sinon → actif si une de ses adhésions est dans une année active
 *
 * Note : pour le trombinoscope et les compteurs, on utilise get_active_member_ids()
 * qui ne fait pas cette exemption — un admin/coach/bureau n'y figure que s'il a
 * une adhésion active.
 *
 * @param int $memberId Identifiant du membre
 *
 * @return bool
 */
function is_member_active(int $memberId): bool
{
    $stmt = app_pdo()->prepare('SELECT role, generic_account FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':id' => $memberId]);
    $member = $stmt->fetch();

    if ($member === false) {
        return false;
    }

    if ($member['role'] !== 'adherent' || (int) $member['generic_account'] === 1) {
        return true;
    }

    $activeYears = get_active_school_years();

    if ($activeYears === []) {
        return false;
    }

    // Construction dynamique de la liste IN (?, ?, ...) pour les années actives.
    $placeholders = implode(',', array_fill(0, count($activeYears), '?'));
    $stmt = app_pdo()->prepare(
        'SELECT 1 FROM memberships WHERE member_id = ? AND school_year IN (' . $placeholders . ') LIMIT 1'
    );
    // Le premier paramètre est l'ID membre, suivent les années actives.
    $stmt->execute(array_merge([$memberId], $activeYears));

    return $stmt->fetch() !== false;
}

/**
 * Compte le nombre d'adhérents actifs (role='adherent', generic_account=0,
 * ayant une adhésion dans une année active). Les non-adhérents (admin, coach,
 * bureau) ne sont pas comptés.
 *
 * @return int
 */
function count_active_members(): int
{
    return count(get_active_member_ids());
}

/**
 * Récupère le nombre d'adhérents par année active, trié par année décroissante.
 * Ne compte que les role='adherent', generic_account=0, non supprimés.
 *
 * @return array Liste de ['year' => '2026-2027', 'count' => 43]
 */
function get_active_year_counts(): array
{
    $activeYears = get_active_school_years();

    if ($activeYears === []) {
        return [];
    }

    $result = [];
    foreach ($activeYears as $year) {
        $result[] = [
            'year' => $year,
            'count' => count_members_for_school_year($year),
        ];
    }

    return $result;
}

/**
 * Récupère les IDs des membres adhérents actifs (role='adherent',
 * generic_account=0, ayant une adhésion dans une année active).
 * Les non-adhérents ne sont pas inclus (ils sont toujours actifs).
 *
 * @return array<int,int> Set des IDs actifs
 */
function get_active_member_ids(): array
{
    $activeYears = get_active_school_years();

    if ($activeYears === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($activeYears), '?'));
    $sql = '
        SELECT DISTINCT m.id
        FROM members m
        WHERE m.deleted_at IS NULL
          AND m.generic_account = 0
          AND EXISTS (
            SELECT 1 FROM memberships ms
            WHERE ms.member_id = m.id AND ms.school_year IN (' . $placeholders . ')
          )
    ';
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($activeYears);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Récupère le nombre d'adhérents en attente de renouvellement :
 * adhérents qui ont une adhésion dans une saison active mais PAS dans
 * la saison active la plus récente.
 *
 * @return array ['year' => '2026-2027', 'count' => 12] ou null si < 2 saisons actives
 */
function get_renewal_pending(): ?array
{
    $activeYears = get_active_school_years();

    if (count($activeYears) < 2) {
        return null;
    }

    $latestYear = $activeYears[0];
    $olderYears = array_slice($activeYears, 1);

    // Adhérents qui ont une adhésion dans une ancienne saison active
    // mais PAS dans la saison la plus récente.
    $olderPlaceholders = implode(',', array_fill(0, count($olderYears), '?'));
    $sql = '
        SELECT COUNT(DISTINCT m.id)
        FROM members m
        WHERE m.deleted_at IS NULL
          AND m.generic_account = 0
          AND EXISTS (
            SELECT 1 FROM memberships ms
            WHERE ms.member_id = m.id AND ms.school_year IN (' . $olderPlaceholders . ')
          )
          AND NOT EXISTS (
            SELECT 1 FROM memberships ms
            WHERE ms.member_id = m.id AND ms.school_year = ?
          )
    ';
    $params = array_merge($olderYears, [$latestYear]);
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);

    return [
        'year' => $latestYear,
        'count' => (int) $stmt->fetchColumn(),
    ];
}

/**
 * Récupère les IDs des adhérents en attente de renouvellement :
 * adhérents qui ont une adhésion dans une ancienne saison active
 * mais PAS dans la saison active la plus récente.
 *
 * @return array<int,int> Liste des IDs
 */
function get_renewal_pending_ids(): array
{
    $activeYears = get_active_school_years();

    if (count($activeYears) < 2) {
        return [];
    }

    $latestYear = $activeYears[0];
    $olderYears = array_slice($activeYears, 1);

    $olderPlaceholders = implode(',', array_fill(0, count($olderYears), '?'));
    $sql = '
        SELECT DISTINCT m.id
        FROM members m
        WHERE m.deleted_at IS NULL
          AND m.generic_account = 0
          AND EXISTS (
            SELECT 1 FROM memberships ms
            WHERE ms.member_id = m.id AND ms.school_year IN (' . $olderPlaceholders . ')
          )
          AND NOT EXISTS (
            SELECT 1 FROM memberships ms
            WHERE ms.member_id = m.id AND ms.school_year = ?
          )
    ';
    $params = array_merge($olderYears, [$latestYear]);
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
