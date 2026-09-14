<?php

declare(strict_types=1);

/**
 * Définition / changement de son propre mot de passe.
 * Utilisé notamment après une première connexion via date de naissance,
 * pour forcer la création d'un mot de passe personnel.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée, merci de réessayer.');
        redirect_to('change-password');
    }
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    // Les deux champs doivent correspondre avant d'essayer l'enregistrement.
    if ($password !== $passwordConfirm) {
        set_flash('danger', 'Les deux mots de passe ne correspondent pas.');
        redirect_to('change-password');
    }
    try {
        update_current_user_password($password);
        set_flash('success', 'Votre mot de passe a été mis a jour.');
        redirect_to('home');
    } catch (Throwable $exception) {
        // Les erreurs de validation (longueur, complexité) remontent ici.
        set_flash('danger', $exception->getMessage());
        redirect_to('change-password');
    }
}
twig_render('pages/auth/change-password.twig', ['title' => 'Définir mon mot de passe']);
