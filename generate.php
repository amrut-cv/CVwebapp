<?php
// generate.php — CoreVoice Contract Builder document renderer

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px">
          <h2>Access denied</h2>
          <p>Please use the <a href="index.html">Contract Builder</a> form.</p>
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
    $num = (float)preg_replace('/[^0-9.]/', '', $n);
    if (!$num) return '';
    return $sym . "\u{2009}" . number_format($num);
}

/* ── Collect data ────────────────────────── */
$co          = clean('companyName');
$coType      = clean('companyType');
$cin         = clean('cin');
$gst         = clean('gst');
$address     = clean('address');
$sigName     = clean('signatoryName');
$sigTitle    = clean('designation');
$sigEmail    = clean('signatoryEmail');
$agreeDate   = clean('agreementDate');
$bizDesc     = clean('bizDescription');
$clientSaid  = clean('clientSaid');
$triggers    = cleanArr('triggers');
$engType     = clean('engagementType');
$duration    = clean('duration');
$effDate     = clean('effectiveDate');
$objective   = clean('objective');
$customScope = clean('customScope');
$scopeItems  = cleanArr('scope');
$cadence     = clean('cadence');
$currCode    = clean('currency');
$sym         = ($currCode === 'USD') ? '$' : '₹';
$feeType     = clean('feeType');
$monthlyFee  = clean('monthlyFee');
$retDur      = clean('retainerDuration');
$payTerms    = clean('paymentTerms');
$advance     = clean('advanceAmount');
$totalFee    = clean('totalFee');
$fixedAdv    = clean('fixedAdvance');
$fixPayTerms = clean('fixedPaymentTerms');
$milestones  = clean('milestoneSchedule');
$expenses    = clean('expenses');
$outputType  = clean('outputType');
$msgBody     = clean('msgBody');
$senderName  = clean('senderName');
$senderTitle = clean('senderTitle');
$senderEmail = clean('senderEmail');

$isProposal  = ($outputType !== 'contract');

/* ── Lookup tables ───────────────────────── */
$engLabels = [
    'full-retainer'    => 'Full-Stack Retainer',
    'outcome-retainer' => 'Outcome-Focused Retainer',
    'content-retainer' => 'Content Retainer',
    'new-gtm'          => 'New Product GTM',
    'gtm-relaunch'     => 'GTM Relaunch',
    'fundraising'      => 'Fundraising Comms',
    'sales-video'      => 'Sales Video Series',
    'custom'           => 'Custom Scope',
];

$engDescs = [
    'full-retainer'    => 'An ongoing, multi-functional marketing partnership covering strategy, content, and execution across your growth goals.',
    'outcome-retainer' => 'A time-boxed, goal-specific engagement with defined deliverables, milestones, and success criteria.',
    'content-retainer' => 'Ongoing content production covering calendar planning, creation, editing, and distribution.',
    'new-gtm'          => 'A structured, quarter-long engagement to plan and execute the launch of your product into market.',
    'gtm-relaunch'     => 'A quarter-long brand refresh and market repositioning project to sharpen your competitive edge.',
    'fundraising'      => 'A focused quarter-long engagement to build investor-ready materials and thought leadership ahead of a fundraise.',
    'sales-video'      => 'A project delivering 3–5 polished sales or explainer videos, from scripting through final production.',
    'custom'           => 'A bespoke engagement scoped to your specific requirements and objectives.',
];

$engLabel = $engLabels[$engType] ?? ucwords(str_replace('-', ' ', $engType));
$engDesc  = $engDescs[$engType] ?? '';

$feeTypeLabels = [
    'retainer'  => 'Monthly Retainer',
    'fixed'     => 'Fixed Project Fee',
    'milestone' => 'Milestone-Based',
];
$feeLabel = $feeTypeLabels[$feeType] ?? ucfirst($feeType);

$expensesMap = [
    'included' => 'Out-of-pocket expenses are included within the engagement fee.',
    'actuals'  => 'Out-of-pocket expenses will be billed at actuals with prior written approval.',
    'capped'   => 'Out-of-pocket expenses are subject to a cap agreed in advance.',
];
$expensesText = $expensesMap[$expenses] ?? '';

