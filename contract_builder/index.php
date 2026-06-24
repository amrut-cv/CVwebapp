<?php
require __DIR__ . '/../session_guard.php';
require __DIR__ . '/../db.php';

$pdo = getDB();
$rows = $pdo->query("SELECT id, list_key, label, sort_order FROM list_items ORDER BY list_key, sort_order, id")->fetchAll();
$caseStudies = $pdo->query("SELECT id, name, description FROM case_studies ORDER BY sort_order, id")->fetchAll();
$lists = [];
foreach ($rows as $r) {
    $lists[$r['list_key']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CoreVoice — Contract Builder</title>
  <link href="/CVwebapp/assets/quill.snow.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:       #1a1a2e;
      --accent:      #e94560;
      --accent-light:#fde8ec;
      --bg:          #f7f8fc;
      --white:       #ffffff;
      --border:      #e2e5ef;
      --text:        #1c1c2e;
      --muted:       #6b7280;
      --step-done:   #10b981;
      --radius:      10px;
      --shadow:      0 2px 16px rgba(0,0,0,.08);
    }

    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* Header */
    header {
      background: var(--brand);
      color: white;
      padding: 18px 32px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    header .logo       { font-size: 1.3rem; font-weight: 700; letter-spacing: .5px; }
    header .logo span  { color: var(--accent); }
    header .sep        { opacity: .3; }
    header .sub        { font-size: .82rem; opacity: .55; }
    header .header-actions { margin-left: auto; display: flex; gap: 8px; }
    .btn-header {
      background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.2);
      border-radius: 6px; padding: 6px 14px; font-size: .78rem; font-weight: 600;
      cursor: pointer; font-family: inherit; transition: background .15s;
      text-decoration: none; display: inline-flex; align-items: center;
    }
    .btn-header:hover { background: rgba(255,255,255,.22); }
    .btn-header.accent { background: var(--accent); border-color: var(--accent); }
    .btn-header.accent:hover { opacity: .88; }

    /* Drafts panel */
    .drafts-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.35);
      z-index: 1000; display: flex; justify-content: flex-end;
    }
    .drafts-overlay.hidden { display: none; }
    .drafts-drawer {
      width: 380px; max-width: 100%; background: #fff;
      height: 100%; display: flex; flex-direction: column;
      box-shadow: -4px 0 24px rgba(0,0,0,.12);
    }
    .drafts-drawer-head {
      padding: 20px 24px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      background: var(--brand); color: #fff;
    }
    .drafts-drawer-head span { font-weight: 700; font-size: .95rem; }
    .drafts-close { background: none; border: none; color: #fff; font-size: 1.2rem; cursor: pointer; opacity: .7; }
    .drafts-close:hover { opacity: 1; }
    .drafts-body { flex: 1; overflow-y: auto; padding: 16px; }
    .draft-item {
      border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px;
      margin-bottom: 10px; cursor: pointer; transition: border-color .15s, box-shadow .15s;
      display: flex; align-items: center; gap: 12px;
    }
    .draft-item:hover { border-color: var(--accent); box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .draft-item-info { flex: 1; min-width: 0; }
    .draft-item-name { font-weight: 600; font-size: .88rem; color: var(--text); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .draft-item-date { font-size: .75rem; color: var(--muted); }
    .draft-delete {
      background: none; border: none; color: #d1d5db; font-size: 1rem;
      cursor: pointer; padding: 4px; border-radius: 4px; flex-shrink: 0;
    }
    .draft-delete:hover { color: #ef4444; background: #fef2f2; }
    .drafts-empty { text-align: center; padding: 48px 24px; color: var(--muted); font-size: .88rem; }

    /* Toast */
    .toast {
      position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
      background: #1a1a2e; color: #fff; padding: 10px 20px;
      border-radius: 8px; font-size: .83rem; font-weight: 600;
      z-index: 2000; opacity: 0; transition: opacity .2s;
      pointer-events: none;
    }
    .toast.show { opacity: 1; }

    /* Layout */
    .wrapper { max-width: 860px; margin: 40px auto 80px; padding: 0 16px; }

    /* Stepper */
    .stepper {
      display: flex; align-items: flex-start; justify-content: space-between;
      margin-bottom: 36px; position: relative;
    }
    .stepper::before {
      content: ''; position: absolute; top: 18px; left: 0; right: 0;
      height: 2px; background: var(--border); z-index: 0;
    }
    .step-item {
      display: flex; flex-direction: column; align-items: center;
      gap: 6px; flex: 1; position: relative; z-index: 1; cursor: pointer;
    }
    .step-item:hover .step-circle { border-color: var(--accent); color: var(--accent); }
    .step-item.active:hover .step-circle,
    .step-item.done:hover   .step-circle { opacity: .85; }
    .step-circle {
      width: 36px; height: 36px; border-radius: 50%;
      background: var(--white); border: 2px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: .85rem; font-weight: 600; color: var(--muted); transition: all .25s;
    }
    .step-item.active .step-circle { background: var(--accent); border-color: var(--accent); color: white; }
    .step-item.done   .step-circle { background: var(--step-done); border-color: var(--step-done); color: white; }
    .step-label { font-size: .72rem; color: var(--muted); text-align: center; max-width: 80px; line-height: 1.3; }
    .step-item.active .step-label { color: var(--accent); font-weight: 600; }
    .step-item.done   .step-label { color: var(--step-done); }

    /* Card */
    .card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 36px 40px; }
    .card-title    { font-size: 1.35rem; font-weight: 700; color: var(--brand); margin-bottom: 6px; }
    .card-subtitle { font-size: .9rem; color: var(--muted); margin-bottom: 28px; }

    /* Form elements */
    .field-group        { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .field-group.single { grid-template-columns: 1fr; }
    .field            { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    .field label      { font-size: .82rem; font-weight: 600; color: var(--text); }
    .field label .req { color: var(--accent); margin-left: 2px; }
    .field input, .field select, .field textarea {
      border: 1.5px solid var(--border); border-radius: 7px;
      padding: 10px 13px; font-size: .9rem; color: var(--text);
      outline: none; transition: border .2s; background: white; font-family: inherit;
    }
    .field input:focus, .field select:focus, .field textarea:focus { border-color: var(--accent); }
    .field textarea { resize: vertical; min-height: 90px; }
    .field .hint    { font-size: .75rem; color: var(--muted); }

    /* Section head */
    .section-head {
      font-size: .78rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: var(--muted);
      margin: 24px 0 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border);
    }

    /* Checkbox grid */
    .check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 4px; }
    .check-item {
      display: flex; align-items: flex-start; gap: 9px;
      padding: 10px 12px; border: 1.5px solid var(--border);
      border-radius: 8px; cursor: pointer;
      transition: border .2s, background .2s;
      font-size: .85rem; line-height: 1.35;
    }
    .check-item:hover { border-color: var(--accent); background: var(--accent-light); }
    .check-item input[type="checkbox"] { margin-top: 2px; accent-color: var(--accent); flex-shrink: 0; }
    .check-item .check-text { flex: 1; }
    .check-item.custom-check { border-style: dashed; }
    .check-remove {
      background: none; border: none; color: var(--muted); cursor: pointer;
      font-size: 15px; line-height: 1; padding: 0 2px; margin-left: auto; flex-shrink: 0;
    }
    .check-remove:hover { color: var(--accent); }

    /* Brief add row */
    .check-add-row { display: flex; gap: 8px; margin-top: 10px; margin-bottom: 4px; }
    .check-add-input {
      flex: 1; border: 1.5px solid var(--border); border-radius: 7px;
      padding: 8px 12px; font-size: .85rem; outline: none; font-family: inherit;
      color: var(--text); background: white;
    }
    .check-add-input:focus { border-color: var(--accent); }
    .check-add-btn {
      padding: 8px 14px; background: var(--white); border: 1.5px solid var(--border);
      border-radius: 7px; font-size: .82rem; font-weight: 600; cursor: pointer;
      font-family: inherit; color: var(--text); white-space: nowrap;
    }
    .check-add-btn:hover { border-color: var(--accent); color: var(--accent); }

    /* Scope chips */
    .scope-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 4px; }
    .scope-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 5px 13px; border-radius: 20px; border: 1.5px solid var(--border);
      font-size: .82rem; cursor: pointer; user-select: none;
      color: var(--muted); background: var(--white);
      transition: border-color .15s, color .15s, background .15s; line-height: 1.4;
    }
    .scope-chip:hover { border-color: var(--accent); color: var(--accent); }
    .scope-chip.selected { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }
    .scope-chip.custom { border-style: dashed; }
    .chip-remove { font-size: 14px; line-height: 1; opacity: .5; margin-left: 3px; }
    .chip-remove:hover { opacity: 1; }
    .scope-add-row { display: flex; gap: 8px; margin-top: 10px; margin-bottom: 4px; }
    .scope-add-input { flex: 1; border: 1.5px solid var(--border); border-radius: 7px; padding: 8px 12px; font-size: .85rem; outline: none; font-family: inherit; color: var(--text); background: white; }
    .scope-add-input:focus { border-color: var(--accent); }
    .scope-add-btn { padding: 8px 14px; background: var(--white); border: 1.5px solid var(--border); border-radius: 7px; font-size: .82rem; font-weight: 600; cursor: pointer; font-family: inherit; color: var(--text); white-space: nowrap; }
    .scope-add-btn:hover { border-color: var(--accent); color: var(--accent); }

    /* Quill rich text editor */
    .rte-wrap .ql-toolbar.ql-snow { border: 1.5px solid var(--border); border-radius: 7px 7px 0 0; padding: 6px 10px; background: #fafafa; }
    .rte-wrap .ql-container.ql-snow { border: 1.5px solid var(--border); border-top: none; border-radius: 0 0 7px 7px; font-size: .9rem; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
    .rte-wrap .ql-editor { min-height: 90px; padding: 10px 13px; color: var(--text); line-height: 1.65; }
    #clientSaidWrap .ql-editor { min-height: 120px; }
    .rte-wrap .ql-editor.ql-blank::before { color: var(--muted); font-style: normal; left: 13px; right: 13px; }
    .rte-wrap:focus-within .ql-toolbar.ql-snow,
    .rte-wrap:focus-within .ql-container.ql-snow { border-color: var(--accent); }
    .rte-wrap .ql-snow .ql-stroke { stroke: var(--muted); }
    .rte-wrap .ql-snow .ql-fill { fill: var(--muted); }
    .rte-wrap .ql-snow.ql-toolbar button:hover .ql-stroke { stroke: var(--accent); }
    .rte-wrap .ql-editor ul, .rte-wrap .ql-editor ol { padding-left: 1.2em; }

    /* Engagement cards */
    .eng-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; margin-bottom: 8px; }
    .eng-card {
      border: 2px solid var(--border); border-radius: var(--radius);
      padding: 16px; cursor: pointer; transition: all .2s; position: relative;
    }
    .eng-card:hover    { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); }
    .eng-card.selected { border-color: var(--accent); background: var(--accent-light); }
    .eng-card .eng-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
    .eng-card .badge { display: inline-block; font-size: .67rem; font-weight: 600; padding: 2px 7px; border-radius: 20px; background: #e0e7ff; color: #3730a3; }
    .eng-card .eng-title { font-weight: 700; font-size: .9rem; margin-bottom: 6px; }
    .eng-card .eng-desc  { font-size: .78rem; color: var(--muted); line-height: 1.4; }
    .eng-card.selected::after { content: '✓'; position: absolute; top: 10px; right: 12px; color: var(--accent); font-weight: 700; }

    /* Output cards */
    .output-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; }
    .output-card { border: 2px solid var(--border); border-radius: var(--radius); padding: 22px; cursor: pointer; transition: all .2s; }
    .output-card:hover    { border-color: var(--accent); }
    .output-card.selected { border-color: var(--accent); background: var(--accent-light); }
    .output-card .out-tag  { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 8px; }
    .output-card .out-title{ font-weight: 700; font-size: 1rem; margin-bottom: 6px; }
    .output-card .out-desc { font-size: .8rem; color: var(--muted); line-height: 1.45; margin-bottom: 14px; }
    .output-card .out-btn  { display: inline-block; padding: 9px 18px; background: var(--accent); color: white; border-radius: 7px; font-size: .82rem; font-weight: 600; border: none; cursor: pointer; font-family: inherit; }
    .output-card.selected .out-btn { background: var(--brand); }

    /* Radio pills */
    .radio-group { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
    .radio-pill { display: flex; align-items: center; gap: 7px; padding: 9px 16px; border: 1.5px solid var(--border); border-radius: 30px; cursor: pointer; font-size: .85rem; font-weight: 500; transition: all .2s; }
    .radio-pill:hover  { border-color: var(--accent); }
    .radio-pill input  { accent-color: var(--accent); }
    .radio-pill.active { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }

    /* Message box */
    .cs-pick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; margin-bottom: 8px; }
    .cs-pick-card { border: 2px solid var(--border); border-radius: var(--radius); padding: 16px; cursor: pointer; transition: border-color .15s, background .15s; }
    .cs-pick-card:hover { border-color: var(--accent); }
    .cs-pick-card.selected { border-color: var(--accent); background: var(--accent-light); }
    .cs-pick-card.disabled { opacity: .45; cursor: default; pointer-events: none; }
    .cs-pick-name { font-weight: 700; font-size: .88rem; margin-bottom: 5px; color: var(--text); }
    .cs-pick-desc { font-size: .76rem; color: var(--muted); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .cs-pick-card.selected .cs-pick-name { color: var(--accent); }
    .cs-pick-card.selected .cs-pick-desc { color: var(--text); }
    .cs-count { font-size: .78rem; color: var(--muted); margin-bottom: 18px; }
    .cs-empty { font-size: .85rem; color: var(--muted); padding: 12px 0; margin-bottom: 18px; }

    .msg-box { border: 1.5px solid var(--border); border-radius: var(--radius); padding: 20px; background: #fafafa; margin-bottom: 20px; }
    .msg-box textarea { width: 100%; border: none; background: transparent; font-size: .9rem; color: var(--text); resize: vertical; min-height: 140px; outline: none; font-family: inherit; line-height: 1.65; }
    .msg-meta { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }
    .msg-meta input { border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: .82rem; flex: 1; min-width: 140px; outline: none; }
    .msg-meta input:focus { border-color: var(--accent); }

    /* Nav row */
    .nav-row { display: flex; justify-content: space-between; align-items: center; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); }
    .btn         { padding: 11px 26px; border-radius: 8px; font-size: .9rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; font-family: inherit; }
    .btn-primary { background: var(--accent); color: white; }
    .btn-primary:hover { background: #c73652; }
    .btn-secondary { background: var(--white); color: var(--text); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-ghost { background: none; border: none; color: var(--accent); font-size: .83rem; cursor: pointer; padding: 6px 0; text-decoration: underline; font-family: inherit; }

    /* Utility */
    .hidden { display: none !important; }
    .mt-4   { margin-top: 16px; }
    .text-muted { color: var(--muted); font-size: .83rem; }

    /* Footer */
    .page-footer { text-align: center; margin-top: 48px; font-size: .75rem; color: var(--muted); }

    /* Responsive */
    @media (max-width: 640px) {
      .card { padding: 24px 18px; }
      .field-group { grid-template-columns: 1fr; }
      .output-grid { grid-template-columns: 1fr; }
      .stepper { gap: 4px; }
      .step-label { font-size: .65rem; max-width: 60px; }
    }
  </style>
</head>
<body>

<header>
  <div class="logo">Core<span>Voice</span></div>
  <span class="sep">·</span>
  <span class="sub">Contract builder</span>
  <div class="header-actions">
    <a href="/CVwebapp/admin/lists.php" class="btn-header">Manage lists</a>
    <button class="btn-header" onclick="openDraftsPanel()">Files</button>
    <button class="btn-header accent" onclick="saveDraft()">Save</button>
  </div>
</header>

<!-- Drafts panel -->
<div class="drafts-overlay hidden" id="draftsOverlay" onclick="if(event.target===this)closeDraftsPanel()">
  <div class="drafts-drawer">
    <div class="drafts-drawer-head">
      <span>Files</span>
      <button class="drafts-close" onclick="closeDraftsPanel()">&#x2715;</button>
    </div>
    <div class="drafts-body" id="draftsList"></div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<div class="wrapper">

  <!-- Stepper -->
  <div class="stepper" id="stepper">
    <div class="step-item active" data-step="1" onclick="goTo(1)"><div class="step-circle">1</div><div class="step-label">Client details</div></div>
    <div class="step-item"        data-step="2" onclick="goTo(2)"><div class="step-circle">2</div><div class="step-label">Business brief</div></div>
    <div class="step-item"        data-step="3" onclick="goTo(3)"><div class="step-circle">3</div><div class="step-label">Engagement type</div></div>
    <div class="step-item"        data-step="4" onclick="goTo(4)"><div class="step-circle">4</div><div class="step-label">Scope of work</div></div>
    <div class="step-item"        data-step="5" onclick="goTo(5)"><div class="step-circle">5</div><div class="step-label">Fee &amp; payment</div></div>
    <div class="step-item"        data-step="6" onclick="goTo(6)"><div class="step-circle">6</div><div class="step-label">Generate</div></div>
  </div>

  <!-- STEP 1: Client Details -->
  <div class="card" id="step1">
    <div class="card-title">Who are we working with?</div>
    <div class="card-subtitle">Basic company and signatory details for the agreement.</div>

    <div class="field-group">
      <div class="field">
        <label>Company legal name <span class="req">*</span></label>
        <input type="text" id="companyName" placeholder="e.g. Acme Technologies Pvt Ltd" />
      </div>
      <div class="field">
        <label>Company type</label>
        <select id="companyType">
          <option value="">Select type</option>
          <option>Private Limited</option>
          <option>LLP</option>
          <option>Inc (Delaware)</option>
          <option>Ltd (UK)</option>
          <option>Other</option>
        </select>
      </div>
    </div>

    <div class="field-group">
      <div class="field">
        <label>CIN / Registration no.</label>
        <input type="text" id="cin" placeholder="e.g. U72900MH2018PTC123456" />
      </div>
      <div class="field">
        <label>GST number</label>
        <input type="text" id="gst" placeholder="e.g. 27AADCB2230M1ZT" />
      </div>
    </div>

    <div class="field">
      <label>Registered address <span class="req">*</span></label>
      <textarea id="address" placeholder="Full registered address of the company"></textarea>
    </div>

    <div class="field-group">
      <div class="field">
        <label>Signatory name <span class="req">*</span></label>
        <input type="text" id="signatoryName" placeholder="Full name" />
      </div>
      <div class="field">
        <label>Designation <span class="req">*</span></label>
        <input type="text" id="designation" placeholder="e.g. CEO, Director" />
      </div>
    </div>

    <div class="field-group">
      <div class="field">
        <label>Signatory email</label>
        <input type="email" id="signatoryEmail" placeholder="signatory@company.com" />
      </div>
      <div class="field">
        <label>Agreement date <span class="req">*</span></label>
        <input type="date" id="agreementDate" />
      </div>
    </div>

    <div class="field">
      <label>Client's business — one line <span class="req">*</span></label>
      <input type="text" id="bizDescription" placeholder="e.g. builds AI-powered compliance tools for mid-market financial firms" />
      <span class="hint">Goes into the WHEREAS clause. Start with a verb.</span>
    </div>

    <div class="nav-row">
      <button class="btn-ghost" onclick="fillSampleData()">&#x26A1; Fill with sample data</button>
      <button class="btn btn-primary" onclick="goTo(2)">Next: Business brief &#x2192;</button>
    </div>
  </div>

  <!-- STEP 2: Business Brief -->
  <div class="card hidden" id="step2">
    <div class="card-title">What did the client say?</div>
    <div class="card-subtitle">Capture the conversation. This shapes the engagement recommendation and appears in the proposal.</div>

    <div class="field">
      <label>What did the client say? <span class="req">*</span></label>
      <div class="rte-wrap" id="clientSaidWrap"></div>
      <input type="hidden" id="clientSaid" />
    </div>

    <?php
    $briefSections = [
        ['key' => 'brief_sales',        'label' => 'Sales triggers',                   'section' => 'sales',        'id' => 'briefSalesChips'],
        ['key' => 'brief_messaging',    'label' => 'Messaging / Positioning triggers', 'section' => 'messaging',    'id' => 'briefMessagingChips'],
        ['key' => 'brief_mkt_strategy', 'label' => 'Marketing strategy triggers',      'section' => 'mkt_strategy', 'id' => 'briefMktStrategyChips'],
        ['key' => 'brief_structure',    'label' => 'Existing marketing structure',      'section' => 'structure',    'id' => 'briefStructureChips'],
        ['key' => 'brief_engagement',   'label' => 'About the engagement',             'section' => 'engagement',   'id' => 'briefEngagementChips'],
    ];
    foreach ($briefSections as $bs):
        $items   = $lists[$bs['key']] ?? [];
        $sec     = htmlspecialchars($bs['section']);
        $chipsId = htmlspecialchars($bs['id']);
        $ph      = htmlspecialchars('Add to ' . $bs['label'] . '...');
    ?>
    <div class="section-head"><?= htmlspecialchars($bs['label']) ?></div>
    <div class="scope-chips" id="<?= $chipsId ?>">
      <?php foreach ($items as $item):
        $val = htmlspecialchars($item['label']);
      ?>
      <span class="scope-chip" data-section="<?= $sec ?>" data-value="<?= $val ?>"
        onclick="toggleScopeChip(this)"><?= $val ?></span>
      <?php endforeach; ?>
    </div>
    <div class="scope-add-row">
      <input type="text" class="scope-add-input" placeholder="<?= $ph ?>"
        onkeydown="if(event.key==='Enter'){event.preventDefault();addScopeItem('<?= $chipsId ?>','<?= $sec ?>',this)}" />
      <button type="button" class="scope-add-btn"
        onclick="addScopeItem('<?= $chipsId ?>','<?= $sec ?>',this.previousElementSibling)">+ Add</button>
    </div>
    <?php endforeach; ?>

    <div class="nav-row">
      <button class="btn btn-secondary" onclick="goTo(1)">&#x2190; Back</button>
      <button class="btn btn-primary" onclick="goTo(3)">Next: Engagement type &#x2192;</button>
    </div>
  </div>

  <!-- STEP 3: Engagement Type -->
  <div class="card hidden" id="step3">
    <div class="card-title">Select the type of engagement.</div>

    <div class="eng-grid">
      <div class="eng-card" data-eng="full-retainer" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Retainership</span><span class="badge">Ongoing</span></div>
        <div class="eng-title">Full-stack retainer</div>
        <div class="eng-desc">Strategy, content, and marketing ops — all three running together on an ongoing basis. We operate as an external marketing team, shared across functions.</div>
      </div>
      <div class="eng-card" data-eng="outcome-retainer" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Retainership</span><span class="badge">Time-boxed</span></div>
        <div class="eng-title">Outcome-focused retainer</div>
        <div class="eng-desc">Similar to full-stack retainer but it's time-boxed and around a specific goal. We define the target and the window together, then do everything needed to get there.</div>
      </div>
      <div class="eng-card" data-eng="content-retainer" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Retainership</span><span class="badge">Ongoing</span></div>
        <div class="eng-title">Content retainer</div>
        <div class="eng-desc">Ongoing production of content assets — video, text, images, webpages, etc, etc. Built to accumulate and compound over time.</div>
      </div>
      <div class="eng-card" data-eng="new-gtm" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Project</span></div>
        <div class="eng-title">New product GTM</div>
        <div class="eng-desc">Positioning, identity, website, and a full sales kit (deck, videos, brochures, etc). Optional press outreach. For companies launching for the first time or after a pivot.</div>
      </div>
      <div class="eng-card" data-eng="gtm-relaunch" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Project</span></div>
        <div class="eng-title">GTM relaunch</div>
        <div class="eng-desc">Visual refresh of existing brand artefacts, updated website, updated sales kit (deck, videos, brochures, etc). Optional booth redesign and press outreach.</div>
      </div>
      <div class="eng-card" data-eng="fundraising" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Project</span></div>
        <div class="eng-title">Fundraising comms</div>
        <div class="eng-desc">Narrative clean-up, pitch deck, and website redesign (optional) for startups heading into a funding round.</div>
      </div>
      <div class="eng-card" data-eng="sales-video" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Project</span></div>
        <div class="eng-title">Content sprint</div>
        <div class="eng-desc">Fresh content for use in sales or others. Could be video focussed — product explainers, testimonials, or use-case demos. Could be other stuff.</div>
      </div>
      <div class="eng-card" data-eng="custom" onclick="selectEng(this)">
        <div class="eng-badges"><span class="badge">Custom</span></div>
        <div class="eng-title">Custom scope</div>
        <div class="eng-desc">Define your own scope from scratch.</div>
      </div>
    </div>

    <div class="field-group mt-4">
      <div class="field">
        <label>Duration <span class="req">*</span></label>
        <select id="duration">
          <option value="">Select duration</option>
          <option>1 month</option>
          <option>2 months</option>
          <option>3 months</option>
          <option>6 months</option>
          <option>12 months</option>
          <option>Ongoing</option>
          <option>Custom</option>
        </select>
      </div>
      <div class="field">
        <label>Effective from</label>
        <input type="date" id="effectiveDate" />
      </div>
    </div>

    <div class="nav-row">
      <button class="btn btn-secondary" onclick="goTo(2)">&#x2190; Back</button>
      <button class="btn btn-primary" onclick="goTo(4)">Next: Scope of work &#x2192;</button>
    </div>
  </div>

  <!-- STEP 4: Scope of Work -->
  <div class="card hidden" id="step4">
    <div class="card-title">Scope of work</div>
    <div class="card-subtitle">Set the objective and select services for Annexure A.</div>

    <div class="field">
      <label>Marketing objective <span class="req">*</span></label>
      <div class="rte-wrap" id="objectiveWrap"></div>
      <input type="hidden" id="objective" />
    </div>

    <?php
    $scopeSections = [
        ['key' => 'scope_strategy', 'label' => 'Strategy: Figure out...', 'section' => 'strategy', 'id' => 'strategyChips', 'ph' => 'Add to Figure out...'],
        ['key' => 'scope_content',  'label' => 'Content: Make these...',  'section' => 'content',  'id' => 'contentChips',  'ph' => 'Add to Make these...'],
        ['key' => 'scope_ops', 'label' => 'Marketing Ops: Setup and/or manage these...', 'section' => 'ops', 'id' => 'opsChips', 'ph' => 'Add to Setup/Manage...'],
    ];
    foreach ($scopeSections as $ss):
        $items   = $lists[$ss['key']] ?? [];
        $sec     = htmlspecialchars($ss['section']);
        $chipsId = htmlspecialchars($ss['id']);
        $ph      = htmlspecialchars($ss['ph']);
    ?>
    <div class="section-head"><?= htmlspecialchars($ss['label']) ?></div>
    <div class="scope-chips" id="<?= $chipsId ?>">
      <?php foreach ($items as $item):
        $val = htmlspecialchars($item['label']);
      ?>
      <span class="scope-chip" data-section="<?= $sec ?>" data-value="<?= $val ?>"
        onclick="toggleScopeChip(this)"><?= $val ?></span>
      <?php endforeach; ?>
    </div>
    <div class="scope-add-row">
      <input type="text" class="scope-add-input" placeholder="<?= $ph ?>"
        onkeydown="if(event.key==='Enter'){event.preventDefault();addScopeItem('<?= $chipsId ?>','<?= $sec ?>',this)}" />
      <button type="button" class="scope-add-btn"
        onclick="addScopeItem('<?= $chipsId ?>','<?= $sec ?>',this.previousElementSibling)">+ Add</button>
    </div>
    <?php endforeach; ?>

    <div class="section-head">Governance cadence</div>
    <div class="radio-group" id="cadenceGroup">
      <label class="radio-pill" onclick="selectPill(this, 'cadenceGroup')"><input type="radio" name="cadence" value="weekly" /> Weekly</label>
      <label class="radio-pill" onclick="selectPill(this, 'cadenceGroup')"><input type="radio" name="cadence" value="monthly" /> Monthly review</label>
      <label class="radio-pill" onclick="selectPill(this, 'cadenceGroup')"><input type="radio" name="cadence" value="quarterly" /> Quarterly review</label>
    </div>

    <div class="field">
      <label>Additional scope notes <span class="text-muted">(optional)</span></label>
      <input type="text" id="additionalScope" placeholder="Any exclusions, assumptions, or nuances" />
    </div>

    <div class="nav-row">
      <button class="btn btn-secondary" onclick="goTo(3)">&#x2190; Back</button>
      <button class="btn btn-primary" onclick="goTo(5)">Next: Fee &amp; payment &#x2192;</button>
    </div>
  </div>

  <!-- STEP 5: Fee & Payment -->
  <div class="card hidden" id="step5">
    <div class="card-title">Fee &amp; payment</div>
    <div class="card-subtitle">This will populate Annexure B.</div>

    <div class="section-head">Currency</div>
    <div class="radio-group" id="currencyGroup">
      <label class="radio-pill active" onclick="selectPill(this, 'currencyGroup')"><input type="radio" name="currency" value="INR" checked /> &#x20B9; INR</label>
      <label class="radio-pill"        onclick="selectPill(this, 'currencyGroup')"><input type="radio" name="currency" value="USD" /> $ USD</label>
    </div>

    <div class="section-head">Fee structure</div>
    <div class="radio-group" id="feeGroup">
      <label class="radio-pill" onclick="selectPill(this, 'feeGroup'); showFeeFields('retainer')"><input type="radio" name="feeType" value="retainer" /> Monthly retainer</label>
      <label class="radio-pill" onclick="selectPill(this, 'feeGroup'); showFeeFields('fixed')"><input type="radio" name="feeType" value="fixed" /> Fixed project fee</label>
      <label class="radio-pill" onclick="selectPill(this, 'feeGroup'); showFeeFields('milestone')"><input type="radio" name="feeType" value="milestone" /> Milestone-based</label>
    </div>

    <div id="retainerFields" class="hidden">
      <div class="field-group">
        <div class="field">
          <label>Monthly fee <span class="req">*</span></label>
          <input type="number" id="monthlyFee" placeholder="excl. GST" />
        </div>
        <div class="field">
          <label>Duration</label>
          <select id="retainerDuration">
            <option value="">Select</option>
            <option>3 months</option>
            <option>6 months</option>
            <option>12 months</option>
            <option>Ongoing</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Payment terms</label>
        <div class="radio-group" id="retainerTermsGroup">
          <label class="radio-pill" onclick="selectPill(this,'retainerTermsGroup')"><input type="radio" name="retainerTerms" value="Net 15" /> Net 15</label>
          <label class="radio-pill" onclick="selectPill(this,'retainerTermsGroup')"><input type="radio" name="retainerTerms" value="Net 30" /> Net 30</label>
          <label class="radio-pill" onclick="selectPill(this,'retainerTermsGroup')"><input type="radio" name="retainerTerms" value="Advance" /> Advance</label>
        </div>
      </div>
    </div>

    <div id="fixedFields" class="hidden">
      <div class="field-group">
        <div class="field">
          <label>Total fixed fee <span class="req">*</span></label>
          <input type="number" id="totalFee" placeholder="excl. GST" />
        </div>
        <div class="field">
          <label>Advance amount</label>
          <input type="number" id="fixedAdvance" placeholder="e.g. 250000" />
        </div>
      </div>
      <div class="field">
        <label>Payment terms</label>
        <div class="radio-group" id="fixedTermsGroup">
          <label class="radio-pill" onclick="selectPill(this,'fixedTermsGroup')"><input type="radio" name="fixedTerms" value="Net 15" /> Net 15</label>
          <label class="radio-pill" onclick="selectPill(this,'fixedTermsGroup')"><input type="radio" name="fixedTerms" value="Net 30" /> Net 30</label>
        </div>
      </div>
    </div>

    <div id="milestoneFields" class="hidden">
      <div class="field">
        <label>Milestone payment schedule <span class="req">*</span></label>
        <textarea id="milestoneSchedule" placeholder="e.g.&#10;Milestone 1 — Kickoff &amp; strategy: &#x20B9;1,00,000&#10;Milestone 2 — Mid-delivery review: &#x20B9;1,50,000&#10;Milestone 3 — Final delivery: &#x20B9;1,00,000"></textarea>
      </div>
    </div>

    <div class="section-head">Out-of-pocket expenses</div>
    <div class="radio-group" id="expenseGroup">
      <label class="radio-pill" onclick="selectPill(this, 'expenseGroup')"><input type="radio" name="expenses" value="preapproved" /> Pre-approved and submitted for reimbursement</label>
      <label class="radio-pill" onclick="selectPill(this, 'expenseGroup')"><input type="radio" name="expenses" value="none" /> No OPE reimbursement</label>
    </div>

    <div class="field">
      <label>Additional payment notes <span class="text-muted">(optional)</span></label>
      <input type="text" id="paymentNotes" placeholder="Any additional payment conditions or notes" />
    </div>

    <div class="nav-row">
      <button class="btn btn-secondary" onclick="goTo(4)">&#x2190; Back</button>
      <button class="btn btn-primary" onclick="goTo(6)">Next: Generate &#x2192;</button>
    </div>
  </div>

  <!-- STEP 6: Generate -->
  <div class="card hidden" id="step6">
    <div class="card-title">Generate document</div>
    <div class="card-subtitle">Choose what you'd like to produce. Both use the same inputs you've just filled in.</div>

    <div class="section-head">Case studies</div>
    <p class="text-muted" style="margin-bottom:12px;">Pick up to 3 to show in the proposal.</p>
    <?php if (empty($caseStudies)): ?>
    <div class="cs-empty">No case studies yet. <a href="/CVwebapp/admin/case_studies.php">Add some in the admin.</a></div>
    <?php else: ?>
    <div class="cs-pick-grid" id="csPickGrid">
      <?php foreach ($caseStudies as $cs): ?>
      <div class="cs-pick-card" data-id="<?= (int)$cs['id'] ?>" onclick="toggleCaseStudy(this)">
        <div class="cs-pick-name"><?= htmlspecialchars($cs['name']) ?></div>
        <div class="cs-pick-desc"><?= htmlspecialchars($cs['description']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="cs-count" id="csCount">0 of 3 selected</div>
    <?php endif; ?>

    <div class="section-head">Message from CV team</div>
    <p class="text-muted" style="margin-bottom:12px;">Editable — appears at the top of the proposal.</p>
    <div class="msg-box">
      <textarea id="msgBody">Hi [first name],

Thanks for the conversation — it's clear you're building something that matters, and we'd love to be part of it.

What we do best is take the marketing off your plate in a way that actually feels like an extension of your team, not an agency relationship.

This proposal outlines what we'd recommend, what's in scope, and what it costs. It's a starting point — if anything needs adjusting, just say so.</textarea>
      <div class="msg-meta">
        <input type="text" id="senderName" placeholder="Sender name" />
        <input type="text" id="senderTitle" placeholder="Sender title" />
        <input type="email" id="senderEmail" placeholder="Sender email" />
      </div>
    </div>

    <div class="output-grid">
      <div class="output-card" onclick="selectOutput(this, 'proposal')">
        <div class="out-tag">Send before signing</div>
        <div class="out-title">Proposal</div>
        <div class="out-desc">Warm, conversational. Personal note, recommended engagement, scope, fee, and next steps. No legal language.</div>
        <button class="out-btn" onclick="event.stopPropagation(); selectOutput(this.closest('.output-card'), 'proposal'); generateDocument()">Generate proposal &#x2192;</button>
      </div>
      <div class="output-card" onclick="selectOutput(this, 'contract')">
        <div class="out-tag">Ready to sign</div>
        <div class="out-title">Contract</div>
        <div class="out-desc">Full legal agreement with all clauses, Annexure A scope, Annexure B fee terms, and Annexure C NDA.</div>
        <button class="out-btn" onclick="event.stopPropagation(); selectOutput(this.closest('.output-card'), 'contract'); generateDocument()">Generate contract &#x2192;</button>
      </div>
    </div>

    <div class="nav-row">
      <button class="btn btn-secondary" onclick="goTo(5)">&#x2190; Back to fee</button>
      <div></div>
    </div>
  </div>

</div>

<footer class="page-footer">CoreVoice &middot; Corebook Consulting Pvt Ltd</footer>

<script src="/CVwebapp/assets/quill.min.js"></script>
<script>
  var currentStep   = 1;
  var selectedEng    = null;
  var selectedOutput = null;

  function goTo(step) {
    document.getElementById('step' + currentStep).classList.add('hidden');
    document.getElementById('step' + step).classList.remove('hidden');
    document.querySelectorAll('.step-item').forEach(function(item) {
      var s = parseInt(item.dataset.step);
      item.classList.remove('active', 'done');
      if (s < step) item.classList.add('done');
      if (s === step) item.classList.add('active');
    });
    currentStep = step;
    ensureRTE(step);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    silentSave();
  }

  function selectEng(card) {
    document.querySelectorAll('.eng-card').forEach(function(c) { c.classList.remove('selected'); });
    card.classList.add('selected');
    selectedEng = card.dataset.eng;
  }

  function selectOutput(card, type) {
    document.querySelectorAll('.output-card').forEach(function(c) { c.classList.remove('selected'); });
    card.classList.add('selected');
    selectedOutput = type;
  }

  function selectPill(pill, groupId) {
    document.getElementById(groupId).querySelectorAll('.radio-pill').forEach(function(p) { p.classList.remove('active'); });
    pill.classList.add('active');
  }

  function showFeeFields(type) {
    ['retainerFields', 'fixedFields', 'milestoneFields'].forEach(function(id) {
      document.getElementById(id).classList.add('hidden');
    });
    document.getElementById(type + 'Fields').classList.remove('hidden');
  }

  function setPill(radioName, value) {
    var radio = document.querySelector('input[name="' + radioName + '"][value="' + value + '"]');
    if (!radio) return;
    radio.checked = true;
    var pill  = radio.closest('.radio-pill');
    var group = pill.parentElement;
    group.querySelectorAll('.radio-pill').forEach(function(p) { p.classList.remove('active'); });
    pill.classList.add('active');
  }

  function fillSampleData() {
    document.getElementById('companyName').value    = 'Nexora Health Technologies Pvt Ltd';
    document.getElementById('companyType').value    = 'Private Limited';
    document.getElementById('cin').value            = 'U85110KA2021PTC234567';
    document.getElementById('gst').value            = '29AACCN1234M1Z5';
    document.getElementById('address').value        = '42, HSR Layout Sector 6, Bengaluru, Karnataka 560102';
    document.getElementById('signatoryName').value  = 'Priya Venkataraman';
    document.getElementById('designation').value    = 'Co-Founder & CEO';
    document.getElementById('signatoryEmail').value = 'priya@nexorahealth.in';
    document.getElementById('agreementDate').value  = '2026-06-01';
    document.getElementById('bizDescription').value = 'builds AI-powered wellness programs for enterprise HR teams';

    setRTE('clientSaid',
      '<p>We\'ve been growing steadily but our marketing is entirely word-of-mouth. ' +
      'The founding team is spending too much time on content and we need someone to take it off our plate. ' +
      'We have a real product and real customers — we just need the world to know about it.</p>');
    ['Founding team doing marketing',
     'Post-fundraise, need to scale growth',
     'Long sales cycle, need consistent engagement',
     'Want to own category narrative'
    ].forEach(function(val) {
      var chip = document.querySelector('#briefSalesChips .scope-chip[data-value="' + val + '"],' +
        '#briefMessagingChips .scope-chip[data-value="' + val + '"],' +
        '#briefMktStrategyChips .scope-chip[data-value="' + val + '"],' +
        '#briefStructureChips .scope-chip[data-value="' + val + '"],' +
        '#briefEngagementChips .scope-chip[data-value="' + val + '"]');
      if (chip) chip.classList.add('selected');
    });

    var engCard = document.querySelector('[data-eng="full-retainer"]');
    if (engCard) selectEng(engCard);
    document.getElementById('duration').value      = '6 months';
    document.getElementById('effectiveDate').value = '2026-06-15';

    setRTE('objective',
      '<p>Build Nexora\'s marketing function from scratch — establish clear positioning, ' +
      'drive awareness among enterprise HR buyers, and create a consistent content engine ' +
      'that generates inbound leads over the next two quarters.</p>');
    addScopeItem('strategyChips', 'strategy', { value: 'Quarterly brand health audit', focus: function() {} });
    document.getElementById('additionalScope').value = 'Paid media execution deferred to month 3 pending creative readiness.';
    setPill('cadence', 'monthly');

    setPill('currency', 'INR');
    setPill('feeType', 'retainer');
    showFeeFields('retainer');
    document.getElementById('monthlyFee').value       = '225000';
    document.getElementById('retainerDuration').value = '6 months';
    setPill('retainerTerms', 'Net 15');
    setPill('expenses', 'preapproved');
    document.getElementById('paymentNotes').value = 'First invoice raised on 1 June 2026 upon contract signing.';

    document.getElementById('msgBody').value =
      'Hi Priya,\n\nThanks for the conversation — it\'s clear you\'re building something that matters, ' +
      'and we\'d love to be part of it.\n\nWhat we do best is take the marketing off your plate in a way ' +
      'that actually feels like an extension of your team, not an agency relationship.\n\nThis proposal outlines ' +
      'what we\'d recommend, what\'s in scope, and what it costs. It\'s a starting point — ' +
      'if anything needs adjusting, just say so.';
    document.getElementById('senderName').value  = 'Amrut Shastri';
    document.getElementById('senderTitle').value = 'Founder, CoreVoice';
    document.getElementById('senderEmail').value = 'amrut@corevoice.in';
  }

  /* Case study picker */
  function toggleCaseStudy(card) {
    var grid = document.getElementById('csPickGrid');
    if (!grid) return;
    var selected = grid.querySelectorAll('.cs-pick-card.selected');
    if (card.classList.contains('selected')) {
      card.classList.remove('selected');
    } else {
      if (selected.length >= 3) return;
      card.classList.add('selected');
    }
    var now = grid.querySelectorAll('.cs-pick-card.selected').length;
    document.getElementById('csCount').textContent = now + ' of 3 selected';
    grid.querySelectorAll('.cs-pick-card:not(.selected)').forEach(function(c) {
      c.classList.toggle('disabled', now >= 3);
    });
  }

  /* Scope chips */
  function toggleScopeChip(chip) {
    chip.classList.toggle('selected');
  }

  function addScopeItem(chipsId, section, input) {
    var val = (typeof input === 'object') ? input.value.trim() : String(input).trim();
    if (!val) return;
    var chips = document.getElementById(chipsId);
    if ([].slice.call(chips.querySelectorAll('.custom')).some(function(c) { return c.dataset.value === val; })) return;
    var chip = document.createElement('span');
    chip.className = 'scope-chip selected custom';
    chip.dataset.section = section;
    chip.dataset.value = val;
    var rm = document.createElement('span');
    rm.className = 'chip-remove';
    rm.textContent = ' x';
    rm.onclick = function(e) { e.stopPropagation(); chip.remove(); };
    chip.appendChild(document.createTextNode(val));
    chip.appendChild(rm);
    chips.appendChild(chip);
    if (typeof input === 'object' && 'value' in input) { input.value = ''; if (input.focus) input.focus(); }
  }

  /* Draft save / load */
  var _urlId = parseInt(new URLSearchParams(window.location.search).get('id')) || null;
  var currentDraftId  = _urlId;
  var _restoringDraft = false;

  function collectFormData() {
    syncRTE();
    var d = {};
    ['companyName','companyType','cin','gst','address','signatoryName','designation',
     'signatoryEmail','agreementDate','bizDescription','clientSaid','duration',
     'effectiveDate','objective','additionalScope','monthlyFee',
     'retainerDuration','totalFee','fixedAdvance','milestoneSchedule','paymentNotes',
     'msgBody','senderName','senderTitle','senderEmail'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) d[id] = el.value;
    });
    var BRIEF_CHIP_MAP = [
      ['briefSalesChips','sales'],['briefMessagingChips','messaging'],
      ['briefMktStrategyChips','mkt_strategy'],['briefStructureChips','structure'],
      ['briefEngagementChips','engagement']
    ];
    d.triggers = [];
    BRIEF_CHIP_MAP.forEach(function(pair) {
      var container = document.getElementById(pair[0]);
      if (!container) return;
      [].slice.call(container.querySelectorAll('.scope-chip.selected:not(.custom)')).forEach(function(chip) { d.triggers.push(chip.dataset.value); });
      d['customBrief_' + pair[1]] = [].slice.call(container.querySelectorAll('.scope-chip.custom')).map(function(c) { return c.dataset.value; });
    });
    d.scope    = [].slice.call(document.querySelectorAll('#strategyChips .scope-chip.selected:not(.custom),#contentChips .scope-chip.selected:not(.custom),#opsChips .scope-chip.selected:not(.custom)')).map(function(e) { return e.dataset.value; });
    d.customStrategyItems = [].slice.call(document.querySelectorAll('#strategyChips .scope-chip.custom')).map(function(e) { return e.dataset.value; });
    d.customContentItems  = [].slice.call(document.querySelectorAll('#contentChips .scope-chip.custom')).map(function(e) { return e.dataset.value; });
    d.customOpsItems      = [].slice.call(document.querySelectorAll('#opsChips .scope-chip.custom')).map(function(e) { return e.dataset.value; });
    d.engagementType = selectedEng || '';
    ['currency','feeType','cadence','retainerTerms','fixedTerms','expenses'].forEach(function(name) {
      var el = document.querySelector('input[name="' + name + '"]:checked');
      d[name] = el ? el.value : '';
    });
    var csGrid = document.getElementById('csPickGrid');
    d.caseStudyIds = csGrid
      ? [].slice.call(csGrid.querySelectorAll('.cs-pick-card.selected')).map(function(c) { return parseInt(c.dataset.id); })
      : [];
    return d;
  }

  function restoreFormData(d) {
    ['companyName','companyType','cin','gst','address','signatoryName','designation',
     'signatoryEmail','agreementDate','bizDescription','clientSaid','duration',
     'effectiveDate','objective','additionalScope','monthlyFee',
     'retainerDuration','totalFee','fixedAdvance','milestoneSchedule','paymentNotes',
     'msgBody','senderName','senderTitle','senderEmail'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el && d[id] !== undefined) el.value = d[id];
    });
    var BRIEF_CHIP_MAP = [
      ['briefSalesChips','sales'],['briefMessagingChips','messaging'],
      ['briefMktStrategyChips','mkt_strategy'],['briefStructureChips','structure'],
      ['briefEngagementChips','engagement']
    ];
    BRIEF_CHIP_MAP.forEach(function(pair) {
      var chipsId = pair[0], sec = pair[1];
      var container = document.getElementById(chipsId);
      if (!container) return;
      container.querySelectorAll('.scope-chip:not(.custom)').forEach(function(chip) {
        chip.classList.toggle('selected', (d.triggers || []).indexOf(chip.dataset.value) !== -1);
      });
      (d['customBrief_' + sec] || []).forEach(function(val) { if (val) addScopeItem(chipsId, sec, {value: val, focus: function(){}}); });
    });
    if (d.engagementType) {
      var card = document.querySelector('[data-eng="' + d.engagementType + '"]');
      if (card) selectEng(card);
    }
    setRTE('clientSaid', d.clientSaid || '');
    setRTE('objective',  d.objective  || '');
    document.querySelectorAll('#strategyChips .scope-chip:not(.custom),#contentChips .scope-chip:not(.custom),#opsChips .scope-chip:not(.custom)').forEach(function(chip) {
      chip.classList.toggle('selected', (d.scope || []).indexOf(chip.dataset.value) !== -1);
    });
    [['strategy','strategyChips'],['content','contentChips'],['ops','opsChips']].forEach(function(pair) {
      var section = pair[0], chipsId = pair[1];
      var key = 'custom' + section.charAt(0).toUpperCase() + section.slice(1) + 'Items';
      (d[key] || []).forEach(function(val) { if (val) addScopeItem(chipsId, section, {value: val, focus: function(){}}); });
    });
    if (d.currency)      setPill('currency', d.currency);
    if (d.feeType)     { setPill('feeType', d.feeType); showFeeFields(d.feeType); }
    if (d.cadence)       setPill('cadence', d.cadence);
    if (d.retainerTerms) setPill('retainerTerms', d.retainerTerms);
    if (d.fixedTerms)    setPill('fixedTerms', d.fixedTerms);
    if (d.expenses)      setPill('expenses', d.expenses);
    var csGrid = document.getElementById('csPickGrid');
    if (csGrid && Array.isArray(d.caseStudyIds)) {
      csGrid.querySelectorAll('.cs-pick-card').forEach(function(c) {
        c.classList.remove('selected', 'disabled');
      });
      d.caseStudyIds.forEach(function(id) {
        var c = csGrid.querySelector('[data-id="' + id + '"]');
        if (c) c.classList.add('selected');
      });
      var count = csGrid.querySelectorAll('.cs-pick-card.selected').length;
      document.getElementById('csCount').textContent = count + ' of 3 selected';
      csGrid.querySelectorAll('.cs-pick-card:not(.selected)').forEach(function(c) {
        c.classList.toggle('disabled', count >= 3);
      });
    }
    goTo(1);
  }

  async function silentSave() {
    if (_restoringDraft) return;
    var data = collectFormData();
    var name = (data.companyName || '').trim() || 'Untitled';
    try {
      var res = await fetch('/CVwebapp/api/contracts.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save', id: currentDraftId, name: name, data: data})
      });
      var json = await res.json();
      if (json.ok && json.id) {
        currentDraftId = json.id;
        history.replaceState({}, '', '?id=' + json.id);
      }
    } catch(e) {}
  }

  async function saveDraft() {
    var data = collectFormData();
    var name = (data.companyName || '').trim() || ('Draft ' + new Date().toLocaleDateString('en-IN'));
    try {
      var res = await fetch('/CVwebapp/api/contracts.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'save', id: currentDraftId, name: name, data: data})
      });
      var json = await res.json();
      if (json.ok) { currentDraftId = json.id; history.replaceState({}, '', '?id=' + json.id); showToast('Saved'); }
      else showToast('Save failed');
    } catch(e) { showToast('Save failed'); }
  }

  async function openDraftsPanel() {
    document.getElementById('draftsOverlay').classList.remove('hidden');
    var list = document.getElementById('draftsList');
    list.innerHTML = '<div class="drafts-empty">Loading...</div>';
    try {
      var rows = await fetch('/CVwebapp/api/contracts.php').then(function(r) { return r.json(); });
      if (!Array.isArray(rows) || !rows.length) {
        list.innerHTML = '<div class="drafts-empty">No files saved yet.<br>Hit Save to save your current entries.</div>';
        return;
      }
      list.innerHTML = rows.map(function(r) {
        var dt = new Date(r.updated_at).toLocaleString('en-IN', {dateStyle: 'medium', timeStyle: 'short'});
        return '<div class="draft-item" onclick="loadDraft(' + r.id + ')">' +
          '<div class="draft-item-info">' +
          '<div class="draft-item-name">' + (r.name || 'Untitled') + '</div>' +
          '<div class="draft-item-date">' + dt + '</div>' +
          '</div>' +
          '<button class="draft-delete" title="Delete" onclick="event.stopPropagation();deleteDraft(' + r.id + ',this)">&#x2715;</button>' +
          '</div>';
      }).join('');
    } catch(e) { list.innerHTML = '<div class="drafts-empty">Failed to load files.</div>'; }
  }

  function closeDraftsPanel() {
    document.getElementById('draftsOverlay').classList.add('hidden');
  }

  async function loadDraft(id) {
    try {
      var res = await fetch('/CVwebapp/api/contracts.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'load', id: id})
      });
      var json = await res.json();
      if (!json.ok) { showToast('Failed to load'); return; }
      currentDraftId = json.contract.id;
      history.replaceState({}, '', '?id=' + json.contract.id);
      _restoringDraft = true;
      restoreFormData(json.contract.data);
      _restoringDraft = false;
      closeDraftsPanel();
      showToast('Loaded');
    } catch(e) { showToast('Failed to load'); }
  }

  async function deleteDraft(id, btn) {
    if (!confirm('Delete this file?')) return;
    try {
      var res = await fetch('/CVwebapp/api/contracts.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
      });
      var json = await res.json();
      if (json.ok) {
        btn.closest('.draft-item').remove();
        if (!document.querySelector('.draft-item')) {
          document.getElementById('draftsList').innerHTML = '<div class="drafts-empty">No files saved yet.</div>';
        }
        if (currentDraftId === id) currentDraftId = null;
      }
    } catch(e) { showToast('Delete failed'); }
  }

  function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2200);
  }

  function generateDocument() {
    if (!selectedOutput) { alert('Please select a document type — Proposal or Contract.'); return; }
    syncRTE();
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/CVwebapp/contract_builder/generate.php';
    form.target = '_blank';

    function add(name, value) {
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = name; input.value = value || '';
      form.appendChild(input);
    }
    function radio(name) {
      var el = document.querySelector('input[name="' + name + '"]:checked');
      return el ? el.value : '';
    }

    add('companyName',    document.getElementById('companyName').value);
    add('companyType',    document.getElementById('companyType').value);
    add('cin',            document.getElementById('cin').value);
    add('gst',            document.getElementById('gst').value);
    add('address',        document.getElementById('address').value);
    add('signatoryName',  document.getElementById('signatoryName').value);
    add('designation',    document.getElementById('designation').value);
    add('signatoryEmail', document.getElementById('signatoryEmail').value);
    add('agreementDate',  document.getElementById('agreementDate').value);
    add('bizDescription', document.getElementById('bizDescription').value);
    add('clientSaid',     document.getElementById('clientSaid').value);
    ['briefSalesChips','briefMessagingChips','briefMktStrategyChips','briefStructureChips','briefEngagementChips'].forEach(function(id) {
      var container = document.getElementById(id);
      if (!container) return;
      container.querySelectorAll('.scope-chip.selected').forEach(function(chip) { add('triggers[]', chip.dataset.value); });
    });
    add('engagementType', selectedEng || '');
    add('duration',       document.getElementById('duration').value);
    add('effectiveDate',  document.getElementById('effectiveDate').value);
    add('objective',      document.getElementById('objective').value);
    add('additionalScope',document.getElementById('additionalScope').value);
    document.querySelectorAll('#strategyChips .scope-chip.selected').forEach(function(chip) { add('scope_strategy[]', chip.dataset.value); });
    document.querySelectorAll('#contentChips .scope-chip.selected').forEach(function(chip) { add('scope_content[]', chip.dataset.value); });
    document.querySelectorAll('#opsChips .scope-chip.selected').forEach(function(chip) { add('scope_ops[]', chip.dataset.value); });
    add('cadence',           radio('cadence'));
    add('currency',          radio('currency') || 'INR');
    add('feeType',           radio('feeType'));
    add('monthlyFee',        document.getElementById('monthlyFee').value);
    add('retainerDuration',  document.getElementById('retainerDuration').value);
    add('paymentTerms',      radio('retainerTerms'));
    add('totalFee',          document.getElementById('totalFee').value);
    add('fixedAdvance',      document.getElementById('fixedAdvance').value);
    add('fixedPaymentTerms', radio('fixedTerms'));
    add('milestoneSchedule', document.getElementById('milestoneSchedule').value);
    add('expenses',          radio('expenses'));
    add('paymentNotes',      document.getElementById('paymentNotes').value);
    add('outputType',        selectedOutput);
    var csGrid = document.getElementById('csPickGrid');
    if (csGrid) {
      csGrid.querySelectorAll('.cs-pick-card.selected').forEach(function(c) { add('case_study_ids[]', c.dataset.id); });
    }
    add('msgBody',           document.getElementById('msgBody').value);
    add('senderName',        document.getElementById('senderName').value);
    add('senderTitle',       document.getElementById('senderTitle').value);
    add('senderEmail',       document.getElementById('senderEmail').value);

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
  }

  /* Rich text editors (lazy) */
  var quillClientSaid = null, quillObjective = null;
  var _rteQueue = {};
  var _RTE_TOOLBAR = [['bold', 'italic'], [{ list: 'bullet' }, { list: 'ordered' }]];

  function ensureRTE(step) {
    if (typeof Quill === 'undefined') return;
    try {
      if (step === 2 && !quillClientSaid) {
        quillClientSaid = new Quill('#clientSaidWrap', {
          theme: 'snow',
          placeholder: "A few lines is fine. This will appear in the proposal under 'What we heard from you.'",
          modules: { toolbar: _RTE_TOOLBAR }
        });
        quillClientSaid.on('text-change', syncRTE);
        if ('clientSaid' in _rteQueue) { _applyRTE(quillClientSaid, 'clientSaid', _rteQueue.clientSaid); delete _rteQueue.clientSaid; }
      }
      if (step === 4 && !quillObjective) {
        quillObjective = new Quill('#objectiveWrap', {
          theme: 'snow',
          placeholder: 'What does the client want to achieve?',
          modules: { toolbar: _RTE_TOOLBAR }
        });
        quillObjective.on('text-change', syncRTE);
        if ('objective' in _rteQueue) { _applyRTE(quillObjective, 'objective', _rteQueue.objective); delete _rteQueue.objective; }
      }
    } catch(e) { console.warn('Quill init failed:', e); }
  }

  function _applyRTE(quill, hiddenId, html) {
    if (!html) { quill.setContents([]); return; }
    if (!/<[a-z]/i.test(html)) {
      quill.root.innerHTML = html.split('\n').map(function(l) { return '<p>' + (l || '<br>') + '</p>'; }).join('');
    } else {
      quill.root.innerHTML = html;
    }
    syncRTE();
  }

  function syncRTE() {
    var empty = '<p><br></p>';
    if (quillClientSaid) document.getElementById('clientSaid').value = quillClientSaid.root.innerHTML === empty ? '' : quillClientSaid.root.innerHTML;
    if (quillObjective)  document.getElementById('objective').value  = quillObjective.root.innerHTML  === empty ? '' : quillObjective.root.innerHTML;
  }

  function setRTE(key, html) {
    var quill = key === 'clientSaid' ? quillClientSaid : quillObjective;
    if (quill) {
      _applyRTE(quill, key, html);
    } else {
      _rteQueue[key] = html || '';
      document.getElementById(key).value = html || '';
    }
  }

  // Auto-load draft on page open when ?id= is in the URL
  if (_urlId) {
    (async function() {
      try {
        var res = await fetch('/CVwebapp/api/contracts.php', {
          method: 'POST', headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({action: 'load', id: _urlId})
        });
        var json = await res.json();
        if (json.ok) {
          _restoringDraft = true;
          restoreFormData(json.contract.data);
          _restoringDraft = false;
        } else {
          history.replaceState({}, '', window.location.pathname);
          currentDraftId = null;
        }
      } catch(e) {}
    })();
  }
</script>
</body>
</html>
