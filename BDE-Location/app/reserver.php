<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$user = require_login();   // renvoie vers la connexion, puis revient ici
$id = (int)query('id', (string)($_POST['item_id'] ?? '0'));
$st = db()->prepare('SELECT * FROM items WHERE id = ? AND active = 1');
$st->execute([$id]);
$item = $st->fetch();
if (!$item) {
    http_response_code(404);
    page_header('Objet introuvable', ['active' => 'catalogue']);
    echo '<div class="empty"><h1>Cet objet n\'est plus au catalogue</h1><a class="btn primary" href="' . e(url('index.php')) . '">Voir le matériel</a></div>';
    page_footer();
    exit;
}

$errors = [];
$v = [
    'start' => today(), 'end' => today(), 'qty' => '1',
    'phone' => (string)($user['phone'] ?? ''), 'note' => '',
];

if (is_post()) {
    csrf_verify();
    foreach ($v as $k => $_) {
        $v[$k] = post($k, $v[$k]);
    }
    $v['note'] = mb_substr((string)($_POST['note'] ?? ''), 0, 300);

    if (post('website') !== '') {            // champ piège : un humain ne le remplit pas
        redirect('index.php');
    }
    $res = create_reservation($item, $v, $user);
    if ($res['ok']) {
        $r = $res['reservation'];
        notify_new_reservation($r);
        redirect('reservation.php?ref=' . urlencode($r['ref']) . '&nouvelle=1');
    }
    $errors = $res['errors'];
}

$usage = usage_by_day((int)$item['id'], today(), add_days(today(), 400));
$stock = max(0, (int)$item['stock']);
$maxDays = max_days();

page_header($item['name'], ['active' => 'catalogue']);
?>
<div class="page-narrow">
<p><a class="btn small" href="<?= e(url('index.php')) ?>">← Matériel</a></p>

<section class="panel">
    <?php $img = item_image_url($item['image']); if ($img !== ''): ?>
        <img class="thumb thumb-wide" src="<?= e($img) ?>" alt="<?= e($item['name']) ?>" width="400" height="300">
    <?php endif; ?>
    <div class="panel-head">
        <span class="tag">Caution <?= e(money($item['caution'])) ?></span>
        <h1><?= e($item['name']) ?></h1>
        <?php if ($item['description'] !== ''): ?><p class="muted"><?= e($item['description']) ?></p><?php endif; ?>
    </div>

    <?php foreach ($errors as $err): ?>
        <p class="msg bad" role="alert"><?= e($err) ?></p>
    <?php endforeach; ?>

    <div class="cal-wrap">
        <p class="lbl">Choisis tes dates</p>
        <div id="cal" class="cal" hidden
             data-stock="<?= $stock ?>"
             data-today="<?= e(today()) ?>"
             data-max="<?= $maxDays ?>"
             data-used="<?= e(json_encode((object)$usage, JSON_UNESCAPED_SLASHES)) ?>"></div>
        <p class="muted small" id="cal-help"><?= $stock > 1
            ? 'Le chiffre sur chaque jour est le nombre d\'exemplaires libres (' . $stock . ' au total). Un jour est bloqué quand il n\'en reste plus.'
            : 'Les jours barrés sont déjà pris.' ?></p>
        <p class="msg" id="cal-msg" role="status" hidden></p>
    </div>

    <form method="post" class="form" id="booking-form" autocomplete="on"
          data-price="<?= e((string)(float)$item['price_day']) ?>"
          data-caution="<?= e((string)(float)$item['caution']) ?>"
          data-max="<?= $maxDays ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
        <div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="start">Du</label>
                <input id="start" name="start" type="date" min="<?= e(today()) ?>" value="<?= e($v['start']) ?>" required>
            </div>
            <div class="field">
                <label class="lbl" for="end">Au (inclus)</label>
                <input id="end" name="end" type="date" min="<?= e(today()) ?>" value="<?= e($v['end']) ?>" required>
            </div>
            <?php if ($stock > 1): ?>
            <div class="field">
                <label class="lbl" for="qty">Quantité</label>
                <input id="qty" name="qty" type="number" min="1" max="<?= $stock ?>" value="<?= e($v['qty']) ?>" inputmode="numeric">
            </div>
            <?php endif; ?>
            <div class="field<?= $stock > 1 ? '' : ' span2' ?>">
                <label class="lbl" for="phone">Téléphone (facultatif)</label>
                <input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($v['phone']) ?>" autocomplete="tel">
            </div>
            <div class="field span2">
                <label class="lbl" for="note">Remarque (facultatif)</label>
                <textarea id="note" name="note" maxlength="300"><?= e($v['note']) ?></textarea>
            </div>
        </div>

        <p class="muted small">Réservation au nom de <b><?= e($user['name']) ?></b> (<?= e($user['email']) ?>).</p>

        <dl class="sum" id="summary" hidden>
            <div><dt>Durée</dt><dd id="sum-days">-</dd></div>
            <div><dt>Location</dt><dd id="sum-total">-</dd></div>
            <div><dt>Caution</dt><dd id="sum-caution">-</dd></div>
        </dl>
        <p class="muted small">Durée maximale : <?= e(plural($maxDays, 'jour')) ?>. Tu règles la location et la caution en même temps, juste après l'envoi. L'objet t'est réservé dès la demande.</p>

        <div class="actions">
            <button class="btn primary" type="submit">Envoyer la demande</button>
        </div>
    </form>
</section>
</div>
<?php
page_footer();
