<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

// Kainos skaičiuoklė: POST {items: [...], delivery, packaging} -> kaina su išdėstymu ant plokščių.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Netinkama užklausa.'], 405);
}
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in) || !is_array($in['items'] ?? null) || count($in['items']) > config('max_items') + 50) {
    json_response(['ok' => false, 'error' => 'Netinkami duomenys.'], 400);
}

$quote = compute_quote(
    quote_items_from_input($in['items']),
    (string) ($in['delivery'] ?? 'pickup'),
    (string) ($in['packaging'] ?? 'standard'),
);
json_response(['ok' => true] + $quote);
