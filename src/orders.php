<?php
declare(strict_types=1);

const EDGE_SIDES = ['l1', 'l2', 'w1', 'w2'];

/** Paverčia „1 200,5“ į 1200.5; tuščią ar netinkamą reikšmę – į null. */
function parse_number(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $value));
    return is_numeric($s) ? (float) $s : null;
}

function str_field(array $src, string $key, int $max): string
{
    $v = $src[$key] ?? '';
    return is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
}

/**
 * Patikrina kliento pateiktą užsakymą.
 * Grąžina [švarūs duomenys, klaidos pagal lauką, klaidos pagal detalės indeksą].
 */
function validate_order(array $in): array
{
    $errors = [];
    $o = [
        'contact_name'     => str_field($in, 'contact_name', 120),
        'company'          => str_field($in, 'company', 160),
        'company_code'     => str_field($in, 'company_code', 20),
        'phone'            => str_field($in, 'phone', 40),
        'email'            => str_field($in, 'email', 160),
        'delivery'         => str_field($in, 'delivery', 20),
        'delivery_address' => str_field($in, 'delivery_address', 300),
        'return_offcuts'   => !empty($in['return_offcuts']) ? 1 : 0,
        'packaging'        => str_field($in, 'packaging', 20),
        'packaging_note'   => str_field($in, 'packaging_note', 300),
        'comment'          => str_field($in, 'comment', 2000),
    ];

    if ($o['contact_name'] === '') {
        $errors['contact_name'] = 'Įrašykite vardą ir pavardę.';
    }
    if (!preg_match('/^\+?[0-9 ()-]{6,}$/', $o['phone'])) {
        $errors['phone'] = 'Įrašykite telefono numerį.';
    }
    if ($o['email'] !== '' && !filter_var($o['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Neteisingas el. pašto adresas.';
    }
    if ($o['company_code'] !== '' && !preg_match('/^[0-9]{5,12}$/', $o['company_code'])) {
        $errors['company_code'] = 'Įmonės kodą sudaro tik skaitmenys.';
    }
    if (!in_array($o['delivery'], ['pickup', 'delivery'], true)) {
        $errors['delivery'] = 'Pasirinkite, kaip atsiimsite užsakymą.';
    } elseif ($o['delivery'] === 'delivery' && $o['delivery_address'] === '') {
        $errors['delivery_address'] = 'Nurodykite pristatymo adresą.';
    }
    if (!in_array($o['packaging'], ['standard', 'none', 'special'], true)) {
        $errors['packaging'] = 'Pasirinkite pakavimo būdą.';
    } elseif ($o['packaging'] === 'special' && $o['packaging_note'] === '') {
        $errors['packaging_note'] = 'Aprašykite specialias pakavimo sąlygas.';
    }

    [$items, $itemErrors] = validate_items(is_array($in['items'] ?? null) ? $in['items'] : []);
    if (!$items && !$itemErrors) {
        $errors['items'] = 'Įrašykite bent vieną detalę.';
    } elseif (count($items) > config('max_items')) {
        $errors['items'] = 'Per daug detalių (daugiausia ' . config('max_items') . ').';
    }
    $itemErrors += validate_offcut_usage($items);
    $o['items'] = $items;

    return [$o, $errors, $itemErrors];
}

function validate_items(array $rows): array
{
    $items = [];
    $errors = [];
    $max = (float) config('max_dimension');

    foreach (array_values($rows) as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $edges = is_array($row['edges'] ?? null) ? $row['edges'] : [];
        $item = [
            'board'     => str_field($row, 'name', 160),
            'thickness' => parse_number($row['thickness'] ?? ''),
            'length'    => parse_number($row['length'] ?? ''),
            'width'     => parse_number($row['width'] ?? ''),
            'qty'       => parse_number($row['qty'] ?? ''),
            'note'      => str_field($row, 'note', 300),
            'cnc'       => str_field($row, 'cnc', 60),
            'offcut_id' => is_admin() ? ((int) ($row['offcut_id'] ?? 0) ?: null) : null, // likučius skiria tik administratorius
            'board_id'  => (int) ($row['board_id'] ?? 0) ?: null,
            '_i'        => $i,
        ];
        foreach (EDGE_SIDES as $side) {
            $item['edge_' . $side] = is_scalar($edges[$side] ?? null) ? (string) $edges[$side] : '';
        }

        $isEmpty = $item['board'] === '' && !$item['board_id'] && $item['length'] === null && $item['width'] === null
            && $item['qty'] === null && $item['note'] === '' && $item['cnc'] === '';
        if ($isEmpty) {
            continue;
        }

        // Plokštė – tik iš katalogo; pavadinimas ir storis imami iš jo, o ne iš kliento duomenų.
        $e = [];
        $board = find_board($item['board_id']);
        if ($board === null || !$board['active']) {
            $e['code'] = 'Pasirinkite plokštę iš katalogo (kodas, struktūra, storis).';
        } else {
            $item['board'] = board_label($board);
            $item['thickness'] = (float) $board['thickness'];
        }
        foreach (['length' => 'Ilgis', 'width' => 'Plotis'] as $key => $label) {
            if ($item[$key] === null || $item[$key] <= 0) {
                $e[$key] = $label . ' turi būti didesnis už 0.';
            } elseif ($item[$key] > $max) {
                $e[$key] = $label . ' negali viršyti ' . fmt_num($max) . ' mm.';
            }
        }
        if ($item['qty'] === null || $item['qty'] < 1 || floor($item['qty']) != $item['qty'] || $item['qty'] > 9999) {
            $e['qty'] = 'Kiekis – sveikas skaičius nuo 1.';
        }
        foreach (EDGE_SIDES as $side) {
            $edge = $item['edge_' . $side];
            if ($edge !== '' && ($item['thickness'] === null || !edge_allowed($edge, $item['thickness']))) {
                $e['edges'] = 'Pasirinkta briauna netinka šio storio plokštei.';
            }
        }

        if ($e) {
            $errors[$i] = $e;
        } else {
            $item['qty'] = (int) $item['qty'];
            $item['position'] = count($items) + 1;
            $items[] = $item;
        }
    }
    return [$items, $errors];
}

function save_order(array $o): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $token = bin2hex(random_bytes(12));
        $stmt = $pdo->prepare('INSERT INTO orders (token, created_at, status, contact_name, company, company_code, phone, email,
                delivery, delivery_address, return_offcuts, packaging, packaging_note, comment, quote_json, total)
            VALUES (?, ?, "new", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $token, date('Y-m-d H:i:s'), $o['contact_name'], $o['company'], $o['company_code'], $o['phone'], $o['email'],
            $o['delivery'], $o['delivery'] === 'delivery' ? $o['delivery_address'] : '', $o['return_offcuts'],
            $o['packaging'], $o['packaging'] === 'special' ? $o['packaging_note'] : '', $o['comment'],
            json_encode($o['quote'], JSON_UNESCAPED_UNICODE), $o['quote']['total'],
        ]);
        $id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, position, board, thickness, length, width, qty,
                edge_l1, edge_l2, edge_w1, edge_w2, note, cnc, offcut_id, board_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($o['items'] as $pos => $it) {
            $stmt->execute([
                $id, $pos + 1, $it['board'], $it['thickness'], $it['length'], $it['width'], $it['qty'],
                $it['edge_l1'], $it['edge_l2'], $it['edge_w1'], $it['edge_w2'], $it['note'], $it['cnc'], $it['offcut_id'], $it['board_id'],
            ]);
        }
        reserve_offcuts($id, $o['items']);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return ['id' => $id, 'token' => $token];
}

