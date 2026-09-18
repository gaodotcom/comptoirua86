<?php

declare(strict_types=1);

/**
 * Calendar — agrégation des courses, événements UA86 et anniversaires
 *
 * Construit la grille d'un mois (semaines du lundi au dimanche, jours des mois
 * adjacents inclus) et y range les éléments de chaque jour, triés par type.
 */

/**
 * Retourne le nom français d'un mois.
 *
 * @param int $month Numéro du mois (1-12)
 *
 * @return string Nom du mois (ex: 'septembre')
 */
function french_month_name(int $month): string
{
    $names = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    return $names[$month] ?? '';
}

/**
 * Formate une date en toutes lettres (ex: 'vendredi 18 septembre 2026').
 *
 * @param DateTimeImmutable $date Date à formater
 *
 * @return string Date en français
 */
function french_long_date(DateTimeImmutable $date): string
{
    $days = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    return $days[(int) $date->format('N')] . ' ' . $date->format('j') . ' '
        . french_month_name((int) $date->format('n')) . ' ' . $date->format('Y');
}

/**
 * Formate une plage de dates pour l'affichage (ex: '18/09/2026 → 20/09/2026').
 *
 * @return string
 */
function format_date_range(string $start, ?string $end): string
{
    if ($end === null || $end === '' || $end === $start) {
        return format_date($start);
    }

    return format_date($start) . ' → ' . format_date($end);
}

/**
 * Ajoute un élément sur chaque jour qu'il couvre, dans la limite de la plage affichée.
 *
 * @param array  $days  Éléments par jour (clé 'Y-m-d'), modifié par référence
 * @param array  $item  Élément à ajouter
 * @param string $start Date de début (Y-m-d)
 * @param string $end   Date de fin (Y-m-d)
 * @param string $from  Premier jour affiché (Y-m-d)
 * @param string $to    Dernier jour affiché (Y-m-d)
 *
 * @return void
 */
function calendar_add_item(array &$days, array $item, string $start, string $end, string $from, string $to): void
{
    $cursor = new DateTimeImmutable(max($start, $from));
    $last = new DateTimeImmutable(min($end, $to));

    while ($cursor <= $last) {
        $key = $cursor->format('Y-m-d');
        $days[$key][] = $item;
        $cursor = $cursor->modify('+1 day');
    }
}

/**
 * Récupère les éléments (événements, courses, anniversaires) entre deux dates.
 *
 * @param string $from Premier jour (Y-m-d)
 * @param string $to   Dernier jour (Y-m-d)
 *
 * @return array Éléments indexés par jour 'Y-m-d', triés événements → courses → anniversaires
 */
