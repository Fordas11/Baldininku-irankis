<?php
declare(strict_types=1);

/**
 * Plokščių katalogas (sandėlis). Vienas įrašas – konkreti prekė: gamintojas + dekoro kodas +
 * paviršiaus struktūra + storis, su plokštės matmenimis, kaina ir likučiu sandėlyje.
 */

/** CSV stulpeliai (importas ir eksportas). */
const BOARD_CSV_COLUMNS = ['tipas', 'gamintojas', 'kodas', 'pavadinimas', 'struktura', 'storis', 'ilgis', 'plotis',
    'tekstura', 'kaina_be_pvm', 'sandelyje', 'aktyvi'];

/**
 * Pavyzdiniai įrašai, kad sistema veiktų iš karto: plokštės (tas pats dekoro kodas – su keliomis
 * struktūromis ir storiais) ir keli likučiai sandėlyje. Kainos ir kiekiai – PAVYZDINIAI:
 * prieš naudojant pakeiskite tikrais (admin → Plokštės → CSV importas, admin → Likučiai).
 */
function seed_boards(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM boards')->fetchColumn() > 0) {
        return;
    }
    $rows = [
        // tipas, gamintojas, kodas, pavadinimas, struktūra, storis, lapo ilgis, plotis, kaina €/lapą be PVM, sandėlyje, tekstūra
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'MA', 10, 2800, 2100, 38.90, 14, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'MA', 16, 2800, 2100, 47.80, 20, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'MA', 18, 2800, 2100, 53.45, 60, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'MA', 25, 2800, 2100, 72.10, 8, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'CW', 18, 2800, 2100, 53.45, 22, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'MT', 18, 2800, 2100, 53.45, 18, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'SG', 18, 2800, 2100, 53.45, 25, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'SG', 25, 2800, 2100, 72.10, 4, 0],
        ['LMDP', 'Woodeco', 'PB0050', 'Balta', 'SO', 18, 2800, 2100, 56.20, 0, 0],
        ['LMDP', 'Woodeco', 'PU1203', 'Antracitas', 'MT', 18, 2800, 2100, 61.30, 12, 0],
        ['LMDP', 'Woodeco', 'PU1203', 'Antracitas', 'SG', 16, 2800, 2100, 55.90, 6, 0],
        ['LMDP', 'Woodeco', 'PU1203', 'Antracitas', 'SG', 18, 2800, 2100, 61.30, 15, 0],
        ['LMDP', 'Woodeco', 'PU1510', 'Akmens pilka', 'MT', 18, 2800, 2100, 61.30, 9, 0],
        ['LMDP', 'Woodeco', 'PU2200', 'Avokadas', 'SG', 18, 2800, 2100, 64.80, 3, 0],
        ['LMDP', 'Woodeco', 'PD7020', 'Alksnis kalnų', 'CW', 18, 2800, 2100, 58.60, 7, 1],
        ['LMDP', 'Woodeco', 'PD3016', 'Ąžuolas Artisan', 'PU', 18, 2800, 2100, 66.40, 16, 1],
        ['LMDP', 'Woodeco', 'PD3016', 'Ąžuolas Artisan', 'PU', 25, 2800, 2100, 88.90, 4, 1],
        ['LMDP', 'Woodeco', 'PD3102', 'Ąžuolas Baltic Dune', 'RW', 18, 2800, 2100, 68.20, 10, 1],
        ['LMDP', 'Woodeco', 'PD3014', 'Ąžuolas grafitinis', 'LN', 18, 2800, 2100, 64.50, 5, 1],
        ['LMDP', 'Pfleiderer', 'W10003', 'Balta', 'MP', 18, 2655, 2100, 46.10, 12, 0],
        ['LMDP', 'Pfleiderer', 'W10003', 'Balta', 'SM', 18, 2655, 2100, 46.10, 8, 0],
        ['LMDP', 'Pfleiderer', 'U12290', 'Antracitas blizgi', 'XG', 19, 2800, 2100, 98.70, 2, 0],
        ['LMDP', 'Kronospan', 'K003', 'Ąžuolas Craft auksinis', 'PW', 18, 2800, 2070, 63.90, 14, 1],
        ['LMDP', 'Kronospan', 'K003', 'Ąžuolas Craft auksinis', 'PW', 25, 2800, 2070, 84.30, 5, 1],
        ['LMDP', 'Kronospan', 'K003', 'Ąžuolas Craft auksinis', 'PW', 36, 2800, 2070, 129.00, 2, 1],
        ['LMDP', 'Kronospan', 'K082', 'Ąžuolas Bourbon', 'PW', 18, 2800, 2070, 63.90, 6, 1],
        ['LMDP', 'Kronospan', 'U164', 'Antracitas', 'PE', 18, 2800, 2070, 57.40, 11, 0],
        ['LMDP', 'Kronospan', 'U164', 'Antracitas', 'SU', 18, 2800, 2070, 59.80, 0, 0],
        ['LMDP', 'Swiss Krono', 'D3276', 'Ąžuolas Ankona', 'MX', 18, 2800, 2070, 62.70, 8, 1],
        ['LMDP', 'Swiss Krono', 'D4031', 'Ąžuolas Havana', 'CL', 18, 2800, 2070, 60.90, 6, 1],
        ['LMDP', 'Sonae Arauco', 'M6252', 'Ąžuolas Baris', 'FLW', 18, 2800, 2070, 67.30, 9, 1],
        ['LMDP', 'Sonae Arauco', 'L5212', 'Anglis', 'SC', 18, 2800, 2070, 62.10, 7, 0],
        ['MDF', 'Kronospan', 'MDF', 'Neapdirbta MDF', '', 16, 2800, 2070, 38.60, 20, 0],
        ['MDF', 'Kronospan', 'MDF', 'Neapdirbta MDF', '', 19, 2800, 2070, 44.20, 15, 0],
    ];
    $stmt = $pdo->prepare('INSERT INTO boards (type, manufacturer, code, name, structure, thickness, sheet_length, sheet_width,
        grain, price_sheet, stock, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($rows as [$type, $man, $code, $name, $struct, $th, $len, $wid, $price, $stock, $grain]) {
        $stmt->execute([$type, $man, $code, $name, $struct, $th, $len, $wid, $grain, $price, $stock]);
    }
    seed_offcuts($pdo);
}

