<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Netinkama užklausa.'], 405);
}
if (!check_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'message' => 'Sesija pasibaigė. Atnaujinkite puslapį (duomenys išliks).'], 400);
}
// Robotų spąstai: tikras naudotojas šio lauko nemato.
if (!empty($_POST['website'])) {
    json_response(['ok' => false, 'message' => 'Užklausa atmesta.'], 400);
}

$input = json_decode((string) ($_POST['order'] ?? ''), true);
if (!is_array($input)) {
    json_response(['ok' => false, 'message' => 'Netinkami užsakymo duomenys.'], 400);
}

[$order, $errors, $itemErrors] = validate_order($input);

$uploads = $_FILES['files'] ?? ['name' => []];
$uploadErrors = check_uploads($uploads);
if ($uploadErrors) {
    $errors['files'] = implode(' ', $uploadErrors);
}

if (!$errors && !$itemErrors) {
    $order['quote'] = compute_quote($order['items'], $order['delivery'], $order['packaging']);
}

if ($errors || $itemErrors) {
    json_response(['ok' => false, 'errors' => $errors, 'itemErrors' => (object) $itemErrors], 422);
}

try {
    $saved = save_order($order);
} catch (OffcutTakenException $ex) {
    json_response(['ok' => false, 'message' => $ex->getMessage()], 409);
}
store_uploads($saved['id'], $uploads);
notify_new_order(['id' => $saved['id']] + $order);

json_response([
    'ok'  => true,
    'id'  => $saved['id'],
    'total' => $order['quote']['total'],
    'url' => 'order.php?id=' . $saved['id'] . '&t=' . $saved['token'],
]);
