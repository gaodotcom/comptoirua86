<?php

declare(strict_types=1);

/**
 * Définition / changement de son propre mot de passe.
 * Utilisé notamment après une première connexion via date de naissance,
 * pour forcer la création d'un mot de passe personnel.
 */

// Page initialement demandée avant la redirection forcée vers la connexion,
// transmise depuis login.php (cf. safe_internal_redirect_path()).
$redirectTarget = safe_internal_redirect_path($_POST['redirect'] ?? $_GET['redirect'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf('change-password', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    // Les deux champs doivent correspondre avant d'essayer l'enregistrement.
    if ($password !== $passwordConfirm) {
        set_flash('danger', 'Les deux mots de passe ne correspondent pas.');
        redirect_to('change-password', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
    }
    try {
        update_current_user_password($password);
        set_flash('success', 'Votre mot de passe a été mis a jour.');
        if ($redirectTarget !== null) {
            header('Location: ' . base_url($redirectTarget));
            exit;
        }
        redirect_to('home');
    } catch (Throwable $exception) {
        // Les erreurs de validation (longueur, complexité) remontent ici.
        set_flash('danger', $exception->getMessage());
        redirect_to('change-password', $redirectTarget !== null ? ['redirect' => $redirectTarget] : []);
    }
}
twig_render('pages/auth/change-password.twig', ['title' => 'Définir mon mot de passe', 'redirectTarget' => $redirectTarget]);
