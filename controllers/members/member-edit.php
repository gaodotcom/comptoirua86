<?php

declare(strict_types=1);

/**
 * Modification d'un adhérent (administration).
 * Met à jour les informations, gère la photo (suppression ou remplacement) et
 * enregistre une nouvelle adhésion si une année scolaire est fournie.
 */

$memberId = (int) ($_GET['id'] ?? 0);
if ($memberId <= 0) {
    set_flash('danger', 'Adhérent introuvable.');
    redirect_to('trombinoscope');
}

$editingMember = get_member_by_id($memberId);
if ($editingMember === null) {
    set_flash('danger', 'Adhérent introuvable.');
    redirect_to('trombinoscope');
}

// Historique des adhésions affiché dans le formulaire d'édition.
$memberMemberships = get_member_memberships($memberId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('member-edit', ['id' => $memberId]);
    }

    [$ok, $errors] = admin_update_member($memberId, $_POST);
    if ($ok) {
        // Gestion de la photo : on supprime l'ancienne si demande de suppression
        // ou si une nouvelle photo est fournie (remplacement).
        $deletePhoto = !empty($_POST['delete_photo']);
        $hasNewPhoto = !empty($_FILES['photo']['name']);

        if ($deletePhoto || $hasNewPhoto) {
            $currentPhoto = $editingMember['photo_path'] ?? '';
            if ($currentPhoto !== '') {
                delete_uploaded_file($currentPhoto);
                update_member_photo($memberId, '');
            }
        }

        // Enregistrement de la nouvelle photo le cas échéant.
        if ($hasNewPhoto) {
            try {
                $path = save_uploaded_image($_FILES['photo'], 'members', $memberId);
                if ($path !== null) {
                    update_member_photo($memberId, $path);
                }
            } catch (Throwable $e) {
                set_flash('warning', 'Adhérent mis à jour, mais la photo n\'a pas pu être enregistrée.');
            }
        }

        // Une adhésion n'est enregistrée que si une année scolaire est renseignée.
        $schoolYear = trim((string) ($_POST['school_year'] ?? ''));
        if ($schoolYear !== '') {
            add_membership_for_member($memberId, $_POST);
        }
        set_flash('success', 'Adhérent mis à jour.');
        redirect_to('trombinoscope');
    }

    foreach ($errors as $error) {
        set_flash('danger', (string) $error);
    }
    redirect_to('member-edit', ['id' => $memberId]);
}

twig_render('pages/members/member-edit.twig', [
    'title' => 'Modifier l\'adhérent',
    'editingMember' => $editingMember,
    'memberId' => $memberId,
    'memberMemberships' => $memberMemberships,
]);