$cadenceMap   = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly'];
$cadenceLabel = ($cadenceMap[$cadence] ?? ucfirst($cadence)) . ' Review';

/* ── Scope grouping ──────────────────────── */
$strategyAll = ['Brand positioning','Messaging framework','Market research','GTM strategy','Competitive analysis','Audience segmentation','Content strategy','Channel strategy','Campaign planning'];
$contentAll  = ['Social media posts','Long-form articles / blogs','Email newsletters','Thought leadership pieces','Case studies','Website copywriting','Pitch deck copy','Sales collateral','Video scripting','Video production','Design & visual assets','Infographics','White papers','Press releases','Investor materials','Annual report content'];
$opsAll      = ['Campaign execution','Paid media management','SEO & content optimisation','Email marketing','CRM setup & management','Marketing automation','Analytics & reporting','Performance dashboards','Event support','PR & media outreach','Influencer coordination'];

$strategyScope = array_values(array_filter($scopeItems, fn($i) => in_array($i, $strategyAll)));
$contentScope  = array_values(array_filter($scopeItems, fn($i) => in_array($i, $contentAll)));
$opsScope      = array_values(array_filter($scopeItems, fn($i) => in_array($i, $opsAll)));

/* ── Fee block renderer ──────────────────── */
function feeBlock(string $feeType, string $sym, string $monthlyFee, string $retDur,
                  string $payTerms, string $advance, string $totalFee,
                  string $fixedAdv, string $fixPayTerms, string $milestones): string {
    $rows = [];
    switch ($feeType) {
        case 'retainer':
            if ($monthlyFee) $rows[] = ['Monthly fee',    fmtMoney($monthlyFee, $sym)];
            if ($retDur)     $rows[] = ['Duration',       esc($retDur)];
            if ($payTerms)   $rows[] = ['Payment terms',  esc($payTerms)];
            if ($advance)    $rows[] = ['Advance',        fmtMoney($advance, $sym)];
            break;
        case 'fixed':
            if ($totalFee)    $rows[] = ['Total fee',       fmtMoney($totalFee, $sym)];
            if ($fixedAdv)    $rows[] = ['Advance payable', fmtMoney($fixedAdv, $sym)];
            if ($fixPayTerms) $rows[] = ['Payment terms',   esc($fixPayTerms)];
            break;
        case 'milestone':
            if ($milestones) $rows[] = ['Milestone schedule', nl2br(esc($milestones))];
            break;
    }
    if (!$rows) return '<p class="muted">Fee details not specified.</p>';
    $html = '<table class="fee-table">';
    foreach ($rows as [$label, $val]) {
        $html .= "<tr><td class=\"fee-label\">$label</td><td class=\"fee-val\">$val</td></tr>";
    }
    return $html . '</table>';
}

$feeHTML   = feeBlock($feeType, $sym, $monthlyFee, $retDur, $payTerms, $advance,
                       $totalFee, $fixedAdv, $fixPayTerms, $milestones);
