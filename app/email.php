<?php

declare(strict_types=1);

/**
 * Email — envoi des emails.
 *
 * En production (Ionos mutualisé) : SMTP authentifié (smtp.ionos.fr:465 SSL).
 * Supporte aussi STARTTLS (port 587) si SMTP_SECURE=tls dans le .env.
 * En développement : fonction native PHP mail(), routée par php.ini vers
 * msmtp puis MailHog. Le mode SMTP est activé uniquement si SMTP_HOST et
 * SMTP_USER sont renseignés dans le .env (vides en dev).
 */

/**
 * Renvoie l'adresse d'expédition configurée, avec repli sur mail_contact.
 *
 * @return string
 */
function mail_from_address(): string
{
    $config = app_config();

    return $config['mail_noreply'] ?: ($config['mail_contact'] ?: 'noreply@adherents.local');
}

/**
 * Construit la chaîne d'en-têtes email standard.
 *
 * @param string $from    Adresse d'expédition
 * @param string $appName Nom de l'application
 * @param array  $extra   En-têtes additionnels
 *
 * @return string
 */
function build_email_headers(string $from, string $appName, array $extra = []): string
{
    $headers = [
        'From' => sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($appName), $from),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];

    foreach ($extra as $key => $value) {
        if (strtolower((string) $key) !== 'from') {
            $headers[$key] = $value;
        }
    }

    $str = '';
    foreach ($headers as $key => $value) {
        $str .= "$key: $value\r\n";
    }

    return $str;
}

/**
 * Renvoie true si le mode SMTP authentifié est activé (SMTP_HOST et SMTP_USER renseignés).
 *
 * @return bool
 */
function smtp_enabled(): bool
{
    $smtp = app_config()['smtp'] ?? [];

    return !empty($smtp['host']) && !empty($smtp['user']);
}

/**
 * Envoie un email.
 *
 * En production (SMTP_HOST et SMTP_USER définis) : SMTP authentifié vers Ionos.
 * En développement : fonction native mail() (routée vers MailHog).
 *
 * @param string $to      Adresse destinataire
 * @param string $subject Sujet du message
 * @param string $body    Corps du message (HTML)
 * @param array  $headers Headers additionnels (optionnel)
 *
 * @return bool true si accepté, false sinon
 */
function send_email(string $to, string $subject, string $body, array $headers = []): bool
{
    $config = app_config();
    $from = mail_from_address();
    $appName = $config['app_name'] ?? 'Adherents';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headerStr = build_email_headers($from, $appName, $headers);

    if (smtp_enabled()) {
        error_log("Email via SMTP: To: $to | Subject: $subject | From: $from");
        $result = send_email_smtp_auth($to, $from, $appName, $encodedSubject, $body, $headerStr);
    } else {
        error_log("Email via mail(): To: $to | Subject: $subject | From: $from");
        $result = @mail($to, $encodedSubject, $body, $headerStr);
    }

    if (!$result) {
        error_log("Email FAILED to $to");
    } else {
        error_log("Email accepted for $to");
    }

    return $result;
}

/**
 * Envoie un email via SMTP authentifié (AUTH LOGIN, SSL/TLS).
 *
 * @param string $to            Destinataire
 * @param string $from          Expéditeur
 * @param string $appName       Nom de l'app (pour EHLO)
 * @param string $encodedSubject Sujet déjà encodé MIME
 * @param string $body          Corps HTML
 * @param string $headerStr     En-têtes formatés
 *
 * @return bool
 */
