<?php
declare(strict_types=1);

/* ---------- Adresses e-mail ---------- */

function normalize_email(string $e): string
{
    return mb_strtolower(trim($e));
}

function valid_email(string $e): bool
{
    return $e !== '' && mb_strlen($e) <= 120 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/** Domaines autorisés à créer un compte. Tableau vide = tout le monde. */
function allowed_domains(): array
{
    $raw = setting('email_domains', 'ipsa.fr');
    if ($raw === '*') {
        return [];
    }
    $out = [];
    foreach (preg_split('/[\s,;]+/', mb_strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $d) {
        $d = ltrim($d, '@');
        if ($d !== '') {
            $out[] = $d;
        }
    }
    return $out;
}

function email_domain_ok(string $email): bool
{
    $domains = allowed_domains();
    if (!$domains) {
        return true;
    }
    $at = strrpos($email, '@');
    $host = $at === false ? '' : substr($email, $at + 1);
    foreach ($domains as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) {
            return true;
        }
    }
    return false;
}

function domains_label(): string
{
    return implode(' ou ', array_map(fn($d) => '@' . $d, allowed_domains()));
}

/* ---------- Session ---------- */

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $cached = null;
    }
    $st = db()->prepare('SELECT id, email, name, is_admin, phone, notify FROM users WHERE id = ?');
    $st->execute([(int)$id]);
    $row = $st->fetch();
    if (!$row) {
        unset($_SESSION['user_id']);
        return $cached = null;
    }
    return $cached = $row;
}

function current_admin(): ?array
{
    $u = current_user();
    return ($u && (int)$u['is_admin'] === 1) ? $u : null;
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    unset($_SESSION['csrf']);
    db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
}

function request_next(): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $bp = base_path();
    if ($bp !== '' && str_starts_with($uri, $bp)) {
        $uri = substr($uri, strlen($bp));
    }
    return ltrim($uri, '/');
}

/** N'accepte qu'une page interne du site (jamais une adresse externe). */
function safe_next(string $n): string
{
    $n = ltrim($n, '/');
    if ($n === '' || strlen($n) > 200) {
        return '';
    }
    return preg_match('#^[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $n) ? $n : '';
}

function after_login_target(array $user, string $next = ''): string
{
    $next = safe_next($next);
    if ($next !== '') {
        return $next;
    }
    return (int)$user['is_admin'] === 1 ? 'admin/index.php' : 'compte.php';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php?next=' . urlencode(request_next()));
    }
    header('Cache-Control: no-store');
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ((int)$u['is_admin'] !== 1) {
        http_response_code(403);
        page_header('Accès réservé', ['active' => 'compte']);
        echo '<div class="empty"><h1>Espace réservé au bureau</h1><p class="muted">Ton compte n\'a pas les droits d\'administration. Si tu fais partie du bureau, demande à un autre membre de t\'inviter.</p>'
            . '<a class="btn primary" href="' . e(url('compte.php')) . '">Mon compte</a></div>';
        page_footer();
        exit;
    }
    return $u;
}

/* ---------- Limitation des essais ---------- */

function too_many_attempts(string $bucket = 'login', int $max = 8): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([$bucket . ':' . client_ip(), time() - 900]);
    return (int)$st->fetchColumn() >= $max;
}

function record_failed_attempt(string $bucket = 'login'): void
{
    $pdo = db();
    $pdo->prepare('INSERT INTO login_attempts(ip, at) VALUES (?, ?)')->execute([$bucket . ':' . client_ip(), time()]);
    $pdo->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 86400]);
}

/* ---------- Connexion ---------- */

