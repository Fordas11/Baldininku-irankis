(function () {
  'use strict';

  const CFG = JSON.parse(document.getElementById('app-config').textContent);
  const TYPES = CFG.edgeTypes;
  const TYPE_BY_ID = Object.fromEntries(TYPES.map((t) => [t.id, t]));
  const SIDES = ['l1', 'l2', 'w1', 'w2'];
  const PASTE_ORDER = ['code', 'thickness', 'length', 'width', 'qty', 'l1', 'l2', 'w1', 'w2', 'note', 'cnc'];
  const BOARDS = CFG.boards;
  const BOARD_BY_ID = new Map(BOARDS.map((b) => [b.id, b]));
  const DRAFT_KEY = 'baldininku-irankis-juodrastis-v1';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const form = $('#order-form');
  const tbody = $('#parts-body');

  let rows = [];
  let files = [];
  let nextId = 1;
  // Briauna, dedama spustelėjus detalės kraštą (pasirenkama legendoje arba dialoge).
  let brush = (TYPES.find((t) => t.id === '2') || TYPES[0]).id;

  // ---------- pagalbinės ----------

  function num(v) {
    const s = String(v ?? '').replace(/[\s ]/g, '').replace(',', '.');
    return s === '' ? NaN : Number(s);
  }

  function fmt(n, d = 2) {
    return n.toLocaleString('lt-LT', { maximumFractionDigits: d });
  }

  function thicknessOf(row) {
    const t = num(row.thickness);
    return Number.isFinite(t) && t > 0 ? t : CFG.defaultThickness;
  }

  function typesFor(thickness) {
    return TYPES.filter((t) => thickness >= t.min && thickness <= t.max);
  }

  /** Briaunos ID, tinkantis nurodytam storiui (pvz. PVC 2 ant 25 mm -> 2.8), arba ''. */
  function convertEdge(id, thickness) {
    if (!id) return '';
    const t = TYPE_BY_ID[id];
    if (!t) return '';
    if (thickness >= t.min && thickness <= t.max) return id;
    const same = typesFor(thickness).find((x) => x.nominal === t.nominal);
    return same ? same.id : '';
  }

  function edgeFromValue(value, thickness) {
    const n = num(value);
    if (!Number.isFinite(n) || n === 0) return '';
    const exact = TYPES.find((t) => Math.abs(Number(t.id) - n) < 0.001);
    if (exact) return convertEdge(exact.id, thickness);
    const nominal = TYPES.find((t) => Math.abs(t.nominal - n) < 0.001);
    return nominal ? convertEdge(nominal.id, thickness) : '';
  }

  // ---------- plokščių katalogas ----------

  const norm = (s) => String(s ?? '').trim().toLowerCase();

  function structuresFor(code) {
    return [...new Set(BOARDS.filter((b) => norm(b.code) === norm(code)).map((b) => b.structure))];
  }

  function boardsFor(code, structure) {
    return BOARDS.filter((b) => norm(b.code) === norm(code) && norm(b.structure) === norm(structure))
      .sort((a, b) => a.thickness - b.thickness);
  }

  /**
   * Suderina eilutės kodą, struktūrą ir storį su katalogu: vienintelė struktūra parenkama automatiškai,
   * storis – jei pasirinkto nėra, imamas pirmas turimas sandėlyje. Nustato board_id ir pavadinimą.
   */
  function resolveBoard(r) {
    const known = BOARDS.find((b) => norm(b.code) === norm(r.code));
    if (known) r.code = known.code;
    const structs = structuresFor(r.code);
    const exact = structs.find((s) => norm(s) === norm(r.structure));
    r.structure = exact !== undefined ? exact : (structs.length === 1 ? structs[0] : '');
    const list = boardsFor(r.code, r.structure);
    let b = list.find((x) => x.thickness === num(r.thickness));
    if (!b) b = list.find((x) => x.stock > 0) || null;
    if (b) r.thickness = String(b.thickness);
    r.board_id = b ? b.id : null;
    r.name = b ? b.label : r.code.trim();
    return b;
  }

  /** Atpažįsta plokštę iš laisvo teksto (pvz. „Egger W1000 ST9“ iš Excel). */
  function boardFromText(text) {
    const words = norm(text).split(/[\s,;/]+/).filter(Boolean);
    let best = null;
    for (const b of BOARDS) {
      if (!words.includes(norm(b.code))) continue;
      const score = (b.structure && words.includes(norm(b.structure)) ? 2 : 0) + (words.includes(norm(b.man)) ? 1 : 0);
      if (!best || score > best.score) best = { b, score };
    }
    if (!best) return { code: String(text ?? '').trim(), structure: '' };
    return { code: best.b.code, structure: best.score >= 2 ? best.b.structure : '' };
  }

  /** Eilutė iš senų duomenų (juodraštis, Excel) turi tik pavadinimą – paverčiama kodu ir struktūra. */
  function upgradeRow(r) {
    if (!r.code && r.name) Object.assign(r, boardFromText(r.name));
    resolveBoard(r);
    return r;
  }

  function colorOf(id) {
    return TYPE_BY_ID[id] ? TYPE_BY_ID[id].color : '#888';
  }

  function labelOf(id) {
    return TYPE_BY_ID[id] ? TYPE_BY_ID[id].label : '';
  }

  function isRowEmpty(r) {
    return !String(r.length).trim() && !String(r.width).trim() && !String(r.note).trim() && !String(r.cnc).trim()
      && !SIDES.some((s) => r.edges[s]) && !r.offcut;
  }

  function newRow(prev) {
    return {
      id: nextId++,
      code: prev ? prev.code : '',
      structure: prev ? prev.structure : '',
      board_id: prev ? prev.board_id : null,
      name: prev ? prev.name : '',
      thickness: prev ? prev.thickness : String(CFG.defaultThickness),
      length: '',
      width: '',
      qty: '',
      edges: { l1: '', l2: '', w1: '', w2: '' },
      note: '',
      cnc: '',
      offcut: null,
    };
  }

  function toast(msg) {
    let el = $('#toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast';
      el.className = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('is-visible');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => el.classList.remove('is-visible'), 3200);
  }

  // ---------- lentelė ----------

  function rowHtml(r, i) {
    const input = (f, attrs = '') => `<input data-f="${f}" value="${escapeAttr(r[f])}" ${attrs}>`;
    return `
      <td class="c-nr" data-label="Nr."><span class="nr">${i + 1}</span></td>
      <td class="c-name" data-label="Plokštės kodas">${input('code', 'list="board-codes" placeholder="Kodas, pvz. W1000" autocomplete="off" aria-label="Plokštės dekoro kodas"')}
        <div class="board-desc" data-role="desc"></div>
        <div class="offcut-slot" data-role="offcut"></div></td>
      <td class="c-struct" data-label="Struktūra"><select data-f="structure" aria-label="Paviršiaus struktūra"></select></td>
      <td class="c-num" data-label="Storis, mm"><select data-f="thickness" aria-label="Storis, mm"></select></td>
      <td class="c-num" data-label="Ilgis, mm">${input('length', 'inputmode="decimal" aria-label="Ilgis, mm"')}</td>
      <td class="c-num" data-label="Plotis, mm">${input('width', 'inputmode="decimal" aria-label="Plotis, mm"')}</td>
      <td class="c-qty" data-label="Kiekis">${input('qty', 'inputmode="numeric" placeholder="1" aria-label="Kiekis"')}</td>
      <td class="c-edge" data-label="Briaunavimas">
        <div class="edge-cell">
          <div class="mini" data-role="mini"></div>
          <div class="edge-cell__meta">
            <button type="button" class="link-btn" data-act="edges">Briaunos</button>
            <span class="edge-sum" data-role="sum"></span>
          </div>
        </div>
      </td>
      <td class="c-note" data-label="Pastabos">${input('note', 'aria-label="Pastabos"')}</td>
      <td class="c-cnc" data-label="CNC kodas">${input('cnc', 'aria-label="CNC kodas"')}</td>
      <td class="c-act">
        <button type="button" class="icon-btn" data-act="dup" title="Kopijuoti eilutę" aria-label="Kopijuoti eilutę">
          <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="7" y="7" width="9" height="9" rx="1.5"/><path d="M4 13V5.5C4 4.7 4.7 4 5.5 4H13"/></svg></button>
        <button type="button" class="icon-btn icon-btn--danger" data-act="del" title="Pašalinti eilutę" aria-label="Pašalinti eilutę">
          <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg></button>
      </td>`;
  }

  function escapeAttr(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function renderAll() {
    tbody.innerHTML = '';
    rows.forEach((r, i) => {
      const tr = document.createElement('tr');
      tr.dataset.id = r.id;
      tr.innerHTML = rowHtml(r, i);
      tbody.appendChild(tr);
      renderBoard(r, tr);
      renderEdges(r, tr);
      renderOffcut(r, tr);
      checkOffcuts(r);
    });
    updateDatalists();
    updateSummary();
  }

  function trOf(row) {
    return tbody.querySelector(`tr[data-id="${row.id}"]`);
  }

  function rowOf(el) {
    const tr = el.closest('tr[data-id]');
    return tr ? rows.find((r) => r.id === Number(tr.dataset.id)) : null;
  }

  function renderEdges(r, tr = trOf(r)) {
    if (!tr) return;
    const mini = $('[data-role="mini"]', tr);
    mini.innerHTML = EdgeBoard.svg({
      length: num(r.length), width: num(r.width), edges: r.edges, colorOf, labelOf,
    });
    const count = (prefix) => SIDES.filter((s) => s[0] === prefix && r.edges[s]).length;
    const l = count('l');
    const w = count('w');
    const used = [...new Set(SIDES.map((s) => r.edges[s]).filter(Boolean).map((id) => TYPE_BY_ID[id].short))];
    $('[data-role="sum"]', tr).textContent = l + w === 0 ? 'nėra' : `${l ? l + ' ilg.' : ''}${l && w ? ' + ' : ''}${w ? w + ' plot.' : ''} · ${used.join('/')} mm`;
  }

  function renumber() {
    $$('tr[data-id]', tbody).forEach((tr, i) => { $('.nr', tr).textContent = i + 1; });
  }

  function addRow(after, focusField) {
    const prev = after || rows[rows.length - 1];
    const r = newRow(prev);
    const idx = after ? rows.indexOf(after) + 1 : rows.length;
    rows.splice(idx, 0, r);
    const tr = document.createElement('tr');
    tr.dataset.id = r.id;
    tr.innerHTML = rowHtml(r, idx);
    const ref = tbody.children[idx] || null;
    tbody.insertBefore(tr, ref);
    renderBoard(r, tr);
    renderEdges(r, tr);
    renderOffcut(r, tr);
    renumber();
    if (focusField) $(`[data-f="${focusField}"]`, tr).focus();
    saveDraft();
    return r;
  }

  function updateDatalists() {
    if ($('#board-codes')) return;
    const dl = document.createElement('datalist');
    dl.id = 'board-codes';
    const byCode = new Map();
    for (const b of BOARDS) {
      const key = norm(b.code);
      if (!byCode.has(key)) byCode.set(key, { b, structs: new Set() });
      byCode.get(key).structs.add(b.structure);
    }
    dl.innerHTML = [...byCode.values()].map(({ b, structs }) => `<option value="${escapeAttr(b.code)}" label="${escapeAttr(
      [b.man, b.name, [...structs].filter(Boolean).join(', ')].filter(Boolean).join(' · '))}">`).join('');
    document.body.appendChild(dl);
  }

  function fmtMoney(n) {
    return n.toLocaleString('lt-LT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  /** Kodo, struktūros ir storio laukai pagal katalogą; neturimi sandėlyje storiai – neaktyvūs. */
  function renderBoard(r, tr = trOf(r)) {
    if (!tr) return;
    const structs = structuresFor(r.code);
    const sSel = $('[data-f="structure"]', tr);
    sSel.innerHTML = (structs.length > 1 && !r.structure ? '<option value="">— pasirinkite —</option>' : '')
      + (structs.length ? structs.map((s) => `<option value="${escapeAttr(s)}" ${s === r.structure ? 'selected' : ''}>${escapeAttr(s || 'be struktūros')}</option>`).join('') : '<option value="">—</option>');
    sSel.disabled = !structs.length;

    const list = boardsFor(r.code, r.structure);
    const tSel = $('[data-f="thickness"]', tr);
    tSel.innerHTML = (r.board_id || !list.length ? '' : '<option value="">—</option>') + (list.length
      ? list.map((b) => {
        const sel = b.id === r.board_id;
        return `<option value="${b.thickness}" ${sel ? 'selected' : ''} ${b.stock > 0 || sel ? '' : 'disabled'}>${fmt(b.thickness, 1)}${b.stock > 0 ? '' : ' – nėra'}</option>`;
      }).join('')
      : '<option value="">—</option>');
    tSel.disabled = !list.length;

    const b = BOARD_BY_ID.get(r.board_id);
    const desc = $('[data-role="desc"]', tr);
    const unknown = r.code.trim() && !structs.length;
    desc.classList.toggle('board-desc--error', !!unknown);
    if (b) {
      desc.textContent = [b.man, b.name].filter(Boolean).join(' ') + (b.stock > 0 ? ` · sandėlyje ${b.stock} lap.` : ' · nėra sandėlyje');
    } else if (unknown) {
      desc.textContent = 'Tokio kodo kataloge nėra';
    } else if (r.code.trim()) {
      desc.textContent = !r.structure && structs.length > 1 ? 'Pasirinkite struktūrą' : 'Šio storio sandėlyje nėra';
    } else {
      desc.textContent = '';
    }
  }

  // ---------- kainos skaičiuoklė ----------

  let quoteSeq = 0;
  let layoutsOpen = false;

  function updateSummary() {
    clearTimeout(updateSummary.timer);
    $('#summary').classList.add('is-loading');
    updateSummary.timer = setTimeout(loadQuote, 350);
  }

  async function loadQuote() {
    const seq = ++quoteSeq;
    const f = getFields();
    const items = rows.map((r) => ({
      board_id: r.board_id, length: r.length, width: r.width, qty: r.qty, edges: r.edges,
      offcut_id: r.offcut ? r.offcut.id : null, cnc: r.cnc,
    }));
    try {
      const res = await fetch('api/quote.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items, delivery: f.delivery, packaging: f.packaging }),
      });
      const q = await res.json();
      if (seq !== quoteSeq) return;
      if (!q.ok) throw new Error(q.error);
      renderQuote(q);
    } catch (_) {
      if (seq !== quoteSeq) return;
      $('#summary').classList.remove('is-loading');
      $('#summary').innerHTML = '<p class="muted">Kainos apskaičiuoti nepavyko – bandysime dar kartą pakeitus duomenis.</p>';
    }
  }

  function sheetSvg(m, layout) {
    const [L, W] = m.sheet;
    const font = Math.max(L, W) / 26;
    const parts = layout.parts.map(([x, y, w, h, pos, rot]) => `
      <g><rect class="nest-part${rot ? ' nest-part--rot' : ''}" x="${x}" y="${y}" width="${w}" height="${h}"/>
      ${w > font * 1.6 && h > font * 1.1 ? `<text x="${x + w / 2}" y="${y + h / 2}" font-size="${Math.min(font, h * 0.6)}" text-anchor="middle" dominant-baseline="central">${pos}</text>` : ''}
      <title>Detalė Nr. ${pos}: ${fmt(w, 1)} × ${fmt(h, 1)} mm${rot ? ' (pasukta)' : ''}</title></g>`).join('');
    return `<figure class="nest-sheet"><svg viewBox="0 0 ${L} ${W}" role="img" aria-label="Plokštės išdėstymas, išnaudota ${layout.utilization} %">
      <rect class="nest-board" x="0" y="0" width="${L}" height="${W}"/>${parts}</svg>
      <figcaption>${layout.utilization} % išnaudota</figcaption></figure>`;
  }

  function renderQuote(q) {
    const box = $('#summary');
    box.classList.remove('is-loading');
    if (!q.lines.length) {
      box.innerHTML = '<p class="muted quote__empty">Kaina bus apskaičiuota, kai įrašysite plokštę ir detalių matmenis.</p>';
      $('#submit-info').textContent = '';
      return;
    }
    let group = '';
    const body = q.lines.map((l) => {
      const head = l.group !== group ? `<tr class="quote__group"><td colspan="4">${escapeAttr(l.group)}</td></tr>` : '';
      group = l.group;
      return head + `<tr><td>${escapeAttr(l.label)}${l.detail ? `<small>${escapeAttr(l.detail)}</small>` : ''}</td>
        <td class="num">${fmt(l.qty, 3)} ${l.unit}</td><td class="num">${fmtMoney(l.price)}</td><td class="num">${fmtMoney(l.sum)}</td></tr>`;
    }).join('');
    const sheets = q.materials.reduce((a, m) => a + m.sheets, 0);
    const layouts = q.materials.filter((m) => m.layouts.length).map((m) => `
      <div class="nest-group"><h4>${escapeAttr(m.label)} <span class="muted">· ${m.sheets} lap. · ${m.utilization} % · ${m.grain ? 'su tekstūra' : 'be tekstūros (detalės gali būti pasuktos)'}</span></h4>
      <div class="nest-sheets">${m.layouts.map((lay) => sheetSvg(m, lay)).join('')}${m.sheets > m.layouts.length ? `<p class="muted small">…ir dar ${m.sheets - m.layouts.length} lap.</p>` : ''}</div></div>`).join('');
    box.innerHTML = `
      <div class="quote">
        <div class="quote__head"><h3>Kaina</h3><span class="muted small">Pagal galiojančius įkainius. Pjūvių ilgis – pagal detalių išdėstymą ant plokščių.</span></div>
        <div class="table-scroll table-scroll--quote"><table class="quote__table">
          <thead><tr><th>Pozicija</th><th class="num">Kiekis</th><th class="num">Kaina be PVM</th><th class="num">Suma</th></tr></thead>
          <tbody>${body}</tbody>
          <tfoot>
            <tr><td colspan="3">Suma be PVM</td><td class="num">${fmtMoney(q.subtotal)}</td></tr>
            <tr><td colspan="3">PVM ${fmt(q.vat_percent, 0)} %</td><td class="num">${fmtMoney(q.vat)}</td></tr>
            <tr class="quote__total"><td colspan="3">Iš viso su PVM</td><td class="num">${fmtMoney(q.total)}</td></tr>
          </tfoot>
        </table></div>
        ${q.warnings.length ? `<ul class="quote__warnings">${q.warnings.map((w) => `<li>${escapeAttr(w)}</li>`).join('')}</ul>` : ''}
        ${layouts ? `<details class="quote__layouts" ${layoutsOpen ? 'open' : ''}><summary>Pjovimo schema – ${sheets} plokšt${sheets === 1 ? 'ė' : 'ės'}</summary>${layouts}</details>` : ''}
      </div>`;
    const det = $('.quote__layouts', box);
    if (det) det.addEventListener('toggle', () => { layoutsOpen = det.open; });
    $('#submit-info').innerHTML = `Kaina: <b>${fmtMoney(q.total)}</b> su PVM`;
  }

  // Įvedimas lentelėje
  tbody.addEventListener('input', (e) => {
    const f = e.target.dataset.f;
    const r = rowOf(e.target);
    if (!f || !r) return;
    r[f] = e.target.value;
    e.target.classList.remove('invalid');
    if (f === 'length' || f === 'width') renderEdges(r);
    if (['code', 'structure', 'thickness'].includes(f)) onBoardChange(r);
    if (['code', 'structure', 'thickness', 'length', 'width'].includes(f)) onOffcutFieldChange(r, f);
    updateSummary();
    saveDraft();
  });

  // Išėjus iš kodo laukelio – rodomas katalogo užrašas (pvz. „w1000“ -> „W1000“).
  tbody.addEventListener('change', (e) => {
    const r = rowOf(e.target);
    if (r && e.target.dataset.f === 'code') e.target.value = r.code;
  });

  /** Pakeitus kodą, struktūrą ar storį – suderinama su katalogu, briaunos pritaikomos naujam storiui. */
  function onBoardChange(r) {
    resolveBoard(r);
    renderBoard(r);
    const t = thicknessOf(r);
    let dropped = false;
    for (const s of SIDES) {
      const conv = convertEdge(r.edges[s], t);
      if (r.edges[s] && !conv) dropped = true;
      r.edges[s] = conv;
    }
    if (dropped) toast(`Kai kurios briaunos netinka ${t} mm plokštei – jos pašalintos.`);
    renderEdges(r);
  }

  tbody.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.dataset.f) return;
    e.preventDefault();
    const r = rowOf(e.target);
    const idx = rows.indexOf(r);
    const f = e.target.dataset.f;
    if (idx < rows.length - 1) {
      $(`[data-f="${f}"]`, trOf(rows[idx + 1])).focus();
    } else {
      addRow(r, f === 'code' ? 'code' : 'length');
    }
  });

  tbody.addEventListener('click', (e) => {
    const r = rowOf(e.target);
    if (!r) return;
    const hit = e.target.closest('.edge-hit');
    if (hit) {
      toggleSide(r, hit.dataset.side);
      return;
    }
    const act = e.target.closest('[data-act]');
    if (e.target.closest('.board-face') || (act && act.dataset.act === 'edges')) {
      openEdgeDialog(r);
      return;
    }
    if (!act) return;
    if (act.dataset.act === 'offcut-pick') {
      openOffcutDialog(r);
      return;
    }
    if (act.dataset.act === 'offcut-remove') {
      r.offcut = null;
      renderOffcut(r);
      checkOffcuts(r);
      updateSummary();
      saveDraft();
      return;
    }
    if (act.dataset.act === 'del') {
      rows.splice(rows.indexOf(r), 1);
      trOf(r).remove();
      if (!rows.length) addRow();
      renumber();
      updateSummary();
      saveDraft();
    } else if (act.dataset.act === 'dup') {
      const copy = { ...JSON.parse(JSON.stringify(r)), id: nextId++ };
      rows.splice(rows.indexOf(r) + 1, 0, copy);
      renderAll();
      saveDraft();
    }
  });

  function toggleSide(r, side) {
    const t = thicknessOf(r);
    if (r.edges[side]) {
      r.edges[side] = '';
    } else {
      let id = convertEdge(brush, t);
      if (!id) {
        const first = typesFor(t)[0];
        if (!first) {
          toast(`${t} mm plokštei briaunų nėra.`);
          return;
        }
        id = first.id;
        toast(`${labelOf(brush)} netinka ${t} mm plokštei – uždėta ${labelOf(id)}.`);
      }
      r.edges[side] = id;
    }
    renderEdges(r);
    updateSummary();
    saveDraft();
  }

  // Įklijavimas iš Excel (tabuliacija atskirti stulpeliai)
  tbody.addEventListener('paste', (e) => {
    const f = e.target.dataset.f;
    const start = rowOf(e.target);
    const text = (e.clipboardData || window.clipboardData).getData('text');
    if (!f || !start || !/[\t\n]/.test(text.trim())) return;
    e.preventDefault();
    const lines = text.replace(/\r/g, '').replace(/\n+$/, '').split('\n');
    const col0 = PASTE_ORDER.indexOf(f);
    let idx = rows.indexOf(start);
    for (const line of lines) {
      let r = rows[idx];
      if (!r) {
        r = newRow(rows[idx - 1]);
        rows.push(r);
      }
      line.split('\t').forEach((raw, j) => {
        const key = PASTE_ORDER[col0 + j];
        const value = raw.trim();
        if (!key) return;
        if (SIDES.includes(key)) r.edges[key] = edgeFromValue(value, thicknessOf(r));
        else if (key === 'code') Object.assign(r, boardFromText(value));
        else r[key] = value;
        if (key === 'code' || key === 'thickness') resolveBoard(r);
      });
      idx++;
    }
    renderAll();
    saveDraft();
    toast(`Įklijuota eilučių: ${lines.length}`);
  });

  $('#add-row').addEventListener('click', () => addRow(null, 'code'));
  $('#add-rows').addEventListener('click', () => {
    for (let i = 0; i < 10; i++) addRow();
  });

  // ---------- likučiai ----------

  const offcutCache = new Map();
  const offcutMatches = new Map();
  const offcutTimers = new Map();

  function offcutText(o) {
    return `${fmt(o.length, 1)} × ${fmt(o.width, 1)} mm`;
  }

  function renderOffcut(r, tr = trOf(r)) {
    if (!tr) return;
    const slot = $('[data-role="offcut"]', tr);
    slot.classList.remove('invalid');
    const matches = offcutMatches.get(r.id) || [];
    if (!CFG.offcuts) {
      slot.innerHTML = '';
    } else if (r.offcut) {
      slot.innerHTML = `<span class="offcut-chip" title="Detalė bus pjaunama iš likučio #${r.offcut.id} (${offcutText(r.offcut)})">♻ Iš likučio #${r.offcut.id}
        <button type="button" data-act="offcut-remove" aria-label="Nebenaudoti likučio">×</button></span>`;
    } else if (matches.length) {
      slot.innerHTML = `<button type="button" class="offcut-hint" data-act="offcut-pick" title="Detalę galima išpjauti iš jau turimo likučio">♻ Tinka likučių: ${matches.length}</button>`;
    } else {
      slot.innerHTML = '';
    }
  }

  /** Užklausia serverio, ar yra laisvų likučių, į kuriuos telpa ši detalė. */
  function checkOffcuts(r) {
    clearTimeout(offcutTimers.get(r.id));
    const L = num(r.length);
    const W = num(r.width);
    if (!CFG.offcuts || r.offcut || !r.board_id || !(L > 0 && W > 0)) {
      offcutMatches.delete(r.id);
      renderOffcut(r);
      return;
    }
    const params = new URLSearchParams({ board_id: r.board_id, length: L, width: W });
    const key = params.toString();
    const apply = (list) => {
      offcutMatches.set(r.id, list);
      renderOffcut(r);
    };
    if (offcutCache.has(key)) {
      apply(offcutCache.get(key));
      return;
    }
    offcutTimers.set(r.id, setTimeout(async () => {
      try {
        const data = await fetch('api/offcuts.php?' + key).then((res) => res.json());
        offcutCache.set(key, data.offcuts || []);
        if (rows.includes(r)) apply(data.offcuts || []);
      } catch (_) { /* be likučių pasiūlymo forma veikia kaip įprasta */ }
    }, 400));
  }

  function onOffcutFieldChange(r, f) {
    if (r.offcut && ['code', 'structure', 'thickness'].includes(f)) {
      if (r.board_id !== r.offcut.board_id) {
        toast(`Plokštė pakeista – detalė nebebus pjaunama iš likučio #${r.offcut.id}.`);
        r.offcut = null;
      }
    }
    checkOffcuts(r);
  }

  const offcutDlg = $('#offcut-dialog');
  let offcutRow = null;

  function openOffcutDialog(r) {
    offcutRow = r;
    const list = offcutMatches.get(r.id) || [];
    const partsArea = num(r.length) * num(r.width) * (num(r.qty) > 0 ? num(r.qty) : 1);
    $('#offcut-dialog-sub').textContent = `Detalė Nr. ${rows.indexOf(r) + 1}: ${r.name} · ${thicknessOf(r)} mm · ${r.length} × ${r.width} mm`;
    $('#offcut-pick').innerHTML = list.map((o) => `
      <div class="offcut-option">
        <div class="offcut-option__shape">${EdgeBoard.svg({ length: o.length, width: o.width, edges: {}, colorOf, labelOf })}</div>
        <div>
          <b>Likutis #${o.id}</b> · ${offcutText(o)}
          <div class="muted small">Liks apie ${fmt(Math.max(0, o.length * o.width - partsArea) / 1e6)} m²${o.note ? ' · ' + escapeAttr(o.note) : ''}</div>
        </div>
        <button type="button" class="btn btn--primary" data-pick="${o.id}">Pasirinkti</button>
      </div>`).join('') || '<p class="muted">Tinkamų likučių neliko.</p>';
    offcutDlg.showModal();
  }

  offcutDlg.addEventListener('click', (e) => {
    const b = e.target.closest('[data-pick]');
    if (b && offcutRow) {
      const o = (offcutMatches.get(offcutRow.id) || []).find((x) => x.id === Number(b.dataset.pick));
      const shared = o && rows.some((x) => x !== offcutRow && x.offcut && x.offcut.id === o.id);
      offcutRow.offcut = o || null;
      renderOffcut(offcutRow);
      updateSummary();
      saveDraft();
      offcutDlg.close();
      if (shared) toast(`Likutis #${o.id} pasirinktas ir kitai detalei – patikrinsime, ar abi telpa.`);
      return;
    }
    if (e.target === offcutDlg || e.target.id === 'offcut-cancel') offcutDlg.close();
  });

  /** Atėjus iš katalogo (index.php?offcut=12) – pridedama detalė, pjaunama iš to likučio. */
  async function addOffcutFromUrl() {
    const id = new URLSearchParams(location.search).get('offcut');
    if (!id || !CFG.offcuts) return;
    history.replaceState(null, '', location.pathname);
    try {
      const data = await fetch('api/offcuts.php?id=' + encodeURIComponent(id)).then((res) => res.json());
      if (!data.ok) throw new Error(data.error);
      const o = data.offcut;
      const b = BOARD_BY_ID.get(o.board_id);
      if (!b) throw new Error('Šio likučio plokštės kataloge nebėra.');
      const r = rows.find((x) => isRowEmpty(x)) || addRow();
      Object.assign(r, { code: b.code, structure: b.structure, thickness: String(b.thickness), offcut: o });
      resolveBoard(r);
      renderAll();
      trOf(r).scrollIntoView({ block: 'center' });
      $('[data-f="length"]', trOf(r)).focus();
      saveDraft();
      toast(`Detalė bus pjaunama iš likučio #${o.id} (${offcutText(o)}). Įrašykite detalės matmenis.`);
    } catch (err) {
      toast(err.message || 'Nepavyko įkelti likučio.');
    }
  }

  // ---------- briaunų „teptukas“ legendoje ----------

  function renderBrush() {
    const holder = $('.edge-legend__items');
    const base = TYPES.filter((t) => t.min <= CFG.defaultThickness && t.max >= CFG.defaultThickness);
    holder.innerHTML = '<span class="edge-legend__label">Spustelėjus kraštą dedama:</span>' + base.map((t) => `
      <button type="button" class="swatch${convertEdge(brush, CFG.defaultThickness) === t.id ? ' is-active' : ''}" data-brush="${t.id}" aria-pressed="${brush === t.id}">
        <i style="background:${t.color}"></i>${escapeAttr(t.label)}</button>`).join('');
  }

  $('.edge-legend').addEventListener('click', (e) => {
    const b = e.target.closest('[data-brush]');
    if (!b) return;
    brush = b.dataset.brush;
    renderBrush();
    saveDraft();
  });

  // ---------- briaunų dialogas ----------

  const dlg = $('#edge-dialog');
  let dlgRow = null;
  let dlgEdges = null;

  function openEdgeDialog(r) {
    dlgRow = r;
    dlgEdges = { ...r.edges };
    const t = thicknessOf(r);
    const types = typesFor(t);
    $('#edge-dialog-title').textContent = `Briaunavimas – detalė Nr. ${rows.indexOf(r) + 1}`;
    $('#edge-dialog-sub').textContent = `${r.name || 'Plokštė nenurodyta'} · ${t} mm · ${r.length || '?'} × ${r.width || '?'} mm`;
    const active = convertEdge(brush, t) || (types[0] && types[0].id);
    $('#edge-dialog-types').innerHTML = types.length
      ? '<span class="muted">Spustelėjus kraštą dedama:</span>' + types.map((x) => `
        <label class="swatch${x.id === active ? ' is-active' : ''}"><input type="radio" name="dlg-brush" value="${x.id}" ${x.id === active ? 'checked' : ''}>
          <i style="background:${x.color}"></i>${escapeAttr(x.label)}</label>`).join('')
      : `<span class="muted">${t} mm plokštei briaunų nėra.</span>`;
    renderDialog();
    dlg.returnValue = '';
    dlg.showModal();
  }

  function dialogBrush() {
    const checked = $('input[name="dlg-brush"]:checked', dlg);
    return checked ? checked.value : '';
  }

  function renderDialog() {
    const r = dlgRow;
    const types = typesFor(thicknessOf(r));
    $('#edge-dialog-board').innerHTML = EdgeBoard.svg({
      length: num(r.length), width: num(r.width), edges: dlgEdges, colorOf, labelOf, big: true,
    });
    $('#edge-dialog-sides').innerHTML = SIDES.map((s) => `
      <label class="field">
        <span>${EdgeBoard.SIDE_NAMES[s]}</span>
        <select data-side="${s}">
          <option value="">— be briaunos —</option>
          ${types.map((t) => `<option value="${t.id}" ${dlgEdges[s] === t.id ? 'selected' : ''}>${escapeAttr(t.label)}</option>`).join('')}
        </select>
      </label>`).join('');
  }

  dlg.addEventListener('click', (e) => {
    const hit = e.target.closest('.edge-hit');
    if (hit) {
      const s = hit.dataset.side;
      dlgEdges[s] = dlgEdges[s] ? '' : dialogBrush();
      renderDialog();
      return;
    }
    const q = e.target.closest('[data-quick]');
    if (q) {
      const id = dialogBrush();
      const on = { all: SIDES, long: ['l1', 'l2'], short: ['w1', 'w2'], l1: ['l1'], l1w1: ['l1', 'w1'], none: [] }[q.dataset.quick];
      for (const s of SIDES) dlgEdges[s] = on.includes(s) ? id : '';
      renderDialog();
      return;
    }
    if (e.target === dlg) dlg.close('cancel');
  });

  dlg.addEventListener('change', (e) => {
    if (e.target.name === 'dlg-brush') {
      brush = e.target.value;
      $$('#edge-dialog-types .swatch', dlg).forEach((l) => l.classList.toggle('is-active', $('input', l).checked));
      renderBrush();
    } else if (e.target.dataset.side) {
      dlgEdges[e.target.dataset.side] = e.target.value;
      renderDialog();
    }
  });

  $('#edge-cancel').addEventListener('click', () => dlg.close('cancel'));

  dlg.addEventListener('close', () => {
    if (dlg.returnValue === 'ok' && dlgRow) {
      dlgRow.edges = dlgEdges;
      renderEdges(dlgRow);
      updateSummary();
      saveDraft();
    }
    dlgRow = null;
  });

  // ---------- kliento laukai ----------

  const FORM_FIELDS = ['contact_name', 'phone', 'email', 'company', 'company_code', 'delivery', 'delivery_address',
    'return_offcuts', 'packaging', 'packaging_note', 'comment'];

  function getFields() {
    const out = {};
    for (const name of FORM_FIELDS) {
      const els = form.elements[name];
      if (els instanceof RadioNodeList) out[name] = els.value;
      else if (els.type === 'checkbox') out[name] = els.checked;
      else out[name] = els.value.trim();
    }
    return out;
  }

  function setFields(data) {
    for (const name of FORM_FIELDS) {
      if (!(name in data)) continue;
      const els = form.elements[name];
      if (els instanceof RadioNodeList) els.value = data[name];
      else if (els.type === 'checkbox') els.checked = !!data[name];
      else els.value = data[name];
    }
    updateConditional();
  }

  function updateConditional() {
    $$('[data-show-when]', form).forEach((el) => {
      const [name, value] = el.dataset.showWhen.split('=');
      el.hidden = form.elements[name].value !== value;
    });
  }

  form.addEventListener('input', (e) => {
    if (e.target.closest('#parts')) return;
    e.target.classList.remove('invalid');
    updateConditional();
    if (e.target.name === 'delivery' || e.target.name === 'packaging') updateSummary();
    saveDraft();
  });

  // ---------- juodraštis ----------

  function saveDraft() {
    clearTimeout(saveDraft.timer);
    saveDraft.timer = setTimeout(() => {
      try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify({ fields: getFields(), rows, brush }));
      } catch (_) { /* privatus režimas ar pilna saugykla – juodraštis tiesiog nesaugomas */ }
    }, 300);
  }

  function loadDraft() {
    try {
      const d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
      if (!d) return false;
      setFields(d.fields || {});
      if (d.brush && TYPE_BY_ID[d.brush]) brush = d.brush;
      rows = (d.rows || []).map((r) => upgradeRow({ ...newRow(), ...r, id: nextId++, edges: { ...newRow().edges, ...(r.edges || {}) },
        offcut: CFG.offcuts ? r.offcut || null : null }));
      return rows.length > 0;
    } catch (_) {
      return false;
    }
  }

  function resetAll() {
    try { localStorage.removeItem(DRAFT_KEY); } catch (_) { /* ignoruojama */ }
    form.reset();
    rows = [];
    files = [];
    for (let i = 0; i < 5; i++) rows.push(newRow());
    renderAll();
    renderFiles();
    updateConditional();
  }

  $('#clear-form').addEventListener('click', () => {
    if (confirm('Išvalyti visą formą ir detalių sąrašą?')) resetAll();
  });

  // ---------- importas iš Excel ----------

  $('#import-file').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    const body = new FormData();
    body.append('csrf', CFG.csrf);
    body.append('file', file);
    try {
      const res = await fetch('api/import.php', { method: 'POST', body });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Nepavyko nuskaityti failo.');
      if (!data.rows.length) {
        toast('Faile neradome detalių.');
        return;
      }
      const filled = rows.filter((r) => !isRowEmpty(r));
      let keep = [];
      if (filled.length) {
        keep = confirm(`Formoje jau yra ${filled.length} detalės. Spauskite „Gerai“, kad jas pakeistumėte, arba „Atšaukti“, kad naujos būtų pridėtos.`) ? [] : filled;
      }
      const imported = data.rows.map((r) => {
        const row = upgradeRow({ ...newRow(), ...r, code: '', id: nextId++ });
        const t = thicknessOf(row);
        for (const s of SIDES) row.edges[s] = convertEdge(r.edges[s], t);
        return row;
      });
      rows = keep.concat(imported);
      const current = getFields();
      const contact = {};
      for (const [k, v] of Object.entries(data.contact || {})) if (!current[k] || k === 'delivery' || k === 'packaging') contact[k] = v;
      setFields(contact);
      renderAll();
      saveDraft();
      toast(`Įkelta detalių: ${imported.length}`);
    } catch (err) {
      toast(err.message);
    }
  });

  // ---------- failai ----------

  const fileInput = $('#files');
  const dropzone = $('#dropzone');

  function addFiles(list) {
    for (const f of list) {
      const ext = f.name.split('.').pop().toLowerCase();
      if (!CFG.upload.extensions.includes(ext)) {
        toast(`${f.name}: netinkamas failo tipas.`);
      } else if (f.size > CFG.upload.max_size) {
        toast(`${f.name}: failas per didelis.`);
      } else if (files.length >= CFG.upload.max_files) {
        toast(`Daugiausia ${CFG.upload.max_files} failų.`);
        break;
      } else {
        files.push(f);
      }
    }
    renderFiles();
  }

  function renderFiles() {
    $('#file-list').innerHTML = files.map((f, i) => `
      <li><span>${escapeAttr(f.name)}</span><small>${fmt(f.size / 1048576, 1)} MB</small>
      <button type="button" class="icon-btn icon-btn--danger" data-remove-file="${i}" aria-label="Pašalinti ${escapeAttr(f.name)}">
        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg></button></li>`).join('');
  }

  fileInput.addEventListener('change', () => {
    addFiles(fileInput.files);
    fileInput.value = '';
  });
  ['dragenter', 'dragover'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
    e.preventDefault();
    dropzone.classList.add('is-over');
  }));
  ['dragleave', 'drop'].forEach((ev) => dropzone.addEventListener(ev, () => dropzone.classList.remove('is-over')));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    addFiles(e.dataTransfer.files);
  });
  $('#file-list').addEventListener('click', (e) => {
    const b = e.target.closest('[data-remove-file]');
    if (!b) return;
    files.splice(Number(b.dataset.removeFile), 1);
    renderFiles();
  });

  // ---------- tikrinimas ir pateikimas ----------

  function markInvalid(el, msg) {
    el.classList.add('invalid');
    el.title = msg;
    return msg;
  }

  function clearInvalid() {
    $$('.invalid', form).forEach((el) => { el.classList.remove('invalid'); el.removeAttribute('title'); });
  }

  function validateClient(fields, sent) {
    const errs = [];
    const need = (name, ok, msg) => { if (!ok) errs.push(markInvalid(form.elements[name], msg)); };
    need('contact_name', fields.contact_name, 'Įrašykite vardą ir pavardę.');
    need('phone', /^\+?[0-9 ()-]{6,}$/.test(fields.phone), 'Įrašykite telefono numerį.');
    if (fields.email) need('email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fields.email), 'Neteisingas el. pašto adresas.');
    if (fields.delivery === 'delivery') need('delivery_address', fields.delivery_address, 'Nurodykite pristatymo adresą.');
    if (fields.packaging === 'special') need('packaging_note', fields.packaging_note, 'Aprašykite pakavimo sąlygas.');

    if (!sent.length) errs.push('Įrašykite bent vieną detalę.');
    sent.forEach(({ row }) => {
      const tr = trOf(row);
      const nr = rows.indexOf(row) + 1;
      const bad = (f, msg) => errs.push(`Nr. ${nr}: ${markInvalid($(`[data-f="${f}"]`, tr), msg)}`);
      const L = num(row.length);
      const W = num(row.width);
      const q = row.qty.trim() === '' ? 1 : num(row.qty);
      if (!row.board_id) bad('code', 'Pasirinkite plokštę iš katalogo: kodą, struktūrą ir sandėlyje esantį storį.');
      if (!(L > 0)) bad('length', 'Įrašykite ilgį.');
      else if (L > CFG.maxDimension) bad('length', `Ilgis negali viršyti ${CFG.maxDimension} mm.`);
      if (!(W > 0)) bad('width', 'Įrašykite plotį.');
      else if (W > CFG.maxDimension) bad('width', `Plotis negali viršyti ${CFG.maxDimension} mm.`);
      if (!(Number.isInteger(q) && q >= 1)) bad('qty', 'Kiekis – sveikas skaičius.');
      if (row.offcut && (L > row.offcut.length || W > row.offcut.width)) {
        errs.push(`Nr. ${nr}: ${markInvalid($('.offcut-slot', tr), `Detalė netelpa į likutį #${row.offcut.id} (${row.offcut.length} × ${row.offcut.width} mm).`)}`);
      }
    });
    return errs;
  }

  function showErrors(list) {
    const box = $('#form-error');
    if (!list.length) {
      box.hidden = true;
      return;
    }
    const shown = list.slice(0, 8);
    box.innerHTML = '<b>Patikrinkite užsakymą:</b><ul>' + shown.map((m) => `<li>${escapeAttr(m)}</li>`).join('')
      + (list.length > shown.length ? `<li>…ir dar ${list.length - shown.length}</li>` : '') + '</ul>';
    box.hidden = false;
    const first = $('.invalid', form);
    (first || box).scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (first) first.focus({ preventScroll: true });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearInvalid();
    const fields = getFields();
    const sent = rows.filter((r) => !isRowEmpty(r)).map((row) => ({
      row,
      item: { ...row, qty: row.qty.trim() || '1', id: undefined, offcut: undefined, offcut_id: row.offcut ? row.offcut.id : null },
    }));
    const errs = validateClient(fields, sent);
    if (errs.length) {
      showErrors(errs);
      return;
    }
    showErrors([]);

    const btn = $('#submit-btn');
    btn.disabled = true;
    btn.textContent = 'Siunčiama…';
    const body = new FormData();
    body.append('csrf', CFG.csrf);
    body.append('website', form.elements.website.value);
    body.append('order', JSON.stringify({ ...fields, items: sent.map((s) => s.item) }));
    files.forEach((f) => body.append('files[]', f, f.name));
    try {
      const res = await fetch('api/submit.php', { method: 'POST', body });
      const data = await res.json().catch(() => ({ ok: false, message: 'Serverio klaida.' }));
      if (!data.ok) {
        const list = [];
        for (const [name, msg] of Object.entries(data.errors || {})) {
          list.push(form.elements[name] ? markInvalid(form.elements[name], msg) : msg);
        }
        for (const [i, itemErr] of Object.entries(data.itemErrors || {})) {
          const row = sent[Number(i)] && sent[Number(i)].row;
          if (!row) continue;
          for (const [f, msg] of Object.entries(itemErr)) {
            const el = f === 'edges' ? $('.mini', trOf(row))
              : f === 'offcut' ? $('.offcut-slot', trOf(row)) : $(`[data-f="${f}"]`, trOf(row));
            list.push(`Nr. ${rows.indexOf(row) + 1}: ${el ? markInvalid(el, msg) : msg}`);
          }
        }
        if (data.message) list.push(data.message);
        showErrors(list.length ? list : ['Nepavyko pateikti užsakymo.']);
        return;
      }
      try { localStorage.removeItem(DRAFT_KEY); } catch (_) { /* ignoruojama */ }
      $('#success-id').textContent = data.id;
      $('#success-total').textContent = fmtMoney(data.total);
      $('#success-link').href = data.url;
      form.hidden = true;
      $('#success').hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (_) {
      showErrors(['Nepavyko susisiekti su serveriu. Patikrinkite interneto ryšį ir bandykite dar kartą.']);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Pateikti užsakymą';
    }
  });

  $('#new-order').addEventListener('click', () => {
    resetAll();
    $('#success').hidden = true;
    form.hidden = false;
  });

  // ---------- pradžia ----------

  if (!loadDraft()) {
    for (let i = 0; i < 5; i++) rows.push(newRow());
  }
  updateConditional();
  renderBrush();
  renderAll();
  addOffcutFromUrl();
})();
