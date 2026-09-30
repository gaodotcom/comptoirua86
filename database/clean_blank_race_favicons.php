<?php

// phpcs:ignoreFile

declare(strict_types=1);

/**
 * Script ponctuel (CLI) : supprime les logos de courses qui sont des images
 * vides (transparentes ou d'une seule couleur), pour que la page des courses
 * affiche le picto par défaut à la place.
 *
 * Usage : php database/clean_blank_race_favicons.php [--dry-run]
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/uploads.php';
require __DIR__ . '/../app/races.php';

$dryRun = in_array('--dry-run', $argv, true);
$config = app_config();
$webRoot = trim($config['uploads_web_root'], '/');
$fsRoot = rtrim($config['uploads_fs_root'], '/');

$rows = app_pdo()->query("SELECT id, title, favicon_path FROM races WHERE favicon_path IS NOT NULL AND favicon_path <> ''")->fetchAll();
$cleaned = 0;

foreach ($rows as $row) {
    $path = (string) $row['favicon_path'];
    $fsPath = $fsRoot . '/' . substr($path, strlen($webRoot) + 1);
    $body = is_file($fsPath) ? file_get_contents($fsPath) : false;

    // Fichier absent ou image vide : le logo n'est d'aucune utilité.
    if ($body !== false && !is_blank_favicon($body)) {
        continue;
    }

    echo ($dryRun ? '[dry-run] ' : '') . "#{$row['id']} {$row['title']} : {$path}\n";
    $cleaned++;

    if (!$dryRun) {
        delete_uploaded_file($path);
        app_pdo()->prepare('UPDATE races SET favicon_path = NULL WHERE id = :id')->execute(['id' => $row['id']]);
    }
}

echo "{$cleaned} logo(s) " . ($dryRun ? 'à nettoyer' : 'nettoyé(s)') . ".\n";
