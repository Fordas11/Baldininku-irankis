<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_csrf($_POST['csrf'] ?? null)) {
    if (($_POST['action'] ?? '') === 'offcut') {
        $error = assign_offcut_to_item($id, (int) ($_POST['item_id'] ?? 0), (int) ($_POST['offcut_id'] ?? 0) ?: null);
        header('Location: order.php?id=' . $id . ($error ? '&offcut_error=' . rawurlencode($error) : '&offcut_saved=1') . '#offcut-use');
        exit;
    }
    update_order_admin($id, $_POST);
    header('Location: order.php?id=' . $id . '&saved=1');
    exit;
}

$order = find_order($id);
if ($order === null) {
    http_response_code(404);
    exit('Užsakymas nerastas.');
}

render_header('Užsakymas Nr. ' . $order['id'], 'page-sheet page-admin nav-admin-orders');
?>
<main class="wrap">
  <div class="sheet-actions no-print">
    <a class="btn btn--ghost" href="index.php">← Visi užsakymai</a>
    <a class="btn btn--ghost" href="export.php?id=<?= (int) $order['id'] ?>&amp;format=ardis">CSV → Ardis</a>
    <a class="btn btn--ghost" href="export.php?id=<?= (int) $order['id'] ?>&amp;format=om">CSV → OM / Biesse</a>
    <button type="button" class="btn btn--primary" onclick="window.print()">Spausdinti</button>
  </div>

  <form class="card admin-edit no-print" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <h2>Pildo įmonė</h2>
    <div class="grid grid--5">
      <label class="field"><span>Užsakymo Nr.</span><input name="internal_order_no" value="<?= e($order['internal_order_no']) ?>"></label>
      <label class="field"><span>Data</span><input type="date" name="internal_date" value="<?= e($order['internal_date'] ?: date('Y-m-d')) ?>"></label>
      <label class="field"><span>Įvykdymo data</span><input type="date" name="internal_due_date" value="<?= e($order['internal_due_date']) ?>"></label>
      <label class="field"><span>Statusas</span>
        <select name="status">
          <?php foreach (config('statuses') as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="field field--button"><button class="btn btn--primary">Išsaugoti</button></div>
    </div>
    <label class="field"><span>Vidinė pastaba (klientas nemato)</span><textarea name="admin_note" rows="2"><?= e($order['admin_note']) ?></textarea></label>
    <?php if (!empty($_GET['saved'])): ?><p class="saved">✓ Išsaugota</p><?php endif; ?>
    <p class="muted small">Kliento nuoroda: <a href="../order.php?id=<?= (int) $order['id'] ?>&amp;t=<?= e($order['token']) ?>" target="_blank">order.php?id=<?= (int) $order['id'] ?>&amp;t=…</a></p>
  </form>

  <?php $canUseOffcuts = !in_array($order['status'], ['done', 'cancelled'], true); ?>
  <section class="card no-print offcut-use" id="offcut-use">
    <h2>Pjauti iš likučių</h2>
    <p class="muted small">Detalėms, kurios telpa į sandėlio likučius, galite priskirti likutį – jis bus rezervuotas šiam užsakymui. Klientas likučių nemato, užsakymo kaina nekeičiama.</p>
    <?php if (!empty($_GET['offcut_error'])): ?><p class="form-error"><?= e($_GET['offcut_error']) ?></p><?php endif; ?>
    <?php if (!empty($_GET['offcut_saved'])): ?><p class="saved">✓ Išsaugota</p><?php endif; ?>
    <table class="admin-table admin-table--static">
      <thead><tr><th>Nr.</th><th>Plokštė</th><th>Detalė, mm</th><th>Kiekis</th><th>Likutis</th></tr></thead>
      <tbody>
        <?php foreach ($order['items'] as $it): ?>
          <tr>
            <td><?= (int) $it['position'] ?></td>
            <td><?= e($it['board']) ?> · <?= fmt_num((float) $it['thickness']) ?> mm</td>
            <td><?= fmt_num((float) $it['length']) ?> × <?= fmt_num((float) $it['width']) ?></td>
            <td><?= (int) $it['qty'] ?></td>
            <td>
              <?php if ($it['offcut_id']): ?>
                <form method="post" class="offcut-use__form">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="offcut">
                  <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                  <span class="offcut-tag">♻ Likutis #<?= (int) $it['offcut_id'] ?> · <?= fmt_num((float) $it['offcut_length']) ?> × <?= fmt_num((float) $it['offcut_width']) ?><?= $it['offcut_location'] ? ', vieta ' . e($it['offcut_location']) : '' ?></span>
                  <?php if ($canUseOffcuts): ?><button class="btn btn--ghost">Atsisakyti</button><?php endif; ?>
                </form>
              <?php elseif (!$canUseOffcuts || !$it['board_id']): ?>
                <span class="muted">—</span>
              <?php elseif (!($matches = matching_offcuts((int) $it['board_id'], (float) $it['length'], (float) $it['width']))): ?>
                <span class="muted">Tinkamų likučių nėra</span>
              <?php else: ?>
                <form method="post" class="offcut-use__form">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="offcut">
                  <input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
                  <select name="offcut_id" aria-label="Likutis detalei Nr. <?= (int) $it['position'] ?>">
                    <?php foreach ($matches as $m): ?>
                      <option value="<?= (int) $m['id'] ?>">#<?= (int) $m['id'] ?> · <?= fmt_num((float) $m['length']) ?> × <?= fmt_num((float) $m['width']) ?><?= $m['location'] ? ' · vieta ' . e($m['location']) : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn--primary-outline">Naudoti</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card no-print offcut-register">
    <h2>Likučiai iš šio užsakymo</h2>
    <p class="muted small">
      <?= $order['return_offcuts'] ? 'Klientas prašė grąžinti atraižas – į katalogą registruokite tik tai, ko klientas neatsiims.' : 'Klientas atraižų neatsiima – tinkamus likučius užregistruokite kataloge, kad juos galėtų panaudoti kiti klientai.' ?>
    </p>
    <div class="offcut-register__links">
      <?php
      $boards = [];
      foreach ($order['items'] as $it) {
          if ($it['board_id']) {
              $boards[$it['board_id']] = $it;
          }
      }
      foreach ($boards as $it): ?>
        <a class="btn btn--ghost" href="offcuts.php?<?= e(http_build_query(['board_id' => $it['board_id'], 'source_order_id' => $order['id']])) ?>#offcut-form">+ <?= e($it['board']) ?> · <?= fmt_num((float) $it['thickness']) ?> mm</a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php render_order_sheet($order); ?>
</main>
<?php render_footer(); ?>