/** Retourne l'utilisateur connecté, ou null si l'e-mail ou le mot de passe est faux. */
function attempt_login(string $email, string $password): ?array
{
    $email = normalize_email($email);
    $st = db()->prepare('SELECT id, password_hash, is_admin FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch();
    // Même coût de calcul que le compte existe ou non.
    $hash = $row['password_hash'] ?? password_hash('inexistant', PASSWORD_DEFAULT);
    if (!password_verify($password, $hash) || !$row) {
        record_failed_attempt();
        return null;
    }
    login_user((int)$row['id']);
    return $row;
}

function valid_password(string $pw): ?string
{
    if (mb_strlen($pw) < 10) {
        return 'Le mot de passe doit faire au moins 10 caractères.';
    }
    return null;
}

/* ---------- Inscription et invitations du bureau ---------- */

/**
 * Cas d'une base créée avant l'arrivée des comptes : il y a d'anciens
 * identifiants admin mais aucun compte. Le premier compte créé devient alors admin.
 */
function legacy_setup_needed(): bool
{
    $pdo = db();
    $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'admins'")->fetchColumn();
    if (!$has) {
        return false;
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0
        && (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
}

function make_invite_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $c = '';
    for ($i = 0; $i < 10; $i++) {
        $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return substr($c, 0, 5) . '-' . substr($c, 5);
}

function normalize_code(string $c): string
{
    return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $c));
}

/**
 * Crée un compte. Si l'adresse a été invitée par le bureau, le bon code d'invitation
 * est obligatoire : c'est ce qui empêche quelqu'un de s'inscrire avec l'adresse d'un
 * futur admin pour en prendre les droits.
 *
 * @return array{ok:bool, errors:list<string>, id?:int, is_admin?:bool}
 */
function register_user(string $name, string $email, string $pw, string $pw2, string $inviteCode, bool $skipDomain = false): array
{
    $pdo = db();
    $errors = [];
    $name = clean_line($name, 80);
    $email = normalize_email($email);

    if (mb_strlen($name) < 2) {
        $errors[] = 'Indique ton prénom et ton nom.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Indique une adresse e-mail valide.';
    }
    if ($err = valid_password($pw)) {
        $errors[] = $err;
    }
    if ($pw !== $pw2) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $first = legacy_setup_needed();
    $st = $pdo->prepare('SELECT code_hash FROM admin_invites WHERE email = ?');
    $st->execute([$email]);
    $invite = $first ? false : $st->fetch();

    if (!$first && !$skipDomain && !$invite && !email_domain_ok($email)) {
        return ['ok' => false, 'errors' => ['Cette adresse n\'est pas autorisée. Utilise ton adresse e-mail de l\'école (' . domains_label() . ').']];
    }

    $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) {
        return ['ok' => false, 'errors' => ['Un compte existe déjà avec cette adresse e-mail. Connecte-toi, ou demande au bureau de réinitialiser ton mot de passe.']];
    }

    $isAdmin = $first;
    if ($invite) {
        $code = normalize_code($inviteCode);
        if ($code === '' || !password_verify($code, (string)$invite['code_hash'])) {
            record_failed_attempt('register');
            return ['ok' => false, 'errors' => ['Cette adresse est réservée au bureau. Saisis le code d\'invitation qu\'on t\'a remis (rubrique « Tu es membre du bureau ? »).']];
        }
        $isAdmin = true;
    }

    try {
        $pdo->exec('BEGIN IMMEDIATE');
        $pdo->prepare('INSERT INTO users(email, name, password_hash, is_admin, created_at) VALUES (?,?,?,?,?)')
            ->execute([$email, $name, password_hash($pw, PASSWORD_DEFAULT), $isAdmin ? 1 : 0, date('Y-m-d H:i:s')]);
        $id = (int)$pdo->lastInsertId();
        if ($invite) {
            $pdo->prepare('DELETE FROM admin_invites WHERE email = ?')->execute([$email]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $ignored) {
        }
        if ($ex instanceof PDOException && (string)$ex->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['Un compte existe déjà avec cette adresse e-mail. Connecte-toi.']];
        }
        error_log('register_user: ' . $ex->getMessage());
        return ['ok' => false, 'errors' => ['Impossible de créer le compte pour le moment. Réessaie dans un instant.']];
    }
    return ['ok' => true, 'errors' => [], 'id' => $id, 'is_admin' => $isAdmin];
}
