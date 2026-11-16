<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$next = safe_next(query('next', post('next')));

if ($u = current_user()) {
    redirect(after_login_target($u, $next));
}

$error = '';
$email = '';
if (is_post()) {
    csrf_verify();
    $email = clean_line(post('email'), 120);
    if (too_many_attempts()) {
        $error = 'Trop d\'essais échoués. Réessaie dans un quart d\'heure.';
    } elseif ($row = attempt_login($email, (string)($_POST['password'] ?? ''))) {
        redirect(after_login_target($row, $next));
    } else {
        $error = 'E-mail ou mot de passe incorrect.';
    }
}

page_header('Connexion', ['active' => 'r:login.php']);
?>
<section class="panel narrow">
    <div class="panel-head">
        <h1>Connexion</h1>
        <p class="muted">Avec ton adresse e-mail de l'école et ton mot de passe.</p>
    </div>
    <?php if (legacy_setup_needed()): ?>
        <p class="msg warn">Le site vient d'être mis à jour. <a href="<?= e(url('inscription.php')) ?>">Crée ton compte</a> : le premier compte créé devient administrateur.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?><p class="msg bad" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label class="lbl" for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" autocapitalize="none" value="<?= e($email) ?>" required autofocus>
        </div>
        <div class="field">
            <label class="lbl" for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Se connecter</button></div>
    </form>
    <p class="muted small">Pas encore de compte ? <a href="<?= e(url('inscription.php' . ($next !== '' ? '?next=' . urlencode($next) : ''))) ?>">Créer un compte</a>.
        Mot de passe oublié ? Demande au bureau de le réinitialiser.</p>
</section>
<?php
page_footer();