function send_email_smtp_auth(string $to, string $from, string $appName, string $encodedSubject, string $body, string $headerStr): bool
{
    $smtp = app_config()['smtp'];
    $secure = $smtp['secure'] ?? 'ssl';
    $host = $smtp['host'];
    $port = $smtp['port'];
    $user = $smtp['user'];
    $pass = $smtp['pass'];

    // Pour SSL, on se connecte en ssl://host ; pour TLS (587), connexion en clair puis STARTTLS.
    $remote = ($secure === 'ssl') ? 'ssl://' . $host : $host;

    $socket = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP: connexion échouée $remote:$port - $errstr ($errno)");
        return false;
    }

    $ehloHost = parse_url(app_config()['base_url'] ?? '', PHP_URL_HOST) ?: 'localhost';

    $ok = smtp_expect($socket, '220');
    $ok = $ok && smtp_cmd($socket, 'EHLO ' . $ehloHost, '250');

    if ($secure === 'tls') {
        $ok = $ok && smtp_starttls($socket, $ehloHost);
    }

    $ok = $ok && smtp_auth_login($socket, $user, $pass);
    $ok = $ok && smtp_cmd($socket, "MAIL FROM:<$from>", '250');
    $ok = $ok && smtp_cmd($socket, "RCPT TO:<$to>", '250');
    $ok = $ok && smtp_cmd($socket, 'DATA', '354');

    if ($ok) {
        // Assemblage du message : en-têtes, puis To/Subject, ligne vide, corps, puis "." final.
        $msg = $headerStr;
        $msg .= "To: $to\r\n";
        $msg .= "Subject: $encodedSubject\r\n";
        $msg .= "\r\n";
        $msg .= $body;
        $msg .= "\r\n.\r\n";
        fputs($socket, $msg);
        $ok = smtp_expect($socket, '250');
    }

    @fputs($socket, "QUIT\r\n");
    fclose($socket);

    return $ok;
}

/**
 * Négocie STARTTLS sur une socket SMTP puis renvoie le EHLO.
 * Restreint à TLS 1.2 / 1.3 (exclut les versions obsolètes).
 *
 * @param resource $socket   Socket SMTP
 * @param string   $ehloHost Nom d'hôte pour EHLO
 *
 * @return bool
 */
function smtp_starttls($socket, string $ehloHost): bool
{
    if (!smtp_cmd($socket, 'STARTTLS', '220')) {
        return false;
    }

    // TLS 1.2 et 1.3 uniquement (exclut TLS 1.0 / 1.1 vulnérables).
    $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
    if (!stream_socket_enable_crypto($socket, true, $crypto)) {
        error_log('SMTP: échec STARTTLS');
        return false;
    }

    return smtp_cmd($socket, 'EHLO ' . $ehloHost, '250');
}

/**
 * Envoie la séquence AUTH LOGIN (utilisateur puis mot de passe en base64).
 *
 * @param resource $socket Socket SMTP
 * @param string   $user   Nom d'utilisateur SMTP
 * @param string   $pass   Mot de passe SMTP
 *
 * @return bool
 */
function smtp_auth_login($socket, string $user, string $pass): bool
{
    if (!smtp_cmd($socket, 'AUTH LOGIN', '334')) {
        return false;
    }

    if (!smtp_cmd($socket, base64_encode($user), '334')) {
        return false;
    }

    return smtp_cmd($socket, base64_encode($pass), '235');
}

/**
 * Lit la réponse SMTP et vérifie le code attendu.
 *
 * @param resource $socket      Socket SMTP
 * @param string   $expectedCode Code attendu (ex: '250')
 *
 * @return bool
 */
function smtp_expect($socket, string $expectedCode): bool
{
    $response = '';
    // Une réponse SMTP peut tenir sur plusieurs lignes ; la dernière ligne du bloc
    // a un espace en 4e position (les lignes intermédiaires ont un '-' à cet endroit).
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    if (strpos($response, $expectedCode) !== 0) {
        error_log("SMTP: attendu $expectedCode, reçu: " . trim($response));
        return false;
    }

    return true;
}

/**
 * Envoie une commande SMTP et vérifie le code de réponse.
 *
 * @param resource $socket
 * @param string   $cmd
 * @param string   $expectedCode
 *
 * @return bool
 */
function smtp_cmd($socket, string $cmd, string $expectedCode): bool
{
    fputs($socket, $cmd . "\r\n");

    return smtp_expect($socket, $expectedCode);
}