$pageTitle = ($isProposal ? 'Marketing Proposal' : 'Marketing Services Agreement')
             . ' — ' . ($co ?: 'Client');
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
    }
    body {
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 10.5pt;
      color: #1c1c2e;
      background: #f0f2f5;
      line-height: 1.7;
    }

    /* ── Page shell ── */
    .page {
      max-width: 800px;
      margin: 32px auto 80px;
      background: #fff;
      box-shadow: 0 4px 32px rgba(0,0,0,.12);
      border-radius: 4px;
      overflow: hidden;
    }

    /* ── Document header ── */
    .doc-header {
      background: var(--brand);
      color: #fff;
      padding: 40px 52px 34px;
    }
    .doc-header .brand      { font-size: 1rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 28px; }
    .doc-header .brand span { color: var(--accent); }
    .doc-header h1          { font-size: 1.9rem; font-weight: 700; line-height: 1.2; margin-bottom: 6px; }
    .doc-header .prepared   { font-size: .88rem; opacity: .65; margin-top: 4px; }
    .doc-header .meta       { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 22px; }
    .doc-header .meta-item  { font-size: .78rem; opacity: .6; }
    .doc-header .meta-item strong { display: block; opacity: 1; font-size: .82rem; margin-bottom: 2px; }

    /* ── Body ── */
    .doc-body { padding: 48px 52px; }

    /* ── Sections ── */
    section, .clause { margin-bottom: 36px; }
    section:last-child, .clause:last-child { margin-bottom: 0; }

    h2 {
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .14em;
      color: var(--accent);
      margin-bottom: 14px;
      padding-bottom: 8px;
      border-bottom: 1.5px solid var(--accent);
    }
    h3 { font-size: .98rem; font-weight: 700; margin-bottom: 6px; color: var(--brand); }
    p  { margin-bottom: 10px; }
    p:last-child { margin-bottom: 0; }
    ul { margin: 8px 0 10px 20px; }
    li { margin-bottom: 4px; }

    /* ── Engagement highlight ── */
    .eng-highlight {
      background: #fdf0f2;
      border-left: 3px solid var(--accent);
      padding: 16px 20px;
      border-radius: 0 4px 4px 0;
      margin-bottom: 14px;
    }
    .eng-highlight .eng-name { font-size: 1.05rem; font-weight: 700; color: var(--brand); }
    .eng-highlight .eng-meta { font-size: .8rem; color: var(--muted); margin-top: 4px; }
    .eng-highlight .eng-body { margin-top: 8px; font-size: .9rem; }

    /* ── Scope columns ── */
    .scope-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 0 32px; margin-top: 16px; }
    .scope-group h4 {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: var(--muted); margin-bottom: 6px;
    }
    .scope-group ul { margin-left: 16px; margin-bottom: 16px; }
    .scope-group li { font-size: .88rem; }

    /* ── Fee table ── */
    .fee-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .fee-table tr { border-bottom: 1px solid var(--border); }
    .fee-table tr:last-child { border-bottom: none; }
    .fee-label { padding: 9px 0; font-size: .87rem; color: var(--muted); width: 180px; vertical-align: top; }
    .fee-val   { padding: 9px 0; font-size: .87rem; font-weight: 600; }

    /* ── Personal note ── */
    .note-box {
      background: #f9fafb;
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 20px 24px;
      font-style: italic;
      white-space: pre-wrap;
      font-size: .9rem;
      line-height: 1.75;
    }
    .note-sender       { margin-top: 16px; font-style: normal; font-size: .84rem; }
    .note-sender strong { display: block; }

    /* ── Legal clause ── */
    .clause-num { font-weight: 700; font-size: .95rem; color: var(--brand); margin-bottom: 8px; }
    .clause p, .clause ul { font-size: .87rem; }

    /* ── Annexure divider ── */
    .ann-divider {
      margin: 48px 0 32px;
      text-align: center;
      border-top: 2px solid var(--border);
      padding-top: 28px;
    }
    .ann-divider h2 { display: inline-block; }

    /* ── Signature block ── */
    .sig-block { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px; }
    .sig-party h4 {
      font-size: .78rem; text-transform: uppercase; letter-spacing: .08em;
      color: var(--muted); margin-bottom: 16px;
    }
    .sig-line { border-top: 1px solid var(--brand); padding-top: 6px; margin-top: 48px; font-size: .82rem; }
    .sig-line strong { display: block; }

    /* ── Utility ── */
    .muted { color: var(--muted); font-size: .85rem; }

    /* ── Doc footer ── */
    .doc-footer {
      background: #f3f4f6;
      border-top: 1px solid var(--border);
      padding: 16px 52px;
      font-size: .73rem;
      color: var(--muted);
      display: flex;
      justify-content: space-between;
    }

    /* ── Print toolbar (hidden when printing) ── */
    .print-bar {
      position: fixed;
      bottom: 24px; right: 24px;
      display: flex; gap: 10px;
      z-index: 100;
    }
    .btn-print {
      background: var(--accent); color: #fff;
      border: none; border-radius: 8px;
      padding: 12px 22px; font-size: .9rem; font-weight: 600;
      cursor: pointer; box-shadow: 0 4px 12px rgba(233,69,96,.35);
      font-family: inherit;
    }
    .btn-print:hover { background: #c73652; }
    .btn-back {
      background: #fff; color: var(--brand);
      border: 1.5px solid var(--border); border-radius: 8px;
      padding: 12px 22px; font-size: .9rem; font-weight: 600;
      cursor: pointer; text-decoration: none; font-family: inherit;
      display: inline-flex; align-items: center;
    }

    /* ── Print styles ── */
    @media print {
      body        { background: #fff; font-size: 10pt; }
      .page       { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
      .print-bar  { display: none; }
      section,
      .clause     { page-break-inside: avoid; }
      .ann-divider { page-break-before: always; }
    }
  </style>
</head>
<body>

<div class="print-bar">
  <button class="btn-back" onclick="window.close()">&#8592; Close</button>
  <button class="btn-print" onclick="window.print()">&#9113; Print / Save PDF</button>
</div>

<div class="page">

  <!-- ── Header ── -->
  <div class="doc-header">
    <div class="brand">Core<span>Voice</span></div>
    <h1><?= $isProposal ? 'Marketing Proposal' : 'Marketing Services Agreement' ?></h1>
    <div class="prepared">Prepared for <?= esc($co ?: '[Client Company]') ?><?= $coType ? ' &mdash; ' . esc($coType) : '' ?></div>
    <div class="meta">
      <?php if ($agreeDate): ?>
        <div class="meta-item"><strong>Date</strong><?= fmtDate($agreeDate) ?></div>
      <?php endif; ?>
      <?php if ($effDate): ?>
        <div class="meta-item"><strong>Effective</strong><?= fmtDate($effDate) ?></div>
      <?php endif; ?>
      <?php if ($engLabel): ?>
        <div class="meta-item"><strong>Engagement</strong><?= esc($engLabel) ?></div>
      <?php endif; ?>
      <?php if ($duration): ?>
        <div class="meta-item"><strong>Duration</strong><?= esc($duration) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Body ── -->
  <div class="doc-body">

  <?php if ($isProposal): ?>
  <!-- ════════════════════════════════════════
       PROPOSAL
  ════════════════════════════════════════ -->

    <?php if ($msgBody): ?>
    <section>
      <h2>A Note From Us</h2>
      <div class="note-box"><?= esc($msgBody) ?></div>
      <?php if ($senderName || $senderTitle || $senderEmail): ?>
      <div class="note-sender">
        <?php if ($senderName):  ?><strong><?= esc($senderName) ?></strong><?php endif; ?>
        <?php if ($senderTitle): ?><?= esc($senderTitle) ?><br><?php endif; ?>
        <?php if ($senderEmail): ?><?= esc($senderEmail) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($bizDesc): ?>
    <section>
      <h2>About <?= esc($co ?: 'Your Business') ?></h2>
      <p><?= escNL($bizDesc) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($clientSaid || $triggers): ?>
    <section>
      <h2>What We Heard</h2>
      <?php if ($clientSaid): ?><p><?= escNL($clientSaid) ?></p><?php endif; ?>
      <?php if ($triggers): ?>
      <ul>
        <?php foreach ($triggers as $t): ?><li><?= esc($t) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($engLabel): ?>
    <section>
      <h2>Recommended Engagement</h2>
      <div class="eng-highlight">
        <div class="eng-name"><?= esc($engLabel) ?></div>
        <?php
          $metaParts = array_filter([$duration, $effDate ? 'from ' . fmtDate($effDate) : '']);
          if ($metaParts):
        ?>
        <div class="eng-meta"><?= implode(' &nbsp;&middot;&nbsp; ', array_map('esc', $metaParts)) ?></div>
        <?php endif; ?>
        <?php if ($engDesc): ?><div class="eng-body"><?= esc($engDesc) ?></div><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($objective || $scopeItems): ?>
    <section>
      <h2>Scope of Work</h2>
      <?php if ($objective): ?>
        <h3>Objective</h3>
        <p><?= escNL($objective) ?></p>
      <?php endif; ?>
      <?php if ($customScope): ?><p><em><?= esc($customScope) ?></em></p><?php endif; ?>
      <?php if ($scopeItems): ?>
        <div class="scope-cols">
          <?php if ($strategyScope): ?>
          <div class="scope-group">
            <h4>Strategy</h4>
            <ul><?php foreach ($strategyScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
          <?php if ($contentScope): ?>
          <div class="scope-group">
            <h4>Content</h4>
            <ul><?php foreach ($contentScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
          <?php if ($opsScope): ?>
          <div class="scope-group">
            <h4>Marketing Ops</h4>
            <ul><?php foreach ($opsScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($cadence): ?>
          <p style="margin-top:14px;" class="muted"><strong>Review cadence:</strong> <?= esc($cadenceLabel) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section>
      <h2>Investment</h2>
      <?php if ($feeLabel): ?><h3><?= esc($feeLabel) ?></h3><?php endif; ?>
      <?= $feeHTML ?>
      <?php if ($expensesText): ?><p class="muted" style="margin-top:14px;"><?= esc($expensesText) ?></p><?php endif; ?>
    </section>

    <section>
      <h2>Next Steps</h2>
      <ul>
        <li>Review this proposal and share any questions or feedback.</li>
        <li>Once aligned, we&rsquo;ll prepare a formal agreement for signatures.</li>
        <li>Upon execution, we&rsquo;ll schedule a kickoff and begin onboarding.</li>
      </ul>
      <?php if ($senderEmail): ?>
        <p style="margin-top:14px;" class="muted">Reach us at <strong><?= esc($senderEmail) ?></strong> — happy to jump on a call anytime.</p>
      <?php endif; ?>
    </section>

  <?php else: ?>
  <!-- ════════════════════════════════════════
       CONTRACT
  ════════════════════════════════════════ -->

    <section>
      <h2>Parties</h2>
      <p>This Marketing Services Agreement (&ldquo;<strong>Agreement</strong>&rdquo;) is entered into as of
        <strong><?= fmtDate($agreeDate) ?: '[Date]' ?></strong> (&ldquo;<strong>Effective Date</strong>&rdquo;) between:</p>
      <p><strong>CoreVoice</strong> (&ldquo;Agency&rdquo;), a marketing services firm; and</p>
      <p>
        <strong><?= esc($co ?: '[Client Company]') ?></strong><?= $coType ? ', a ' . esc($coType) : '' ?><?= $cin ? ', CIN&nbsp;' . esc($cin) : '' ?>,
        having its registered office at <?= esc($address ?: '[Address]') ?>
        (&ldquo;<strong>Client</strong>&rdquo;), represented by <?= esc($sigName ?: '[Signatory]') ?>, <?= esc($sigTitle ?: '[Designation]') ?>.
      </p>
      <p>Agency and Client are individually a &ldquo;Party&rdquo; and collectively the &ldquo;Parties.&rdquo;</p>
    </section>

    <div class="clause">
      <div class="clause-num">1. Services</div>
      <p>Agency agrees to provide marketing services as described in Annexure&nbsp;A (Scope of Work).
        The engagement type is <strong><?= esc($engLabel) ?></strong><?= $duration ? ', for a period of <strong>' . esc($duration) . '</strong>' : '' ?>,
        commencing <?= fmtDate($effDate ?: $agreeDate) ?: 'on the Effective Date' ?>.</p>
      <?php if ($engDesc): ?><p><?= esc($engDesc) ?></p><?php endif; ?>
    </div>

    <div class="clause">
      <div class="clause-num">2. Fees and Payment</div>
      <p>Client agrees to pay Agency in accordance with Annexure&nbsp;B. Fee structure: <strong><?= esc($feeLabel) ?></strong>.</p>
      <?= $feeHTML ?>
      <?php if ($expensesText): ?><p style="margin-top:10px; font-size:.87rem;"><?= esc($expensesText) ?></p><?php endif; ?>
      <p>Invoices are due within the agreed payment terms. Late payments attract interest at 1.5% per month on the outstanding balance.</p>
    </div>

    <div class="clause">
      <div class="clause-num">3. Intellectual Property</div>
      <p>Upon receipt of full payment, Agency assigns to Client all rights, title, and interest in deliverables produced under this Agreement.
        Agency retains the right to reference deliverables in its portfolio unless expressly restricted in writing.</p>
    </div>

    <div class="clause">
      <div class="clause-num">4. Confidentiality</div>
      <p>Each Party shall keep confidential all non-public information received from the other Party and use it solely for purposes
        of this Agreement. This obligation survives termination for two (2) years.</p>
    </div>

    <div class="clause">
      <div class="clause-num">5. Termination</div>
      <p>Either Party may terminate with thirty (30) days&rsquo; written notice. Client shall pay for all work completed and
        expenses incurred through the termination date. Agency may terminate immediately if payment remains outstanding
        for more than fifteen (15) days past its due date.</p>
    </div>

    <div class="clause">
      <div class="clause-num">6. Limitation of Liability</div>
      <p>Neither Party shall be liable for indirect, incidental, consequential, or punitive damages.
        Agency&rsquo;s total liability shall not exceed fees paid by Client in the three (3) months preceding the claim.</p>
    </div>

    <div class="clause">
      <div class="clause-num">7. Independent Contractor</div>
      <p>Agency is an independent contractor. This Agreement creates no employment, partnership, joint venture, or agency
        relationship between the Parties.</p>
    </div>

    <div class="clause">
      <div class="clause-num">8. Governing Law &amp; Jurisdiction</div>
      <p>This Agreement is governed by the laws of India. Disputes are subject to the exclusive jurisdiction of the courts
        of Bengaluru, Karnataka.</p>
    </div>

    <div class="clause">
      <div class="clause-num">9. Entire Agreement</div>
      <p>This Agreement and its Annexures constitute the entire agreement between the Parties and supersede all prior understandings.
        Amendments must be in writing and signed by both Parties.</p>
    </div>

    <!-- Signature block -->
    <section style="margin-top:40px;">
      <h2>Signatures</h2>
      <p class="muted">In witness whereof, the Parties have executed this Agreement as of the date first written above.</p>
      <div class="sig-block">
        <div class="sig-party">
          <h4>For CoreVoice (Agency)</h4>
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

    <!-- ── Annexure A ── -->
    <div class="ann-divider"><h2>Annexure A &mdash; Scope of Work</h2></div>

    <?php if ($objective): ?>
      <p><strong>Objective:</strong> <?= escNL($objective) ?></p>
      <?php if ($customScope): ?><p class="muted" style="margin-top:6px;"><em><?= esc($customScope) ?></em></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($scopeItems): ?>
      <div class="scope-cols">
        <?php if ($strategyScope): ?>
        <div class="scope-group">
          <h4>Strategy</h4>
          <ul><?php foreach ($strategyScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($contentScope): ?>
        <div class="scope-group">
          <h4>Content</h4>
          <ul><?php foreach ($contentScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($opsScope): ?>
        <div class="scope-group">
          <h4>Marketing Ops</h4>
          <ul><?php foreach ($opsScope as $s): ?><li><?= esc($s) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($cadence): ?>
        <p style="margin-top:14px;"><strong>Review cadence:</strong> <?= esc($cadenceLabel) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p class="muted">Scope to be agreed and appended.</p>
    <?php endif; ?>

    <!-- ── Annexure B ── -->
    <div class="ann-divider"><h2>Annexure B &mdash; Fee Schedule</h2></div>

    <?php if ($feeLabel): ?><p><strong>Fee structure:</strong> <?= esc($feeLabel) ?></p><?php endif; ?>
    <div style="margin-top:10px;"><?= $feeHTML ?></div>
    <?php if ($expensesText): ?><p class="muted" style="margin-top:12px;"><?= esc($expensesText) ?></p><?php endif; ?>
    <?php if ($gst): ?>
      <p class="muted" style="margin-top:8px;">GST applicable as per prevailing rates. Client GST: <?= esc($gst) ?>.</p>
    <?php endif; ?>

  <?php endif; ?>

  </div><!-- /doc-body -->

  <div class="doc-footer">
    <span>CoreVoice &mdash; Confidential</span>
    <span><?= fmtDate($agreeDate) ?: date('j F Y') ?></span>
  </div>

</div><!-- /page -->
</body>
</html>
