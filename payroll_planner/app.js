(function () {
  'use strict';

  var C = window.PayrollCalc;
  var API = window.PAYROLL_API || '/CVwebapp/api/payroll_planner.php';
  var root = document.getElementById('app');
  var GROUPS = C.GROUPS;
  var GLABEL = { Leaders: 'Leaders', Managers: 'Managers', ICs: 'Individual contributors' };

  var st = { meta: { id: null, name: '' }, data: null, list: [], dirty: false, r: null };

  /* ---------- helpers ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function num(v) { v = Number(v); return isFinite(v) ? v : 0; }
  function uid() { return 'p' + Math.random().toString(36).slice(2, 8); }
  function getp(o, p) { return String(p).split('.').reduce(function (a, k) { return a == null ? a : a[k]; }, o); }
  function setp(o, p, v) {
    var ks = String(p).split('.'), last = ks.pop();
    ks.reduce(function (a, k) { return a[k]; }, o)[last] = v;
  }
  function inr(n) {
    n = Math.round(num(n));
    return (n < 0 ? '−' : '') + '₹' + Math.abs(n).toLocaleString('en-IN');
  }
  function lakh(n) { return String(parseFloat((num(n) / 100000).toFixed(2))); }
  function rupeeL(n) { return '₹' + lakh(n) + 'L'; }
  function inputK(r) { return String(Math.round(num(r)) / 1000); }
  function kfmt(n) { return (num(n) / 1000).toLocaleString('en-IN', { maximumFractionDigits: 1 }); }
  function fmt(v, f) {
    if (f === 'k') return kfmt(v);
    if (f === 'rs') return inr(v);
    if (f === 'pct') return Math.round(num(v) * 100) + '%';
    return String(v);
  }

  /* ---------- plan data ---------- */
  function blank() {
    return {
      version: 1,
      settings: { revenue: 1950000, r0: 1810000, r1: 1950000, r2: 2080000, overhead: 600000, keepPct: 30 },
      people: []
    };
  }
  function normalize(d) {
    var b = blank();
    d = d || {};
    d.settings = d.settings || {};
    if (d.settings.r2 == null && d.settings.r1 != null) d.settings.r2 = d.settings.r1;
    Object.keys(b.settings).forEach(function (k) {
      d.settings[k] = d.settings[k] == null ? b.settings[k] : num(d.settings[k]);
    });
    d.people = (d.people || []).map(function (p) {
      // Older plans had one variable target and a pool choice per person.
      var legacy = p.variable1 == null && p.variable2 == null;
      var v1 = legacy ? (p.pool === 2 ? 0 : num(p.variable)) : num(p.variable1);
      var v2 = legacy ? (p.pool === 2 ? num(p.variable) : 0) : num(p.variable2);
      return {
        id: p.id || uid(), name: String(p.name || ''),
        group: GROUPS.indexOf(p.group) >= 0 ? p.group : 'ICs',
        fixed: num(p.fixed), fromClient: Math.max(0, num(p.fromClient)),
        variable1: Math.max(0, v1), variable2: Math.max(0, v2)
      };
    });
    return d;
  }

  /* ---------- api ---------- */
  function apiErr(r, j) {
    if (r.status === 401 || (j && j.error === 'session_expired')) {
      var e = new Error('Your session expired. Sign in again to continue.');
      e.expired = true;
      return e;
    }
    return new Error((j && j.error) || ('HTTP ' + r.status));
  }
  function post(body) {
    return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) {
        return r.json().catch(function () { return null; }).then(function (j) {
          if (!r.ok || (j && j.error)) throw apiErr(r, j);
          return j;
        });
      });
  }
  function getList() {
    return fetch(API, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) {
      return r.json().catch(function () { return null; }).then(function (j) {
        if (!Array.isArray(j)) throw apiErr(r, j);
        return j;
      });
    });
  }
  function showError(e) {
    root.innerHTML = '<div class="card"><h2>Something went wrong</h2><p class="hint">' + esc(e.message || e) + '</p>' +
      (e.expired ? '<a class="btn primary" href="/CVwebapp/login.php">Sign in</a>' : '') + '</div>';
  }

  /* ---------- tooltips ---------- */
  var TIPS = {
    sim: 'Try different revenue figures to see the payout percentage and what each person would earn. Nothing here is saved until you press Save.',
    revenue: 'The month\'s service invoices before GST. Leave out reimbursements and pass-through costs.',
    payout1: 'The share of each Pool 1 variable target that is paid. It is 0% at or below R0 and rises in a straight line to 100% at R1.',
    payout2: 'The share of each Pool 2 variable target that is paid. Pool 2 pays nothing until Pool 1 is full at R1, then rises in a straight line to 100% at R2.',
    varpaid: 'Variable pay across everyone at this revenue.',
    payroll: 'What CV pays: fixed pay (which can be negative) plus the variable pay earned at this revenue. Pay a client makes directly is not included.',
    profit: 'Revenue minus total payroll minus overhead. This is what the company keeps after paying everyone.',
    scale: 'Two pools on one revenue line. Pool 1 is paid first, from R0 to R1. Pool 2 starts only once Pool 1 is full, from R1 to R2. Each person\'s variable target in a pool is paid at that pool\'s percentage.',
    r0: 'The revenue where variable pay starts. Below it nobody earns any. A sound starting point is fixed payroll plus overhead.',
    r1: 'The revenue where Pool 1 reaches 100% of its variable targets. Pool 2 starts paying from here. Keep the gap from R0 at least as large as the Pool 1 targets.',
    r2: 'The revenue where Pool 2 reaches 100% of its variable targets. Keep the gap from R1 at least as large as the Pool 2 targets.',
    overhead: 'Monthly costs other than payroll, such as rent, software and consultants, before GST. Used for the profit figure and the suggested R0.',
    keep: 'The share of the revenue above R0 that the company keeps while pay is being restored. At 30%, 70% of that surplus can go to variable pay. Used for the suggested R1.',
    people: 'One row per person. Fixed pay is guaranteed. Each pool\'s variable target is paid in full only when that pool reaches 100%.',
    fixed: 'Guaranteed monthly pay from CV in ₹ thousands. Can be negative, for example when a client pays more than the person\'s full income and the excess is offset here.',
    client: 'Income a client pays the person directly, in ₹ thousands a month. It counts toward their pay but is not CV payroll.',
    variable1: 'Monthly variable pay in ₹ thousands from Pool 1, which is paid first. Earned in full only when Pool 1 reaches 100% at R1.',
    variable2: 'Monthly variable pay in ₹ thousands from Pool 2, which only starts once Pool 1 is full. Earned in full only when Pool 2 reaches 100% at R2.',
    share: 'Variable target as a share of full pay (fixed plus from client plus both variable targets).',
    earned: 'The variable pay this person earns at the revenue above: each pool\'s target times that pool\'s payout percentage.'
  };
  function tip(text) {
    return '<span class="q" tabindex="0" role="note" aria-label="' + esc(text) + '" data-tip="' + esc(text) + '">?</span>';
  }

  /* ---------- html builders ---------- */
  function topBar() {
    var opts = '';
    if (!st.meta.id) opts += '<option value="">' + esc(st.meta.name || '(unsaved plan)') + '</option>';
    st.list.forEach(function (s) {
      opts += '<option value="' + s.id + '"' + (String(s.id) === String(st.meta.id) ? ' selected' : '') + '>' + esc(s.name) + '</option>';
    });
    return '<div class="bar"><h1>Payroll <span>planner</span></h1>' +
      '<select id="planSel" aria-label="Plan">' + opts + '</select>' +
      '<button class="btn primary" data-act="save">Save</button>' +
      '<button class="btn" data-act="saveas">Save as</button>' +
      '<button class="btn" data-act="rename">Rename</button>' +
      '<button class="btn" data-act="new">New blank</button>' +
      '<button class="btn danger" data-act="delete">Delete</button>' +
      '<span class="dirty" id="dirty" style="display:none">Unsaved changes</span></div>';
  }

  function field(id, label, path, unit, value, tipText) {
    return '<div class="fld"><label for="' + id + '">' + label + tip(tipText) + '</label>' +
      '<input type="text" id="' + id + '" inputmode="decimal" data-path="' + path + '" data-unit="' + unit + '" value="' + esc(value) + '"></div>';
  }

  function simPanel() {
    var s = st.data.settings;
    return '<div class="card"><h2>Simulate a month' + tip(TIPS.sim) + '</h2>' +
      '<div class="hint">Change the revenue to see the payout percentage and what each person earns.</div>' +
      '<div class="simrow"><label for="rev">If revenue is</label>&#8377; ' +
      '<input type="text" id="rev" inputmode="decimal" data-path="settings.revenue" data-unit="lakh" value="' + esc(lakh(s.revenue)) + '">' +
      '<span class="unit">lakh</span>' + tip(TIPS.revenue) +
      '<input type="range" id="revr" data-path="settings.revenue" data-unit="rs" aria-label="Revenue"></div>' +
      '<div class="cards" id="cards"></div><p class="status" id="status"></p></div>';
  }

  function scalePanel() {
    var s = st.data.settings;
    return '<div class="card"><h2>Payout scale' + tip(TIPS.scale) + '</h2>' +
      '<div class="hint">Pool 1 is paid first: 0% at or below R0, rising to 100% at R1. Pool 2 starts only once Pool 1 is full: 0% at R1, rising to 100% at R2. Each person\'s variable target in a pool is paid at that pool\'s percentage.</div>' +
      '<div class="grid4">' +
      field('r0', 'R0, variable pay starts (₹ lakh)', 'settings.r0', 'lakh', lakh(s.r0), TIPS.r0) +
      field('r1', 'R1, Pool 1 full (₹ lakh)', 'settings.r1', 'lakh', lakh(s.r1), TIPS.r1) +
      field('r2', 'R2, Pool 2 full (₹ lakh)', 'settings.r2', 'lakh', lakh(s.r2), TIPS.r2) +
      field('ovh', 'Overhead (₹ lakh a month)', 'settings.overhead', 'lakh', lakh(s.overhead), TIPS.overhead) +
      field('keep', 'Company keeps (% of surplus)', 'settings.keepPct', 'n', String(s.keepPct), TIPS.keep) +
      '</div>' +
      '<div class="suggest"><span id="suggestTxt"></span><button class="btn" data-act="usesuggest">Use suggested R0, R1 and R2</button></div>' +
      '<div id="scaleWarn"></div><div class="chart" id="chart"></div></div>';
  }

  function peoplePanel() {
    var d = st.data;
    var h = '<div class="card"><h2>People' + tip(TIPS.people) + '</h2>' +
      '<div class="hint">Fixed, from client and the two variable targets are in &#8377; thousands a month. Fixed can be negative; from client is pay a client makes directly, so it counts toward the person&#39;s pay but not CV payroll. Pool 1 is paid first and Pool 2 only once Pool 1 is full. The last two columns show what each person earns at the revenue above.</div>' +
      '<div class="wrap"><table class="g"><tr><th>Person</th><th class="lv">Level</th>' +
      '<th>Fixed (&#8377;K)' + tip(TIPS.fixed) + '</th><th>From client (&#8377;K)' + tip(TIPS.client) + '</th><th>Variable, Pool 1 (&#8377;K)' + tip(TIPS.variable1) + '</th><th>Variable, Pool 2 (&#8377;K)' + tip(TIPS.variable2) + '</th>' +
      '<th>Full pay (&#8377;K)</th><th>Variable share' + tip(TIPS.share) + '</th>' +
      '<th>Variable earned' + tip(TIPS.earned) + '</th><th>Pay this month</th></tr>';
    GROUPS.forEach(function (g) {
      h += '<tr class="grp"><td>' + GLABEL[g] + '</td><td class="lv"></td>' +
        '<td data-v="groups.' + g + '.fixed" data-f="k"></td><td data-v="groups.' + g + '.client" data-f="k"></td><td data-v="groups.' + g + '.variable1" data-f="k"></td><td data-v="groups.' + g + '.variable2" data-f="k"></td>' +
        '<td data-v="groups.' + g + '.full" data-f="k"></td><td></td>' +
        '<td data-v="groups.' + g + '.earned" data-f="rs"></td><td data-v="groups.' + g + '.pay" data-f="rs"></td></tr>';
      d.people.forEach(function (p, i) {
        if (p.group !== g) return;
        var path = 'people.' + i;
        h += '<tr><td><div class="lblrow"><input class="txt" data-path="' + path + '.name" data-unit="str" value="' + esc(p.name) + '" aria-label="Name">' +
          '<button class="xbtn" data-del="' + i + '" title="Remove" aria-label="Remove person">&times;</button></div></td>' +
          '<td class="lv"><select class="kind" data-path="' + path + '.group" data-unit="str" data-rebuild="1" aria-label="Level">' +
          GROUPS.map(function (o) { return '<option value="' + o + '"' + (o === p.group ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></td>' +
          '<td><input class="cell" inputmode="decimal" data-path="' + path + '.fixed" data-unit="ks" value="' + esc(inputK(p.fixed)) + '" aria-label="Fixed"></td>' +
          '<td><input class="cell" inputmode="decimal" data-path="' + path + '.fromClient" data-unit="k" value="' + esc(inputK(p.fromClient)) + '" aria-label="From client"></td>' +
          '<td><input class="cell" inputmode="decimal" data-path="' + path + '.variable1" data-unit="k" value="' + esc(inputK(p.variable1)) + '" aria-label="Variable, Pool 1"></td>' +
          '<td><input class="cell" inputmode="decimal" data-path="' + path + '.variable2" data-unit="k" value="' + esc(inputK(p.variable2)) + '" aria-label="Variable, Pool 2"></td>' +
          '<td data-v="rows.' + i + '.full" data-f="k"></td><td data-v="rows.' + i + '.share" data-f="pct"></td>' +
          '<td data-v="rows.' + i + '.earned" data-f="rs"></td><td data-v="rows.' + i + '.pay" data-f="rs"></td></tr>';
      });
    });
    h += '<tr class="addrow"><td colspan="10"><button data-add="1">+ Add person</button></td></tr>' +
      '<tr class="tot"><td>Total</td><td class="lv"></td>' +
      '<td data-v="total.fixed" data-f="k"></td><td data-v="total.client" data-f="k"></td><td data-v="total.variable1" data-f="k"></td><td data-v="total.variable2" data-f="k"></td><td data-v="total.full" data-f="k"></td><td></td>' +
      '<td data-v="total.earned" data-f="rs"></td><td data-v="total.pay" data-f="rs"></td></tr></table></div>';
    if (!d.people.length) h += '<div class="empty">No people yet. Add the first person to start planning.</div>';
    return h + '</div>';
  }

  function buildAll() {
    root.innerHTML = topBar() + simPanel() + scalePanel() + peoplePanel();
    paint();
    setDirty(st.dirty);
  }

  /* ---------- painting ---------- */
  function card(cls, label, value, sub, tipText) {
    return '<div class="mc ' + cls + '"><div class="l">' + label + tip(tipText) + '</div><div class="v">' + value + '</div><div class="s">' + sub + '</div></div>';
  }

  function paint() {
    var r = C.compute(st.data), s = st.data.settings;
    st.r = r;
    root.querySelectorAll('[data-v]').forEach(function (el) {
      el.textContent = fmt(getp(r, el.getAttribute('data-v')), el.getAttribute('data-f'));
    });

    var rev = num(s.revenue);
    var rin = root.querySelector('#rev');
    if (rin && document.activeElement !== rin) rin.value = lakh(rev);
    var sl = root.querySelector('#revr');
    if (sl) {
      var top = Math.max(num(s.r1), num(s.r2));
      var span = Math.max(top - num(s.r0), 200000);
      var lo = Math.max(0, Math.min(Math.floor((num(s.r0) - span) / 100000) * 100000, Math.floor(rev / 100000) * 100000));
      var hi = Math.max(Math.ceil((top + span) / 100000) * 100000, Math.ceil(rev / 100000) * 100000);
      sl.min = lo; sl.max = hi; sl.step = 10000; sl.value = rev;
    }

    var cards = root.querySelector('#cards');
    if (cards) {
      var n1 = r.pools[1].count, n2 = r.pools[2].count;
      cards.innerHTML =
        card('', 'Pool 1 payout', Math.round(r.payout1 * 100) + '%', n1 + (n1 === 1 ? ' person' : ' people') + ', paid first', TIPS.payout1) +
        card('', 'Pool 2 payout', Math.round(r.payout2 * 100) + '%', n2 + (n2 === 1 ? ' person' : ' people') + ', paid after Pool 1', TIPS.payout2) +
        card('', 'Variable paid', inr(r.total.earned), 'of ' + inr(r.total.variable) + ' at 100%', TIPS.varpaid) +
        card('', 'Total payroll', inr(r.total.cv), 'fixed ' + inr(r.total.fixed) + (r.total.client > 0 ? ', plus ' + inr(r.total.client) + ' paid by clients' : ''), TIPS.payroll) +
        card(r.profit < 0 ? 'bad' : 'good', 'Profit after payroll', inr(r.profit), 'revenue − payroll − ' + rupeeL(s.overhead) + ' overhead', TIPS.profit);
    }

    var status = root.querySelector('#status');
    if (status) {
      var parts = [];
      if (rev <= num(s.r0)) parts.push('Revenue is at or below R0 (' + rupeeL(s.r0) + '), so no variable pay is earned.');
      else {
        if (r.pools[1].count) parts.push(r.payout1 >= 1 ? 'Pool 1 is paid in full.' : 'Pool 1 is ' + Math.round(r.payout1 * 100) + '% of the way to full at R1 (' + rupeeL(s.r1) + ').');
        if (r.pools[2].count) parts.push(rev <= num(s.r1) ? 'Pool 2 has not started. It starts once Pool 1 is full at R1.' :
          (r.payout2 >= 1 ? 'Pool 2 is paid in full.' : 'Pool 2 is ' + Math.round(r.payout2 * 100) + '% of the way to full at R2 (' + rupeeL(s.r2) + ').'));
        if (!parts.length) parts.push('Add variable targets to see what the scale pays.');
      }
      status.textContent = parts.join(' ');
    }

    var sug = root.querySelector('#suggestTxt');
    if (sug) {
      var kp = Math.round(num(s.keepPct));
      sug.innerHTML = 'Suggested: R0 <strong>' + rupeeL(r.suggested.r0) + '</strong> = fixed payroll ' + rupeeL(r.total.fixed) + ' + overhead ' + rupeeL(s.overhead) +
        '. R1 <strong>' + rupeeL(r.suggested.r1) + '</strong> = R0 + Pool 1 targets ' + rupeeL(r.pools[1].variable) + ' ÷ (1 − ' + kp + '%).' +
        ' R2 <strong>' + rupeeL(r.suggested.r2) + '</strong> = R1 + Pool 2 targets ' + rupeeL(r.pools[2].variable) + ' ÷ (1 − ' + kp + '%).';
    }

    var warn = root.querySelector('#scaleWarn');
    if (warn) {
      var msgs = [];
      if (r.broken1) msgs.push('R1 needs to be higher than R0 for Pool 1 to work.');
      else if (r.underfunded1) msgs.push('The gap between R0 and R1 (' + rupeeL(num(s.r1) - num(s.r0)) + ') is smaller than the Pool 1 targets (' + rupeeL(r.pools[1].variable) +
        '), so near R1 variable pay can exceed the revenue above R0. Widen the gap or use the suggested values.');
      if (r.broken2) msgs.push('R2 needs to be higher than R1 for Pool 2 to work.');
      else if (r.underfunded2) msgs.push('The gap between R1 and R2 (' + rupeeL(num(s.r2) - num(s.r1)) + ') is smaller than the Pool 2 targets (' + rupeeL(r.pools[2].variable) +
        '), so near R2 variable pay can exceed the revenue above R0. Widen the gap or use the suggested values.');
      if (num(s.r0) < r.total.fixed + num(s.overhead) - 1) msgs.push('R0 is below fixed payroll plus overhead (' + rupeeL(r.total.fixed + num(s.overhead)) +
        '), so variable pay can start while the company is still making a loss.');
      warn.innerHTML = msgs.map(function (m) { return '<div class="warn">' + esc(m) + '</div>'; }).join('');
    }

    paintChart(r);
  }

  function paintChart(r) {
    var el = root.querySelector('#chart');
    if (!el) return;
    var s = st.data.settings, r0 = num(s.r0), r1 = num(s.r1), r2 = num(s.r2), rev = num(s.revenue);
    var has1 = r.pools[1].count > 0, has2 = r.pools[2].count > 0;
    var top = Math.max(r1, has2 ? r2 : r1);
    var span = Math.max(top - r0, 100000);
    var xmin = Math.max(0, Math.min(r0 - span * 0.25, rev - span * 0.1));
    var xmax = Math.max(top + span * 0.25, rev + span * 0.1);
    var L = 46, R = 18, T = 30, B = 52, W = 640, H = 232;
    var pw = W - L - R, ph = H - T - B;
    function X(v) { return L + (v - xmin) / (xmax - xmin) * pw; }
    function Y(p) { return T + (1 - p) * ph; }
    function clampX(v) { return Math.max(xmin, Math.min(v, xmax)); }
    function poly(a, b, color, dash) {
      a = clampX(a); b = clampX(Math.max(b, a));
      var pts = [[xmin, 0], [a, 0], [b, 1], [xmax, 1]];
      return '<polyline fill="none" stroke="' + color + '" stroke-width="2.5" stroke-linejoin="round"' + (dash ? ' stroke-dasharray="7 4"' : '') + ' points="' +
        pts.map(function (p) { return X(p[0]).toFixed(1) + ',' + Y(p[1]).toFixed(1); }).join(' ') + '"/>';
    }
    var out = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Payout percentage by revenue for each pool">';
    [0, 0.5, 1].forEach(function (p) {
      out += '<line x1="' + L + '" y1="' + Y(p) + '" x2="' + (W - R) + '" y2="' + Y(p) + '" stroke="#e5e7ef" stroke-width="1"/>' +
        '<text x="' + (L - 8) + '" y="' + (Y(p) + 4) + '" font-size="11" fill="#6b7280" text-anchor="end">' + Math.round(p * 100) + '%</text>';
    });
    if (has1) out += poly(r0, r1, '#1a1a2e', false);
    if (has2) out += poly(r1, r2, '#0F6E56', true);
    [['R0', r0, 19], ['R1', r1, 32], ['R2', r2, 19]].forEach(function (m) {
      if (m[1] < xmin || m[1] > xmax) return;
      out += '<line x1="' + X(m[1]) + '" y1="' + Y(0) + '" x2="' + X(m[1]) + '" y2="' + (Y(0) + 5) + '" stroke="#6b7280" stroke-width="1"/>' +
        '<text x="' + X(m[1]) + '" y="' + (Y(0) + m[2]) + '" font-size="11" fill="#4b5563" text-anchor="middle">' + m[0] + ' ' + rupeeL(m[1]) + '</text>';
    });
    var mx = X(rev);
    var label = rupeeL(rev) + (has1 ? ': Pool 1 ' + Math.round(r.payout1 * 100) + '%' : '') + (has2 ? (has1 ? ', ' : ': ') + 'Pool 2 ' + Math.round(r.payout2 * 100) + '%' : '');
    var anchor = mx > W - 170 ? 'end' : (mx < L + 170 ? 'start' : 'middle');
    out += '<line x1="' + mx + '" y1="' + Y(1) + '" x2="' + mx + '" y2="' + Y(0) + '" stroke="#C9972A" stroke-width="1.5" stroke-dasharray="4 3"/>';
    if (has1) out += '<circle cx="' + mx + '" cy="' + Y(r.payout1) + '" r="5" fill="#1a1a2e" stroke="#ffffff" stroke-width="1.5"/>';
    if (has2) out += '<circle cx="' + mx + '" cy="' + Y(r.payout2) + '" r="4" fill="#0F6E56" stroke="#ffffff" stroke-width="1.5"/>';
    out += '<text x="' + mx + '" y="' + (T - 12) + '" font-size="12" font-weight="600" fill="#1a1a2e" text-anchor="' + anchor + '">' + label + '</text>';
    var lx = L;
    if (has1) { out += '<line x1="' + lx + '" y1="' + (H - 8) + '" x2="' + (lx + 22) + '" y2="' + (H - 8) + '" stroke="#1a1a2e" stroke-width="2.5"/><text x="' + (lx + 28) + '" y="' + (H - 4) + '" font-size="11" fill="#4b5563">Pool 1, paid first</text>'; lx += 150; }
    if (has2) out += '<line x1="' + lx + '" y1="' + (H - 8) + '" x2="' + (lx + 22) + '" y2="' + (H - 8) + '" stroke="#0F6E56" stroke-width="2.5" stroke-dasharray="7 4"/><text x="' + (lx + 28) + '" y="' + (H - 4) + '" font-size="11" fill="#4b5563">Pool 2, paid after Pool 1</text>';
    el.innerHTML = out + '</svg>';
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
    var u = t.getAttribute('data-unit'), val;
    if (u === 'lakh') val = Math.max(0, Math.round(num(t.value.replace(/[,\s]/g, '')) * 100000));
    else if (u === 'rs') val = Math.max(0, Math.round(num(t.value)));
    else if (u === 'ks') val = Math.round(num(t.value.replace(/[,\s]/g, '')) * 1000);
    else if (u === 'k') val = Math.max(0, Math.round(num(t.value.replace(/[,\s]/g, '')) * 1000));
    else if (u === 'n') val = Math.max(0, num(t.value.replace(/[,\s]/g, '')));
    else val = t.value;
    setp(st.data, p, val);
    setDirty(true);
    if (t.hasAttribute('data-rebuild') && e.type === 'change') { buildAll(); return; }
    paint();
  }

  function save(asNew) {
    var name = st.meta.name;
    if (asNew || !st.meta.id || !name) {
      name = window.prompt('Plan name', name || '');
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

  function openPlan(id) {
    return post({ action: 'load', id: id }).then(function (j) {
      st.meta = { id: j.plan.id, name: j.plan.name };
      st.data = normalize(j.plan.data);
      st.dirty = false;
      buildAll();
    });
  }

  function newBlank(name) {
    st.meta = { id: null, name: name || '' };
    st.data = blank();
    st.dirty = true;
    buildAll();
  }

  function confirmDiscard() { return !st.dirty || window.confirm('You have unsaved changes. Discard them?'); }

  function onClick(e) {
    var t = e.target.closest('[data-del],[data-add],[data-act]');
    if (!t) return;
    if (t.hasAttribute('data-add')) {
      st.data.people.push({ id: uid(), name: 'New person', group: 'ICs', fixed: 0, fromClient: 0, variable1: 0, variable2: 0 });
      setDirty(true); buildAll(); return;
    }
    if (t.hasAttribute('data-del')) {
      st.data.people.splice(+t.getAttribute('data-del'), 1);
      setDirty(true); buildAll(); return;
    }
    var act = t.getAttribute('data-act');
    if (act === 'save') save(false);
    else if (act === 'saveas') save(true);
    else if (act === 'usesuggest') {
      st.data.settings.r0 = st.r.suggested.r0;
      st.data.settings.r1 = st.r.suggested.r1;
      st.data.settings.r2 = st.r.suggested.r2;
      setDirty(true); buildAll();
    } else if (act === 'rename') {
      var nn = window.prompt('Plan name', st.meta.name || '');
      if (nn && nn.trim()) { st.meta.name = nn.trim(); setDirty(true); if (st.meta.id) save(false); else buildAll(); }
    } else if (act === 'new') {
      if (!confirmDiscard()) return;
      newBlank('');
    } else if (act === 'delete') {
      if (!st.meta.id) { window.alert('This plan has not been saved yet.'); return; }
      if (!window.confirm('Delete "' + st.meta.name + '"? This cannot be undone.')) return;
      post({ action: 'delete', id: st.meta.id }).then(getList).then(function (list) {
        st.list = list;
        if (list.length) return openPlan(list[0].id);
        newBlank('');
      }).catch(function (err) { window.alert('Delete failed: ' + err.message); });
    }
  }

  function onSelect(e) {
    var t = e.target;
    if (t.id !== 'planSel' || !t.value) return;
    if (!confirmDiscard()) { buildAll(); return; }
    openPlan(t.value).catch(function (err) { window.alert('Could not open: ' + err.message); buildAll(); });
  }

  /* ---------- tooltip behaviour ---------- */
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
  root.addEventListener('click', function (e) { var t = qOf(e); if (t) { if (tipEl.style.display === 'block') hideTip(); else showTip(t); } });
  window.addEventListener('scroll', hideTip, true);

  root.addEventListener('input', onEdit);
  root.addEventListener('change', function (e) { onEdit(e); onSelect(e); });
  root.addEventListener('click', onClick);
  root.addEventListener('focusout', function (e) {
    var t = e.target;
    if (qOf(e)) hideTip();
    if (t.getAttribute && t.getAttribute('data-unit') === 'lakh') t.value = lakh(getp(st.data, t.getAttribute('data-path')));
  });
  window.addEventListener('beforeunload', function (e) {
    if (st.dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------- boot ---------- */
  getList().then(function (list) {
    st.list = list;
    if (list.length) return openPlan(list[0].id);
    newBlank('');
  }).catch(showError);
})();
