/**
 * Plokštės schema su briaunomis (SVG). Ilgis – horizontaliai (tekstūros kryptis).
 * Kraštai: l1 – viršutinis ilgis, l2 – apatinis ilgis, w1 – kairysis plotis, w2 – dešinysis plotis.
 */
window.EdgeBoard = (function () {
  'use strict';

  const SIDE_NAMES = { l1: 'Ilgis 1', l2: 'Ilgis 2', w1: 'Plotis 1', w2: 'Plotis 2' };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function fmt(n) {
    return Number.isFinite(n) ? String(Math.round(n * 10) / 10).replace('.', ',') : '–';
  }

  /** Stačiakampis, išlaikantis ilgio ir pločio santykį, bet ne per siauras, kad būtų galima spustelėti. */
  function fitRect(length, width, boxW, boxH, minSide) {
    let ratio = length > 0 && width > 0 ? length / width : 1.6;
    let w = boxW;
    let h = boxW / ratio;
    if (h > boxH) {
      h = boxH;
      w = boxH * ratio;
    }
    w = Math.max(w, minSide);
    h = Math.max(h, minSide);
    return { x: (boxW - w) / 2, y: (boxH - h) / 2, w, h };
  }

  /**
   * opts: { length, width, edges: {l1,l2,w1,w2}, colorOf(id), labelOf(id), big: bool }
   */
  function svg(opts) {
    const big = !!opts.big;
    const W = big ? 460 : 96;
    const H = big ? 270 : 58;
    const pad = big ? 56 : 9;
    const r = fitRect(opts.length, opts.width, W - pad * 2, H - pad * 2, big ? 40 : 12);
    r.x += pad;
    r.y += pad;
    const x2 = r.x + r.w;
    const y2 = r.y + r.h;
    const lines = {
      l1: [r.x, r.y, x2, r.y],
      l2: [r.x, y2, x2, y2],
      w1: [r.x, r.y, r.x, y2],
      w2: [x2, r.y, x2, y2],
    };

    let out = `<svg class="board-svg${big ? ' board-svg--big' : ''}" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" role="img"`
      + ` aria-label="${esc(describe(opts))}">`;
    out += `<rect class="board-face" x="${r.x}" y="${r.y}" width="${r.w}" height="${r.h}" rx="${big ? 2 : 1}"/>`;

    // tekstūros linijos (išilgai ilgio)
    const grain = big ? 7 : 3;
    for (let i = 1; i <= grain; i++) {
      const gy = r.y + (r.h * i) / (grain + 1);
      const wobble = big ? 3 : 1;
      out += `<path class="board-grain" d="M${r.x + 2} ${gy} Q ${r.x + r.w * 0.35} ${gy - wobble} ${r.x + r.w * 0.55} ${gy} T ${x2 - 2} ${gy}"/>`;
    }

    for (const side of ['l1', 'l2', 'w1', 'w2']) {
      const [a, b, c, d] = lines[side];
      const id = opts.edges[side] || '';
      if (id) {
        out += `<line class="edge-on" x1="${a}" y1="${b}" x2="${c}" y2="${d}" stroke="${esc(opts.colorOf(id))}" stroke-width="${big ? 9 : 4}" stroke-linecap="square"/>`;
      } else {
        out += `<line class="edge-off" x1="${a}" y1="${b}" x2="${c}" y2="${d}"/>`;
      }
      out += `<line class="edge-hit" data-side="${side}" x1="${a}" y1="${b}" x2="${c}" y2="${d}" stroke-width="${big ? 30 : 13}">`
        + `<title>${esc(SIDE_NAMES[side] + (id ? ': ' + opts.labelOf(id) : ': be briaunos'))} – spustelėkite</title></line>`;
    }

    if (big) {
      const mid = (r.x + x2) / 2;
      const midY = (r.y + y2) / 2;
      const lab = (side) => SIDE_NAMES[side] + (opts.edges[side] ? ' · ' + opts.labelOf(opts.edges[side]) : '');
      out += `<text class="side-label" x="${mid}" y="${r.y - 16}" text-anchor="middle">${esc(lab('l1'))}</text>`;
      out += `<text class="side-label" x="${mid}" y="${y2 + 28}" text-anchor="middle">${esc(lab('l2'))}</text>`;
      out += `<text class="side-label" transform="translate(${r.x - 18} ${midY}) rotate(-90)" text-anchor="middle">${esc(lab('w1'))}</text>`;
      out += `<text class="side-label" transform="translate(${x2 + 26} ${midY}) rotate(-90)" text-anchor="middle">${esc(lab('w2'))}</text>`;
      out += `<text class="dim-label" x="${mid}" y="${midY - 4}" text-anchor="middle">${fmt(opts.length)} × ${fmt(opts.width)} mm</text>`;
      out += `<text class="grain-label" x="${mid}" y="${midY + 16}" text-anchor="middle">⟵ tekstūros kryptis ⟶</text>`;
    }
    return out + '</svg>';
  }

  function describe(opts) {
    const on = Object.keys(SIDE_NAMES).filter((s) => opts.edges[s]);
    if (!on.length) return 'Detalė be briaunų';
    return 'Briaunuota: ' + on.map((s) => SIDE_NAMES[s] + ' ' + opts.labelOf(opts.edges[s])).join(', ');
  }

  return { svg, describe, SIDE_NAMES };
})();
