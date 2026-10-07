<?php

declare(strict_types=1);

/**
 * Archives des courses terminées par saison (1er septembre → 31 août), réservées
 * au bureau et aux admins, de la plus ancienne à la plus récente. Par défaut, la saison en cours ; ?saison=AAAA-AAAA choisit une
 * saison, parmi celles qui ont des courses en base.
 */

$seasons = get_race_seasons();
$currentSeason = race_season_for_date(new DateTimeImmutable('today'));

$season = (string) ($_GET['saison'] ?? $currentSeason);
if (!in_array($season, $seasons, true)) {
    $season = $currentSeason;
}

[$from, $to] = race_season_bounds($season);
$userId = (int) $user['id'];

$raceCards = [];
foreach (get_past_races_between($from, $to) as $race) {
    $raceCards[] = [
        'race' => $race,
        'responses' => get_race_responses((int) $race['id']),
        'canEdit' => is_admin() || (int) $race['created_by'] === $userId,
    ];
}

twig_render('pages/races/races-archives.twig', [
    'title' => 'Archives des courses',
    'seasons' => $seasons,
    'season' => $season,
    'raceCards' => $raceCards,
]);
