/*
 * data.js — the network's node/edge dataset. This is the only file you should
 * need to hand-edit day to day. graph.js reads this and knows nothing about
 * where the data came from, so you can swap this out for a generated/DB-backed
 * file later without touching the rendering code.
 *
 * Shape:
 *   categories   — one entry per node "type", used for legend + default color.
 *                  Add/remove/rename freely; graph.js reads whatever is here.
 *   nodes        — { id, label, type, color?, important? }
 *                    - id: unique string, referenced by edges
 *                    - label: text shown under the node
 *                    - type: a key from `categories` above
 *                    - color: optional per-node override (skips category color)
 *                    - important: optional boolean; true gives the node a
 *                      thicker border to call it out as more critical
 *   edges        — [idA, idB, label?] pairs, undirected (order doesn't matter,
 *                  duplicates and self-edges are ignored automatically). The
 *                  optional 3rd item is a short label shown on the edge, but
 *                  only while it's connected to whichever node is centered
 *                  (kept hidden otherwise to avoid clutter).
 *   initialCenter — optional node id to start centered on (defaults to nodes[0])
 *
 * This file replaces the placeholder mock — swap in your real ~50 workflows /
 * modules / people and their actual dependencies.
 */
var NETWORK_DATA = {
  categories: {
    module:   { label: 'Module',   color: '#1D9E75' },
    workflow: { label: 'Workflow', color: '#D85A30' },
    person:   { label: 'Person',   color: '#D4537E' }
  },
  nodes: [
    { id: 'auth-service',      label: 'Auth service',       type: 'module', important: true },
    { id: 'billing-service',   label: 'Billing service',    type: 'module' },
    { id: 'contract-builder',  label: 'Contract builder',   type: 'module', important: true },
    { id: 'onboarding-flow',   label: 'Onboarding flow',    type: 'workflow' },
    { id: 'renewal-flow',      label: 'Renewal flow',       type: 'workflow' },
    { id: 'support-triage',    label: 'Support triage',     type: 'workflow' },
    { id: 'amruta',            label: 'Amruta',             type: 'person' },
    { id: 'sales-lead',        label: 'Sales lead',         type: 'person' },
    { id: 'eng-lead',          label: 'Eng lead',           type: 'person' },
    { id: 'ops-lead',          label: 'Ops lead',           type: 'person' }
  ],
  edges: [
    ['auth-service', 'onboarding-flow', 'gates signup'],
    ['auth-service', 'billing-service'],
    ['billing-service', 'renewal-flow', 'charges renewal'],
    ['billing-service', 'contract-builder'],
    ['contract-builder', 'onboarding-flow', 'generates first contract'],
    ['contract-builder', 'sales-lead', 'owns templates'],
    ['onboarding-flow', 'sales-lead'],
    ['onboarding-flow', 'amruta'],
    ['renewal-flow', 'sales-lead'],
    ['renewal-flow', 'ops-lead'],
    ['support-triage', 'ops-lead', 'escalates to'],
    ['support-triage', 'eng-lead', 'escalates to'],
    ['support-triage', 'auth-service'],
    ['eng-lead', 'auth-service', 'maintains'],
    ['eng-lead', 'contract-builder', 'maintains'],
    ['amruta', 'sales-lead'],
    ['amruta', 'eng-lead'],
    ['ops-lead', 'amruta']
  ]
};
