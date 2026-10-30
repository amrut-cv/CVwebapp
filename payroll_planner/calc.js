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

  function blankTotals() { return { fixed: 0, client: 0, variable1: 0, variable2: 0, variable: 0, full: 0, earned: 0, pay: 0, cv: 0 }; }

  // Pool 1 is paid first (scale r0 to r1). Pool 2 only starts once pool 1 is
  // full (scale r1 to r2). Each person has a variable target in each pool and
  // gets the pool's percentage of each.
  function compute(plan) {
    var s = plan.settings || {};
    var pcts = { 1: payoutPct(s.revenue, s.r0, s.r1), 2: payoutPct(s.revenue, s.r1, s.r2) };
    var total = blankTotals(), groups = {}, pools = { 1: { count: 0, variable: 0, earned: 0 }, 2: { count: 0, variable: 0, earned: 0 } };
    GROUPS.forEach(function (g) { groups[g] = blankTotals(); });

    var rows = (plan.people || []).map(function (p) {
      // Fixed may be negative (it then reduces CV payroll). "From client" is pay a
      // client makes directly: it counts in the person's pay but not in CV payroll.
      var fixed = num(p.fixed), client = Math.max(0, num(p.fromClient));
      var v1 = Math.max(0, num(p.variable1)), v2 = Math.max(0, num(p.variable2));
      var variable = v1 + v2;
      var e1 = v1 * pcts[1], e2 = v2 * pcts[2], earned = e1 + e2;
      var full = fixed + client + variable;
      var row = {
        fixed: fixed, client: client, variable1: v1, variable2: v2, variable: variable, full: full,
        share: full > 0 ? variable / full : 0,
        earned: earned, pay: fixed + client + earned, cv: fixed + earned
      };
      var g = groups[p.group] ? p.group : 'ICs';
      [groups[g], total].forEach(function (t) {
        t.fixed += fixed; t.client += client; t.variable1 += v1; t.variable2 += v2; t.variable += variable; t.full += row.full; t.earned += earned; t.pay += row.pay; t.cv += row.cv;
      });
      if (v1 > 0) { pools[1].count += 1; pools[1].variable += v1; pools[1].earned += e1; }
      if (v2 > 0) { pools[2].count += 1; pools[2].variable += v2; pools[2].earned += e2; }
      return row;
    });

    var r0 = num(s.r0), r1 = num(s.r1), r2 = num(s.r2);
    var overhead = Math.max(0, num(s.overhead));
    var keep = Math.max(0, Math.min(99, num(s.keepPct))) / 100;
    var r0s = ceil10k(total.fixed + overhead);
    var r1s = ceil10k(r0s + pools[1].variable / (1 - keep));
    var r2s = ceil10k(r1s + pools[2].variable / (1 - keep));

    return {
      payout1: pcts[1], payout2: pcts[2],
      rows: rows, groups: groups, pools: pools, total: total,
      profit: num(s.revenue) - total.cv - overhead,
      suggested: { r0: r0s, r1: r1s, r2: r2s },
      broken1: pools[1].variable > 0 && r1 <= r0,
      broken2: pools[2].variable > 0 && r2 <= r1,
      underfunded1: pools[1].variable > 0 && r1 > r0 && (r1 - r0) < pools[1].variable,
      underfunded2: pools[2].variable > 0 && r2 > r1 && (r2 - r1) < pools[2].variable
    };
  }

  var api = { compute: compute, payoutPct: payoutPct, GROUPS: GROUPS };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.PayrollCalc = api;
})(this);
