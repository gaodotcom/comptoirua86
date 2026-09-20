<?php

declare(strict_types=1);

/**
 * Liste des actualités (administration).
 * Gère la suppression et la bascule publié / non publié via POST.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('news');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_news') {
        try {
            delete_news((int) ($_POST['news_id'] ?? 0));
            set_flash('success', 'Supprimée.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('news');
    }

    // Bascule de la visibilité : 1 -> 0, 0 -> 1.
    if ($action === 'toggle_published') {
        try {
            $nid = (int) ($_POST['news_id'] ?? 0);
            $ni = get_news_by_id($nid);
            if ($ni !== null) {
                $ni['published'] = (int) $ni['published'] === 1 ? 0 : 1;
                update_news($nid, $ni);
            }
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('news');
    }

    if ($action === 'move_news_up' || $action === 'move_news_down') {
        try {
            move_news((int) ($_POST['news_id'] ?? 0), $action === 'move_news_up' ? 'up' : 'down');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('news');
    }
}

$allNews = get_all_news();

twig_render('pages/news/news.twig', [
    'title' => 'Liste des actualités',
    'allNews' => $allNews,
]);
