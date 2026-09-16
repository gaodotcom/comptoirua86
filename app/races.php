<?php

declare(strict_types=1);

/**
 * Races — CRUD des courses et gestion des réponses des adhérents
 *
 * Les courses sont partagées par n'importe quel adhérent connecté.
 * Les autres adhérents peuvent indiquer qu'ils sont intéressés ou inscrits.
 */

/**
 * Valide les données d'une course et retourne les champs nettoyés.
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function validate_race_payload(array $payload): array
{
    $data = [
        'title' => trim((string) ($payload['title'] ?? '')),
        'start_date' => trim((string) ($payload['start_date'] ?? '')),
        'end_date' => trim((string) ($payload['end_date'] ?? '')),
        'location' => trim((string) ($payload['location'] ?? '')),
        'distances' => trim((string) ($payload['distances'] ?? '')),
        'website_url' => trim((string) ($payload['website_url'] ?? '')),
        'registration_info' => trim((string) ($payload['registration_info'] ?? '')),
        'created_by' => isset($payload['created_by']) ? (int) $payload['created_by'] : null,
    ];

    $errors = [];

    if ($data['title'] === '') {
        $errors[] = 'Le nom de la course est obligatoire.';
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

    if ($data['distances'] === '') {
        $errors[] = 'Les distances sont obligatoires.';
    }

    if ($data['website_url'] !== '' && !preg_match('#^https?://#i', $data['website_url'])) {
        $errors[] = 'Le lien doit commencer par http:// ou https://';
    }

    return [$data, $errors];
}

/**
 * Crée une nouvelle course.
 *
 * @param array $payload Données du formulaire (title, start_date, end_date,
 *                       location, distances, website_url, registration_info, created_by)
 *
 * @return void
 *
 * @throws RuntimeException Si les données sont invalides
 */
function create_race(array $payload): void
{
    [$data, $errors] = validate_race_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $stmt = app_pdo()->prepare(
        'INSERT INTO races (title, start_date, end_date, location, distances, website_url, registration_info, created_by)
         VALUES (:title, :start_date, :end_date, :location, :distances, :website_url, :registration_info, :created_by)'
    );

    $stmt->execute(
        [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'distances' => $data['distances'],
        'website_url' => $data['website_url'] !== '' ? $data['website_url'] : null,
        'registration_info' => $data['registration_info'] !== '' ? $data['registration_info'] : null,
        'created_by' => $data['created_by'] !== null && $data['created_by'] > 0 ? $data['created_by'] : null,
        ]
    );
}

/**
 * Récupère une course par son ID (avec le nom du créateur).
 *
 * @param int $id Identifiant de la course
 *
 * @return array|null Données de la course ou null
 */
function get_race_by_id(int $id): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.*, m.first_name AS author_first_name, m.last_name AS author_last_name, m.photo_path AS author_photo_path
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         WHERE r.id = :id'
    );

    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();

    return $result === false ? null : $result;
}

/**
 * Modifie une course existante.
 *
 * @param int   $id      Identifiant de la course
 * @param array $payload Données du formulaire
 *
 * @return void
 *
 * @throws RuntimeException Si les données sont invalides
 */
function update_race(int $id, array $payload): void
{
    [$data, $errors] = validate_race_payload($payload);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $createdBy = $data['created_by'];

    $sql = 'UPDATE races
             SET title = :title,
                 start_date = :start_date,
                 end_date = :end_date,
                 location = :location,
                 distances = :distances,
                 website_url = :website_url,
                 registration_info = :registration_info';

    $params = [
        'title' => $data['title'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
        'location' => $data['location'] !== '' ? $data['location'] : null,
        'distances' => $data['distances'],
        'website_url' => $data['website_url'] !== '' ? $data['website_url'] : null,
        'registration_info' => $data['registration_info'] !== '' ? $data['registration_info'] : null,
        'id' => $id,
    ];

    if ($createdBy !== null) {
        $sql .= ', created_by = :created_by';
        $params['created_by'] = $createdBy > 0 ? $createdBy : null;
    }

    $sql .= ' WHERE id = :id';

    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);
}

