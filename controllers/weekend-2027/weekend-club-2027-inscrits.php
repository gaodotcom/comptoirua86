<?php

declare(strict_types=1);

/**
 * Liste des préinscriptions au week-end club Lozère Trail 2027.
 * Accessible à tout adhérent connecté : avatar, nom, prénom et course(s)
 * uniquement. L'export CSV et les infos complémentaires (formule, bivouac,
 * taille de maillot, hébergement...), ainsi que la clôture/réouverture des
 * préinscriptions, sont réservés aux admins.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('weekend-club-2027-inscrits');

    if (($_POST['action'] ?? '') === 'toggle_closed' && is_admin()) {
        weekend_2027_set_closed(!weekend_2027_is_closed(), (int) $user['id']);
        set_flash('success', weekend_2027_is_closed() ? 'Préinscriptions clôturées.' : 'Préinscriptions rouvertes.');
        redirect_to('weekend-club-2027-inscrits');
    }
}

$registrations = get_all_weekend_2027_registrations();

if (is_admin() && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="week-end-club-2027-inscriptions.csv"');

    $output = fopen('php://output', 'w');
    // BOM UTF-8 pour un affichage correct des accents dans Excel.
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Nom', 'Prénom', 'Téléphone', 'Email',
        'Courses', 'Formule', 'Coéquipier duo', 'Bivouac',
        'Taille maillot', 'Contact urgence - nom', 'Contact urgence - téléphone',
        'Hébergement', 'Licence / PPS', 'Préinscrit le',
    ], ';');

    foreach ($registrations as $r) {
        $courseLabels = array_map('weekend_2027_course_label', $r['courses']);
        $duoPartner = $r['duo_partner_first_name'] !== null
            ? $r['duo_partner_first_name'] . ' ' . $r['duo_partner_last_name']
            : '';

        fputcsv($output, [
            $r['member_last_name'],
            $r['member_first_name'],
            $r['member_phone'] ?? '',
            $r['member_email'] ?? '',
            implode(', ', $courseLabels),
            $r['team_mode'] === 'duo' ? 'Duo' : ($r['team_mode'] === 'solo' ? 'Solo' : ''),
            $duoPartner,
            $r['bivouac'] ? 'Oui' : 'Non',
            $r['shirt_size'],
            $r['emergency_contact_name'],
            $r['emergency_contact_phone'],
            $r['accommodation'] === 'group' ? 'Avec Ultramical86' : 'Indépendant',
            $r['license_number'],
            $r['created_at'],
        ], ';');
    }

    fclose($output);
    exit;
}

// Sa propre préinscription (si elle existe) en premier dans la liste — pratique
// comme rappel/confirmation de ce qu'on a soi-même choisi. Tri stable (PHP 8+) :
// l'ordre alphabétique existant est conservé pour tous les autres.
$memberId = (int) $user['id'];
usort($registrations, static function (array $a, array $b) use ($memberId): int {
    $aIsMe = (int) $a['member_id'] === $memberId;
    $bIsMe = (int) $b['member_id'] === $memberId;

    if ($aIsMe === $bIsMe) {
        return 0;
    }

    return $aIsMe ? -1 : 1;
});

twig_render('pages/weekend-2027/weekend-club-2027-inscrits.twig', [
    'title' => 'Week-end club 2027 — Inscrits',
    'registrations' => $registrations,
    'closed' => weekend_2027_is_closed(),
]);
