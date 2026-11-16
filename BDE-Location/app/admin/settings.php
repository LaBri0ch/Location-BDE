<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require_installed();
require_admin();

$errors = [];

if (is_post()) {
    csrf_verify();

    if (post('action') === 'test_mail') {
        $to = setting('notify_email') ?: setting('mail_from');
        if ($to === '') {
            flash('bad', 'Renseigne d\'abord une adresse d\'expédition et enregistre.');
        } elseif (send_mail($to, 'Test du site de location', "Si tu lis ce message, l'envoi d'e-mails fonctionne.\n")) {
            flash('ok', 'E-mail de test envoyé à ' . $to . '. Vérifie aussi les spams.');
        } else {
            flash('bad', 'L\'envoi a échoué : ' . (mail_last_error() ?: 'raison inconnue') . ' (Enregistre tes réglages avant de tester.)');
        }
        redirect('admin/settings.php');
    }

    $siteName = clean_line(post('site_name'), 60);
    $maxDays = post('max_days');
    $ha = post('helloasso_url');
    $sw = post('swikly_url');
    $from = clean_line(post('mail_from'), 120);
    $notify = clean_line(post('notify_email'), 120);
    $smtpHost = clean_line(post('smtp_host'), 120);
    $smtpPort = post('smtp_port', '587');
    $smtpSecure = in_array(post('smtp_secure'), ['tls', 'ssl', 'none'], true) ? post('smtp_secure') : 'tls';
    $smtpUser = clean_line(post('smtp_user'), 160);
    $smtpPass = (string)($_POST['smtp_pass'] ?? '');
    $domainsIn = mb_strtolower(clean_line(post('email_domains'), 200));
    $domains = [];
    foreach (preg_split('/[\s,;]+/', $domainsIn, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $d) {
        $d = ltrim($d, '@');
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/', $d)) {
            $errors[] = 'Le domaine « ' . $d . ' » n\'est pas valide (exemple : ipsa.fr).';
        } else {
            $domains[] = $d;
        }
    }

    if ($siteName === '') {
        $errors[] = 'Le nom du site ne peut pas être vide.';
    }
    if (!ctype_digit($maxDays) || (int)$maxDays < 1 || (int)$maxDays > 365) {
        $errors[] = 'La durée maximale doit être comprise entre 1 et 365 jours.';
    }
    if ($ha !== '' && safe_url($ha) === '') {
        $errors[] = 'Le lien HelloAsso doit commencer par https://';
    }
    if ($sw !== '' && safe_url($sw) === '') {
        $errors[] = 'Le lien Swikly doit commencer par https://';
    }
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse d\'expédition n\'est pas valide.';
    }
    if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse de notification n\'est pas valide.';
    }

    if ($smtpHost !== '' && !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $smtpHost)) {
        $errors[] = 'Le serveur SMTP n\'est pas valide (exemple : smtp.gmail.com).';
    }
    if (!ctype_digit($smtpPort) || (int)$smtpPort < 1 || (int)$smtpPort > 65535) {
        $errors[] = 'Le port SMTP doit être un nombre (587 ou 465 en général).';
    }

    if (!$errors) {
        save_setting('site_name', $siteName);
        save_setting('max_days', (string)(int)$maxDays);
        save_setting('helloasso_url', $ha === '' ? '' : safe_url($ha));
        save_setting('swikly_url', $sw === '' ? '' : safe_url($sw));
        save_setting('mail_from', $from);
        save_setting('notify_email', $notify);
        save_setting('email_domains', $domains ? implode(', ', array_unique($domains)) : '*');
        save_setting('smtp_host', $smtpHost);
        save_setting('smtp_port', (string)(int)$smtpPort);
        save_setting('smtp_secure', $smtpSecure);
        save_setting('smtp_user', $smtpUser);
        if ($smtpPass !== '') {                       // champ vide = on garde le mot de passe actuel
            save_setting('smtp_pass', $smtpPass);
        }
        if (post('smtp_pass_clear') === '1') {
            save_setting('smtp_pass', '');
        }
        save_setting('pickup_info', mb_substr(trim((string)($_POST['pickup_info'] ?? '')), 0, 600));
        flash('ok', 'Réglages enregistrés.');
        redirect('admin/settings.php');
    }
}

$val = fn(string $k, string $d = '') => is_post() && $errors ? post($k) : setting($k, $d);

page_header('Réglages', ['admin' => true, 'active' => 'settings']);
?>
<div class="sec-head"><h1>Réglages</h1></div>

<?php foreach ($errors as $err): ?><p class="msg bad" role="alert"><?= e($err) ?></p><?php endforeach; ?>

