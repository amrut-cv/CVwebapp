/*
 * graph.js — standalone network graph engine. Depends only on:
 *   - a `NETWORK_DATA` global (see data.js for shape)
 *   - a container element with id="network-graph-root" on the page
 * No PHP/session/app dependency, so this whole folder can be lifted into
 * another project by copying index.php's container div + these two <script>
 * includes (or dropping the div into any other page).
 */
(function () {
  'use strict';

  var container = document.getElementById('network-graph-root');
  if (!container || typeof NETWORK_DATA === 'undefined') return;

  var svgNS = 'http://www.w3.org/2000/svg';

  var W = 900, H = 520, CX = W / 2, CY = H / 2;
  var RING_RADIUS = 190;
  var BG_RX = 320, BG_RY = 220;
  var NODE_R = 13;
  var SCALE_CENTER = 1.55, SCALE_NEIGHBOR = 1.1, SCALE_BG = 0.6;

  var nodes = NETWORK_DATA.nodes || [];
  var categories = NETWORK_DATA.categories || {};

  var nodesById = {};
  nodes.forEach(function (n) { nodesById[n.id] = n; });

  nodes.forEach(function (n, i) {
    var angle = (i / nodes.length) * Math.PI * 2 + (Math.random() * 0.2 - 0.1);
    n.bgX = CX + Math.cos(angle) * BG_RX;
    n.bgY = CY + Math.sin(angle) * BG_RY;
  });

  var edgeSeen = {};
  var edges = [];
  (NETWORK_DATA.edges || []).forEach(function (pair) {
    var a = pair[0], b = pair[1], label = pair[2] || '';
    if (!nodesById[a] || !nodesById[b] || a === b) return;
    var key = [a, b].sort().join('~');
    if (edgeSeen[key]) return;
    edgeSeen[key] = true;
    edges.push([a, b, label]);
  });

  var adj = {};
  nodes.forEach(function (n) { adj[n.id] = []; });
  edges.forEach(function (e) {
    adj[e[0]].push(e[1]);
    adj[e[1]].push(e[0]);
  });

  /* ---- build DOM ---- */

  var controls = document.createElement('div');
  controls.className = 'ng-controls';
  controls.innerHTML =
    '<div class="ng-controls-left">' +
      '<button type="button" class="ng-btn ng-btn-ego">Ego view</button>' +
      '<button type="button" class="ng-btn ng-btn-full">Full network</button>' +
    '</div>' +
    '<div class="ng-status">Centered on <span class="ng-center-label"></span></div>';
  container.appendChild(controls);

  var legend = document.createElement('div');
  legend.className = 'ng-legend';
  Object.keys(categories).forEach(function (key) {
    var cat = categories[key];
    var item = document.createElement('span');
    item.className = 'ng-legend-item';
    item.innerHTML = '<span class="ng-dot" style="background:' + cat.color + '"></span>' + cat.label;
    legend.appendChild(item);
  });
  var hint = document.createElement('span');
  hint.className = 'ng-hint';
  hint.textContent = 'Click any node to make it the center';
  legend.appendChild(hint);
  container.appendChild(legend);

  var svg = document.createElementNS(svgNS, 'svg');
  svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
  svg.setAttribute('class', 'ng-svg');
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', 'Network graph, click a node to recenter');
  var edgesLayer = document.createElementNS(svgNS, 'g');
  var nodesLayer = document.createElementNS(svgNS, 'g');
  svg.appendChild(edgesLayer);
  svg.appendChild(nodesLayer);
  container.appendChild(svg);

  var edgeEls = edges.map(function (edge) {
    var line = document.createElementNS(svgNS, 'line');
    line.style.transition = 'opacity .4s ease, stroke .2s ease';
    edgesLayer.appendChild(line);

    var label = null;
    if (edge[2]) {
      label = document.createElementNS(svgNS, 'text');
      label.setAttribute('class', 'ng-edge-label');
      label.setAttribute('text-anchor', 'middle');
      label.style.transition = 'opacity .4s ease';
      label.textContent = edge[2];
      edgesLayer.appendChild(label);
    }
    return { line: line, label: label };
  });

  var nodeEls = {};
  var wiggleEls = {};
  nodes.forEach(function (n) {
    var g = document.createElementNS(svgNS, 'g');
    g.setAttribute('class', 'ng-node' + (n.important ? ' is-important' : ''));
    g.style.transition = 'transform .5s cubic-bezier(.2,.7,.3,1), opacity .4s ease';

    var wiggle = document.createElementNS(svgNS, 'g');
    wiggle.setAttribute('class', 'ng-wiggle');
    wiggle.style.animationDuration = (3 + Math.random() * 3).toFixed(2) + 's';
    wiggle.style.animationDelay = (-Math.random() * 6).toFixed(2) + 's';

    var circle = document.createElementNS(svgNS, 'circle');
    circle.setAttribute('cx', 0);
    circle.setAttribute('cy', 0);
    circle.setAttribute('r', NODE_R);
    var cat = categories[n.type];
    circle.setAttribute('fill', n.color || (cat && cat.color) || '#9ca3af');

    var text = document.createElementNS(svgNS, 'text');
    text.setAttribute('x', 0);
    text.setAttribute('y', NODE_R + 14);
    text.textContent = n.label;

    wiggle.appendChild(circle);
    wiggle.appendChild(text);
    g.appendChild(wiggle);
    g.addEventListener('click', function () {
      centerId = n.id;
      render();
    });
    nodesLayer.appendChild(g);
    nodeEls[n.id] = g;
    wiggleEls[n.id] = wiggle;
  });

  var mode = 'ego';
  var centerId = (NETWORK_DATA.initialCenter && nodesById[NETWORK_DATA.initialCenter])
    ? NETWORK_DATA.initialCenter
    : (nodes[0] && nodes[0].id);

  function lerp(a, b, t) { return a + (b - a) * t; }

  function render() {
    if (!centerId) return;
    var neighbors = adj[centerId] || [];
    var neighborSet = {};
    neighbors.forEach(function (id) { neighborSet[id] = true; });
    var positions = {};

    if (mode === 'ego') {
      nodes.forEach(function (n) { positions[n.id] = { x: n.bgX, y: n.bgY }; });
      positions[centerId] = { x: CX, y: CY };
      neighbors.forEach(function (nid, idx) {
        var angle = (idx / neighbors.length) * Math.PI * 2 - Math.PI / 2;
        positions[nid] = { x: CX + Math.cos(angle) * RING_RADIUS, y: CY + Math.sin(angle) * RING_RADIUS };
      });
    } else {
      nodes.forEach(function (n) {
        if (n.id === centerId) positions[n.id] = { x: CX, y: CY };
        else if (neighborSet[n.id]) positions[n.id] = { x: lerp(n.bgX, CX, 0.4), y: lerp(n.bgY, CY, 0.4) };
        else positions[n.id] = { x: n.bgX, y: n.bgY };
      });
    }

    nodes.forEach(function (n) {
      var g = nodeEls[n.id];
      var isCenter = n.id === centerId;
      var isNeighbor = !!neighborSet[n.id];
      var pos = positions[n.id];
      var scale = isCenter ? SCALE_CENTER : isNeighbor ? SCALE_NEIGHBOR : SCALE_BG;
      var opacity = mode === 'ego' ? (isCenter || isNeighbor ? 1 : 0) : (isCenter || isNeighbor ? 1 : 0.4);
      g.style.transform = 'translate(' + pos.x + 'px,' + pos.y + 'px) scale(' + scale + ')';
      g.style.opacity = opacity;
      g.classList.toggle('is-center', isCenter);
      g.querySelector('text').style.opacity = (isCenter || isNeighbor) ? 1 : 0;
      wiggleEls[n.id].classList.toggle('is-still', isCenter);
    });

    edges.forEach(function (edge, idx) {
      var line = edgeEls[idx].line;
      var label = edgeEls[idx].label;
      var touches = edge[0] === centerId || edge[1] === centerId;
      var a = positions[edge[0]];
      var b = positions[edge[1]];
      line.setAttribute('x1', a.x);
      line.setAttribute('y1', a.y);
      line.setAttribute('x2', b.x);
      line.setAttribute('y2', b.y);
      if (mode === 'ego') {
        line.style.opacity = touches ? 0.85 : 0;
        line.setAttribute('stroke', touches ? '#5f5e5a' : '#b4b2a9');
      } else {
        line.style.opacity = touches ? 0.9 : 0.45;
        line.setAttribute('stroke', touches ? '#5f5e5a' : '#8f8d86');
      }
      line.setAttribute('stroke-width', touches ? 2 : 1);

      if (label) {
        var midX = (a.x + b.x) / 2;
        var midY = (a.y + b.y) / 2;
        var angle = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
        if (angle > 90 || angle < -90) angle += 180;
        label.setAttribute('x', midX);
        label.setAttribute('y', midY - 5);
        label.setAttribute('transform', 'rotate(' + angle + ' ' + midX + ' ' + midY + ')');
        label.style.opacity = touches ? 1 : 0;
      }
    });

    controls.querySelector('.ng-center-label').textContent = nodesById[centerId].label;
  }

  var egoBtn = controls.querySelector('.ng-btn-ego');
  var fullBtn = controls.querySelector('.ng-btn-full');
  egoBtn.addEventListener('click', function () {
    mode = 'ego';
    egoBtn.classList.add('active');
    fullBtn.classList.remove('active');
    render();
  });
  fullBtn.addEventListener('click', function () {
    mode = 'full';
    fullBtn.classList.add('active');
    egoBtn.classList.remove('active');
    render();
  });
  egoBtn.classList.add('active');

  render();
})();
