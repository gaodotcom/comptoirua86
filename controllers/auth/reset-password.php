<?php

declare(strict_types=1);

/**
 * Réinitialisation du mot de passe.
 * Valide le jeton reçu par email puis enregistre le nouveau mot de passe.
 * Le jeton est un identifiant aléatoire de 64 caractères, à usage unique et temporaire.
 */

$token = trim((string) ($_GET['token'] ?? ''));
$message = '';
$messageType = '';
$isValidToken = false;

// Vérifier le token : présent et de la longueur attendue (64 caractères).
if ($token === '' || strlen($token) !== 64) {
    $message = 'Lien de réinitialisation invalide ou expiré.';
    $messageType = 'danger';
} else {
    $memberId = verify_password_reset_token($token);

    if ($memberId === null) {
        $message = 'Lien de réinitialisation invalide ou expiré.';
        $messageType = 'danger';
    } else {
        $isValidToken = true;
    }
}

// Le POST n'est traité que si le jeton est valide (sinon on affiche simplement l'erreur).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = 'Session expirée, merci de réessayer.';
        $messageType = 'danger';
        $isValidToken = false;
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        // Vérifications de cohérence et de longueur minimale (8 caractères).
        if ($password !== $passwordConfirm) {
            $message = 'Les deux mots de passe ne correspondent pas.';
            $messageType = 'danger';
        } elseif (strlen($password) < 8) {
            $message = 'Le mot de passe doit contenir au moins 8 caractères.';
            $messageType = 'warning';
        } else {
            try {
                $success = apply_password_reset($token, $password);

                if ($success) {
                    set_flash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
                    redirect_to('login');
                } else {
                    // Échec de la réinitialisation : on invalide le formulaire côté vue.
                    $message = 'Erreur lors de la réinitialisation du mot de passe.';
                    $messageType = 'danger';
                    $isValidToken = false;
                }
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

twig_render(
    'pages/auth/reset-password.twig',
    [
        'title' => 'Réinitialiser mon mot de passe',
        'token' => $token,
        'isValidToken' => $isValidToken,
        'message' => $message,
        'messageType' => $messageType,
    ]
);