<form method="post" class="form stack">
    <?= csrf_field() ?>

    <section class="panel">
        <h2>Le site</h2>
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="site_name">Nom du site</label>
                <input id="site_name" name="site_name" type="text" maxlength="60" value="<?= e($val('site_name', 'Location BDE')) ?>" required>
            </div>
            <div class="field">
                <label class="lbl" for="max_days">Durée maximale d'une location (jours)</label>
                <input id="max_days" name="max_days" type="text" inputmode="numeric" value="<?= e($val('max_days', '30')) ?>" required>
            </div>
            <div class="field span2">
                <label class="lbl" for="pickup_info">Récupération et retour (affiché à l'emprunteur)</label>
                <textarea id="pickup_info" name="pickup_info" maxlength="600" placeholder="Ex : permanence du BDE, local B12, du lundi au jeudi de 12h à 14h."><?= e($val('pickup_info')) ?></textarea>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Inscriptions</h2>
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="email_domains">Adresses e-mail autorisées à créer un compte</label>
                <input id="email_domains" name="email_domains" type="text" autocomplete="off" autocapitalize="none" placeholder="ipsa.fr"
                       value="<?= e(is_post() && $errors ? post('email_domains') : (setting('email_domains', 'ipsa.fr') === '*' ? '' : setting('email_domains', 'ipsa.fr'))) ?>">
                <span class="hint">Un ou plusieurs domaines séparés par une virgule. Les sous-domaines sont acceptés (ipsa.fr accepte aussi prenom@etu.ipsa.fr). Laisse vide pour accepter toutes les adresses. Les membres invités au bureau ne sont pas concernés.</span>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Paiement et caution</h2>
        <p class="muted small">Ces liens s'affichent à l'emprunteur après sa demande. Chaque objet peut avoir ses propres liens (onglet Matériel) : ils passent alors avant ceux-ci.</p>
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="helloasso_url">Lien HelloAsso général</label>
                <input id="helloasso_url" name="helloasso_url" type="url" inputmode="url" placeholder="https://www.helloasso.com/…" value="<?= e($val('helloasso_url')) ?>">
            </div>
            <div class="field span2">
                <label class="lbl" for="swikly_url">Lien Swikly général</label>
                <input id="swikly_url" name="swikly_url" type="url" inputmode="url" placeholder="https://…" value="<?= e($val('swikly_url')) ?>">
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>E-mails automatiques</h2>
        <p class="muted small">Facultatif. Tant que l'adresse d'expédition est vide, aucun e-mail n'est envoyé : les demandes restent visibles dans « Mes demandes ».
            Sans serveur SMTP, le site utilise la fonction <code>mail()</code> de PHP, qui ne marche que si la machine a un service d'envoi (rare en local, fréquent chez un hébergeur). Un serveur SMTP marche partout.</p>
        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="mail_from">Adresse d'expédition</label>
                <input id="mail_from" name="mail_from" type="email" placeholder="contact@ton-domaine.fr" value="<?= e($val('mail_from')) ?>">
            </div>
            <div class="field">
                <label class="lbl" for="notify_email">Prévenir le bureau à chaque demande</label>
                <input id="notify_email" name="notify_email" type="email" placeholder="bde@ton-domaine.fr" value="<?= e($val('notify_email')) ?>">
            </div>
        </div>
        <h3 class="h-small">Serveur SMTP (recommandé)</h3>
        <p class="muted small">Gmail : serveur <code>smtp.gmail.com</code>, port 587, sécurité STARTTLS, identifiant = ton adresse Gmail, mot de passe = un <b>mot de passe d'application</b> (Compte Google → Sécurité → validation en 2 étapes → mots de passe des applications). L'adresse d'expédition doit être la même que l'identifiant. Chez un hébergeur (OVH, o2switch…) : utilise les paramètres SMTP de ta boîte mail.</p>
        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="smtp_host">Serveur SMTP</label>
                <input id="smtp_host" name="smtp_host" type="text" autocomplete="off" autocapitalize="none" placeholder="smtp.gmail.com" value="<?= e($val('smtp_host')) ?>">
            </div>
            <div class="field">
                <label class="lbl" for="smtp_port">Port</label>
                <input id="smtp_port" name="smtp_port" type="text" inputmode="numeric" value="<?= e($val('smtp_port', '587')) ?>">
            </div>
            <div class="field">
                <label class="lbl" for="smtp_secure">Sécurité</label>
                <select id="smtp_secure" name="smtp_secure">
                    <?php $sec = $val('smtp_secure', 'tls'); foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Aucune (déconseillé)'] as $k => $l): ?>
                        <option value="<?= $k ?>"<?= $sec === $k ? ' selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="lbl" for="smtp_user">Identifiant</label>
                <input id="smtp_user" name="smtp_user" type="text" autocomplete="off" autocapitalize="none" value="<?= e($val('smtp_user')) ?>">
            </div>
            <div class="field span2">
                <label class="lbl" for="smtp_pass">Mot de passe SMTP</label>
                <input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password" placeholder="<?= setting('smtp_pass') !== '' ? '•••••••• (enregistré, laisse vide pour le garder)' : '' ?>">
                <?php if (setting('smtp_pass') !== ''): ?>
                    <label class="check"><input type="checkbox" name="smtp_pass_clear" value="1"> Effacer le mot de passe enregistré</label>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="actions"><button class="btn primary" type="submit">Enregistrer les réglages</button></div>
</form>

<form method="post" class="actions">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_mail">
    <button class="btn small" type="submit">Envoyer un e-mail de test</button>
    <span class="muted small">Enregistre d'abord tes réglages.</span>
</form>
<?php
page_footer(['admin' => true]);
