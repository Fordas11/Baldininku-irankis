<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

if (!is_admin()) {
    json_response(['ok' => false, 'error' => 'Likučiai prieinami tik administratoriui.'], 403);
}

// Vienas likutis (atėjus iš katalogo): ?id=12
if (isset($_GET['id'])) {
    $off = find_offcut((int) $_GET['id']);
    if ($off === null || $off['status'] !== 'available') {
        json_response(['ok' => false, 'error' => 'Šis likutis jau nebepasiekiamas.'], 404);
    }
    json_response(['ok' => true, 'offcut' => offcut_public($off)]);
}

// Tinkami likučiai detalei: ?board_id=3&length=600&width=300
$boardId = (int) ($_GET['board_id'] ?? 0);
$length = parse_number($_GET['length'] ?? '');
$width = parse_number($_GET['width'] ?? '');
if (!$boardId || !$length || !$width) {
    json_response(['ok' => true, 'offcuts' => []]);
}

json_response([
    'ok'      => true,
    'offcuts' => array_map('offcut_public', matching_offcuts($boardId, $length, $width)),
]);