/**
 * Envoie un email de réinitialisation de mot de passe.
 *
 * @param string $email    Email du destinataire
 * @param string $name     Nom du destinataire
 * @param string $resetUrl URL complète du lien de réinitialisation
 *
 * @return bool
 */
function send_password_reset_email(string $email, string $name, string $resetUrl): bool
{
    $subject = 'Réinitialisation de votre mot de passe';

    $escapedUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $escapedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Réinitialisation de votre mot de passe</h2>
        
        <p>Bonjour $escapedName,</p>
        
        <p>Vous avez demandé à réinitialiser votre mot de passe pour Le Comptoir des Adhérents.</p>
        
        <p>Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe:</p>
        
        <a href="$escapedUrl" class="button">Réinitialiser mon mot de passe</a>
        
        <p style="color: #666; font-size: 12px;">Ou copiez-collez ce lien:<br>
        <a href="$escapedUrl">$escapedUrl</a></p>
        
        <p style="color: #999; font-size: 12px;"><strong>Ce lien expire dans 30 minutes.</strong></p>
        
        <p style="margin-top: 30px; font-size: 12px;">Si vous n'avez pas demandé cette réinitialisation, ignorez ce message.</p>
        
        <div class="footer">
            <p>Cordialement,<br>L'équipe Ultramical86</p>
        </div>
    </div>
</body>
</html>
EOT;

    return send_email($email, $subject, $body);
}

/**
 * Envoie un email de confirmation (ou de mise à jour) de préinscription au
 * week-end club Lozère Trail 2027.
 *
 * @param string $email       Email du destinataire
 * @param string $firstName   Prénom du destinataire
 * @param array  $summaryLines Récapitulatif de la préinscription, liste de
 *                             ['label' => string, 'value' => string]
 * @param bool   $isUpdate    true si c'est une mise à jour d'une préinscription existante
 * @param string $formUrl     URL complète du formulaire (pour modifier/consulter)
 *
 * @return bool
 */
function send_weekend_2027_confirmation_email(
    string $email,
    string $firstName,
    array $summaryLines,
    bool $isUpdate,
    string $formUrl
): bool {
    $subject = $isUpdate
        ? '[WE club UA86- Lozère Trail] Mise à jour de ta préinscription'
        : '[WE club UA86- Lozère Trail] Confirmation de ta préinscription';
    // Titre affiché dans le corps du mail : distinct de $subject pour ne pas y
    // répéter le préfixe "[WE club UA86- Lozère Trail]" (qui n'a de sens que
    // dans la liste des emails, pas au fil de la lecture du message).
    $heading = $isUpdate
        ? 'Mise à jour de ta préinscription — Week-end club Lozère Trail'
        : 'Confirmation de ta préinscription — Week-end club Lozère Trail';

    $escapedName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8');
    $introText = $isUpdate
        ? 'Ta préinscription au week-end club Ultramical86 au Lozère Trail a bien été mise à jour. En voici le récapitulatif :'
        : 'Ta préinscription au week-end club Ultramical86 au Lozère Trail (15-16 mai 2027) a bien été enregistrée. En voici le récapitulatif :';

    $rows = '';
    foreach ($summaryLines as $line) {
        $label = htmlspecialchars((string) $line['label'], ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string) $line['value'], ENT_QUOTES, 'UTF-8');
        $rows .= "<tr><td style=\"padding:4px 12px 4px 0;color:#666;white-space:nowrap;\">$label</td><td style=\"padding:4px 0;font-weight:600;\">$value</td></tr>\n";
    }

    $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="format-detection" content="telephone=no">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        table { border-collapse: collapse; margin: 16px 0; }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>{$heading}</h2>

        <p>Bonjour $escapedName,</p>

        <p>$introText</p>

        <table>
            $rows
        </table>

        <p>La distance choisie est définitive une fois envoyée à l'organisateur : nous ne pourrons pas gérer les changements après cet envoi. Tu peux modifier ta préinscription tant qu'elle n'est pas encore transmise.</p>

        <a href="$escapedUrl" class="button" style="color:#ffffff !important;">Voir / modifier ma préinscription</a>

        <div class="footer">
            <p>Cordialement,<br>L'équipe Ultramical86</p>
        </div>
    </div>
