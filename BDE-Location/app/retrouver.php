<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require_installed();

$error = '';
if (is_post()) {
    csrf_verify();
    if (too_many_attempts('find', 10)) {
        $error = 'Trop d\'essais. Réessaie dans un quart d\'heure.';
    } else {
        $ref = strtoupper(clean_line(post('ref'), 12));
        $email = clean_line(post('email'), 120);
        $st = db()->prepare('SELECT ref, token FROM reservations WHERE ref = ? AND lower(email) = lower(?)');
        $st->execute([$ref, $email]);
        $r = $st->fetch();
        if ($r) {
            redirect('reservation.php?ref=' . urlencode($r['ref']) . '&t=' . urlencode($r['token']));
        }
        record_failed_attempt('find');
        $error = 'Aucune demande ne correspond. Vérifie la référence (6 caractères) et l\'adresse e-mail utilisée.';
    }
}

page_header('Retrouver ma demande', ['active' => 'retrouver']);
?>
<section class="panel">
    <div class="panel-head">
        <h1>Retrouver ma demande</h1>
        <p class="muted">Tu as perdu ton lien de suivi ? Entre la référence de ta demande et ton adresse e-mail.</p>
    </div>
    <?php if ($error !== ''): ?><p class="msg bad" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label class="lbl" for="ref">Référence</label>
                <input id="ref" name="ref" type="text" maxlength="12" autocapitalize="characters" autocomplete="off" required>
            </div>
            <div class="field">
                <label class="lbl" for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="120" autocomplete="email" required>
            </div>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Retrouver</button></div>
    </form>
</section>
<?php
page_footer();
