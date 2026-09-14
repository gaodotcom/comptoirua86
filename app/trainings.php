<?php

declare(strict_types=1);

/**
 * Trainings — CRUD des semaines d'entraînement
 *
 * Création, lecture, modification et suppression des entraînements
 * publiés par le coach.
 */

/**
 * Récupère les dernières semaines d'entraînement (avec nom de l'auteur).
 *
 * @param int $limit Nombre maximum d'entraînements à retourner
 *
 * @return array Liste des résultats
 */
function get_last_trainings(int $limit = 3): array
{
    $stmt = app_pdo()->prepare(
        'SELECT t.*, m.first_name AS author_first_name, m.last_name AS author_last_name
         FROM trainings t
         LEFT JOIN members m ON m.id = t.created_by
         ORDER BY t.week_start DESC
         LIMIT :limit'
    );

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Récupère un entraînement par son ID (avec nom de l'auteur).
 * @return array|null Données de l'entraînement ou null
 */
function get_training_by_id(int $id): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT t.*, m.first_name AS author_first_name, m.last_name AS author_last_name
         FROM trainings t
         LEFT JOIN members m ON m.id = t.created_by
         WHERE t.id = :id'
    );

    $stmt->execute(['id' => $id]);

    $result = $stmt->fetch();

    return $result === false ? null : $result;
}

/**
 * Crée une nouvelle semaine d'entraînement.
 *
 * @throws RuntimeException Si les données sont invalides ou la date existe déjà
 * @return void
 */
function create_training(array $payload): void
{
    $weekStart = trim((string) ($payload['week_start'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $presentation = trim((string) ($payload['presentation_text'] ?? ''));
    $imagePath = trim((string) ($payload['image_path'] ?? ''));
    $comment = trim((string) ($payload['comment_text'] ?? ''));
    $createdBy = (int) ($payload['created_by'] ?? 0);

    if ($weekStart === '' || !is_iso_date($weekStart)) {
        throw new RuntimeException('La date de debut de semaine est invalide.');
    }

    if ($title === '') {
        throw new RuntimeException('Le titre de la semaine est obligatoire.');
    }

    if ($presentation === '') {
        throw new RuntimeException('Le texte de presentation est obligatoire.');
    }

    if ($imagePath === '') {
        throw new RuntimeException('Une image est obligatoire pour la semaine d entrainement.');
    }

    if ($createdBy <= 0) {
        throw new RuntimeException('Auteur de la semaine invalide.');
    }

    $stmt = app_pdo()->prepare(
        'INSERT INTO trainings (week_start, title, presentation_text, image_path, comment_text, created_by)
         VALUES (:week_start, :title, :presentation_text, :image_path, :comment_text, :created_by)'
    );

    try {
        $stmt->execute(
            [
            'week_start' => $weekStart,
            'title' => $title,
            'presentation_text' => $presentation,
            'image_path' => $imagePath,
            'comment_text' => $comment !== '' ? $comment : null,
            'created_by' => $createdBy,
            ]
        );
    } catch (PDOException $exception) {
        // Code 23000 = violation de contrainte d'unicité (une semaine existe déjà pour cette date).
        if ($exception->getCode() === '23000') {
            throw new RuntimeException('Une semaine d entrainement existe deja pour cette date.');
        }

        throw $exception;
    }
}

/**
 * Modifie une semaine d'entraînement existante.
 *
 * @throws RuntimeException Si les données sont invalides
 * @return void
 */
function update_training(int $id, array $payload): void
{
    $weekStart = trim((string) ($payload['week_start'] ?? ''));
    $title = trim((string) ($payload['title'] ?? ''));
    $presentation = trim((string) ($payload['presentation_text'] ?? ''));
    $imagePath = trim((string) ($payload['image_path'] ?? ''));
    $comment = trim((string) ($payload['comment_text'] ?? ''));

    if ($weekStart === '' || !is_iso_date($weekStart)) {
        throw new RuntimeException('La date de debut de semaine est invalide.');
    }

    if ($title === '') {
        throw new RuntimeException('Le titre de la semaine est obligatoire.');
    }

    if ($presentation === '') {
        throw new RuntimeException('Le texte de presentation est obligatoire.');
    }

    if ($imagePath === '') {
        throw new RuntimeException('Une image est obligatoire pour la semaine d entrainement.');
    }

    $stmt = app_pdo()->prepare(
        'UPDATE trainings
         SET week_start = :week_start,
             title = :title,
             presentation_text = :presentation_text,
             image_path = :image_path,
             comment_text = :comment_text
         WHERE id = :id'
    );

    try {
        $stmt->execute(
            [
            'week_start' => $weekStart,
            'title' => $title,
            'presentation_text' => $presentation,
            'image_path' => $imagePath,
            'comment_text' => $comment !== '' ? $comment : null,
            'id' => $id,
            ]
        );
    } catch (PDOException $exception) {
        // Code 23000 = violation de contrainte d'unicité (une semaine existe déjà pour cette date).
        if ($exception->getCode() === '23000') {
            throw new RuntimeException('Une semaine d entrainement existe deja pour cette date.');
        }

        throw $exception;
    }
}

/**
 * Supprime définitivement un entraînement.
 * @return void
 */
function delete_training(int $id): void
{
    $stmt = app_pdo()->prepare('DELETE FROM trainings WHERE id = :id');
    $stmt->execute(['id' => $id]);
}
