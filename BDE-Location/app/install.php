<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if (is_installed()) {
    page_header('Déjà installé');
    echo '<div class="empty"><h1>Le site est déjà installé</h1><p class="muted">Pour des raisons de sécurité, cette page est désactivée. Tu peux supprimer le fichier <code>install.php</code> du serveur.</p>'
        . '<a class="btn primary" href="' . e(url('login.php')) . '">Se connecter</a></div>';
    page_footer();
    exit;
}

$errors = [];
$problems = [];
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $problems[] = 'PHP 8.1 ou plus récent est nécessaire (version actuelle : ' . PHP_VERSION . ').';
}
foreach (['pdo_sqlite', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        $problems[] = 'L\'extension PHP « ' . $ext . ' » est absente. Active-la dans le panneau de ton hébergeur.';
    }
}
if (!is_dir(DATA_DIR) && !@mkdir(DATA_DIR, 0750, true)) {
    $problems[] = 'Impossible de créer le dossier « data ». Crée-le à la main à côté de install.php et rends-le accessible en écriture.';
} elseif (!is_writable(DATA_DIR)) {
    $problems[] = 'Le dossier « data » n\'est pas accessible en écriture. Donne-lui les droits 755 (ou 775) dans ton gestionnaire de fichiers.';
}

$siteName = 'Location BDE';
$name = '';
$email = '';

if (is_post() && !$problems) {
    csrf_verify();
    $siteName = clean_line(post('site_name'), 60) ?: 'Location BDE';
    $name = clean_line(post('name'), 80);
    $email = normalize_email(post('email'));
    $pw = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');

    if (mb_strlen($name) < 2) {
        $errors[] = 'Indique ton prénom et ton nom.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Indique une adresse e-mail valide : elle servira à te connecter.';
    }
    if ($err = valid_password($pw)) {
        $errors[] = $err;
    }
    if ($pw !== $pw2) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            install_schema($pdo);
            $pdo->prepare('INSERT INTO users(email, name, password_hash, is_admin, created_at) VALUES (?,?,?,1,?)')
                ->execute([$email, $name, password_hash($pw, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            save_setting('site_name', $siteName);
            save_setting('max_days', '30');
            file_put_contents(INSTALL_LOCK, date('c'));
            flash('ok', 'Installation terminée. Connecte-toi pour ajouter ton matériel.');
            redirect('login.php');
        } catch (Throwable $ex) {
            error_log('install: ' . $ex->getMessage());
            @unlink(DB_FILE);
            $errors[] = 'L\'installation a échoué. Vérifie les droits d\'écriture du dossier « data » et réessaie.';
        }
    }
}

page_header('Installation', ['bare' => true]);
?>
<section class="panel narrow">
    <div class="panel-head">
        <h1>Installation du site</h1>
        <p class="muted">Dernière étape : crée le premier compte administrateur. Tu pourras inviter les autres membres du bureau ensuite.</p>
    </div>
    <?php foreach ($problems as $p): ?><p class="msg bad" role="alert"><?= e($p) ?></p><?php endforeach; ?>
    <?php foreach ($errors as $p): ?><p class="msg bad" role="alert"><?= e($p) ?></p><?php endforeach; ?>

    <?php if (!$problems): ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label class="lbl" for="site_name">Nom du site</label>
            <input id="site_name" name="site_name" type="text" maxlength="60" value="<?= e($siteName) ?>" required>
        </div>
        <div class="field">
            <label class="lbl" for="name">Prénom et nom</label>
            <input id="name" name="name" type="text" maxlength="80" autocomplete="name" value="<?= e($name) ?>" required>
        </div>
        <div class="field">
            <label class="lbl" for="email">Adresse e-mail (sert d'identifiant)</label>
            <input id="email" name="email" type="email" maxlength="120" autocomplete="username" autocapitalize="none" value="<?= e($email) ?>" required>
        </div>
        <div class="field">
            <label class="lbl" for="password">Mot de passe (10 caractères minimum)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label class="lbl" for="password2">Confirmer le mot de passe</label>
            <input id="password2" name="password2" type="password" autocomplete="new-password" required>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Installer</button></div>
    </form>
    <?php endif; ?>
</section>
<?php
page_footer(['bare' => true]);
