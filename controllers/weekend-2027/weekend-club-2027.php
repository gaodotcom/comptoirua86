<?php

declare(strict_types=1);

/**
 * Formulaire de préinscription au week-end club Lozère Trail 2027.
 * Accessible à tout adhérent connecté ; chacun ne peut se préinscrire que
 * pour lui-même. Modifiable tant que les préinscriptions ne sont pas closes.
 */

$memberId = (int) $user['id'];
$closed = weekend_2027_is_closed();
$registration = get_weekend_2027_registration_for_member($memberId);
// Si quelqu'un d'autre a déjà choisi cet adhérent comme coéquipier de duo pour
// l'Ultra Lozère, son choix de course est verrouillé sur ce duo (non modifiable).
$duoLeader = get_weekend_2027_duo_leader_for_member($memberId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('weekend-club-2027');

    if ($closed) {
        set_flash('danger', 'Les préinscriptions sont closes.');
        redirect_to('weekend-club-2027');
    }

    if (($_POST['action'] ?? '') === 'delete_registration') {
        delete_weekend_2027_registration($memberId);
        set_flash('success', 'Votre préinscription a été supprimée.');
        redirect_to('weekend-club-2027');
    }

    // Le choix de course est imposé par le leader du duo : on ignore ce que le
    // formulaire aurait pu poster pour ces champs (ils y sont désactivés côté
    // vue, mais on ne fait pas confiance au client).
    if ($duoLeader !== null) {
        $_POST['courses'] = ['ultra'];
        $_POST['team_mode'] = 'duo';
        $_POST['duo_partner_member_id'] = (string) $duoLeader['member_id'];
        $_POST['bivouac'] = $duoLeader['bivouac'] ? '1' : '';
    }

    try {
        save_weekend_2027_registration($memberId, $_POST);
        set_flash('success', 'Votre préinscription a bien été enregistrée.');
        redirect_to('weekend-club-2027');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
}

// Membres pouvant être choisis comme coéquipier : adhérents d'une saison active
// uniquement (pas les comptes génériques), hors soi-même.
$activeMemberIds = array_diff(get_active_member_ids(), [$memberId]);
$membersForDuoSelect = get_members_by_ids($activeMemberIds);

// Contacts référents du week-end club (Anne-Charlotte et Brice).
$contacts = array_values(array_filter([
    get_member_by_id(4),
    get_member_by_id(13),
]));

// La course du dimanche choisie (trail2r/trail27/salta), ou '' si aucune : sert à
// pré-cocher le bon radio (dont "Aucune") en édition, sans logique complexe côté twig.
$sundaySelection = '';
if ($registration !== null) {
    $sundayCourse = array_intersect($registration['courses'], ['trail2r', 'trail27', 'salta']);
    $sundaySelection = $sundayCourse !== [] ? (string) reset($sundayCourse) : '';
}

twig_render('pages/weekend-2027/weekend-club-2027.twig', [
    'title' => 'Week-end club Ultramical86 au Lozère Trail',
    'courses' => weekend_2027_courses(),
    'closed' => $closed,
    'registration' => $registration,
    'membersForDuoSelect' => $membersForDuoSelect,
    'sundaySelection' => $sundaySelection,
    'duoLeader' => $duoLeader,
    'contacts' => $contacts,
]);
