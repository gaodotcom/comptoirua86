<?php

declare(strict_types=1);

/**
 * Formulaire d'ajout / modification d'une course.
 * Accessible à tout adhérent connecté pour créer ; en édition, seul l'auteur
 * ou un admin peut modifier. Un admin peut en plus réassigner l'auteur (created_by).
 */

// ?id>0 indique une édition, sinon c'est une création.
$raceId = (int) ($_GET['id'] ?? 0);
$isEditing = $raceId > 0;
$editingRace = $isEditing ? get_race_by_id($raceId) : null;

if ($isEditing && $editingRace === null) {
    set_flash('danger', 'Introuvable.');
    redirect_to('races');
}

// En édition, on refuse l'accès si l'utilisateur n'est ni auteur ni admin.
if ($isEditing && !is_admin() && (int) $editingRace['created_by'] !== (int) $user['id']) {
    set_flash('danger', 'Accès refusé.');
    redirect_to('races');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('race-form', $isEditing ? ['id' => $raceId] : []);
    }

    try {
        if ($isEditing) {
            $p = $_POST;
            // Seul un admin peut changer l'auteur d'une course.
            if (is_admin()) {
                $p['created_by'] = (int) ($_POST['created_by'] ?? 0);
            }
            update_race($raceId, $p);
            set_flash('success', 'Modifiée.');
        } else {
            $p = $_POST;
            $p['created_by'] = (int) $user['id'];
            create_race($p);
            set_flash('success', 'Ajoutée.');
        }
        redirect_to('races');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect_to('race-form', $isEditing ? ['id' => $raceId] : []);
    }
}

// Liste des membres pour le sélecteur d'auteur (admin uniquement, en édition).
$membersForSelect = [];
if ($isEditing && is_admin()) {
    $membersForSelect = app_pdo()->query(
        'SELECT id, first_name, last_name FROM members WHERE deleted_at IS NULL ORDER BY first_name ASC, last_name ASC'
    )->fetchAll();
}

twig_render('pages/races/race-form.twig', [
    'title' => $isEditing ? 'Modifier la course' : 'Partager une course',
    'isEditing' => $isEditing,
    'raceId' => $raceId,
    'raceTitle' => $isEditing ? (string) $editingRace['title'] : '',
    'startDate' => $isEditing ? (string) $editingRace['start_date'] : '',
    'endDate' => $isEditing ? (string) ($editingRace['end_date'] ?? '') : '',
    'location' => $isEditing ? (string) ($editingRace['location'] ?? '') : '',
    'distances' => $isEditing ? (string) $editingRace['distances'] : '',
    'websiteUrl' => $isEditing ? (string) ($editingRace['website_url'] ?? '') : '',
    'registrationInfo' => $isEditing ? (string) ($editingRace['registration_info'] ?? '') : '',
    'membersForSelect' => $membersForSelect,
    'currentCreatedBy' => $isEditing ? (int) ($editingRace['created_by'] ?? 0) : 0,
]);
