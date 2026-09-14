<?php

declare(strict_types=1);

/**
 * Trombinoscope : affiche la photo, le prénom et le nom des adhérents actifs.
 * Gère aussi la suppression d'un adhérent (admin only, sauf son propre compte).
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('trombinoscope');
    }

    if (($_POST['action'] ?? '') === 'delete_member') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        // Protection : on ne peut pas supprimer son propre compte.
        if ($memberId === (int) (current_user()['id'] ?? 0)) {
            set_flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect_to('trombinoscope');
        }
        try {
            admin_delete_member($memberId);
            set_flash('success', 'Adhérent supprimé.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect_to('trombinoscope');
    }
}

$members = get_trombinoscope_members();
$yearCounts = get_active_year_counts();
$activeCount = count_active_members();
$renewalPending = get_renewal_pending();
$renewalPendingIds = $renewalPending ? get_renewal_pending_ids() : [];
twig_render('pages/members/trombinoscope.twig', ['title' => 'Trombinoscope', 'members' => $members, 'yearCounts' => $yearCounts, 'activeCount' => $activeCount, 'renewalPending' => $renewalPending, 'renewalPendingIds' => $renewalPendingIds]);
