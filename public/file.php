<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$stmt = db()->prepare('SELECT f.*, o.token FROM order_files f JOIN orders o ON o.id = f.order_id WHERE f.id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$file = $stmt->fetch();
$token = $_GET['t'] ?? '';

if (!$file || !(is_admin() || (is_string($token) && hash_equals($file['token'], $token)))) {
    http_response_code(404);
    exit('Failas nerastas.');
}

$path = config('upload_dir') . '/' . (int) $file['order_id'] . '/' . basename($file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Failas nerastas.');
}

header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['original_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
