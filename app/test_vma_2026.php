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

/**
 * Indique si le formulaire est clos : par défaut à partir du 12/10/2026 à 0h
 * (heure de Paris), sauf si un admin a forcé la clôture ou la réouverture.
 *
 * @return bool
 */
function test_vma_2026_is_closed(): bool
{
    $stmt = app_pdo()->query('SELECT manual_state FROM test_vma_2026_settings WHERE id = 1 LIMIT 1');
    $state = $stmt->fetchColumn();

    if ($state !== false && $state !== null) {
        return (int) $state === 1;
    }

    $tz = new DateTimeZone('Europe/Paris');

    return new DateTimeImmutable('now', $tz) >= new DateTimeImmutable('2026-10-12 00:00:00', $tz);
}

/**
 * Force la clôture (true) ou la réouverture (false) du formulaire.
 *
 * @param bool $closed true pour clôturer, false pour rouvrir
 *
 * @return void
 */
function test_vma_2026_set_closed(bool $closed): void
{
    $stmt = app_pdo()->prepare(
        'INSERT INTO test_vma_2026_settings (id, manual_state) VALUES (1, :state)
         ON DUPLICATE KEY UPDATE manual_state = VALUES(manual_state)'
    );
    $stmt->execute(['state' => $closed ? 1 : 0]);
}

/** Saison d'adhésion requise pour accéder aux pages du test VMA. */
const TEST_VMA_2026_REQUIRED_SCHOOL_YEAR = '2026-2027';

/**
 * Bloque l'accès (page « adhésion requise ») si l'adhésion 2026-2027 est absente,
 * voir require_school_year_membership().
 *
 * @param array $user Membre connecté
 *
 * @return void
 */
function require_test_vma_2026_membership(array $user): void
{
    require_school_year_membership($user, TEST_VMA_2026_REQUIRED_SCHOOL_YEAR, [
        'title' => 'Test VMA du 16 octobre 2026',
        'icon' => 'bi-stopwatch',
        'access_to' => 'au test VMA',
        'next_step' => 't\'inscrire au test VMA',
    ]);
}
