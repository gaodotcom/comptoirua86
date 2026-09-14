<?php

declare(strict_types=1);

/**
 * News — CRUD des actualités de l'association
 *
 * Création, lecture, modification et suppression des actualités
 * (titre, contenu, publication).
 */

/**
 * Crée une nouvelle actualité.
 *
 * @throws RuntimeException Si les données sont invalides
 * @return void
 */
function create_news(array $payload): void
{
    $title = trim((string) ($payload['title'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));
    // published est un booléen de formulaire : on le normalise en 0/1 pour la base.
    $published = !empty($payload['published']) ? 1 : 0;
    $createdBy = (int) ($payload['created_by'] ?? 0);

    if ($title === '') {
        throw new RuntimeException('Le titre est obligatoire.');
    }

    if ($content === '') {
        throw new RuntimeException('Le contenu est obligatoire.');
    }

    $stmt = app_pdo()->prepare(
        'INSERT INTO news (title, content, published, created_by)
         VALUES (:title, :content, :published, :created_by)'
    );

    $stmt->execute(
        [
        'title' => $title,
        'content' => $content,
        'published' => $published,
        // Auteur optionnel : NULL si non renseigné.
        'created_by' => $createdBy > 0 ? $createdBy : null,
        ]
    );
}

/**
 * Récupère une actualité par son ID.
 * @return array|null Données de l'actualité ou null
 */
function get_news_by_id(int $id): ?array
{
    $stmt = app_pdo()->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $result = $stmt->fetch();

    return $result === false ? null : $result;
}

/**
 * Modifie une actualité existante.
 *
 * @throws RuntimeException Si les données sont invalides
 * @return void
 */
function update_news(int $id, array $payload): void
{
    $title = trim((string) ($payload['title'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));
    $published = !empty($payload['published']) ? 1 : 0;

    if ($title === '') {
        throw new RuntimeException('Le titre est obligatoire.');
    }

    if ($content === '') {
        throw new RuntimeException('Le contenu est obligatoire.');
    }

    $stmt = app_pdo()->prepare(
        'UPDATE news
         SET title = :title,
             content = :content,
             published = :published
         WHERE id = :id'
    );

    $stmt->execute(
        [
        'title' => $title,
        'content' => $content,
        'published' => $published,
        'id' => $id,
        ]
    );
}

/**
 * Supprime définitivement une actualité.
 * @return void
 */
function delete_news(int $id): void
{
    $stmt = app_pdo()->prepare('DELETE FROM news WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * Récupère les actualités publiées, triées par date de création décroissante.
 *
 * @param int $limit Nombre maximum d'actualités (0 = toutes)
 *
 * @return array Liste des résultats
 */
function get_published_news(int $limit = 0): array
{
    $sql = 'SELECT * FROM news WHERE published = 1 ORDER BY created_at DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT :limit';
        $stmt = app_pdo()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    return app_pdo()->query($sql)->fetchAll();
}

/**
 * Récupère toutes les actualités (publiées et brouillons), avec le nom de l'auteur.
 * Utilisé par la page de gestion admin.
 * @return array Liste des résultats
 */
function get_all_news(): array
{
    $stmt = app_pdo()->query(
        'SELECT n.*, m.first_name AS author_first_name, m.last_name AS author_last_name
         FROM news n
         LEFT JOIN members m ON m.id = n.created_by
         ORDER BY n.created_at DESC'
    );

    return $stmt->fetchAll();
}