/** Išsaugo įkeltus brėžinius. $files – $_FILES['files'] struktūra. Grąžina klaidų sąrašą. */
function check_uploads(array $files): array
{
    $cfg = config('upload');
    $errors = [];
    $names = (array) ($files['name'] ?? []);
    if (count($names) > $cfg['max_files']) {
        return ['Daugiausia ' . $cfg['max_files'] . ' failų.'];
    }
    foreach ($names as $i => $name) {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = $name . ': nepavyko įkelti.';
        } elseif (!in_array($ext, $cfg['extensions'], true)) {
            $errors[] = $name . ': netinkamas failo tipas.';
        } elseif ($files['size'][$i] > $cfg['max_size']) {
            $errors[] = $name . ': failas per didelis.';
        }
    }
    return $errors;
}

function store_uploads(int $orderId, array $files): void
{
    $dir = config('upload_dir') . '/' . $orderId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stmt = db()->prepare('INSERT INTO order_files (order_id, original_name, stored_name, size) VALUES (?, ?, ?, ?)');
    foreach ((array) ($files['name'] ?? []) as $i => $name) {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $stored = bin2hex(random_bytes(10)) . '.' . $ext;
        if (move_uploaded_file($files['tmp_name'][$i], $dir . '/' . $stored)) {
            $stmt->execute([$orderId, mb_substr(basename((string) $name), 0, 200), $stored, (int) $files['size'][$i]]);
        }
    }
}

