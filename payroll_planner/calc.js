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

  function blankTotals() { return { fixed: 0, client: 0, variable1: 0, variable2: 0, variable3: 0, variable: 0, full: 0, earned: 0, pay: 0, cv: 0 }; }

  // Pool 1 is paid first (scale r0 to r1). Pool 2 only starts once pool 1 is
  // full (scale r1 to r2), and pool 3 once pool 2 is full (scale r2 to r3).
  // Each person has a variable target in each pool and gets that pool's
  // percentage of it.
  function compute(plan) {
    var s = plan.settings || {};
    var rs = [num(s.r0), num(s.r1), num(s.r2), num(s.r3)];
    var pcts = { 1: payoutPct(s.revenue, rs[0], rs[1]), 2: payoutPct(s.revenue, rs[1], rs[2]), 3: payoutPct(s.revenue, rs[2], rs[3]) };
    var total = blankTotals(), groups = {};
    var pools = { 1: { count: 0, variable: 0, earned: 0 }, 2: { count: 0, variable: 0, earned: 0 }, 3: { count: 0, variable: 0, earned: 0 } };
    GROUPS.forEach(function (g) { groups[g] = blankTotals(); });

    var rows = (plan.people || []).map(function (p) {
      // Fixed may be negative (it then reduces CV payroll). "From client" is pay a
      // client makes directly: it counts in the person's pay but not in CV payroll.
      var fixed = num(p.fixed), client = Math.max(0, num(p.fromClient));
      var v = { 1: Math.max(0, num(p.variable1)), 2: Math.max(0, num(p.variable2)), 3: Math.max(0, num(p.variable3)) };
      var variable = v[1] + v[2] + v[3];
      var earned = v[1] * pcts[1] + v[2] * pcts[2] + v[3] * pcts[3];
      var full = fixed + client + variable;
      var row = {
        fixed: fixed, client: client, variable1: v[1], variable2: v[2], variable3: v[3], variable: variable, full: full,
        share: full > 0 ? variable / full : 0,
        earned: earned, pay: fixed + client + earned, cv: fixed + earned
      };
      var g = groups[p.group] ? p.group : 'ICs';
      [groups[g], total].forEach(function (t) {
        t.fixed += fixed; t.client += client; t.variable1 += v[1]; t.variable2 += v[2]; t.variable3 += v[3]; t.variable += variable;
        t.full += row.full; t.earned += earned; t.pay += row.pay; t.cv += row.cv;
      });
      [1, 2, 3].forEach(function (k) {
        if (v[k] > 0) { pools[k].count += 1; pools[k].variable += v[k]; pools[k].earned += v[k] * pcts[k]; }
      });
      return row;
    });

    var overhead = Math.max(0, num(s.overhead));
    var keep = Math.max(0, Math.min(99, num(s.keepPct))) / 100;
    var r0s = ceil10k(total.fixed + overhead);
    var r1s = ceil10k(r0s + pools[1].variable / (1 - keep));
    var r2s = ceil10k(r1s + pools[2].variable / (1 - keep));
    var r3s = ceil10k(r2s + pools[3].variable / (1 - keep));

    var out = {
      payout1: pcts[1], payout2: pcts[2], payout3: pcts[3],
      rows: rows, groups: groups, pools: pools, total: total,
      profit: num(s.revenue) - total.cv - overhead,
      suggested: { r0: r0s, r1: r1s, r2: r2s, r3: r3s }
    };
    [1, 2, 3].forEach(function (k) {
      var lo = rs[k - 1], hi = rs[k];
      out['broken' + k] = pools[k].variable > 0 && hi <= lo;
      out['underfunded' + k] = pools[k].variable > 0 && hi > lo && (hi - lo) < pools[k].variable;
    });
    return out;
  }

  var api = { compute: compute, payoutPct: payoutPct, GROUPS: GROUPS };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.PayrollCalc = api;
})(this);
