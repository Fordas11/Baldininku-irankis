<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Vilnius');

require __DIR__ . '/orders.php';
require __DIR__ . '/offcuts.php';
require __DIR__ . '/catalog.php';
require __DIR__ . '/pricing.php';
require __DIR__ . '/export.php';
require __DIR__ . '/xlsx_import.php';
require __DIR__ . '/view.php';

function config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config.php';
        $local = APP_ROOT . '/config.local.php';
        if (is_file($local)) {
            $config = array_replace($config, require $local);
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = config('db');
        if (str_starts_with($cfg['dsn'], 'sqlite:')) {
            $dir = dirname(substr($cfg['dsn'], 7));
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        migrate($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT NOT NULL,
        created_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "new",
        contact_name TEXT NOT NULL,
        company TEXT NOT NULL DEFAULT "",
        company_code TEXT NOT NULL DEFAULT "",
        phone TEXT NOT NULL,
        email TEXT NOT NULL DEFAULT "",
        delivery TEXT NOT NULL,
        delivery_address TEXT NOT NULL DEFAULT "",
        return_offcuts INTEGER NOT NULL DEFAULT 0,
        packaging TEXT NOT NULL,
        packaging_note TEXT NOT NULL DEFAULT "",
        comment TEXT NOT NULL DEFAULT "",
        internal_order_no TEXT NOT NULL DEFAULT "",
        internal_date TEXT NOT NULL DEFAULT "",
        internal_due_date TEXT NOT NULL DEFAULT "",
        admin_note TEXT NOT NULL DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        position INTEGER NOT NULL,
        board TEXT NOT NULL,
        thickness REAL NOT NULL,
        length REAL NOT NULL,
        width REAL NOT NULL,
        qty INTEGER NOT NULL,
        edge_l1 TEXT NOT NULL DEFAULT "",
        edge_l2 TEXT NOT NULL DEFAULT "",
        edge_w1 TEXT NOT NULL DEFAULT "",
        edge_w2 TEXT NOT NULL DEFAULT "",
        note TEXT NOT NULL DEFAULT "",
        cnc TEXT NOT NULL DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS order_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        size INTEGER NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS offcuts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        board TEXT NOT NULL,
        thickness REAL NOT NULL,
        length REAL NOT NULL,
        width REAL NOT NULL,
        location TEXT NOT NULL DEFAULT "",
        note TEXT NOT NULL DEFAULT "",
        admin_note TEXT NOT NULL DEFAULT "",
        source_order_id INTEGER NULL,
        reserved_order_id INTEGER NULL,
        status TEXT NOT NULL DEFAULT "available",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS boards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL DEFAULT "LMDP",
        manufacturer TEXT NOT NULL DEFAULT "",
        code TEXT NOT NULL,
        name TEXT NOT NULL DEFAULT "",
        structure TEXT NOT NULL DEFAULT "",
        thickness REAL NOT NULL,
        sheet_length REAL NOT NULL,
        sheet_width REAL NOT NULL,
        grain INTEGER NOT NULL DEFAULT 1,
        price_sheet REAL NOT NULL DEFAULT 0,
        stock INTEGER NOT NULL DEFAULT 0,
        active INTEGER NOT NULL DEFAULT 1
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

    // Stulpeliai, atsiradę vėlesnėse versijose.
    add_column_if_missing($pdo, 'order_items', 'offcut_id', 'INTEGER NULL');
    add_column_if_missing($pdo, 'order_items', 'board_id', 'INTEGER NULL');
    add_column_if_missing($pdo, 'offcuts', 'board_id', 'INTEGER NULL');
    add_column_if_missing($pdo, 'orders', 'quote_json', 'TEXT NOT NULL DEFAULT ""');
    add_column_if_missing($pdo, 'orders', 'total', 'REAL NOT NULL DEFAULT 0');

    seed_boards($pdo);
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    try {
        $pdo->query("SELECT $column FROM $table LIMIT 1");
    } catch (PDOException) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(?string $token): bool
{
    start_session();
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function is_admin(): bool
{
    start_session();
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Skaičius lietuvišku formatu: 1 234,5 (be nereikalingų nulių). */
function fmt_num(float $n, int $decimals = 2): string
{
    $s = number_format($n, $decimals, ',', ' ');
    return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function edge_types(): array
{
    return config('edge_types');
}

/** Briaunos pavadinimas su juostos pločiu, pvz. „PVC 2 mm (28 mm)“. */
function edge_label(string $id): string
{
    $t = edge_types()[$id] ?? null;
    if ($t === null) {
        return '';
    }
    return $t['tape'] === 22 ? $t['label'] : $t['label'] . ' (' . $t['tape'] . ' mm juosta)';
}

function edge_allowed(string $id, float $thickness): bool
{
    $t = edge_types()[$id] ?? null;
    return $t !== null && $thickness >= $t['min_thickness'] && $thickness <= $t['max_thickness'];
}

/** Konfigūracija, perduodama naršyklės skriptui. */
function client_config(): array
{
    $edges = [];
    foreach (edge_types() as $id => $t) {
        $edges[] = [
            'id'    => (string) $id,
            'label' => edge_label((string) $id),
            'short' => str_replace('.', ',', (string) $t['nominal']),
            'nominal' => $t['nominal'],
            'min'   => $t['min_thickness'],
            'max'   => $t['max_thickness'],
            'color' => $t['color'],
        ];
    }
    return [
        'edgeTypes'        => $edges,
        'boards'           => client_boards(),
        'thicknessOptions' => config('thickness_options'),
        'defaultThickness' => config('default_thickness'),
        'maxDimension'     => config('max_dimension'),
        'maxItems'         => config('max_items'),
        'upload'           => config('upload'),
        'offcuts'          => is_admin(), // likučių pasiūlymai formoje – tik administratoriui
    ];
}

function render_header(string $title, string $active = ''): void
{
    start_session(); // meniu priklauso nuo is_admin(), o sesija turi prasidėti prieš išvestį
    $branch = config('branch');
    $base = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '';
    ?>
<!doctype html>
<html lang="lt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Baldininkų įrankis</title>
<link rel="icon" href="<?= $base ?>assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= $base ?>assets/app.css?v=7">
</head>
<body class="<?= e($active) ?>">
<header class="site-header">
  <div class="wrap site-header__inner">
    <a class="brand" href="<?= $base ?>index.php">
      <span class="eyebrow">Klientams</span>
      <strong><?= e(config('app_name')) ?></strong>
    </a>
    <nav class="site-nav" aria-label="Pagrindinis meniu">
      <?php if (is_admin()): ?>
        <a href="<?= $base ?>admin/index.php" <?= str_contains($active, 'nav-admin-orders') ? 'aria-current="page"' : '' ?>>Užsakymai</a>
        <a href="<?= $base ?>admin/offcuts.php" <?= str_contains($active, 'nav-admin-offcuts') ? 'aria-current="page"' : '' ?>>Likučiai</a>
        <a href="<?= $base ?>admin/boards.php" <?= str_contains($active, 'nav-admin-boards') ? 'aria-current="page"' : '' ?>>Plokštės</a>
        <a href="<?= $base ?>admin/prices.php" <?= str_contains($active, 'nav-admin-prices') ? 'aria-current="page"' : '' ?>>Kainos</a>
      <?php endif; ?>
      <a href="<?= $base ?>index.php" <?= str_contains($active, 'nav-order') ? 'aria-current="page"' : '' ?>>Pjovimo užsakymas</a>
      <?php if (is_admin()): ?>
        <a href="<?= $base ?>likuciai.php" <?= str_contains($active, 'nav-offcuts') ? 'aria-current="page"' : '' ?>>Likučių katalogas</a>
      <?php endif; ?>
    </nav>
    <address class="branch">
      <strong><?= e($branch['company']) ?> · <?= e($branch['name']) ?></strong><br>
      <?= e($branch['address']) ?><br>
      Tel. <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $branch['phone'])) ?>"><?= e($branch['phone']) ?></a>
    </address>
  </div>
</header>
<?php
}

function render_footer(): void
{
    ?>
<footer class="site-footer"><div class="wrap">© <?= date('Y') ?> <?= e(config('branch')['company']) ?> · Pjovimo paklaida ± 1 mm · Detalių matmenys nurodomi galutiniai, su briauna</div></footer>
</body>
</html>
<?php
}
