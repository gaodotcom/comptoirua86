<?php

declare(strict_types=1);

/**
 * Page « adhésion requise » du week-end club (aussi affichée à la place des pages
 * du week-end club quand l'adhésion 2026-2027 est absente, voir require_weekend_2027_membership()).
 */

twig_render('pages/weekend-2027/weekend-club-2027-adhesion.twig', [
    'title' => 'Week-end club — adhésion requise',
    'schoolYear' => WEEKEND_2027_REQUIRED_SCHOOL_YEAR,
]);