function find_order(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $stmt = db()->prepare('SELECT i.*, f.location AS offcut_location, f.length AS offcut_length, f.width AS offcut_width
        FROM order_items i LEFT JOIN offcuts f ON f.id = i.offcut_id WHERE i.order_id = ? ORDER BY i.position');
    $stmt->execute([$id]);
    $order['items'] = $stmt->fetchAll();
    $stmt = db()->prepare('SELECT * FROM order_files WHERE order_id = ? ORDER BY id');
    $stmt->execute([$id]);
    $order['files'] = $stmt->fetchAll();
    return $order;
}

/** Užsakymas, jei prieiga leidžiama: administratoriui arba turint nuorodos raktą. */
function find_order_for_viewer(int $id, ?string $token): ?array
{
    $order = find_order($id);
    if ($order === null) {
        return null;
    }
    if (is_admin() || (is_string($token) && hash_equals($order['token'], $token))) {
        return $order;
    }
    return null;
}

function list_orders(string $status = '', string $search = ''): array
{
    $sql = 'SELECT o.*, (SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = o.id) AS parts,
            (SELECT COALESCE(SUM(length * width * qty), 0) / 1000000.0 FROM order_items WHERE order_id = o.id) AS area
            FROM orders o WHERE 1 = 1';
    $args = [];
    if ($status !== '') {
        $sql .= ' AND o.status = ?';
        $args[] = $status;
    }
    if ($search !== '') {
        $sql .= ' AND (o.contact_name LIKE ? OR o.company LIKE ? OR o.phone LIKE ? OR o.internal_order_no LIKE ? OR CAST(o.id AS TEXT) = ?)';
        $like = '%' . $search . '%';
        array_push($args, $like, $like, $like, $like, $search);
    }
    $stmt = db()->prepare($sql . ' ORDER BY o.id DESC LIMIT 500');
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function update_order_admin(int $id, array $in): void
{
    $status = (string) ($in['status'] ?? 'new');
    if (!isset(config('statuses')[$status])) {
        $status = 'new';
    }
    $stmt = db()->prepare('UPDATE orders SET status = ?, internal_order_no = ?, internal_date = ?, internal_due_date = ?, admin_note = ? WHERE id = ?');
    $stmt->execute([
        $status, str_field($in, 'internal_order_no', 60), str_field($in, 'internal_date', 10),
        str_field($in, 'internal_due_date', 10), str_field($in, 'admin_note', 2000), $id,
    ]);
    sync_offcuts_with_order($id, $status);
}

/** Suvestinė: detalių kiekis, plotas pagal plokštę, briaunų metrai pagal tipą. */
function order_summary(array $items): array
{
    $parts = 0;
    $boards = [];
    $edges = [];
    foreach ($items as $it) {
        $qty = (int) $it['qty'];
        $parts += $qty;
        $key = $it['board'] . ' · ' . fmt_num((float) $it['thickness']) . ' mm';
        $boards[$key] = ($boards[$key] ?? 0) + $it['length'] * $it['width'] * $qty / 1e6;
        foreach (EDGE_SIDES as $side) {
            $edge = $it['edge_' . $side];
            if ($edge !== '') {
                $len = $side[0] === 'l' ? $it['length'] : $it['width'];
                $edges[$edge] = ($edges[$edge] ?? 0) + $len * $qty / 1000;
            }
        }
    }
    return ['parts' => $parts, 'boards' => $boards, 'edges' => $edges];
}

function notify_new_order(array $order): void
{
    $to = config('notify_email');
    if (!$to) {
        return;
    }
    $subject = 'Naujas pjovimo užsakymas Nr. ' . $order['id'] . ' – ' . $order['contact_name'];
    $body = "Gautas naujas pjovimo užsakymas.\n\nKlientas: {$order['contact_name']}\nĮmonė: {$order['company']}\n"
        . "Telefonas: {$order['phone']}\nEl. paštas: {$order['email']}\nDetalių eilučių: " . count($order['items']) . "\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, "Content-Type: text/plain; charset=utf-8\r\n");
}
