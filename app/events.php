<?php

declare(strict_types=1);

/**
 * Events — CRUD des événements de l'association
 *
 * Création, lecture, modification et suppression des événements
 * (titre, dates, lieu).
 */

/**
 * Valide les données d'un événement et retourne les champs nettoyés.
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function validate_event_payload(array $payload): array
{
    $data = [
        'title' => trim((string) ($payload['title'] ?? '')),
        'start_date' => trim((string) ($payload['start_date'] ?? '')),
        'end_date' => trim((string) ($payload['end_date'] ?? '')),
        'location' => trim((string) ($payload['location'] ?? '')),
        'description' => trim((string) ($payload['description'] ?? '')),
        'created_by' => (int) ($payload['created_by'] ?? 0),
        'published' => (int) ($payload['published'] ?? 1),
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors[] = 'Le titre est obligatoire.';
    }

    if ($data['start_date'] === '' || !is_iso_date($data['start_date'])) {
        $errors[] = 'La date de début est invalide.';
    }

    if ($data['end_date'] !== '' && !is_iso_date($data['end_date'])) {
        $errors[] = 'La date de fin est invalide.';
    }

    if ($data['end_date'] !== '' && $data['end_date'] < $data['start_date']) {
        $errors[] = 'La date de fin ne peut pas être antérieure à la date de début.';
    }

    return [$data, $errors];
}

/**
 * Crée un nouvel événement.
 *
 * @throws RuntimeException Si les données sont invalides
 * @return void
 */
function create_event(array $payload): void
{
    [$data, $errors] = validate_event_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $stmt = app_pdo()->prepare(
        'INSERT INTO events (title, start_date, end_date, location, description, published, created_by)
         VALUES (:title, :start_date, :end_date, :location, :description, :published, :created_by)'
    );

    // Les champs optionnels vides sont stockés NULL plutôt que chaîne vide.
    $stmt->execute(
        [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'description' => $data['description'] !== '' ? $data['description'] : null,
        'published' => $data['published'],
        'created_by' => $data['created_by'] > 0 ? $data['created_by'] : null,
        ]
    );
}

/**
 * Récupère un événement par son ID.
 * @return array|null Données de l'événement ou null
 */
function get_event_by_id(int $id): ?array
{
    $stmt = app_pdo()->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();

    return $result === false ? null : $result;
}

/**
 * Modifie un événement existant.
 *
 * @throws RuntimeException Si les données sont invalides
 * @return void
 */
function update_event(int $id, array $payload): void
{
    [$data, $errors] = validate_event_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $stmt = app_pdo()->prepare(
        'UPDATE events
         SET title = :title,
             start_date = :start_date,
             end_date = :end_date,
             location = :location,
             description = :description,
             published = :published
         WHERE id = :id'
    );

    $stmt->execute(
        [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'description' => $data['description'] !== '' ? $data['description'] : null,
        'published' => $data['published'],
        'id' => $id,
        ]
    );
}

/**
 * Supprime définitivement un événement.
 * @return void
 */
function delete_event(int $id): void
{
    $stmt = app_pdo()->prepare('DELETE FROM events WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * Récupère tous les événements, triés par date de début décroissante.
 * @return array Liste des résultats
 */
function get_events(): array
{
    $stmt = app_pdo()->query(
        'SELECT * FROM events ORDER BY start_date DESC'
    );

    return $stmt->fetchAll();
}

/**
 * Compte les événements à venir (date de fin >= aujourd'hui).
 * @return int
 */
function get_upcoming_events_count(): int
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $hiddenClause = is_admin() ? '' : ' AND published = 1';
    $stmt = app_pdo()->prepare(
        'SELECT COUNT(*) FROM events WHERE COALESCE(end_date, start_date) >= :today' . $hiddenClause
    );
    $stmt->execute(['today' => $today]);

    return (int) $stmt->fetchColumn();
}
