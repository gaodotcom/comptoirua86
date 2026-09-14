<?php

declare(strict_types=1);

/**
 * Sélection des années scolaires "actives" (administration).
 * Les années actives déterminent quels adhérents apparaissent dans la liste détaillée
 * et le trombinoscope : un membre est actif s'il a une adhésion pour au moins une
 * des années cochées ici.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('active-years');
    }

    if (($_POST['action'] ?? '') === 'save') {
        $years = $_POST['years'] ?? [];
        if (!is_array($years)) {
            $years = [];
        }
        // Toutes les valeurs sont normalisées en chaînes avant stockage.
        set_active_school_years(array_map('strval', $years));
        set_flash('success', 'Années actives mises à jour.');
        redirect_to('active-years');
    }
}

$allYears = get_all_school_years();
// array_flip permet une recherche O(1) pour savoir si une année est active.
$activeYears = array_flip(get_active_school_years());

// Enrichit chaque année avec le nombre d'adhérents concernés.
$yearsWithCounts = [];
foreach ($allYears as $year) {
    $yearsWithCounts[] = [
        'year' => $year,
        'active' => isset($activeYears[$year]),
        'member_count' => count_members_for_school_year($year),
    ];
}

twig_render('pages/members/active-years.twig', [
    'title' => 'Années actives',
    'years' => $yearsWithCounts,
    'activeCount' => count($activeYears),
    'totalActiveMembers' => count(get_active_member_ids()),
]);
