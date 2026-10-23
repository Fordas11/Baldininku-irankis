<?php
/**
 * Pagrindiniai nustatymai. Vietinius pakeitimus (slaptažodį, el. paštą) rašyk į
 * config.local.php – jis perrašo šiuos raktus ir nekeliamas į git.
 */
return [
    'app_name' => 'Baldininkų įrankis',

    // Padalinys, kuriam siunčiamas užsakymas (rodomas formos antraštėje ir spaudinyje).
    'branch' => [
        'company' => 'UAB „Jūsų įmonė"',
        'name'    => 'Padalinys',
        'address' => 'Gatvė 1, Miestas',
        'phone'   => '+370 600 00000',
    ],

    // Duomenų bazė: SQLite failas (už public/ ribų). Lentelės sukuriamos automatiškai (src/bootstrap.php).
    'db' => [
        'dsn'      => 'sqlite:' . __DIR__ . '/data/orders.sqlite',
        'user'     => null,
        'password' => null,
    ],
    'upload_dir' => __DIR__ . '/data/uploads',

    // Administravimo slaptažodžio hash: php tools/hash-password.php <slaptažodis>
    'admin_password_hash' => '',

    // Jei nurodyta – apie kiekvieną naują užsakymą siunčiamas laiškas (PHP mail()).
    'notify_email' => '',

    // Plokščių storiai, siūlomi pasirinkti (galima įvesti ir kitą).
    'thickness_options' => [18, 25, 36],
    'default_thickness' => 18,

    // Didžiausias leidžiamas detalės matmuo, mm.
    'max_dimension' => 4200,
    'max_items'     => 500,

    /**
     * PVC / ABS briaunos (kantai). Raktas – reikšmė iš Excel formos sąrašo (J21:J25);
     * 0,4 ir 0,8 – papildomi pavyzdžiai (plona PVC vidinėms detalėms, ABS 0,8 × 22 mm).
     * min/max_thickness – kokio storio plokštėms briauna tinka (Excel: 18 mm -> 0,6/1/2,
     * 25 mm -> 2,8, 36 mm -> 4,2; tarpiniai storiai priskirti artimiausiai grupei).
     * ardis / om – kodai eksportui į pjovimo (Ardis) ir briaunavimo (OM/Biesse) programas.
     */
    'edge_types' => [
        '0.4' => ['label' => 'PVC 0,4 mm', 'nominal' => 0.4, 'tape' => 22, 'min_thickness' => 0,  'max_thickness' => 22, 'ardis' => '22_04', 'om' => '0,4', 'color' => '#8a8f98'],
        '0.6' => ['label' => 'PVC 0,6 mm', 'nominal' => 0.6, 'tape' => 22, 'min_thickness' => 0,  'max_thickness' => 22, 'ardis' => '22_06', 'om' => '0,6', 'color' => '#e3a21a'],
        '0.8' => ['label' => 'ABS 0,8 mm', 'nominal' => 0.8, 'tape' => 22, 'min_thickness' => 0,  'max_thickness' => 22, 'ardis' => '22_08', 'om' => '0,8', 'color' => '#6b4fb3'],
        '1'   => ['label' => 'PVC 1 mm',   'nominal' => 1,   'tape' => 22, 'min_thickness' => 0,  'max_thickness' => 22, 'ardis' => '22_10', 'om' => '1',   'color' => '#2a8c9c'],
        '2'   => ['label' => 'PVC 2 mm',   'nominal' => 2,   'tape' => 22, 'min_thickness' => 0,  'max_thickness' => 22, 'ardis' => '22_20', 'om' => '2',   'color' => '#c74440'],
        '2.8' => ['label' => 'PVC 2 mm',   'nominal' => 2,   'tape' => 28, 'min_thickness' => 23, 'max_thickness' => 30, 'ardis' => '22_28', 'om' => '1',   'color' => '#c74440'],
        '4.2' => ['label' => 'PVC 2 mm',   'nominal' => 2,   'tape' => 42, 'min_thickness' => 31, 'max_thickness' => 45, 'ardis' => '22_42', 'om' => '1',   'color' => '#c74440'],
    ],

    /**
     * Kainodara (numatytosios reikšmės; administratorius jas keičia admin → Kainos).
     * Visos kainos – EUR be PVM. Reikšmės PAVYZDINĖS – įrašykite tikrus savo įkainius.
     */
    'pricing' => [
        // sheets – mokama už visas sunaudotas plokštes; area – už detalių plotą + atliekų procentas
        'material_mode'      => 'sheets',
        'waste_percent'      => 15,
        'cut_price_m'        => 0.70,  // pjovimas, €/m pjūvio
        'edge_prices_m'      => ['0.4' => 0.65, '0.8' => 0.95, '0.6' => 0.80, '1' => 1.00, '2' => 1.40, '2.8' => 1.70, '4.2' => 2.30], // briaunavimas su juosta, €/m
        'edge_allowance_mm'  => 50,    // juostos užlaida kiekvienam briaunuotam kraštui
        'kerf_mm'            => 4,     // pjūklo disko plotis
        'trim_mm'            => 10,    // plokštės kraštų apipjovimas iš kiekvienos pusės
        'offcut_discount_percent' => 40, // likučio nuolaida nuo plokštės m² kainos
        'delivery_price'     => 30.00,
        'special_packaging_price' => 10.00,
        'min_order'          => 15.00, // mažiausia užsakymo suma be PVM
        'vat_percent'        => 21,
    ],

    // Brėžinių failai.
    'upload' => [
        'max_files'  => 10,
        'max_size'   => 15 * 1024 * 1024,
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'dxf', 'dwg', 'xls', 'xlsx'],
    ],

    'statuses' => [
        'new'        => 'Naujas',
        'accepted'   => 'Priimtas',
        'production' => 'Gaminamas',
        'ready'      => 'Paruoštas',
        'done'       => 'Atiduotas',
        'cancelled'  => 'Atšauktas',
    ],
];
