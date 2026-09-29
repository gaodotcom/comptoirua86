<?php

declare(strict_types=1);

/**
 * Test VMA du vendredi 16 octobre 2026 : chaque adhérent connecté indique
 * s'il participe ou non (modifiable à tout moment).
 */

$memberId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('test-vma-2026');

    $participate = ($_POST['participate'] ?? '') === '1';
    set_test_vma_2026_participation($memberId, $participate);
    set_flash('success', $participate ? 'Votre participation au test VMA est enregistrée.' : 'Vous ne participez pas au test VMA.');
    redirect_to('test-vma-2026');
}

twig_render('pages/test-vma-2026/test-vma-2026.twig', [
    'title' => 'Test VMA du 16 octobre 2026',
    'answer' => get_test_vma_2026_answer($memberId),
]);
