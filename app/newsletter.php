<?php

declare(strict_types=1);

/**
 * Newsletter — composition de la newsletter hebdomadaire du lundi.
 *
 * Rassemble les contenus de la semaine (plan du coach, courses, anniversaires,
 * agenda, actualités) et les rend en HTML d'email via le gabarit Twig
 * templates/emails/newsletter.twig.
 */

/**
 * Renvoie le lundi de la semaine contenant une date.
 *
 * @param string|null $date Date Y-m-d (aujourd'hui si null ou invalide)
 *
 * @return DateTimeImmutable Le lundi à 00:00
 */
function newsletter_monday(?string $date = null): DateTimeImmutable
{
	$day = ($date !== null && is_iso_date($date))
		? new DateTimeImmutable($date)
		: new DateTimeImmutable('today');

	return $day->modify('monday this week');
}

/**
 * Anniversaires des adhérents actifs dont le jour/mois tombe entre deux dates
 * (bornes incluses), triés par date.
 *
 * @param DateTimeImmutable $from Premier jour
 * @param DateTimeImmutable $to   Dernier jour
 *
 * @return array Liste [first_name, last_name, date, age]
 */
function get_birthdays_between(DateTimeImmutable $from, DateTimeImmutable $to): array
{
	$members = app_pdo()->query(
		'SELECT id, first_name, last_name, date_of_birth
         FROM members
         WHERE date_of_birth IS NOT NULL
           AND generic_account = 0
           AND deleted_at IS NULL
           AND EXISTS (
               SELECT 1 FROM memberships ms
               WHERE ms.member_id = members.id
                 AND ms.school_year IN (SELECT school_year FROM active_school_years)
           )'
	)->fetchAll();

	$birthdays = [];

	foreach ($members as $member) {
		$born = DateTimeImmutable::createFromFormat('Y-m-d', (string) $member['date_of_birth']);
		if ($born === false) {
			continue;
		}

		// La fenêtre peut chevaucher deux années (fin décembre) : on teste les deux.
		foreach ([(int) $from->format('Y'), (int) $to->format('Y')] as $year) {
			$date = DateTimeImmutable::createFromFormat('Y-n-j', $year . '-' . $born->format('n-j'));
			if ($date === false) {
				continue;
			}
			$date = $date->setTime(0, 0);
			if ($date >= $from && $date <= $to) {
				$birthdays[$member['id']] = [
					'id' => (int) $member['id'],
					'first_name' => $member['first_name'],
					'last_name' => $member['last_name'],
					'date' => $date,
					'day_label' => newsletter_day_label($date),
					'age' => $year - (int) $born->format('Y'),
				];
			}
		}
	}

	usort(
		$birthdays,
		static fn (array $a, array $b): int => [$a['date'], $a['last_name']] <=> [$b['date'], $b['last_name']]
	);

	return $birthdays;
}

/**
 * Plan d'entraînement de la semaine : celui dont week_start est le lundi
 * demandé, sinon le plus récent publié avant (le coach n'a pas encore publié).
 *
 * @return array{training: array|null, is_current: bool}
 */
function get_newsletter_training(DateTimeImmutable $monday): array
{
	$stmt = app_pdo()->prepare(
		'SELECT * FROM trainings WHERE week_start <= :monday ORDER BY week_start DESC LIMIT 1'
	);
	$stmt->execute(['monday' => $monday->format('Y-m-d')]);
	$training = $stmt->fetch() ?: null;

	return [
		'training' => $training,
		'is_current' => $training !== null && $training['week_start'] === $monday->format('Y-m-d'),
	];
}

/**
 * Courses ajoutées entre deux dates et pas encore courues à la fin de la
 * période (écarte les rattrapages de courses passées insérés en base).
 */
function get_races_added_between(DateTimeImmutable $from, DateTimeImmutable $to): array
{
	$stmt = app_pdo()->prepare(
		'SELECT r.*, ' . race_author_columns_sql() . '
         FROM races r
         LEFT JOIN members m ON m.id = r.created_by
         WHERE r.created_at >= :from AND r.created_at < :to
           AND r.start_date >= :today
         ORDER BY r.start_date ASC'
	);
	$stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'today' => $to->format('Y-m-d')]);

	return $stmt->fetchAll();
}

/**
 * Courses dont le début tombe entre deux dates (bornes incluses).
 */
function get_races_starting_between(DateTimeImmutable $from, DateTimeImmutable $to): array
{
	$stmt = app_pdo()->prepare(
		'SELECT * FROM races WHERE start_date BETWEEN :from AND :to ORDER BY start_date ASC, title ASC'
	);
	$stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

	return $stmt->fetchAll();
}

/**
 * Événements publiés non terminés à la date donnée.
 */
function get_newsletter_events(DateTimeImmutable $from, int $limit = 3): array
{
	$stmt = app_pdo()->prepare(
		'SELECT * FROM events
         WHERE published = 1 AND COALESCE(end_date, start_date) >= :from
         ORDER BY start_date ASC LIMIT ' . (int) $limit
	);
	$stmt->execute(['from' => $from->format('Y-m-d')]);

	return $stmt->fetchAll();
}

/**
 * Jours de la semaine en français (lundi = 1).
 */
function newsletter_day_label(DateTimeInterface $date): string
{
	$days = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

	return $days[(int) $date->format('N')] . ' ' . $date->format('j/m');
}

