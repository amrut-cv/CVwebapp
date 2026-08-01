<?php
require __DIR__ . '/../session_guard.php';
require_module_access('cashflow_status');
require_once __DIR__ . '/../db.php';
require __DIR__ . '/helpers.php';
$db = getDB();

$entries = $db->query("SELECT * FROM cashflow_entries ORDER BY entry_date ASC")->fetchAll();

$points = [];
foreach ($entries as $e) {
    $c = cf_calc($e);
    $points[] = [
        'date'         => $e['entry_date'],
        'eom'          => $c['eom_position'],
        'total_liquid' => $c['total_liquid_position'],
        'total'        => $c['total_position'],
        'months_total' => $c['months_total'],
    ];
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function fmt_date($d) { return date('j M Y', strtotime($d)); }

$nav_active = 'cashflow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Cashflow trend — CoreVoice</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,sans-serif;background:#f7f8fc;color:#1a1a2e}
    .page{padding:36px 40px;max-width:1200px}
    .page-header{display:flex;justify-content:space-between;align-items:center;gap:14px;padding-bottom:20px;margin-bottom:24px;border-bottom:1px solid #e2e5ef;flex-wrap:wrap}
    .page-header .title-group{display:flex;align-items:center;gap:14px}
    .page-header .icon-badge{width:42px;height:42px;border-radius:11px;background:#1a1a2e;color:#C9972A;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .page-header h1{font-family:Georgia,serif;font-size:1.65rem;font-weight:700;line-height:1.15}
    .page-header h1 span{color:#C9972A}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
    .btn-ghost{background:#fff;border:1.5px solid #d1d5db;color:#374151}
    .btn-ghost:hover{border-color:#C9972A;color:#C9972A}

    .viz-root{
      --surface-1:#fcfcfb; --text-primary:#0b0b0b; --text-secondary:#52514e; --text-muted:#898781;
      --gridline:#e1e0d9; --baseline:#c3c2b7;
      --series-eom:#2a78d6; --series-liquid:#1baf7a; --series-total:#eda100; --series-months:#2a78d6;
    }
    .card{background:var(--surface-1);border:1px solid #e2e5ef;border-radius:12px;padding:24px 26px;box-shadow:0 2px 12px rgba(0,0,0,.05);margin-bottom:24px}
    .card h2{font-size:1rem;font-weight:700;margin-bottom:4px;color:var(--text-primary)}
    .card .card-sub{font-size:.8rem;color:var(--text-secondary);margin-bottom:16px}
    .legend{display:flex;gap:18px;margin-bottom:14px;flex-wrap:wrap}
    .legend-item{display:flex;align-items:center;gap:7px;font-size:.8rem;color:var(--text-secondary)}
    .legend-swatch{width:16px;height:2px;border-radius:1px;flex-shrink:0}
    .chart-wrap{position:relative}
    svg.chart{width:100%;height:auto;display:block;overflow:visible}
    .gridline{stroke:var(--gridline);stroke-width:1}
    .baseline{stroke:var(--baseline);stroke-width:1}
    .axis-label{fill:var(--text-muted);font-size:11px;font-family:inherit}
    .series-line{fill:none;stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
    .series-dot{stroke:var(--surface-1);stroke-width:2}
    .end-label{font-size:11.5px;font-weight:700;font-family:inherit}
    .crosshair{stroke:var(--baseline);stroke-width:1;stroke-dasharray:3,3;pointer-events:none;opacity:0}
    .hit-col{fill:transparent}
    .tooltip{
      position:absolute;pointer-events:none;background:#1a1a2e;color:#fff;border-radius:8px;
      padding:10px 14px;font-size:.78rem;line-height:1.6;box-shadow:0 6px 24px rgba(0,0,0,.18);
      opacity:0;transition:opacity .1s;white-space:nowrap;z-index:10;transform:translate(-50%,-100%);top:-10px;
    }
    .tooltip .t-date{font-weight:700;margin-bottom:4px;color:#fff}
    .tooltip .t-row{display:flex;align-items:center;gap:6px;color:#c9cbe0}
    .tooltip .t-key{width:10px;height:2px;border-radius:1px;flex-shrink:0}
    .tooltip .t-val{font-weight:700;color:#fff;margin-left:auto;padding-left:12px}

    .table-toggle{background:none;border:none;color:#6b7280;font-size:.8rem;font-weight:600;cursor:pointer;padding:0;margin-top:6px}
    .table-toggle:hover{color:#C9972A}
    .data-table{width:100%;border-collapse:collapse;font-size:.83rem;margin-top:16px;display:none}
    .data-table.show{display:table}
    .data-table th{text-align:right;font-size:.68rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.03em;padding:6px 10px;font-weight:700;border-bottom:1.5px solid #e2e5ef}
    .data-table th:first-child{text-align:left}
    .data-table td{padding:8px 10px;text-align:right;border-top:1px solid #f1f0e8;font-variant-numeric:tabular-nums}
    .data-table td:first-child{text-align:left;color:#6b7280;font-weight:600;font-variant-numeric:normal}
    .empty{text-align:center;padding:64px 20px;color:#9ca3af}
    .empty h2{font-size:1.1rem;margin-bottom:12px;color:#6b7280}
  </style>
</head>
<body>
<div class="cv-layout">
  <?php require __DIR__ . '/../nav.php'; ?>
  <div class="page">
    <div class="page-header">
      <div class="title-group">
        <div class="icon-badge">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <h1>Cashflow <span>trend</span></h1>
      </div>
      <a href="index.php" class="btn btn-ghost">&larr; Back to status</a>
    </div>

    <?php if (count($points) < 2): ?>
      <div class="empty">
        <h2>Not enough entries yet for a trend</h2>
        <p>Add at least two cashflow entries to see how things move over time.</p>
      </div>
    <?php else: ?>

      <div class="viz-root">
        <div class="card">
          <h2>Cash position over time</h2>
          <div class="card-sub">EOM, total liquid, and total position — only dates with an entry are plotted.</div>
          <div class="legend" id="legend-money"></div>
          <div class="chart-wrap">
            <svg class="chart" id="chart-money" viewBox="0 0 900 320" preserveAspectRatio="xMidYMid meet"></svg>
            <div class="tooltip" id="tooltip-money"></div>
          </div>
          <button class="table-toggle" onclick="toggleTable('table-money', this)">Show as table &darr;</button>
          <table class="data-table" id="table-money">
            <thead><tr><th>Date</th><th>EOM</th><th>Total liquid</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($points as $p): ?>
              <tr>
                <td><?= h(fmt_date($p['date'])) ?></td>
                <td><?= h(cf_inr($p['eom'])) ?></td>
                <td><?= h(cf_inr($p['total_liquid'])) ?></td>
                <td><?= h(cf_inr($p['total'])) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <div class="card">
          <h2>Months of cash (salary only)</h2>
          <div class="card-sub">Total position &divide; monthly salary outlay — only dates with an entry are plotted.</div>
          <div class="chart-wrap">
            <svg class="chart" id="chart-months" viewBox="0 0 900 280" preserveAspectRatio="xMidYMid meet"></svg>
            <div class="tooltip" id="tooltip-months"></div>
          </div>
          <button class="table-toggle" onclick="toggleTable('table-months', this)">Show as table &darr;</button>
          <table class="data-table" id="table-months">
            <thead><tr><th>Date</th><th>Months of cash</th></tr></thead>
            <tbody>
              <?php foreach ($points as $p): ?>
              <tr>
                <td><?= h(fmt_date($p['date'])) ?></td>
                <td><?= $p['months_total'] === null ? '&mdash;' : h(round($p['months_total'], 2)) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif ?>
  </div>
</div>

<?php if (count($points) >= 2): ?>
<script>
const DATA = <?= json_encode($points) ?>;

function toggleTable(id, btn) {
  const t = document.getElementById(id);
  const show = !t.classList.contains('show');
  t.classList.toggle('show', show);
  btn.textContent = show ? 'Hide table ↑' : 'Show as table ↓';
}

function fmtDateShort(iso) {
  const d = new Date(iso + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function fmtINR(n) {
  const abs = Math.abs(n);
  let s;
  if (abs >= 10000000) s = (n / 10000000).toFixed(1) + 'Cr';
  else if (abs >= 100000) s = (n / 100000).toFixed(1) + 'L';
  else if (abs >= 1000) s = (n / 1000).toFixed(0) + 'k';
  else s = Math.round(n).toString();
  return '₹' + s;
}

function niceTicks(min, max, count) {
  if (min === max) { min -= 1; max += 1; }
  const span = max - min;
  const rawStep = span / count;
  const mag = Math.pow(10, Math.floor(Math.log10(rawStep)));
  const norm = rawStep / mag;
  const step = (norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10) * mag;
  const niceMin = Math.floor(min / step) * step;
  const niceMax = Math.ceil(max / step) * step;
  const ticks = [];
  for (let v = niceMin; v <= niceMax + step * 0.001; v += step) ticks.push(v);
  return ticks;
}

function svgEl(tag, attrs) {
  const el = document.createElementNS('http://www.w3.org/2000/svg', tag);
  for (const k in attrs) el.setAttribute(k, attrs[k]);
  return el;
}

// series: [{key, label, color}]. valueFmt(n) -> string. points: DATA array.
function renderChart(svgId, tooltipId, legendId, series, points, valueFmt) {
  const svg = document.getElementById(svgId);
  const vb = svg.viewBox.baseVal;
  const W = vb.width, H = vb.height;
  const padL = 56, padR = 20, padT = 16, padB = 32;
  const plotW = W - padL - padR, plotH = H - padT - padB;

  const allVals = [];
  points.forEach(p => series.forEach(s => { if (p[s.key] !== null && p[s.key] !== undefined) allVals.push(p[s.key]); }));
  const dataMin = Math.min(0, ...allVals);
  const dataMax = Math.max(...allVals);
  const ticks = niceTicks(dataMin, dataMax, 5);
  const yMin = ticks[0], yMax = ticks[ticks.length - 1];

  const times = points.map(p => new Date(p.date + 'T00:00:00').getTime());
  const tMin = times[0], tMax = times[times.length - 1];
  const tSpan = tMax - tMin || 1;
  const xFor = i => padL + (points.length === 1 ? plotW / 2 : ((times[i] - tMin) / tSpan) * plotW);
  const yFor = v => padT + plotH - ((v - yMin) / (yMax - yMin)) * plotH;

  while (svg.firstChild) svg.removeChild(svg.firstChild);

  // gridlines + y labels
  ticks.forEach(t => {
    const y = yFor(t);
    svg.appendChild(svgEl('line', { x1: padL, x2: W - padR, y1: y, y2: y, class: 'gridline' }));
    const lbl = svgEl('text', { x: padL - 10, y: y + 4, class: 'axis-label', 'text-anchor': 'end' });
    lbl.textContent = valueFmt(t);
    svg.appendChild(lbl);
  });
  svg.appendChild(svgEl('line', { x1: padL, x2: W - padR, y1: padT + plotH, y2: padT + plotH, class: 'baseline' }));

  // x labels (first, last, and a few in between if room)
  const xLabelEvery = Math.max(1, Math.ceil(points.length / 6));
  points.forEach((p, i) => {
    if (i % xLabelEvery !== 0 && i !== points.length - 1) return;
    const lbl = svgEl('text', { x: xFor(i), y: H - 8, class: 'axis-label', 'text-anchor': 'middle' });
    lbl.textContent = fmtDateShort(p.date);
    svg.appendChild(lbl);
  });

  // lines + dots
  series.forEach(s => {
    const coords = points.map((p, i) => [xFor(i), yFor(p[s.key])]).filter((_, i) => points[i][s.key] !== null && points[i][s.key] !== undefined);
    if (coords.length) {
      const d = coords.map((c, i) => (i === 0 ? 'M' : 'L') + c[0] + ',' + c[1]).join(' ');
      svg.appendChild(svgEl('path', { d, class: 'series-line', stroke: s.color }));
    }
    points.forEach((p, i) => {
      if (p[s.key] === null || p[s.key] === undefined) return;
      svg.appendChild(svgEl('circle', { cx: xFor(i), cy: yFor(p[s.key]), r: 4, fill: s.color, class: 'series-dot' }));
    });
    // end label on the last point
    const lastIdx = points.map(p => p[s.key]).map((v, i) => (v !== null && v !== undefined) ? i : -1).filter(i => i >= 0).pop();
    if (lastIdx !== undefined) {
      const lbl = svgEl('text', {
        x: xFor(lastIdx) + 8, y: yFor(points[lastIdx][s.key]) + 4,
        class: 'end-label', fill: s.color
      });
      lbl.textContent = valueFmt(points[lastIdx][s.key]);
      svg.appendChild(lbl);
    }
  });

  // legend
  if (legendId && series.length > 1) {
    const legend = document.getElementById(legendId);
    legend.innerHTML = '';
    series.forEach(s => {
      const item = document.createElement('div');
      item.className = 'legend-item';
      const sw = document.createElement('span');
      sw.className = 'legend-swatch';
      sw.style.background = s.color;
      item.appendChild(sw);
      const txt = document.createElement('span');
      txt.textContent = s.label;
      item.appendChild(txt);
      legend.appendChild(item);
    });
  }

  // crosshair + hover hit columns
  const crosshair = svgEl('line', { x1: 0, x2: 0, y1: padT, y2: padT + plotH, class: 'crosshair' });
  svg.appendChild(crosshair);
  const tooltip = document.getElementById(tooltipId);

  points.forEach((p, i) => {
    const x0 = i === 0 ? padL : (xFor(i - 1) + xFor(i)) / 2;
    const x1 = i === points.length - 1 ? W - padR : (xFor(i) + xFor(i + 1)) / 2;
    const hit = svgEl('rect', { x: x0, y: padT, width: Math.max(1, x1 - x0), height: plotH, class: 'hit-col' });
    hit.addEventListener('pointerenter', () => showTooltip(i));
    hit.addEventListener('pointermove', () => showTooltip(i));
    hit.addEventListener('pointerleave', hideTooltip);
    svg.appendChild(hit);
  });

  function showTooltip(i) {
    const p = points[i];
    crosshair.setAttribute('x1', xFor(i));
    crosshair.setAttribute('x2', xFor(i));
    crosshair.style.opacity = '1';

    tooltip.innerHTML = '';
    const dateEl = document.createElement('div');
    dateEl.className = 't-date';
    dateEl.textContent = fmtDateShort(p.date);
    tooltip.appendChild(dateEl);
    series.forEach(s => {
      const row = document.createElement('div');
      row.className = 't-row';
      const key = document.createElement('span');
      key.className = 't-key';
      key.style.background = s.color;
      row.appendChild(key);
      const name = document.createElement('span');
      name.textContent = s.label;
      row.appendChild(name);
      const val = document.createElement('span');
      val.className = 't-val';
      const v = p[s.key];
      val.textContent = (v === null || v === undefined) ? '—' : valueFmt(v);
      row.appendChild(val);
      tooltip.appendChild(row);
    });

    const svgRect = svg.getBoundingClientRect();
    const wrapRect = svg.parentElement.getBoundingClientRect();
    const scale = svgRect.width / W;
    tooltip.style.left = (xFor(i) * scale + (svgRect.left - wrapRect.left)) + 'px';
    tooltip.style.top = ((yFor(dataMax) * scale) + (svgRect.top - wrapRect.top)) + 'px';
    tooltip.style.opacity = '1';
  }
  function hideTooltip() {
    crosshair.style.opacity = '0';
    tooltip.style.opacity = '0';
  }
}

const COLORS = { eom: '#2a78d6', total_liquid: '#1baf7a', total: '#eda100', months_total: '#2a78d6' };

renderChart('chart-money', 'tooltip-money', 'legend-money', [
  { key: 'eom', label: 'EOM', color: COLORS.eom },
  { key: 'total_liquid', label: 'Total liquid', color: COLORS.total_liquid },
  { key: 'total', label: 'Total', color: COLORS.total },
], DATA, fmtINR);

renderChart('chart-months', 'tooltip-months', null, [
  { key: 'months_total', label: 'Months of cash', color: COLORS.months_total },
], DATA, n => n.toFixed(1));
</script>
<?php endif ?>
</body>
</html>
