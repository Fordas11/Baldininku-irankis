<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$client = client_config() + ['csrf' => csrf_token()];
$edgeTypes = edge_types();

render_header('Plokščių pjovimo užsakymas', 'page-order nav-order');
?>
<main class="wrap" id="app">
  <form id="order-form" class="order-form" novalidate>
    <div class="intro">
      <h1>Plokščių pjovimo ir briaunavimo užsakymas</h1>
      <p>Užpildykite detalių sąrašą – užsakymas iškart pateks į <?= e(config('branch')['name']) ?>. Juodraštis saugomas šioje naršyklėje, tad galite grįžti ir tęsti vėliau.</p>
      <div class="intro__actions">
        <label class="btn btn--ghost" for="import-file">
          <svg aria-hidden="true" viewBox="0 0 20 20"><path d="M10 3v9m0 0-3.5-3.5M10 12l3.5-3.5M4 14v2h12v-2"/></svg>
          Įkelti iš Excel formos
        </label>
        <input type="file" id="import-file" accept=".xlsx" hidden>
        <?php if (is_admin()): ?><a class="btn btn--ghost" href="likuciai.php">♻ Likučių katalogas</a><?php endif; ?>
        <button type="button" class="btn btn--ghost" id="clear-form">Išvalyti formą</button>
      </div>
    </div>

    <section class="card">
      <h2><span class="step">1</span> Užsakovas</h2>
      <div class="grid grid--4">
        <label class="field">
          <span>Vardas, pavardė <em>*</em></span>
          <input name="contact_name" autocomplete="name" required>
        </label>
        <label class="field">
          <span>Telefono numeris <em>*</em></span>
          <input name="phone" type="tel" autocomplete="tel" required>
        </label>
        <label class="field">
          <span>El. paštas</span>
          <input name="email" type="email" autocomplete="email">
        </label>
        <div></div>
        <label class="field">
          <span>Įmonės pavadinimas</span>
          <input name="company" autocomplete="organization">
        </label>
        <label class="field">
          <span>Įmonės kodas</span>
          <input name="company_code" inputmode="numeric">
        </label>
      </div>
    </section>

    <section class="card">
      <h2><span class="step">2</span> Atsiėmimas ir pakavimas</h2>
      <div class="grid grid--2">
        <fieldset class="choice-group" data-field="delivery">
          <legend>Užsakymo atsiėmimas</legend>
          <label class="choice"><input type="radio" name="delivery" value="pickup" checked> Atsiimsiu pats</label>
          <label class="choice"><input type="radio" name="delivery" value="delivery"> Pristatyti adresu</label>
          <label class="field field--sub" data-show-when="delivery=delivery">
            <span>Pristatymo adresas <em>*</em></span>
            <input name="delivery_address" autocomplete="street-address">
          </label>
          <label class="choice choice--check"><input type="checkbox" name="return_offcuts" value="1"> Grąžinti atraižas</label>
        </fieldset>
        <fieldset class="choice-group" data-field="packaging">
          <legend>Pakavimas</legend>
          <label class="choice"><input type="radio" name="packaging" value="standard" checked> Standartinis užsakymo pakavimas</label>
          <label class="choice"><input type="radio" name="packaging" value="none"> Užsakymo nepakuoti</label>
          <label class="choice"><input type="radio" name="packaging" value="special"> Specialios pakavimo sąlygos</label>
          <label class="field field--sub" data-show-when="packaging=special">
            <span>Nurodykite sąlygas <em>*</em></span>
            <input name="packaging_note">
          </label>
        </fieldset>
      </div>
    </section>

    <section class="card card--parts">
      <div class="parts-head">
        <h2><span class="step">3</span> Detalės</h2>
        <ul class="rules">
          <li><b>Ilgis</b> nurodo tekstūros kryptį</li>
          <li>Pjovimo paklaida <b>± 1 mm</b></li>
          <li>Matmenys – <b>galutiniai, su briauna</b></li>
        </ul>
      </div>

      <div class="edge-legend" aria-label="Briaunų žymėjimas">
        <span class="edge-legend__title">Briaunavimas:</span>
        <span class="edge-legend__hint">spustelėkite detalės kraštą schemoje, kad jį pažymėtumėte, arba mygtuką <b>Briaunos</b> – išsamesniam pasirinkimui.</span>
        <span class="edge-legend__items">
          <?php foreach ($edgeTypes as $id => $t): if ($t['tape'] !== 22) continue; ?>
            <span class="swatch"><i style="background: <?= e($t['color']) ?>"></i><?= e($t['label']) ?></span>
          <?php endforeach; ?>
        </span>
      </div>

      <div class="table-scroll">
        <table class="parts" id="parts">
          <thead>
            <tr>
              <th class="c-nr">Nr.</th>
              <th class="c-name">Plokštės kodas</th>
              <th class="c-struct">Struktūra</th>
              <th class="c-num">Storis, mm</th>
              <th class="c-num">Ilgis, mm</th>
              <th class="c-num">Plotis, mm</th>
              <th class="c-qty">Kiekis</th>
              <th class="c-edge">Briaunavimas</th>
              <th class="c-note">Pastabos</th>
              <th class="c-cnc">CNC kodas</th>
              <th class="c-act"><span class="sr-only">Veiksmai</span></th>
            </tr>
          </thead>
          <tbody id="parts-body"></tbody>
        </table>
      </div>
      <div class="parts-foot">
        <div class="parts-foot__buttons">
          <button type="button" class="btn btn--primary-outline" id="add-row">+ Pridėti detalę</button>
          <button type="button" class="btn btn--ghost" id="add-rows">+ 10 eilučių</button>
        </div>
        <p class="tip">Įrašykite dekoro kodą (pvz. W1000) – struktūra ir sandėlyje esantys storiai pasiūlomi automatiškai. Eilutes galite įklijuoti iš Excel (Ctrl+V). <kbd>Enter</kbd> – kita eilutė.</p>
      </div>
      <div class="summary" id="summary" aria-live="polite"></div>
    </section>

    <section class="card">
      <h2><span class="step">4</span> Brėžiniai ir pastabos</h2>
      <p class="muted">Figūrinių detalių gabaritinius matmenis būtinai įrašykite į bendrą detalių sąrašą, o brėžinį pridėkite čia.</p>
      <div class="grid grid--2">
        <div>
          <div class="dropzone" id="dropzone">
            <input type="file" id="files" multiple accept="<?= e(implode(',', array_map(fn ($x) => '.' . $x, config('upload')['extensions']))) ?>">
            <p><b>Nutempkite failus čia</b> arba <u>pasirinkite</u></p>
            <p class="muted small"><?= e(strtoupper(implode(', ', config('upload')['extensions']))) ?> · iki <?= (int) (config('upload')['max_size'] / 1048576) ?> MB</p>
          </div>
          <ul class="file-list" id="file-list"></ul>
        </div>
        <label class="field">
          <span>Bendros pastabos užsakymui</span>
          <textarea name="comment" rows="5" placeholder="Pvz., kitos spalvos briauna, pageidaujama įvykdymo data…"></textarea>
        </label>
      </div>
    </section>

    <div class="hp" aria-hidden="true"><label>Svetainė <input name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="submit-bar">
      <div class="submit-bar__info" id="submit-info"></div>
      <button type="submit" class="btn btn--primary btn--lg" id="submit-btn">Pateikti užsakymą</button>
    </div>
    <div class="form-error" id="form-error" role="alert" hidden></div>
  </form>

  <section class="card success" id="success" hidden>
    <div class="success__icon" aria-hidden="true">✓</div>
    <h1>Užsakymas pateiktas</h1>
    <p>Jūsų užsakymo numeris: <strong id="success-id"></strong>. Užsakymo suma: <strong id="success-total"></strong> su PVM.</p>
    <p>Su jumis susisieksime patvirtinti užsakymo ir įvykdymo datos.</p>
    <p><a class="btn btn--primary" id="success-link" href="#" target="_blank">Peržiūrėti / spausdinti užsakymą</a>
       <button type="button" class="btn btn--ghost" id="new-order">Naujas užsakymas</button></p>
    <p class="muted small">Išsisaugokite nuorodą – per ją bet kada galėsite peržiūrėti užsakymą.</p>
  </section>
