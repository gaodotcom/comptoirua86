<?php

declare(strict_types=1);

/**
 * Week-end club Lozère Trail 2027 — préinscriptions (page ponctuelle).
 *
 * Un adhérent connecté se préinscrit lui-même (choix de course(s) parmi un
 * catalogue fixe, options associées). Un admin peut consulter la liste des
 * inscrits, exporter les données en CSV, et clôturer/rouvrir les
 * préinscriptions.
 */

/**
 * Catalogue fixe des courses proposées pour le week-end club.
 * 'group' vaut 'saturday' ou 'sunday' pour les règles de combinaison (l'Ultra
 * n'a pas de 'group' : elle est exclusive, cf. validate_weekend_2027_payload()).
 *
 * @return array<string, array{label: string, description: string, day: string, group: string|null, price: string}>
 */
function weekend_2027_courses(): array
{
    return [
        'ultra' => [
            'label' => 'Ultra Lozère',
            'description' => '110 km/5600m D+ en 2 étapes',
            'day' => 'Samedi et dimanche',
            'group' => null,
            'price' => '110 € en solo, ou 110 € par équipier en duo (navette 10 €)',
        ],
        'trail2r' => [
            'label' => 'Lozère Trail 2 Rivières',
            'description' => '52 km/1900m D+',
            'day' => 'Dimanche',
            'group' => 'sunday',
            'price' => '60 € (navette 10 €)',
        ],
        'trail27' => [
            'label' => 'Lozère Trail',
            'description' => '27 km/1100m D+',
            'day' => 'Dimanche',
            'group' => 'sunday',
            'price' => '37 €',
        ],
        'salta' => [
            'label' => 'Salta Bartas',
            'description' => '12 km/500m D+',
            'day' => 'Dimanche',
            'group' => 'sunday',
            'price' => '15 €',
        ],
        'skyrace' => [
            'label' => 'Skyrace Gorges du Tarn',
            'description' => '25 km/1900m D+',
            'day' => 'Samedi',
            'group' => 'saturday',
            'price' => '45 €',
        ],
    ];
}

/**
 * Libellé lisible d'une course à partir de son code (retourne le code si inconnu).
 *
 * @return string
 */
function weekend_2027_course_label(string $courseCode): string
{
    return weekend_2027_courses()[$courseCode]['label'] ?? $courseCode;
}

/**
 * Insère un espace de largeur nulle (invisible) entre chaque caractère d'un
 * numéro de téléphone, pour empêcher les clients mail (Gmail, Outlook,
 * Apple Mail...) de le détecter automatiquement comme un numéro cliquable.
 * La balise <meta name="format-detection" content="telephone=no"> ne
 * suffit pas : elle n'est respectée que par les clients Apple/WebKit.
 *
 * @param string $phone Numéro de téléphone en clair
 *
 * @return string Même numéro, visuellement identique, mais non détectable
 */
function weekend_2027_break_phone_autolink(string $phone): string
{
    return implode("\u{200B}", mb_str_split($phone));
}

/**
 * Valide le payload du formulaire de préinscription et retourne les données nettoyées.
 *
 * @param array $payload  Données brutes du formulaire ($_POST)
 * @param int   $memberId Identifiant de l'adhérent qui se préinscrit (pour exclure du choix de coéquipier)
 *
 * @return array{0: array, 1: array} [données nettoyées, liste d'erreurs]
 */
