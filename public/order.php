<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$order = find_order_for_viewer((int) ($_GET['id'] ?? 0), $_GET['t'] ?? null);
if ($order === null) {
    http_response_code(404);
    render_header('Užsakymas nerastas');
    echo '<main class="wrap"><section class="card"><h1>Užsakymas nerastas</h1><p>Patikrinkite nuorodą arba susisiekite su mumis.</p></section></main>';
    render_footer();
    exit;
}

render_header('Užsakymas Nr. ' . $order['id'], 'page-sheet');
?>
<main class="wrap">
  <div class="sheet-actions no-print">
    <a class="btn btn--ghost" href="index.php">← Naujas užsakymas</a>
    <span class="status status--<?= e($order['status']) ?>"><?= e(config('statuses')[$order['status']] ?? $order['status']) ?></span>
    <button type="button" class="btn btn--primary" onclick="window.print()">Spausdinti</button>
  </div>
  <?php render_order_sheet($order); ?>
</main>
<?php render_footer(); ?>