</main>

<dialog class="edge-dialog" id="edge-dialog" aria-labelledby="edge-dialog-title">
  <form method="dialog" class="edge-dialog__inner">
    <header>
      <h2 id="edge-dialog-title">Briaunavimas</h2>
      <p class="muted" id="edge-dialog-sub"></p>
    </header>
    <div class="edge-dialog__types" id="edge-dialog-types" role="radiogroup" aria-label="Briaunos tipas spustelėjimui"></div>
    <div class="edge-dialog__board" id="edge-dialog-board"></div>
    <div class="edge-dialog__sides" id="edge-dialog-sides"></div>
    <div class="edge-dialog__quick">
      <span class="muted">Greitai:</span>
      <button type="button" class="chip" data-quick="all">Visi kraštai</button>
      <button type="button" class="chip" data-quick="long">Abu ilgiai</button>
      <button type="button" class="chip" data-quick="short">Abu pločiai</button>
      <button type="button" class="chip" data-quick="l1">Vienas ilgis</button>
      <button type="button" class="chip" data-quick="l1w1">Ilgis + plotis</button>
      <button type="button" class="chip" data-quick="none">Be briaunų</button>
    </div>
    <footer>
      <button type="button" class="btn btn--ghost" value="cancel" id="edge-cancel">Atšaukti</button>
      <button type="submit" class="btn btn--primary" value="ok" id="edge-ok">Išsaugoti</button>
    </footer>
  </form>
</dialog>

<dialog class="edge-dialog offcut-dialog" id="offcut-dialog" aria-labelledby="offcut-dialog-title">
  <div class="edge-dialog__inner">
    <header>
      <h2 id="offcut-dialog-title">Pjauti iš likučio</h2>
      <p class="muted" id="offcut-dialog-sub"></p>
    </header>
    <div class="offcut-pick" id="offcut-pick"></div>
    <footer>
      <button type="button" class="btn btn--ghost" id="offcut-cancel">Uždaryti</button>
    </footer>
  </div>
</dialog>

<script type="application/json" id="app-config"><?= json_encode($client, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="assets/edges.js?v=1"></script>
<script src="assets/app.js?v=6"></script>
<?php render_footer(); ?>