function get_calendar_items(string $from, string $to): array
{
    $pdo = app_pdo();
    $days = [];

    // Événements UA86 (les brouillons ne sont visibles que par l'admin).
    $publishedClause = is_admin() ? '' : ' AND published = 1';
    $stmt = $pdo->prepare(
        'SELECT id, title, start_date, end_date, location, published
         FROM events
         WHERE start_date <= :to AND COALESCE(end_date, start_date) >= :from' . $publishedClause . '
         ORDER BY start_date ASC, title ASC'
    );
    $stmt->execute(['from' => $from, 'to' => $to]);

    foreach ($stmt->fetchAll() as $event) {
        $end = (string) ($event['end_date'] ?? $event['start_date']);
        calendar_add_item($days, [
            'type' => 'event',
            'title' => $event['title'],
            'details' => $event['location'],
            'dates' => format_date_range((string) $event['start_date'], $event['end_date']),
            'url' => page_url('events') . '#evenement-' . (int) $event['id'],
            'draft' => (int) $event['published'] !== 1,
        ], (string) $event['start_date'], $end, $from, $to);
    }

    // Courses partagées par les adhérents.
    $stmt = $pdo->prepare(
        'SELECT r.id, r.title, r.start_date, r.end_date, r.location, r.website_url, r.favicon_path,
                (SELECT COUNT(*) FROM race_responses rr WHERE rr.race_id = r.id AND rr.status = \'registered\') AS registered_count
         FROM races r
         WHERE r.start_date <= :to AND COALESCE(r.end_date, r.start_date) >= :from
         ORDER BY r.start_date ASC, r.title ASC'
    );
    $stmt->execute(['from' => $from, 'to' => $to]);

    foreach ($stmt->fetchAll() as $race) {
        $end = (string) ($race['end_date'] ?? $race['start_date']);
        calendar_add_item($days, [
            'type' => 'race',
            'title' => $race['title'],
            'details' => (string) ($race['location'] ?? ''),
            'dates' => format_date_range((string) $race['start_date'], $race['end_date']),
            'url' => page_url('races') . '#course-' . (int) $race['id'],
            'favicon' => race_favicon_url($race),
            'registered' => (int) $race['registered_count'],
        ], (string) $race['start_date'], $end, $from, $to);
    }

    // Anniversaires des adhérents actifs (mêmes critères que le trombinoscope).
    $stmt = $pdo->query(
        'SELECT id, first_name, last_name, date_of_birth
         FROM members
         WHERE deleted_at IS NULL
           AND generic_account = 0
           AND date_of_birth IS NOT NULL
           AND EXISTS (
               SELECT 1 FROM memberships ms
               WHERE ms.member_id = members.id
                 AND ms.school_year IN (SELECT school_year FROM active_school_years)
           )
         ORDER BY first_name ASC'
    );
    $birthdays = $stmt->fetchAll();

    // La plage affichée peut chevaucher deux années (décembre/janvier).
    $years = array_unique([(int) substr($from, 0, 4), (int) substr($to, 0, 4)]);

    foreach ($birthdays as $member) {
        $monthDay = substr((string) $member['date_of_birth'], 5, 5);

        foreach ($years as $year) {
            // Nés un 29 février : fêtés le 28 les années non bissextiles.
            $celebrated = ($monthDay === '02-29' && !checkdate(2, 29, $year)) ? '02-28' : $monthDay;
            $key = sprintf('%04d-%s', $year, $celebrated);

            if ($key < $from || $key > $to) {
                continue;
            }

            $days[$key][] = [
                'type' => 'birthday',
                'title' => $member['first_name'],
                'full_name' => $member['first_name'] . ' ' . $member['last_name'],
            ];
        }
    }

    // Ordre d'affichage dans une journée : événements, courses, anniversaires.
    $order = ['event' => 0, 'race' => 1, 'birthday' => 2];
    foreach ($days as &$items) {
        usort($items, static fn (array $a, array $b): int => $order[$a['type']] <=> $order[$b['type']]);
    }
    unset($items);

    return $days;
}

/**
 * Calcule les bornes de navigation du calendrier : de quand à quand on peut
 * feuilleter les mois avec les flèches précédent/suivant.
 *
 * - Passé : un seul mois en arrière suffit, l'agenda n'a pas vocation d'archive.
 * - Futur : un an par défaut ; au-delà seulement s'il existe un rendez-vous UA86
 *   ou une course déjà programmée plus loin (les anniversaires, qui se répètent
 *   chaque année, ne justifient pas à eux seuls d'aller plus loin).
 *
 * Sert aussi à rejeter au plus tôt (avant tout calcul de grille) une requête
 * ?mois=... hors bornes, qu'elle vienne d'un lien cassé ou d'un robot qui
 * fait défiler des dates au hasard.
 *
 * @return array{0: string, 1: string} [mois minimum, mois maximum] au format 'Y-m'
 */
function get_calendar_navigable_bounds(): array
{
    $today = new DateTimeImmutable('today');
    $min = $today->modify('-1 month')->format('Y-m');
    $max = $today->modify('+12 months')->format('Y-m');

    $farthest = (string) app_pdo()->query(
        'SELECT MAX(ym) FROM (
            SELECT DATE_FORMAT(start_date, "%Y-%m") AS ym FROM events
            UNION ALL
            SELECT DATE_FORMAT(start_date, "%Y-%m") AS ym FROM races
        ) far'
    )->fetchColumn();

    if ($farthest > $max) {
        $max = $farthest;
    }

    return [$min, $max];
}

/**
 * Construit la grille d'un mois : semaines complètes (lundi → dimanche).
 * Les flèches précédent/suivant sont masquées (null) au-delà des bornes
 * renvoyées par get_calendar_navigable_bounds().
 *
 * @param string $yearMonth Mois au format 'Y-m'
 *
 * @return array{weeks: array, label: string, prev: ?string, next: ?string, current: string, monthDays: array}
 */
function build_calendar_month(string $yearMonth): array
{
    $first = new DateTimeImmutable($yearMonth . '-01');
    $lastOfMonth = $first->modify('last day of this month');
    $gridStart = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
    $gridEnd = $lastOfMonth->modify('+' . (7 - (int) $lastOfMonth->format('N')) . ' days');
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    $items = get_calendar_items($gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));

    $weeks = [];
    $monthDays = [];
    $cursor = $gridStart;

    while ($cursor <= $gridEnd) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->format('Y-m-d');
            $day = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'label' => french_long_date($cursor),
                'inMonth' => $cursor->format('Y-m') === $yearMonth,
                'isToday' => $key === $today,
                'isWeekend' => (int) $cursor->format('N') >= 6,
                'items' => $items[$key] ?? [],
            ];
            $week[] = $day;
            // Liste « au programme » (vue mobile) : jours du mois ayant au moins un élément.
            if ($day['inMonth'] && $day['items'] !== []) {
                $monthDays[] = $day;
            }
            $cursor = $cursor->modify('+1 day');
        }
        $weeks[] = $week;
    }

    [$minMonth, $maxMonth] = get_calendar_navigable_bounds();
    $prev = $first->modify('-1 month')->format('Y-m');
    $next = $first->modify('+1 month')->format('Y-m');

    return [
        'weeks' => $weeks,
        'monthDays' => $monthDays,
        'label' => ucfirst(french_month_name((int) $first->format('n'))) . ' ' . $first->format('Y'),
        'prev' => $prev >= $minMonth ? $prev : null,
        'next' => $next <= $maxMonth ? $next : null,
        'current' => $yearMonth,
    ];
}
