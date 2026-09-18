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
	$allEvents = array_filter($allEvents, fn ($e) => (int) ($e['published'] ?? 1) === 1);
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

twig_render(
	'pages/events/events.twig',
	[
	'title' => 'L\'agenda UA86',
	'upcomingEvents' => $upcomingEvents,
	'pastEvents' => $pastEvents,
	]
);
