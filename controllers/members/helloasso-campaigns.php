<?php

declare(strict_types=1);

/**
 * Gestion des campagnes HelloAsso (administration).
 * Permet d'enregistrer les campagnes d'adhésion HelloAsso (slug, année scolaire, URL)
 * qui serviront ensuite à importer automatiquement les adhésions.
 */

// ?edit>0 indique qu'on édite une campagne existante ; sinon on en crée une nouvelle.
$editingCampaign = null;
$editId = (int) ($_GET['edit'] ?? 0);

if ($editId > 0) {
    $editingCampaign = helloasso_get_campaign($editId);
    if ($editingCampaign === null) {
        set_flash('danger', 'Campagne introuvable.');
        redirect_to('helloasso-campaigns');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('helloasso-campaigns');

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'slug' => $_POST['slug'] ?? '',
            'school_year' => $_POST['school_year'] ?? '',
            'url' => $_POST['url'] ?? '',
        ];

        // Déduction automatique du slug depuis l'URL si slug vide.
        if (trim($data['slug']) === '' && trim($data['url']) !== '') {
            $data['slug'] = helloasso_slug_from_url($data['url']);
        }

        // Mise à jour ou création selon qu'on a un identifiant d'édition.
        if ($editId > 0) {
            [$ok, $errors] = helloasso_update_campaign($editId, $data);
        } else {
            [$ok, $errors] = helloasso_create_campaign($data);
        }

        if ($ok) {
            set_flash('success', $editId > 0 ? 'Campagne mise à jour.' : 'Campagne ajoutée.');
            redirect_to('helloasso-campaigns');
        }
        foreach ($errors as $error) {
            set_flash('danger', (string) $error);
        }
        redirect_to('helloasso-campaigns', $editId > 0 ? ['edit' => $editId] : []);
    }

    if ($action === 'delete') {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        if ($campaignId > 0) {
            helloasso_delete_campaign($campaignId);
            set_flash('success', 'Campagne supprimée.');
        }
        redirect_to('helloasso-campaigns');
    }
}

$campaigns = helloasso_get_campaigns();

twig_render('pages/members/helloasso-campaigns.twig', [
    'title' => 'Campagnes HelloAsso',
    'campaigns' => $campaigns,
    'editingCampaign' => $editingCampaign,
    'editId' => $editId,
]);
