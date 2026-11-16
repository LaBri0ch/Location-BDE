<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require_installed();
$me = require_admin();
$pdo = db();

function admin_count(PDO $pdo): int
{
    return (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'invite') {
        $email = normalize_email(post('email'));
        $st = $pdo->prepare('SELECT is_admin FROM users WHERE email = ?');
        $st->execute([$email]);
        $existing = $st->fetch();
        if (!valid_email($email)) {
            flash('bad', 'Indique une adresse e-mail valide.');
        } elseif ($existing && (int)$existing['is_admin'] === 1) {
            flash('bad', 'Cette personne est déjà membre du bureau.');
        } elseif ($existing) {
            flash('bad', 'Cette personne a déjà un compte : utilise « Donner les droits » dans la liste des comptes.');
        } else {
            $code = make_invite_code();
            $pdo->prepare('INSERT INTO admin_invites(email, code_hash, created_by, created_at) VALUES (?,?,?,?)
                           ON CONFLICT(email) DO UPDATE SET code_hash = excluded.code_hash, created_by = excluded.created_by, created_at = excluded.created_at')
                ->execute([$email, password_hash(normalize_code($code), PASSWORD_DEFAULT), $me['name'], date('Y-m-d H:i:s')]);
            flash('ok', 'Invitation créée pour ' . $email . '. Code à lui remettre en main propre : ' . $code
                . ' (il ne sera plus affiché ; pour en obtenir un autre, refais une invitation).');
        }
    } elseif ($action === 'cancel_invite') {
        $pdo->prepare('DELETE FROM admin_invites WHERE email = ?')->execute([normalize_email(post('email'))]);
        flash('ok', 'Invitation annulée.');
    } elseif ($action === 'grant') {
        $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?')->execute([$id]);
        flash('ok', 'Droits d\'administration donnés.');
    } elseif ($action === 'revoke') {
        if ($id === (int)$me['id']) {
            flash('bad', 'Tu ne peux pas te retirer toi-même les droits.');
        } elseif (admin_count($pdo) <= 1) {
            flash('bad', 'Il doit rester au moins un administrateur.');
        } else {
            $pdo->prepare('UPDATE users SET is_admin = 0 WHERE id = ?')->execute([$id]);
            flash('ok', 'Droits d\'administration retirés.');
        }
    } elseif ($action === 'reset') {
        $pw = (string)($_POST['new_password'] ?? '');
        if ($err = valid_password($pw)) {
            flash('bad', $err);
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            flash('ok', 'Nouveau mot de passe enregistré. Communique-le à la personne : elle pourra le changer dans « Mes demandes ».');
        }
    }
    redirect('admin/comptes.php');
}

$invites = $pdo->query('SELECT email, created_by, created_at FROM admin_invites ORDER BY created_at DESC')->fetchAll();
$q = clean_line(query('q'), 60);
if ($q !== '') {
    $st = $pdo->prepare("SELECT id, email, name, is_admin, created_at, last_login FROM users
                         WHERE name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' ORDER BY is_admin DESC, name COLLATE NOCASE LIMIT 200");
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $st->execute([$like, $like]);
    $users = $st->fetchAll();
} else {
    $users = $pdo->query('SELECT id, email, name, is_admin, created_at, last_login FROM users ORDER BY is_admin DESC, name COLLATE NOCASE LIMIT 200')->fetchAll();
}
$total = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

page_header('Comptes', ['admin' => true, 'active' => 'comptes']);
?>
<div class="sec-head"><h1>Comptes</h1><p class="muted"><?= e(plural($total, 'compte')) ?></p></div>

<section class="panel">
    <h2 class="h-small">Inviter un membre du bureau</h2>
    <p class="muted small">Saisis son adresse e-mail. Le site génère un code à lui remettre en main propre : à l'inscription avec cette adresse, il sera reconnu comme membre du bureau et arrivera sur l'espace administrateur. Sans ce code, personne ne peut s'inscrire avec cette adresse.</p>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite">
        <div class="field">
            <label class="lbl" for="invite_email">Adresse e-mail</label>
            <input id="invite_email" name="email" type="email" maxlength="120" autocomplete="off" autocapitalize="none" required>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Créer l'invitation</button></div>
    </form>
    <?php if ($invites): ?>
        <p class="lbl">Invitations en attente</p>
        <ul class="plain accounts">
        <?php foreach ($invites as $inv): ?>
            <li>
                <span><b><?= e($inv['email']) ?></b> <span class="muted small">invité par <?= e($inv['created_by']) ?> le <?= e(date_fr(substr($inv['created_at'], 0, 10))) ?></span></span>
                <form method="post" data-confirm="Annuler cette invitation ?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_invite">
                    <input type="hidden" name="email" value="<?= e($inv['email']) ?>">
                    <button class="btn small danger" type="submit">Annuler</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="spacer"></div>

<div class="sec-head"><h2 class="h-section">Tous les comptes</h2></div>
<form method="get" class="search" role="search">
    <label class="sr" for="q">Rechercher un compte</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Nom ou e-mail" autocomplete="off">
    <button class="btn" type="submit">Chercher</button>
</form>

<?php if (!$users): ?>
    <div class="empty"><h3>Aucun compte ne correspond</h3></div>
<?php else: ?>
<div class="list">
<?php foreach ($users as $usr):
    $isMe = (int)$usr['id'] === (int)$me['id']; ?>
    <article class="card row">
        <div class="row-main">
            <div class="row-title">
                <h3 class="card-title"><?= e($usr['name']) ?><?= $isMe ? ' <span class="muted">(toi)</span>' : '' ?></h3>
                <?= (int)$usr['is_admin'] === 1 ? '<span class="pill p-info">Bureau</span>' : '<span class="pill p-off">Membre</span>' ?>
            </div>
            <p class="meta-line"><span class="sel"><?= e($usr['email']) ?></span></p>
            <p class="meta-line muted">Inscrit le <?= e(date_fr(substr($usr['created_at'], 0, 10))) ?><?= $usr['last_login'] ? ' · dernière connexion le ' . e(date_fr(substr($usr['last_login'], 0, 10))) : '' ?></p>
            <details>
                <summary>Nouveau mot de passe</summary>
                <form method="post" class="note-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                    <label class="sr" for="np<?= (int)$usr['id'] ?>">Nouveau mot de passe pour <?= e($usr['name']) ?></label>
                    <input id="np<?= (int)$usr['id'] ?>" name="new_password" type="text" autocomplete="off" placeholder="10 caractères minimum" required>
                    <button class="btn small" type="submit">Enregistrer</button>
                </form>
            </details>
        </div>
        <div class="row-act">
            <?php if (!$isMe): ?>
            <form method="post" data-confirm="<?= (int)$usr['is_admin'] === 1 ? 'Retirer les droits d\'administration à ' : 'Donner les droits d\'administration à ' ?><?= e($usr['name']) ?> ?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= (int)$usr['is_admin'] === 1 ? 'revoke' : 'grant' ?>">
                <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                <button class="btn small block<?= (int)$usr['is_admin'] === 1 ? ' danger' : '' ?>" type="submit"><?= (int)$usr['is_admin'] === 1 ? 'Retirer les droits' : 'Donner les droits' ?></button>
            </form>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif;

page_footer(['admin' => true]);
