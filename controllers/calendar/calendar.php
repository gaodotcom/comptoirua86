<?php

declare(strict_types=1);

/**
 * Calendrier : vue mensuelle des événements UA86, des courses et des anniversaires.
 * Le mois affiché est choisi via ?mois=YYYY-MM (mois courant par défaut).
 */

$month = trim((string) ($_GET['mois'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

// Rejette (redirige) tout mois hors des bornes de navigation, avant tout calcul
// de grille : évite de faire travailler la base pour une date lointaine au
// hasard (lien cassé, ou robot qui fait défiler ?mois=... par curiosité).
[$minMonth, $maxMonth] = get_calendar_navigable_bounds();
if ($month < $minMonth) {
    redirect_to('calendar', ['mois' => $minMonth]);
}
if ($month > $maxMonth) {
    redirect_to('calendar', ['mois' => $maxMonth]);
}

twig_render('pages/calendar/calendar.twig', [
    'title' => 'Calendrier',
    'cal' => build_calendar_month($month),
    'thisMonth' => date('Y-m'),
]);