</body>
</html>
EOT;

    return send_email($email, $subject, $body);
}

/**
 * Envoie une confirmation d'annulation de préinscription au week-end club
 * Lozère Trail 2027, à l'adhérent qui vient de supprimer la sienne.
 *
 * @param string $email     Email du destinataire
 * @param string $firstName Prénom du destinataire
 * @param string $formUrl   URL complète du formulaire (pour se réinscrire si besoin)
 *
 * @return bool
 */
function send_weekend_2027_deletion_email(string $email, string $firstName, string $formUrl): bool
{
    $subject = '[WE club UA86- Lozère Trail] Annulation de ta préinscription';
    // Titre affiché dans le corps du mail : distinct de $subject, cf. remarque
    // équivalente dans send_weekend_2027_confirmation_email().
    $heading = 'Annulation de ta préinscription — Week-end club Lozère Trail';

    $escapedName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8');

    $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>{$heading}</h2>

        <p>Bonjour $escapedName,</p>

        <p>Ta préinscription au week-end club Ultramical86 au Lozère Trail a bien été supprimée. Toutes les informations que tu avais renseignées ont été effacées.</p>

        <p>Si c'est une erreur, ou si tu changes d'avis, tu peux te réinscrire à tout moment tant que les préinscriptions ne sont pas closes.</p>

        <a href="$escapedUrl" class="button" style="color:#ffffff !important;">Me réinscrire</a>

        <div class="footer">
            <p>Cordialement,<br>L'équipe Ultramical86</p>
        </div>
    </div>
</body>
</html>
EOT;

    return send_email($email, $subject, $body);
}

/**
 * Envoie un email de notification au coéquipier·e de duo, quand l'autre
 * membre du duo crée ou modifie sa préinscription (le choix de course lui-même
 * étant partagé entre les deux, cf. get_weekend_2027_duo_leader_for_member()).
 *
 * @param string $email            Email du destinataire (le/la coéquipier·e)
 * @param string $recipientFirstName Prénom du destinataire
 * @param string $teammateFirstName  Prénom de l'autre membre du duo (qui vient d'enregistrer)
 * @param string $teammateLastName   Nom de l'autre membre du duo
 * @param array  $summaryLines      Récapitulatif partagé du duo (course, bivouac...), liste de
 *                                  ['label' => string, 'value' => string]
 * @param bool   $isUpdate         true si l'autre membre a modifié une préinscription déjà existante
 * @param string $formUrl          URL complète du formulaire (pour consulter/compléter la sienne)
 *
 * @return bool
 */
function send_weekend_2027_duo_partner_notification_email(
    string $email,
    string $recipientFirstName,
    string $teammateFirstName,
    string $teammateLastName,
    array $summaryLines,
    bool $isUpdate,
    string $formUrl
): bool {
    $teammateFullName = trim($teammateFirstName . ' ' . $teammateLastName);
    $subject = $isUpdate
        ? "[WE club UA86- Lozère Trail] Ton·ta coéquipier·e de duo a mis à jour sa préinscription"
        : "[WE club UA86- Lozère Trail] Tu as été inscrit·e en duo par $teammateFullName";

    $escapedRecipientName = htmlspecialchars($recipientFirstName, ENT_QUOTES, 'UTF-8');
    $escapedTeammateName = htmlspecialchars($teammateFullName, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8');
    $introText = $isUpdate
        ? "Ton·ta coéquipier·e de duo <strong>$escapedTeammateName</strong> a mis à jour sa préinscription pour le week-end club Ultramical86 au Lozère Trail. Voici le récapitulatif de ton duo :"
        : "<strong>$escapedTeammateName</strong> t'a désigné·e comme coéquipier·e pour l'Ultra Lozère en duo, dans le cadre du week-end club Ultramical86 au Lozère Trail (15-16 mai 2027). Voici le récapitulatif de ton duo :";

    $rows = '';
    foreach ($summaryLines as $line) {
        $label = htmlspecialchars((string) $line['label'], ENT_QUOTES, 'UTF-8');
        $value = htmlspecialchars((string) $line['value'], ENT_QUOTES, 'UTF-8');
        $rows .= "<tr><td style=\"padding:4px 12px 4px 0;color:#666;white-space:nowrap;\">$label</td><td style=\"padding:4px 0;font-weight:600;\">$value</td></tr>\n";
    }

    $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        table { border-collapse: collapse; margin: 16px 0; }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Préinscription en duo — Week-end club Lozère Trail</h2>

        <p>Bonjour $escapedRecipientName,</p>

        <p>$introText</p>

        <table>
            $rows
        </table>

        <p>Ce choix de course a été fait par $escapedTeammateName et n'est pas modifiable de ton côté ; pense en revanche à compléter tes propres informations (taille de maillot, hébergement, contact d'urgence...).</p>

        <a href="$escapedUrl" class="button" style="color:#ffffff !important;">Voir / compléter ma préinscription</a>

        <div class="footer">
            <p>Cordialement,<br>L'équipe Ultramical86</p>
        </div>
    </div>
</body>
</html>
EOT;

    return send_email($email, $subject, $body);
}

