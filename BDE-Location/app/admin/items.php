<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require_installed();
require_admin();
$pdo = db();

$editing = null;      // ligne en cours d'édition (null = formulaire fermé)
$isNew = false;
$form = ['name' => '', 'description' => '', 'stock' => '1', 'caution' => '0', 'price_day' => '0', 'helloasso_url' => '', 'swikly_url' => ''];
$errors = [];

if (is_post() && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('bad', 'Le fichier envoyé est trop lourd pour le serveur (limite : ' . human_bytes((string)ini_get('post_max_size')) . '). Choisis une photo plus légère.');
    redirect('admin/items.php');
}

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'save') {
        foreach ($form as $k => $_) {
            $form[$k] = post($k, $form[$k]);
        }
        $name = clean_line($form['name'], 80);
        $desc = clean_line($form['description'], 200);
        $stock = ctype_digit($form['stock']) ? (int)$form['stock'] : null;
        $caution = parse_decimal($form['caution']);
        $price = parse_decimal($form['price_day']);
        $ha = $form['helloasso_url'] === '' ? '' : safe_url($form['helloasso_url']);
        $sw = $form['swikly_url'] === '' ? '' : safe_url($form['swikly_url']);

        if (mb_strlen($name) < 2) {
            $errors[] = 'Donne un nom à l\'objet.';
        }
        if ($stock === null || $stock > 999) {
            $errors[] = 'Le stock doit être un nombre entier (0 pour retirer l\'objet de la location).';
        }
        if ($caution === null || $caution < 0 || $caution > 100000) {
            $errors[] = 'La caution doit être un montant positif ou nul.';
        }
        if ($price === null || $price < 0 || $price > 100000) {
            $errors[] = 'Le prix par jour doit être un montant positif ou nul.';
        }
        if ($form['helloasso_url'] !== '' && $ha === '') {
            $errors[] = 'Le lien HelloAsso doit commencer par https://';
        }
        if ($form['swikly_url'] !== '' && $sw === '') {
            $errors[] = 'Le lien Swikly doit commencer par https://';
        }

        $newImage = null;
        if (!$errors) {
            $up = save_item_image($_FILES['photo'] ?? null);
            if ($up['error'] !== null) {
                $errors[] = $up['error'];
            } else {
                $newImage = $up['file'];
            }
        }

        $oldImage = '';
        if ($id > 0) {
            $st = $pdo->prepare('SELECT image FROM items WHERE id = ?');
            $st->execute([$id]);
            $oldImage = (string)$st->fetchColumn();
        }

        if (!$errors) {
            $image = $oldImage;
            if ($newImage !== null) {
                $image = $newImage;
            } elseif (post('remove_photo') === '1') {
                $image = '';
            }
            if ($id > 0) {
                $pdo->prepare('UPDATE items SET name=?, description=?, stock=?, caution=?, price_day=?, helloasso_url=?, swikly_url=?, image=? WHERE id=?')
                    ->execute([$name, $desc, $stock, round($caution, 2), round($price, 2), $ha, $sw, $image, $id]);
                flash('ok', '« ' . $name . ' » mis à jour.');
            } else {
                $pdo->prepare('INSERT INTO items(name, description, stock, caution, price_day, helloasso_url, swikly_url, image, active, created_at) VALUES (?,?,?,?,?,?,?,?,1,?)')
                    ->execute([$name, $desc, $stock, round($caution, 2), round($price, 2), $ha, $sw, $image, date('Y-m-d H:i:s')]);
                flash('ok', '« ' . $name . ' » ajouté au catalogue.');
            }
            if ($oldImage !== '' && $oldImage !== $image) {
                delete_item_image($oldImage);
            }
            redirect('admin/items.php');
        }
        // Erreur : on rouvre le formulaire avec ce que la personne a saisi.
        $isNew = $id === 0;
        $editing = ['id' => $id, 'image' => $oldImage];
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE items SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('ok', 'Visibilité modifiée.');
        redirect('admin/items.php');
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE item_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            $pdo->prepare('UPDATE items SET active = 0 WHERE id = ?')->execute([$id]);
            flash('ok', 'Cet objet a déjà des réservations : il est masqué plutôt que supprimé, pour garder l\'historique.');
        } else {
            $st = $pdo->prepare('SELECT image FROM items WHERE id = ?');
            $st->execute([$id]);
            $img = (string)$st->fetchColumn();
            $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
            delete_item_image($img);
            flash('ok', 'Objet supprimé.');
        }
        redirect('admin/items.php');
    }
} elseif (query('new') === '1') {
    $isNew = true;
    $editing = ['id' => 0];
} elseif (query('edit') !== '') {
    $st = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $st->execute([(int)query('edit')]);
    $row = $st->fetch();
    if ($row) {
        $editing = $row;
        $form = [
            'name' => $row['name'], 'description' => $row['description'], 'stock' => (string)$row['stock'],
            'caution' => rtrim(rtrim(number_format((float)$row['caution'], 2, ',', ''), '0'), ','),
            'price_day' => rtrim(rtrim(number_format((float)$row['price_day'], 2, ',', ''), '0'), ','),
            'helloasso_url' => $row['helloasso_url'], 'swikly_url' => $row['swikly_url'],
        ];
        foreach (['caution', 'price_day'] as $k) {
            if ($form[$k] === '') {
                $form[$k] = '0';
            }
        }
    }
}

