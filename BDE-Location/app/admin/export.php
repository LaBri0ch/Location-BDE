<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require_installed();
require_admin();

$rows = db()->query('SELECT * FROM reservations ORDER BY start_date, id')->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="location-bde-' . date('Y-m-d') . '.csv"');

// BOM + point-virgule : s'ouvre directement et correctement dans Excel en français.
echo "\xEF\xBB\xBF";
$head = ['Réf.', 'Objet', 'Quantité', 'Début', 'Fin', 'Jours', 'Emprunteur', 'E-mail', 'Téléphone', 'Statut',
         'Location (€)', 'Caution (€)', 'Remarque', 'Note interne', 'Demandée le'];
echo implode(';', array_map('csv_cell', $head)) . "\r\n";

$dec = fn($n) => str_replace('.', ',', (string)(float)$n);
foreach ($rows as $r) {
    $line = [
        $r['ref'], $r['item_name'], $r['qty'], $r['start_date'], $r['end_date'], $r['days'], $r['borrower'],
        $r['email'], $r['phone'], statuses()[$r['status']][0] ?? $r['status'],
        $dec($r['total']), $dec($r['caution']), $r['note'], $r['admin_note'], substr($r['created_at'], 0, 10),
    ];
    echo implode(';', array_map('csv_cell', $line)) . "\r\n";
}
