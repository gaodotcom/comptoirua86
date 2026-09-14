<?php

declare(strict_types=1);

/**
 * Profil d'un adhérent.
 * Par défaut, l'utilisateur gère son propre profil (photo, nom d'utilisateur, mot de passe).
 * Un admin peut consulter le profil d'un autre adhérent via ?id=X (lecture seule pour
 * les champs de modification, qui restent liés à l'utilisateur courant).
 */

// Un membre du bureau ou admin peut consulter le profil d'un autre adhérent via ?id=X
$viewingId = (int) ($_GET['id'] ?? 0);
$isViewingOther = $viewingId > 0 && can_view_detailed_members() && $viewingId !== (int) ($user['id'] ?? 0);

if ($isViewingOther) {
    $profileMember = get_member_by_id($viewingId);
    if ($profileMember === null) {
        set_flash('danger', 'Adhérent introuvable.');
        redirect_to('trombinoscope');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Session expirée, merci de réessayer.');
        redirect_to('profile', $isViewingOther ? ['id' => $viewingId] : []);
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    // Action : mise à jour de la photo de l'utilisateur courant.
    if ($action === 'photo') {
        try {
            $path = save_uploaded_image($_FILES['photo'] ?? [], 'members', (int) $user['id']);
            if ($path === null) {
                throw new RuntimeException('Merci de selectionner une image.');
            }
            // On recharge le membre pour récupérer l'ancienne photo avant de la remplacer.
            $member = current_user(true);
            $oldPhoto = $member['photo_path'] ?? '';
            if ($oldPhoto !== '') {
                delete_uploaded_file($oldPhoto);
            }
            update_member_photo((int) $user['id'], $path);
            // current_user(true) rafraîchit la session avec les nouvelles données.
            current_user(true);
            set_flash('success', 'Photo mise a jour.');
        } catch (Throwable $exception) {
            set_flash('danger', $exception->getMessage());
        }
    } elseif ($action === 'delete_photo') {
        // Action : suppression de la photo de l'utilisateur courant.
        $member = current_user(true);
        $photoPath = $member['photo_path'] ?? null;
        if ($photoPath !== null && $photoPath !== '') {
            delete_uploaded_file($photoPath);
            update_member_photo((int) $user['id'], '');
            current_user(true);
            set_flash('success', 'Photo supprimée.');
        }
    } elseif ($action === 'username') {
        // Action : mise à jour du nom d'utilisateur (optionnel).
        try {
            $newUsername = trim((string) ($_POST['username'] ?? ''));
            if ($newUsername !== '') {
                update_current_user_username($newUsername);
                set_flash('success', 'Nom d\'utilisateur mis à jour.');
            }
        } catch (Throwable $exception) {
            set_flash('danger', $exception->getMessage());
        }
    } elseif ($action === 'password') {
        // Action : changement de mot de passe par l'utilisateur lui-même.
        try {
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            if ($password !== $passwordConfirm) {
                throw new RuntimeException('Les deux mots de passe ne correspondent pas.');
            }
            update_current_user_password($password);
            set_flash('success', 'Mot de passe mis à jour.');
        } catch (Throwable $exception) {
            set_flash('danger', $exception->getMessage());
        }
    }

    redirect_to('profile');
}

// Rendu : profil d'un autre adhérent (admin) ou profil personnel.
if ($isViewingOther) {
    $profileUser = $profileMember;
    $memberships = get_member_memberships($viewingId);
    twig_render('pages/members/profile.twig', [
        'title' => 'Profil de ' . $profileMember['first_name'] . ' ' . $profileMember['last_name'],
        'profileUser' => $profileUser,
        'memberships' => $memberships,
        'isViewingOther' => true,
    ]);
} else {
    $profileUser = current_user(true);
    $memberships = get_member_memberships((int) $profileUser['id']);
    twig_render('pages/members/profile.twig', [
        'title' => 'Mon profil',
        'profileUser' => $profileUser,
        'memberships' => $memberships,
        'isViewingOther' => false,
    ]);
}
