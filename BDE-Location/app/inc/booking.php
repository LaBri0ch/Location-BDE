<?php
declare(strict_types=1);

/* ---------- Statuts ---------- */

const STATUS_ORDER = ['demandee', 'payee', 'caution', 'remis', 'rendu'];
const STATUS_BLOCKING = ['demandee', 'payee', 'caution', 'remis'];

function statuses(): array
{
    return [
        'demandee' => ['Demandée', 'wait'],
        'payee'    => ['Réglée', 'info'],
        'caution'  => ['Caution reçue', 'info'],   // ancien statut, gardé pour les anciennes demandes
        'remis'    => ['Remis', 'out'],
        'rendu'    => ['Rendu', 'ok'],
        'annulee'  => ['Annulée', 'off'],
    ];
}

function next_status_labels(): array
{
    return ['payee' => 'Paiement et caution reçus', 'remis' => 'Objet remis', 'rendu' => 'Objet rendu'];
}

function is_late(array $r): bool
{
    return $r['status'] === 'remis' && $r['end_date'] < today();
}

/** Étapes réellement nécessaires : location et caution se règlent en même temps (une seule étape). */
function steps_for(array $r): array
{
    $s = ['demandee' => 'Demande'];
    if ((float)$r['total'] > 0 || (float)$r['caution'] > 0) {
        $s['payee'] = 'Paiement et caution';
    }
    $s['remis'] = 'Remis';
    $s['rendu'] = 'Rendu';
    return $s;
}

function next_status(array $r): ?string
{
    $seq = array_keys(steps_for($r));
    $cur = array_search($r['status'], STATUS_ORDER, true);
    if ($cur === false) {
        return null;
    }
    foreach ($seq as $s) {
        if (array_search($s, STATUS_ORDER, true) > $cur) {
            return $s;
        }
    }
    return null;
}

/* ---------- Disponibilité ---------- */

function blocking_in(): string
{
    return "'" . implode("','", STATUS_BLOCKING) . "'";
}

/** Date de fin « effective » : un objet pas encore rendu reste pris tant qu'il n'est pas revenu. */
function eff_end_sql(): string
{
    $t = today();
    return "CASE WHEN status = 'remis' AND end_date < '" . $t . "' THEN '" . $t . "' ELSE end_date END";
}

/**
 * Nombre d'exemplaires pris chaque jour entre $from et $to (dates incluses).
 * Seuls les jours où au moins un exemplaire est pris sont présents dans le tableau.
 */
function usage_by_day(int $itemId, string $from, string $to): array
{
    $st = db()->prepare(
        'SELECT qty, start_date, (' . eff_end_sql() . ') AS end_date FROM reservations
         WHERE item_id = ? AND status IN (' . blocking_in() . ') AND start_date <= ? AND (' . eff_end_sql() . ') >= ?'
    );
    $st->execute([$itemId, $to, $from]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $a = max($r['start_date'], $from);
        $b = min($r['end_date'], $to);
        $n = days_between($a, $b);
        for ($i = 0; $i < $n; $i++) {
            $d = add_days($a, $i);
            $out[$d] = ($out[$d] ?? 0) + (int)$r['qty'];
        }
    }
    ksort($out);
    return $out;
}

/**
 * Retourne null si la période est libre, sinon un tableau décrivant le problème.
 * La disponibilité est calculée jour par jour : le stock doit suffire chaque jour.
 */
function item_conflict(PDO $pdo, array $item, string $start, string $end, int $qty, ?int $ignoreId = null): ?array
{
    $stock = max(0, (int)$item['stock']);
    if ($qty > $stock) {
        return ['msg' => 'Seulement ' . plural($stock, 'exemplaire') . ' en stock.'];
    }
    $st = $pdo->prepare(
        'SELECT qty, start_date, (' . eff_end_sql() . ') AS end_date FROM reservations
         WHERE item_id = ? AND status IN (' . blocking_in() . ')
           AND start_date <= ? AND (' . eff_end_sql() . ') >= ?' . ($ignoreId ? ' AND id != ' . (int)$ignoreId : '')
    );
    $st->execute([(int)$item['id'], $end, $start]);
    $rows = $st->fetchAll();
    if (!$rows) {
        return null;
    }
    $n = days_between($start, $end);
    for ($i = 0; $i < $n; $i++) {
        $d = add_days($start, $i);
        $used = 0;
        foreach ($rows as $r) {
            if ($r['start_date'] <= $d && $r['end_date'] >= $d) {
                $used += (int)$r['qty'];
            }
        }
        if ($used + $qty > $stock) {
            return ['date' => $d, 'used' => $used, 'stock' => $stock];
        }
    }
    return null;
}

function conflict_message(array $c): string
{
    if (isset($c['msg'])) {
        return $c['msg'];
    }
    return 'Plus de disponibilité le ' . date_fr($c['date'], true) . ' (' . $c['used'] . ' sur ' . $c['stock']
        . ' déjà réservé' . ($c['used'] > 1 ? 's' : '') . '). Essaie d\'autres dates.';
}

