<?php
declare(strict_types=1);

/**
 * Kainos skaičiavimas: detalės išdėstomos ant plokščių (giljotininis pjovimas, kaip formatinio
 * pjūklo), iš išdėstymo gaunamas plokščių skaičius ir pjūvių ilgis; prie jų pridedamas
 * briaunavimas, likučiai, pristatymas ir PVM. Visi įkainiai – pricing() (admin → Kainos).
 */

const NEST_MAX_PIECES = 3000;

/**
 * Išbando kelias rikiavimo ir pjovimo strategijas ir grąžina tą, kuriai reikia mažiausiai
 * plokščių (lygiųjų atveju – trumpiausio pjūvių ilgio).
 */
function nest_best(array $parts, float $sheetL, float $sheetW, bool $grain, float $kerf, float $trim): array
{
    $sorts = ['max', 'area', 'length', 'width'];
    $splits = ['short', 'long', 'horizontal', 'vertical'];
    if (count($parts) > 400) {
        $sorts = ['max', 'area'];
        $splits = ['short', 'horizontal'];
    }
    $best = null;
    foreach ($sorts as $sort) {
        foreach ($splits as $split) {
            $r = nest_parts($parts, $sheetL, $sheetW, $grain, $kerf, $trim, $sort, $split);
            $key = [count($r['unplaced']), count($r['sheets']), array_sum(array_column($r['sheets'], 'cut'))];
            if ($best === null || $key < $best[0]) {
                $best = [$key, $r];
            }
        }
    }
    return $best[1];
}

/**
 * Išdėsto detales ant vienodų plokščių.
 * $parts – [['l' => ilgis, 'w' => plotis, 'pos' => Nr.], ...] (kiekviena vienetinė detalė atskirai).
 * Ilgis dedamas išilgai plokštės ilgio (tekstūros kryptimi); be tekstūros detalę galima pasukti.
 */
function nest_parts(array $parts, float $sheetL, float $sheetW, bool $grain, float $kerf, float $trim,
    string $sort = 'max', string $split = 'short'): array
{
    // Prie naudingo ploto pridedamas vienas pjūvio plotis: paskutinei detalei prie krašto pjūvio nereikia.
    $usableL = $sheetL - 2 * $trim + $kerf;
    $usableW = $sheetW - 2 * $trim + $kerf;
    $key = match ($sort) {
        'area'   => static fn ($p) => [$p['l'] * $p['w'], max($p['l'], $p['w'])],
        'length' => static fn ($p) => [$p['l'], $p['w']],
        'width'  => static fn ($p) => [$p['w'], $p['l']],
        default  => static fn ($p) => [max($p['l'], $p['w']), $p['l'] * $p['w']],
    };
    usort($parts, static fn ($a, $b) => $key($b) <=> $key($a));

    $sheets = [];
    $unplaced = [];
    foreach ($parts as $part) {
        $orientations = [[$part['l'], $part['w'], false]];
        if (!$grain && $part['l'] != $part['w']) {
            $orientations[] = [$part['w'], $part['l'], true];
        }
        $best = nest_find_spot($sheets, $orientations, $kerf);
        if ($best === null) {
            $sheets[] = ['free' => [[0, 0, $usableL, $usableW]], 'parts' => [], 'cut' => 0.0, 'area' => 0.0];
            $best = nest_find_spot([array_key_last($sheets) => end($sheets)], $orientations, $kerf);
            if ($best === null) {
                array_pop($sheets);
                $unplaced[] = $part;
                continue;
            }
        }
        nest_place($sheets[$best['sheet']], $best, $part, $kerf, $trim, $split);
    }

    $trimCut = $trim > 0 ? 2 * ($sheetL + $sheetW) : 0;
    foreach ($sheets as &$s) {
        $s['cut'] += $trimCut;
        unset($s['free']);
    }
    return ['sheets' => $sheets, 'unplaced' => $unplaced];
}

/** Geriausia vieta: laisvas stačiakampis, kuriame lieka mažiausiai trumposios kraštinės (BSSF). */
function nest_find_spot(array $sheets, array $orientations, float $kerf): ?array
{
    $best = null;
    foreach ($sheets as $si => $sheet) {
        foreach ($sheet['free'] as $fi => [$fx, $fy, $fw, $fh]) {
            foreach ($orientations as [$w, $h, $rot]) {
                $nw = $w + $kerf;
                $nh = $h + $kerf;
                if ($nw > $fw + 0.001 || $nh > $fh + 0.001) {
                    continue;
                }
                $score = [min($fw - $nw, $fh - $nh), max($fw - $nw, $fh - $nh)];
                if ($best === null || $score < $best['score']) {
                    $best = ['sheet' => $si, 'free' => $fi, 'w' => $w, 'h' => $h, 'rot' => $rot, 'score' => $score];
                }
            }
        }
        if ($best !== null) {
            break; // pirmiausia pildoma ankstesnė plokštė
        }
    }
    return $best;
}