function validate_weekend_2027_payload(array $payload, int $memberId): array
{
    $catalog = weekend_2027_courses();
    $selectedCourses = array_values(array_unique(array_filter(
        array_map('strval', (array) ($payload['courses'] ?? [])),
        static fn (string $code): bool => isset($catalog[$code])
    )));

    $errors = [];

    if ($selectedCourses === []) {
        $errors[] = 'Merci de choisir au moins une course.';
    }

    $hasUltra = in_array('ultra', $selectedCourses, true);
    $sundayCourses = array_filter($selectedCourses, static fn (string $c) => ($catalog[$c]['group'] ?? null) === 'sunday');

    if ($hasUltra && count($selectedCourses) > 1) {
        $errors[] = 'L\'Ultra Lozère ne peut pas être combinée avec une autre course.';
    }

    if (count($sundayCourses) > 1) {
        $errors[] = 'Une seule course du dimanche peut être choisie (2 Rivières, Lozère Trail ou Salta Bartas).';
    }

    $teamMode = trim((string) ($payload['team_mode'] ?? ''));
    $duoPartnerId = (int) ($payload['duo_partner_member_id'] ?? 0);

    if ($hasUltra) {
        if (!in_array($teamMode, ['solo', 'duo'], true)) {
            $errors[] = 'Merci de préciser si vous participez à l\'Ultra Lozère en solo ou en duo.';
        } elseif ($teamMode === 'duo') {
            if ($duoPartnerId <= 0) {
                $errors[] = 'Merci de choisir votre coéquipier pour le duo.';
            } elseif ($duoPartnerId === $memberId) {
                $errors[] = 'Vous ne pouvez pas vous choisir vous-même comme coéquipier.';
            } elseif (!in_array($duoPartnerId, get_active_member_ids(), true)) {
                $errors[] = 'Le coéquipier choisi est introuvable ou n\'est pas un adhérent actif.';
            } else {
                // Un coéquipier ne peut être réservé que par une seule personne : le premier
                // inscrit décide, cf. get_weekend_2027_duo_leader_for_member().
                $stmt = app_pdo()->prepare(
                    'SELECT id FROM weekend_2027_registrations WHERE duo_partner_member_id = :partner_id AND member_id != :member_id LIMIT 1'
                );
                $stmt->execute(['partner_id' => $duoPartnerId, 'member_id' => $memberId]);
                if ($stmt->fetch() !== false) {
                    $errors[] = 'Ce coéquipier a déjà été choisi par un autre adhérent pour l\'Ultra Lozère en duo.';
                }
            }
        }
    } else {
        $teamMode = '';
        $duoPartnerId = 0;
    }

    $bivouac = $hasUltra && !empty($payload['bivouac']) ? 1 : 0;

    $shirtSize = trim((string) ($payload['shirt_size'] ?? ''));
    if (!in_array($shirtSize, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true)) {
        $errors[] = 'Merci de choisir une taille de maillot valide.';
    }

    // Contact d'urgence facultatif.
    $emergencyName = trim((string) ($payload['emergency_contact_name'] ?? ''));
    $emergencyPhone = trim((string) ($payload['emergency_contact_phone'] ?? ''));

    $accommodation = trim((string) ($payload['accommodation'] ?? ''));
    if (!in_array($accommodation, ['group', 'independent'], true)) {
        $errors[] = 'Merci de préciser votre choix d\'hébergement.';
    }

    // Numéro de licence / PPS facultatif.
    $licenseNumber = trim((string) ($payload['license_number'] ?? ''));

    $data = [
        'courses' => $selectedCourses,
        'team_mode' => $teamMode !== '' ? $teamMode : null,
        'duo_partner_member_id' => $duoPartnerId > 0 ? $duoPartnerId : null,
        'bivouac' => $bivouac,
        'shirt_size' => $shirtSize,
        'emergency_contact_name' => $emergencyName,
        'emergency_contact_phone' => $emergencyPhone,
        'accommodation' => $accommodation,
        'license_number' => $licenseNumber,
    ];

    return [$data, $errors];
}

/**
 * Enregistre (crée ou met à jour) la préinscription d'un adhérent.
 *
 * @param int   $memberId Identifiant de l'adhérent
 * @param array $payload  Données brutes du formulaire ($_POST)
 *
 * @return void
 *
 * @throws RuntimeException Si les données sont invalides
 */
