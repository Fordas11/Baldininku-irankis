<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

$order = find_order((int) ($_GET['id'] ?? 0));
$format = ($_GET['format'] ?? '') === 'om' ? 'om' : 'ardis';
if ($order === null) {
    http_response_code(404);
    exit('Užsakymas nerastas.');
}

$name = $order['internal_order_no'] !== '' ? preg_replace('/[^A-Za-z0-9_-]/', '_', $order['internal_order_no']) : 'web-' . $order['id'];
send_csv($format . '-' . $name . '.csv', export_rows($order, $format));