/** Padeda detalę į laisvo stačiakampio kampą ir padalija likutį dviem giljotininiais pjūviais. */
function nest_place(array &$sheet, array $spot, array $part, float $kerf, float $trim, string $split): void
{
    [$fx, $fy, $fw, $fh] = $sheet['free'][$spot['free']];
    array_splice($sheet['free'], $spot['free'], 1);
    $w = $spot['w'] + $kerf;
    $h = $spot['h'] + $kerf;
    $restW = $fw - $w;
    $restH = $fh - $h;

    // horizontal – pirmas pjūvis per visą laisvo gabalo plotį; short/long – pagal likusią ašį.
    $horizontal = match ($split) {
        'horizontal' => true,
        'vertical'   => false,
        'long'       => $restW >= $restH,
        default      => $restW < $restH,
    };
    if ($horizontal) {
        $new = [[$fx + $w, $fy, $restW, $h], [$fx, $fy + $h, $fw, $restH]];
        $cuts = ($restH > $kerf ? $fw - $kerf : 0) + ($restW > $kerf ? $spot['h'] : 0);
    } else {
        $new = [[$fx + $w, $fy, $restW, $fh], [$fx, $fy + $h, $w, $restH]];
        $cuts = ($restW > $kerf ? $fh - $kerf : 0) + ($restH > $kerf ? $spot['w'] : 0);
    }
    foreach ($new as $r) {
        if ($r[2] > $kerf && $r[3] > $kerf) {
            $sheet['free'][] = $r;
        }
    }
    $sheet['cut'] += $cuts;
    $sheet['area'] += $part['l'] * $part['w'];
    $sheet['parts'][] = [round($fx + $trim, 1), round($fy + $trim, 1), $spot['w'], $spot['h'], $part['pos'], $spot['rot']];
}

/**
 * Detalės skaičiavimui iš formos eilučių. Atmetamos nepilnos eilutės (skaičiuoklė veikia pildant),
 * netinkamos briaunos ignoruojamos. 'pos' – eilutės Nr. formoje.
 */
function quote_items_from_input(array $rows): array
{
    $items = [];
    foreach (array_values($rows) as $i => $row) {
        if (!is_array($row)) {
            continue;
        }
        $offcut = is_admin() ? ((int) ($row['offcut_id'] ?? 0) ?: null) : null; // likučius skiria tik administratorius
        $board = find_board((int) ($row['board_id'] ?? 0));
        $L = parse_number($row['length'] ?? '');
        $W = parse_number($row['width'] ?? '');
        $q = trim((string) ($row['qty'] ?? '')) === '' ? 1.0 : parse_number($row['qty']);
        if (!$board || !$L || !$W || $L <= 0 || $W <= 0 || !$q || $q < 1 || $q != floor($q)) {
            continue;
        }
        $edges = is_array($row['edges'] ?? null) ? $row['edges'] : [];
        $item = ['position' => $i + 1, 'board_id' => (int) $board['id'], 'thickness' => (float) $board['thickness'],
            'length' => $L, 'width' => $W, 'qty' => (int) $q, 'offcut_id' => $offcut, 'cnc' => trim((string) ($row['cnc'] ?? ''))];
        foreach (EDGE_SIDES as $side) {
            $id = is_scalar($edges[$side] ?? null) ? (string) $edges[$side] : '';
            $item['edge_' . $side] = $id !== '' && edge_allowed($id, $item['thickness']) ? $id : '';
        }
        $items[] = $item;
    }
    return $items;
}

/**
 * Kaina. $items – užsakymo detalės (board_id, length, width, qty, edge_*, offcut_id, position, cnc).
 * $reservedFor – užsakymo ID, kurio rezervuoti likučiai laikomi tinkamais (perskaičiuojant pateiktą užsakymą).
 */
