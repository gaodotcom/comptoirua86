<?php

declare(strict_types=1);

/**
 * Liste des séances d'entraînement publiées par le coach.
 * Gère aussi la suppression d'une séance (réservée à ceux qui peuvent gérer les entraînements).
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can_manage_trainings()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('trainings');
    }

    if (($_POST['action'] ?? '') === 'delete_training') {
        try {
            delete_training((int) ($_POST['training_id'] ?? 0));
            set_flash('success', 'Entraînement supprimé.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('trainings');
    }
}

$allTrainings = get_last_trainings(100);

twig_render('pages/trainings/trainings.twig', [
    'title' => 'Les séances du coach',
    'allTrainings' => $allTrainings,
]);
