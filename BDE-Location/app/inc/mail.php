<?php
declare(strict_types=1);

/**
 * Envoi d'e-mails. Deux modes :
 *  - SMTP (recommandé) si un serveur est renseigné dans Réglages : marche partout, y compris en local ;
 *  - sinon la fonction mail() de PHP, qui exige un serveur de mail installé sur la machine (rare en local).
 * Rien n'est envoyé tant que l'adresse d'expédition est vide. En cas d'échec le site continue normalement
 * et mail_last_error() donne la raison.
 */
function mail_last_error(?string $set = null): string
{
    static $err = '';
    if ($set !== null) {
        $err = $set;
    }
    return $err;
}

function send_mail(string $to, string $subject, string $body): bool
{
    mail_last_error('');
    $from = setting('mail_from');
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Aucune adresse d\'expédition valide n\'est renseignée.');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_last_error('Adresse du destinataire invalide.');
        return false;
    }
    $siteName = clean_line(setting('site_name', 'Location BDE'), 60);
    $subject = clean_line($subject, 150);
    try {
        if (setting('smtp_host') !== '') {
            return smtp_send($to, $from, $siteName, $subject, $body);
        }
        $headers = [
            'From: =?UTF-8?B?' . base64_encode($siteName) . '?= <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$ok) {
            $last = error_get_last();
            mail_last_error('La fonction mail() de PHP a échoué' . ($last ? ' (' . $last['message'] . ')' : '')
                . '. Ce serveur n\'a probablement pas de service d\'envoi : renseigne un serveur SMTP dans Réglages.');
        }
        return $ok;
    } catch (Throwable $e) {
        error_log('send_mail: ' . $e->getMessage());
        mail_last_error($e->getMessage());
        return false;
    }
}

/** Lit une réponse SMTP (éventuellement sur plusieurs lignes) et vérifie son code. */
function smtp_expect($fp, array $okCodes): string
{
    $reply = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $reply .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    if ($reply === '') {
        throw new RuntimeException('Le serveur SMTP ne répond pas (délai dépassé).');
    }
    if (!in_array((int)substr($reply, 0, 3), $okCodes, true)) {
        throw new RuntimeException('Réponse du serveur SMTP : ' . trim($reply));
    }
    return $reply;
}

function smtp_cmd($fp, string $cmd, array $okCodes): string
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $okCodes);
}

function smtp_send(string $to, string $from, string $fromName, string $subject, string $body): bool
{
    $host = setting('smtp_host');
    $port = (int)setting('smtp_port', '587') ?: 587;
    $secure = setting('smtp_secure', 'tls');          // tls (STARTTLS, port 587), ssl (port 465) ou none
    $user = setting('smtp_user');
    $pass = setting('smtp_pass');
    $fp = null;
    try {
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            throw new RuntimeException('Connexion impossible à ' . $host . ':' . $port . ' (' . ($errstr ?: 'erreur ' . $errno) . ').'
                . ($secure === 'ssl' ? ' Vérifie que l\'extension PHP openssl est activée.' : ''));
        }
        stream_set_timeout($fp, 15);
        smtp_expect($fp, [220]);
        $me = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost';
        smtp_cmd($fp, 'EHLO ' . $me, [250]);
        if ($secure === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Connexion sécurisée (TLS) impossible. Vérifie que l\'extension PHP openssl est activée (et que php.ini connaît les certificats : openssl.cafile).');
            }
            smtp_cmd($fp, 'EHLO ' . $me, [250]);
        }
        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', [334]);
            smtp_cmd($fp, base64_encode($user), [334]);
            try {
                smtp_cmd($fp, base64_encode($pass), [235]);
            } catch (RuntimeException $e) {
                throw new RuntimeException('Identifiant ou mot de passe SMTP refusé. ' . $e->getMessage()
                    . ' (Avec Gmail, il faut un « mot de passe d\'application ».)');
            }
        }
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $data = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode(str_replace("\r\n", "\n", $body)), 76, "\r\n");
        fwrite($fp, $data . ".\r\n");
        smtp_expect($fp, [250]);
        @smtp_cmd($fp, 'QUIT', [221]);
        return true;
    } catch (Throwable $e) {
        error_log('smtp_send: ' . $e->getMessage());
        mail_last_error($e->getMessage());
        return false;
    } finally {
        if (is_resource($fp)) {
            fclose($fp);
        }
    }
}

function notify_new_reservation(array $r): void
{
    $link = absolute_url('reservation.php?ref=' . urlencode($r['ref']));
    $period = date_fr($r['start_date'], true) . ' au ' . date_fr($r['end_date'], true) . ' (' . plural((int)$r['days'], 'jour') . ')';

    $wants = true;
    if (!empty($r['user_id'])) {
        $st = db()->prepare('SELECT notify FROM users WHERE id = ?');
        $st->execute([(int)$r['user_id']]);
        $v = $st->fetchColumn();
        $wants = $v === false || (int)$v !== 0;
    }
    if ($wants) send_mail(
        $r['email'],
        'Ta demande de location (réf. ' . $r['ref'] . ')',
        "Salut " . $r['borrower'] . ",\n\n"
        . "Ta demande est bien enregistrée :\n"
        . $r['item_name'] . ($r['qty'] > 1 ? ' x' . $r['qty'] : '') . "\n"
        . $period . "\n"
        . "Location : " . money($r['total']) . " / Caution : " . money($r['caution']) . "\n\n"
        . "Connecte-toi pour suivre ta demande et retrouver les liens de paiement :\n" . $link . "\n\n"
        . "À bientôt !\n" . setting('site_name', 'Location BDE') . "\n"
    );

    $to = setting('notify_email');
    if ($to !== '') {
        send_mail(
            $to,
            'Nouvelle demande : ' . $r['item_name'] . ' (' . $r['ref'] . ')',
            $r['borrower'] . " demande : " . $r['item_name'] . ($r['qty'] > 1 ? ' x' . $r['qty'] : '') . "\n"
            . $period . "\n"
            . "Contact : " . $r['email'] . ($r['phone'] !== '' ? ' / ' . $r['phone'] : '') . "\n\n"
            . "À traiter ici : " . absolute_url('admin/index.php') . "\n"
        );
    }
}
