<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$ref = strtoupper(query('ref'));
$token = query('t');
$me = current_user();

$st = db()->prepare('SELECT * FROM reservations WHERE ref = ?');
$st->execute([$ref]);
$r = $st->fetch();

// Accès : le propriétaire de la demande, ou le bureau. Les anciennes demandes faites
// sans compte restent accessibles avec le lien secret qu'elles avaient reçu.
$legacy = $r && $r['user_id'] === null && $token !== '' && hash_equals((string)$r['token'], $token);
$allowed = $r && ($legacy || ($me && ((int)$r['user_id'] === (int)$me['id'] || (int)$me['is_admin'] === 1)));

if (!$allowed) {
    if (!$me) {
        redirect('login.php?next=' . urlencode(request_next()));
    }
    http_response_code(404);
    page_header('Demande introuvable', ['active' => 'compte']);
    echo '<div class="empty"><h1>Demande introuvable</h1><p class="muted">Cette demande n\'existe pas, ou elle n\'est pas liée à ton compte.</p>'
        . '<a class="btn primary" href="' . e(url('compte.php')) . '">Mes demandes</a></div>';
    page_footer();
    exit;
}

$self = 'reservation.php?ref=' . urlencode($r['ref']) . ($legacy ? '&t=' . urlencode($r['token']) : '');

if (is_post()) {
    csrf_verify();
    if (post('action') === 'cancel' && $r['status'] === 'demandee') {
        change_status((int)$r['id'], 'annulee', $me ? $me['name'] : $r['borrower']);
        flash('ok', 'La demande est annulée.');
    }
    redirect($self);
}

$links = pay_links($r);
$status = $r['status'];
$steps = steps_for($r);
$curIdx = array_search($status, STATUS_ORDER, true);
$needPay = (float)$r['total'] > 0;
$needCau = (float)$r['caution'] > 0;

$showPay = $status === 'demandee' && ($needPay || $needCau);   // location et caution : en même temps

page_header('Demande ' . $r['ref'], ['active' => 'compte']);
?>
<div class="page-narrow">
<?php if (query('nouvelle') === '1'): ?>
    <p class="msg ok" role="status">Demande envoyée. Tu la retrouveras à tout moment dans « Mes demandes ».</p>
<?php endif; ?>
<?php if ($me): ?>
    <p><a class="btn small" href="<?= e(url('compte.php')) ?>">← Mes demandes</a></p>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div class="row-title">
            <h1><?= e($r['item_name']) ?><?= (int)$r['qty'] > 1 ? ' <span class="muted">×' . (int)$r['qty'] . '</span>' : '' ?></h1>
            <?= status_pill($r, true) ?>
        </div>
        <p class="muted"><?= e(date_fr($r['start_date'], true)) ?> → <?= e(date_fr($r['end_date'], true)) ?> · <?= e(plural((int)$r['days'], 'jour')) ?> · Réf. <span class="ref"><?= e($r['ref']) ?></span></p>
    </div>

    <dl class="sum">
        <div><dt>Location</dt><dd><?= $needPay ? e(money($r['total'])) : 'Gratuit' ?></dd></div>
        <div><dt>Caution</dt><dd><?= e(money($r['caution'])) ?></dd></div>
        <div><dt>Emprunteur</dt><dd class="dd-text"><?= e($r['borrower']) ?></dd></div>
    </dl>

    <?php if ($status !== 'annulee'): ?>
        <ol class="steps" aria-label="Avancement">
        <?php foreach ($steps as $key => $label):
            $i = array_search($key, STATUS_ORDER, true);
            $cls = $i < $curIdx ? 'done' : ($i === $curIdx ? 'cur' : ''); ?>
            <li class="<?= $cls ?>"><?= e($label) ?></li>
        <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($showPay): ?>
        <p class="msg warn"><b>À faire maintenant :</b> <?= $needPay && $needCau ? 'règle la location et dépose la caution en même temps. Ta demande est confirmée quand le bureau a reçu les deux.' : ($needPay ? 'règle la location.' : 'dépose la caution.') ?></p>
        <ol class="todo">
        <?php if ($needPay): ?>
            <li><div>
                <b>Paie la location : <?= e(money($r['total'])) ?></b>
                <?php if ($links['helloasso']): ?>
                    <a class="btn primary" href="<?= e($links['helloasso']) ?>" target="_blank" rel="noopener noreferrer">Payer sur HelloAsso</a>
                    <span class="muted small">Indique ta référence <?= e($r['ref']) ?> si le formulaire le permet.</span>
                <?php else: ?>
                    <span class="muted">Le bureau t'enverra le lien de paiement.</span>
                <?php endif; ?>
            </div></li>
        <?php endif; ?>
        <?php if ($needCau): ?>
            <li><div>
                <b>Dépose la caution : <?= e(money($r['caution'])) ?></b>
                <?php if ($links['swikly']): ?>
                    <a class="btn primary" href="<?= e($links['swikly']) ?>" target="_blank" rel="noopener noreferrer">Déposer la caution sur Swikly</a>
                <?php else: ?>
                    <span class="muted">Le bureau t'enverra le lien de caution.</span>
                <?php endif; ?>
            </div></li>
        <?php endif; ?>
        </ol>
        <p class="muted small">Le bureau confirme à la main : la page se met à jour quand c'est fait. L'objet t'est réservé dès maintenant.</p>
    <?php elseif ($status === 'remis'): ?>
        <p class="msg ok">L'objet est chez toi. À rendre le <?= e(date_fr($r['end_date'], true)) ?>.</p>
    <?php elseif ($status === 'rendu'): ?>
        <p class="msg ok">Objet rendu, merci !</p>
    <?php elseif ($status === 'annulee'): ?>
        <p class="msg muted">Cette demande est annulée.</p>
    <?php elseif ($status === 'caution' || $status === 'payee'): ?>
        <p class="msg muted">Tout est en ordre de ton côté. Le bureau te dit où et quand récupérer l'objet.</p>
    <?php endif; ?>

    <?php $pickup = setting('pickup_info'); if ($pickup !== '' && $status !== 'annulee' && $status !== 'rendu'): ?>
        <div class="note-box">
            <p class="lbl">Récupération et retour</p>
            <p><?= nl2br(e($pickup)) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($status === 'demandee'): ?>
        <form method="post" class="actions" data-confirm="Annuler cette demande ?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button class="btn small danger" type="submit">Annuler la demande</button>
        </form>
    <?php endif; ?>
</section>
</div>
<?php
page_footer();
