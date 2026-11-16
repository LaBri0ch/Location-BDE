<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require_installed();
$admin = require_admin();
$pdo = db();

$filters = [
    'todo' => ['À préparer', "status IN ('demandee','payee','caution')"],
    'out'  => ['Prêtés', "status = 'remis'"],
    'late' => ['En retard', "status = 'remis' AND end_date < :today"],
    'done' => ['Terminées', "status IN ('rendu','annulee')"],
    'all'  => ['Toutes', '1 = 1'],
];
$f = query('f', 'todo');
if (!isset($filters[$f])) {
    $f = 'todo';
}
$q = clean_line(query('q'), 60);

/* ---------- Actions ---------- */
if (is_post()) {
    csrf_verify();
    $id = (int)post('id');
    $action = post('action');
    $back = 'admin/index.php?f=' . urlencode(post('f', 'todo')) . (post('q') !== '' ? '&q=' . urlencode(post('q')) : '') . '#r' . $id;

    $st = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();

    if ($r) {
        if ($action === 'next') {
            $target = post('status');
            if ($target === next_status($r) && change_status($id, $target, $admin['name'])) {
                flash('ok', $r['borrower'] . ' : ' . (statuses()[$target][0]) . '.');
            }
        } elseif ($action === 'set_status') {
            $target = post('status');
            if (change_status($id, $target, $admin['name'])) {
                flash('ok', $r['borrower'] . ' : statut « ' . statuses()[$target][0] . ' ».');
            }
        } elseif ($action === 'note') {
            $pdo->prepare('UPDATE reservations SET admin_note = ?, updated_at = ? WHERE id = ?')
                ->execute([mb_substr((string)($_POST['admin_note'] ?? ''), 0, 500), date('Y-m-d H:i:s'), $id]);
            flash('ok', 'Note enregistrée.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM events WHERE reservation_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
            flash('ok', 'Demande supprimée.');
            $back = 'admin/index.php?f=' . urlencode(post('f', 'todo'));
        }
    }
    redirect($back);
}

/* ---------- Données ---------- */
$counts = [];
foreach ($filters as $k => [$label, $where]) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE $where");
    if (strpos($where, ':today') !== false) {
        $st->bindValue(':today', today());
    }
    $st->execute();
    $counts[$k] = (int)$st->fetchColumn();
}

