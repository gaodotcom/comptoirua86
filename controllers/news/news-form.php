<?php

declare(strict_types=1);

/**
 * Formulaire d'ajout / modification d'une actualité (administration).
 * En création, l'actualité est rattachée à l'administrateur connecté.
 */

// ?id>0 indique une édition, sinon c'est une création.
$newsId = (int) ($_GET['id'] ?? 0);
$isEditing = $newsId > 0;
$editingNews = $isEditing ? get_news_by_id($newsId) : null;

if ($isEditing && $editingNews === null) {
    set_flash('danger', 'Introuvable.');
    redirect_to('news');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('news-form', $isEditing ? ['id' => $newsId] : []);
    }

    try {
        if ($isEditing) {
            update_news($newsId, $_POST);
            set_flash('success', 'Modifiée.');
        } else {
            $p = $_POST;
            $p['created_by'] = (int) (current_user()['id'] ?? 0);
            create_news($p);
            set_flash('success', 'Ajoutée.');
        }
        redirect_to('news');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect_to('news-form', $isEditing ? ['id' => $newsId] : []);
    }
}

twig_render('pages/news/news-form.twig', [
    'title' => $isEditing ? 'Modifier l\'actualité' : 'Ajouter une actualité',
    'isEditing' => $isEditing,
    'newsId' => $newsId,
    'newsTitle' => $isEditing ? (string) $editingNews['title'] : '',
    'newsContent' => $isEditing ? (string) $editingNews['content'] : '',
    'newsPublished' => $isEditing ? (int) $editingNews['published'] === 1 : true,
]);
