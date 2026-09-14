<?php

declare(strict_types=1);

/**
 * Publication d'une nouvelle semaine d'entraînement (coach).
 * Une image est obligatoire : la semaine ne peut pas être enregistrée sans illustration.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('training-form');
    }

    try {
        // L'image est requise : save_uploaded_image renvoie null si aucun fichier n'est fourni.
        $path = save_uploaded_image($_FILES['image'] ?? [], 'trainings');
        if ($path === null) {
            throw new RuntimeException('Une image est obligatoire.');
        }

        $cu = current_user();
        create_training([
            'week_start' => $_POST['week_start'] ?? '',
            'title' => $_POST['title'] ?? '',
            'presentation_text' => $_POST['presentation_text'] ?? '',
            'comment_text' => $_POST['comment_text'] ?? '',
            'image_path' => $path,
            'created_by' => (int) ($cu['id'] ?? 0),
        ]);
        set_flash('success', 'Semaine d\'entraînement enregistrée.');
        redirect_to('trainings');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect_to('training-form');
    }
}

// Valeur par défaut : le lundi de la semaine courante.
$defaultWeek = (new DateTimeImmutable('monday this week'))->format('Y-m-d');

twig_render('pages/trainings/training-form.twig', [
    'title' => 'Publier une semaine',
    'defaultWeek' => $defaultWeek,
]);
