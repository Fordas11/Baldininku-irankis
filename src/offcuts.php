<?php
declare(strict_types=1);

/**
 * Likučių (atraižų) katalogas. Vienas įrašas – vienas fizinis plokštės gabalas.
 * Būsena: available (laisvas) -> reserved (rezervuotas kliento užsakymui) -> used (supjaustytas).
 */

const OFFCUT_STATUSES = [
    'available' => 'Laisvas',
    'reserved'  => 'Rezervuotas',
    'used'      => 'Panaudotas',
];

/** Laukai, kuriuos mato klientai (be vietos sandėlyje ir vidinių pastabų). */
function offcut_public(array $o): array
{
    return [
        'id'        => (int) $o['id'],
        'board_id'  => (int) $o['board_id'],
        'board'     => $o['board'],
        'thickness' => 0 + $o['thickness'],
        'length'    => 0 + $o['length'],
        'width'     => 0 + $o['width'],
        'note'      => $o['note'],
    ];
}

function find_offcut(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM offcuts WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Filtrai: board, thickness, min_length, min_width, status, q (laisva paieška). */
function list_offcuts(array $f): array
{
    $sql = 'SELECT * FROM offcuts WHERE 1 = 1';
    $args = [];
    if (($f['status'] ?? '') !== '') {
        $sql .= ' AND status = ?';
        $args[] = $f['status'];
    }
    if (($f['board'] ?? '') !== '') {
        $sql .= ' AND LOWER(board) = LOWER(?)';
        $args[] = trim((string) $f['board']);
    }
    if (($f['q'] ?? '') !== '') {
        $sql .= ' AND (board LIKE ? OR location LIKE ? OR note LIKE ? OR CAST(id AS TEXT) = ?)';
        $like = '%' . trim((string) $f['q']) . '%';
        array_push($args, $like, $like, $like, trim((string) $f['q']));
    }
    foreach (['thickness' => 'thickness = ?', 'min_length' => 'length >= ?', 'min_width' => 'width >= ?'] as $key => $cond) {
        $n = parse_number($f[$key] ?? '');
        if ($n !== null && $n > 0) {
            $sql .= ' AND ' . $cond;
            $args[] = $n;
        }
    }
    $stmt = db()->prepare($sql . ' ORDER BY board, thickness, length * width DESC LIMIT 1000');
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** Laisvų likučių plokščių pavadinimai su kiekiais (katalogo filtrui). */
function offcut_boards(): array
{
    return db()->query('SELECT board, thickness, COUNT(*) AS n FROM offcuts WHERE status = "available"
        GROUP BY LOWER(board), thickness ORDER BY board, thickness')->fetchAll();
}

/**
 * Laisvi likučiai, iš kurių galima išpjauti detalę: ta pati plokštė ir storis, telpa
 * neperkreipiant tekstūros (detalės ilgis išilgai likučio ilgio). Mažiausi – pirmi, kad
 * dideli gabalai liktų didesnėms detalėms.
 */
function matching_offcuts(int $boardId, float $length, float $width, int $limit = 6): array
{
    $stmt = db()->prepare('SELECT * FROM offcuts WHERE status = "available" AND board_id = ?
        AND length >= ? AND width >= ? ORDER BY length * width LIMIT ' . $limit);
    $stmt->execute([$boardId, $length, $width]);
    return $stmt->fetchAll();
}

function validate_offcut(array $in): array
{
    $board = find_board((int) ($in['board_id'] ?? 0));
    $o = [
        'board_id'  => $board ? (int) $board['id'] : null,
        'board'     => $board ? board_label($board) : '',
        'thickness' => $board ? (float) $board['thickness'] : null,
        'length'    => parse_number($in['length'] ?? ''),
        'width'     => parse_number($in['width'] ?? ''),
        'location'  => str_field($in, 'location', 60),
        'note'      => str_field($in, 'note', 300),
        'admin_note' => str_field($in, 'admin_note', 300),
        'source_order_id' => (int) ($in['source_order_id'] ?? 0) ?: null,
        'status'    => isset(OFFCUT_STATUSES[$in['status'] ?? '']) ? $in['status'] : 'available',
    ];
    $errors = [];
    if ($board === null) {
        $errors['board_id'] = 'Pasirinkite plokštę iš katalogo.';
    } else {
        foreach (['length' => 'sheet_length', 'width' => 'sheet_width'] as $key => $max) {
            if ($o[$key] !== null && $o[$key] > $board[$max]) {
                $errors[$key] = 'Likutis negali būti didesnis už plokštę.';
            }
        }
    }
    foreach (['length' => 'Ilgis', 'width' => 'Plotis'] as $key => $label) {
        if ($o[$key] === null || $o[$key] <= 0 || $o[$key] > config('max_dimension')) {
            $errors[$key] = $label . ' turi būti nuo 1 iki ' . config('max_dimension') . ' mm.';
        }
    }
    return [$o, $errors];
}

/** Sukuria $count vienodų likučių arba atnaujina esamą ($id). */
function save_offcut(array $o, ?int $id = null, int $count = 1): void
{
    $now = date('Y-m-d H:i:s');
    if ($id) {
        $stmt = db()->prepare('UPDATE offcuts SET board_id = ?, board = ?, thickness = ?, length = ?, width = ?, location = ?, note = ?,
            admin_note = ?, source_order_id = ?, status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$o['board_id'], $o['board'], $o['thickness'], $o['length'], $o['width'], $o['location'], $o['note'],
            $o['admin_note'], $o['source_order_id'], $o['status'], $now, $id]);
        if ($o['status'] === 'available') {
            db()->prepare('UPDATE offcuts SET reserved_order_id = NULL WHERE id = ?')->execute([$id]);
        }
        return;
    }
    $stmt = db()->prepare('INSERT INTO offcuts (board_id, board, thickness, length, width, location, note, admin_note, source_order_id,
        status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "available", ?, ?)');
    for ($i = 0; $i < max(1, min($count, 100)); $i++) {
        $stmt->execute([$o['board_id'], $o['board'], $o['thickness'], $o['length'], $o['width'], $o['location'], $o['note'],
            $o['admin_note'], $o['source_order_id'], $now, $now]);
    }
}

function delete_offcut(int $id): void
{
    db()->prepare('UPDATE order_items SET offcut_id = NULL WHERE offcut_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM offcuts WHERE id = ?')->execute([$id]);
}

/**
 * Patikrina detales, kurias klientas nori pjauti iš likučių.
 * $items – validate_items() rezultatas (su '_i' – eilutės indeksu formoje). Grąžina klaidas pagal indeksą.
 */
function validate_offcut_usage(array $items): array
{
    $errors = [];
    $usedArea = [];
    foreach ($items as $it) {
        if (!$it['offcut_id']) {
            continue;
        }
        $off = find_offcut($it['offcut_id']);
        $msg = null;
        if ($off === null || $off['status'] !== 'available') {
            $msg = 'Likutis #' . $it['offcut_id'] . ' jau nebepasiekiamas – pasirinkite kitą arba pjaukite iš naujos plokštės.';
        } elseif ((int) $off['board_id'] !== (int) $it['board_id']) {
            $msg = 'Likutis #' . $off['id'] . ' yra kitos plokštės (' . $off['board'] . ', ' . fmt_num((float) $off['thickness']) . ' mm).';
        } elseif ($it['length'] > $off['length'] || $it['width'] > $off['width']) {
            $msg = 'Detalė netelpa į likutį #' . $off['id'] . ' (' . fmt_num((float) $off['length']) . ' × ' . fmt_num((float) $off['width']) . ' mm).';
        } else {
            $id = (int) $off['id'];
            $usedArea[$id] = ($usedArea[$id] ?? 0) + $it['length'] * $it['width'] * $it['qty'];
            if ($usedArea[$id] > $off['length'] * $off['width']) {
                $msg = 'Detalių iš likučio #' . $id . ' per daug – jos netelpa į ' . fmt_num((float) $off['length']) . ' × ' . fmt_num((float) $off['width']) . ' mm.';
            }
        }
        if ($msg !== null) {
            $errors[$it['_i']]['offcut'] = $msg;
        }
    }
    return $errors;
}

/** Rezervuoja likučius užsakymui. Kviečiama transakcijos viduje; jei kas nors spėjo pirmas – išimtis. */
function reserve_offcuts(int $orderId, array $items): void
{
    $stmt = db()->prepare('UPDATE offcuts SET status = "reserved", reserved_order_id = ?, updated_at = ? WHERE id = ? AND status = "available"');
    $ids = array_unique(array_filter(array_column($items, 'offcut_id')));
    foreach ($ids as $id) {
        $stmt->execute([$orderId, date('Y-m-d H:i:s'), $id]);
        if ($stmt->rowCount() === 0) {
            throw new OffcutTakenException('Likutį #' . $id . ' ką tik rezervavo kitas klientas. Pasirinkite kitą likutį.');
        }
    }
}

final class OffcutTakenException extends RuntimeException
{
}

/**
 * Administratorius pateiktame užsakyme detalei priskiria likutį ($offcutId) arba jo atsisako (null).
 * Likutis rezervuojamas užsakymui; nebenaudojamas – grąžinamas į sandėlį. Užsakymo kaina nekeičiama.
 * Grąžina klaidos tekstą arba null.
 */
function assign_offcut_to_item(int $orderId, int $itemId, ?int $offcutId): ?string
{
    $stmt = db()->prepare('SELECT i.*, o.status AS order_status FROM order_items i JOIN orders o ON o.id = i.order_id
        WHERE i.id = ? AND i.order_id = ?');
    $stmt->execute([$itemId, $orderId]);
    $item = $stmt->fetch();
    if (!$item) {
        return 'Detalė nerasta.';
    }
    if (in_array($item['order_status'], ['done', 'cancelled'], true)) {
        return 'Atiduoto ar atšaukto užsakymo likučių keisti negalima.';
    }
    $oldId = (int) $item['offcut_id'] ?: null;
    if ($offcutId !== null) {
        $off = find_offcut($offcutId);
        $mine = $off && $off['status'] === 'reserved' && (int) $off['reserved_order_id'] === $orderId;
        if ($off === null || ($off['status'] !== 'available' && !$mine)) {
            return 'Likutis #' . $offcutId . ' jau nebepasiekiamas.';
        }
        if ((int) $off['board_id'] !== (int) $item['board_id']) {
            return 'Likutis #' . $offcutId . ' yra kitos plokštės.';
        }
        if ($item['length'] > $off['length'] || $item['width'] > $off['width']) {
            return 'Detalė netelpa į likutį #' . $offcutId . '.';
        }
        $stmt = db()->prepare('SELECT COALESCE(SUM(length * width * qty), 0) FROM order_items WHERE order_id = ? AND offcut_id = ? AND id <> ?');
        $stmt->execute([$orderId, $offcutId, $itemId]);
        if ((float) $stmt->fetchColumn() + $item['length'] * $item['width'] * $item['qty'] > $off['length'] * $off['width']) {
            return 'Detalių iš likučio #' . $offcutId . ' per daug – jos netelpa.';
        }
    }

    $now = date('Y-m-d H:i:s');
    db()->beginTransaction();
    db()->prepare('UPDATE order_items SET offcut_id = ? WHERE id = ?')->execute([$offcutId, $itemId]);
    if ($offcutId !== null) {
        db()->prepare('UPDATE offcuts SET status = "reserved", reserved_order_id = ?, updated_at = ? WHERE id = ?')
            ->execute([$orderId, $now, $offcutId]);
    }
    if ($oldId !== null && $oldId !== $offcutId) {
        db()->prepare('UPDATE offcuts SET status = "available", reserved_order_id = NULL, updated_at = ? WHERE id = ? AND reserved_order_id = ?
            AND status = "reserved" AND NOT EXISTS (SELECT 1 FROM order_items WHERE order_id = ? AND offcut_id = ?)')
            ->execute([$now, $oldId, $orderId, $orderId, $oldId]);
    }
    db()->commit();
    return null;
}

/** Pakeitus užsakymo būseną: atšauktas – likučiai grąžinami į katalogą, atiduotas – pažymimi panaudotais. */
function sync_offcuts_with_order(int $orderId, string $status): void
{
    $now = date('Y-m-d H:i:s');
    if ($status === 'cancelled') {
        db()->prepare('UPDATE offcuts SET status = "available", reserved_order_id = NULL, updated_at = ? WHERE reserved_order_id = ? AND status = "reserved"')
            ->execute([$now, $orderId]);
    } elseif ($status === 'done') {
        db()->prepare('UPDATE offcuts SET status = "used", updated_at = ? WHERE reserved_order_id = ?')->execute([$now, $orderId]);
    }
}
