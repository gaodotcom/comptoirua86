<?php

declare(strict_types=1);

/**
 * Génération de la newsletter hebdomadaire (administration) : aperçu du
 * lundi choisi et envoi de test à une adresse (MailHog en développement).
 */

$monday = newsletter_monday((string) ($_POST['date'] ?? $_GET['date'] ?? ''));
$mondayIso = $monday->format('Y-m-d');
// Destinataires de test autorisés : l'admin connecté et les adresses ci-dessous.
// Adresses de test, à compléter ici après les tests (une par ligne).
$extraTestRecipients = [
	'contact@ultramical86.net',
	'bureau-ultramical86@googlegroups.com',
];
$testRecipients = array_values(array_unique(array_filter([
	(string) ($user['email'] ?? ''),
	...$extraTestRecipients,
])));
$testEmail = (string) ($_POST['email'] ?? ($testRecipients[0] ?? ''));

// Éléments décochés dans l'aperçu (clés « race:12 », « birthday:5 »…).
$excluded = array_values(array_filter((array) ($_POST['excluded'] ?? []), 'is_string'));

// Aperçu avec cases à cocher ; l'email envoyé est rendu sans case et sans les éléments exclus.
$html = render_newsletter($monday, [], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_valid_csrf('newsletter', ['date' => $mondayIso]);

	// TEMPORAIRE (dev) — à supprimer avant le déploiement, avec le bouton du gabarit.
	// Envoi en un clic vers MailHog ; refusé dès que le SMTP de production est configuré.
	/*
	if (($_POST['action'] ?? '') === 'test_mailhog') {
		if (smtp_enabled()) {
			set_flash('danger', 'Bouton MailHog désactivé : le SMTP de production est configuré.');
		} elseif (send_email('mailhog@adherents.local', newsletter_subject($monday), $html)) {
			set_flash('success', 'Envoyé dans MailHog (http://localhost:8082).');
		} else {
			set_flash('danger', 'Échec de l\'envoi vers MailHog.');
		}
		redirect_to('newsletter', ['date' => $mondayIso]);
	}
	*/

	if (!in_array($testEmail, $testRecipients, true)) {
		set_flash('danger', 'Destinataire non autorisé.');
	} elseif (send_email($testEmail, newsletter_subject($monday), render_newsletter($monday, $excluded), [], mail_newsletter_address())) {
		log_newsletter_send($monday, $testEmail, (int) ($user['id'] ?? 0));
		set_flash('success', 'Newsletter envoyée à ' . $testEmail . '.');
	} else {
		set_flash('danger', 'Échec de l\'envoi.');
	}
	redirect_to('newsletter', ['date' => $mondayIso]);
}

twig_render('pages/newsletter/newsletter.twig', [
	'title' => 'Newsletter',
	'mondayIso' => $mondayIso,
	'testEmail' => $testEmail,
	'testRecipients' => $testRecipients,
	'ownEmail' => (string) ($user['email'] ?? ''),
	'lastSend' => get_last_newsletter_send($monday),
	'html' => $html,
	'training' => get_newsletter_training($monday),
]);