/** Pavyzdiniai likučiai sandėlyje (matomi tik administratoriui). */
function seed_offcuts(PDO $pdo): void
{
    $rows = [
        // kodas, struktūra, storis, ilgis, plotis, vieta, kiekis, vidinė pastaba
        ['PB0050', 'MA', 18, 1850, 760, 'A-1', 2, ''],
        ['PB0050', 'MA', 18, 1200, 590, 'A-1', 1, ''],
        ['PB0050', 'MA', 18, 720, 450, 'A-2', 3, ''],
        ['PB0050', 'MA', 16, 2100, 380, 'A-2', 1, 'Vienas kraštas su įbrėžimu'],
        ['PB0050', 'SG', 18, 1400, 820, 'A-3', 1, ''],
        ['PU1203', 'SG', 18, 2350, 610, 'B-1', 1, ''],
        ['PU1203', 'MT', 18, 900, 560, 'B-1', 2, ''],
        ['PD3016', 'PU', 18, 2600, 480, 'B-2', 1, 'Tekstūra išilgai ilgio'],
        ['PD3016', 'PU', 18, 1100, 700, 'B-2', 1, ''],
        ['PD3102', 'RW', 18, 1600, 350, 'B-3', 2, ''],
        ['K003', 'PW', 25, 1950, 640, 'C-1', 1, ''],
        ['K003', 'PW', 36, 1300, 620, 'C-1', 1, 'Stalviršio atraiža'],
        ['U164', 'PE', 18, 1750, 900, 'C-2', 1, ''],
        ['M6252', 'FLW', 18, 800, 600, 'C-3', 2, ''],
        ['MDF', '', 19, 1500, 1000, 'D-1', 1, ''],
    ];
    $find = $pdo->prepare('SELECT * FROM boards WHERE code = ? AND structure = ? AND thickness = ?');
    $insert = $pdo->prepare('INSERT INTO offcuts (board_id, board, thickness, length, width, location, admin_note,
        status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "available", ?, ?)');
    $now = date('Y-m-d H:i:s');
    foreach ($rows as [$code, $struct, $th, $len, $wid, $location, $count, $note]) {
        $find->execute([$code, $struct, $th]);
        $board = $find->fetch(PDO::FETCH_ASSOC);
        if (!$board) {
            continue;
        }
        for ($i = 0; $i < $count; $i++) {
            $insert->execute([$board['id'], board_label($board), $th, $len, $wid, $location, $note, $now, $now]);
        }
    }
}

