<?php

declare(strict_types=1);

/**
 * Liste des courses partagées par les adhérents.
 * Gère la participation (interested/registered) d'un membre à une course et la
 * suppression d'une course (par son auteur ou un admin). Les courses sont séparées
 * en à venir / passées. Le filtre ?filter=mine restreint la liste aux courses que
 * l'utilisateur a créées ou auxquelles il a répondu.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('races');
    }

    $action = (string) ($_POST['action'] ?? '');

    // Enregistre la réponse de participation de l'utilisateur courant.
    if ($action === 'set_response') {
        try {
            set_race_response((int) ($_POST['race_id'] ?? 0), (int) $user['id'], (string) ($_POST['status'] ?? ''));
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('races');
    }

    // Suppression : seul l'auteur ou un admin peut supprimer la course.
    if ($action === 'delete_race') {
        try {
            $rid = (int) ($_POST['race_id'] ?? 0);
            $race = get_race_by_id($rid);
            if ($race !== null && (is_admin() || (int) $race['created_by'] === (int) $user['id'])) {
                delete_race($rid);
                set_flash('success', 'Supprimée.');
            }
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('races');
    }
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$allRaces = get_all_races();
// Filtre optionnel : ne montrer que les courses qui me concernent.
$mineOnly = ($_GET['filter'] ?? '') === 'mine';
$userId = (int) $user['id'];
$upcomingRaces = [];
$pastRaces = [];

// Répartition à venir / passées selon la date de fin (date de début par défaut).
foreach ($allRaces as $race) {
    $re = (string) ($race['end_date'] ?? $race['start_date']);
    if ($re >= $today) {
        $upcomingRaces[] = $race;
    } else {
        $pastRaces[] = $race;
    }
}

// Les courses à venir sont affichées du plus proche au plus lointain.
$upcomingRaces = array_reverse($upcomingRaces);
$upcomingRaceCards = [];
foreach ($upcomingRaces as $race) {
    // En mode "mine", on ne garde que les courses créées par l'utilisateur ou auxquelles il a répondu.
    if ($mineOnly) {
        $createdByMe = (int) $race['created_by'] === $userId;
        $myResp = get_member_race_response((int) $race['id'], $userId);
        $isInvolved = $createdByMe || $myResp !== null;
        if (!$isInvolved) {
            continue;
        }
    }
    $upcomingRaceCards[] = [
        'race' => $race,
        'responses' => get_race_responses((int) $race['id']),
        'myResponse' => get_member_race_response((int) $race['id'], $userId),
        'canEdit' => is_admin() || (int) $race['created_by'] === $userId,
    ];
}

$pastRaceCards = [];
foreach ($pastRaces as $race) {
    if ($mineOnly) {
        $createdByMe = (int) $race['created_by'] === $userId;
        $myResp = get_member_race_response((int) $race['id'], $userId);
        $isInvolved = $createdByMe || $myResp !== null;
        if (!$isInvolved) {
            continue;
        }
    }
    $pastRaceCards[] = [
        'race' => $race,
        'responses' => get_race_responses((int) $race['id']),
        'canEdit' => is_admin() || (int) $race['created_by'] === $userId,
    ];
}

twig_render('pages/races/races.twig', [
    'title' => 'Les courses des ultramicalistes',
    'upcomingRaceCards' => $upcomingRaceCards,
    'pastRaceCards' => $pastRaceCards,
    'mineOnly' => $mineOnly,
]);
