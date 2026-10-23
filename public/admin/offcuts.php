<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

$errors = [];
$form = [
    'board_id' => '', 'length' => '', 'width' => '', 'location' => '',
    'note' => '', 'admin_note' => '', 'source_order_id' => '', 'status' => 'available', 'count' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf($_POST['csrf'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $id) {
        delete_offcut($id);
        header('Location: offcuts.php?msg=deleted');
        exit;
    }
    if ($action === 'status' && $id && ($off = find_offcut($id))) {
        save_offcut(['status' => $_POST['status'] ?? 'available'] + $off, $id);
        header('Location: offcuts.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'q']))));
        exit;
    }
    if ($action === 'save') {
        [$data, $errors] = validate_offcut($_POST);
        if (!$errors) {
            save_offcut($data, $id ?: null, (int) ($_POST['count'] ?? 1));
            header('Location: offcuts.php?msg=' . ($id ? 'updated' : 'added'));
            exit;
        }
        $form = $_POST + $form;
    }
} elseif (!empty($_GET['edit']) && ($off = find_offcut((int) $_GET['edit']))) {
    $form = $off + $form;
} else {
    // Atėjus iš užsakymo („Registruoti likutį“) – plokštė ir storis užpildomi iš anksto.
    foreach (['board_id', 'source_order_id'] as $key) {
        if (isset($_GET[$key])) {
            $form[$key] = (string) $_GET[$key];
        }
    }
}

$filter = ['status' => (string) ($_GET['status'] ?? 'available'), 'q' => trim((string) ($_GET['q'] ?? ''))];
$offcuts = list_offcuts($filter);
$editing = !empty($form['id']);
$messages = ['added' => 'Likutis užregistruotas.', 'updated' => 'Likutis atnaujintas.', 'deleted' => 'Likutis ištrintas.'];
$counts = db()->query('SELECT status, COUNT(*) AS n, COALESCE(SUM(length * width), 0) / 1000000.0 AS area FROM offcuts GROUP BY status')
    ->fetchAll(PDO::FETCH_UNIQUE);
$catalog = list_boards();

render_header('Likučių sandėlis', 'page-admin nav-admin-offcuts');
?>
<main class="wrap">
  <div class="admin-head">
    <h1>Likučių sandėlis</h1>
    <div class="stat-row">
      <?php foreach (OFFCUT_STATUSES as $key => $label): ?>
        <span class="status status--offcut-<?= $key ?>"><?= e($label) ?>: <?= (int) ($counts[$key]['n'] ?? 0) ?> vnt. · <?= fmt_num((float) ($counts[$key]['area'] ?? 0)) ?> m²</span>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (isset($messages[$_GET['msg'] ?? ''])): ?><p class="saved"><?= e($messages[$_GET['msg']]) ?></p><?php endif; ?>

  <form class="card" method="post" id="offcut-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><?php endif; ?>
    <h2><?= $editing ? 'Redaguoti likutį #' . (int) $form['id'] : 'Registruoti likutį' ?></h2>
    <div class="grid grid--offcut">
      <label class="field field--wide"><span>Plokštė (kodas, struktūra, storis) <em>*</em></span>
        <select name="board_id" class="<?= isset($errors['board_id']) ? 'invalid' : '' ?>" required>
          <option value="">— pasirinkite —</option>
          <?php foreach ($catalog as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (string) $form['board_id'] === (string) $b['id'] ? 'selected' : '' ?>>
              <?= e(board_label($b) . ' · ' . fmt_num((float) $b['thickness']) . ' mm' . ($b['name'] ? ' – ' . $b['name'] : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Ilgis, mm <em>*</em> <small class="muted">(tekstūros kryptis)</small></span>
        <input name="length" inputmode="decimal" value="<?= e($form['length']) ?>" class="<?= isset($errors['length']) ? 'invalid' : '' ?>">
      </label>
      <label class="field"><span>Plotis, mm <em>*</em></span>
        <input name="width" inputmode="decimal" value="<?= e($form['width']) ?>" class="<?= isset($errors['width']) ? 'invalid' : '' ?>">
      </label>
      <?php if ($editing): ?>
        <label class="field"><span>Būsena</span>
          <select name="status">
            <?php foreach (OFFCUT_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php else: ?>
        <label class="field"><span>Kiekis (vienodų gabalų)</span>
          <input name="count" type="number" min="1" max="100" value="<?= (int) $form['count'] ?>">
        </label>
      <?php endif; ?>
      <label class="field"><span>Vieta sandėlyje</span>
        <input name="location" value="<?= e($form['location']) ?>" placeholder="pvz. A-3">
      </label>
      <label class="field"><span>Iš užsakymo Nr.</span>
        <input name="source_order_id" inputmode="numeric" value="<?= e($form['source_order_id']) ?>">
      </label>
      <label class="field field--wide"><span>Aprašymas klientams</span>
        <input name="note" value="<?= e($form['note']) ?>" placeholder="pvz. viename kampe įbrėžimas">
      </label>
      <label class="field field--wide"><span>Vidinė pastaba</span>
        <input name="admin_note" value="<?= e($form['admin_note']) ?>">
      </label>
    </div>
    <?php if ($errors): ?><p class="form-error"><?= e(implode(' ', $errors)) ?></p><?php endif; ?>
    <div class="form-actions">
      <button class="btn btn--primary"><?= $editing ? 'Išsaugoti' : 'Įtraukti į katalogą' ?></button>
      <?php if ($editing): ?><a class="btn btn--ghost" href="offcuts.php">Atšaukti</a><?php endif; ?>
    </div>
  </form>

  <form class="admin-filter admin-filter--bar" method="get">
    <input type="search" name="q" value="<?= e($filter['q']) ?>" placeholder="Plokštė, vieta, Nr.">
    <select name="status" onchange="this.form.submit()">
      <option value="">Visos būsenos</option>
      <?php foreach (OFFCUT_STATUSES as $key => $label): ?>
        <option value="<?= $key ?>" <?= $filter['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn--ghost">Ieškoti</button>
  </form>

  <section class="card card--flush">
    <?php if (!$offcuts): ?>
      <p class="empty">Likučių nėra.</p>
    <?php else: ?>
      <table class="admin-table admin-table--static">
        <thead>
          <tr><th>Nr.</th><th></th><th>Plokštė</th><th>Storis</th><th>Ilgis × plotis</th><th>Plotas</th><th>Vieta</th><th>Kilmė / rezervacija</th><th>Būsena</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($offcuts as $o): ?>
            <tr>
              <td>#<?= (int) $o['id'] ?></td>
              <td class="t-schema"><?= board_svg(['length' => $o['length'], 'width' => $o['width'], 'edge_l1' => '', 'edge_l2' => '', 'edge_w1' => '', 'edge_w2' => '']) ?></td>
              <td><b><?= e($o['board']) ?></b><?= $o['note'] ? '<br><small>' . e($o['note']) . '</small>' : '' ?><?= $o['admin_note'] ? '<br><small class="muted">' . e($o['admin_note']) . '</small>' : '' ?></td>
              <td><?= fmt_num((float) $o['thickness']) ?> mm</td>
              <td class="nowrap"><?= fmt_num((float) $o['length']) ?> × <?= fmt_num((float) $o['width']) ?> mm</td>
              <td><?= fmt_num($o['length'] * $o['width'] / 1e6) ?> m²</td>
              <td><?= e($o['location'] ?: '—') ?></td>
              <td class="small">
                <?= $o['source_order_id'] ? 'iš <a href="order.php?id=' . (int) $o['source_order_id'] . '">#' . (int) $o['source_order_id'] . '</a>' : '' ?>
                <?= $o['reserved_order_id'] ? '<br>užsakymui <a href="order.php?id=' . (int) $o['reserved_order_id'] . '">#' . (int) $o['reserved_order_id'] . '</a>' : '' ?>
              </td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                  <select name="status" onchange="this.form.submit()" aria-label="Likučio #<?= (int) $o['id'] ?> būsena">
                    <?php foreach (OFFCUT_STATUSES as $key => $label): ?>
                      <option value="<?= $key ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="nowrap">
                <a class="icon-btn" href="offcuts.php?edit=<?= (int) $o['id'] ?>#offcut-form" title="Redaguoti" aria-label="Redaguoti likutį #<?= (int) $o['id'] ?>">
                  <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 16h3l8.5-8.5-3-3L4 13v3zM11 6l3 3"/></svg></a>
                <form method="post" class="inline-form" onsubmit="return confirm('Ištrinti likutį #<?= (int) $o['id'] ?>?')">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                  <button class="icon-btn icon-btn--danger" title="Ištrinti" aria-label="Ištrinti likutį #<?= (int) $o['id'] ?>">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
<?php render_footer(); ?>
