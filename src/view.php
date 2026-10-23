<?php
declare(strict_types=1);

/** Maža detalės schema su briaunomis (tas pats vaizdas kaip formoje, tik be sąveikos). */
function board_svg(array $item): string
{
    $W = 64;
    $H = 40;
    $pad = 6;
    $L = (float) $item['length'];
    $Wd = (float) $item['width'];
    $ratio = $L > 0 && $Wd > 0 ? $L / $Wd : 1.6;
    $w = $W - 2 * $pad;
    $h = $w / $ratio;
    if ($h > $H - 2 * $pad) {
        $h = $H - 2 * $pad;
        $w = $h * $ratio;
    }
    $w = max($w, 8);
    $h = max($h, 8);
    $x = ($W - $w) / 2;
    $y = ($H - $h) / 2;
    $lines = [
        'l1' => [$x, $y, $x + $w, $y],
        'l2' => [$x, $y + $h, $x + $w, $y + $h],
        'w1' => [$x, $y, $x, $y + $h],
        'w2' => [$x + $w, $y, $x + $w, $y + $h],
    ];
    $svg = sprintf('<svg class="board-svg board-svg--print" viewBox="0 0 %d %d" aria-hidden="true"><rect class="board-face" x="%.1f" y="%.1f" width="%.1f" height="%.1f"/>', $W, $H, $x, $y, $w, $h);
    foreach ($lines as $side => [$a, $b, $c, $d]) {
        $edge = $item['edge_' . $side];
        $svg .= $edge !== ''
            ? sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="3.5"/>', $a, $b, $c, $d, e(edge_types()[$edge]['color'] ?? '#888'))
            : sprintf('<line class="edge-off" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $a, $b, $c, $d);
    }
    return $svg . '</svg>';
}

function edge_cell(string $id): string
{
    if ($id === '') {
        return '<td class="edge-val"></td>';
    }
    $t = edge_types()[$id];
    return '<td class="edge-val" title="' . e(edge_label($id)) . '"><i style="background:' . e($t['color']) . '"></i>'
        . e(str_replace('.', ',', $id)) . '</td>';
}

/** Užsakymo lapas – išdėstymas kaip Excel formoje, tinka spausdinti. */
function render_order_sheet(array $o): void
{
    $branch = config('branch');
    $summary = order_summary($o['items']);
    $check = static fn (bool $on): string => '<span class="box">' . ($on ? '✓' : '') . '</span>';
    ?>
<article class="sheet">
  <div class="sheet__top">
    <div class="sheet__issuer">
      <div><b class="sheet__company"><?= e($branch['company']) ?></b><br><b><?= e(mb_strtoupper($branch['name'])) ?></b><br><?= e($branch['address']) ?><br>Tel. <?= e($branch['phone']) ?></div>
    </div>
    <div class="sheet__block">
      <h3>Užsakymas <small>(pildo įmonė)</small></h3>
      <dl>
        <dt>Užsakymo Nr.</dt><dd><?= e($o['internal_order_no'] ?: '—') ?></dd>
        <dt>Data</dt><dd><?= e($o['internal_date'] ?: '—') ?></dd>
        <dt>Įvykdymo data</dt><dd><?= e($o['internal_due_date'] ?: '—') ?></dd>
        <dt>Web užsakymas</dt><dd>Nr. <?= (int) $o['id'] ?>, <?= e(substr($o['created_at'], 0, 16)) ?></dd>
      </dl>
    </div>
    <div class="sheet__block">
      <h3>Užsakovas <small>(pildo klientas)</small></h3>
      <dl>
        <dt>V. Pavardė</dt><dd><?= e($o['contact_name']) ?></dd>
        <dt>Įmonės pavadinimas</dt><dd><?= e($o['company'] ?: '—') ?></dd>
        <dt>Įmonės kodas</dt><dd><?= e($o['company_code'] ?: '—') ?></dd>
        <dt>Telefonas</dt><dd><?= e($o['phone']) ?></dd>
        <?php if ($o['email']): ?><dt>El. paštas</dt><dd><?= e($o['email']) ?></dd><?php endif; ?>
      </dl>
      <ul class="sheet__checks">
        <li><?= $check($o['delivery'] === 'pickup') ?> Atsiimsiu pats</li>
        <li><?= $check($o['delivery'] === 'delivery') ?> Pristatyti adresu<?= $o['delivery_address'] ? ': <b>' . e($o['delivery_address']) . '</b>' : '' ?></li>
        <li><?= $check((bool) $o['return_offcuts']) ?> Grąžinti atraižas</li>
        <li><?= $check($o['packaging'] === 'standard') ?> Standartinis užsakymo pakavimas</li>
        <li><?= $check($o['packaging'] === 'none') ?> Užsakymo nepakuoti</li>
        <li><?= $check($o['packaging'] === 'special') ?> Spec. pakavimo sąlygos<?= $o['packaging_note'] ? ': <b>' . e($o['packaging_note']) . '</b>' : '' ?></li>
      </ul>
    </div>
  </div>

  <div class="sheet__notes">
    <div><b>Svarbu!</b> Ilgis nurodo tekstūros kryptį · Pjovimo paklaida ± 1 mm · Detalių matmenys pateikti galutiniai su kantu</div>
    <div class="sheet__legend">Kantų žymėjimas:
      <?php foreach (edge_types() as $id => $t): ?>
        <span><i style="background:<?= e($t['color']) ?>"></i><b><?= e(str_replace('.', ',', (string) $id)) ?></b> – <?= e(edge_label((string) $id)) ?>, <?= $t['min_thickness'] ? $t['min_thickness'] . '–' . $t['max_thickness'] : 'iki ' . $t['max_thickness'] ?> mm plokštei</span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sheet__table-wrap">
  <table class="sheet__table">
    <thead>
      <tr>
        <th rowspan="2">Nr.</th><th rowspan="2">Plokštės pavadinimas</th><th rowspan="2">Storis, mm</th>
        <th colspan="3">Detalių matmenys, mm</th><th colspan="5">Briaunavimas</th>
        <th rowspan="2">Pastabos</th><th rowspan="2">CNC kodas</th>
      </tr>
      <tr><th>Ilgis</th><th>Plotis</th><th>Kiekis</th><th>Ilgis</th><th>Ilgis</th><th>Plotis</th><th>Plotis</th><th>Schema</th></tr>
    </thead>
    <tbody>
      <?php foreach ($o['items'] as $it): ?>
        <tr>
          <td><?= (int) $it['position'] ?></td>
          <td class="t-left"><?= e($it['board']) ?></td>
          <td><?= fmt_num((float) $it['thickness']) ?></td>
          <td><?= fmt_num((float) $it['length']) ?></td>
          <td><?= fmt_num((float) $it['width']) ?></td>
          <td><?= (int) $it['qty'] ?></td>
          <?= edge_cell($it['edge_l1']) . edge_cell($it['edge_l2']) . edge_cell($it['edge_w1']) . edge_cell($it['edge_w2']) ?>
          <td class="t-schema"><?= board_svg($it) ?></td>
          <td class="t-left">
            <?php if ($it['offcut_id'] && is_admin()): ?>
              <span class="offcut-tag">♻ Iš likučio #<?= (int) $it['offcut_id'] ?><?= is_admin() && $it['offcut_location'] ? ', vieta ' . e($it['offcut_location']) : '' ?></span>
            <?php endif; ?>
            <?= e($it['note']) ?>
          </td>
          <td><?= e($it['cnc']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div class="sheet__bottom">
    <div class="sheet__summary">
      <h3>Suvestinė</h3>
      <p>Detalių: <b><?= $summary['parts'] ?> vnt.</b></p>
      <?php foreach ($summary['boards'] as $name => $area): ?>
        <p><?= e($name) ?>: <b><?= fmt_num($area, 2) ?> m²</b></p>
      <?php endforeach; ?>
      <?php foreach ($summary['edges'] as $id => $m): ?>
        <p><?= e(edge_label((string) $id)) ?>: <b><?= fmt_num($m, 1) ?> m</b></p>
      <?php endforeach; ?>
    </div>
    <?php if ($o['comment'] !== '' || $o['files']): ?>
      <div class="sheet__summary">
        <h3>Pastabos ir brėžiniai</h3>
        <?php if ($o['comment'] !== ''): ?><p class="pre"><?= e($o['comment']) ?></p><?php endif; ?>
        <?php if ($o['files']): ?>
          <ul class="sheet__files">
            <?php foreach ($o['files'] as $f): ?>
              <li><a href="<?= str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '' ?>file.php?id=<?= (int) $f['id'] ?>&amp;t=<?= e($o['token']) ?>"><?= e($f['original_name']) ?></a> <small>(<?= fmt_num($f['size'] / 1048576, 1) ?> MB)</small></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php $quote = json_decode((string) ($o['quote_json'] ?? ''), true); ?>
  <?php if (!empty($quote['lines'])): ?>
    <div class="sheet__price">
      <h3>Kaina</h3>
      <table class="quote__table">
        <thead><tr><th>Pozicija</th><th class="num">Kiekis</th><th class="num">Kaina be PVM</th><th class="num">Suma</th></tr></thead>
        <tbody>
          <?php foreach ($quote['lines'] as $l): ?>
            <tr>
              <td><?= e($l['label']) ?><?= $l['detail'] ? '<small>' . e($l['detail']) . '</small>' : '' ?></td>
              <td class="num"><?= fmt_num((float) $l['qty'], 3) ?> <?= e($l['unit']) ?></td>
              <td class="num"><?= fmt_money((float) $l['price']) ?></td>
              <td class="num"><?= fmt_money((float) $l['sum']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3">Suma be PVM</td><td class="num"><?= fmt_money((float) $quote['subtotal']) ?></td></tr>
          <tr><td colspan="3">PVM <?= fmt_num((float) $quote['vat_percent']) ?> %</td><td class="num"><?= fmt_money((float) $quote['vat']) ?></td></tr>
          <tr class="quote__total"><td colspan="3">Iš viso su PVM</td><td class="num"><?= fmt_money((float) $quote['total']) ?></td></tr>
        </tfoot>
      </table>
      <?php if (!empty($quote['warnings'])): ?>
        <ul class="quote__warnings"><?php foreach ($quote['warnings'] as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</article>
<?php
}