function save_weekend_2027_registration(int $memberId, array $payload): void
{
    [$data, $errors] = validate_weekend_2027_payload($payload, $memberId);

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $pdo = app_pdo();

    // Détermine s'il s'agit d'une création ou d'une modification, pour adapter
    // l'objet de l'email de confirmation envoyé après l'enregistrement.
    $existsStmt = $pdo->prepare('SELECT id FROM weekend_2027_registrations WHERE member_id = :member_id LIMIT 1');
    $existsStmt->execute(['member_id' => $memberId]);
    $isUpdate = $existsStmt->fetch() !== false;

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO weekend_2027_registrations
                (member_id, team_mode, duo_partner_member_id, bivouac, shirt_size,
                 emergency_contact_name, emergency_contact_phone,
                 accommodation, license_number)
             VALUES
                (:member_id, :team_mode, :duo_partner_member_id, :bivouac, :shirt_size,
                 :emergency_contact_name, :emergency_contact_phone,
                 :accommodation, :license_number)
             ON DUPLICATE KEY UPDATE
                team_mode = VALUES(team_mode),
                duo_partner_member_id = VALUES(duo_partner_member_id),
                bivouac = VALUES(bivouac),
                shirt_size = VALUES(shirt_size),
                emergency_contact_name = VALUES(emergency_contact_name),
                emergency_contact_phone = VALUES(emergency_contact_phone),
                accommodation = VALUES(accommodation),
                license_number = VALUES(license_number)'
        );
        $stmt->execute(
            [
            'member_id' => $memberId,
            'team_mode' => $data['team_mode'],
            'duo_partner_member_id' => $data['duo_partner_member_id'],
            'bivouac' => $data['bivouac'],
            'shirt_size' => $data['shirt_size'],
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
            'accommodation' => $data['accommodation'],
            'license_number' => $data['license_number'],
            ]
        );

        $stmt = $pdo->prepare('SELECT id FROM weekend_2027_registrations WHERE member_id = :member_id LIMIT 1');
        $stmt->execute(['member_id' => $memberId]);
        $registrationId = (int) $stmt->fetchColumn();

        $pdo->prepare('DELETE FROM weekend_2027_registration_courses WHERE registration_id = :registration_id')
            ->execute(['registration_id' => $registrationId]);

        $insertCourse = $pdo->prepare(
            'INSERT INTO weekend_2027_registration_courses (registration_id, course_code) VALUES (:registration_id, :course_code)'
        );
        foreach ($data['courses'] as $courseCode) {
            $insertCourse->execute(['registration_id' => $registrationId, 'course_code' => $courseCode]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // L'échec de l'envoi d'email n'invalide jamais l'enregistrement, déjà en
    // base à ce stade (send_email() journalise ses propres erreurs).
    send_weekend_2027_registration_confirmation_email($memberId, $data, $isUpdate);
}

/**
 * Construit le récapitulatif et envoie l'email de confirmation/mise à jour
 * de préinscription au membre concerné. Ignore silencieusement si le membre
 * n'a pas d'adresse email connue.
 *
 * @param int   $memberId  Identifiant de l'adhérent
 * @param array $data      Données nettoyées de la préinscription (cf. validate_weekend_2027_payload())
 * @param bool  $isUpdate  true si c'est une mise à jour d'une préinscription existante
 *
 * @return void
 */
function send_weekend_2027_registration_confirmation_email(int $memberId, array $data, bool $isUpdate): void
{
    $stmt = app_pdo()->prepare('SELECT first_name, last_name, email FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $memberId]);
    $member = $stmt->fetch();

    if ($member === false) {
        return;
    }

    // Récupéré une seule fois : sert au récapitulatif ("Duo avec ...") et à la
    // notification envoyée au/à la coéquipier·e plus bas.
    $partner = false;
    // true si le/la coéquipier·e a déjà, de son côté, une préinscription en duo
    // pointant vers moi : il/elle est alors déjà au courant de ce duo (email de
    // "mise à jour"), plutôt que découvrir maintenant qu'il/elle y a été inscrit·e.
    $partnerAlreadyKnowsDuo = false;
    if ($data['team_mode'] === 'duo' && $data['duo_partner_member_id'] !== null) {
        $partnerStmt = app_pdo()->prepare('SELECT first_name, last_name, email FROM members WHERE id = :id');
        $partnerStmt->execute(['id' => $data['duo_partner_member_id']]);
        $partner = $partnerStmt->fetch();

        $partnerRegistration = get_weekend_2027_registration_for_member($data['duo_partner_member_id']);
        $partnerAlreadyKnowsDuo = $partnerRegistration !== null
            && $partnerRegistration['team_mode'] === 'duo'
            && (int) ($partnerRegistration['duo_partner_member_id'] ?? 0) === $memberId;
    }

    $courseLabels = array_map('weekend_2027_course_label', $data['courses']);

    $formuleLabel = '';
    if ($data['team_mode'] === 'duo') {
        $formuleLabel = 'Duo avec ' . ($partner !== false ? $partner['first_name'] . ' ' . $partner['last_name'] : '');
    } elseif ($data['team_mode'] === 'solo') {
        $formuleLabel = 'Solo';
    }

    $summaryLines = [
        ['label' => 'Course(s)', 'value' => implode(' + ', $courseLabels)],
    ];

    if ($formuleLabel !== '') {
        $summaryLines[] = ['label' => 'Formule', 'value' => $formuleLabel];
    }

    if ($data['bivouac']) {
        $summaryLines[] = ['label' => 'Bivouac', 'value' => 'Oui'];
    }

    $summaryLines[] = ['label' => 'Taille de maillot', 'value' => $data['shirt_size']];
    $summaryLines[] = [
        'label' => 'Hébergement',
        'value' => $data['accommodation'] === 'group' ? 'Avec Ultramical86' : 'Indépendant',
    ];

    if ($data['emergency_contact_name'] !== '' || $data['emergency_contact_phone'] !== '') {
        $phone = $data['emergency_contact_phone'] !== '' ? weekend_2027_break_phone_autolink($data['emergency_contact_phone']) : '';
        $contact = trim($data['emergency_contact_name'] . ' ' . ($phone !== '' ? "($phone)" : ''));
        $summaryLines[] = ['label' => 'Contact d\'urgence', 'value' => $contact];
    }

    if ($data['license_number'] !== '') {
        $summaryLines[] = ['label' => 'Licence / PPS', 'value' => $data['license_number']];
    }

    if (!empty($member['email'])) {
        send_weekend_2027_confirmation_email(
            (string) $member['email'],
            (string) $member['first_name'],
            $summaryLines,
            $isUpdate,
            page_url('weekend-club-2027')
        );
    }

    // Notifie le/la coéquipier·e de duo (dans les deux sens : que ce soit le
    // leader ou son partenaire verrouillé qui vienne d'enregistrer), avec un
    // récapitulatif limité aux infos partagées du duo (pas les infos
    // personnelles de l'autre, non pertinentes pour lui/elle).
    if ($partner !== false && !empty($partner['email'])) {
        $duoSummaryLines = [
            ['label' => 'Course', 'value' => weekend_2027_course_label('ultra')],
        ];
        if ($data['bivouac']) {
            $duoSummaryLines[] = ['label' => 'Bivouac', 'value' => 'Oui'];
        }

        send_weekend_2027_duo_partner_notification_email(
            (string) $partner['email'],
            (string) $partner['first_name'],
            (string) $member['first_name'],
            (string) $member['last_name'],
            $duoSummaryLines,
            $partnerAlreadyKnowsDuo,
            page_url('weekend-club-2027')
        );
    }
}

/**
 * Supprime définitivement la préinscription d'un adhérent (et ses courses associées,
 * en cascade via la contrainte de clé étrangère).
 *
 * Si l'adhérent faisait partie d'un duo pour l'Ultra Lozère, gère le sort du/de
 * la coéquipier·e et le/la notifie par email (cf. send_weekend_2027_duo_cancellation_email()) :
 * - si le/la coéquipier·e s'était inscrit·e après moi (son choix de course avait donc
 *   été verrouillé sur ce duo, cf. get_weekend_2027_duo_leader_for_member()), son choix
 *   de course est débloqué (remis à zéro) pour qu'il/elle puisse en refaire un ;
 * - si c'est lui/elle qui s'était inscrit·e en premier (c'est donc lui/elle qui avait
 *   choisi ce duo), sa préinscription n'est pas modifiée : "le premier inscrit décide",
 *   il/elle reste seul·e maître de son choix et devra le mettre à jour lui/elle-même
 *   s'il/elle le souhaite.
 *
 * @param int $memberId Identifiant de l'adhérent
 *
 * @return void
 */
function delete_weekend_2027_registration(int $memberId): void
{
    $registration = get_weekend_2027_registration_for_member($memberId);

    if ($registration === null) {
        return;
    }

    $partnerId = $registration['team_mode'] === 'duo' ? (int) ($registration['duo_partner_member_id'] ?? 0) : 0;
    $partnerRegistration = $partnerId > 0 ? get_weekend_2027_registration_for_member($partnerId) : null;
    $partnerIsMutuallyLinked = $partnerRegistration !== null
        && $partnerRegistration['team_mode'] === 'duo'
        && (int) ($partnerRegistration['duo_partner_member_id'] ?? 0) === $memberId;

    $pdo = app_pdo();
    $pdo->prepare('DELETE FROM weekend_2027_registrations WHERE member_id = :member_id')
        ->execute(['member_id' => $memberId]);

    $partnerWasLocked = false;

    if ($partnerIsMutuallyLinked) {
        // Le/la coéquipier·e s'est inscrit·e après moi : son duo (et donc son choix de
        // course) dépendait du mien, on le débloque pour qu'il/elle puisse en refaire un.
        $partnerWasLocked = strtotime((string) $partnerRegistration['created_at']) > strtotime((string) $registration['created_at']);

        if ($partnerWasLocked) {
            $pdo->prepare(
                'UPDATE weekend_2027_registrations
                 SET team_mode = NULL, duo_partner_member_id = NULL, bivouac = 0
                 WHERE id = :id'
            )->execute(['id' => $partnerRegistration['id']]);
            $pdo->prepare('DELETE FROM weekend_2027_registration_courses WHERE registration_id = :id')
                ->execute(['id' => $partnerRegistration['id']]);
        }
    }

    // L'échec de l'envoi d'email n'a aucun impact sur la suppression, déjà
    // effectuée à ce stade (send_email() journalise ses propres erreurs).
    send_weekend_2027_deletion_confirmation_email($memberId);

    if ($partnerIsMutuallyLinked) {
        send_weekend_2027_duo_cancellation_email($memberId, $partnerId, $partnerWasLocked);
    }
}

/**
 * Envoie une confirmation d'annulation à l'adhérent qui vient de supprimer sa
 * propre préinscription. Ignore silencieusement s'il n'a pas d'email connu.
 *
 * @param int $memberId Identifiant de l'adhérent (déjà supprimé de weekend_2027_registrations)
 *
 * @return void
 */
function send_weekend_2027_deletion_confirmation_email(int $memberId): void
{
    $stmt = app_pdo()->prepare('SELECT first_name, email FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $memberId]);
    $member = $stmt->fetch();

    if ($member === false || empty($member['email'])) {
        return;
    }

    send_weekend_2027_deletion_email(
        (string) $member['email'],
        (string) $member['first_name'],
        page_url('weekend-club-2027')
    );
}

/**
 * Notifie le/la coéquipier·e de duo qu'un membre du duo vient d'annuler sa
 * préinscription. Ignore silencieusement si l'un des deux membres est
 * introuvable ou sans email connu.
 *
 * @param int  $cancellingMemberId Identifiant de l'adhérent qui vient de supprimer sa préinscription
 * @param int  $partnerId          Identifiant du/de la coéquipier·e à notifier
 * @param bool $courseWasCleared   true si le choix de course du/de la coéquipier·e a été remis à
 *                                 zéro (il/elle s'était inscrit·e après, donc son choix en dépendait)
 *
 * @return void
 */
function send_weekend_2027_duo_cancellation_email(int $cancellingMemberId, int $partnerId, bool $courseWasCleared): void
{
    $stmt = app_pdo()->prepare('SELECT first_name, last_name, email FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');

    $stmt->execute(['id' => $partnerId]);
    $partner = $stmt->fetch();

    if ($partner === false || empty($partner['email'])) {
        return;
    }

    $stmt->execute(['id' => $cancellingMemberId]);
    $canceller = $stmt->fetch();

    if ($canceller === false) {
        return;
    }

    send_weekend_2027_duo_cancellation_notification_email(
        (string) $partner['email'],
        (string) $partner['first_name'],
        (string) $canceller['first_name'],
        (string) $canceller['last_name'],
        $courseWasCleared,
        page_url('weekend-club-2027')
    );
}

/**
 * Recherche si un adhérent a été désigné comme coéquipier de duo par quelqu'un
 * d'autre pour l'Ultra Lozère. Si oui, son choix de course est verrouillé sur
 * ce duo : c'est le premier inscrit (le "leader") qui décide, cf.
 * validate_weekend_2027_payload().
 *
 * @param int $memberId Identifiant de l'adhérent potentiellement désigné
 *
 * @return array|null Ligne de préinscription du leader (+ leader_first_name/leader_last_name), ou null
 */
function get_weekend_2027_duo_leader_for_member(int $memberId): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.*, m.first_name AS leader_first_name, m.last_name AS leader_last_name
         FROM weekend_2027_registrations r
         INNER JOIN members m ON m.id = r.member_id
         WHERE r.duo_partner_member_id = :member_id AND r.team_mode = \'duo\'
         ORDER BY r.created_at ASC
         LIMIT 1'
    );
    $stmt->execute(['member_id' => $memberId]);
    $leader = $stmt->fetch();

    return $leader === false ? null : $leader;
}

/**
 * Récupère la préinscription d'un adhérent, avec ses courses choisies et le nom du coéquipier.
 *
 * @param int $memberId Identifiant de l'adhérent
 *
 * @return array|null Données de la préinscription (clé 'courses' = liste de codes), ou null
 */
function get_weekend_2027_registration_for_member(int $memberId): ?array
{
    $stmt = app_pdo()->prepare(
        'SELECT r.*, p.first_name AS duo_partner_first_name, p.last_name AS duo_partner_last_name
         FROM weekend_2027_registrations r
         LEFT JOIN members p ON p.id = r.duo_partner_member_id
         WHERE r.member_id = :member_id
         LIMIT 1'
    );
    $stmt->execute(['member_id' => $memberId]);
    $registration = $stmt->fetch();

    if ($registration === false) {
        return null;
    }

    $stmt = app_pdo()->prepare(
        'SELECT course_code FROM weekend_2027_registration_courses WHERE registration_id = :registration_id'
    );
    $stmt->execute(['registration_id' => (int) $registration['id']]);
    $registration['courses'] = array_column($stmt->fetchAll(), 'course_code');

    return $registration;
}

/**
 * Récupère toutes les préinscriptions (administration), avec les infos de l'adhérent,
 * du coéquipier éventuel, et les courses choisies.
 *
 * @return array Liste des préinscriptions
 */
function get_all_weekend_2027_registrations(): array
{
    $stmt = app_pdo()->query(
        'SELECT r.*,
                m.first_name AS member_first_name, m.last_name AS member_last_name,
                m.phone AS member_phone, m.email AS member_email, m.photo_path AS member_photo_path,
                m.gender AS member_gender,
                p.first_name AS duo_partner_first_name, p.last_name AS duo_partner_last_name
         FROM weekend_2027_registrations r
         INNER JOIN members m ON m.id = r.member_id
         LEFT JOIN members p ON p.id = r.duo_partner_member_id
         ORDER BY m.last_name ASC, m.first_name ASC'
    );
    $registrations = $stmt->fetchAll();

    if ($registrations === []) {
        return [];
    }

    $coursesStmt = app_pdo()->query(
        'SELECT registration_id, course_code FROM weekend_2027_registration_courses'
    );
    $coursesByRegistration = [];
    foreach ($coursesStmt->fetchAll() as $row) {
        $coursesByRegistration[(int) $row['registration_id']][] = $row['course_code'];
    }

    foreach ($registrations as &$registration) {
        $registration['courses'] = $coursesByRegistration[(int) $registration['id']] ?? [];
    }
    unset($registration);

    return $registrations;
}

/**
 * Indique si les préinscriptions sont actuellement clôturées.
 *
 * @return bool
 */
function weekend_2027_is_closed(): bool
{
    $stmt = app_pdo()->query('SELECT is_closed FROM weekend_2027_settings WHERE id = 1 LIMIT 1');
    $row = $stmt->fetch();

    return $row !== false && (int) $row['is_closed'] === 1;
}

/**
 * Clôture ou rouvre les préinscriptions.
 *
 * @param bool $closed  true pour clôturer, false pour rouvrir
 * @param int  $adminId Identifiant de l'admin qui effectue l'action
 *
 * @return void
 */
function weekend_2027_set_closed(bool $closed, int $adminId): void
{
    $stmt = app_pdo()->prepare(
        'UPDATE weekend_2027_settings
         SET is_closed = :is_closed,
             closed_at = :closed_at,
             closed_by = :closed_by
         WHERE id = 1'
    );
    $stmt->execute(
        [
        'is_closed' => $closed ? 1 : 0,
        'closed_at' => $closed ? date('Y-m-d H:i:s') : null,
        'closed_by' => $closed ? $adminId : null,
        ]
    );
}