/** Plokštės pavadinimas, naudojamas užsakyme ir likučiuose, pvz. „Egger W1000 ST9“. */
function board_label(array $b): string
{
    return trim(preg_replace('/\s+/', ' ', $b['manufacturer'] . ' ' . $b['code'] . ' ' . $b['structure']));
}

function find_board(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $stmt = db()->prepare('SELECT * FROM boards WHERE id = ?');
        $stmt->execute([$id]);
        $cache[$id] = $stmt->fetch() ?: null;
    }
    return $cache[$id];
}

function list_boards(bool $activeOnly = false, string $search = ''): array
{
    $sql = 'SELECT * FROM boards WHERE 1 = 1';
    $args = [];
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    if ($search !== '') {
        $sql .= ' AND (code LIKE ? OR name LIKE ? OR manufacturer LIKE ? OR structure LIKE ?)';
        array_push($args, ...array_fill(0, 4, '%' . $search . '%'));
    }
    $stmt = db()->prepare($sql . ' ORDER BY manufacturer, code, structure, thickness');
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** Katalogas naršyklei: tik aktyvios plokštės, be vidinių laukų. */
function client_boards(): array
{
    return array_map(static fn (array $b): array => [
        'id'        => (int) $b['id'],
        'type'      => $b['type'],
        'man'       => $b['manufacturer'],
        'code'      => $b['code'],
        'name'      => $b['name'],
        'structure' => $b['structure'],
        'thickness' => 0 + $b['thickness'],
        'sheet'     => [0 + $b['sheet_length'], 0 + $b['sheet_width']],
        'grain'     => (bool) $b['grain'],
        'stock'     => (int) $b['stock'],
        'label'     => board_label($b),
    ], list_boards(true));
}

function validate_board(array $in): array
{
    $num = static fn (string $k): ?float => parse_number($in[$k] ?? '');
    $b = [
        'type'         => str_field($in, 'type', 30) ?: 'LMDP',
        'manufacturer' => str_field($in, 'manufacturer', 60),
        'code'         => str_field($in, 'code', 40),
        'name'         => str_field($in, 'name', 120),
        'structure'    => str_field($in, 'structure', 20),
        'thickness'    => $num('thickness'),
        'sheet_length' => $num('sheet_length'),
        'sheet_width'  => $num('sheet_width'),
        'grain'        => !empty($in['grain']) && $in['grain'] !== '0' ? 1 : 0,
        'price_sheet'  => $num('price_sheet') ?? 0.0,
        'stock'        => (int) ($num('stock') ?? 0),
        'active'       => !isset($in['active']) || ($in['active'] !== '0' && $in['active'] !== '') ? 1 : 0,
    ];
    $errors = [];
    if ($b['code'] === '') {
        $errors['code'] = 'Nurodykite dekoro kodą.';
    }
    if ($b['thickness'] === null || $b['thickness'] <= 0 || $b['thickness'] > 100) {
        $errors['thickness'] = 'Netinkamas storis.';
    }
    if ($b['sheet_length'] === null || $b['sheet_length'] < 100 || $b['sheet_width'] === null || $b['sheet_width'] < 100) {
        $errors['sheet'] = 'Nurodykite plokštės matmenis.';
    }
    if ($b['price_sheet'] < 0) {
        $errors['price_sheet'] = 'Kaina negali būti neigiama.';
    }
    return [$b, $errors];
}

/** Įrašo plokštę; jei $id nenurodytas, ieško tokios pat (kodas + struktūra + storis + gamintojas) ir ją atnaujina. */
function save_board(array $b, ?int $id = null): int
{
    if (!$id) {
        $stmt = db()->prepare('SELECT id FROM boards WHERE LOWER(manufacturer) = LOWER(?) AND LOWER(code) = LOWER(?)
            AND LOWER(structure) = LOWER(?) AND thickness = ?');
        $stmt->execute([$b['manufacturer'], $b['code'], $b['structure'], $b['thickness']]);
        $id = (int) $stmt->fetchColumn() ?: null;
    }
    $cols = ['type', 'manufacturer', 'code', 'name', 'structure', 'thickness', 'sheet_length', 'sheet_width', 'grain',
        'price_sheet', 'stock', 'active'];
    $values = array_map(static fn ($c) => $b[$c], $cols);
    if ($id) {
        $stmt = db()->prepare('UPDATE boards SET ' . implode(' = ?, ', $cols) . ' = ? WHERE id = ?');
        $stmt->execute([...$values, $id]);
        return $id;
    }
    $stmt = db()->prepare('INSERT INTO boards (' . implode(', ', $cols) . ') VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')');
    $stmt->execute($values);
    return (int) db()->lastInsertId();
}

/** Plokštės, naudotos užsakymuose ar likučiuose, netrinamos – tik išjungiamos. */
function delete_board(int $id): bool
{
    $stmt = db()->prepare('SELECT (SELECT COUNT(*) FROM order_items WHERE board_id = ?) + (SELECT COUNT(*) FROM offcuts WHERE board_id = ?)');
    $stmt->execute([$id, $id]);
    if ((int) $stmt->fetchColumn() > 0) {
        db()->prepare('UPDATE boards SET active = 0 WHERE id = ?')->execute([$id]);
        return false;
    }
    db()->prepare('DELETE FROM boards WHERE id = ?')->execute([$id]);
    return true;
}

/** CSV importas (skirtukas ; arba ,). Grąžina [įrašyta, klaidos]. */
function import_boards_csv(string $path): array
{
    $text = (string) file_get_contents($path);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $lines = preg_split('/\r\n|\n|\r/', trim($text));
    $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
    $header = array_map(static fn ($h) => mb_strtolower(trim($h)), str_getcsv(array_shift($lines) ?? '', $delimiter, '"', ''));
    $missing = array_diff(['kodas', 'storis', 'kaina_be_pvm'], $header);
    if ($missing) {
        return [0, ['Trūksta stulpelių: ' . implode(', ', $missing) . '. Reikalingi: ' . implode(';', BOARD_CSV_COLUMNS)]];
    }
    $saved = 0;
    $errors = [];
    foreach ($lines as $n => $line) {
        if (trim($line) === '') {
            continue;
        }
        $row = array_combine($header, array_pad(str_getcsv($line, $delimiter, '"', ''), count($header), ''));
        [$b, $err] = validate_board([
            'type' => $row['tipas'] ?? '', 'manufacturer' => $row['gamintojas'] ?? '', 'code' => $row['kodas'],
            'name' => $row['pavadinimas'] ?? '', 'structure' => $row['struktura'] ?? '', 'thickness' => $row['storis'],
            'sheet_length' => $row['ilgis'] ?? '2800', 'sheet_width' => $row['plotis'] ?? '2070',
            'grain' => $row['tekstura'] ?? '1', 'price_sheet' => $row['kaina_be_pvm'], 'stock' => $row['sandelyje'] ?? '0',
            'active' => $row['aktyvi'] ?? '1',
        ]);
        if ($err) {
            $errors[] = 'Eilutė ' . ($n + 2) . ': ' . implode(' ', $err);
            continue;
        }
        save_board($b);
        $saved++;
    }
    return [$saved, $errors];
}

function boards_csv_rows(): array
{
    $rows = [BOARD_CSV_COLUMNS];
    foreach (list_boards() as $b) {
        $rows[] = [$b['type'], $b['manufacturer'], $b['code'], $b['name'], $b['structure'], fmt_num((float) $b['thickness']),
            0 + $b['sheet_length'], 0 + $b['sheet_width'], $b['grain'], str_replace('.', ',', (string) (0 + $b['price_sheet'])),
            $b['stock'], $b['active']];
    }
    return $rows;
}

// ---------- kainų nustatymai ----------

function pricing(): array
{
    static $pricing = null;
    if ($pricing === null) {
        $stmt = db()->prepare('SELECT value FROM settings WHERE key = "pricing"');
        $stmt->execute();
        $saved = json_decode((string) $stmt->fetchColumn(), true);
        $pricing = array_replace_recursive(config('pricing'), is_array($saved) ? $saved : []);
    }
    return $pricing;
}

function save_pricing(array $p): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES ("pricing", ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([json_encode($p, JSON_UNESCAPED_UNICODE)]);
}
