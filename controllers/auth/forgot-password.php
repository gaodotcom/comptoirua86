<?php

declare(strict_types=1);

/**
 * Mot de passe oublié.
 * Permet à un utilisateur de demander un email de réinitialisation de mot de passe.
 * Pour des raisons de sécurité, la réponse est identique que l'email existe ou non,
 * afin de ne pas révéler qu'un compte existe.
 */

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $message = 'Session expirée, merci de réessayer.';
        $messageType = 'danger';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        // Validations côté formulaire avant de solliciter la base.
        if ($email === '') {
            $message = 'Merci d\'entrer votre adresse email.';
            $messageType = 'warning';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Adresse email invalide.';
            $messageType = 'warning';
        } else {
            $sent = request_password_reset($email);

            if ($sent) {
                set_flash('success', 'Un email de réinitialisation a été envoyé à ' . e($email) . '. Veuillez vérifier votre boîte de réception.');
                redirect_to('login');
            } else {
                // Pour la sécurité, on ne dit pas si l'email existe ou non.
                set_flash('success', 'Si cet email existe dans notre base, vous recevrez un lien de réinitialisation.');
                redirect_to('login');
            }
        }
    }
}

twig_render(
    'pages/auth/forgot-password.twig',
    [
        'title' => 'Mot de passe oublié',
        'message' => $message,
        'messageType' => $messageType,
    ]
);
