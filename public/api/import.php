<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Sesija pasibaigė. Atnaujinkite puslapį.'], 400);
}

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024) {
    json_response(['ok' => false, 'error' => 'Nepavyko įkelti failo.'], 400);
}

try {
    $data = import_cut_form_xlsx($file['tmp_name']);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => 'Nepavyko nuskaityti – įkelkite pjovimo užsakymo formą (.xlsx).'], 422);
}

json_response(['ok' => true] + $data);
