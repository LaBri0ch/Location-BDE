<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();
$me = require_login();
$pdo = db();

$st = $pdo->prepare('SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$st->execute([(int)$me['id']]);
$mine = $st->fetchAll();

$active = array_values(array_filter($mine, fn($r) => !in_array($r['status'], ['rendu', 'annulee'], true)));
$past = array_values(array_filter($mine, fn($r) => in_array($r['status'], ['rendu', 'annulee'], true)));

/** Ce que l'emprunteur doit faire maintenant, ou chaîne vide. */
function todo_hint(array $r): string
{
    $pay = (float)$r['total'] > 0;
    $cau = (float)$r['caution'] > 0;
    if ($r['status'] === 'demandee' && ($pay || $cau)) {
        $parts = [];
        if ($pay) {
            $parts[] = 'payer la location (' . money($r['total']) . ')';
        }
        if ($cau) {
            $parts[] = 'déposer la caution (' . money($r['caution']) . ')';
        }
        return 'À faire : ' . implode(' et ', $parts) . (count($parts) > 1 ? ', en même temps.' : '.');
    }
    if ($r['status'] === 'remis') {
        return is_late($r) ? 'À rendre dès que possible.' : 'À rendre le ' . date_fr($r['end_date'], true) . '.';
    }
    if ($r['status'] === 'demandee' || $r['status'] === 'payee' || $r['status'] === 'caution') {
        return 'Le bureau prépare ton objet.';
    }
    return '';
}

function request_card(array $r): void
{
    $hint = todo_hint($r);
    $href = url('reservation.php?ref=' . urlencode($r['ref']));
    ?>
    <article class="card row">
        <div class="row-main">
            <div class="row-title">
                <h3 class="card-title"><?= e($r['item_name']) ?><?= (int)$r['qty'] > 1 ? ' <span class="muted">×' . (int)$r['qty'] . '</span>' : '' ?></h3>
                <?= status_pill($r) ?>
            </div>
            <p class="meta-line"><?= e(date_fr($r['start_date'], true)) ?> → <?= e(date_fr($r['end_date'], true)) ?> · <?= e(plural((int)$r['days'], 'jour')) ?> · Réf. <span class="ref"><?= e($r['ref']) ?></span></p>
            <p class="meta-line muted">Location <?= (float)$r['total'] > 0 ? e(money($r['total'])) : 'gratuite' ?> · Caution <?= e(money($r['caution'])) ?></p>
            <?php if ($hint !== ''): ?><p class="meta-line"><b><?= e($hint) ?></b></p><?php endif; ?>
        </div>
        <div class="row-act">
            <a class="btn small<?= $hint !== '' && str_starts_with($hint, 'À faire') ? ' primary' : '' ?> block" href="<?= e($href) ?>">Ouvrir</a>
        </div>
    </article>
    <?php
}

page_header('Mon compte', ['active' => 'compte']);
?>
<div class="sec-head">
    <div>
        <h1>Bonjour <?= e(explode(' ', $me['name'])[0]) ?></h1>
        <p class="lede"><?= e($me['email']) ?><?= (int)$me['is_admin'] === 1 ? ' · <a href="' . e(url('admin/index.php')) . '">Espace bureau</a>' : '' ?></p>
    </div>
    <a class="btn primary small" href="<?= e(url('index.php')) ?>">Réserver du matériel</a>
</div>

<h2 class="h-section">Mes demandes</h2>
<?php if (!$mine): ?>
    <div class="empty">
        <h3>Aucune demande pour l'instant</h3>
        <p class="muted">Choisis un objet dans le catalogue : ta demande apparaîtra ici, avec les liens de paiement et l'avancement.</p>
        <a class="btn primary" href="<?= e(url('index.php')) ?>">Voir le matériel</a>
    </div>
<?php else: ?>
    <?php if ($active): ?>
    <div class="list">
        <?php foreach ($active as $r) { request_card($r); } ?>
    </div>
    <?php else: ?>
        <p class="muted">Aucune demande en cours.</p>
    <?php endif; ?>

    <?php if ($past): ?>
    <h2 class="h-section">Historique</h2>
    <div class="list">
        <?php foreach ($past as $r) { request_card($r); } ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php
page_footer();
