<?php
declare(strict_types=1);

/**
 * Nuskaito užpildytą Excel formą „Pjovimo užsakymo forma“ (lapas „Klientams“),
 * kad klientas galėtų perkelti jau paruoštą sąrašą į web formą.
 * Grąžina ['contact' => [...], 'rows' => [...]] arba meta RuntimeException.
 */
function import_cut_form_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Failas nėra Excel (.xlsx) dokumentas.');
    }
    try {
        $sheetPath = xlsx_find_sheet($zip, 'Klientams');
        $strings = xlsx_shared_strings($zip);
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            throw new RuntimeException('Nerastas lapas „Klientams“.');
        }
        $cells = xlsx_cells($xml, $strings);
    } finally {
        $zip->close();
    }

    $get = static fn (string $ref): string => trim((string) ($cells[$ref] ?? ''));
    $firstIn = static function (int $row, array $cols) use ($get): string {
        foreach ($cols as $c) {
            if ($get($c . $row) !== '') {
                return $get($c . $row);
            }
        }
        return '';
    };
    $checked = static fn (string $ref): bool => $get($ref) !== '';

    $contact = [
        'contact_name' => $firstIn(3, ['H', 'I', 'J', 'K']),
        'company'      => $firstIn(4, ['H', 'I', 'J', 'K']),
        'company_code' => $firstIn(5, ['H', 'I', 'J', 'K']),
        'phone'        => $firstIn(6, ['H', 'I', 'J', 'K']),
    ];
    if ($checked('G9')) {
        $contact['delivery'] = 'delivery';
        $contact['delivery_address'] = $firstIn(9, ['I', 'J', 'K']) ?: $firstIn(10, ['H', 'I', 'J', 'K']);
    } elseif ($checked('G7')) {
        $contact['delivery'] = 'pickup';
    }
    if ($checked('G11')) {
        $contact['return_offcuts'] = true;
    }
    foreach (['G13' => 'standard', 'G15' => 'none', 'G17' => 'special'] as $ref => $value) {
        if ($checked($ref)) {
            $contact['packaging'] = $value;
        }
    }
    if (($contact['packaging'] ?? '') === 'special') {
        $contact['packaging_note'] = $get('G18');
    }

    $rows = [];
    for ($r = 29; $r <= 1000; $r++) {
        $row = [
            'name'      => $get('B' . $r),
            'thickness' => $get('C' . $r),
            'length'    => $get('D' . $r),
            'width'     => $get('E' . $r),
            'qty'       => $get('F' . $r),
            'edges'     => [
                'l1' => xlsx_edge_id($get('G' . $r)),
                'l2' => xlsx_edge_id($get('H' . $r)),
                'w1' => xlsx_edge_id($get('I' . $r)),
                'w2' => xlsx_edge_id($get('J' . $r)),
            ],
            'note'      => $get('K' . $r),
            'cnc'       => $get('L' . $r),
        ];
        if ($row['name'] === '' && $row['length'] === '' && $row['width'] === '' && $row['qty'] === '') {
            continue;
        }
        $rows[] = $row;
    }
    return ['contact' => array_filter($contact, static fn ($v) => $v !== ''), 'rows' => $rows];
}

/** Excel briaunos reikšmė (0,6 / 1 / 2 / 2,8 / 4,2) -> briaunos ID konfigūracijoje. */
function xlsx_edge_id(string $value): string
{
    $n = parse_number($value);
    if ($n === null) {
        return '';
    }
    foreach (array_keys(edge_types()) as $id) {
        if (abs((float) $id - $n) < 0.001) {
            return (string) $id;
        }
    }
    return '';
}

function xlsx_find_sheet(ZipArchive $zip, string $name): string
{
    $wb = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
    $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
    if (!$wb || !$rels) {
        throw new RuntimeException('Nepavyko perskaityti Excel failo.');
    }
    $targets = [];
    foreach ($rels->Relationship as $rel) {
        $targets[(string) $rel['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']), '/');
    }
    $first = null;
    foreach ($wb->sheets->sheet as $sheet) {
        $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $path = 'xl/' . ($targets[$rid] ?? '');
        $first ??= $path;
        if (mb_strtolower((string) $sheet['name']) === mb_strtolower($name)) {
            return $path;
        }
    }
    if ($first === null) {
        throw new RuntimeException('Excel faile nėra lapų.');
    }
    return $first;
}

function xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }
    $sst = simplexml_load_string($xml);
    $out = [];
    foreach ($sst->si as $si) {
        $text = (string) $si->t;
        foreach ($si->r as $run) {
            $text .= (string) $run->t;
        }
        $out[] = $text;
    }
    return $out;
}

/** Ląstelių reikšmės pagal adresą (A1 => reikšmė). */
function xlsx_cells(string $xml, array $strings): array
{
    $sheet = simplexml_load_string($xml);
    $cells = [];
    foreach ($sheet->sheetData->row as $row) {
        foreach ($row->c as $c) {
            $type = (string) $c['t'];
            $value = match ($type) {
                's'         => $strings[(int) $c->v] ?? '',
                'inlineStr' => (string) $c->is->t,
                default     => (string) $c->v,
            };
            if ($value !== '') {
                $cells[(string) $c['r']] = $value;
            }
        }
    }
    return $cells;
}