/**
 * Rassemble toutes les données de la newsletter pour le lundi donné.
 *
 * @param array $excluded Éléments décochés (clés « race:12 », « birthday:5 », « event:3 », « news:2 »)
 * @param bool  $preview  Aperçu admin : les éléments exclus restent présents (avec leur case à cocher)
 *
 * @return array Variables du gabarit Twig
 */
function build_newsletter_data(DateTimeImmutable $monday, array $excluded = [], bool $preview = false): array
{
	$trainingInfo = get_newsletter_training($monday);
	// Du lundi au dimanche, comme les courses de la semaine.
	$birthdays = get_birthdays_between($monday, $monday->modify('+6 days'));
	$keep = static fn (string $type) => static fn (array $item): bool => $preview
		|| !in_array($type . ':' . $item['id'], $excluded, true);
	$birthdays = array_values(array_filter($birthdays, $keep('birthday')));
	$birthdaysByDay = [];
	foreach ($birthdays as $birthday) {
		$birthdaysByDay[$birthday['date']->format('Y-m-d')]['label'] = ucfirst($birthday['day_label']);
		$birthdaysByDay[$birthday['date']->format('Y-m-d')]['date'] = $birthday['date']->format('Y-m-d');
		$birthdaysByDay[$birthday['date']->format('Y-m-d')]['members'][] = $birthday;
	}
	$addedRaces = get_races_added_between($monday->modify('-7 days'), $monday);
	$addedRaces = array_values(array_filter($addedRaces, $keep('race')));
	$addedRaces = array_map(static fn (array $race): array => $race + ['location_label' => newsletter_place_case((string) $race['location'])], $addedRaces);

	// Une course déjà listée dans « ajoutées » n'est pas répétée dans les courses de la semaine.
	$addedIds = array_column($addedRaces, 'id');
	$upcomingAll = array_values(array_filter(
		get_races_starting_between($monday, $monday->modify('+6 days')),
		static fn (array $race): bool => !in_array($race['id'], $addedIds, true)
	));
	$upcomingRaces = array_values(array_filter($upcomingAll, $keep('race')));
	$upcomingRaces = array_map(static fn (array $race): array => $race + ['location_label' => newsletter_place_case((string) $race['location'])], $upcomingRaces);

	return [
		'monday' => $monday,
		'training' => $trainingInfo['training'],
		'training_is_current' => $trainingInfo['is_current'],
		'added_races' => $addedRaces,
		'upcoming_races' => $upcomingRaces,
		// Courses de la semaine toutes décochées : on masque le bloc au lieu du message « pas de course ».
		'upcoming_hidden' => $upcomingAll !== [] && $upcomingRaces === [],
		'preview' => $preview,
		'birthdays' => $birthdays,
		'birthdays_by_day' => array_values($birthdaysByDay),
		'events' => array_values(array_filter(get_newsletter_events($monday), $keep('event'))),
		'news' => array_values(array_filter(get_published_news(3), $keep('news'))),
		'site_url' => base_url('/'),
		'logo_url' => base_url('/logo-blanc.png'),
	];
}

/**
 * Rend le HTML de la newsletter (styles en ligne, mise en page en tableaux,
 * pour la compatibilité avec les clients mail).
 */
function render_newsletter(DateTimeImmutable $monday, array $excluded = [], bool $preview = false): string
{
	return twig_instance()->render('emails/newsletter.twig', build_newsletter_data($monday, $excluded, $preview));
}

/**
 * Objet de l'email : « Au menu de la semaine du 12 octobre ».
 */
function newsletter_subject(DateTimeImmutable $monday): string
{
	$months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
	$day = (int) $monday->format('j');

	return 'Au menu de la semaine du ' . ($day === 1 ? '1er' : $day) . ' ' . $months[(int) $monday->format('n')];
}

/**
 * Enregistre un envoi de la newsletter (historique, à titre d'indication).
 */
function log_newsletter_send(DateTimeImmutable $monday, string $recipient, int $memberId): void
{
	app_pdo()->prepare(
		'INSERT INTO newsletter_sends (week_start, recipient, sent_by) VALUES (:week, :recipient, :member)'
	)->execute(['week' => $monday->format('Y-m-d'), 'recipient' => $recipient, 'member' => $memberId > 0 ? $memberId : null]);
}

/**
 * Dernier envoi enregistré pour la semaine donnée.
 *
 * @return array|null Ligne [recipient, sent_at], ou null si jamais envoyée
 */
function get_last_newsletter_send(DateTimeImmutable $monday): ?array
{
	$stmt = app_pdo()->prepare(
		'SELECT recipient, sent_at FROM newsletter_sends WHERE week_start = :week ORDER BY sent_at DESC, id DESC LIMIT 1'
	);
	$stmt->execute(['week' => $monday->format('Y-m-d')]);

	return $stmt->fetch() ?: null;
}

/**
 * Met un nom de lieu en « casse titre » (« LE GRAND RECOIN » → « Le Grand Recoin »),
 * même s'il a été saisi en majuscules. Les petits mots de liaison (de, du, sur,
 * d', l'…) restent en minuscules, sauf en tête.
 */
function newsletter_place_case(string $place): string
{
	$small = ['de', 'du', 'des', 'd', 'l', 'sur', 'sous', 'en', 'et', 'au', 'aux', 'à', 'lès', 'lez'];
	$isFirst = true;

	return (string) preg_replace_callback(
		'/\p{L}+/u',
		static function (array $match) use ($small, &$isFirst): string {
			$word = $match[0];
			$first = $isFirst;
			$isFirst = false;

			if (!$first && in_array($word, $small, true)) {
				return $word;
			}

			return mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
		},
		mb_strtolower($place)
	);
}
