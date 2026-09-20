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

    // Nouvelle actualité en première position de l'ordre d'affichage.
    $nextSortOrder = (int) app_pdo()->query('SELECT COALESCE(MIN(sort_order), 1) - 1 FROM news')->fetchColumn();

    $stmt = app_pdo()->prepare(
        'INSERT INTO news (title, content, published, sort_order, created_by)
         VALUES (:title, :content, :published, :sort_order, :created_by)'
    );

    $stmt->execute(
        [
        'title' => $title,
        'content' => $content,
        'published' => $published,
        'sort_order' => $nextSortOrder,
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
 * Déplace une actualité d'un cran vers le haut ou le bas dans l'ordre
 * d'affichage, en échangeant son sort_order avec celui de sa voisine.
 * Sans effet si l'actualité est déjà première (haut) ou dernière (bas).
 *
 * @return void
 */
function move_news(int $id, string $direction): void
{
    $pdo = app_pdo();

    $stmt = $pdo->prepare('SELECT sort_order FROM news WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch();

    if ($current === false) {
        return;
    }

    $currentSortOrder = (int) $current['sort_order'];

    if ($direction === 'up') {
        $neighborSql = 'SELECT id, sort_order FROM news WHERE sort_order < :sort_order ORDER BY sort_order DESC LIMIT 1';
    } elseif ($direction === 'down') {
        $neighborSql = 'SELECT id, sort_order FROM news WHERE sort_order > :sort_order ORDER BY sort_order ASC LIMIT 1';
    } else {
        throw new RuntimeException('Direction invalide.');
    }

    $stmt = $pdo->prepare($neighborSql);
    $stmt->execute(['sort_order' => $currentSortOrder]);
    $neighbor = $stmt->fetch();

    if ($neighbor === false) {
        // Déjà en première (haut) ou dernière (bas) position.
        return;
    }

    $pdo->beginTransaction();

    $swap = $pdo->prepare('UPDATE news SET sort_order = :sort_order WHERE id = :id');
    $swap->execute(['sort_order' => $neighbor['sort_order'], 'id' => $id]);
    $swap->execute(['sort_order' => $currentSortOrder, 'id' => $neighbor['id']]);

    $pdo->commit();
}

/**
 * Récupère les actualités publiées, triées selon l'ordre d'affichage défini
 * par les admins.
 *
 * @param int $limit Nombre maximum d'actualités (0 = toutes)
 *
 * @return array Liste des résultats
 */
function get_published_news(int $limit = 0): array
{
    $sql = 'SELECT * FROM news WHERE published = 1 ORDER BY sort_order ASC';

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
         ORDER BY n.sort_order ASC'
    );

    return $stmt->fetchAll();
}
