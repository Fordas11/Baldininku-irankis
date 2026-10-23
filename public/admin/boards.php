<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

if (($_GET['export'] ?? '') === 'csv') {
    send_csv('plokstes-' . date('Y-m-d') . '.csv', boards_csv_rows());
}

$errors = [];
$notice = '';
$form = ['type' => 'LMDP', 'manufacturer' => '', 'code' => '', 'name' => '', 'structure' => '', 'thickness' => '18',
    'sheet_length' => '2800', 'sheet_width' => '2070', 'grain' => 1, 'price_sheet' => '', 'stock' => '0', 'active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'save') {
        [$data, $errors] = validate_board($_POST);
        if (!$errors) {
            save_board($data, $id ?: null);
            header('Location: boards.php?msg=saved');
            exit;
        }
        $form = $_POST + $form;
    } elseif ($action === 'delete' && $id) {
        header('Location: boards.php?msg=' . (delete_board($id) ? 'deleted' : 'deactivated'));
        exit;
    } elseif ($action === 'import' && ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        [$saved, $importErrors] = import_boards_csv($_FILES['csv']['tmp_name']);
        $notice = 'Importuota / atnaujinta plokščių: ' . $saved . '.';
        $errors = $importErrors;
    }
} elseif (!empty($_GET['edit']) && ($b = find_board((int) $_GET['edit']))) {
    $form = $b;
}

$search = trim((string) ($_GET['q'] ?? ''));
$boards = list_boards(false, $search);
$editing = !empty($form['id']);
$messages = ['saved' => 'Plokštė išsaugota.', 'deleted' => 'Plokštė ištrinta.',
    'deactivated' => 'Plokštė naudojama užsakymuose, todėl ne ištrinta, o išjungta.'];

