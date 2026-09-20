<?php

declare(strict_types=1);

if (!can_view_detailed_members()) {
    set_flash('danger', 'Accès réservé.');
    redirect_to('home');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    require_valid_csrf('season-members');

    if (($_POST['action'] ?? '') === 'delete_member') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        if ($memberId === (int) (current_user()['id'] ?? 0)) {
            set_flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect_to('season-members');
        }
        try {
            admin_delete_member($memberId);
            set_flash('success', 'Adhérent supprimé.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('season-members');
    }
}

$allYears = get_all_school_years();
$selectedYear = trim((string) ($_GET['year'] ?? ''));

// Par défaut, sélectionne la dernière année (la plus récente).
if ($selectedYear === '' && $allYears !== []) {
    $selectedYear = $allYears[0];
}

$members = [];
if ($selectedYear !== '') {
    $members = get_members_by_school_year($selectedYear);
}

$yearCounts = [];
foreach ($allYears as $year) {
    $yearCounts[] = [
        'year' => $year,
        'count' => count_members_for_school_year($year),
    ];
}

twig_render('pages/members/members-season.twig', [
    'title' => 'Adhérents par saison',
    'members' => $members,
    'allYears' => $allYears,
    'yearCounts' => $yearCounts,
    'selectedYear' => $selectedYear,
]);
