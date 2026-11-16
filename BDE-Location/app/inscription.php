<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$next = safe_next(query('next', post('next')));

if ($u = current_user()) {
    redirect(after_login_target($u, $next));
}

$errors = [];
$v = ['name' => '', 'email' => ''];
$domains = domains_label();

if (is_post()) {
    csrf_verify();
    $v['name'] = clean_line(post('name'), 80);
    $v['email'] = clean_line(post('email'), 120);
    if (post('website') !== '') {                       // champ piège pour les robots
        redirect('index.php');
    }
    if (too_many_attempts('register', 30)) {
        $errors[] = 'Trop d\'essais. Réessaie dans un quart d\'heure.';
    } else {
        record_failed_attempt('register');              // limite aussi les créations en masse
        $res = register_user($v['name'], $v['email'], (string)($_POST['password'] ?? ''), (string)($_POST['password2'] ?? ''), post('invite'));
        if ($res['ok']) {
            login_user((int)$res['id']);
            $user = ['is_admin' => $res['is_admin'] ? 1 : 0];
            flash('ok', $res['is_admin'] ? 'Compte créé : tu as accès à l\'espace bureau.' : 'Compte créé. Tu peux maintenant réserver du matériel.');
            redirect(after_login_target($user, $next));
        }
        $errors = $res['errors'];
    }
}

page_header('Créer un compte', ['active' => 'r:inscription.php']);
?>
<section class="panel narrow">
    <div class="panel-head">
        <h1>Créer un compte</h1>
        <p class="muted">
            <?= legacy_setup_needed()
                ? 'Mise à jour du site : le premier compte créé ici devient administrateur.'
                : ($domains !== '' ? 'Utilise ton adresse e-mail de l\'école (' . e($domains) . '). Un seul compte par adresse.' : 'Un seul compte par adresse e-mail.') ?>
        </p>
    </div>
    <?php foreach ($errors as $err): ?><p class="msg bad" role="alert"><?= e($err) ?></p><?php endforeach; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field">
            <label class="lbl" for="name">Prénom et nom</label>
            <input id="name" name="name" type="text" maxlength="80" autocomplete="name" value="<?= e($v['name']) ?>" required>
        </div>
        <div class="field">
            <label class="lbl" for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" maxlength="120" autocomplete="username" autocapitalize="none" value="<?= e($v['email']) ?>" required>
        </div>
        <div class="field">
            <label class="lbl" for="password">Mot de passe (10 caractères minimum)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label class="lbl" for="password2">Confirmer le mot de passe</label>
            <input id="password2" name="password2" type="password" autocomplete="new-password" required>
        </div>
        <details>
            <summary>Tu es membre du bureau ?</summary>
            <div class="field">
                <label class="lbl" for="invite">Code d'invitation</label>
                <input id="invite" name="invite" type="text" autocomplete="off" autocapitalize="characters" placeholder="XXXXX-XXXXX">
                <span class="hint">Le bureau te le remet en main propre. Utilise l'adresse e-mail pour laquelle il a été créé.</span>
            </div>
        </details>
        <div class="actions"><button class="btn primary" type="submit">Créer mon compte</button></div>
    </form>
    <p class="muted small">Déjà un compte ? <a href="<?= e(url('login.php' . ($next !== '' ? '?next=' . urlencode($next) : ''))) ?>">Se connecter</a>.</p>
</section>
<?php
page_footer();