render_header('Plokščių katalogas', 'page-admin nav-admin-boards');
?>
<main class="wrap">
  <div class="admin-head">
    <h1>Plokščių katalogas ir sandėlis</h1>
    <div class="admin-filter">
      <a class="btn btn--ghost" href="boards.php?export=csv">Eksportuoti CSV</a>
    </div>
  </div>

  <?php if (isset($messages[$_GET['msg'] ?? ''])): ?><p class="saved"><?= e($messages[$_GET['msg']]) ?></p><?php endif; ?>
  <?php if ($notice): ?><p class="saved"><?= e($notice) ?></p><?php endif; ?>
  <?php if ($errors): ?><div class="form-error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

  <div class="grid grid--admin-2">
    <form class="card" method="post" id="board-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><?php endif; ?>
      <h2><?= $editing ? 'Redaguoti: ' . e(board_label($form) . ' · ' . fmt_num((float) $form['thickness']) . ' mm') : 'Nauja plokštė' ?></h2>
      <div class="grid grid--4">
        <label class="field"><span>Tipas</span><input name="type" value="<?= e($form['type']) ?>" list="board-types"></label>
        <label class="field"><span>Gamintojas</span><input name="manufacturer" value="<?= e($form['manufacturer']) ?>" placeholder="Egger"></label>
        <label class="field"><span>Dekoro kodas <em>*</em></span><input name="code" value="<?= e($form['code']) ?>" placeholder="W1000" required></label>
        <label class="field"><span>Struktūra</span><input name="structure" value="<?= e($form['structure']) ?>" placeholder="ST9"></label>
        <label class="field field--wide"><span>Dekoro pavadinimas</span><input name="name" value="<?= e($form['name']) ?>" placeholder="Premium White"></label>
        <label class="field"><span>Storis, mm <em>*</em></span><input name="thickness" inputmode="decimal" value="<?= e(0 + (float) $form['thickness']) ?>"></label>
        <label class="field"><span>Kaina €/lapą be PVM <em>*</em></span><input name="price_sheet" inputmode="decimal" value="<?= e($form['price_sheet']) ?>"></label>
        <label class="field"><span>Plokštės ilgis, mm</span><input name="sheet_length" inputmode="numeric" value="<?= e(0 + (float) $form['sheet_length']) ?>"></label>
        <label class="field"><span>Plokštės plotis, mm</span><input name="sheet_width" inputmode="numeric" value="<?= e(0 + (float) $form['sheet_width']) ?>"></label>
        <label class="field"><span>Sandėlyje, lap.</span><input name="stock" type="number" min="0" value="<?= (int) $form['stock'] ?>"></label>
        <div class="field field--checks">
          <input type="hidden" name="grain" value="0">
          <label class="choice"><input type="checkbox" name="grain" value="1" <?= $form['grain'] ? 'checked' : '' ?>> Turi tekstūrą (detalių nesukti)</label>
          <input type="hidden" name="active" value="0">
          <label class="choice"><input type="checkbox" name="active" value="1" <?= $form['active'] ? 'checked' : '' ?>> Rodoma klientams</label>
        </div>
      </div>
      <datalist id="board-types"><option value="LMDP"><option value="MDF"><option value="HDF"><option value="Fanera"><option value="Kompaktinė"></datalist>
      <div class="form-actions">
        <button class="btn btn--primary"><?= $editing ? 'Išsaugoti' : 'Pridėti' ?></button>
        <?php if ($editing): ?><a class="btn btn--ghost" href="boards.php">Atšaukti</a><?php endif; ?>
      </div>
    </form>

    <form class="card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="import">
      <h2>CSV importas</h2>
      <p class="muted small">Stulpeliai (pirmoje eilutėje): <code><?= e(implode(';', BOARD_CSV_COLUMNS)) ?></code>.
        Ta pati plokštė (gamintojas + kodas + struktūra + storis) atnaujinama, nauja – pridedama. Kaina – € už lapą be PVM,
        <code>tekstura</code> ir <code>aktyvi</code> – 1 arba 0.</p>
      <label class="field"><span>CSV failas</span><input type="file" name="csv" accept=".csv,text/csv" required></label>
      <div class="form-actions"><button class="btn btn--primary-outline">Importuoti</button></div>
    </form>
  </div>

  <form class="admin-filter admin-filter--bar" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Kodas, pavadinimas, gamintojas">
    <button class="btn btn--ghost">Ieškoti</button>
  </form>

  <section class="card card--flush">
    <table class="admin-table admin-table--static">
      <thead>
        <tr><th>Plokštė</th><th>Pavadinimas</th><th>Tipas</th><th>Storis</th><th>Lapas, mm</th><th>Tekstūra</th><th>Kaina be PVM</th><th>€/m²</th><th>Sandėlyje</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($boards as $b): $area = $b['sheet_length'] * $b['sheet_width'] / 1e6; ?>
          <tr class="<?= $b['active'] ? '' : 'is-inactive' ?>">
            <td><b><?= e(board_label($b)) ?></b><?= $b['active'] ? '' : ' <small class="muted">(išjungta)</small>' ?></td>
            <td><?= e($b['name']) ?></td>
            <td><?= e($b['type']) ?></td>
            <td><?= fmt_num((float) $b['thickness']) ?> mm</td>
            <td class="nowrap"><?= fmt_num((float) $b['sheet_length']) ?> × <?= fmt_num((float) $b['sheet_width']) ?></td>
            <td><?= $b['grain'] ? 'Taip' : 'Ne' ?></td>
            <td class="nowrap"><?= fmt_money((float) $b['price_sheet']) ?></td>
            <td class="nowrap muted"><?= fmt_money($area > 0 ? $b['price_sheet'] / $area : 0) ?></td>
            <td><span class="status <?= $b['stock'] > 0 ? 'status--offcut-available' : 'status--new' ?>"><?= (int) $b['stock'] ?> lap.</span></td>
            <td class="nowrap">
              <a class="icon-btn" href="boards.php?edit=<?= (int) $b['id'] ?>#board-form" title="Redaguoti" aria-label="Redaguoti <?= e(board_label($b)) ?>">
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 16h3l8.5-8.5-3-3L4 13v3zM11 6l3 3"/></svg></a>
              <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti plokštę?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="icon-btn icon-btn--danger" title="Ištrinti" aria-label="Ištrinti <?= e(board_label($b)) ?>">
                  <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
<?php render_footer(); ?>
