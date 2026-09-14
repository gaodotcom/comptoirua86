<?php

declare(strict_types=1);

/**
 * Ajout d'un adhérent (administration).
 * Crée l'adhérent, puis sa photo si fournie, et enregistre une adhésion si une
 * année scolaire a été saisie dans le même formulaire.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('member-add');
    }

    [$ok, $errors] = admin_create_member($_POST);
    if ($ok) {
        // Récupère l'identifiant du nouvel adhérent pour gérer la photo et l'adhésion.
        $memberId = (int) app_pdo()->lastInsertId();
        // La photo est optionnelle : on ne l'enregistre que si un fichier a été envoyé.
        if (!empty($_FILES['photo']['name'])) {
            try {
                $path = save_uploaded_image($_FILES['photo'], 'members', $memberId);
                if ($path !== null) {
                    update_member_photo($memberId, $path);
                }
            } catch (Throwable $e) {
                set_flash('warning', 'Adhérent ajouté, mais la photo n\'a pas pu être enregistrée.');
            }
        }
        // Une adhésion n'est enregistrée que si une année scolaire est renseignée.
        $schoolYear = trim((string) ($_POST['school_year'] ?? ''));
        if ($schoolYear !== '') {
            add_membership_for_member($memberId, $_POST);
        }
        set_flash('success', 'Adhérent ajouté.');
        redirect_to('trombinoscope');
    }

    foreach ($errors as $error) {
        set_flash('danger', (string) $error);
    }
    redirect_to('member-add');
}

twig_render('pages/members/member-add.twig', ['title' => 'Ajouter un adhérent']);
