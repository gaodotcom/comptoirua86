<?php

declare(strict_types=1);

/**
 * Anciens membres (administration).
 * Liste les adhérents qui ne sont plus actifs (aucune adhésion pour les années
 * actives) et permet de leur réajouter une adhésion pour les rendre à nouveau actifs.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('inactive-members');

    if (($_POST['action'] ?? '') === 'add_membership') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $schoolYear = trim((string) ($_POST['school_year'] ?? ''));
        $fee = trim((string) ($_POST['fee'] ?? '0'));
        $donation = trim((string) ($_POST['donation'] ?? '0'));

        if ($memberId <= 0) {
            set_flash('danger', 'Membre introuvable.');
            redirect_to('inactive-members');
        }

        // add_membership_for_member renvoie [ok, erreurs] : on affiche toutes les erreurs éventuelles.
        [$ok, $errors] = add_membership_for_member($memberId, [
            'school_year' => $schoolYear,
            'fee' => $fee,
            'donation' => $donation,
        ]);

        if ($ok) {
            set_flash('success', sprintf('Adhésion %s ajoutée. Le membre est maintenant actif.', $schoolYear));
        } else {
            foreach ($errors as $error) {
                set_flash('danger', (string) $error);
            }
        }
        redirect_to('inactive-members');
    }
}

$members = get_inactive_members();
$genericMembers = get_generic_members();
$yearCounts = get_active_year_counts();

twig_render('pages/members/members-inactive.twig', [
    'title' => 'Anciens membres',
    'members' => $members,
    'genericMembers' => $genericMembers,
    'yearCounts' => $yearCounts,
    'activeYears' => get_active_school_years(),
]);
