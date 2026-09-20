<?php

declare(strict_types=1);

/**
 * Modification d'une semaine d'entraînement existante (coach).
 * L'image n'est obligatoire qu'à la création : ici, si aucun nouveau fichier n'est
 * fourni, on conserve l'image existante.
 */

$trainingId = (int) ($_GET['id'] ?? 0);
if ($trainingId <= 0) {
    set_flash('danger', 'Introuvable.');
    redirect_to('trainings');
}

$training = get_training_by_id($trainingId);
if ($training === null) {
    set_flash('danger', 'Introuvable.');
    redirect_to('trainings');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('training-edit', ['id' => $trainingId]);

    try {
        // Nouvelle image optionnelle : on garde l'ancienne si rien n'est envoyé.
        $newPath = save_uploaded_image($_FILES['image'] ?? [], 'trainings');
        $imagePath = $newPath ?? (string) $training['image_path'];
        update_training($trainingId, [
            'week_start' => $_POST['week_start'] ?? '',
            'title' => $_POST['title'] ?? '',
            'presentation_text' => $_POST['presentation_text'] ?? '',
            'comment_text' => $_POST['comment_text'] ?? '',
            'image_path' => $imagePath,
        ]);
        set_flash('success', 'Modifiée.');
        redirect_to('trainings');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect_to('training-edit', ['id' => $trainingId]);
    }
}

twig_render('pages/trainings/training-edit.twig', [
    'title' => 'Modifier la semaine',
    'training' => $training,
    'trainingId' => $trainingId,
]);
