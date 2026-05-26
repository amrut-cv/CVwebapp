<?php
// generate.php — CoreVoice document renderer (Proposal & Contract)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px">
          <h2>Access denied</h2>
          <p>Use the <a href="index.html">Contract Builder</a> to generate a document.</p>
          </body></html>';
    exit;
}

/* ── Helpers ─────────────────────────────── */
function clean(string $key): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}
function cleanArr(string $key): array {
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) return [];
    return array_values(array_filter(array_map('trim', (array)$_POST[$key])));
}
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function escNL(string $s): string {
    return nl2br(htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}
function fmtDate(string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    return $ts ? date('j F Y', $ts) : esc($d);
}
function fmtMoney(string $n, string $sym): string {
    $num = (float) preg_replace('/[^0-9.]/', '', $n);
    if (!$num) return '';
    return $sym . "\u{202F}" . number_format($num);
}
function listItems(array $items): string {
    if (!$items) return '';
    return '<ul>' . implode('', array_map(fn($i) => '<li>' . esc($i) . '</li>', $items)) . '</ul>';
}

/* ── Collect POST data ───────────────────── */
$co          = clean('companyName');
$coType      = clean('companyType');
$cin         = clean('cin');
$gst         = clean('gst');
$address     = clean('address');
$sigName     = clean('signatoryName');
$sigTitle    = clean('designation');
$sigEmail    = clean('signatoryEmail');
$agreeDate   = clean('agreementDate');
$bizDesc     = clean('bizDescription');   // "builds AI-powered..." — goes in WHEREAS
$clientSaid  = clean('clientSaid');
$triggers    = cleanArr('triggers');
$engType     = clean('engagementType');
$duration    = clean('duration');
$effDate     = clean('effectiveDate');
$objective   = clean('objective');
$customScope = clean('customScope');
$addlScope   = clean('additionalScope');
$scopeItems  = cleanArr('scope');
$cadence     = clean('cadence');
$currCode    = clean('currency');
$sym         = ($currCode === 'USD') ? '$' : '₹';
$feeType     = clean('feeType');
$monthlyFee  = clean('monthlyFee');
$retDur      = clean('retainerDuration');
$payTerms    = clean('paymentTerms');
$totalFee    = clean('totalFee');
$fixedAdv    = clean('fixedAdvance');
$fixPayTerms = clean('fixedPaymentTerms');
$milestones  = clean('milestoneSchedule');
$expenses    = clean('expenses');
$payNotes    = clean('paymentNotes');
$outputType  = clean('outputType');
$msgBody     = clean('msgBody');
$senderName  = clean('senderName');
$senderTitle = clean('senderTitle');
$senderEmail = clean('senderEmail');

$isProposal = ($outputType !== 'contract');

/* ── Engagement lookup ───────────────────── */
$engLabels = [
    'full-retainer'    => 'Full-stack retainer',
    'outcome-retainer' => 'Outcome-focused retainer',
    'content-retainer' => 'Content retainer',
    'new-gtm'          => 'New product GTM',
    'gtm-relaunch'     => 'GTM relaunch',
    'fundraising'      => 'Fundraising comms',
    'sales-video'      => 'Sales video series',
    'custom'           => 'Custom scope',
];
$engDescs = [
    'full-retainer'    => 'Strategy, content, and marketing ops — all three running together on an ongoing basis. We operate as an external marketing team, shared across functions.',
    'outcome-retainer' => 'A time-boxed engagement built around a specific goal. We define the target and the window together, then design and run whatever\'s needed to get there.',
    'content-retainer' => 'Ongoing production of content assets — video, written, founder-led, or AI-optimised. Built to accumulate and compound over time.',
    'new-gtm'          => 'Positioning, identity, website, and a full sales kit — deck, video, technical brochure. Optional press outreach. For companies launching for the first time or after a pivot.',
    'gtm-relaunch'     => 'Visual refresh of existing brand artefacts, updated website, updated sales kit — deck, video, technical brochure. Optional booth redesign and press outreach.',
    'fundraising'      => 'Narrative clean-up, pitch deck, and website tightening for startups heading into a funding round.',
    'sales-video'      => '3–5 videos — product explainers, testimonials, or use-case demos — built specifically for sales enablement.',
    'custom'           => 'Scope defined as agreed between the parties.',
];
$engLabel = $engLabels[$engType] ?? ucwords(str_replace('-', ' ', $engType));
$engDesc  = $engDescs[$engType]  ?? '';

/* ── Fee label ───────────────────────────── */
$feeTypeLabels = [
    'retainer'  => 'Monthly retainer',
    'fixed'     => 'Fixed project fee',
    'milestone' => 'Milestone-based',
];
$feeLabel = $feeTypeLabels[$feeType] ?? '';

/* ── OPE text ────────────────────────────── */
$opeMap = [
    'preapproved'    => 'Out-of-pocket expenses (media spends, travel, printing) are pre-approved in writing and submitted for reimbursement with receipts.',
    'casebycasetools' => 'AI and SaaS tool costs are not reimbursed. Media spends and travel are approved case-by-case in writing prior to being incurred.',
    'none'           => 'No out-of-pocket expenses will be reimbursed. All incidental costs are absorbed within the engagement fee.',
];
$opeText = $opeMap[$expenses] ?? '';

/* ── Cadence label ───────────────────────── */
$cadenceMap   = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly'];
$cadenceLabel = ($cadenceMap[$cadence] ?? ucfirst($cadence)) . ' review';

/* ── Scope grouping ──────────────────────── */
$strategyAll = [
    'Positioning & communication', 'Marketing strategy', 'CMO office — goals & reporting',
    'GTM & launch strategy', 'Customer & competitive research', 'Budget & resource planning',
    'Growth experiment framework', 'Investor narrative framework', 'Content & editorial strategy',
    'Founder personal brand strategy', 'SEO, AEO & AI visibility strategy',
];
$contentAll = [
    'Website design', 'Logo & visual system', 'Brand guide',
    'Social posts — text, image, carousels', 'Reels, shorts & social video', 'Long-form video',
    'SEO blogs & newsletters', 'Ad creatives — static & video', 'Sales decks & presentations',
    'Testimonial & case study videos', 'Product explainers & guides', 'Event & booth collateral',
    'Pitch deck', 'One-pager & exec summary', 'Thought leadership articles', 'AI knowledge hub build',
];
$opsAll = [
    'Social media management', 'Paid ads — Google, Meta, LinkedIn', 'SEO + AEO execution',
    'ABM — account research & outreach', 'Campaign & event execution', 'Event planning & marketing',
    'Community & audience building', 'Stakeholder communications', 'Marketing automation & CRM',
    'Performance tracking & dashboards', 'Reporting & dashboard setup', 'AEO site architecture & schema',
];

$strategyScope = array_values(array_filter($scopeItems, fn($i) => in_array($i, $strategyAll)));
$contentScope  = array_values(array_filter($scopeItems, fn($i) => in_array($i, $contentAll)));
$opsScope      = array_values(array_filter($scopeItems, fn($i) => in_array($i, $opsAll)));

/* ── Fee block renderer ──────────────────── */
function feeRows(string $feeType, string $sym, array $d): array {
    switch ($feeType) {
        case 'retainer':
            $rows = [];
            if ($d['monthlyFee']) $rows[] = ['Monthly fee (excl. GST)', fmtMoney($d['monthlyFee'], $sym)];
            if ($d['retDur'])     $rows[] = ['Duration',                esc($d['retDur'])];
            if ($d['payTerms'])   $rows[] = ['Payment terms',           esc($d['payTerms'])];
            return $rows;
        case 'fixed':
            $rows = [];
            if ($d['totalFee'])    $rows[] = ['Total fixed fee (excl. GST)', fmtMoney($d['totalFee'], $sym)];
            if ($d['fixedAdv'])    $rows[] = ['Advance payable',              fmtMoney($d['fixedAdv'], $sym)];
            if ($d['fixPayTerms']) $rows[] = ['Payment terms',                esc($d['fixPayTerms'])];
            return $rows;
        case 'milestone':
            if ($d['milestones']) return [['Schedule', nl2br(esc($d['milestones']))]];
            return [];
        default:
            return [];
    }
}

$feeData   = compact('monthlyFee', 'retDur', 'payTerms', 'totalFee', 'fixedAdv', 'fixPayTerms', 'milestones');
$feeRowsAr = feeRows($feeType, $sym, $feeData);

function renderFeeTable(array $rows): string {
    if (!$rows) return '<p class="c-muted">Fee details not specified.</p>';
    $html = '<table class="fee-tbl">';
    foreach ($rows as [$label, $val]) {
        $html .= "<tr><td class=\"fee-lbl\">$label</td><td class=\"fee-val\">$val</td></tr>";
    }
    return $html . '</table>';
}

$feeHTML   = renderFeeTable($feeRowsAr);
$pageTitle = ($isProposal ? 'CoreVoice Proposal' : 'CoreVoice Contract') . ' — ' . ($co ?: 'Client');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($pageTitle) ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --brand:  #1a1a2e;
      --accent: #e94560;
      --muted:  #6b7280;
      --border: #d1d5db;
      --bg:     #f0f2f5;
    }
    body {
      font-family: 'Georgia', serif;
      font-size: 10.5pt;
      line-height: 1.72;
      color: #1c1c2e;
      background: var(--bg);
    }

    /* ── Page shell ── */
    .page {
      max-width: 800px;
      margin: 28px auto 80px;
      background: #fff;
      box-shadow: 0 4px 28px rgba(0,0,0,.11);
      border-radius: 4px;
      overflow: hidden;
    }

    /* ── Document header ── */
    .doc-header { background: var(--brand); color: #fff; padding: 38px 52px 32px; }
    .doc-header .brand      { font-family: 'Segoe UI', sans-serif; font-size: .95rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 26px; opacity: .9; }
    .doc-header .brand span { color: var(--accent); }
    .doc-header h1          { font-size: 1.85rem; font-weight: 700; line-height: 1.2; margin-bottom: 5px; }
    .doc-header .prepared   { font-size: .86rem; opacity: .6; }
    .doc-header .meta       { display: flex; flex-wrap: wrap; gap: 26px; margin-top: 22px; }
    .doc-header .m-item     { font-size: .76rem; opacity: .55; }
    .doc-header .m-item strong { display: block; opacity: 1; font-size: .8rem; margin-bottom: 1px; }

    /* ── Body ── */
    .doc-body { padding: 48px 52px; }

    /* ── Sections ── */
    section, .clause { margin-bottom: 36px; }

    h2 {
      font-family: 'Segoe UI', sans-serif;
      font-size: .65rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .15em;
      color: var(--accent);
      margin-bottom: 14px; padding-bottom: 7px;
      border-bottom: 1.5px solid var(--accent);
    }
    h3 { font-size: .96rem; font-weight: 700; color: var(--brand); margin-bottom: 6px; }
    p  { margin-bottom: 10px; }
    p:last-child { margin-bottom: 0; }
    ul { margin: 8px 0 10px 20px; }
    li { margin-bottom: 3px; }
    strong { font-weight: 700; }

    /* ── Engagement highlight ── */
    .eng-box {
      border-left: 3px solid var(--accent);
      background: #fdf0f2;
      padding: 16px 20px;
      border-radius: 0 4px 4px 0;
      margin-bottom: 12px;
    }
    .eng-box .eng-name { font-size: 1.02rem; font-weight: 700; color: var(--brand); }
    .eng-box .eng-meta { font-size: .79rem; color: var(--muted); margin-top: 3px; }
    .eng-box .eng-body { margin-top: 9px; font-size: .9rem; font-style: italic; color: #444; }

    /* ── Scope columns ── */
    .scope-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px; margin-top: 14px; }
    .scope-group { margin-bottom: 14px; }
    .scope-group h4 {
      font-family: 'Segoe UI', sans-serif;
      font-size: .68rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .09em; color: var(--muted); margin-bottom: 5px;
    }
    .scope-group ul { margin-left: 16px; }
    .scope-group li { font-size: .87rem; }

    /* ── Fee table ── */
    .fee-tbl { width: 100%; border-collapse: collapse; }
    .fee-tbl tr { border-bottom: 1px solid var(--border); }
    .fee-tbl tr:last-child { border-bottom: none; }
    .fee-lbl { padding: 9px 0; font-size: .86rem; color: var(--muted); width: 200px; vertical-align: top; }
    .fee-val { padding: 9px 0; font-size: .86rem; font-weight: 600; }

    /* ── Personal note ── */
    .note-box {
      background: #f9fafb;
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 22px 26px;
      white-space: pre-wrap;
      font-size: .92rem;
      line-height: 1.78;
      font-style: normal;
    }
    .sender-block { margin-top: 18px; font-size: .83rem; }
    .sender-block strong { display: block; font-size: .88rem; }

    /* ── Legal clause ── */
    .clause-head { font-weight: 700; font-size: .94rem; color: var(--brand); margin-bottom: 7px; }
    .clause p, .clause ul { font-size: .87rem; }

    /* ── Annexure divider ── */
    .ann-break {
      margin: 48px 0 30px;
      border-top: 2px solid var(--border);
      padding-top: 26px;
      text-align: center;
    }
    .ann-break h2 { display: inline-block; }

    /* ── Signature block ── */
    .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 30px; }
    .sig-party h4 {
      font-family: 'Segoe UI', sans-serif;
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .09em; color: var(--muted); margin-bottom: 14px;
    }
    .sig-line { border-top: 1px solid var(--brand); padding-top: 6px; margin-top: 52px; font-size: .82rem; }
    .sig-line strong { display: block; }

    /* ── Utility ── */
    .c-muted { color: var(--muted); font-size: .85rem; }
    .mt-s    { margin-top: 12px; }

    /* ── Document footer ── */
    .doc-footer {
      background: #f3f4f6; border-top: 1px solid var(--border);
      padding: 15px 52px; font-size: .72rem; color: var(--muted);
      display: flex; justify-content: space-between;
    }

    /* ── Toolbar (hidden on print) ── */
    .toolbar {
      position: fixed; bottom: 24px; right: 24px;
      display: flex; gap: 10px; z-index: 100;
    }
    .btn-print {
      background: var(--accent); color: #fff; border: none;
      border-radius: 8px; padding: 12px 22px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
      box-shadow: 0 4px 14px rgba(233,69,96,.35);
    }
    .btn-print:hover { background: #c73652; }
    .btn-back {
      background: #fff; color: var(--brand); border: 1.5px solid var(--border);
      border-radius: 8px; padding: 12px 22px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
    }

    /* ── Print ── */
    @media print {
      body        { background: #fff; font-size: 10pt; }
      .page       { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
      .toolbar    { display: none; }
      section, .clause { page-break-inside: avoid; }
      .ann-break  { page-break-before: always; }
    }
  </style>
</head>
<body>

<div class="toolbar">
  <button class="btn-back" onclick="window.close()">&#8592; Edit</button>
  <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">

  <!-- ── Header ── -->
  <div class="doc-header">
    <div class="brand">Core<span>Voice</span></div>
    <h1><?= $isProposal ? 'CoreVoice Proposal' : 'CoreVoice Contract' ?></h1>
    <div class="prepared">Prepared for <?= esc($co ?: '[Client]') ?><?= $coType ? ', ' . esc($coType) : '' ?></div>
    <div class="meta">
      <?php if ($agreeDate): ?>
        <div class="m-item"><strong>Date</strong><?= fmtDate($agreeDate) ?></div>
      <?php endif; ?>
      <?php if ($effDate): ?>
        <div class="m-item"><strong>Effective</strong><?= fmtDate($effDate) ?></div>
      <?php endif; ?>
      <?php if ($engLabel): ?>
        <div class="m-item"><strong>Engagement</strong><?= esc($engLabel) ?></div>
      <?php endif; ?>
      <?php if ($duration): ?>
        <div class="m-item"><strong>Duration</strong><?= esc($duration) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="doc-body">

  <?php if ($isProposal): ?>
  <!-- ════════════════════════════════
       PROPOSAL
  ════════════════════════════════ -->

    <?php if ($msgBody): ?>
    <section>
      <div class="note-box"><?= esc($msgBody) ?></div>
      <?php if ($senderName || $senderTitle || $senderEmail): ?>
      <div class="sender-block">
        <?php if ($senderName):  ?><strong><?= esc($senderName) ?></strong><?php endif; ?>
        <?php if ($senderTitle): ?><?= esc($senderTitle) ?><br><?php endif; ?>
        <?php if ($senderEmail): ?><?= esc($senderEmail) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($clientSaid || $triggers): ?>
    <section>
      <h2>What we heard from you</h2>
      <?php if ($clientSaid): ?><p><?= escNL($clientSaid) ?></p><?php endif; ?>
      <?php if ($triggers): ?><?= listItems($triggers) ?><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($engLabel): ?>
    <section>
      <h2>What we&rsquo;d recommend</h2>
      <div class="eng-box">
        <div class="eng-name"><?= esc($engLabel) ?></div>
        <?php
          $meta = array_filter([$duration, $effDate ? 'from ' . fmtDate($effDate) : '']);
          if ($meta): ?>
          <div class="eng-meta"><?= implode(' &nbsp;&middot;&nbsp; ', array_map('esc', $meta)) ?></div>
        <?php endif; ?>
        <?php if ($engDesc): ?><div class="eng-body"><?= esc($engDesc) ?></div><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($objective || $scopeItems): ?>
    <section>
      <h2>What&rsquo;s in scope</h2>
      <?php if ($objective): ?>
        <p><?= escNL($objective) ?></p>
      <?php endif; ?>
      <?php if ($customScope): ?><p class="c-muted"><em><?= esc($customScope) ?></em></p><?php endif; ?>
      <?php if ($scopeItems): ?>
        <div class="scope-cols">
          <?php if ($strategyScope): ?>
          <div class="scope-group"><h4>Strategy</h4><?= listItems($strategyScope) ?></div>
          <?php endif; ?>
          <?php if ($contentScope): ?>
          <div class="scope-group"><h4>Content</h4><?= listItems($contentScope) ?></div>
          <?php endif; ?>
          <?php if ($opsScope): ?>
          <div class="scope-group"><h4>Marketing ops</h4><?= listItems($opsScope) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($cadence): ?>
        <p class="mt-s c-muted"><strong>Governance:</strong> <?= esc($cadenceLabel) ?></p>
      <?php endif; ?>
      <?php if ($addlScope): ?>
        <p class="mt-s c-muted"><em><?= esc($addlScope) ?></em></p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section>
      <h2>What it costs</h2>
      <?php if ($feeLabel): ?><h3><?= esc($feeLabel) ?></h3><?php endif; ?>
      <?= $feeHTML ?>
      <?php if ($opeText): ?><p class="mt-s c-muted"><?= esc($opeText) ?></p><?php endif; ?>
      <?php if ($payNotes): ?><p class="c-muted"><em><?= esc($payNotes) ?></em></p><?php endif; ?>
    </section>

    <section>
      <h2>Next steps</h2>
      <ul>
        <li>Review this proposal and share any questions or feedback.</li>
        <li>Once aligned, we&rsquo;ll send the contract for signatures.</li>
        <li>Upon execution we&rsquo;ll schedule a kickoff and begin immediately.</li>
      </ul>
      <?php if ($senderEmail): ?>
        <p class="mt-s c-muted">Reach us at <strong><?= esc($senderEmail) ?></strong> — happy to jump on a call.</p>
      <?php endif; ?>
    </section>

  <?php else: ?>
  <!-- ════════════════════════════════
       CONTRACT
  ════════════════════════════════ -->

    <section>
      <h2>Parties</h2>
      <p>This Marketing Services Agreement (&ldquo;<strong>Agreement</strong>&rdquo;) is entered into as of
        <strong><?= fmtDate($agreeDate) ?: '[Date]' ?></strong> between:</p>

      <p><strong>Corebook Consulting Pvt Ltd</strong>, trading as <strong>CoreVoice</strong>
        (&ldquo;<strong>Agency</strong>&rdquo;); and</p>

      <p><strong><?= esc($co ?: '[Client Company]') ?></strong><?= $coType ? ', a ' . esc($coType) : '' ?><?= $cin ? ', CIN&nbsp;' . esc($cin) : '' ?>,
        having its registered office at <?= esc($address ?: '[Address]') ?>
        (&ldquo;<strong>Client</strong>&rdquo;), represented by <strong><?= esc($sigName ?: '[Signatory]') ?></strong>,
        <?= esc($sigTitle ?: '[Designation]') ?><?= $sigEmail ? ', ' . esc($sigEmail) : '' ?>.</p>

      <?php if ($bizDesc): ?>
      <p style="margin-top:14px; font-size:.87rem;"><strong>WHEREAS</strong>, the Client <?= esc($bizDesc) ?>;
        and <strong>WHEREAS</strong>, the Client desires to engage Agency for marketing services on the terms set out below;
        <strong>NOW THEREFORE</strong>, the Parties agree as follows.</p>
      <?php endif; ?>
    </section>

    <div class="clause">
      <div class="clause-head">1. &nbsp; Engagement &amp; Services</div>
      <p>Agency agrees to provide marketing services to Client as detailed in <strong>Annexure A</strong> (Scope of Work).
        The engagement type is <strong><?= esc($engLabel) ?></strong><?= $duration ? ', for a period of <strong>' . esc($duration) . '</strong>' : '' ?>,
        commencing <strong><?= fmtDate($effDate ?: $agreeDate) ?: 'on the Effective Date' ?></strong>.</p>
      <?php if ($engDesc): ?><p><?= esc($engDesc) ?></p><?php endif; ?>
    </div>

    <div class="clause">
      <div class="clause-head">2. &nbsp; Fees &amp; Payment</div>
      <p>Client shall pay Agency in accordance with the fee schedule in <strong>Annexure B</strong>.
        Structure: <strong><?= esc($feeLabel) ?></strong>.</p>
      <?= $feeHTML ?>
      <?php if ($opeText): ?><p class="mt-s" style="font-size:.87rem;"><?= esc($opeText) ?></p><?php endif; ?>
      <?php if ($payNotes): ?><p style="font-size:.87rem;"><em><?= esc($payNotes) ?></em></p><?php endif; ?>
      <p style="font-size:.87rem; margin-top:10px;">Invoices not paid within the agreed payment terms shall attract interest at the rate of 1.5% per month on the outstanding balance. All fees are exclusive of applicable taxes (GST or equivalent).</p>
    </div>

    <div class="clause">
      <div class="clause-head">3. &nbsp; Intellectual Property</div>
      <p>Upon receipt of full and final payment, Agency assigns to Client all rights, title, and interest in the deliverables produced exclusively for Client under this Agreement. Agency retains ownership of its pre-existing tools, frameworks, templates, and methodologies. Agency may reference deliverables in its portfolio and case studies unless restricted in writing by Client.</p>
    </div>

    <div class="clause">
      <div class="clause-head">4. &nbsp; Confidentiality</div>
      <p>Each Party shall keep confidential all non-public information (&ldquo;Confidential Information&rdquo;) received from the other and shall not disclose it to third parties without prior written consent. The obligations in this clause and <strong>Annexure C</strong> survive termination for a period of three (3) years.</p>
    </div>

    <div class="clause">
      <div class="clause-head">5. &nbsp; Revisions &amp; Approvals</div>
      <p>Each deliverable includes up to two (2) rounds of revisions unless otherwise specified in Annexure A. Feedback provided beyond that scope will be treated as a change request and quoted separately. Client approval (written or by email) constitutes acceptance of a deliverable.</p>
    </div>

    <div class="clause">
      <div class="clause-head">6. &nbsp; Termination</div>
      <p>Either Party may terminate this Agreement with <strong>thirty (30) days&rsquo;</strong> written notice. Client shall pay for all work completed and expenses incurred through the termination date. Agency may suspend services or terminate immediately if any invoice remains unpaid for more than fifteen (15) days past its due date.</p>
    </div>

    <div class="clause">
      <div class="clause-head">7. &nbsp; Limitation of Liability</div>
      <p>Neither Party shall be liable for indirect, consequential, incidental, or punitive damages. Agency&rsquo;s total aggregate liability under this Agreement shall not exceed the total fees paid by Client in the <strong>three (3) calendar months</strong> immediately preceding the claim.</p>
    </div>

    <div class="clause">
      <div class="clause-head">8. &nbsp; Independent Contractor</div>
      <p>Agency is an independent contractor. This Agreement does not create any employment, partnership, joint venture, or agency relationship between the Parties. Agency personnel remain employees or contractors of Agency at all times.</p>
    </div>

    <div class="clause">
      <div class="clause-head">9. &nbsp; Governing Law &amp; Dispute Resolution</div>
      <p>This Agreement is governed by the laws of India. Any dispute that cannot be resolved amicably within thirty (30) days shall be referred to arbitration under the Arbitration and Conciliation Act, 1996, with a sole arbitrator mutually appointed by the Parties. The seat of arbitration shall be Bengaluru, Karnataka.</p>
    </div>

    <div class="clause">
      <div class="clause-head">10. &nbsp; Entire Agreement</div>
      <p>This Agreement, together with its Annexures, constitutes the entire agreement between the Parties with respect to its subject matter and supersedes all prior discussions, representations, and understandings. Amendments must be in writing and signed by authorised representatives of both Parties.</p>
    </div>

    <!-- Signatures -->
    <section style="margin-top:40px;">
      <h2>Signatures</h2>
      <p class="c-muted" style="font-size:.86rem;">Each Party confirms it has read and agrees to this Agreement as of the date first written above.</p>
      <div class="sig-grid">
        <div class="sig-party">
          <h4>For Corebook Consulting Pvt Ltd (CoreVoice)</h4>
          <div class="sig-line">
            <?php if ($senderName):  ?><strong><?= esc($senderName) ?></strong><?php endif; ?>
            <?php if ($senderTitle): ?><?= esc($senderTitle) ?><br><?php endif; ?>
            <?php if ($senderEmail): ?><?= esc($senderEmail) ?><?php endif; ?>
          </div>
        </div>
        <div class="sig-party">
          <h4>For <?= esc($co ?: 'Client') ?></h4>
          <div class="sig-line">
            <?php if ($sigName):  ?><strong><?= esc($sigName) ?></strong><?php endif; ?>
            <?php if ($sigTitle): ?><?= esc($sigTitle) ?><br><?php endif; ?>
            <?php if ($sigEmail): ?><?= esc($sigEmail) ?><?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ── Annexure A — Scope of Work ── -->
    <div class="ann-break"><h2>Annexure A &mdash; Scope of Work</h2></div>

    <?php if ($objective): ?>
      <p><strong>Objective:</strong> <?= escNL($objective) ?></p>
    <?php endif; ?>
    <?php if ($customScope): ?><p class="c-muted mt-s"><em><?= esc($customScope) ?></em></p><?php endif; ?>

    <?php if ($scopeItems): ?>
      <div class="scope-cols" style="margin-top:16px;">
        <?php if ($strategyScope): ?>
          <div class="scope-group"><h4>Strategy</h4><?= listItems($strategyScope) ?></div>
        <?php endif; ?>
        <?php if ($contentScope): ?>
          <div class="scope-group"><h4>Content</h4><?= listItems($contentScope) ?></div>
        <?php endif; ?>
        <?php if ($opsScope): ?>
          <div class="scope-group"><h4>Marketing ops</h4><?= listItems($opsScope) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($cadence): ?>
        <p class="mt-s"><strong>Governance cadence:</strong> <?= esc($cadenceLabel) ?></p>
      <?php endif; ?>
      <?php if ($addlScope): ?>
        <p class="mt-s c-muted"><em><?= esc($addlScope) ?></em></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="c-muted">Scope to be agreed and appended as a signed addendum.</p>
    <?php endif; ?>

    <!-- ── Annexure B — Fee Schedule ── -->
    <div class="ann-break"><h2>Annexure B &mdash; Fee Schedule</h2></div>

    <?php if ($feeLabel): ?><p><strong>Structure:</strong> <?= esc($feeLabel) ?></p><?php endif; ?>
    <div class="mt-s"><?= $feeHTML ?></div>
    <?php if ($opeText): ?><p class="mt-s c-muted"><?= esc($opeText) ?></p><?php endif; ?>
    <?php if ($payNotes): ?><p class="c-muted"><em><?= esc($payNotes) ?></em></p><?php endif; ?>
    <?php if ($gst): ?>
      <p class="mt-s c-muted">GST applicable at prevailing rates. Client GSTIN: <?= esc($gst) ?>.</p>
    <?php endif; ?>

    <!-- ── Annexure C — Non-Disclosure Agreement ── -->
    <div class="ann-break"><h2>Annexure C &mdash; Non-Disclosure Agreement</h2></div>

    <p>This Non-Disclosure Agreement (&ldquo;<strong>NDA</strong>&rdquo;) forms part of and is incorporated into the Marketing Services Agreement between <strong>Corebook Consulting Pvt Ltd (CoreVoice)</strong> (&ldquo;Agency&rdquo;) and <strong><?= esc($co ?: '[Client]') ?></strong> (&ldquo;Client&rdquo;) dated <?= fmtDate($agreeDate) ?: '[Date]' ?>.</p>

    <div class="clause" style="margin-top:20px;">
      <div class="clause-head">C.1 &nbsp; Definition of Confidential Information</div>
      <p>&ldquo;Confidential Information&rdquo; means any non-public information disclosed by one Party (&ldquo;Disclosing Party&rdquo;) to the other (&ldquo;Receiving Party&rdquo;) — whether oral, written, digital, or visual — that is designated as confidential or that reasonably should be understood to be confidential given the nature of the information and the circumstances of disclosure. This includes, without limitation: business plans, financial data, pricing, client lists, product roadmaps, marketing strategies, creative briefs, campaign data, and trade secrets.</p>
    </div>

    <div class="clause">
      <div class="clause-head">C.2 &nbsp; Obligations of the Receiving Party</div>
      <p>The Receiving Party shall: (a) hold Confidential Information in strict confidence using at least the same degree of care it uses for its own confidential information, but no less than reasonable care; (b) not disclose Confidential Information to any third party without prior written consent of the Disclosing Party; (c) use Confidential Information solely to perform obligations or exercise rights under the Agreement; and (d) limit access to Confidential Information to those employees, contractors, and advisors who have a need to know and are bound by confidentiality obligations no less protective than those herein.</p>
    </div>

    <div class="clause">
      <div class="clause-head">C.3 &nbsp; Exclusions</div>
      <p>Obligations under this NDA do not apply to information that: (a) is or becomes publicly known through no breach by the Receiving Party; (b) was rightfully known to the Receiving Party before disclosure; (c) is received from a third party without restriction; or (d) is independently developed by the Receiving Party without reference to the Confidential Information. Disclosure required by law or court order is permitted provided the Receiving Party gives prompt written notice to the Disclosing Party (where legally permitted) and cooperates in seeking a protective order.</p>
    </div>

    <div class="clause">
      <div class="clause-head">C.4 &nbsp; Term &amp; Return of Information</div>
      <p>Obligations under this NDA commence on the Effective Date and continue for <strong>three (3) years</strong> following termination or expiry of the Agreement. Upon written request by the Disclosing Party, the Receiving Party shall promptly return or destroy all Confidential Information (including copies) and certify destruction in writing.</p>
    </div>

    <div class="clause">
      <div class="clause-head">C.5 &nbsp; Remedies</div>
      <p>The Parties acknowledge that breach of this NDA may cause irreparable harm for which monetary damages would be an inadequate remedy. The Disclosing Party shall be entitled to seek injunctive or other equitable relief in addition to any other remedies available at law, without the requirement to post a bond or other security.</p>
    </div>

  <?php endif; ?>

  </div><!-- /doc-body -->

  <div class="doc-footer">
    <span>CoreVoice &middot; Corebook Consulting Pvt Ltd &middot; Confidential</span>
    <span><?= fmtDate($agreeDate) ?: date('j F Y') ?></span>
  </div>

</div><!-- /page -->
</body>
</html>
