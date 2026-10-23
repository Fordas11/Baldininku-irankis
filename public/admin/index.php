<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

start_session();
$loginError = '';
$hash = (string) config('admin_password_hash');

if (($_POST['action'] ?? '') === 'login') {
    if (!check_csrf($_POST['csrf'] ?? null)) {
        $loginError = 'Sesija pasibaigė, bandykite dar kartą.';
    } elseif ($hash !== '' && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        usleep(400000);
        $loginError = 'Neteisingas slaptažodis.';
    }
}

if (!is_admin()) {
    render_header('Administravimas', 'page-admin nav-admin-orders');
    ?>
<main class="wrap">
  <section class="card login">
    <h1>Užsakymų administravimas</h1>
    <?php if ($hash === ''): ?>
      <p class="form-error">Administratoriaus slaptažodis nenustatytas. Paleiskite <code>php tools/hash-password.php &lt;slaptažodis&gt;</code> ir įrašykite rezultatą į <code>config.local.php</code>.</p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="field"><span>Slaptažodis</span><input type="password" name="password" autofocus required></label>
        <?php if ($loginError): ?><p class="form-error"><?= e($loginError) ?></p><?php endif; ?>
        <button class="btn btn--primary">Prisijungti</button>
      </form>
    <?php endif; ?>
  </section>
</main>
<?php
    render_footer();
    exit;
}

$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$orders = list_orders($status, $search);
$statuses = config('statuses');

render_header('Užsakymai', 'page-admin nav-admin-orders');
?>
<main class="wrap">
  <div class="admin-head">
    <h1>Pjovimo užsakymai</h1>
    <form class="admin-filter" method="get">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Klientas, telefonas, Nr.">
      <select name="status" onchange="this.form.submit()">
        <option value="">Visi statusai</option>
        <?php foreach ($statuses as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost">Ieškoti</button>
      <a class="btn btn--ghost" href="logout.php">Atsijungti</a>
    </form>
  </div>

  <section class="card card--flush">
    <?php if (!$orders): ?>
      <p class="empty">Užsakymų nėra.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Nr.</th><th>Gauta</th><th>Klientas</th><th>Telefonas</th><th>Detalių</th><th>Plotas</th><th>Suma su PVM</th><th>Atsiėmimas</th><th>Vidinis Nr.</th><th>Statusas</th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr onclick="location.href='order.php?id=<?= (int) $o['id'] ?>'">
              <td><a href="order.php?id=<?= (int) $o['id'] ?>"><?= (int) $o['id'] ?></a></td>
              <td><?= e(substr($o['created_at'], 0, 16)) ?></td>
              <td><b><?= e($o['contact_name']) ?></b><?= $o['company'] ? '<br><small>' . e($o['company']) . '</small>' : '' ?></td>
              <td><?= e($o['phone']) ?></td>
              <td><?= (int) $o['parts'] ?> vnt.</td>
              <td><?= fmt_num((float) $o['area']) ?> m²</td>
              <td class="nowrap"><b><?= fmt_money((float) $o['total']) ?></b></td>
              <td><?= $o['delivery'] === 'delivery' ? 'Pristatyti' : 'Atsiims' ?></td>
              <td><?= e($o['internal_order_no']) ?></td>
              <td><span class="status status--<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
<?php render_footer(); ?>
