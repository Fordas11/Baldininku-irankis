<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Likučiai klientams nerodomi – katalogą mato tik administratorius.
require_admin();

$filter = [
    'status'     => 'available',
    'board'      => trim((string) ($_GET['board'] ?? '')),
    'thickness'  => (string) ($_GET['thickness'] ?? ''),
    'min_length' => (string) ($_GET['min_length'] ?? ''),
    'min_width'  => (string) ($_GET['min_width'] ?? ''),
];
$offcuts = list_offcuts($filter);
$boards = offcut_boards();
$thicknesses = array_unique(array_map(static fn ($b) => 0 + $b['thickness'], $boards));
sort($thicknesses);

render_header('Likučių katalogas', 'page-offcuts nav-offcuts');
?>
<main class="wrap">
  <div class="intro">
    <div>
      <h1>Plokščių likučių katalogas</h1>
      <p>Po pjovimo likę plokščių gabalai – tinka mažesnėms detalėms. Pasirinkite likutį, ir jūsų detalės bus pjaunamos iš jo, o ne iš naujos plokštės. Matmenys – faktiniai, ilgis nurodo tekstūros kryptį.</p>
    </div>
  </div>

  <form class="card offcut-filter" method="get">
    <label class="field field--wide"><span>Plokštė</span>
      <select name="board">
        <option value="">Visos plokštės</option>
        <?php foreach (array_unique(array_column($boards, 'board')) as $b): ?>
          <option value="<?= e($b) ?>" <?= mb_strtolower($b) === mb_strtolower($filter['board']) ? 'selected' : '' ?>><?= e($b) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>Storis, mm</span>
      <select name="thickness">
        <option value="">Visi</option>
        <?php foreach ($thicknesses as $t): ?>
          <option value="<?= e($t) ?>" <?= (string) $t === $filter['thickness'] ? 'selected' : '' ?>><?= fmt_num((float) $t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field"><span>Ilgis bent, mm</span><input name="min_length" inputmode="numeric" value="<?= e($filter['min_length']) ?>" placeholder="pvz. 600"></label>
    <label class="field"><span>Plotis bent, mm</span><input name="min_width" inputmode="numeric" value="<?= e($filter['min_width']) ?>" placeholder="pvz. 300"></label>
    <div class="field field--button"><button class="btn btn--primary">Rodyti</button></div>
  </form>

  <p class="muted"><?= count($offcuts) ?> likučiai<?= array_filter(array_slice($filter, 1)) ? ' pagal filtrą · <a href="likuciai.php">rodyti visus</a>' : '' ?></p>

  <?php if (!$offcuts): ?>
    <section class="card empty">Šiuo metu tinkamų likučių nėra. Pabandykite pakeisti filtrą arba užsakykite pjovimą iš naujos plokštės.</section>
  <?php else: ?>
    <div class="offcut-grid">
      <?php foreach ($offcuts as $o): ?>
        <article class="offcut-card">
          <div class="offcut-card__shape"><?= board_svg(['length' => $o['length'], 'width' => $o['width'], 'edge_l1' => '', 'edge_l2' => '', 'edge_w1' => '', 'edge_w2' => '']) ?></div>
          <div class="offcut-card__body">
            <span class="offcut-card__id">Likutis #<?= (int) $o['id'] ?></span>
            <h2><?= e($o['board']) ?></h2>
            <p class="offcut-card__dims"><?= fmt_num((float) $o['length']) ?> × <?= fmt_num((float) $o['width']) ?> mm <span>· <?= fmt_num((float) $o['thickness']) ?> mm</span></p>
            <p class="muted small"><?= fmt_num($o['length'] * $o['width'] / 1e6) ?> m²<?= $o['note'] ? ' · ' . e($o['note']) : '' ?></p>
          </div>
          <a class="btn btn--primary-outline" href="index.php?offcut=<?= (int) $o['id'] ?>">Pjauti iš šio likučio</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php render_footer(); ?>
