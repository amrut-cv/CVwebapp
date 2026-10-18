(function () {
  'use strict';

  var C = window.PlannerCalc;
  var API = window.PLANNER_API || '/CVwebapp/api/profitability_planner.php';
  var root = document.getElementById('app');
  var MN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var GROUPS = ['Leaders', 'Managers', 'ICs'];
  var GLABEL = { Leaders: 'Leaders', Managers: 'Managers', ICs: 'Individual contributors' };

  var st = { meta: { id: null, name: '' }, data: null, list: [], tab: 'overview', dirty: false, compare: null, r: null };

  /* ---------- helpers ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function num(v) { v = Number(v); return isFinite(v) ? v : 0; }
  function pad(n) { return n < 10 ? '0' + n : String(n); }
  function uid() { return 'r' + Math.random().toString(36).slice(2, 8); }
  function zeros(n) { var a = []; for (var i = 0; i < n; i++) a.push(0); return a; }
  function getp(o, p) { return String(p).split('.').reduce(function (a, k) { return a == null ? a : a[k]; }, o); }
  function setp(o, p, v) {
    var ks = String(p).split('.'), last = ks.pop();
    var t = ks.reduce(function (a, k) { return a[k]; }, o);
    t[last] = v;
  }
  function fmtK(r) {
    var k = Math.round(num(r) / 1000);
    var s = Math.abs(k).toLocaleString('en-IN');
    return k < 0 ? '−' + s : s;
  }
  function fmtL(v) { return (v < 0 ? '−' : '') + '₹' + (Math.abs(v) / 100000).toFixed(1) + 'L'; }
  function inputK(r) { return String(Math.round(num(r) / 10) / 100); }
  function fmtOpening(r) { r = Math.round(num(r)); return r === 0 ? '' : r.toLocaleString('en-IN'); }
  function monthLabel(key, withYear) {
    var p = String(key).split('-');
    return MN[+p[1] - 1] + (withYear ? ' ' + String(p[0]).slice(2) : '');
  }
  function monthLong(key) { var p = String(key).split('-'); return MN[+p[1] - 1] + ' ' + p[0]; }
  function nMonths() { return st.data.months.length; }
  function fmt(v, f) {
    switch (f) {
      case 'k': return fmtK(v);
      case 'kneg': return fmtK(-num(v));
      case 'x1': return v == null ? '—' : Number(v).toFixed(1);
      default: return String(v);
    }
  }

  /* ---------- scenario data ---------- */
  function blank() {
    var months = [], y = 2026, m = 10;
    for (var i = 0; i < 6; i++) {
      months.push(y + '-' + pad(m + 1));
      m++; if (m > 11) { m = 0; y++; }
    }
    return {
      version: 1, months: months, opening: 0,
      settings: { gstInputCredit: 0, priorMonthGst: 0, crisis: { on: false, fromMonth: months[0], pct: { Leaders: 40, Managers: 25, ICs: 10 } } },
      invoices: [], salaries: [], rent: [], consultants: [], expenses: [],
      gstOverride: [null, null, null, null, null, null], buffer: zeros(6)
    };
  }
  function normalize(d) {
    var b = blank();
    d = d || {};
    d.months = (d.months && d.months.length) ? d.months : b.months;
    var n = d.months.length;
    d.opening = num(d.opening);
    d.settings = d.settings || {};
    d.settings.priorMonthGst = num(d.settings.priorMonthGst);
    if (d.settings.gstInputCredit == null) {
      d.settings.gstInputCredit = d.settings.gstInputCreditPct != null ? Math.round(num(d.settings.gstInputCreditPct) / 100 * d.settings.priorMonthGst) : 0;
    }
    d.settings.gstInputCredit = num(d.settings.gstInputCredit);
    delete d.settings.gstInputCreditPct;
    d.settings.crisis = d.settings.crisis || {};
    d.settings.crisis.on = !!d.settings.crisis.on;
    d.settings.crisis.fromMonth = d.settings.crisis.fromMonth || d.months[0];
    d.settings.crisis.pct = d.settings.crisis.pct || {};
    GROUPS.forEach(function (g) { if (d.settings.crisis.pct[g] == null) d.settings.crisis.pct[g] = b.settings.crisis.pct[g]; });
    delete d.receivables;
    ['invoices', 'salaries', 'rent', 'consultants', 'expenses'].forEach(function (k) { d[k] = d[k] || []; });
    ['invoices', 'salaries', 'rent', 'consultants', 'expenses'].forEach(function (k) {
      d[k].forEach(function (row) {
        row.id = row.id || uid();
        row.amounts = row.amounts || [];
        while (row.amounts.length < n) row.amounts.push(0);
      });
    });
    d.invoices.forEach(function (row) {
      row.mode = row.mode === 'export' ? 'export' : 'indian';
      delete row.collect; delete row.prob; delete row.gstPct; delete row.tdsPct; delete row.lagDays; delete row.invoiceDay;
    });
    d.gstOverride = d.gstOverride || [];
    while (d.gstOverride.length < n) d.gstOverride.push(null);
    d.buffer = d.buffer || [];
    while (d.buffer.length < n) d.buffer.push(0);
    return d;
  }

  /* ---------- api ---------- */
  function post(body) {
    return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok || j.error) throw new Error(j.error || ('HTTP ' + r.status));
          return j;
        });
      });
  }
  function getList() {
    return fetch(API, { credentials: 'same-origin' }).then(function (r) {
      return r.json().then(function (j) {
        if (!Array.isArray(j)) throw new Error((j && j.error) || 'Could not load scenarios');
        return j;
      });
    });
  }
  function showError(e) {
    root.innerHTML = '<div class="card"><h2>Something went wrong</h2><p class="hint">' + esc(e.message || e) + '</p></div>';
  }

  /* ---------- tooltips ---------- */
  var TIPS = {
    'inv.exGst': 'Invoices you raise this month, before GST.',
    'inv.gst': 'GST charged on those invoices: 18% for Indian clients, none for export clients.',
    'inv.total': 'Invoice value including GST. This is what clients are billed.',
    'inv.tds': 'TDS that Indian clients deduct and deposit with the government instead of paying you (10%). It reduces the cash that reaches your bank.',
    'inv.collection': 'What this month\'s invoices bring into the bank: total + GST \u2212 TDS. The planner assumes clients pay in the month you invoice them.',
    'out.payroll': 'Gross monthly pay for everyone on the Salaries tab, after any crisis-mode cuts.',
    'out.consultants': 'External consultant payments from the Rent and expenses tab.',
    'out.rent': 'Seats, podcast studio and day-passes from the Rent and expenses tab.',
    'out.other': 'Software, client-facing ads and studio, travel and other lines from the Rent and expenses tab.',
    'out.gst': 'GST you charged last month, less the fixed input credit you set, paid to the government this month. Edit it on the Rent and expenses tab.',
    'out.buffer': 'A cushion for costs that usually run over plan. Set it on the Rent and expenses tab.',
    'out.total': 'Everything that leaves the bank this month: payroll, consultants, rent, other expenses, GST paid and the buffer.',
    'out.costs': 'Payroll + consultants + rent + other expenses + overrun buffer. GST is left out because it passes through to the government.',
    'profit': 'Invoices raised (ex-GST) minus costs. GST and TDS are left out.',
    'net': 'Collection minus everything paid out this month.',
    'opening': 'Cash at the start of each month. The first month is the number you typed.',
    'closing': 'Opening cash plus net for the month. Red means the account is overdrawn.',
    'coverage': 'Closing cash divided by that month\'s payroll: how many months of salaries the cash would cover.',
    'out.nonPayroll': 'Consultants, rent, other expenses, GST paid and the buffer added together.'
  };
  var GTIPS = {
    invoicing: 'Invoices you raise each month and the cash they bring in.',
    paid: 'Money leaving the bank each month.',
    profit: 'How the business does on billing and costs, ignoring GST and cash timing.',
    cash: 'How the bank balance moves month to month.'
  };
  function tip(text) {
    return '<span class="q" tabindex="0" role="note" aria-label="' + esc(text) + '" data-tip="' + esc(text) + '">?</span>';
  }
  var NO_NOTE = 'No note yet. Type one in the field below the name.';
  function noteTip(path, note) {
    var t = note ? note : NO_NOTE;
    return '<span class="q' + (note ? '' : ' q-empty') + '" tabindex="0" role="note" aria-label="' + esc(t) + '" data-note-for="' + path + '" data-tip="' + esc(t) + '">?</span>';
  }
  function tipOf(base) { return TIPS[base] ? tip(TIPS[base]) : ''; }

  /* ---------- html builders ---------- */
  function colspan() { return nMonths() + 1; }
  function head(first) {
    var h = '<tr><th>' + (first || '') + '</th>';
    st.data.months.forEach(function (k, i) { h += '<th>' + monthLabel(k, i === 0 || k.slice(5) === '01') + '</th>'; });
    return h + '</tr>';
  }
  function grp(label, tipText) { return '<tr class="grp"><td colspan="' + colspan() + '">' + label + (tipText ? tip(tipText) : '') + '</td></tr>'; }
  function vrow(label, base, f, cls, neg) {
    var n = nMonths(), h = '<tr class="' + (cls || '') + '"><td>' + label + tipOf(base) + '</td>';
    for (var i = 0; i < n; i++) {
      h += '<td data-v="' + base + '.' + i + '" data-f="' + (f || 'k') + '"' + (neg ? ' data-neg="1"' : '') + (neg === 2 ? ' data-bg="1"' : '') + '></td>';
    }
    return h + '</tr>';
  }
  function cellK(path, r) {
    return '<td><input class="cell" inputmode="decimal" data-path="' + path + '" data-unit="k" value="' + inputK(r) + '"></td>';
  }
  function cellsK(base, arr) {
    var h = '';
    for (var i = 0; i < nMonths(); i++) h += cellK(base + '.' + i, arr[i]);
    return h;
  }
  function mini(path, v) {
    return '<input class="mini" inputmode="decimal" data-path="' + path + '" data-unit="n" value="' + esc(v == null ? '' : v) + '">';
  }
  function txt(path, v) {
    return '<input class="txt" data-path="' + path + '" data-unit="str" value="' + esc(v) + '">';
  }
  function delBtn(path) { return '<button class="xbtn" data-del="' + path + '" title="Remove" aria-label="Remove row">&times;</button>'; }
  function addRow(what, label) {
    return '<tr class="addrow"><td colspan="' + colspan() + '"><button data-add="' + what + '">+ ' + label + '</button></td></tr>';
  }
  function kindSel(path, v, opts) {
    var h = '<select class="kind" data-path="' + path + '" data-unit="str">';
    opts.forEach(function (o) { h += '<option' + (o === v ? ' selected' : '') + '>' + o + '</option>'; });
    return h + '</select>';
  }

  function sel(path, val, pairs, cls) {
    var h = '<select class="' + cls + '" data-path="' + path + '" data-unit="str">';
    pairs.forEach(function (p) { h += '<option value="' + esc(p[0]) + '"' + (p[0] === val ? ' selected' : '') + '>' + esc(p[1]) + '</option>'; });
    return h + '</select>';
  }
  function radios(name, path, val, pairs) {
    return pairs.map(function (p) {
      return '<label class="rad"><input type="radio" name="' + name + '" data-path="' + path + '" data-unit="str" value="' + p[0] + '"' + (p[0] === val ? ' checked' : '') + '> ' + p[1] + '</label>';
    }).join('');
  }
  var MODE_OPTS = [['indian', 'India'], ['export', 'Export']];

  function invSummary() {
    return grp('Invoicing: raised this month', GTIPS.invoicing) +
      vrow('Total invoices (ex-GST)', 'inv.exGst') +
      vrow('GST', 'inv.gst') +
      vrow('Total + GST', 'inv.total') +
      vrow('TDS deposited by clients', 'inv.tds', 'kneg') +
      vrow('Collection (total + GST &minus; TDS)', 'inv.collection', 'k', 'bold');
  }

  function topBar() {
    var opts = '';
    if (!st.meta.id) opts += '<option value="">' + esc(st.meta.name || '(unsaved scenario)') + '</option>';
    st.list.forEach(function (s) {
      opts += '<option value="' + s.id + '"' + (String(s.id) === String(st.meta.id) ? ' selected' : '') + '>' + esc(s.name) + '</option>';
    });
    return '<div class="bar"><h1>Profitability <span>planner</span></h1>' +
      '<select id="scSel" aria-label="Scenario">' + opts + '</select>' +
      '<button class="btn primary" data-act="save">Save</button>' +
      '<button class="btn" data-act="saveas">Save as</button>' +
      '<button class="btn" data-act="rename">Rename</button>' +
      '<button class="btn" data-act="new">New blank</button>' +
      '<button class="btn danger" data-act="delete">Delete</button>' +
      '<span class="dirty" id="dirty" style="display:none">Unsaved changes</span></div>';
  }

  function overviewPanel() {
    var d = st.data, n = nMonths();
    var win = monthLong(d.months[0]) + ' to ' + monthLong(d.months[n - 1]);
    return '<div class="card">' +
      '<div class="openbox"><label for="open">Opening cash on 1 ' + monthLabel(d.months[0], false) + ' ' + d.months[0].slice(0, 4) + '</label>' + tip('Money in the bank on day one of the window. You type this each time.') +
      '&#8377; <input id="open" inputmode="numeric" placeholder="0" data-path="opening" data-unit="rs" value="' + fmtOpening(d.opening) + '">' +
      '<span class="span">Window: ' + win + '</span></div>' +
      '<div class="cards" id="cards"></div>' +
      '<div class="chart" id="chart"></div>' +
      '<div class="wrap"><table class="g">' + head('') +
      invSummary() +
      grp('Paid out', GTIPS.paid) +
      vrow('Payroll (gross)', 'out.payroll') +
      vrow('Consultants', 'out.consultants') +
      vrow('Rent', 'out.rent') +
      vrow('Other expenses', 'out.other') +
      vrow('GST paid to government', 'out.gst') +
      vrow('Overrun buffer', 'out.buffer') +
      vrow('Total paid out', 'out.total', 'k', 'tot') +
      grp('Profit', GTIPS.profit) +
      vrow('Costs (excl. GST)', 'out.costs') +
      vrow('Profit for the month (invoices &minus; costs)', 'profit', 'k', 'tot', 1) +
      grp('Cash position', GTIPS.cash) +
      vrow('Net (collection &minus; paid out)', 'net', 'k', '', 1) +
      vrow('Opening cash', 'opening', 'k', '', 1) +
      vrow('Closing cash', 'closing', 'k', 'tot', 2) +
      vrow('Months of payroll covered', 'coverage', 'x1', 'sub') +
      '</table></div>' +
      '<div class="note">All figures in &#8377; thousands. Edit the numbers on the other tabs and everything here updates.</div></div>' +
      '<div class="card"><h2>Compare scenarios</h2>' +
      '<div class="cmpsel"><label for="cmpSel">Compare with</label><select id="cmpSel">' + compareOptions() + '</select></div>' +
      '<div class="cmpwrap" id="cmp"></div></div>';
  }

  function compareOptions() {
    var h = '<option value="">Choose a saved scenario</option>';
    st.list.forEach(function (s) {
      if (String(s.id) === String(st.meta.id)) return;
      h += '<option value="' + s.id + '"' + (st.compare && String(st.compare.id) === String(s.id) ? ' selected' : '') + '>' + esc(s.name) + '</option>';
    });
    return h;
  }

  function invoicesPanel() {
    var d = st.data, h = '';
    h += '<div class="card"><h2>Invoices to raise' + tip('One row per client. Enter the amount you will invoice each month, before GST.') + '</h2>' +
      '<div class="hint">Amounts are ex-GST, in &#8377; thousands. Invoice each client as India (18% GST, 10% TDS) or Export (no GST, no TDS). The planner assumes each client pays in the month you invoice them.</div>' +
      '<div class="wrap"><table class="g inv">' + head('Client');
    d.invoices.forEach(function (inv, i) {
      var p = 'invoices.' + i;
      h += '<tr><td><div class="lblrow">' + txt(p + '.client', inv.client) + delBtn(p) + '</div>' +
        '<div class="set"><span class="rl">Invoice to</span>' + radios('m' + i, p + '.mode', inv.mode, MODE_OPTS) + tip('India: 18% GST and 10% TDS. Export: no GST and no TDS.') +
        '</div></td>' + cellsK(p + '.amounts', inv.amounts) + '</tr>';
    });
    h += addRow('invoices', 'Add client or pipeline deal') + '</table></div></div>';

    h += '<div class="card"><div class="wrap"><table class="g">' + head('') + invSummary() + '</table></div></div>';

    return h;
  }

  function salariesPanel() {
    var d = st.data, c = d.settings.crisis, h = '';
    var monthOpts = d.months.map(function (k) { return '<option value="' + k + '"' + (k === c.fromMonth ? ' selected' : '') + '>' + monthLabel(k, true) + '</option>'; }).join('');
    h += '<div class="card"><h2>Salaries' + tip('Gross monthly pay for each person. Totals use these amounts after any crisis cuts.') + '</h2>' +
      '<div class="hint">Gross monthly pay in &#8377; thousands. Totals count what you pay out. Crisis mode cuts each group by the percentage you set, from the month you pick.</div>' +
      '<div class="crisis"><label><input type="checkbox" data-path="settings.crisis.on" data-unit="bool"' + (c.on ? ' checked' : '') + '> <strong>Crisis mode</strong></label>' + tip('Cuts each group\'s pay by the percentage you set, from the month you pick, so you can see the effect of a salary cut.') +
      '<span class="lab">from</span><select data-path="settings.crisis.fromMonth" data-unit="str">' + monthOpts + '</select>';
    GROUPS.forEach(function (g) {
      h += '<span class="lab">' + g + ' cut</span><input type="number" min="0" max="100" data-path="settings.crisis.pct.' + g + '" data-unit="n" value="' + esc(c.pct[g]) + '">%';
    });
    h += '</div><div class="wrap"><table class="g wide">' + head('Person');
    GROUPS.forEach(function (g) {
      var label = GLABEL[g] + (c.on ? ' (after cuts)' : '');
      h += '<tr class="grp"><td>' + label + '</td>';
      for (var i = 0; i < nMonths(); i++) h += '<td class="n" data-v="payrollByGroup.' + g + '.' + i + '" data-f="k"></td>';
      h += '</tr>';
      d.salaries.forEach(function (p, idx) {
        if ((GROUPS.indexOf(p.group) >= 0 ? p.group : 'ICs') !== g) return;
        var path = 'salaries.' + idx;
        h += '<tr><td><div class="lblrow">' + txt(path + '.name', p.name) + noteTip(path, p.note) +
          '<select class="kind" data-path="' + path + '.group" data-unit="str" data-rebuild="1">' +
          GROUPS.map(function (o) { return '<option' + (o === p.group ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select>' +
          delBtn(path) + '</div><input class="txt note" data-path="' + path + '.note" data-unit="str" placeholder="Add a note (shows in the ? tooltip)" value="' + esc(p.note || '') + '"></td>' + cellsK(path + '.amounts', p.amounts) + '</tr>';
      });
    });
    h += addRow('salaries', 'Add person') + vrow('Total payroll', 'out.payroll', 'k', 'tot') + '</table></div></div>';
    return h;
  }

  function expensesPanel() {
    var d = st.data, h = '<div class="card"><h2>Rent and other expenses' + tip('Fixed and recurring costs outside payroll, plus the GST you pay and a buffer for overruns.') + '</h2>' +
      '<div class="hint">&#8377; thousands per month. Bordered cells are yours to edit. Amounts are gross, so there is no separate TDS line.</div>' +
      '<div class="wrap"><table class="g wide">' + head('Line');

    function section(title, arr, key, kinds, addLabel, totalPath, tipText) {
      var s = '<tr class="grp"><td>' + title + tip(tipText) + '</td>';
      for (var i = 0; i < nMonths(); i++) s += '<td class="n" data-v="' + totalPath + '.' + i + '" data-f="k"></td>';
      s += '</tr>';
      arr.forEach(function (row, idx) {
        var p = key + '.' + idx;
        s += '<tr><td><div class="lblrow">' + txt(p + '.label', row.label) + (kinds ? kindSel(p + '.kind', row.kind, kinds) : '') + delBtn(p) + '</div></td>' + cellsK(p + '.amounts', row.amounts) + '</tr>';
      });
      return s + addRow(key, addLabel);
    }
    h += section('Rent', d.rent, 'rent', ['fixed', 'varies'], 'Add rent line', 'out.rent', 'Seats, podcast studio and day-passes. "fixed" and "varies" are just labels for you.');
    h += section('Consultants', d.consultants, 'consultants', null, 'Add consultant', 'out.consultants', 'External consultants and what you expect to pay them each month.');
    h += section('Other expenses', d.expenses, 'expenses', ['recurring', 'one-off'], 'Add bucket or vendor', 'out.other', 'Main buckets and vendors. "recurring" and "one-off" are labels for you; the amounts are what count.');

    h += '<tr class="grp"><td>Tax and buffer' + tip('GST paid to the government, and a cushion for overruns.') + '</td>';
    for (var i = 0; i < nMonths(); i++) h += '<td class="n" data-v="out.gst.' + i + '" data-f="k"></td>';
    h += '</tr>';
    h += '<tr><td><div class="lblrow"><span>GST paid to government</span><span class="tag">auto</span>' + tipOf('out.gst') + '</div>' +
      '<div class="set">Last month&#8217;s output GST, less &#8377;<input class="mini" style="width:80px" inputmode="decimal" data-path="settings.gstInputCredit" data-unit="rs" value="' + esc(Math.round(d.settings.gstInputCredit)) + '"> input credit each month &middot; first month uses output GST of ' +
      '&#8377;<input class="mini" style="width:80px" inputmode="decimal" data-path="settings.priorMonthGst" data-unit="rs" value="' + esc(Math.round(d.settings.priorMonthGst)) + '"> &middot; type in a cell to override</div></td>';
    for (i = 0; i < nMonths(); i++) {
      var ov = d.gstOverride[i];
      h += '<td><input class="cell" inputmode="decimal" data-path="gstOverride.' + i + '" data-unit="k" data-ph="gstAuto.' + i + '" value="' + (ov === null || ov === undefined || ov === '' ? '' : inputK(ov)) + '"></td>';
    }
    h += '</tr><tr><td><div class="lblrow"><span>Overrun buffer</span>' + tipOf('out.buffer') + '</div><div class="set">Cushion for likely overruns</div></td>' + cellsK('buffer', d.buffer) + '</tr>';
    h += vrow('Total outside payroll', 'out.nonPayroll', 'k', 'tot');
    return h + '</table></div></div>';
  }

  function tabsBar() {
    var t = [['overview', 'Overview'], ['invoices', 'Invoices'], ['salaries', 'Salaries'], ['expenses', 'Rent and expenses']];
    return '<div class="tabs">' + t.map(function (x) {
      return '<button data-tab="' + x[0] + '" class="' + (st.tab === x[0] ? 'on' : '') + '">' + x[1] + '</button>';
    }).join('') + '</div>';
  }

  function buildAll() {
    root.innerHTML = topBar() + tabsBar() +
      '<div class="panel" data-panel="overview">' + overviewPanel() + '</div>' +
      '<div class="panel" data-panel="invoices">' + invoicesPanel() + '</div>' +
      '<div class="panel" data-panel="salaries">' + salariesPanel() + '</div>' +
      '<div class="panel" data-panel="expenses">' + expensesPanel() + '</div>';
    showTab(st.tab);
    paint();
    setDirty(st.dirty);
  }

  function showTab(t) {
    st.tab = t;
    root.querySelectorAll('.tabs button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-tab') === t); });
    root.querySelectorAll('.panel').forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-panel') === t); });
  }

  /* ---------- painting computed values ---------- */
  function paint() {
    var r = C.compute(st.data);
    st.r = r;
    root.querySelectorAll('[data-v]').forEach(function (el) {
      var v = getp(r, el.getAttribute('data-v'));
      el.textContent = fmt(v, el.getAttribute('data-f') || 'k');
      if (el.hasAttribute('data-neg')) {
        var neg = num(v) < 0;
        el.classList.toggle('neg', neg);
        if (el.hasAttribute('data-bg')) el.classList.toggle('negbg', neg);
      }
    });
    root.querySelectorAll('[data-ph]').forEach(function (el) {
      el.placeholder = String(Math.round(num(getp(r, el.getAttribute('data-ph'))) / 1000));
    });
    paintCards(r);
    paintChart(r);
    paintCompare();
  }

  function card(cls, label, value, tipText) {
    return '<div class="mc ' + cls + '"><div class="l">' + label + (tipText ? tip(tipText) : '') + '</div><div class="v">' + value + '</div></div>';
  }
  function paintCards(r) {
    var el = root.querySelector('#cards');
    if (!el) return;
    var m = st.data.months, burn = -r.avgNet;
    el.innerHTML =
      (r.firstNegative >= 0 ? card('bad', 'Cash goes negative', monthLong(m[r.firstNegative]), 'The first month the closing balance drops below zero.') : card('good', 'Cash stays positive', 'Through ' + monthLong(m[m.length - 1]), 'The closing balance never drops below zero in this window.')) +
      card('', burn >= 0 ? 'Average monthly burn' : 'Average monthly surplus', fmtL(Math.abs(burn)), 'Average of collection minus cash paid out across the window. Burn means the balance is shrinking.') +
      card('', 'Lowest closing cash', fmtL(r.lowest) + ' <span style="font-size:.75rem;font-weight:400;color:#6b7280">' + monthLabel(m[r.lowestIdx], false) + '</span>', 'The lowest month-end balance in the window, and the month it happens.');
  }

  function paintChart(r) {
    var el = root.querySelector('#chart');
    if (!el) return;
    var n = r.closing.length;
    var maxPos = Math.max(0, Math.max.apply(null, r.closing));
    var maxNeg = Math.max(0, -Math.min.apply(null, r.closing));
    var range = (maxPos + maxNeg) || 1;
    var top = 30, h = 130, k = h / range, zero = top + maxPos * k;
    var slot = 620 / n, bw = Math.min(56, slot * 0.55);
    var s = '<svg viewBox="0 0 640 190" role="img" aria-label="Closing cash by month"><line x1="10" y1="' + zero + '" x2="630" y2="' + zero + '" stroke="#d1d5db" stroke-width="1"/>';
    for (var i = 0; i < n; i++) {
      var cx = 10 + slot * (i + 0.5), v = r.closing[i], hh = Math.abs(v) * k;
      var color = v < 0 ? '#E24B4A' : (v < r.out.payroll[i] ? '#BA7517' : '#1D9E75');
      var y = v >= 0 ? zero - hh : zero;
      s += '<text x="' + cx + '" y="14" font-size="12" fill="#6b7280" text-anchor="middle">' + monthLabel(st.data.months[i], false) + '</text>';
      s += '<rect x="' + (cx - bw / 2) + '" y="' + y + '" width="' + bw + '" height="' + Math.max(hh, 1) + '" fill="' + color + '"/>';
      s += '<text x="' + cx + '" y="' + (v >= 0 ? y - 4 : y + hh + 13) + '" font-size="11" fill="#4b5563" text-anchor="middle">' + fmtK(v) + '</text>';
    }
    el.innerHTML = s + '</svg>';
  }

  function paintCompare() {
    var box = root.querySelector('#cmp');
    if (!box) return;
    if (!st.compare) {
      box.innerHTML = '<p class="hint">Pick another saved scenario to see closing cash side by side.</p>';
      return;
    }
    var a = st.r, b = C.compute(st.compare.data), m = st.data.months;
    function row(label, arr, cls) {
      return '<tr class="' + (cls || '') + '"><td>' + esc(label) + '</td>' + arr.map(function (v) {
        return '<td class="' + (v < 0 ? 'neg' : '') + '">' + fmtK(v) + '</td>';
      }).join('') + '</tr>';
    }
    var diff = a.closing.map(function (v, i) { return v - b.closing[i]; });
    var an = a.firstNegative >= 0 ? monthLong(m[a.firstNegative]) : 'none in window';
    var bn = b.firstNegative >= 0 ? monthLong(m[b.firstNegative]) : 'none in window';
    var h = '<div class="wrap"><table class="g"><tr><th>Closing cash</th>' + m.map(function (k, i) { return '<th>' + monthLabel(k, i === 0) + '</th>'; }).join('') + '</tr>' +
      row(st.meta.name || 'This scenario', a.closing) + row(st.compare.name, b.closing) + row('Difference', diff, 'bold') + '</table></div>' +
      '<div class="note">Cash goes negative: ' + esc(st.meta.name || 'this scenario') + ' &mdash; ' + an + '; ' + esc(st.compare.name) + ' &mdash; ' + bn + '.</div>';
    box.innerHTML = h;
  }

  /* ---------- state changes ---------- */
  function setDirty(v) {
    st.dirty = v;
    var el = root.querySelector('#dirty');
    if (el) el.style.display = v ? '' : 'none';
  }

  function onEdit(e) {
    var t = e.target;
    var p = t.getAttribute && t.getAttribute('data-path');
    if (!p) return;
    if (t.type === 'radio' && !t.checked) return;
    var u = t.getAttribute('data-unit'), val;
    if (u === 'bool') val = t.checked;
    else if (u === 'k') {
      var raw = t.value.replace(/[,\s]/g, '');
      if (p.indexOf('gstOverride.') === 0 && raw === '') val = null;
      else val = Math.round(num(raw) * 1000);
    } else if (u === 'n') val = num(t.value.replace(/[,\s]/g, ''));
    else if (u === 'rs') val = Math.round(num(t.value.replace(/[^0-9.\-]/g, '')));
    else val = t.value;
    setp(st.data, p, val);
    setDirty(true);
    if (/\.note$/.test(p)) {
      var qn = root.querySelector('.q[data-note-for="' + p.slice(0, -5) + '"]');
      if (qn) {
        var nv = String(val).trim();
        qn.setAttribute('data-tip', nv || NO_NOTE);
        qn.setAttribute('aria-label', nv || NO_NOTE);
        qn.classList.toggle('q-empty', !nv);
      }
      return;
    }
    if (t.hasAttribute('data-rebuild') && e.type === 'change') { buildAll(); return; }
    if (p === 'settings.crisis.on' && e.type === 'change') { buildAll(); return; }
    paint();
  }

  function blankRow(what) {
    var n = nMonths(), z = zeros(n), d = st.data;
    if (what === 'invoices') d.invoices.push({ id: uid(), client: 'New client', mode: 'indian', amounts: z });
    else if (what === 'salaries') d.salaries.push({ id: uid(), name: 'New person', group: 'ICs', amounts: z });
    else if (what === 'rent') d.rent.push({ id: uid(), label: 'New rent line', kind: 'fixed', amounts: z });
    else if (what === 'consultants') d.consultants.push({ id: uid(), label: 'New consultant', amounts: z });
    else if (what === 'expenses') d.expenses.push({ id: uid(), label: 'New expense', kind: 'recurring', amounts: z });
  }

  function save(asNew) {
    var name = st.meta.name;
    if (asNew || !st.meta.id || !name) {
      name = window.prompt('Scenario name', name || '');
      if (!name || !name.trim()) return;
      name = name.trim();
    }
    post({ action: 'save', id: asNew ? null : st.meta.id, name: name, data: st.data }).then(function (j) {
      st.meta = { id: j.id, name: j.name };
      return getList();
    }).then(function (list) {
      st.list = list;
      st.dirty = false;
      buildAll();
    }).catch(function (e) { window.alert('Save failed: ' + e.message); });
  }

  function openScenario(id) {
    return post({ action: 'load', id: id }).then(function (j) {
      st.meta = { id: j.scenario.id, name: j.scenario.name };
      st.data = normalize(j.scenario.data);
      st.compare = null;
      st.dirty = false;
      buildAll();
    });
  }

  function newBlank(name) {
    st.meta = { id: null, name: name || '' };
    st.data = blank();
    st.compare = null;
    st.dirty = true;
    buildAll();
  }

  function confirmDiscard() { return !st.dirty || window.confirm('You have unsaved changes. Discard them?'); }

  function onClick(e) {
    var t = e.target.closest('[data-tab],[data-del],[data-add],[data-act]');
    if (!t) return;
    if (t.hasAttribute('data-tab')) { showTab(t.getAttribute('data-tab')); return; }
    if (t.hasAttribute('data-add')) { blankRow(t.getAttribute('data-add')); setDirty(true); buildAll(); return; }
    if (t.hasAttribute('data-del')) {
      var ks = t.getAttribute('data-del').split('.');
      st.data[ks[0]].splice(+ks[1], 1);
      setDirty(true); buildAll(); return;
    }
    var act = t.getAttribute('data-act');
    if (act === 'save') save(false);
    else if (act === 'saveas') save(true);
    else if (act === 'rename') {
      var nn = window.prompt('Scenario name', st.meta.name || '');
      if (nn && nn.trim()) { st.meta.name = nn.trim(); setDirty(true); if (st.meta.id) save(false); else buildAll(); }
    } else if (act === 'new') {
      if (!confirmDiscard()) return;
      newBlank('');
    } else if (act === 'delete') {
      if (!st.meta.id) { window.alert('This scenario has not been saved yet.'); return; }
      if (!window.confirm('Delete "' + st.meta.name + '"? This cannot be undone.')) return;
      post({ action: 'delete', id: st.meta.id }).then(getList).then(function (list) {
        st.list = list;
        if (list.length) return openScenario(list[0].id);
        newBlank('');
      }).catch(function (err) { window.alert('Delete failed: ' + err.message); });
    }
  }

  function onSelect(e) {
    var t = e.target;
    if (t.id === 'scSel') {
      if (!t.value) return;
      if (!confirmDiscard()) { buildAll(); return; }
      openScenario(t.value).catch(function (err) { window.alert('Could not open: ' + err.message); buildAll(); });
    } else if (t.id === 'cmpSel') {
      if (!t.value) { st.compare = null; paintCompare(); return; }
      post({ action: 'load', id: t.value }).then(function (j) {
        st.compare = { id: j.scenario.id, name: j.scenario.name, data: normalize(j.scenario.data) };
        paintCompare();
      }).catch(function (err) { window.alert('Could not load: ' + err.message); });
    }
  }

  var tipEl = document.createElement('div');
  tipEl.className = 'tip';
  tipEl.style.display = 'none';
  document.body.appendChild(tipEl);
  function showTip(t) {
    tipEl.textContent = t.getAttribute('data-tip');
    tipEl.style.display = 'block';
    var r = t.getBoundingClientRect(), w = tipEl.offsetWidth, h = tipEl.offsetHeight;
    var left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
    var top = r.top - h - 8;
    if (top < 8) top = r.bottom + 8;
    tipEl.style.left = left + 'px';
    tipEl.style.top = top + 'px';
  }
  function hideTip() { tipEl.style.display = 'none'; }
  function qOf(e) { return e.target.closest ? e.target.closest('.q') : null; }
  root.addEventListener('mouseover', function (e) { var t = qOf(e); if (t) showTip(t); });
  root.addEventListener('mouseout', function (e) { if (qOf(e)) hideTip(); });
  root.addEventListener('focusin', function (e) { var t = qOf(e); if (t) showTip(t); });
  root.addEventListener('focusout', function (e) { if (qOf(e)) hideTip(); });
  root.addEventListener('click', function (e) { var t = qOf(e); if (t) { if (tipEl.style.display === 'block') hideTip(); else showTip(t); } });
  window.addEventListener('scroll', hideTip, true);

  root.addEventListener('input', onEdit);
  root.addEventListener('change', function (e) { onEdit(e); onSelect(e); });
  root.addEventListener('click', onClick);
  root.addEventListener('focusout', function (e) {
    var t = e.target;
    if (t.getAttribute && t.getAttribute('data-unit') === 'rs' && t.getAttribute('data-path') === 'opening') t.value = fmtOpening(st.data.opening);
  });
  window.addEventListener('beforeunload', function (e) {
    if (st.dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- boot ---------- */
  getList().then(function (list) {
    st.list = list;
    if (list.length) return openScenario(list[0].id);
    newBlank('');
  }).catch(showError);
})();