function compute_quote(array $items, string $delivery, string $packaging, ?int $reservedFor = null): array
{
    $p = pricing();
    $kerf = (float) $p['kerf_mm'];
    $trim = (float) $p['trim_mm'];
    $lines = [];
    $warnings = [];
    $materials = [];
    $cutMm = 0.0;
    $edgeM = [];

    // Detalės iš naujų plokščių – pagal plokštę
    $groups = [];
    $offcutGroups = [];
    foreach ($items as $it) {
        if (!empty($it['offcut_id'])) {
            $offcutGroups[(int) $it['offcut_id']][] = $it;
        } else {
            $groups[(int) $it['board_id']][] = $it;
        }
        foreach (EDGE_SIDES as $side) {
            $edge = $it['edge_' . $side] ?? '';
            if ($edge !== '') {
                $len = $side[0] === 'l' ? $it['length'] : $it['width'];
                $edgeM[$edge] = ($edgeM[$edge] ?? 0) + ($len + $p['edge_allowance_mm']) * $it['qty'] / 1000;
            }
        }
        if (($it['cnc'] ?? '') !== '') {
            $warnings['cnc'] = 'Detalės su CNC kodu (gręžimas, frezavimas) įkainojamos atskirai – su jumis susisieks vadybininkas.';
        }
    }

    foreach ($groups as $boardId => $groupItems) {
        $board = find_board($boardId);
        if ($board === null) {
            continue;
        }
        $label = board_label($board) . ' · ' . fmt_num((float) $board['thickness']) . ' mm';
        $sheetArea = $board['sheet_length'] * $board['sheet_width'] / 1e6;
        $priceM2 = $sheetArea > 0 ? $board['price_sheet'] / $sheetArea : 0;
        $pieces = [];
        $partsArea = 0.0;
        foreach ($groupItems as $it) {
            $partsArea += $it['length'] * $it['width'] * $it['qty'] / 1e6;
            for ($k = 0; $k < $it['qty'] && count($pieces) <= NEST_MAX_PIECES; $k++) {
                $pieces[] = ['l' => (float) $it['length'], 'w' => (float) $it['width'], 'pos' => $it['position']];
            }
        }

        $material = ['label' => $label, 'board_id' => $boardId, 'sheet' => [0 + $board['sheet_length'], 0 + $board['sheet_width']],
            'grain' => (bool) $board['grain'], 'parts_area' => round($partsArea, 3), 'sheets' => 0, 'utilization' => 0, 'layouts' => []];

        if (count($pieces) > NEST_MAX_PIECES) {
            // Labai dideliam kiekiui – plokščių skaičius vertinamas pagal plotą.
            $sheets = (int) ceil($partsArea * (1 + $p['waste_percent'] / 100) / $sheetArea);
            $cutMm += array_sum(array_map(static fn ($it) => ($it['length'] + $it['width']) * $it['qty'], $groupItems));
            $warnings[] = $label . ': daug detalių – plokščių skaičius įvertintas pagal plotą.';
        } else {
            $nest = nest_best($pieces, (float) $board['sheet_length'], (float) $board['sheet_width'], (bool) $board['grain'], $kerf, $trim);
            $sheets = count($nest['sheets']);
            foreach ($nest['sheets'] as $s) {
                $cutMm += $s['cut'];
                if (count($material['layouts']) < 12) {
                    $material['layouts'][] = ['parts' => $s['parts'], 'utilization' => round($s['area'] / ($board['sheet_length'] * $board['sheet_width']) * 100)];
                }
            }
            foreach (array_unique(array_column($nest['unplaced'], 'pos')) as $pos) {
                $warnings[] = 'Detalė Nr. ' . $pos . ' netelpa į ' . $label . ' plokštę (' . fmt_num((float) $board['sheet_length']) . ' × '
                    . fmt_num((float) $board['sheet_width']) . ' mm' . ($board['grain'] ? ', neperkreipiant tekstūros' : '') . ').';
            }
        }
        $material['sheets'] = $sheets;
        $material['utilization'] = $sheets ? round($partsArea / ($sheets * $sheetArea) * 100) : 0;
        $materials[] = $material;

        if ($p['material_mode'] === 'area') {
            $qty = round($partsArea * (1 + $p['waste_percent'] / 100), 3);
            $lines[] = ['group' => 'Medžiagos', 'label' => $label, 'detail' => fmt_num($partsArea, 2) . ' m² + ' . $p['waste_percent'] . ' % atliekų',
                'qty' => $qty, 'unit' => 'm²', 'price' => round($priceM2, 2), 'sum' => round($qty * $priceM2, 2)];
        } else {
            $lines[] = ['group' => 'Medžiagos', 'label' => $label, 'detail' => 'išnaudojimas ' . $material['utilization'] . ' %',
                'qty' => $sheets, 'unit' => 'lap.', 'price' => round((float) $board['price_sheet'], 2), 'sum' => round($sheets * $board['price_sheet'], 2)];
        }
        if ($sheets > (int) $board['stock']) {
            $warnings[] = $label . ': sandėlyje ' . (int) $board['stock'] . ' lap., reikia ' . $sheets . ' – trūkstamas plokštes užsakysime, įvykdymo terminas gali pailgėti.';
        }
    }

    // Detalės iš likučių – mokama už visą likutį su nuolaida
    foreach ($offcutGroups as $offcutId => $groupItems) {
        $off = find_offcut($offcutId);
        $board = $off ? find_board((int) $off['board_id']) : null;
        $usable = $off && ($off['status'] === 'available' || ($reservedFor && (int) $off['reserved_order_id'] === $reservedFor));
        if (!$usable || !$board) {
            $warnings[] = 'Likutis #' . $offcutId . ' nebepasiekiamas – detalės neįskaičiuotos.';
            continue;
        }
        $sheetArea = $board['sheet_length'] * $board['sheet_width'] / 1e6;
        $area = $off['length'] * $off['width'] / 1e6;
        $price = round($area * $board['price_sheet'] / $sheetArea * (1 - $p['offcut_discount_percent'] / 100), 2);
        $lines[] = ['group' => 'Medžiagos', 'label' => 'Likutis #' . $offcutId . ' · ' . board_label($board) . ' ' . fmt_num((float) $board['thickness']) . ' mm',
            'detail' => fmt_num((float) $off['length']) . ' × ' . fmt_num((float) $off['width']) . ' mm, −' . $p['offcut_discount_percent'] . ' %',
            'qty' => 1, 'unit' => 'vnt.', 'price' => $price, 'sum' => $price];
        foreach ($groupItems as $it) {
            $cutMm += ($it['length'] + $it['width']) * $it['qty'];
        }
    }

    if ($cutMm > 0) {
        $m = round($cutMm / 1000, 1);
        $lines[] = ['group' => 'Darbai', 'label' => 'Pjovimas', 'detail' => 'pjūvio plotis ' . fmt_num($kerf) . ' mm',
            'qty' => $m, 'unit' => 'm', 'price' => (float) $p['cut_price_m'], 'sum' => round($m * $p['cut_price_m'], 2)];
    }
    foreach ($edgeM as $id => $m) {
        $m = round($m, 1);
        $price = (float) ($p['edge_prices_m'][$id] ?? 0);
        $lines[] = ['group' => 'Darbai', 'label' => 'Briaunavimas ' . edge_label((string) $id), 'detail' => 'su ' . $p['edge_allowance_mm'] . ' mm užlaida',
            'qty' => $m, 'unit' => 'm', 'price' => $price, 'sum' => round($m * $price, 2)];
    }
    if ($delivery === 'delivery' && $p['delivery_price'] > 0) {
        $lines[] = ['group' => 'Paslaugos', 'label' => 'Pristatymas', 'detail' => '', 'qty' => 1, 'unit' => 'vnt.',
            'price' => (float) $p['delivery_price'], 'sum' => round((float) $p['delivery_price'], 2)];
    }
    if ($packaging === 'special' && $p['special_packaging_price'] > 0) {
        $lines[] = ['group' => 'Paslaugos', 'label' => 'Specialus pakavimas', 'detail' => '', 'qty' => 1, 'unit' => 'vnt.',
            'price' => (float) $p['special_packaging_price'], 'sum' => round((float) $p['special_packaging_price'], 2)];
    }

    $subtotal = round(array_sum(array_column($lines, 'sum')), 2);
    if ($subtotal > 0 && $subtotal < $p['min_order']) {
        $extra = round($p['min_order'] - $subtotal, 2);
        $lines[] = ['group' => 'Paslaugos', 'label' => 'Minimalaus užsakymo priemoka', 'detail' => 'min. ' . fmt_num((float) $p['min_order']) . ' €',
            'qty' => 1, 'unit' => 'vnt.', 'price' => $extra, 'sum' => $extra];
        $subtotal = round($subtotal + $extra, 2);
    }
    $vat = round($subtotal * $p['vat_percent'] / 100, 2);

    return [
        'lines'      => $lines,
        'subtotal'   => $subtotal,
        'vat_percent' => (float) $p['vat_percent'],
        'vat'        => $vat,
        'total'      => round($subtotal + $vat, 2),
        'warnings'   => array_values($warnings),
        'materials'  => $materials,
        'cut_m'      => round($cutMm / 1000, 1),
        'kerf'       => $kerf,
        'trim'       => $trim,
    ];
}

function fmt_money(float $n): string
{
    return number_format($n, 2, ',', ' ') . ' €';
}
