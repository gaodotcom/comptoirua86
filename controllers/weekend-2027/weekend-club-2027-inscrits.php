<?php

declare(strict_types=1);

/**
 * Liste des préinscriptions au week-end club Lozère Trail 2027.
 * Accessible à tout adhérent connecté : avatar, nom, prénom et course(s)
 * uniquement. L'export CSV et les infos complémentaires (formule, bivouac,
 * taille de maillot, hébergement...), ainsi que la clôture/réouverture des
 * préinscriptions, sont réservés aux admins.
 */

require_weekend_2027_membership($user);

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
        'Nom', 'Prénom', 'Date de naissance', 'Genre (H/F)', 'Nationalité',
        'Email', 'Tél.', 'Adresse', 'Code postal', 'Ville', 'Pays',
        'Code promo', 'Option annulation',
        'Courses', 'Formule', 'Coéquipier duo', 'Bivouac',
        'Repas dimanche midi',
        'Taille maillot', 'Contact urgence - nom', 'Contact urgence - téléphone',
        'Hébergement', 'Licence / PPS', 'Préinscrit le', 'Dernière mise à jour',
    ], ';');

    foreach ($registrations as $r) {
        $courseLabels = array_map('weekend_2027_course_label', $r['courses']);
        $duoPartner = $r['duo_partner_first_name'] !== null
            ? $r['duo_partner_first_name'] . ' ' . $r['duo_partner_last_name']
            : '';
        $dateOfBirth = $r['member_date_of_birth'] !== null
            ? (DateTimeImmutable::createFromFormat('Y-m-d', $r['member_date_of_birth']) ?: null)
            : null;

        fputcsv($output, [
            $r['member_last_name'],
            $r['member_first_name'],
            $dateOfBirth !== null ? $dateOfBirth->format('d/m/Y') : '',
            $r['member_gender'] === 'M' ? 'H' : ($r['member_gender'] === 'F' ? 'F' : ''),
            'FRA',
            $r['member_email'] ?? '',
            $r['member_phone'] ?? '',
            $r['member_address'] ?? '',
            $r['member_postal_code'] ?? '',
            $r['member_city'] ?? '',
            'FRA',
            'ULTRAMICAL',
            $r['cancellation_insurance'] ? 'Oui' : 'Non',
            implode(', ', $courseLabels),
            $r['team_mode'] === 'duo' ? 'Duo' : ($r['team_mode'] === 'solo' ? 'Solo' : ''),
            $duoPartner,
            $r['bivouac'] ? 'Oui' : 'Non',
            $r['sunday_lunch'] ? 'Oui' : 'Non',
            $r['shirt_size'],
            $r['emergency_contact_name'],
            $r['emergency_contact_phone'],
            weekend_2027_accommodation_label($r['accommodation']),
            $r['license_number'],
            $r['created_at'],
            $r['updated_at'],
        ], ';');
    }

    fclose($output);
    exit;
}

// Sa propre préinscription (si elle existe) en premier dans la liste — pratique
// comme rappel/confirmation de ce qu'on a soi-même choisi. Tri stable (PHP 8+) :
// l'ordre du dernier inscrit au premier inscrit est conservé pour tous les autres.
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
