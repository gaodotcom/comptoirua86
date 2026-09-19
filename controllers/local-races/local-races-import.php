<?php

declare(strict_types=1);

/**
 * Import du calendrier FFA (courses "Running" du département 086) dans la
 * table local_races. Réservé aux admins. Aperçu (dry-run) avant confirmation,
 * comme l'import HelloAsso.
 */

// Saison FFA "courante" : les tests montrent que la saison N couvre
// septembre (N-1) → août N, donc à partir de septembre on est déjà dans la
// saison N+1 par rapport à l'année civile.
$currentSeason = (int) date('n') >= 9 ? (int) date('Y') + 1 : (int) date('Y');
$season = (int) ($_POST['season'] ?? $_GET['season'] ?? $currentSeason);

// Les deux seules saisons proposées : la courante et la suivante, avec leur
// plage de dates affichée pour lever toute ambiguïté sur le libellé FFA.
$seasonOptions = [];
foreach ([$currentSeason, $currentSeason + 1] as $s) {
    $seasonOptions[] = [
        'value' => $s,
        'label' => $s . ' (septembre ' . ($s - 1) . ' → août ' . $s . ')',
    ];
}

$error = null;
$plan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('local-races-import', ['season' => $season]);
    }

    if (($_POST['action'] ?? '') === 'import') {
        if (empty($_POST['confirm'])) {
            set_flash('warning', 'Merci de cocher la case de confirmation pour valider l\'import.');
            redirect_to('local-races-import', ['season' => $season]);
        }
        try {
            $plan = build_local_races_import_plan($season);
            $result = run_local_races_import($plan, (int) $user['id']);
            set_flash(
                'success',
                sprintf(
                    'Import terminé : %d course(s) ajoutée(s), %d mise(s) à jour, %d supprimée(s) pour la saison %d.',
                    $result['created'],
                    $result['updated'],
                    $result['deleted'],
                    $season
                )
            );
            redirect_to('calendar');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// En dehors d'un POST d'import, on construit juste l'aperçu pour affichage.
if ($error === null && $plan === null) {
    try {
        $plan = build_local_races_import_plan($season);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

twig_render('pages/local-races/local-races-import.twig', [
    'title' => 'Importer le calendrier FFA',
    'plan' => $plan,
    'error' => $error,
    'season' => $season,
    'seasonOptions' => $seasonOptions,
]);
