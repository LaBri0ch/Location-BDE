<?php
declare(strict_types=1);

/* ---------- Échappement, URL, redirections ---------- */

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dossier de base du site (gère une installation dans un sous-dossier). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim($dir, '/');
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    return $base = $dir;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
    return ($https ? 'https://' : 'http://') . $host . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function query(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ---------- CSRF et messages flash ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Formulaire expiré. Retourne en arrière, recharge la page et recommence.');
    }
}

function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Argent et dates (en français, sans extension intl) ---------- */

function money($n): string
{
    $n = (float)$n;
    $dec = (abs($n - round($n)) < 0.005) ? 0 : 2;
    return number_format($n, $dec, ',', "\u{202F}") . "\u{00A0}€";
}

function parse_date(string $s): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    if (!$d || $d->format('Y-m-d') !== $s) {
        return null;
    }
    return $d;
}

function today(): string
{
    return date('Y-m-d');
}

function days_between(string $start, string $end): int
{
    $a = parse_date($start);
    $b = parse_date($end);
    if (!$a || !$b) {
        return 0;
    }
    return (int)$a->diff($b)->format('%r%a') + 1;
}

function add_days(string $date, int $n): string
{
    $d = parse_date($date);
    return $d ? $d->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d') : $date;
}

function date_fr(string $iso, bool $weekday = false): string
{
    $d = parse_date($iso);
    if (!$d) {
        return '?';
    }
    static $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    static $wd = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $s = (int)$d->format('j') . ' ' . $months[(int)$d->format('n') - 1];
    return $weekday ? $wd[(int)$d->format('w')] . ' ' . $s : $s;
}

/** Lit un nombre saisi par un humain ("12,5" ou "12.5"). Retourne null si invalide. */
function parse_decimal(string $s): ?float
{
    $s = str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], trim($s));
    return ($s !== '' && is_numeric($s)) ? (float)$s : null;
}

function plural(int $n, string $word): string
{
    return $n . ' ' . $word . ($n > 1 ? 's' : '');
}

/* ---------- Divers ---------- */

function safe_url(?string $u): string
{
    $u = trim((string)$u);
    if ($u === '' || !filter_var($u, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string)parse_url($u, PHP_URL_SCHEME));
    return ($scheme === 'https' || $scheme === 'http') ? $u : '';
}

function clean_line(string $s, int $max): string
{
    $s = preg_replace('/[\r\n\t]+/', ' ', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function make_ref(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 6; $i++) {
        $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $s;
}

/** Cellule CSV sûre pour Excel (neutralise les formules). */
function csv_cell($v): string
{
    $s = (string)($v ?? '');
    if ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) {
        $s = "'" . $s;
    }
    return '"' . str_replace('"', '""', $s) . '"';
}
