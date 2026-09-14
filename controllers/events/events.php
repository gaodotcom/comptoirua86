<?php

declare(strict_types=1);

/**
 * Liste des événements (administration).
 * Gère aussi la suppression d'un événement via POST. Sépare ensuite les événements
 * à venir des événements passés à partir de leur date de fin.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('events');
    }

    if (($_POST['action'] ?? '') === 'delete_event') {
        try {
            delete_event((int) ($_POST['event_id'] ?? 0));
            set_flash('success', 'Supprimé.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('events');
    }

    // Bascule de la visibilité : 1 -> 0, 0 -> 1.
    if (($_POST['action'] ?? '') === 'toggle_published') {
        try {
            $eid = (int) ($_POST['event_id'] ?? 0);
            $ev = get_event_by_id($eid);
            if ($ev !== null) {
                $ev['published'] = (int) $ev['published'] === 1 ? 0 : 1;
                update_event($eid, $ev);
            }
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('events');
    }
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$allEvents = get_events();
$isAdmin = is_admin();

// Filtrer les événements masqués pour les non-admins
if (!$isAdmin) {
    $allEvents = array_filter($allEvents, fn($e) => (int) ($e['published'] ?? 1) === 1);
}

$upcomingEvents = [];
$pastEvents = [];

// Répartition à venir / passés selon la date de fin (date de début par défaut).
foreach ($allEvents as $event) {
    $e = (string) ($event['end_date'] ?? $event['start_date']);
    if ($e >= $today) {
        $upcomingEvents[] = $event;
    } else {
        $pastEvents[] = $event;
    }
}

// Les événements à venir sont retournés du plus proche au plus lointain.
$upcomingEvents = array_reverse($upcomingEvents);

// --- Calendrier : mois sélectionné et navigation ---
// Mois qui contiennent au moins un événement (futur ou passé).
$eventMonths = [];
foreach ($allEvents as $event) {
    $ym = substr((string) $event['start_date'], 0, 7);
    $eventMonths[$ym] = true;
}
$eventMonthsKeys = array_keys($eventMonths);
sort($eventMonthsKeys);

// Mois courant par défaut, ou celui demandé via ?cal=YYYY-MM
$calMonth = trim((string) ($_GET['cal'] ?? ''));
if ($calMonth === '' || !preg_match('/^\d{4}-\d{2}$/', $calMonth)) {
    $calMonth = date('Y-m');
}

// Flèche précédent : masquée si on est au mois courant
$prevMonth = null;
if ($calMonth > date('Y-m')) {
    $prev = (new DateTimeImmutable($calMonth . '-01'))->modify('-1 month');
    $prevMonth = $prev->format('Y-m');
}

// Flèche suivant : vers le mois suivant (mois par mois, même sans événement)
// tant qu'il y a des événements dans un mois futur
$nextMonth = null;
$maxMonth = $calMonth;
foreach ($eventMonthsKeys as $ym) {
    if ($ym > $maxMonth) {
        $maxMonth = $ym;
    }
}
if ($calMonth < $maxMonth) {
    $next = (new DateTimeImmutable($calMonth . '-01'))->modify('+1 month');
    $nextMonth = $next->format('Y-m');
}

$calYear = substr($calMonth, 0, 4);
$calMonthNum = substr($calMonth, 5, 2);
$calMonthName = (new DateTimeImmutable($calMonth . '-01'))->format('F Y');

// Nom du mois en français
$monthNames = [
    'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
    'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
    'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
    'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre',
];
$enMonth = (new DateTimeImmutable($calMonth . '-01'))->format('F');
$calMonthName = ($monthNames[$enMonth] ?? $enMonth) . ' ' . $calYear;

twig_render(
    'pages/events/events.twig',
    [
    'title' => 'Les événements',
    'upcomingEvents' => $upcomingEvents,
    'pastEvents' => $pastEvents,
    'calYear' => $calYear,
    'calMonth' => $calMonthNum,
    'calMonthName' => $calMonthName,
    'calToday' => date('Y-m-d'),
    'prevMonth' => $prevMonth,
    'nextMonth' => $nextMonth,
    ]
);
