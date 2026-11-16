<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();
$me = require_login();
$pdo = db();

function password_ok(PDO $pdo, int $uid, string $given): bool
{
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$uid]);
    return password_verify($given, (string)$st->fetchColumn());
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $uid = (int)$me['id'];
    $cur = (string)($_POST['current_password'] ?? '');
    $locked = false;
    if (in_array($action, ['email', 'password', 'delete'], true)) {
        if (too_many_attempts('password', 8)) {
            flash('bad', 'Trop d\'essais. Réessaie dans un quart d\'heure.');
            $locked = true;
        } elseif (!password_ok($pdo, $uid, $cur)) {
            record_failed_attempt('password');
            flash('bad', 'Le mot de passe actuel est incorrect.');
            $locked = true;
        }
    }

    if ($locked) {
        // rien d'autre à faire : le message est déjà affiché
    } elseif ($action === 'profile') {
        $name = clean_line(post('name'), 80);
        $phone = clean_line(post('phone'), 30);
        if (mb_strlen($name) < 2) {
            flash('bad', 'Indique ton prénom et ton nom.');
        } elseif ($phone !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $phone)) {
            flash('bad', 'Le numéro de téléphone n\'est pas valide.');
        } else {
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, notify = ? WHERE id = ?')
                ->execute([$name, $phone, post('notify') === '1' ? 1 : 0, $uid]);
            flash('ok', 'Paramètres enregistrés.');
        }
    } elseif ($action === 'email') {
        $new = normalize_email(post('new_email'));
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id != ?');
        $st->execute([$new, $uid]);
        $inv = $pdo->prepare('SELECT 1 FROM admin_invites WHERE email = ?');
        $inv->execute([$new]);
        if (!valid_email($new)) {
            flash('bad', 'Indique une adresse e-mail valide.');
        } elseif ($new === normalize_email((string)$me['email'])) {
            flash('bad', 'C\'est déjà ton adresse actuelle.');
        } elseif ((int)$me['is_admin'] !== 1 && !email_domain_ok($new)) {
            flash('bad', 'Cette adresse n\'est pas autorisée. Utilise ton adresse e-mail de l\'école (' . domains_label() . ').');
        } elseif ($st->fetchColumn() || $inv->fetchColumn()) {
            flash('bad', 'Cette adresse est déjà utilisée par un autre compte.');
        } else {
            try {
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$new, $uid]);
                flash('ok', 'Adresse e-mail changée : connecte-toi désormais avec ' . $new . '.');
            } catch (PDOException $ex) {
                flash('bad', 'Cette adresse est déjà utilisée par un autre compte.');
            }
        }
    } elseif ($action === 'password') {
        $new = (string)($_POST['new_password'] ?? '');
        if ($err = valid_password($new)) {
            flash('bad', $err);
        } elseif ($new !== (string)($_POST['new_password2'] ?? '')) {
            flash('bad', 'Les deux mots de passe ne correspondent pas.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            flash('ok', 'Mot de passe changé.');
        }
    } elseif ($action === 'delete') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status NOT IN ('rendu','annulee')");
        $st->execute([$uid]);
        $lastAdmin = (int)$me['is_admin'] === 1 && (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn() <= 1;
        if (post('confirm') !== '1') {
            flash('bad', 'Coche la case pour confirmer la suppression.');
        } elseif ((int)$st->fetchColumn() > 0) {
            flash('bad', 'Tu as encore des demandes en cours. Termine-les ou annule-les avant de supprimer ton compte.');
        } elseif ($lastAdmin) {
            flash('bad', 'Tu es le dernier administrateur : donne d\'abord les droits à quelqu\'un d\'autre.');
        } else {
            $pdo->prepare('UPDATE reservations SET user_id = NULL WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            $_SESSION = [];
            session_regenerate_id(true);
            flash('ok', 'Ton compte est supprimé.');
            redirect('index.php');
        }
    }
    redirect('parametres.php');
}

page_header('Paramètres', ['active' => 'parametres']);
?>
<div class="page-narrow">
<div class="sec-head">
    <div>
        <h1>Paramètres</h1>
        <p class="lede">Gère les informations de ton compte.</p>
    </div>
</div>

<form method="post" class="form stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <section class="panel">
        <h2 class="h-small">Profil</h2>
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="name">Prénom et nom</label>
                <input id="name" name="name" type="text" maxlength="80" autocomplete="name" value="<?= e($me['name']) ?>" required>
            </div>
            <div class="field span2">
                <label class="lbl" for="phone">Téléphone (facultatif)</label>
                <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?= e($me['phone']) ?>">
                <span class="hint">Pré-rempli quand tu fais une demande.</span>
            </div>
            <div class="field span2">
                <label class="check"><input type="checkbox" name="notify" value="1"<?= (int)$me['notify'] !== 0 ? ' checked' : '' ?>> Recevoir un e-mail de confirmation à chaque demande</label>
            </div>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Enregistrer</button></div>
    </section>
</form>

<form method="post" class="form stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="email">
    <section class="panel">
        <h2 class="h-small">Adresse e-mail</h2>
        <p class="muted small">Adresse actuelle : <b><?= e($me['email']) ?></b>. C'est ton identifiant de connexion.</p>
        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="new_email">Nouvelle adresse</label>
                <input id="new_email" name="new_email" type="email" maxlength="120" autocomplete="off" autocapitalize="none" required>
            </div>
            <div class="field">
                <label class="lbl" for="pw_email">Mot de passe actuel</label>
                <input id="pw_email" name="current_password" type="password" autocomplete="current-password" required>
            </div>
        </div>
        <div class="actions"><button class="btn" type="submit">Changer l'adresse</button></div>
    </section>
</form>

<form method="post" class="form stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <section class="panel">
        <h2 class="h-small">Mot de passe</h2>
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="current_password">Mot de passe actuel</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label class="lbl" for="new_password">Nouveau mot de passe (10 caractères minimum)</label>
                <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label class="lbl" for="new_password2">Confirmer</label>
                <input id="new_password2" name="new_password2" type="password" autocomplete="new-password" required>
            </div>
        </div>
        <div class="actions"><button class="btn" type="submit">Changer le mot de passe</button></div>
    </section>
</form>

<form method="post" class="form stack" data-confirm="Supprimer définitivement ton compte ?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <section class="panel">
        <h2 class="h-small">Supprimer mon compte</h2>
        <p class="muted small">Ton compte est supprimé. L'historique de tes anciens prêts reste au bureau, sans lien avec ton compte. Impossible tant que tu as des demandes en cours.</p>
        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="pw_del">Mot de passe actuel</label>
                <input id="pw_del" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label class="check"><input type="checkbox" name="confirm" value="1"> Je confirme vouloir supprimer mon compte</label>
            </div>
        </div>
        <div class="actions"><button class="btn danger" type="submit">Supprimer mon compte</button></div>
    </section>
</form>
</div>
<?php
page_footer();
