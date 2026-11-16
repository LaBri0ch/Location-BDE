<?php
declare(strict_types=1);

/* Gabarit commun : en-tête, navigation, messages, pied de page.
   Options : admin (espace bureau), active (onglet courant), bare (page sans navigation). */

function site_title(): string
{
    $n = is_installed() ? setting('site_name', '') : '';
    return $n !== '' ? $n : 'Location BDE';
}

function asset_url(string $path): string
{
    $f = ROOT_DIR . '/' . $path;
    return url($path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

function admin_todo_count(): int
{
    if (!is_installed()) {
        return 0;
    }
    $n = 0;
    $st = db()->query("SELECT status, end_date FROM reservations WHERE status IN ('demandee','payee','caution','remis')");
    foreach ($st as $r) {
        if ($r['status'] !== 'remis' || $r['end_date'] < today()) {
            $n++;
        }
    }
    return $n;
}

function status_pill(array $r): string
{
    if (is_late($r)) {
        return '<span class="pill p-late">À rendre</span>';
    }
    [$label, $cls] = statuses()[$r['status']] ?? ['?', 'off'];
    return '<span class="pill p-' . e($cls) . '">' . e($label) . '</span>';
}

/** Petit buste de bonhomme (icône de compte). */
function user_icon(): string
{
    return '<svg class="user-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7z" fill="currentColor"/></svg>';
}

function page_header(string $title = '', array $opts = []): void
{
    $admin = !empty($opts['admin']);
    $bare = !empty($opts['bare']);
    $active = (string)($opts['active'] ?? '');
    $site = site_title();

    header('Content-Type: text/html; charset=utf-8');
    if ($admin) {
        header('Cache-Control: no-store');
    }

    $user = is_installed() ? current_user() : null;
    $right = [];
    if ($admin) {
        $todo = $bare ? 0 : admin_todo_count();
        $nav = [
            'suivi' => ['admin/index.php', 'Suivi' . ($todo ? ' <span class="badge">' . $todo . '</span>' : '')],
            'items' => ['admin/items.php', 'Matériel'],
            'settings' => ['admin/settings.php', 'Réglages'],
            'comptes' => ['admin/comptes.php', 'Comptes'],
        ];
        $right = [['index.php', 'Voir le site'], ['parametres.php', 'Paramètres']];
    } elseif ($user) {
        $nav = [
            'catalogue' => ['index.php', 'Matériel'],
            'compte' => ['compte.php', 'Mes demandes'],
            'parametres' => ['parametres.php', 'Paramètres'],
        ];
        if ((int)$user['is_admin'] === 1) {
            $right[] = ['admin/index.php', 'Espace bureau'];
        }
    } else {
        $nav = ['catalogue' => ['index.php', 'Matériel']];
    }
    ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($admin || $bare): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?= e($title !== '' ? $title . ' · ' . $site : $site) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
<script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div class="top-row">
      <a class="brand" href="<?= e(url($admin ? 'admin/index.php' : 'index.php')) ?>"><?= e($site) ?><?= $admin ? ' <span class="brand-sub">Bureau</span>' : '' ?></a>
      <?php if (!$bare): ?>
      <div class="top-user">
        <?php if ($user): ?>
          <form class="inline" method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button class="user-link" type="submit" title="Se déconnecter"><?= user_icon() ?><span>Déconnexion</span></button>
          </form>
        <?php else: ?>
          <a class="user-link user-link-alt" href="<?= e(url('inscription.php')) ?>">Créer un compte</a>
          <a class="user-link" href="<?= e(url('login.php')) ?>"><?= user_icon() ?><span>Se connecter</span></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!$bare): ?>
    <nav class="tabs" aria-label="Sections">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a class="tab" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
      <?php foreach ($right as $i => [$href, $label]): ?>
        <a class="tab<?= $i === 0 ? ' tab-right' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === 'r:' . $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </header>
  <main>
<?php
    foreach (flashes() as $f) {
        $cls = in_array($f['type'], ['ok', 'bad', 'warn', 'muted'], true) ? $f['type'] : 'muted';
        echo '<p class="msg ' . $cls . '" role="status">' . e($f['text']) . "</p>\n";
    }
}

function page_footer(array $opts = []): void
{
    $admin = !empty($opts['admin']);
    $bare = !empty($opts['bare']);
    ?>
  </main>
  <?php if (!$admin && !$bare): ?>
  <footer class="foot-site">
    <p class="muted small">&copy; <?= (int)date('Y') ?> <?= e(site_title()) ?>. Tous droits réservés.</p>
  </footer>
  <?php endif; ?>
</div>
</body>
</html>
<?php
}
