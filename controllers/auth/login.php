<?php

declare(strict_types=1);

/**
 * Connexion.
 * Tente l'authentification par identifiant (nom d'utilisateur ou email) + mot de passe.
 * Si l'utilisateur s'est connecté via sa date de naissance (aucun mot de passe personnel défini),
 * il est redirigé vers le formulaire de changement de mot de passe.
 */

// Page demandée avant d'être redirigé vers la connexion (cf. require_login()),
// pour y revenir une fois connecté plutôt que d'atterrir systématiquement sur l'accueil.
$redirectTarget = safe_internal_redirect_path($_POST['redirect'] ?? $_GET['redirect'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF pour protéger le formulaire de connexion.
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée, merci de réessayer.');
        redirect_to('login', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
    }

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($identifier, $password)) {
        // "Rester connecté" coché (par défaut) : pose un cookie de connexion
        // mémorisée glissant sur 90 jours, cf. app/auth.php.
        if (!empty($_POST['remember'])) {
            issue_remember_me_token((int) current_user()['id']);
        }

        // Connexion via date de naissance : on force la définition d'un mot de passe personnel.
        if (must_set_password()) {
            set_flash('warning', 'Connexion réussie avec la date de naissance. Merci de définir votre mot de passe personnel.');
            redirect_to('change-password', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
        }

        set_flash('success', 'Connexion réussie.');
        if ($redirectTarget !== null) {
            header('Location: ' . base_url($redirectTarget));
            exit;
        }
        redirect_to('home');
    }

    set_flash('danger', 'Identifiant ou mot de passe incorrect.');
    redirect_to('login', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
}

twig_render('pages/auth/login.twig', ['title' => 'Connexion', 'redirectTarget' => $redirectTarget]);
