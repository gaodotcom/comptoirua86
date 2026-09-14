<?php

declare(strict_types=1);

/**
 * Routes — mapping entre les noms de routes internes et les slugs URL publics
 *
 * Permet d'avoir des URLs en français (/entrainements) tout en gardant
 * des noms de code en anglais (trainings).
 *
 * Le mapping est bi-directionnel :
 * - route_to_slug('trainings') → 'entrainements'
 * - slug_to_route('entrainements') → 'trainings'
 */

/**
 * Mapping des routes internes vers leurs slugs URL publics.
 * Les routes non listées ici utilisent leur nom comme slug (ex: 'home' → '/home').
 */
function route_map(): array
{
    return [
        'home' => 'accueil',
        'login' => 'connexion',
        'logout' => 'deconnexion',
        'forgot-password' => 'mot-de-passe-oublie',
        'reset-password' => 'reinitialiser-mot-de-passe',
        'profile' => 'mon-profil',
        'change-password' => 'modifier-mon-mot-de-passe',
        'trombinoscope' => 'trombinoscope',
        'trainings' => 'entrainements',
        'training-form' => 'publier-un-entrainement',
        'training-edit' => 'modifier-un-entrainement',
        'member-edit' => 'fiche-adherent',
        'member-add' => 'ajouter-un-adherent',
        'helloasso-import' => 'importer-helloasso',
        'helloasso-campaigns' => 'campagnes-helloasso',
        'active-years' => 'saisons-actives',
        'inactive-members' => 'adherents-desactives',
        'season-members' => 'saison',
        'admin-guide' => 'aide',
        'events' => 'evenements',
        'event-form' => 'evenement',
        'news' => 'actualites',
        'news-form' => 'actualite',
        'races' => 'courses',
        'race-form' => 'partager-une-course',
    ];
}

/**
 * Convertit un nom de route interne en slug URL public.
 *
 * @param string $route Nom de route interne (ex: 'trainings')
 *
 * @return string Slug URL public (ex: 'entrainements')
 */
function route_to_slug(string $route): string
{
    $map = route_map();

    return $map[$route] ?? $route;
}

/**
 * Convertit un slug URL public en nom de route interne.
 *
 * @param string $slug Slug URL public (ex: 'entrainements')
 *
 * @return string|null Nom de route interne (ex: 'trainings'), ou null si non trouvé
 */
function slug_to_route(string $slug): ?string
{
    $map = route_map();
    $reverse = array_flip($map);

    return $reverse[$slug] ?? null;
}
