<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

$p = pricing();
$fields = [
    'cut_price_m'             => ['Pjovimas, €/m pjūvio', 'Pjūvių ilgis apskaičiuojamas iš detalių išdėstymo ant plokščių.'],
    'edge_allowance_mm'       => ['Briaunos užlaida, mm', 'Pridedama prie kiekvieno briaunuoto krašto ilgio.'],
    'kerf_mm'                 => ['Pjūklo plotis, mm', 'Tarpas tarp detalių išdėstant.'],
    'trim_mm'                 => ['Kraštų apipjovimas, mm', 'Nuo kiekvieno plokštės krašto; 0 – neapipjaunama.'],
    'waste_percent'           => ['Atliekos, %', 'Tik kai medžiaga skaičiuojama pagal plotą.'],
    'offcut_discount_percent' => ['Likučio nuolaida, %', 'Nuo plokštės m² kainos.'],
    'delivery_price'          => ['Pristatymas, €', ''],
    'special_packaging_price' => ['Specialus pakavimas, €', ''],
    'min_order'               => ['Mažiausia užsakymo suma, €', 'Jei mažiau – pridedama priemoka.'],
    'vat_percent'             => ['PVM, %', ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf($_POST['csrf'] ?? null)) {
    foreach ($fields as $key => $_) {
        $n = parse_number($_POST[$key] ?? '');
        if ($n !== null && $n >= 0) {
            $p[$key] = $n;
        }
    }
    foreach (array_keys(edge_types()) as $id) {
        $n = parse_number($_POST['edge'][(string) $id] ?? '');
        if ($n !== null && $n >= 0) {
            $p['edge_prices_m'][(string) $id] = $n;
        }
    }
    $p['material_mode'] = ($_POST['material_mode'] ?? '') === 'area' ? 'area' : 'sheets';
    save_pricing($p);
    header('Location: prices.php?saved=1');
    exit;
}

$num = static fn ($v): string => str_replace('.', ',', (string) (0 + $v));

render_header('Kainos', 'page-admin nav-admin-prices');
?>
<main class="wrap">
  <div class="admin-head"><h1>Kainodara</h1></div>
  <?php if (!empty($_GET['saved'])): ?><p class="saved">✓ Kainos išsaugotos – skaičiuoklė jas naudoja iš karto.</p><?php endif; ?>

  <form class="card" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <p class="muted">Visos kainos – EUR be PVM. Plokščių kainos nustatomos <a href="boards.php">plokščių kataloge</a>.</p>

    <h2>Medžiagos</h2>
    <fieldset class="choice-group">
      <label class="choice"><input type="radio" name="material_mode" value="sheets" <?= $p['material_mode'] !== 'area' ? 'checked' : '' ?>>
        Pagal sunaudotas plokštes – klientas moka už visus lapus, reikalingus detalėms išdėstyti</label>
      <label class="choice"><input type="radio" name="material_mode" value="area" <?= $p['material_mode'] === 'area' ? 'checked' : '' ?>>
        Pagal detalių plotą + atliekų procentas</label>
    </fieldset>

    <h2>Darbai ir paslaugos</h2>
    <div class="grid grid--4">
      <?php foreach ($fields as $key => [$label, $hint]): ?>
        <label class="field"><span><?= e($label) ?></span>
          <input name="<?= $key ?>" inputmode="decimal" value="<?= e($num($p[$key])) ?>">
          <?php if ($hint): ?><small class="muted"><?= e($hint) ?></small><?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>

    <h2>Briaunavimas, €/m (su juosta)</h2>
    <div class="grid grid--5">
      <?php foreach (edge_types() as $id => $t): ?>
        <label class="field"><span><i class="swatch-dot" style="background:<?= e($t['color']) ?>"></i><?= e(edge_label((string) $id)) ?></span>
          <input name="edge[<?= e($id) ?>]" inputmode="decimal" value="<?= e($num($p['edge_prices_m'][(string) $id] ?? 0)) ?>">
        </label>
      <?php endforeach; ?>
    </div>

    <div class="form-actions"><button class="btn btn--primary">Išsaugoti kainas</button></div>
  </form>
</main>
<?php render_footer(); ?>