/**
 * Envoie un email au/à la coéquipier·e de duo quand l'autre membre du duo
 * annule sa préinscription au week-end club Lozère Trail 2027.
 *
 * @param string $email               Email du destinataire (le/la coéquipier·e restant·e)
 * @param string $recipientFirstName  Prénom du destinataire
 * @param string $cancellerFirstName  Prénom de l'adhérent qui a annulé sa préinscription
 * @param string $cancellerLastName   Nom de l'adhérent qui a annulé sa préinscription
 * @param bool   $courseWasCleared    true si le choix de course du destinataire a été remis à
 *                                    zéro (il/elle s'était inscrit·e après, son choix en dépendait)
 * @param string $formUrl             URL complète du formulaire
 *
 * @return bool
 */
function send_weekend_2027_duo_cancellation_notification_email(
    string $email,
    string $recipientFirstName,
    string $cancellerFirstName,
    string $cancellerLastName,
    bool $courseWasCleared,
    string $formUrl
): bool {
    $cancellerFullName = trim($cancellerFirstName . ' ' . $cancellerLastName);
    $subject = "[WE club UA86- Lozère Trail] $cancellerFullName a annulé sa préinscription";

    $escapedRecipientName = htmlspecialchars($recipientFirstName, ENT_QUOTES, 'UTF-8');
    $escapedCancellerName = htmlspecialchars($cancellerFullName, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8');

    $mainText = $courseWasCleared
        ? "Ton·ta coéquipier·e de duo <strong>$escapedCancellerName</strong> vient d'annuler sa préinscription au week-end club Ultramical86 au Lozère Trail. Comme ton choix de course (Ultra Lozère en duo) dépendait du sien, il a été retiré de ta préinscription — tes autres informations (taille de maillot, hébergement, contact d'urgence...) restent enregistrées."
        : "Ton·ta coéquipier·e de duo <strong>$escapedCancellerName</strong> vient d'annuler sa préinscription au week-end club Ultramical86 au Lozère Trail.";

    $actionText = $courseWasCleared
        ? 'Merci de repasser sur le formulaire pour refaire ton choix de course dès que possible.'
        : "Ton choix de course reste enregistré tel quel pour l'instant. Pense à le mettre à jour si besoin (rester en solo, choisir un·e nouveau/nouvelle coéquipier·e...).";

    $body = <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Annulation de préinscription — Week-end club Lozère Trail</h2>

        <p>Bonjour $escapedRecipientName,</p>

        <p>$mainText</p>

        <p>$actionText</p>

        <a href="$escapedUrl" class="button" style="color:#ffffff !important;">Voir / modifier ma préinscription</a>

        <div class="footer">
            <p>Cordialement,<br>L'équipe Ultramical86</p>
        </div>
    </div>
</body>
</html>
EOT;

    return send_email($email, $subject, $body);
}
