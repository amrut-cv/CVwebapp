/*
 * data.js — the network's node/edge dataset. This is the only file you should
 * need to hand-edit day to day. graph.js reads this and knows nothing about
 * where the data came from, so you can swap this out for a generated/DB-backed
 * file later without touching the rendering code.
 *
 * Shape:
 *   categories   — one entry per node "type", used for legend + default color.
 *                  Add/remove/rename freely; graph.js reads whatever is here.
 *   nodes        — { id, label, type, color? }
 *                    - id: unique string, referenced by edges
 *                    - label: text shown under the node
 *                    - type: a key from `categories` above
 *                    - color: optional per-node override (skips category color)
 *   edges        — [idA, idB] pairs, undirected (order doesn't matter,
 *                  duplicates and self-edges are ignored automatically)
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
    { id: 'auth-service',      label: 'Auth service',       type: 'module' },
    { id: 'billing-service',   label: 'Billing service',    type: 'module' },
    { id: 'contract-builder',  label: 'Contract builder',   type: 'module' },
    { id: 'onboarding-flow',   label: 'Onboarding flow',    type: 'workflow' },
    { id: 'renewal-flow',      label: 'Renewal flow',       type: 'workflow' },
    { id: 'support-triage',    label: 'Support triage',     type: 'workflow' },
    { id: 'amruta',            label: 'Amruta',             type: 'person' },
    { id: 'sales-lead',        label: 'Sales lead',         type: 'person' },
    { id: 'eng-lead',          label: 'Eng lead',           type: 'person' },
    { id: 'ops-lead',          label: 'Ops lead',           type: 'person' }
  ],
  edges: [
    ['auth-service', 'onboarding-flow'],
    ['auth-service', 'billing-service'],
    ['billing-service', 'renewal-flow'],
    ['billing-service', 'contract-builder'],
    ['contract-builder', 'onboarding-flow'],
    ['contract-builder', 'sales-lead'],
    ['onboarding-flow', 'sales-lead'],
    ['onboarding-flow', 'amruta'],
    ['renewal-flow', 'sales-lead'],
    ['renewal-flow', 'ops-lead'],
    ['support-triage', 'ops-lead'],
    ['support-triage', 'eng-lead'],
    ['support-triage', 'auth-service'],
    ['eng-lead', 'auth-service'],
    ['eng-lead', 'contract-builder'],
    ['amruta', 'sales-lead'],
    ['amruta', 'eng-lead'],
    ['ops-lead', 'amruta']
  ]
};
