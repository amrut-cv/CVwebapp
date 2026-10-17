(function (root) {
  'use strict';

  function num(v) { v = Number(v); return isFinite(v) ? v : 0; }
  function zeros(n) { var a = []; for (var i = 0; i < n; i++) a.push(0); return a; }
  var MODES = {
    indian: { gst: 18, tds: 10 },
    export: { gst: 0, tds: 0 }
  };

  function compute(d) {
    var n = d.months.length;
    var s = d.settings || {};

    var exGst = zeros(n), gst = zeros(n), tds = zeros(n), collection = zeros(n), received = zeros(n);

    var outstandingStart = 0;
    (d.invoices || []).forEach(function (inv) {
      var m = MODES[inv.mode] || MODES.indian;
      var gp = m.gst / 100;
      var tp = m.tds / 100;
      var k = 1 + gp - tp;
      var next = inv.collect === 'next';
      for (var i = 0; i < n; i++) {
        var a = num((inv.amounts || [])[i]);
        if (!a) continue;
        exGst[i] += a;
        gst[i] += a * gp;
        tds[i] += a * tp;
        collection[i] += a * k;
        var j = next ? i + 1 : i;
        if (j < n) received[j] += a * k;
      }
      // Next-month payers: assume the invoice just before the window matched
      // the first month, so that month still sees last month's cash arrive.
      if (next && num((inv.amounts || [])[0])) {
        var c0 = num(inv.amounts[0]) * k;
        received[0] += c0;
        outstandingStart += c0;
      }
    });

    var outstanding = zeros(n), prev = outstandingStart;
    for (var i = 0; i < n; i++) {
      prev = prev + collection[i] - received[i];
      outstanding[i] = prev;
    }

    var credit = Math.max(0, Math.min(1, num(s.gstInputCreditPct) / 100));
    var gstAuto = zeros(n), gstPaid = zeros(n);
    var ov = d.gstOverride || [];
    for (i = 0; i < n; i++) {
      var base = i === 0 ? num(s.priorMonthGst) : gst[i - 1];
      gstAuto[i] = base * (1 - credit);
      gstPaid[i] = (ov[i] === null || ov[i] === undefined || ov[i] === '') ? gstAuto[i] : num(ov[i]);
    }

    var crisis = s.crisis || {};
    var fromIdx = d.months.indexOf(crisis.fromMonth);
    if (fromIdx < 0) fromIdx = 0;
    var cp = crisis.pct || {};
    var groups = ['Leaders', 'Managers', 'ICs'];
    var byGroup = { Leaders: zeros(n), Managers: zeros(n), ICs: zeros(n) };
    var payroll = zeros(n);
    (d.salaries || []).forEach(function (p) {
      var g = groups.indexOf(p.group) >= 0 ? p.group : 'ICs';
      for (var i = 0; i < n; i++) {
        var a = num((p.amounts || [])[i]);
        if (crisis.on && i >= fromIdx) a = a * (1 - Math.max(0, Math.min(100, num(cp[g]))) / 100);
        byGroup[g][i] += a;
        payroll[i] += a;
      }
    });

    function sumRows(rows) {
      var t = zeros(n);
      (rows || []).forEach(function (r) {
        for (var i = 0; i < n; i++) t[i] += num((r.amounts || [])[i]);
      });
      return t;
    }
    var consultants = sumRows(d.consultants);
    var rent = sumRows(d.rent);
    var other = sumRows(d.expenses);
    var buffer = zeros(n);
    for (i = 0; i < n; i++) buffer[i] = num((d.buffer || [])[i]);

    var total = zeros(n), nonPayroll = zeros(n), net = zeros(n), opening = zeros(n), closing = zeros(n), total_gst = zeros(n), costs = zeros(n), profit = zeros(n);
    var o = num(d.opening);
    for (i = 0; i < n; i++) {
      nonPayroll[i] = consultants[i] + rent[i] + other[i] + gstPaid[i] + buffer[i];
      total[i] = payroll[i] + nonPayroll[i];
      net[i] = received[i] - total[i];
      opening[i] = o;
      closing[i] = o + net[i];
      o = closing[i];
      total_gst[i] = exGst[i] + gst[i];
      costs[i] = payroll[i] + consultants[i] + rent[i] + other[i] + buffer[i];
      profit[i] = exGst[i] - costs[i];
    }

    var coverage = closing.map(function (c, i) {
      return c > 0 && payroll[i] > 0 ? c / payroll[i] : null;
    });

    var firstNeg = -1, lowest = 0, lowestIdx = 0;
    for (i = 0; i < n; i++) {
      if (firstNeg < 0 && closing[i] < 0) firstNeg = i;
      if (i === 0 || closing[i] < lowest) { lowest = closing[i]; lowestIdx = i; }
    }
    var avgNet = net.reduce(function (a, b) { return a + b; }, 0) / n;

    return {
      months: d.months,
      inv: { exGst: exGst, gst: gst, total: total_gst, tds: tds, collection: collection, received: received, outstanding: outstanding },
      out: { payroll: payroll, consultants: consultants, rent: rent, other: other, gst: gstPaid, buffer: buffer, total: total, nonPayroll: nonPayroll, costs: costs },
      profit: profit,
      payrollByGroup: byGroup,
      gstAuto: gstAuto,
      net: net, opening: opening, closing: closing, coverage: coverage,
      firstNegative: firstNeg, lowest: lowest, lowestIdx: lowestIdx, avgNet: avgNet
    };
  }

  var api = { compute: compute, MODES: MODES };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.PlannerCalc = api;
})(this);