/**
 * Supprime définitivement une course (et ses réponses en cascade).
 *
 * @param int $id Identifiant de la course
 *
 * @return void
 */
function delete_race(int $id): void
{
    $stmt = app_pdo()->prepare('DELETE FROM races WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * Récupère toutes les courses, triées par date de début décroissante.
 * Inclut le nom du créateur.
 *
 * @return array Liste des courses
 */
function get_all_races(): array
{
    $stmt = app_pdo()->query(
        'SELECT r.*, m.first_name AS author_first_name, m.last_name AS author_last_name, m.photo_path AS author_photo_path
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         ORDER BY r.start_date DESC'
    );

    return $stmt->fetchAll();
}

/**
 * Récupère les prochaines courses (date de début >= aujourd'hui),
 * triées par date croissante, limitées à $limit résultats.
 *
 * @param int $limit Nombre maximum de courses à retourner
 *
 * @return array Liste des courses
 */
function get_upcoming_races(int $limit = 2): array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.id, r.title, r.start_date, r.end_date, r.location, r.distances, r.website_url
         FROM races r
         WHERE COALESCE(r.end_date, r.start_date) >= CURDATE()
         ORDER BY r.start_date ASC
         LIMIT ' . (int) $limit
    );
    // (int) $limit garantit que la valeur injectée dans la requête est un entier sûr.
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Récupère les réponses (intéressés et inscrits) pour une course donnée.
 *
 * @param int $raceId Identifiant de la course
 *
 * @return array Tableau avec deux clés : 'interested' et 'registered', chacune contenant un tableau de membres
 */
function get_race_responses(int $raceId): array
{
    $stmt = app_pdo()->prepare(
        'SELECT rr.status, m.id, m.first_name, m.last_name, m.photo_path, m.gender
         FROM race_responses rr
         INNER JOIN members m ON m.id = rr.member_id
         WHERE rr.race_id = :race_id
         ORDER BY m.first_name ASC, m.last_name ASC'
    );

    $stmt->execute(['race_id' => $raceId]);

    $result = ['interested' => [], 'registered' => []];

    foreach ($stmt->fetchAll() as $row) {
        $result[$row['status']][] = $row;
    }

    return $result;
}

/**
 * Récupère la réponse d'un membre pour une course donnée.
 *
 * @param int $raceId   Identifiant de la course
 * @param int $memberId Identifiant du membre
 *
 * @return string|null 'interested', 'registered' ou null si pas de réponse
 */
function get_member_race_response(int $raceId, int $memberId): ?string
{
    $stmt = app_pdo()->prepare(
        'SELECT status FROM race_responses WHERE race_id = :race_id AND member_id = :member_id LIMIT 1'
    );

    $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId]);
    $result = $stmt->fetch();

    return $result === false ? null : (string) $result['status'];
}

/**
 * Définit ou met à jour la réponse d'un membre pour une course.
 * Si le statut est le même que la réponse actuelle, la réponse est supprimée (toggle).
 *
 * @param int    $raceId   Identifiant de la course
 * @param int    $memberId Identifiant du membre
 * @param string $status   'interested' ou 'registered'
 *
 * @return void
 */
function set_race_response(int $raceId, int $memberId, string $status): void
{
    if (!in_array($status, ['interested', 'registered'], true)) {
        return;
    }

    $current = get_member_race_response($raceId, $memberId);

    if ($current === $status) {
        // Toggle off : supprimer la réponse
        $stmt = app_pdo()->prepare('DELETE FROM race_responses WHERE race_id = :race_id AND member_id = :member_id');
        $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId]);
    } else {
        // Upsert : insérer ou mettre à jour
        $stmt = app_pdo()->prepare(
            'INSERT INTO race_responses (race_id, member_id, status)
             VALUES (:race_id, :member_id, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        );
        $stmt->execute(['race_id' => $raceId, 'member_id' => $memberId, 'status' => $status]);
    }
}
