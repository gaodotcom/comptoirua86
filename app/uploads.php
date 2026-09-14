<?php

declare(strict_types=1);

/**
 * Uploads — gestion des téléversements d'images
 *
 * Crée les dossiers d'upload au démarrage et valide/sauvegarde
 * les images envoyées par formulaire (membres, entraînements).
 */

/**
 * Crée les dossiers d'upload s'ils n'existent pas.
 * Appelée au démarrage de l'application.
 * @return void
 */
function ensure_upload_directories(): void
{
    $config = app_config();
    $folders = [
        $config['uploads_fs_root'],
        $config['uploads_fs_root'] . '/members',
        $config['uploads_fs_root'] . '/trainings',
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }
    }
}

/**
 * Sauvegarde une image uploadée et retourne son chemin web relatif.
 *
 * @param array  $file   Tableau $_FILES (clé 'image' ou 'photo')
 * @param string $folder Sous-dossier de destination ('members' ou 'trainings')
 *
 * @return string|null    Chemin web relatif, ou null si aucun fichier envoyé
 *
 * @throws RuntimeException Si le fichier est trop grand, corrompu ou d'un format non supporté
 */
function save_uploaded_image(array $file, string $folder, ?int $memberId = null): ?string
{
    // Aucun fichier envoyé : on retourne null (cas normal, pas une erreur).
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Une erreur d'upload autre que "pas de fichier" est anormale : on lève une exception.
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Echec de televersement du fichier image.');
    }

    $maxSize = app_config()['upload_max_size'];
    $size = (int) ($file['size'] ?? 0);

    if ($size <= 0 || $size > $maxSize) {
        throw new RuntimeException('Image invalide (taille superieure a la limite autorisee).');
    }

    $tmpPath = $file['tmp_name'] ?? '';

    // is_uploaded_file protège contre les chemins forgés (attaque par chemin).
    if (!is_string($tmpPath) || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Fichier image introuvable.');
    }

    // On détermine le type réel du fichier via son contenu (finfo), pas via l'extension
    // déclarée par le client, ce qui évite les faux types.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);

    // MIME type autorisé => extension associée pour le nom de fichier final.
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimes[$mime])) {
        throw new RuntimeException('Format image non supporte (jpg, png, webp, gif uniquement).');
    }

    $extension = $allowedMimes[$mime];
    // Nom unique : préfixe member_ + id + suffixe aléatoire, pour éviter les collisions.
    $filename = 'member_' . ($memberId ?? 0) . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $fsRoot = rtrim(app_config()['uploads_fs_root'], '/');
    $targetDirectory = $fsRoot . '/' . trim($folder, '/');

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('Impossible de preparer le dossier de televersement.');
    }

    $targetPath = $targetDirectory . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Impossible de sauvegarder l image sur le serveur.');
    }

    $webRoot = trim(app_config()['uploads_web_root'], '/');

    // On retourne le chemin web (relatif) à stocker en base et à utiliser dans les <img>.
    return $webRoot . '/' . trim($folder, '/') . '/' . $filename;
}

/**
 * Supprime physiquement un fichier uploadé à partir de son chemin web.
 *
 * @param string $webPath Chemin web relatif (ex: 'public/uploads/members/fichier.png')
 *
 * @return void
 */
function delete_uploaded_file(string $webPath): void
{
    $config = app_config();
    $webRoot = trim($config['uploads_web_root'], '/');
    $fsRoot = rtrim($config['uploads_fs_root'], '/');

    if ($webPath === '' || !str_starts_with($webPath, $webRoot . '/')) {
        return;
    }

    $relativePath = substr($webPath, strlen($webRoot) + 1);
    $fsPath = $fsRoot . '/' . $relativePath;

    // Vérifie que le chemin résolu reste dans le dossier d'uploads (anti path traversal).
    $realFsPath = realpath($fsPath);
    $realFsRoot = realpath($fsRoot);
    if ($realFsPath === false || $realFsRoot === false || !str_starts_with($realFsPath, $realFsRoot . '/')) {
        return;
    }

    if (is_file($realFsPath)) {
        @unlink($realFsPath);
    }
}