$items = $pdo->query(
    'SELECT i.*, (SELECT COUNT(*) FROM reservations r WHERE r.item_id = i.id) AS nb_resa
     FROM items i ORDER BY i.active DESC, i.name COLLATE NOCASE'
)->fetchAll();

page_header('Matériel', ['admin' => true, 'active' => 'items']);
?>
<div class="sec-head">
    <h1>Matériel</h1>
    <?php if (!$editing): ?><a class="btn primary small" href="<?= e(url('admin/items.php?new=1')) ?>">Ajouter un objet</a><?php endif; ?>
</div>

<?php if ($editing): ?>
<section class="panel">
    <h2><?= $isNew ? 'Nouvel objet' : 'Modifier l\'objet' ?></h2>
    <?php foreach ($errors as $err): ?><p class="msg bad" role="alert"><?= e($err) ?></p><?php endforeach; ?>
    <form method="post" class="form" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <div class="form-grid">
            <div class="field span2">
                <label class="lbl" for="name">Nom</label>
                <input id="name" name="name" type="text" maxlength="80" value="<?= e($form['name']) ?>" required>
            </div>
            <div class="field span2">
                <label class="lbl" for="description">Description (facultatif)</label>
                <input id="description" name="description" type="text" maxlength="200" value="<?= e($form['description']) ?>">
            </div>
            <div class="field span2">
                <?php $cur = item_image_url($editing['image'] ?? ''); ?>
                <label class="lbl" for="photo">Photo (facultatif)</label>
                <?php if ($cur !== ''): ?>
                    <img class="thumb thumb-form" src="<?= e($cur) ?>" alt="Photo actuelle de l'objet" width="400" height="300">
                    <label class="check"><input type="checkbox" name="remove_photo" value="1"> Retirer la photo actuelle</label>
                <?php endif; ?>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
                <span class="hint">JPG, PNG ou WebP. Elle est réduite automatiquement. <?= $cur !== '' ? 'Choisis un fichier pour remplacer la photo actuelle.' : '' ?></span>
            </div>
            <div class="field">
                <label class="lbl" for="stock">Exemplaires en stock</label>
                <input id="stock" name="stock" type="text" inputmode="numeric" value="<?= e($form['stock']) ?>" required>
            </div>
            <div class="field">
                <label class="lbl" for="caution">Caution (€)</label>
                <input id="caution" name="caution" type="text" inputmode="decimal" value="<?= e($form['caution']) ?>" required>
            </div>
            <div class="field">
                <label class="lbl" for="price_day">Prix par jour (€, 0 = gratuit)</label>
                <input id="price_day" name="price_day" type="text" inputmode="decimal" value="<?= e($form['price_day']) ?>" required>
            </div>
            <div class="field span2">
                <label class="lbl" for="helloasso_url">Lien HelloAsso de cet objet (facultatif)</label>
                <input id="helloasso_url" name="helloasso_url" type="url" inputmode="url" placeholder="Sinon, le lien général des Réglages est utilisé" value="<?= e($form['helloasso_url']) ?>">
            </div>
            <div class="field span2">
                <label class="lbl" for="swikly_url">Lien Swikly de cet objet (facultatif)</label>
                <input id="swikly_url" name="swikly_url" type="url" inputmode="url" placeholder="Utile si chaque caution a son propre lien" value="<?= e($form['swikly_url']) ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn primary" type="submit">Enregistrer</button>
            <a class="btn" href="<?= e(url('admin/items.php')) ?>">Annuler</a>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if (!$items && !$editing): ?>
    <div class="empty">
        <h2>Aucun objet</h2>
        <p class="muted">Ajoute les objets à prêter avec leur caution et leur stock.</p>
    </div>
<?php else: ?>
<div class="list">
<?php foreach ($items as $it): ?>
    <?php $thumb = item_image_url($it['image']); ?>
    <article class="card row row-img">
        <div class="thumb-sm"><?php if ($thumb !== ''): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy" width="72" height="72"><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?></span><?php endif; ?></div>
        <div class="row-main">
            <div class="row-title">
                <h2 class="card-title"><?= e($it['name']) ?></h2>
                <?php if (!(int)$it['active']): ?><span class="pill p-off">Masqué</span><?php endif; ?>
            </div>
            <p class="meta-line muted">Stock <?= (int)$it['stock'] ?> · Caution <?= e(money($it['caution'])) ?> · <?= (float)$it['price_day'] > 0 ? e(money($it['price_day'])) . ' / jour' : 'Gratuit' ?> · <?= e(plural((int)$it['nb_resa'], 'réservation')) ?></p>
            <?php if ($it['description'] !== ''): ?><p class="meta-line muted"><?= e($it['description']) ?></p><?php endif; ?>
        </div>
        <div class="row-act">
            <a class="btn small block" href="<?= e(url('admin/items.php?edit=' . (int)$it['id'])) ?>">Modifier</a>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn small block" type="submit"><?= (int)$it['active'] ? 'Masquer du site' : 'Réafficher' ?></button>
            </form>
            <form method="post" data-confirm="Supprimer cet objet ? S'il a déjà des réservations, il sera seulement masqué.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn small danger block" type="submit">Supprimer</button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php endif;

page_footer(['admin' => true]);
