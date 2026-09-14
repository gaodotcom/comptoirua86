<?php

declare(strict_types=1);

/**
 * Connexion.
 * Tente l'authentification par identifiant (nom d'utilisateur ou email) + mot de passe.
 * Si l'utilisateur s'est connecté via sa date de naissance (aucun mot de passe personnel défini),
 * il est redirigé vers le formulaire de changement de mot de passe.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF pour protéger le formulaire de connexion.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée, merci de réessayer.');
        redirect_to('login');
    }

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($identifier, $password)) {
        // Connexion via date de naissance : on force la définition d'un mot de passe personnel.
        if (must_set_password()) {
            set_flash('warning', 'Connexion réussie avec la date de naissance. Merci de définir votre mot de passe personnel.');
            redirect_to('change-password');
        }

        set_flash('success', 'Connexion réussie.');
        redirect_to('home');
    }

    set_flash('danger', 'Identifiant ou mot de passe incorrect.');
    redirect_to('login');
}

twig_render('pages/auth/login.twig', ['title' => 'Connexion']);
