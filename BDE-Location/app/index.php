<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$items = db()->query('SELECT * FROM items WHERE active = 1 ORDER BY name COLLATE NOCASE')->fetchAll();
$anyPhoto = (bool)array_filter($items, fn($i) => item_image_url($i['image']) !== '');
$me = current_user();

page_header('', ['active' => 'catalogue']);
?>
<div class="sec-head">
    <div>
        <h1>Matériel à emprunter</h1>
        <p class="lede"><?= $me
            ? 'Choisis un objet, fixe tes dates, puis suis ta demande dans « Mes demandes ».'
            : 'Choisis un objet et fixe tes dates. Il faut un compte avec ton adresse e-mail de l\'école pour réserver : <a href="' . e(url('inscription.php')) . '">crée-le</a> ou <a href="' . e(url('login.php')) . '">connecte-toi</a>.' ?></p>
    </div>
</div>

<?php if (!$items): ?>
    <div class="empty">
        <h2>Le catalogue est vide pour l'instant</h2>
        <p class="muted">Le bureau ajoute bientôt les objets disponibles. Repasse un peu plus tard.</p>
    </div>
<?php else: ?>
    <div class="grid">
    <?php foreach ($items as $it):
        $av = item_available_today($it);
        $blocks = upcoming_blocks((int)$it['id']);
        $next = $blocks[0] ?? null;
        $stock = (int)$it['stock'];
        ?>
        <article class="card">
            <?php $img = item_image_url($it['image']); ?>
            <?php if ($img !== ''): ?>
                <img class="thumb" src="<?= e($img) ?>" alt="<?= e($it['name']) ?>" loading="lazy" width="400" height="300">
            <?php elseif ($anyPhoto): ?>
                <div class="thumb thumb-empty" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></div>
            <?php endif; ?>
            <span class="tag">Caution <?= e(money($it['caution'])) ?></span>
            <h2 class="card-title"><?= e($it['name']) ?></h2>
            <?php if ($it['description'] !== ''): ?>
                <p class="desc"><?= e($it['description']) ?></p>
            <?php endif; ?>
            <div class="meta">
                <span class="price"><?= (float)$it['price_day'] > 0 ? e(money($it['price_day'])) . ' / jour' : 'Gratuit' ?></span>
                <?php if ($stock <= 0): ?>
                    <span class="pill p-off">Hors stock</span>
                <?php elseif ($av['free'] > 0): ?>
                    <span class="pill p-ok">Disponible</span>
                <?php else: ?>
                    <span class="pill p-late">Tout est pris aujourd'hui</span>
                <?php endif; ?>
                <?php if ($stock > 0): ?>
                    <span class="muted"><b><?= (int)$av['free'] ?></b> libre<?= $av['free'] > 1 ? 's' : '' ?> sur <?= $stock ?></span>
                <?php endif; ?>
            </div>
            <div class="foot-card">
                <?php if ($next): ?>
                    <p class="muted small">Prochaine réservation : <?= e(date_fr($next['start_date'])) ?> → <?= e(date_fr($next['end_date'])) ?><?= (int)$next['qty'] > 1 ? ' (' . (int)$next['qty'] . ' ex.)' : '' ?></p>
                <?php endif; ?>
                <?php if ($stock > 0): ?>
                    <a class="btn primary" href="<?= e(url('reserver.php?id=' . (int)$it['id'])) ?>">Réserver</a>
                <?php else: ?>
                    <span class="btn" aria-disabled="true">Indisponible</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif;

page_footer();
