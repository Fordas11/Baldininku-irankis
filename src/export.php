<?php
declare(strict_types=1);

/**
 * Eksportas į tuos pačius stulpelius, kuriuos Excel formoje skaičiuoja lapai „ardis“ ir „OM“.
 * CSV su kabliataškiu ir UTF-8 BOM – atsidaro lietuviškame Excel be papildomų nustatymų.
 */
function export_rows(array $order, string $format): array
{
    $edges = edge_types();
    $code = static fn (string $id, string $key): string => $id !== '' && isset($edges[$id]) ? $edges[$id][$key] : '';
    $num = static fn ($n): string => str_replace('.', ',', (string) (0 + $n));

    if ($format === 'ardis') {
        $rows = [['plokste', 'pozicija', 'ilgis', 'plotis', 'kiekis', 'Ilgis', 'I', 'P', 'P']];
        foreach ($order['items'] as $it) {
            $rows[] = [
                $it['board'], $it['position'], $num($it['length']), $num($it['width']), $it['qty'],
                $code($it['edge_l1'], 'ardis'), $code($it['edge_l2'], 'ardis'),
                $code($it['edge_w1'], 'ardis'), $code($it['edge_w2'], 'ardis'),
            ];
        }
        return $rows;
    }

    $rows = [['Pjovimas', 'Medžiaga', 'Ilgis', 'Plotis', 'Kiekis', 'Aprašymas', 'Ilgis1', 'Ilgis2', 'Plotis3', 'Plotis4',
        'Tekstura', 'Barkodas1', 'INFO1', 'Gam pastaba', 'Storis']];
    foreach ($order['items'] as $it) {
        $rows[] = [
            'BIESSE_1', $it['board'], $num($it['length']), $num($it['width']), $it['qty'],
            $it['offcut_id'] ? 'Likutis #' . $it['offcut_id'] : '',
            $code($it['edge_l1'], 'om'), $code($it['edge_l2'], 'om'), $code($it['edge_w1'], 'om'), $code($it['edge_w2'], 'om'),
            '', $it['cnc'], '', $it['note'], $num($it['thickness']),
        ];
    }
    return $rows;
}

function send_csv(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, $row, ';', '"', '');
    }
    fclose($out);
    exit;
}
