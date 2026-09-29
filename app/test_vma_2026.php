<?php

declare(strict_types=1);

/**
 * Test VMA du vendredi 16 octobre 2026 (page ponctuelle).
 *
 * Test proposé par l'association Béruges Sport Nature sur la piste du CREPS de
 * Boivre. Chaque adhérent connecté indique simplement s'il participe ou non ;
 * un adhérent sans ligne n'a pas encore répondu.
 */

/**
 * Réponse d'un adhérent au test VMA.
 *
 * @param int $memberId Identifiant de l'adhérent
 *
 * @return bool|null true = participe, false = a répondu non, null = pas encore répondu
 */
function get_test_vma_2026_answer(int $memberId): ?bool
{
    $stmt = app_pdo()->prepare('SELECT participates FROM test_vma_2026_participants WHERE member_id = :member_id LIMIT 1');
    $stmt->execute(['member_id' => $memberId]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (int) $value === 1;
}

/**
 * Enregistre la réponse d'un adhérent au test VMA. La date d'inscription n'est
 * mise à jour que si la réponse change (elle sert à ordonner la liste).
 *
 * @param int  $memberId    Identifiant de l'adhérent
 * @param bool $participate true pour participer, false pour refuser
 *
 * @return void
 */
function set_test_vma_2026_participation(int $memberId, bool $participate): void
{
    $stmt = app_pdo()->prepare(
        'INSERT INTO test_vma_2026_participants (member_id, participates)
         VALUES (:member_id, :participates)
         ON DUPLICATE KEY UPDATE
             created_at = IF(participates <> VALUES(participates), CURRENT_TIMESTAMP, created_at),
             participates = VALUES(participates)'
    );
    $stmt->execute(['member_id' => $memberId, 'participates' => $participate ? 1 : 0]);
}

/**
 * Liste des participants (du dernier inscrit au premier) avec leurs coordonnées.
 *
 * @return array<int, array<string, mixed>>
 */
function get_test_vma_2026_participants(): array
{
    $stmt = app_pdo()->query(
        'SELECT p.member_id, p.created_at,
                m.first_name, m.last_name, m.email, m.phone, m.photo_path
         FROM test_vma_2026_participants p
         INNER JOIN members m ON m.id = p.member_id
         WHERE p.participates = 1
         ORDER BY p.created_at DESC, p.id DESC'
    );

    return $stmt->fetchAll();
}
