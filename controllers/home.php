<?php

declare(strict_types=1);

/**
 * Page d'accueil publique.
 * Compile les derniers contenus visibles par un adhérent connecté :
 * entraînement le plus récent, prochains anniversaires, événements à venir / passés,
 * actualités publiées et prochaines courses.
 */

// Dernier entraînement publié (le plus récent seulement).
$trainings = get_last_trainings(1);
$lastTraining = !empty($trainings) ? $trainings[0] : null;
// 8 prochains anniversaires d'adhérents.
$birthdays = get_next_birthdays(5);

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$allEvents = get_events();
if (!is_admin()) {
	$allEvents = array_filter($allEvents, fn ($e) => (int) ($e['published'] ?? 1) === 1);
}
$upcomingEvents = [];
$pastEvents = [];

// Sépare les événements selon qu'ils sont à venir ou passés,
// en se basant sur la date de fin (date de début par défaut).
foreach ($allEvents as $event) {
	$eventEnd = (string) ($event['end_date'] ?? $event['start_date']);
	if ($eventEnd >= $today) {
		$upcomingEvents[] = $event;
	} else {
		$pastEvents[] = $event;
	}
}

// On n'affiche que le dernier événement passé, et les à venir du plus proche au plus lointain.
$recentPastEvents = array_slice(array_reverse($pastEvents), 0, 1);
$upcomingEvents = array_reverse($upcomingEvents);

$publishedNews = get_published_news(3);
$totalPublishedNews = count(get_published_news());

$upcomingRaces = get_upcoming_races(3);

// Enrichit chaque course avec les inscrits (prénom + initiale du nom)
foreach ($upcomingRaces as &$race) {
	$responses = get_race_responses((int) $race['id']);
	$registered = [];
	foreach ($responses['registered'] as $member) {
		$registered[] = $member['first_name'] . ' ' . mb_substr($member['last_name'], 0, 1) . '.';
	}
	$race['registered_names'] = implode(', ', $registered);
}
unset($race);

twig_render(
	'pages/home.twig',
	[
	'title' => 'Accueil',
	'lastTraining' => $lastTraining,
	'birthdays' => $birthdays,
	'recentPastEvents' => $recentPastEvents,
	'upcomingEvents' => $upcomingEvents,
	'eventsCount' => count($upcomingEvents),
	'publishedNews' => $publishedNews,
	'totalPublishedNews' => $totalPublishedNews,
	'upcomingRaces' => $upcomingRaces,
	'racesCount' => count($upcomingRaces),
	]
);