/** Périodes déjà prises pour un objet, affichées sur la page de réservation. */
function upcoming_blocks(int $itemId): array
{
    $st = db()->prepare(
        'SELECT start_date, (' . eff_end_sql() . ') AS end_date, qty FROM reservations
         WHERE item_id = ? AND status IN (' . blocking_in() . ') AND (' . eff_end_sql() . ') >= ?
         ORDER BY start_date LIMIT 8'
    );
    $st->execute([$itemId, today()]);
    return $st->fetchAll();
}

function item_available_today(array $item): array
{
    $st = db()->prepare(
        'SELECT COALESCE(SUM(qty), 0) FROM reservations
         WHERE item_id = ? AND status IN (' . blocking_in() . ') AND start_date <= ? AND (' . eff_end_sql() . ') >= ?'
    );
    $t = today();
    $st->execute([(int)$item['id'], $t, $t]);
    $used = (int)$st->fetchColumn();
    return ['used' => $used, 'free' => max(0, (int)$item['stock'] - $used)];
}

/* ---------- Création et changement de statut ---------- */

function max_days(): int
{
    return max(1, min(365, (int)setting('max_days', '30')));
}

/**
 * Valide et enregistre une demande au nom de l'utilisateur connecté.
 * Retourne ['ok'=>true,'reservation'=>[...]] ou ['ok'=>false,'errors'=>[...]].
 */
function create_reservation(array $item, array $in, array $user): array
{
    $errors = [];
    $start = (string)($in['start'] ?? '');
    $end = (string)($in['end'] ?? '');
    $qty = max(1, (int)($in['qty'] ?? 1));
    $name = (string)$user['name'];
    $email = (string)$user['email'];
    $phone = clean_line((string)($in['phone'] ?? ''), 30);
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 300);

    $s = parse_date($start);
    $e = parse_date($end);
    $days = 0;
    if (!$s || !$e) {
        $errors[] = 'Choisis une date de début et une date de fin.';
    } elseif ($start < today()) {
        $errors[] = 'La date de début est déjà passée.';
    } elseif ($end < $start) {
        $errors[] = 'La date de fin est avant la date de début.';
    } else {
        $days = days_between($start, $end);
        if ($days > max_days()) {
            $errors[] = 'La durée maximale est de ' . plural(max_days(), 'jour') . '. Pour plus long, contacte le bureau.';
        }
    }
    if ($phone !== '' && !preg_match('/^[0-9+().\s-]{6,30}$/', $phone)) {
        $errors[] = 'Le numéro de téléphone n\'est pas valide.';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    // Limite anti-abus : pas plus de 5 demandes en attente par compte.
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status = 'demandee'");
    $st->execute([(int)$user['id']]);
    if ((int)$st->fetchColumn() >= 5) {
        return ['ok' => false, 'errors' => ['Tu as déjà plusieurs demandes en attente. Attends que le bureau les traite.']];
    }

    $total = round((float)$item['price_day'] * $days * $qty, 2);
    $caution = round((float)$item['caution'] * $qty, 2);
    $now = date('Y-m-d H:i:s');

    try {
        $pdo->exec('BEGIN IMMEDIATE');
        $fresh = $pdo->prepare('SELECT * FROM items WHERE id = ? AND active = 1');
        $fresh->execute([(int)$item['id']]);
        $item = $fresh->fetch();
        if (!$item) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'errors' => ['Cet objet n\'est plus disponible à la location.']];
        }
        $c = item_conflict($pdo, $item, $start, $end, $qty);
        if ($c) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'errors' => [conflict_message($c)]];
        }
        $ins = $pdo->prepare(
            'INSERT INTO reservations
             (ref, token, user_id, item_id, item_name, qty, start_date, end_date, days, borrower, email, phone, note,
              total, caution, helloasso_url, swikly_url, status, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ref = make_ref();
        $token = bin2hex(random_bytes(16));
        $ins->execute([
            $ref, $token, (int)$user['id'], (int)$item['id'], $item['name'], $qty, $start, $end, $days, $name, $email, $phone, $note,
            $total, $caution, $item['helloasso_url'], $item['swikly_url'], 'demandee', $now, $now,
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO events(reservation_id, status, by_user, at) VALUES (?,?,?,?)')
            ->execute([$id, 'demandee', $name, $now]);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $ignored) {
        }
        error_log('create_reservation: ' . $ex->getMessage());
        return ['ok' => false, 'errors' => ['Impossible d\'enregistrer la demande pour le moment. Réessaie dans un instant.']];
    }

    $r = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
    $r->execute([$id]);
    return ['ok' => true, 'reservation' => $r->fetch()];
}

function change_status(int $id, string $status, string $by): bool
{
    if (!isset(statuses()[$status])) {
        return false;
    }
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $st = $pdo->prepare('UPDATE reservations SET status = ?, updated_at = ? WHERE id = ?');
    $st->execute([$status, $now, $id]);
    if ($st->rowCount() === 0) {
        return false;
    }
    $pdo->prepare('INSERT INTO events(reservation_id, status, by_user, at) VALUES (?,?,?,?)')->execute([$id, $status, $by, $now]);
    return true;
}

/** Liens de paiement d'une réservation : lien de l'objet s'il existe, sinon lien général. */
function pay_links(array $r): array
{
    return [
        'helloasso' => safe_url($r['helloasso_url'] ?? '') ?: safe_url(setting('helloasso_url')),
        'swikly'    => safe_url($r['swikly_url'] ?? '') ?: safe_url(setting('swikly_url')),
    ];
}
