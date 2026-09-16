<?php

declare(strict_types=1);

/**
 * Import des adhésions depuis HelloAsso (administration).
 * Construit d'abord un "plan d'import" (aperçu des adhérents à créer / adhésions à
 * mettre à jour) depuis une campagne HelloAsso. L'import réel n'a lieu qu'après
 * confirmation explicite, et s'exécute en transaction pour pouvoir annuler en cas
 * d'erreur.
 */

// HelloAsso doit être configuré (identifiants API dans le .env).
if (!helloasso_is_configured()) {
    set_flash('danger', 'HelloAsso n\'est pas configuré (HELLOASSO_CLIENT_ID, CLIENT_SECRET, ORG_SLUG dans le .env).');
    redirect_to('trombinoscope');
}

$campaigns = helloasso_get_campaigns();

if ($campaigns === []) {
    set_flash('warning', 'Aucune campagne enregistrée. Ajoutez d\'abord une campagne.');
    redirect_to('helloasso-campaigns');
}

// Campagne sélectionnée : via POST (preview/import) ou GET (?campaign_id=).
$selectedCampaignId = (int) ($_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);

// Par défaut, sélectionne la première campagne (la plus récente).
if ($selectedCampaignId === 0) {
    $selectedCampaignId = (int) $campaigns[0]['id'];
}

$selectedCampaign = null;
foreach ($campaigns as $c) {
    if ((int) $c['id'] === $selectedCampaignId) {
        $selectedCampaign = $c;
        break;
    }
}

if ($selectedCampaign === null) {
    set_flash('danger', 'Campagne introuvable.');
    redirect_to('helloasso-campaigns');
}

// Mode "complet" (import de la campagne) ou "adhesion" (adhésions des membres déjà en base).
// Par défaut : "complet" pour une saison active, "adhesion" pour une saison inactive.
// Le mode peut être forcé via GET/POST pour changer le comportement.
$seasonActive = is_school_year_active((string) $selectedCampaign['school_year']);
$explicitMode = $_POST['mode'] ?? $_GET['mode'] ?? null;
if ($explicitMode === 'complet' || $explicitMode === 'adhesion') {
    $mode = $explicitMode;
} else {
    $mode = $seasonActive ? 'complet' : 'adhesion';
}

$error = null;
$plan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée.');
        redirect_to('helloasso-import', ['campaign_id' => $selectedCampaignId]);
    }

    if (($_POST['action'] ?? '') === 'import') {
        // La case de confirmation doit être cochée pour déclencher l'import.
        if (empty($_POST['confirm'])) {
            set_flash('warning', 'Merci de cocher la case de confirmation pour valider l\'import.');
            redirect_to('helloasso-import', ['campaign_id' => $selectedCampaignId, 'mode' => $mode]);
        }
        try {
            // L'import s'exécute en transaction : en cas d'erreur, tout est annulé (rollback).
            $plan = helloasso_build_import_plan($selectedCampaign['slug'], $mode);
            $result = helloasso_run_import($plan);
            if ($result['errors'] !== []) {
                foreach ($result['errors'] as $msg) {
                    set_flash('danger', $msg);
                }
                set_flash('warning', 'Import annulé : aucune modification enregistrée (transaction rollback).');
            } else {
                set_flash(
                    'success',
                    sprintf(
                        'Import terminé : %d adhérent(s) créé(s), %d adhésion(s) mise(s) à jour pour %s.',
                        $result['created'],
                        $result['updated'],
                        $plan['school_year']
                    )
                );
            }
            redirect_to('trombinoscope');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// En dehors d'un POST d'import, on construit juste l'aperçu du plan pour affichage.
if ($error === null) {
    try {
        $plan = helloasso_build_import_plan($selectedCampaign['slug'], $mode);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

twig_render('pages/members/helloasso-import.twig', [
    'title' => 'Importer depuis HelloAsso',
    'plan' => $plan,
    'error' => $error,
    'campaigns' => $campaigns,
    'selectedCampaignId' => $selectedCampaignId,
    'selectedCampaign' => $selectedCampaign,
    'seasonActive' => $seasonActive,
    'mode' => $mode,
]);
