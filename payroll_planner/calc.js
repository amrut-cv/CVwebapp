(function (root) {
  'use strict';

  var GROUPS = ['Leaders', 'Managers', 'ICs'];

  function num(v) { v = Number(v); return isFinite(v) ? v : 0; }
  function ceil10k(v) { return Math.ceil(v / 10000 - 1e-9) * 10000; }

  // 0% at or below r0, 100% at or above r1, a straight line between.
  // If r1 is not above r0 the scale collapses to a step at r1.
  function payoutPct(revenue, r0, r1) {
    revenue = num(revenue); r0 = num(r0); r1 = num(r1);
    if (r1 <= r0) return r1 > 0 && revenue >= r1 ? 1 : 0;
    return Math.max(0, Math.min(1, (revenue - r0) / (r1 - r0)));
  }

  function blankTotals() { return { fixed: 0, variable: 0, full: 0, earned: 0, pay: 0 }; }

  function compute(plan) {
    var s = plan.settings || {};
    var pct = payoutPct(s.revenue, s.r0, s.r1);
    var total = blankTotals(), groups = {};
    GROUPS.forEach(function (g) { groups[g] = blankTotals(); });

    var rows = (plan.people || []).map(function (p) {
      var fixed = Math.max(0, num(p.fixed)), variable = Math.max(0, num(p.variable));
      var earned = variable * pct;
      var row = {
        fixed: fixed, variable: variable, full: fixed + variable,
        share: fixed + variable > 0 ? variable / (fixed + variable) : 0,
        earned: earned, pay: fixed + earned
      };
      var g = groups[p.group] ? p.group : 'ICs';
      [groups[g], total].forEach(function (t) {
        t.fixed += fixed; t.variable += variable; t.full += row.full; t.earned += earned; t.pay += row.pay;
      });
      return row;
    });

    var overhead = Math.max(0, num(s.overhead));
    var keep = Math.max(0, Math.min(99, num(s.keepPct))) / 100;
    var r0s = ceil10k(total.fixed + overhead);
    var r1s = ceil10k(r0s + total.variable / (1 - keep));

    return {
      payout: pct,
      rows: rows,
      groups: groups,
      total: total,
      profit: num(s.revenue) - total.pay - overhead,
      suggested: { r0: r0s, r1: r1s },
      scaleBroken: num(s.r1) <= num(s.r0),
      underfunded: num(s.r1) > num(s.r0) && (num(s.r1) - num(s.r0)) < total.variable
    };
  }

  var api = { compute: compute, payoutPct: payoutPct, GROUPS: GROUPS };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.PayrollCalc = api;
})(this);
