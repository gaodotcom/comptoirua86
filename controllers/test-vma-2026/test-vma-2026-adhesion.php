<?php

declare(strict_types=1);

/**
 * Page « adhésion requise » du test VMA (aussi affichée à la place des pages du
 * test quand l'adhésion 2026-2027 est absente, voir require_test_vma_2026_membership()).
 */

twig_render('pages/test-vma-2026/test-vma-2026-adhesion.twig', [
    'title' => 'Test VMA — adhésion requise',
    'schoolYear' => TEST_VMA_2026_REQUIRED_SCHOOL_YEAR,
]);
