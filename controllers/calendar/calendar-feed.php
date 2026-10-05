<?php

declare(strict_types=1);

/**
 * Flux iCal des événements UA86 publiés, destiné à l'abonnement d'un agenda
 * externe (Google Calendar...). Google ne pouvant pas se connecter, l'accès
 * est protégé par un jeton secret (CALENDAR_FEED_TOKEN) passé dans l'URL.
 */

$expectedToken = (string) app_config()['calendar_feed_token'];
$givenToken = (string) ($_GET['token'] ?? '');

// Flux désactivé (jeton non configuré) ou jeton invalide : 404, comme une page inconnue.
if ($expectedToken === '' || !hash_equals($expectedToken, $givenToken)) {
    http_response_code(404);
    twig_render('pages/error.twig', ['title' => 'Page introuvable']);
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="agenda-ua86.ics"');
header('Cache-Control: private, max-age=300');

echo build_events_ics(get_published_events());