$where = $filters[$f][1];
$params = [];
if (strpos($where, ':today') !== false) {
    $params[':today'] = today();
}
if ($q !== '') {
    $where = "($where) AND (borrower LIKE :q ESCAPE '\\' OR email LIKE :q ESCAPE '\\' OR ref LIKE :q ESCAPE '\\' OR item_name LIKE :q ESCAPE '\\')";
    $params[':q'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
}
$order = in_array($f, ['done', 'all'], true) ? 'start_date DESC, id DESC' : 'start_date ASC, id ASC';
$sql = "SELECT * FROM reservations WHERE $where ORDER BY $order LIMIT 300";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$evSt = $pdo->prepare('SELECT status, by_user, at FROM events WHERE reservation_id = ? ORDER BY id');

page_header('Suivi', ['admin' => true, 'active' => 'suivi']);
?>
<div class="sec-head">
    <h1>Suivi des prêts</h1>
    <a class="btn small" href="<?= e(url('admin/export.php')) ?>">Exporter en CSV</a>
</div>

<div class="chips" role="group" aria-label="Filtrer">
<?php foreach ($filters as $k => [$label]): ?>
    <a class="chip" href="<?= e(url('admin/index.php?f=' . $k)) ?>" <?= $f === $k ? 'aria-current="true"' : '' ?>><?= e($label) ?> <b><?= $counts[$k] ?></b></a>
<?php endforeach; ?>
</div>

<form class="search" method="get" action="<?= e(url('admin/index.php')) ?>">
    <input type="hidden" name="f" value="<?= e($f) ?>">
    <label class="lbl sr" for="q">Rechercher</label>
    <input id="q" name="q" type="search" placeholder="Nom, e-mail, référence, objet" value="<?= e($q) ?>">
    <button class="btn" type="submit">Chercher</button>
</form>

<?php if (!$rows): ?>
    <div class="empty">
        <h2>Rien dans cette liste</h2>
        <p class="muted"><?= $q !== '' ? 'Aucun résultat pour cette recherche.' : ($f === 'todo' ? 'Aucune demande à préparer pour le moment.' : 'Aucune réservation ne correspond.') ?></p>
    </div>
<?php else: ?>
<div class="list">
<?php foreach ($rows as $r):
    $next = next_status($r);
    $late = is_late($r);
    $evSt->execute([(int)$r['id']]);
    $events = $evSt->fetchAll();
    $links = pay_links($r);
    ?>
    <article class="card row" id="r<?= (int)$r['id'] ?>">
        <div class="row-main">
            <div class="row-title">
                <h2 class="card-title"><?= e($r['item_name']) ?><?= (int)$r['qty'] > 1 ? ' <span class="muted">×' . (int)$r['qty'] . '</span>' : '' ?></h2>
                <?= status_pill($r) ?>
            </div>
            <p class="meta-line"><?= e(date_fr($r['start_date'], true)) ?> → <?= e(date_fr($r['end_date'], true)) ?> · <?= e(plural((int)$r['days'], 'jour')) ?> · <b><?= e($r['borrower']) ?></b></p>
            <p class="meta-line">
                <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a>
                <?php if ($r['phone'] !== ''): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a><?php endif; ?>
            </p>
            <p class="meta-line muted">Réf. <span class="ref"><?= e($r['ref']) ?></span> · Location <?= e(money($r['total'])) ?> · Caution <?= e(money($r['caution'])) ?><?= $r['note'] !== '' ? ' · « ' . e($r['note']) . ' »' : '' ?></p>
            <details>
                <summary>Note interne et historique</summary>
                <form method="post" class="note-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="note">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="f" value="<?= e($f) ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <label class="lbl sr" for="n<?= (int)$r['id'] ?>">Note interne</label>
                    <textarea id="n<?= (int)$r['id'] ?>" name="admin_note" maxlength="500" placeholder="Visible seulement par le bureau"><?= e($r['admin_note']) ?></textarea>
                    <button class="btn small" type="submit">Enregistrer la note</button>
                </form>
                <ul class="plain history">
                <?php foreach ($events as $ev): ?>
                    <li><?= e(statuses()[$ev['status']][0] ?? $ev['status']) ?> · <?= e(date_fr(substr($ev['at'], 0, 10))) ?> <?= e(substr($ev['at'], 11, 5)) ?> · <?= e($ev['by_user']) ?></li>
                <?php endforeach; ?>
                </ul>
                <p class="small"><a href="<?= e(url('reservation.php?ref=' . urlencode($r['ref']) . '&t=' . urlencode($r['token']))) ?>">Voir la page de suivi de l'emprunteur</a></p>
            </details>
        </div>
        <div class="row-act">
            <?php if ($next): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="next">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="status" value="<?= e($next) ?>">
                <input type="hidden" name="f" value="<?= e($f) ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <button class="btn primary small block" type="submit"><?= e(next_status_labels()[$next]) ?></button>
            </form>
            <?php endif; ?>
            <form method="post" class="inline-status">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="f" value="<?= e($f) ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <label class="lbl sr" for="s<?= (int)$r['id'] ?>">Statut</label>
                <select id="s<?= (int)$r['id'] ?>" name="status">
                <?php foreach (statuses() as $key => [$label]): ?>
                    <option value="<?= e($key) ?>"<?= $key === $r['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
                </select>
                <button class="btn small" type="submit">OK</button>
            </form>
            <form method="post" data-confirm="Supprimer définitivement cette demande ?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="f" value="<?= e($f) ?>">
                <button class="btn small danger block" type="submit">Supprimer</button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif;

page_footer(['admin' => true]);
