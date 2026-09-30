<?php

declare(strict_types=1);

/**
 * Liste des participants au test VMA du 16 octobre 2026.
 * Visible par tout adhérent connecté (avatar, nom, prénom), le sien en premier ; les coordonnées,
 * l'export CSV et la clôture/réouverture du formulaire sont réservés aux admins.
 */

require_test_vma_2026_membership($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('test-vma-2026-inscrits');

    if (($_POST['action'] ?? '') === 'toggle_closed' && is_admin()) {
        $closeNow = !test_vma_2026_is_closed();
        test_vma_2026_set_closed($closeNow);
        set_flash('success', $closeNow ? 'Formulaire clôturé.' : 'Formulaire rouvert.');
        redirect_to('test-vma-2026-inscrits');
    }
}

$participants = get_test_vma_2026_participants();

if (is_admin() && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="test-vma-2026-participants.csv"');

    $output = fopen('php://output', 'w');
    // BOM UTF-8 pour un affichage correct des accents dans Excel.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, ['Nom', 'Prénom', 'Email', 'Téléphone'], ';');

    foreach ($participants as $p) {
        fputcsv($output, [$p['last_name'], $p['first_name'], $p['email'] ?? '', $p['phone'] ?? ''], ';');
    }

    fclose($output);
    exit;
}

// Sa propre participation (si elle existe) en premier dans la liste. Tri stable
// (PHP 8+) : l'ordre du dernier inscrit au premier inscrit est conservé pour
// tous les autres.
$memberId = (int) $user['id'];
usort($participants, static function (array $a, array $b) use ($memberId): int {
    $aIsMe = (int) $a['member_id'] === $memberId;
    $bIsMe = (int) $b['member_id'] === $memberId;

    if ($aIsMe === $bIsMe) {
        return 0;
    }

    return $aIsMe ? -1 : 1;
});

twig_render('pages/test-vma-2026/test-vma-2026-inscrits.twig', [
    'title' => 'Test VMA 2026 — Participants',
    'participants' => $participants,
    'closed' => test_vma_2026_is_closed(),
]);
