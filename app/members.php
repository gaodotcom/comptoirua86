<?php

declare(strict_types=1);

/**
 * Members — gestion des adhérents, trombinoscope, anniversaires, adhésions
 *
 * Toutes les fonctions liées aux membres : lecture, création, modification,
 * suppression (soft delete), trombinoscope, anniversaires, adhésions,
 * étiquettes de rôle, et mise à jour de la photo de profil.
 */

/**
 * Récupère un membre par son ID (non supprimé).
 *
 * @return array|null Données du membre ou null
 */
function get_member_by_id(int $memberId): ?array
{
    $stmt = app_pdo()->prepare('SELECT * FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $memberId]);
    $member = $stmt->fetch();

    return $member === false ? null : $member;
}

/**
 * Rattache l'historique d'adhésions à une liste de membres.
 * Évite le problème N+1 en chargeant tout l'historique en une seule requête.
 *
 * @param array $members Liste de membres (avec clé 'id')
 *
 * @return array Liste des membres enrichis avec la clé 'memberships'
 */
function attach_membership_history(array $members): array
{
    if ($members === []) {
        return [];
    }

    $ids = array_map(static fn (array $member): int => (int) $member['id'], $members);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $historyStmt = app_pdo()->prepare(
        'SELECT member_id, school_year, fee, donation, created_at
         FROM memberships
         WHERE member_id IN (' . $placeholders . ')
         ORDER BY school_year DESC, created_at DESC'
    );
    $historyStmt->execute($ids);

    $historyByMember = [];
    foreach ($historyStmt->fetchAll() as $row) {
        $historyByMember[(int) $row['member_id']][] = $row;
    }

    foreach ($members as &$member) {
        $member['memberships'] = $historyByMember[(int) $member['id']] ?? [];
    }
    unset($member);

    return $members;
}

/**
 * Met à jour la photo de profil d'un membre.
 *
 * @return void
 */
function update_member_photo(int $memberId, string $photoPath): void
{
    $stmt = app_pdo()->prepare('UPDATE members SET photo_path = :photo_path WHERE id = :id');
    $stmt->execute(
        [
        'photo_path' => $photoPath,
        'id' => $memberId,
        ]
    );
}

/**
 * Récupère les membres pour le trombinoscope (non supprimés, hors comptes génériques).
 * N'affiche que les membres ayant une adhésion dans une année active,
 * quel que soit leur rôle. Tri par prénom.
 *
 * @return array Liste des membres (id, first_name, last_name, photo_path, is_bureau, is_coach, bureau_role, gender)
 */
function get_trombinoscope_members(): array
{
    $stmt = app_pdo()->query(
        'SELECT id, first_name, last_name, photo_path, is_bureau, is_coach, bureau_role, gender
         FROM members
         WHERE deleted_at IS NULL
           AND generic_account = 0
           AND EXISTS (
               SELECT 1 FROM memberships ms
               WHERE ms.member_id = members.id
                 AND ms.school_year IN (SELECT school_year FROM active_school_years)
           )
         ORDER BY first_name ASC'
    );

    return $stmt->fetchAll();
}

/**
 * Récupère l'historique des adhésions d'un membre (par année scolaire décroissante).
 *
 * @return array Liste des adhésions (school_year, fee, donation, created_at)
 */
function get_member_memberships(int $memberId): array
{
    $stmt = app_pdo()->prepare(
        'SELECT school_year, fee, donation, created_at
         FROM memberships
         WHERE member_id = :member_id
         ORDER BY school_year DESC, created_at DESC'
    );

    $stmt->execute(['member_id' => $memberId]);

    return $stmt->fetchAll();
}

/**
 * Récupère tous les membres avec leur historique d'adhésions.
 * Utilisé par la page de liste détaillée.
 *
 * @return array Liste des membres enrichis avec la clé memberships
 */
function get_detailed_members_with_history(): array
{
    $stmt = app_pdo()->query(
        'SELECT id, role, gender, first_name, last_name, username, address, postal_code,
                city, date_of_birth, phone, email, whatsapp_opt_in, photo_path, is_bureau,
                is_coach, bureau_role, generic_account
         FROM members
         WHERE deleted_at IS NULL
         ORDER BY generic_account ASC, first_name ASC, last_name ASC'
    );

    return attach_membership_history($stmt->fetchAll());
}

/**
 * Récupère tous les membres ayant une adhésion pour une année scolaire donnée,
 * avec leur historique complet d'adhésions. Inclut les membres actifs et inactifs.
 *
 * @param string $schoolYear Année scolaire (ex: '2021-2022')
 *
 * @return array Liste des membres avec historique d adhésions
 */
function get_members_by_school_year(string $schoolYear): array
{
    $stmt = app_pdo()->prepare(
        'SELECT DISTINCT m.id, m.role, m.gender, m.first_name, m.last_name, m.username,
                m.address, m.postal_code, m.city, m.date_of_birth, m.phone, m.email,
                m.whatsapp_opt_in, m.photo_path, m.is_bureau, m.is_coach, m.bureau_role,
                m.generic_account
         FROM members m
         INNER JOIN memberships ms ON ms.member_id = m.id
         WHERE m.deleted_at IS NULL
           AND ms.school_year = :sy
         ORDER BY m.generic_account ASC, m.first_name ASC, m.last_name ASC'
    );
    $stmt->execute([':sy' => $schoolYear]);

    return attach_membership_history($stmt->fetchAll());
}

/**
 * Récupère les anciens membres : non supprimés, non génériques, sans adhésion
 * dans une année active. Inclut l'historique de leurs adhésions.
 * Tri par nom de famille.
 *
 * @return array Liste des anciens membres avec historique d adhésions
 */
function get_inactive_members(): array
{
    $stmt = app_pdo()->query(
        'SELECT id, role, gender, first_name, last_name, username, address, postal_code,
                city, date_of_birth, phone, email, whatsapp_opt_in, photo_path, is_bureau,
                is_coach, bureau_role, generic_account
         FROM members
         WHERE deleted_at IS NULL
           AND generic_account = 0
           AND NOT EXISTS (
               SELECT 1 FROM memberships ms
               WHERE ms.member_id = members.id
                 AND ms.school_year IN (SELECT school_year FROM active_school_years)
           )
         ORDER BY last_name ASC, first_name ASC'
    );

    return attach_membership_history($stmt->fetchAll());
}

/**
 * Récupère les comptes génériques (test) non supprimés.
 *
 * @return array Liste des comptes génériques
 */
function get_generic_members(): array
{
    $stmt = app_pdo()->query(
        'SELECT id, role, gender, first_name, last_name, username, address, postal_code,
                city, date_of_birth, phone, email, whatsapp_opt_in, photo_path, is_bureau,
                is_coach, bureau_role, generic_account
         FROM members
         WHERE deleted_at IS NULL
           AND generic_account = 1
         ORDER BY first_name ASC, last_name ASC'
    );

    return $stmt->fetchAll();
}

/**
 * Récupère les membres actifs (non supprimés) pour un sélecteur de formulaire
 * (id + nom uniquement). Utilisé pour réassigner l'auteur d'une course.
 *
 * @return array Liste des membres (id, first_name, last_name)
 */
function get_members_for_select(): array
{
    $stmt = app_pdo()->query(
        'SELECT id, first_name, last_name FROM members WHERE deleted_at IS NULL ORDER BY first_name ASC, last_name ASC'
    );

    return $stmt->fetchAll();
}

/**
 * Récupère des membres par leurs IDs (id + nom uniquement), triés par nom.
 * Utilisé pour les sélecteurs de coéquipier (ex: week-end club).
 *
 * @param array $memberIds IDs des membres à récupérer
 *
 * @return array Liste des membres (id, first_name, last_name)
 */
function get_members_by_ids(array $memberIds): array
{
    if ($memberIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = app_pdo()->prepare(
        "SELECT id, first_name, last_name FROM members WHERE id IN ($placeholders) ORDER BY first_name ASC, last_name ASC"
    );
    $stmt->execute(array_values($memberIds));

    return $stmt->fetchAll();
}

/**
 * Vérifie l'unicité d'un champ (username ou email) en base.
 *
 * @param string $column   Nom de la colonne ('username' ou 'email')
 * @param string $value    Valeur à vérifier
 * @param int|null $memberId ID du membre à exclure (en édition)
 *
 * @return bool True si la valeur est déjà utilisée
 */
function member_field_is_taken(string $column, string $value, ?int $memberId = null): bool
{
    $sql = 'SELECT id FROM members WHERE LOWER(' . $column . ') = LOWER(:value)';
    $params = ['value' => $value];

    if ($memberId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $memberId;
    }

    $sql .= ' LIMIT 1';
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch() !== false;
}

/**
 * Vérifie que le rôle au bureau est valide si renseigné.
 *
 * @param string $bureauRole Rôle au bureau à valider
 *
 * @return string|null Message d'erreur, ou null si valide
 */
function validate_bureau_role(string $bureauRole): ?string
{
    $bureauRoles = [
        'Président(e)',
        'Vice-président(e)',
        'Trésorier(ère)',
        'Trésorier(ère) adjoint(e)',
        'Secrétaire',
        'Secrétaire adjoint(e)',
    ];

    return in_array($bureauRole, $bureauRoles, true) ? null : 'Le rôle au bureau est invalide.';
}

/**
 * Vérifie qu'une date de naissance est valide au format Y-m-d.
 *
 * @param string $date Date à valider
 *
 * @return string|null Message d'erreur, ou null si valide
 */
function validate_date_of_birth(string $date): ?string
{
    $obj = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    if ($obj === false || $obj->format('Y-m-d') !== $date) {
        return 'La date de naissance est invalide.';
    }

    return null;
}

/**
 * Nettoie et structure les données d'un membre depuis un payload de formulaire.
 *
 * @param array $payload Données brutes du formulaire
 *
 * @return array Données nettoyées
 */
function sanitize_member_data(array $payload): array
{
    return [
        'first_name' => trim((string) ($payload['first_name'] ?? '')),
        'last_name' => trim((string) ($payload['last_name'] ?? '')),
        'username' => trim((string) ($payload['username'] ?? '')),
        'email' => trim((string) ($payload['email'] ?? '')),
        'role' => trim((string) ($payload['role'] ?? 'adherent')),
        'gender' => trim((string) ($payload['gender'] ?? '')),
        'date_of_birth' => trim((string) ($payload['date_of_birth'] ?? '')),
        'phone' => trim((string) ($payload['phone'] ?? '')),
        'address' => trim((string) ($payload['address'] ?? '')),
        'postal_code' => trim((string) ($payload['postal_code'] ?? '')),
        'city' => trim((string) ($payload['city'] ?? '')),
        'whatsapp_opt_in' => !empty($payload['whatsapp_opt_in']) ? 1 : 0,
        'is_bureau' => !empty($payload['is_bureau']) ? 1 : 0,
        'is_coach' => !empty($payload['is_coach']) ? 1 : 0,
        'generic_account' => !empty($payload['generic_account']) ? 1 : 0,
        'bureau_role' => trim((string) ($payload['bureau_role'] ?? '')),
    ];
}

/**
 * Valide les données d'un membre avant création ou modification.
 * Vérifie les champs obligatoires, les formats, et l'unicité du username et de l'email.
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function validate_member_payload(array $payload, ?int $memberId = null): array
{
    $data = sanitize_member_data($payload);

    $errors = [];

    if ($data['first_name'] === '') {
        $errors[] = 'Le prenom est obligatoire.';
    }

    if ($data['last_name'] === '') {
        $errors[] = 'Le nom est obligatoire.';
    }

    if (!in_array($data['role'], ['adherent', 'coach', 'bureau', 'admin'], true)) {
        $errors[] = 'Le role selectionne est invalide.';
    }

    if ($data['is_bureau'] === 1 && $data['bureau_role'] !== '') {
        $errors = array_merge($errors, array_filter([validate_bureau_role($data['bureau_role'])]));
    }

    if ($data['is_bureau'] === 0) {
        $data['bureau_role'] = '';
    }

    if ($data['gender'] !== '' && !in_array($data['gender'], ['M', 'F'], true)) {
        $errors[] = 'Le sexe selectionne est invalide.';
    }

    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L adresse email est invalide.';
    }

    if ($data['date_of_birth'] !== '') {
        $errors = array_merge($errors, array_filter([validate_date_of_birth($data['date_of_birth'])]));
    }

    if ($data['username'] !== '' && member_field_is_taken('username', $data['username'], $memberId)) {
        $errors[] = 'Ce nom d utilisateur est deja utilise.';
    }

    if ($data['email'] !== '' && member_field_is_taken('email', $data['email'], $memberId)) {
        $errors[] = 'Cette adresse email est deja utilisee.';
    }

    return [$data, $errors];
}

/**
 * Crée un nouveau membre.
 *
 * @return array{0: bool, 1: array, 2: int} [succès, liste d'erreurs, ID créé (0 si échec)]
 */
function admin_create_member(array $payload): array
{
    [$data, $errors] = validate_member_payload($payload);

    if ($errors !== []) {
        return [false, $errors, 0];
    }

    $passwordHash = null;

    $stmt = app_pdo()->prepare(
        'INSERT INTO members (role, gender, first_name, last_name, username, email,
                              password_hash, date_of_birth, phone, address, postal_code,
                              city, whatsapp_opt_in, is_bureau, is_coach, generic_account,
                              bureau_role)
         VALUES (:role, :gender, :first_name, :last_name, :username, :email,
                 :password_hash, :date_of_birth, :phone, :address, :postal_code, :city,
                 :whatsapp_opt_in, :is_bureau, :is_coach, :generic_account, :bureau_role)'
    );

    $stmt->execute(
        [
        'role' => $data['role'],
        'gender' => $data['gender'] !== '' ? $data['gender'] : null,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'username' => $data['username'] !== '' ? $data['username'] : null,
        'email' => $data['email'] !== '' ? $data['email'] : null,
        'password_hash' => $passwordHash,
        'date_of_birth' => $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
        'phone' => $data['phone'] !== '' ? $data['phone'] : null,
        'address' => $data['address'] !== '' ? $data['address'] : null,
        'postal_code' => $data['postal_code'] !== '' ? $data['postal_code'] : null,
        'city' => $data['city'] !== '' ? $data['city'] : null,
        'whatsapp_opt_in' => $data['whatsapp_opt_in'],
        'is_bureau' => $data['is_bureau'],
        'is_coach' => $data['is_coach'],
        'generic_account' => $data['generic_account'],
        'bureau_role' => $data['bureau_role'] !== '' ? $data['bureau_role'] : null,
        ]
    );

    return [true, [], (int) app_pdo()->lastInsertId()];
}

/**
 * Modifie un membre existant.
 *
 * @return array{0: bool, 1: array} [succès, liste d'erreurs]
 */
function admin_update_member(int $memberId, array $payload): array
{
    [$data, $errors] = validate_member_payload($payload, $memberId);

    if ($errors !== []) {
        return [false, $errors];
    }

    $existing = get_member_by_id($memberId);

    if ($existing === null) {
        return [false, ['Adhérent introuvable.']];
    }

    $passwordHash = $existing['password_hash'];

    $stmt = app_pdo()->prepare(
        'UPDATE members
         SET role = :role,
             gender = :gender,
             first_name = :first_name,
             last_name = :last_name,
             username = :username,
             email = :email,
             password_hash = :password_hash,
             date_of_birth = :date_of_birth,
             phone = :phone,
             address = :address,
             postal_code = :postal_code,
             city = :city,
             whatsapp_opt_in = :whatsapp_opt_in,
             is_bureau = :is_bureau,
             is_coach = :is_coach,
             generic_account = :generic_account,
             bureau_role = :bureau_role
         WHERE id = :id'
    );

    $stmt->execute(
        [
        'id' => $memberId,
        'role' => $data['role'],
        'gender' => $data['gender'] !== '' ? $data['gender'] : null,
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'username' => $data['username'] !== '' ? $data['username'] : null,
        'email' => $data['email'] !== '' ? $data['email'] : null,
        'password_hash' => $passwordHash,
        'date_of_birth' => $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
        'phone' => $data['phone'] !== '' ? $data['phone'] : null,
        'address' => $data['address'] !== '' ? $data['address'] : null,
        'postal_code' => $data['postal_code'] !== '' ? $data['postal_code'] : null,
        'city' => $data['city'] !== '' ? $data['city'] : null,
        'whatsapp_opt_in' => $data['whatsapp_opt_in'],
        'is_bureau' => $data['is_bureau'],
        'is_coach' => $data['is_coach'],
        'generic_account' => $data['generic_account'],
        'bureau_role' => $data['bureau_role'] !== '' ? $data['bureau_role'] : null,
        ]
    );

    current_user(true);

    return [true, []];
}

/**
 * Soft delete : marque un membre comme supprimé (deleted_at).
 * Le membre n'apparaît plus nulle part mais reste en base.
 *
 * @return void
 */
function admin_delete_member(int $memberId): void
{
    $stmt = app_pdo()->prepare('UPDATE members SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute(['id' => $memberId]);
}

/**
 * Ajoute ou met à jour une adhésion pour un membre (upsert).
 *
 * @return array{0: bool, 1: array} [succès, liste d'erreurs]
 */
function add_membership_for_member(int $memberId, array $payload): array
{
    $schoolYear = trim((string) ($payload['school_year'] ?? ''));
    $fee = trim((string) ($payload['fee'] ?? '0'));
    $donation = trim((string) ($payload['donation'] ?? '0'));

    $errors = [];

    if (!is_school_year($schoolYear)) {
        $errors[] = 'Le format de l annee scolaire doit etre AAAA-AAAA.';
    }

    if (!is_numeric($fee)) {
        $errors[] = 'Le tarif doit etre numerique.';
    }

    if (!is_numeric($donation)) {
        $errors[] = 'Le don doit etre numerique.';
    }

    if ($errors !== []) {
        return [false, $errors];
    }

    // Upsert : si une adhésion existe déjà pour (membre, année), on met à jour tarif et don.
    $stmt = app_pdo()->prepare(
        'INSERT INTO memberships (member_id, school_year, fee, donation)
         VALUES (:member_id, :school_year, :fee, :donation)
         ON DUPLICATE KEY UPDATE fee = VALUES(fee), donation = VALUES(donation)'
    );

    $stmt->execute(
        [
        'member_id' => $memberId,
        'school_year' => $schoolYear,
        'fee' => (int) $fee,
        'donation' => (int) $donation,
        ]
    );

    return [true, []];
}

/**
 * Calcule les $limit prochains anniversaires parmi les membres actifs.
 * Retourne un tableau trié par proximité, chaque entrée contenant
 * la date du prochain anniversaire et le nombre de jours restants.
 *
 * @return array Liste des prochains anniversaires [id, first_name, last_name, date_of_birth, next_birthday, days_until]
 */
function get_next_birthdays(int $limit = 5): array
{
    $stmt = app_pdo()->query(
        'SELECT id, first_name, last_name, date_of_birth
         FROM members
         WHERE date_of_birth IS NOT NULL
           AND generic_account = 0
           AND deleted_at IS NULL
         ORDER BY month(date_of_birth) ASC, day(date_of_birth) ASC'
    );

    $members = $stmt->fetchAll();
    $today = new DateTimeImmutable('today');
    $upcoming = [];

    foreach ($members as $member) {
        if (empty($member['date_of_birth'])) {
            continue;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $member['date_of_birth']);

        if ($date === false) {
            continue;
        }

        // Prochain anniversaire : on reporte le jour/mois de naissance sur l'année courante.
        $next = DateTimeImmutable::createFromFormat('Y-m-d', $today->format('Y') . '-' . $date->format('m-d'));

        if ($next === false) {
            continue;
        }

        // Si l'anniversaire est déjà passé cette année, on bascule sur l'année suivante.
        if ($next < $today) {
            $next = $next->modify('+1 year');
        }

        $diffDays = (int) $today->diff($next)->format('%a');

        $upcoming[] = [
            'id' => (int) $member['id'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'date_of_birth' => $member['date_of_birth'],
            'next_birthday' => $next,
            'days_until' => $diffDays,
        ];
    }

    usort(
        $upcoming,
        static function (array $left, array $right): int {
            $leftTimestamp = $left['next_birthday']->getTimestamp();
            $rightTimestamp = $right['next_birthday']->getTimestamp();

            if ($leftTimestamp === $rightTimestamp) {
                return strcmp((string) $left['last_name'], (string) $right['last_name']);
            }

            return $leftTimestamp <=> $rightTimestamp;
        }
    );

    return array_slice($upcoming, 0, $limit);
}

/**
 * Retourne le libellé d'un rôle (pour l'affichage).
 *
 * @return string
 */
function role_label(string $role): string
{
    return match ($role) {
        'adherent' => 'Adhérent',
        'coach' => 'Coach',
        'bureau' => 'Bureau',
        'admin' => 'Admin',
        default => $role,
    };
}

/**
 * Indique si le genre fourni est féminin.
 *
 * @return bool
 */
function is_feminine(?string $gender): bool
{
    return $gender === 'F';
}
