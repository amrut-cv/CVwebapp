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

/* ─────────────────────────────── helpers ── */
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
    return $ts ? date('j F Y', $ts) : $d;
}
function fmtIndian(string $n): string {
    $num = (int) preg_replace('/[^0-9]/', '', $n);
    if (!$num) return '';
    $str  = (string) $num;
    $len  = strlen($str);
    if ($len <= 3) return $str;
    $last3  = substr($str, -3);
    $rest   = substr($str, 0, $len - 3);
    $result = $last3;
    while (strlen($rest) > 0) {
        $take   = min(2, strlen($rest));
        $chunk  = substr($rest, -$take);
        $rest   = substr($rest, 0, strlen($rest) - $take);
        $result = $chunk . ',' . $result;
    }
    return $result;
}
function fmtMoney(string $n, string $currency): string {
    if (!$n) return '';
    if ($currency === 'INR') return 'Rs.&nbsp;' . fmtIndian($n);
    $num = (float) preg_replace('/[^0-9.]/', '', $n);
    return '$&nbsp;' . number_format($num);
}

/* ─────────────────────────────── POST data ── */
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
$addlScope   = clean('additionalScope');
$scopeItems  = cleanArr('scope');
$cadence     = clean('cadence');
$currCode    = clean('currency') ?: 'INR';
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

/* ─────────────────────────────── engagement ── */
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
$engRationale = [
    'full-retainer'    => 'Based on what you\'ve shared, a full-stack retainer makes the most sense. You need consistent output across strategy, content, and execution — not a one-time project. We\'d operate as your marketing function, with clear goals, a shared calendar, and regular governance to make sure the work stays aligned with where the business is going.',
    'outcome-retainer' => 'What you\'ve described is a time-boxed problem, not a forever engagement. An outcome-focused retainer lets us define the goal together, set a window, and deploy whatever\'s needed to get there — then reassess. No lock-in beyond what the goal requires.',
    'content-retainer' => 'Your content engine isn\'t running at the level it needs to be. A content retainer gives you consistent, quality output that builds over time — compounding rather than campaign-based. Volume plus consistency is what moves the needle.',
    'new-gtm'          => 'You\'re entering the market fresh — or after a meaningful pivot. That means you need positioning, identity, and a full sales kit before anything else. We\'ll build the foundation so that every subsequent marketing activity has something real to stand on.',
    'gtm-relaunch'     => 'You already have something in the market, but it\'s not landing the way it should. A relaunch isn\'t about starting over — it\'s about updating what exists to reflect where the company actually is now.',
    'fundraising'      => 'When you\'re heading into a round, the narrative has to do the work before you even get in the room. We\'ll clean up your positioning, tighten the deck, and make sure the website backs up the story you\'re telling investors.',
    'sales-video'      => 'Video is the most efficient format for a complex product or a crowded market. A short series of well-made videos — explainers, use cases, testimonials — gives your sales team something that travels across every channel and conversation.',
    'custom'           => 'The scope here has been defined specifically for this engagement, based on what you\'ve described and what we believe will move the needle. We\'ll work from this as our starting point and adjust as we go.',
];
$engLabel     = $engLabels[$engType]     ?? ucwords(str_replace('-', ' ', $engType));
$engDesc      = $engDescs[$engType]      ?? '';
$engRationale = $engRationale[$engType]  ?? '';

/* ─────────────────────────────── scope groups ── */
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

/* ─────────────────────────────── fee display ── */
// Retainer display fee line
function feeDisplay(string $feeType, string $currCode, string $monthlyFee, string $retDur,
                    string $totalFee, string $fixedAdv, string $milestones): string {
    switch ($feeType) {
        case 'retainer':
            $parts = [];
            if ($monthlyFee) $parts[] = fmtMoney($monthlyFee, $currCode) . ' + ' . ($currCode === 'INR' ? 'GST' : 'tax');
            $parts[] = 'per month';
            if ($retDur) $parts[] = $retDur;
            return implode(' &middot; ', $parts);
        case 'fixed':
            $parts = [];
            if ($totalFee) $parts[] = fmtMoney($totalFee, $currCode) . ' + ' . ($currCode === 'INR' ? 'GST' : 'tax');
            $parts[] = 'fixed fee';
            if ($fixedAdv) $parts[] = fmtMoney($fixedAdv, $currCode) . ' advance';
            return implode(' &middot; ', $parts);
        case 'milestone':
            return $milestones ? nl2br(esc($milestones)) : '';
        default:
            return '';
    }
}
$feeDisplayStr = feeDisplay($feeType, $currCode, $monthlyFee, $retDur, $totalFee, $fixedAdv, $milestones);

// Payment sub-line
$payDaysMap = ['Net 15' => '15 days', 'Net 30' => '30 days', 'Advance' => 'advance'];
$payDays    = $payDaysMap[$payTerms] ?? ($payTerms ?: '15 days');
$fixPayDaysMap = ['Net 15' => '15 days', 'Net 30' => '30 days'];
$fixPayDays    = $fixPayDaysMap[$fixPayTerms] ?? ($fixPayTerms ?: '15 days');

// OPE sub-text
$opeSubMap = [
    'preapproved'      => 'Out-of-pocket expenses pre-approved and reimbursed separately',
    'casebycasetools'  => 'AI/SaaS tools not reimbursed; media/travel pre-approved case-by-case',
    'none'             => 'No OPE reimbursement',
];
$opeSub = $opeSubMap[$expenses] ?? '';

// OPE full text for Annexure B
$opeFullMap = [
    'preapproved'      => 'reimbursed after pre-approval in writing with receipts attached to invoice',
    'casebycasetools'  => 'AI and SaaS tool costs are not reimbursed; media spends and travel are approved case-by-case in writing prior to being incurred',
    'none'             => 'not reimbursed; all incidental costs are absorbed within the engagement fee',
];
$opeFull = $opeFullMap[$expenses] ?? '';

// Payment sub-line for proposal investment section
$investSubParts = [];
if ($feeType === 'retainer') {
    $investSubParts[] = 'Invoiced on the 1st of each month';
    $investSubParts[] = 'Payable within ' . $payDays;
} elseif ($feeType === 'fixed') {
    $investSubParts[] = 'Payable within ' . $fixPayDays;
} elseif ($feeType === 'milestone') {
    $investSubParts[] = 'Payable per milestone schedule';
}
if ($opeSub) $investSubParts[] = $opeSub;
$investSub = implode(' &middot; ', $investSubParts);

/* ─────────────────────────────── company type legal desc ── */
function coTypeLegal(string $t): string {
    switch ($t) {
        case 'Private Limited': return 'a company incorporated under the Companies Act, 2013';
        case 'LLP':             return 'a limited liability partnership registered under the Limited Liability Partnership Act, 2008';
        case 'Inc (Delaware)':  return 'a corporation incorporated under the laws of the State of Delaware, USA';
        case 'Ltd (UK)':        return 'a company incorporated under the Companies Act 2006, United Kingdom';
        default:                return $t ? 'a ' . $t : '';
    }
}

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

    body {
      font-family: 'Georgia', 'Times New Roman', serif;
      font-size: 10.5pt;
      line-height: 1.75;
      color: #1c1c2e;
      background: #f0f2f5;
    }

    /* ── Shell ── */
    .page {
      max-width: 760px;
      margin: 32px auto 80px;
      background: #fff;
      box-shadow: 0 4px 32px rgba(0,0,0,.10);
      border-radius: 3px;
    }

    /* ── Toolbar (hidden on print) ── */
    .toolbar {
      position: fixed; bottom: 24px; right: 24px;
      display: flex; gap: 10px; z-index: 100;
    }
    .btn-print {
      background: #1a1a2e; color: #fff; border: none;
      border-radius: 8px; padding: 12px 24px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
      letter-spacing: .03em;
    }
    .btn-print:hover { background: #2d2d4e; }
    .btn-back {
      background: #fff; color: #1a1a2e; border: 1.5px solid #d1d5db;
      border-radius: 8px; padding: 12px 24px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
    }

    /* ════════════════════════════════════
       PROPOSAL STYLES
    ════════════════════════════════════ */
    .prop-hero {
      padding: 56px 56px 40px;
      border-bottom: 1px solid #e8e8f0;
    }
    .prop-tag {
      font-family: 'Segoe UI', sans-serif;
      font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .2em;
      color: #C9972A; margin-bottom: 20px;
    }
    .prop-h1 {
      font-family: 'Georgia', serif;
      font-size: 2.15rem; font-weight: 700;
      line-height: 1.18; color: #1a1a2e;
      margin-bottom: 18px;
    }
    .prop-h1 em { font-style: italic; color: #C9972A; }
    .prop-meta {
      font-family: 'Segoe UI', sans-serif;
      font-size: .83rem; color: #6b7280;
      letter-spacing: .01em;
    }
    .prop-meta .arrow { color: #C9972A; margin: 0 4px; }
    .prop-meta .dot   { margin: 0 8px; opacity: .4; }
    .prop-hr { border: none; border-top: 1.5px solid #e8e8f0; margin: 0; }

    /* Proposal sections */
    .prop-body { padding: 0 56px 56px; }

    .prop-section { padding-top: 48px; }
    .prop-section + .prop-section { border-top: 1px solid #f0f0f5; }

    .sec-label {
      font-family: 'Segoe UI', sans-serif;
      font-size: .62rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .2em;
      color: #9ca3af; margin-bottom: 6px;
    }
    .sec-title {
      font-family: 'Georgia', serif;
      font-size: 1.28rem; font-weight: 700;
      color: #1a1a2e; margin-bottom: 24px;
    }

    /* Note / personal message */
    .note-card {
      border-left: 3px solid #C9972A;
      padding: 22px 26px;
      background: #fffdf7;
      border-radius: 0 4px 4px 0;
      font-size: .93rem; line-height: 1.82;
      white-space: pre-wrap;
      color: #2a2a3e;
    }
    .note-sender {
      margin-top: 20px; padding-top: 16px;
      border-top: 1px solid #e8e8f0;
      font-family: 'Segoe UI', sans-serif;
      font-size: .83rem; color: #6b7280;
    }
    .note-sender strong { display: block; color: #1a1a2e; font-size: .88rem; margin-bottom: 1px; }

    /* What we heard */
    .heard-quote {
      font-style: italic; color: #3a3a5e;
      font-size: .96rem; line-height: 1.82;
      margin-bottom: 20px;
    }
    .trigger-list { list-style: none; display: flex; flex-wrap: wrap; gap: 8px; }
    .trigger-list li {
      background: #f4f4f8; border-radius: 20px;
      padding: 5px 14px; font-family: 'Segoe UI', sans-serif;
      font-size: .78rem; color: #1a1a2e;
    }

    /* Recommendation */
    .rec-card {
      border: 1px solid #e8e8f0; border-radius: 8px;
      padding: 24px 28px; margin-bottom: 0;
    }
    .rec-badge {
      display: inline-block;
      background: #1a1a2e; color: #fff;
      font-family: 'Segoe UI', sans-serif;
      font-size: .62rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      padding: 3px 10px; border-radius: 3px;
      margin-bottom: 14px;
    }
    .rec-name { font-size: 1.12rem; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
    .rec-desc { font-size: .9rem; color: #4a4a6a; line-height: 1.7; margin-bottom: 20px; }
    .rec-divider { border: none; border-top: 1px solid #e8e8f0; margin: 0 0 20px; }
    .rec-rationale { font-style: italic; color: #5a5a7a; font-size: .88rem; line-height: 1.78; }

    /* Scope */
    .scope-obj { color: #3a3a5e; font-size: .93rem; margin-bottom: 24px; line-height: 1.75; }
    .scope-categories { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 24px; margin-bottom: 20px; }
    .scope-cat-head {
      font-family: 'Segoe UI', sans-serif;
      font-size: .63rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em;
      margin-bottom: 10px; padding-bottom: 6px;
      border-bottom: 2px solid;
    }
    .scope-cat-head.strategy { color: #1a1a2e; border-color: #1a1a2e; }
    .scope-cat-head.content  { color: #0d7a72; border-color: #0d9488; }
    .scope-cat-head.ops      { color: #b45309; border-color: #D97706; }
    .scope-cat ul { list-style: none; padding: 0; }
    .scope-cat li { font-size: .82rem; color: #3a3a5e; padding: 3px 0; border-bottom: 1px solid #f4f4f8; }
    .scope-cat li:last-child { border-bottom: none; }
    .scope-note {
      font-style: italic; font-size: .84rem; color: #6b7280; margin-bottom: 8px;
    }
    .scope-cycle {
      font-size: .84rem; color: #6b7280;
    }

    /* Relevant work (case studies) */
    .cases { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    .case-card {
      border: 1px solid #e8e8f0; border-radius: 6px; padding: 18px 18px 16px;
    }
    .case-name {
      font-family: 'Segoe UI', sans-serif;
      font-size: .78rem; font-weight: 700;
      color: #1a1a2e; text-transform: uppercase; letter-spacing: .06em;
      margin-bottom: 4px;
    }
    .case-type {
      font-family: 'Segoe UI', sans-serif;
      font-size: .72rem; color: #C9972A; margin-bottom: 10px;
    }
    .case-desc { font-size: .8rem; color: #5a5a7a; line-height: 1.65; }

    /* Investment */
    .invest-big {
      font-family: 'Georgia', serif;
      font-size: 1.8rem; font-weight: 700;
      color: #1a1a2e; margin-bottom: 10px; line-height: 1.2;
    }
    .invest-sub {
      font-family: 'Segoe UI', sans-serif;
      font-size: .82rem; color: #6b7280;
      margin-bottom: 18px; line-height: 1.6;
    }
    .invest-note {
      font-size: .86rem; color: #4a4a6a; line-height: 1.72;
      padding: 14px 18px; background: #f9fafb;
      border-radius: 4px; border: 1px solid #e8e8f0;
    }

    /* Next steps */
    .steps-list { list-style: none; }
    .steps-list li {
      display: flex; align-items: flex-start; gap: 18px;
      padding: 14px 0; border-bottom: 1px solid #f0f0f5;
      font-size: .9rem; color: #2a2a3e;
    }
    .steps-list li:last-child { border-bottom: none; }
    .step-num {
      font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700;
      background: #1a1a2e; color: #fff;
      width: 22px; height: 22px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; margin-top: 1px;
    }

    /* Proposal footer */
    .prop-footer {
      border-top: 1px solid #e8e8f0;
      padding: 20px 56px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .prop-footer-logo {
      font-family: 'Segoe UI', sans-serif;
      font-size: .88rem; font-weight: 700; letter-spacing: .03em;
    }
    .prop-footer-logo .cv { color: #1a1a2e; }
    .prop-footer-logo .voice { color: #C9972A; }
    .prop-footer-contact {
      font-family: 'Segoe UI', sans-serif;
      font-size: .75rem; color: #9ca3af;
    }

    /* ════════════════════════════════════
       CONTRACT STYLES
    ════════════════════════════════════ */
    .con-body { padding: 52px 56px; }

    .con-logo-center {
      text-align: center; margin-bottom: 28px;
      font-family: 'Segoe UI', sans-serif;
      font-size: 1.3rem; font-weight: 700; letter-spacing: .04em;
    }
    .con-logo-center .cv    { color: #1a1a2e; }
    .con-logo-center .voice { color: #C9972A; }

    .con-title {
      text-align: center;
      font-family: 'Georgia', serif;
      font-size: 1.08rem; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      color: #1a1a2e; margin-bottom: 8px;
    }
    .con-confidential {
      text-align: center;
      font-family: 'Segoe UI', sans-serif;
      font-size: .72rem; font-weight: 700;
      letter-spacing: .18em; text-transform: uppercase;
      color: #9ca3af; margin-bottom: 36px;
    }
    .con-intro { font-size: .9rem; margin-bottom: 28px; line-height: 1.78; }

    /* Parties block */
    .con-party-block {
      background: #f9fafb; border: 1px solid #e2e8f0;
      border-radius: 6px; padding: 22px 24px; margin-bottom: 28px;
    }
    .con-party { margin-bottom: 16px; font-size: .88rem; line-height: 1.78; }
    .con-party:last-child { margin-bottom: 0; }
    .con-and {
      font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #9ca3af; margin: 12px 0;
    }

    /* Effective line */
    .con-effective {
      font-size: .9rem; margin-bottom: 28px;
      padding: 12px 18px;
      border-left: 3px solid #C9972A;
      background: #fffdf7;
    }

    /* Whereas */
    .con-whereas { margin-bottom: 32px; }
    .con-whereas-head {
      font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em;
      color: #9ca3af; margin-bottom: 12px;
    }
    .con-whereas ol { padding-left: 20px; font-size: .88rem; line-height: 1.78; }
    .con-whereas li { margin-bottom: 8px; }

    /* Points of Agreement */
    .con-poa-head {
      font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em;
      color: #9ca3af; margin-bottom: 20px;
    }

    /* Clauses */
    .clause { margin-bottom: 28px; }
    .clause-head {
      font-family: 'Segoe UI', sans-serif;
      font-weight: 700; font-size: .9rem;
      color: #1a1a2e; margin-bottom: 8px;
    }
    .clause p, .clause ol, .clause ul {
      font-size: .86rem; line-height: 1.78; margin-bottom: 8px;
    }
    .clause ol { padding-left: 20px; }
    .clause ul { padding-left: 20px; list-style: disc; }
    .clause li { margin-bottom: 5px; }
    .clause p:last-child { margin-bottom: 0; }

    /* Signatures */
    .sig-intro { font-size: .86rem; color: #4a4a6a; margin-bottom: 28px; }
    .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 36px; }
    .sig-party-label {
      font-family: 'Segoe UI', sans-serif;
      font-size: .66rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .1em;
      color: #9ca3af; margin-bottom: 10px;
    }
    .sig-name   { font-weight: 700; font-size: .88rem; }
    .sig-detail { font-size: .82rem; color: #6b7280; }
    .sig-line-box {
      border-top: 1px solid #1a1a2e; padding-top: 6px; margin-top: 40px;
      font-size: .82rem;
    }

    /* Client details table */
    .client-table { width: 100%; border-collapse: collapse; font-size: .86rem; margin-bottom: 32px; }
    .client-table td { padding: 8px 12px; border: 1px solid #e2e8f0; vertical-align: top; }
    .client-table td:first-child {
      width: 38%; font-weight: 600; color: #6b7280;
      background: #f9fafb; white-space: nowrap;
    }

    /* Annexure headers */
    .ann-header {
      text-align: center; padding: 28px 0 20px;
      border-top: 2px solid #e2e8f0;
      margin: 48px 0 28px;
    }
    .ann-tag {
      font-family: 'Segoe UI', sans-serif;
      font-size: .62rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .18em;
      color: #9ca3af; margin-bottom: 4px;
    }
    .ann-title {
      font-family: 'Georgia', serif;
      font-size: 1.05rem; font-weight: 700;
      color: #1a1a2e;
    }

    /* Annexure A — Scope */
    .ann-eng-type {
      display: inline-block;
      background: #1a1a2e; color: #fff;
      font-family: 'Segoe UI', sans-serif;
      font-size: .62rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .1em;
      padding: 3px 10px; border-radius: 3px;
      margin-bottom: 14px;
    }
    .ann-scope-obj { font-size: .88rem; margin-bottom: 20px; color: #3a3a5e; line-height: 1.75; }
    .ann-scope-cats { margin-bottom: 20px; }
    .ann-scope-cats h4 {
      font-family: 'Segoe UI', sans-serif;
      font-size: .66rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #9ca3af; margin: 16px 0 6px;
    }
    .ann-scope-cats ul { list-style: disc; padding-left: 20px; }
    .ann-scope-cats li { font-size: .86rem; margin-bottom: 3px; }
    .ann-addl { font-size: .86rem; color: #4a4a6a; margin-bottom: 16px; }

    .gov-section { margin-top: 20px; }
    .gov-head {
      font-family: 'Segoe UI', sans-serif;
      font-size: .66rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #9ca3af; margin-bottom: 8px;
    }
    .gov-list { list-style: disc; padding-left: 20px; font-size: .86rem; }
    .gov-list li { margin-bottom: 5px; }
    .gov-note { font-size: .82rem; color: #6b7280; font-style: italic; margin-top: 12px; }

    /* Annexure B — Fee */
    .fee-terms { font-size: .88rem; line-height: 1.78; margin-bottom: 20px; }
    .fee-terms p { margin-bottom: 8px; }
    .bank-table { width: 100%; border-collapse: collapse; font-size: .86rem; margin-top: 20px; }
    .bank-table caption {
      font-family: 'Segoe UI', sans-serif;
      font-size: .66rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #9ca3af; text-align: left; padding-bottom: 8px;
    }
    .bank-table td { padding: 8px 12px; border: 1px solid #e2e8f0; vertical-align: top; }
    .bank-table td:first-child {
      width: 38%; font-weight: 600; color: #6b7280;
      background: #f9fafb; white-space: nowrap;
    }

    /* Annexure C — NDA */
    .nda-section { margin-bottom: 22px; }
    .nda-head {
      font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #1a1a2e; margin-bottom: 8px;
    }
    .nda-body { font-size: .86rem; line-height: 1.78; }
    .nda-body ol { padding-left: 20px; }
    .nda-body li { margin-bottom: 6px; }

    /* ── Print ── */
    @media print {
      body     { background: #fff; font-size: 9.5pt; }
      .page    { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
      .toolbar { display: none !important; }
      .ann-header { page-break-before: always; }
      .clause, .nda-section, .case-card { page-break-inside: avoid; }
    }
  </style>
</head>
<body>

<div class="toolbar">
  <button class="btn-back"  onclick="window.close()">&#8592; Edit</button>
  <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="page">

<?php if ($isProposal): ?>
<!-- ════════════════════════════════════════════
     PROPOSAL
════════════════════════════════════════════ -->

  <!-- Hero -->
  <div class="prop-hero">
    <div class="prop-tag">Proposal for <?= esc($co ?: '[Company]') ?></div>
    <h1 class="prop-h1">Here&rsquo;s how we&rsquo;d work <em>together</em></h1>
    <div class="prop-meta">
      <span>CoreVoice</span>
      <span class="arrow">→</span>
      <span><?= esc($co ?: '[Company]') ?></span>
      <?php if ($agreeDate): ?>
        <span class="dot">&middot;</span>
        <span><?= fmtDate($agreeDate) ?></span>
      <?php endif; ?>
      <?php if ($duration): ?>
        <span class="dot">&middot;</span>
        <span><?= esc($duration) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <hr class="prop-hr" />

  <div class="prop-body">

    <!-- Personal note -->
    <?php if ($msgBody): ?>
    <div class="prop-section">
      <div class="note-card"><?= esc($msgBody) ?></div>
      <?php if ($senderName || $senderTitle || $senderEmail): ?>
      <div class="note-sender">
        <?php if ($senderName):  ?><strong><?= esc($senderName) ?></strong><?php endif; ?>
        <?php if ($senderTitle): ?><?= esc($senderTitle) ?><br><?php endif; ?>
        <?php if ($senderEmail): ?><?= esc($senderEmail) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- What we heard -->
    <?php if ($clientSaid || $triggers): ?>
    <div class="prop-section">
      <div class="sec-label">What we heard from you</div>
      <div class="sec-title">What brought you here</div>
      <?php if ($clientSaid): ?>
        <div class="heard-quote"><?= escNL($clientSaid) ?></div>
      <?php endif; ?>
      <?php if ($triggers): ?>
        <ul class="trigger-list">
          <?php foreach ($triggers as $t): ?>
            <li><?= esc($t) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Our recommendation -->
    <?php if ($engType): ?>
    <div class="prop-section">
      <div class="sec-label">Our recommendation</div>
      <div class="sec-title">What we&rsquo;d suggest, and why</div>
      <div class="rec-card">
        <div class="rec-badge"><?= esc(strtoupper($engLabel)) ?></div>
        <div class="rec-name"><?= esc($engLabel) ?></div>
        <?php if ($engDesc): ?>
          <div class="rec-desc"><?= esc($engDesc) ?></div>
        <?php endif; ?>
        <hr class="rec-divider" />
        <?php if ($engRationale): ?>
          <div class="rec-rationale"><?= esc($engRationale) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Scope of work -->
    <?php if ($scopeItems || $objective): ?>
    <div class="prop-section">
      <div class="sec-label">Scope of work</div>
      <div class="sec-title">What we&rsquo;ll do</div>
      <?php if ($objective): ?>
        <div class="scope-obj"><?= escNL($objective) ?></div>
      <?php endif; ?>
      <?php if ($scopeItems): ?>
      <div class="scope-categories">
        <?php if ($strategyScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head strategy">Strategy</div>
          <ul>
            <?php foreach ($strategyScope as $item): ?>
              <li><?= esc($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ($contentScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head content">Content</div>
          <ul>
            <?php foreach ($contentScope as $item): ?>
              <li><?= esc($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ($opsScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head ops">Marketing ops</div>
          <ul>
            <?php foreach ($opsScope as $item): ?>
              <li><?= esc($item) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($addlScope): ?>
        <div class="scope-note"><?= esc($addlScope) ?></div>
      <?php endif; ?>
      <div class="scope-cycle">Scope is reviewed and adjusted at each governance cycle based on what&rsquo;s working and what the business needs.</div>
    </div>
    <?php endif; ?>

    <!-- Relevant work -->
    <div class="prop-section">
      <div class="sec-label">Relevant work</div>
      <div class="sec-title">Who we&rsquo;ve done this for</div>
      <p style="font-size:.86rem;color:#6b7280;margin-bottom:20px;">A few engagements similar to what we&rsquo;re proposing here.</p>
      <div class="cases">
        <div class="case-card">
          <div class="case-name">GalaxEye</div>
          <div class="case-type">Full-stack retainer</div>
          <div class="case-desc">Built their entire marketing function — brand, website, social, sales collaterals, events and more. GalaxEye went from no marketing presence to a recognised name in the Indian space-tech ecosystem.</div>
        </div>
        <div class="case-card">
          <div class="case-name">Mindgrove</div>
          <div class="case-type">Full-stack retainer</div>
          <div class="case-desc">Took Mindgrove&rsquo;s positioning from an interesting project to India&rsquo;s hottest semiconductor startup. Managed all content, socials, comms, pre-sales, events and more across the engagement.</div>
        </div>
        <div class="case-card">
          <div class="case-name">AskIITM</div>
          <div class="case-type">Full-stack retainer</div>
          <div class="case-desc">Started with a business requirement &mdash; &lsquo;manage perception among applicants&rsquo; &mdash; and created India&rsquo;s largest and most successful higher education campaign, touching over 5 million students.</div>
        </div>
      </div>
    </div>

    <!-- Investment -->
    <?php if ($feeDisplayStr): ?>
    <div class="prop-section">
      <div class="sec-label">Investment</div>
      <div class="sec-title">Fee &amp; payment</div>
      <div class="invest-big"><?= $feeDisplayStr ?></div>
      <?php if ($investSub): ?>
        <div class="invest-sub"><?= $investSub ?></div>
      <?php endif; ?>
      <?php if ($payNotes): ?>
        <div class="invest-note"><?= esc($payNotes) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Getting started -->
    <div class="prop-section">
      <div class="sec-label">Getting started</div>
      <div class="sec-title">Next steps</div>
      <ol class="steps-list">
        <li><span class="step-num">1</span><span>Align on scope and fee.</span></li>
        <li><span class="step-num">2</span><span>Sign contract (digitally).</span></li>
        <li><span class="step-num">3</span><span>First invoice is released.</span></li>
        <li><span class="step-num">4</span><span>Onboarding sequence gets activated.</span></li>
        <li><span class="step-num">5</span><span>First 2&ndash;4 weeks plan is set.</span></li>
        <li><span class="step-num">6</span><span>Project moves into ongoing mode with governance in place.</span></li>
      </ol>
    </div>

  </div><!-- /prop-body -->

  <!-- Footer -->
  <div class="prop-footer">
    <div class="prop-footer-logo">
      <span class="cv">Core</span><span class="voice">Voice</span>
    </div>
    <div class="prop-footer-contact">corevoice.in &nbsp;&middot;&nbsp; amrut@corevoice.in &nbsp;&middot;&nbsp; Bangalore, India</div>
  </div>


<?php else: ?>
<!-- ════════════════════════════════════════════
     CONTRACT
════════════════════════════════════════════ -->

  <div class="con-body">

    <!-- Logo + title block -->
    <div class="con-logo-center">
      <span class="cv">Core</span><span class="voice">Voice</span>
    </div>
    <div class="con-title">Marketing Services Agreement</div>
    <div class="con-confidential">Private &amp; Confidential</div>

    <!-- Intro -->
    <p class="con-intro">
      This Marketing Services Agreement (&ldquo;<strong>Agreement</strong>&rdquo;) is entered into on
      <strong><?= fmtDate($agreeDate) ?: '[Date]' ?></strong> (&ldquo;<strong>Agreement Date</strong>&rdquo;).
    </p>

    <!-- Parties -->
    <div class="con-party-block">
      <div class="con-party">
        <strong><?= esc($co ?: '[Client Company]') ?></strong><?php if ($coType): ?>,
        <?= coTypeLegal($coType) ?><?php endif; ?><?php if ($cin): ?>
        (CIN: <?= esc($cin) ?>)<?php endif; ?>, having its registered office at
        <?= esc($address ?: '[Address]') ?>, represented by
        <strong><?= esc($sigName ?: '[Signatory Name]') ?></strong>
        (designation: <?= esc($sigTitle ?: '[Designation]') ?>),
        hereinafter referred to as the party of the First Part (&ldquo;<strong>Client</strong>&rdquo;)
      </div>
      <div class="con-and">AND</div>
      <div class="con-party">
        <strong>CoreVoice (CV)</strong>, a division of <strong>Corebook Consulting Pvt Ltd</strong>,
        whose registered office is at WeWork Vaishnavi Signature, No. 78/9, Outer Ring Road,
        Bellandur Village, Varthur, Hobli Bangalore Karnataka 560103,
        hereinafter referred to as the (&ldquo;<strong>Consultant</strong>&rdquo;)
      </div>
    </div>

    <!-- Effective -->
    <div class="con-effective">
      This agreement is <strong>EFFECTIVE</strong> from Agreement Date
      <?php if ($duration): ?>for a period of <strong><?= esc($duration) ?></strong><?php endif; ?>.
    </div>

    <!-- Whereas -->
    <div class="con-whereas">
      <div class="con-whereas-head">Whereas</div>
      <ol>
        <li>Client is engaged in the business of <?= esc($bizDesc ?: '[business description]') ?>.</li>
        <li>Consultancy is engaged in the business of providing brand and marketing services &mdash; strategy, content and marketing ops.</li>
      </ol>
    </div>

    <!-- Points of Agreement -->
    <div class="con-poa-head">Points of Agreement</div>

    <div class="clause">
      <div class="clause-head">1. &nbsp; Scope of Work</div>
      <p>Client engages Consultant to provide marketing services as detailed in <strong>Annexure A</strong> (Scope of Work), which forms an integral part of this Agreement. Any changes to the scope shall be agreed in writing by authorised representatives of both Parties prior to implementation. Work outside the agreed scope shall be quoted and agreed separately in writing.</p>
    </div>

    <div class="clause">
      <div class="clause-head">2. &nbsp; Fee &amp; Terms of Payment</div>
      <p>Client shall pay Consultant the fees as specified in <strong>Annexure B</strong> (Fee &amp; Terms of Payment).
      <?php if ($feeType === 'retainer'): ?>
        All invoices are to be raised on the first day of each month.
        All invoices are to be paid within <?= esc($payDays) ?> of the invoice date.
      <?php elseif ($feeType === 'fixed'): ?>
        All invoices are to be paid within <?= esc($fixPayDays) ?> of the invoice date.
      <?php endif; ?>
      Consultant reserves the right to slow down or pause work when payment is delayed. All fees are exclusive of applicable taxes (GST or equivalent), which shall be payable by the Client in addition to the fees.</p>
    </div>

    <div class="clause">
      <div class="clause-head">3. &nbsp; Representations &amp; Warranties</div>
      <p>Each Party represents and warrants to the other that:</p>
      <ol type="a">
        <li>It has the full right, power, and authority to enter into this Agreement and perform its obligations hereunder.</li>
        <li>Entry into and performance of this Agreement does not violate any applicable law or existing contractual obligation.</li>
        <li>Client warrants that all information, materials, and approvals provided to Consultant are accurate, complete, and do not infringe any third-party intellectual property rights.</li>
      </ol>
    </div>

    <div class="clause">
      <div class="clause-head">4. &nbsp; Non-Disclosure</div>
      <p>Both Parties agree to maintain the confidentiality of all information shared in the course of this engagement as detailed in <strong>Annexure C</strong> (Mutual Non-Disclosure Agreement), which forms part of this Agreement. The obligations under this clause and Annexure C survive termination for a period of three (3) years.</p>
    </div>

    <div class="clause">
      <div class="clause-head">5. &nbsp; Permission to Share Work</div>
      <p>Client grants Consultant the right to reference the engagement and share outputs of the work — including creative deliverables, campaign results, and case study summaries — in Consultant&rsquo;s portfolio, proposals, website, and marketing materials, unless Client provides written objection within 30 days of any such reference being shared. Such reference shall not disclose confidential commercial terms without prior written consent.</p>
    </div>

    <div class="clause">
      <div class="clause-head">6. &nbsp; Termination</div>
      <p><strong>With cause:</strong> Either Party may terminate this Agreement immediately upon written notice if the other Party materially breaches this Agreement and fails to cure such breach within 15 (fifteen) days of receiving written notice of the breach.</p>
      <p><strong>Without cause:</strong> Either Party may terminate this Agreement without cause by providing 30 (thirty) days&rsquo; prior written notice to the other Party. Client shall pay for all work completed and expenses incurred through the termination date.</p>
      <p><strong>Non-payment:</strong> Consultant reserves the right to terminate this Agreement if Client fails to make payment within 30 (thirty) days of the due date, without prejudice to any other remedies available to Consultant.</p>
    </div>

    <div class="clause">
      <div class="clause-head">7. &nbsp; Renewal</div>
      <p>This Agreement shall automatically renew for successive periods equal to the original term unless either Party provides written notice of non-renewal at least 30 (thirty) days prior to the end of the then-current term. Renewal fees and scope shall be discussed and agreed in writing between the Parties before the renewal period commences.</p>
    </div>

    <div class="clause">
      <div class="clause-head">8. &nbsp; Intellectual Property</div>
      <p>8.1 &nbsp; All deliverables created exclusively for Client under this Agreement shall, upon receipt of full and final payment therefor, vest in Client as sole owner.</p>
      <p>8.2 &nbsp; Consultant retains ownership of all pre-existing intellectual property, including tools, methodologies, frameworks, templates, processes, and generic creative assets (&ldquo;Consultant IP&rdquo;). Nothing in this Agreement shall be deemed to transfer any rights in Consultant IP to Client.</p>
      <p>8.3 &nbsp; Client grants Consultant a limited, royalty-free licence to use Client&rsquo;s trademarks, logos, and brand materials solely to the extent required to perform the services under this Agreement.</p>
      <p>8.4 &nbsp; Where any deliverable contains Consultant IP, Consultant grants Client a perpetual, non-exclusive, royalty-free licence to use such Consultant IP as embedded in the deliverable for Client&rsquo;s internal business purposes.</p>
    </div>

    <div class="clause">
      <div class="clause-head">9. &nbsp; Limitation of Liability</div>
      <p>9.1 &nbsp; To the fullest extent permitted by applicable law, neither Party shall be liable to the other for any indirect, incidental, consequential, special, or punitive damages arising out of or related to this Agreement, even if advised of the possibility of such damages.</p>
      <p>9.2 &nbsp; Consultant&rsquo;s total aggregate liability to Client under this Agreement, whether in contract, tort, or otherwise, shall not exceed the total fees actually paid by Client to Consultant in the three (3) calendar months immediately preceding the event giving rise to the claim.</p>
      <p>9.3 &nbsp; The limitations in this clause shall not apply in cases of fraud, wilful misconduct, or death or personal injury caused by negligence.</p>
    </div>

    <div class="clause">
      <div class="clause-head">10. &nbsp; Non-Solicitation</div>
      <p>10.1 &nbsp; During the term of this Agreement and for a period of twelve (12) months following its termination or expiry, Client shall not, directly or indirectly, solicit, recruit, or engage any employee or contractor of Consultant who was involved in the delivery of services under this Agreement.</p>
      <p>10.2 &nbsp; In the event of a breach of this clause, Client agrees to pay Consultant a fee equivalent to twelve (12) months&rsquo; gross compensation of the relevant individual as liquidated damages, the Parties acknowledging such sum to be a genuine pre-estimate of loss.</p>
    </div>

    <div class="clause">
      <div class="clause-head">11. &nbsp; Data Protection</div>
      <p>11.1 &nbsp; Both Parties agree to comply with all applicable data protection laws, including the Digital Personal Data Protection Act, 2023 (&ldquo;DPDP Act&rdquo;) and any rules or regulations made thereunder.</p>
      <p>11.2 &nbsp; Each Party shall implement appropriate technical and organisational measures to protect personal data processed in connection with this Agreement against unauthorised access, loss, alteration, or destruction.</p>
      <p>11.3 &nbsp; In the event of a personal data breach, the Party experiencing the breach shall notify the other Party without undue delay, and in any case within 72 (seventy-two) hours of becoming aware of the breach, to the extent such notification is required under applicable law.</p>
    </div>

    <div class="clause">
      <div class="clause-head">12. &nbsp; Force Majeure</div>
      <p>Neither Party shall be liable for any failure or delay in performance under this Agreement to the extent such failure or delay is caused by circumstances beyond the reasonable control of that Party (&ldquo;Force Majeure Event&rdquo;), including but not limited to acts of God, natural disasters, pandemics, government actions, or civil unrest. The affected Party shall promptly notify the other Party and use reasonable efforts to resume performance. If the Force Majeure Event continues for more than 60 (sixty) days, either Party may terminate this Agreement by written notice without further liability.</p>
    </div>

    <div class="clause">
      <div class="clause-head">13. &nbsp; Governing Law &amp; Dispute Resolution</div>
      <p>13.1 &nbsp; This Agreement shall be governed by and construed in accordance with the laws of India.</p>
      <p>13.2 &nbsp; In the event of any dispute, difference, or controversy arising out of or relating to this Agreement, the Parties shall first attempt to resolve the matter through good-faith negotiations for a period of 30 (thirty) days from the date of written notice of the dispute.</p>
      <p>13.3 &nbsp; If such dispute cannot be resolved through negotiation, it shall be submitted to binding arbitration under the Arbitration and Conciliation Act, 1996 (as amended). The arbitration shall be conducted by a sole arbitrator mutually appointed by the Parties, and failing agreement, appointed in accordance with the Act.</p>
      <p>13.4 &nbsp; The seat and venue of arbitration shall be Bangalore, Karnataka, and the language of arbitration shall be English. The award of the arbitrator shall be final and binding on both Parties.</p>
    </div>

    <div class="clause">
      <div class="clause-head">14. &nbsp; Miscellaneous</div>
      <p>14.1 &nbsp; <strong>Entire Agreement.</strong> This Agreement, together with all Annexures, constitutes the entire agreement between the Parties with respect to its subject matter and supersedes all prior negotiations, representations, warranties, and understandings.</p>
      <p>14.2 &nbsp; <strong>Amendments.</strong> No amendment or modification of this Agreement shall be valid unless made in writing and signed by authorised representatives of both Parties.</p>
      <p>14.3 &nbsp; <strong>Severability.</strong> If any provision of this Agreement is found to be illegal, invalid, or unenforceable, the remaining provisions shall continue in full force and effect.</p>
      <p>14.4 &nbsp; <strong>Waiver.</strong> Failure by either Party to enforce any provision of this Agreement shall not constitute a waiver of that Party&rsquo;s right to enforce it subsequently or to enforce any other provision.</p>
      <p>14.5 &nbsp; <strong>Notices.</strong> All notices under this Agreement shall be in writing and delivered by email (with delivery confirmation) or courier to the address specified by each Party. Notices sent by email shall be deemed received on the date of transmission, provided no automated delivery failure notification is received within 24 hours.</p>
    </div>

    <!-- Signatures -->
    <p class="sig-intro">The agreement is electronically executed. Signatories:</p>
    <div class="sig-grid">
      <div>
        <div class="sig-party-label">CoreVoice / Corebook Consulting Pvt Ltd</div>
        <?php if ($senderName): ?>
          <div class="sig-name"><?= esc($senderName) ?></div>
        <?php endif; ?>
        <?php if ($senderTitle): ?>
          <div class="sig-detail"><?= esc($senderTitle) ?></div>
        <?php endif; ?>
        <?php if ($senderEmail): ?>
          <div class="sig-detail"><?= esc($senderEmail) ?></div>
        <?php endif; ?>
        <div class="sig-line-box"><strong>Signature &amp; date</strong></div>
      </div>
      <div>
        <div class="sig-party-label">Client</div>
        <?php if ($sigName): ?>
          <div class="sig-name"><?= esc($sigName) ?></div>
        <?php endif; ?>
        <?php if ($sigTitle): ?>
          <div class="sig-detail"><?= esc($sigTitle) ?></div>
        <?php endif; ?>
        <?php if ($sigEmail): ?>
          <div class="sig-detail"><?= esc($sigEmail) ?></div>
        <?php endif; ?>
        <div class="sig-line-box"><strong>Signature &amp; date</strong></div>
      </div>
    </div>

    <!-- Client details -->
    <table class="client-table">
      <tbody>
        <tr><td>Client company</td><td><?= esc($co ?: '—') ?></td></tr>
        <?php if ($coType): ?><tr><td>Type</td><td><?= esc($coType) ?></td></tr><?php endif; ?>
        <?php if ($cin):    ?><tr><td>CIN</td><td><?= esc($cin) ?></td></tr><?php endif; ?>
        <?php if ($gst):    ?><tr><td>GST</td><td><?= esc($gst) ?></td></tr><?php endif; ?>
        <?php if ($address): ?><tr><td>Registered office</td><td><?= esc($address) ?></td></tr><?php endif; ?>
        <?php if ($sigName): ?><tr><td>Signatory</td><td><?= esc($sigName) ?></td></tr><?php endif; ?>
        <?php if ($sigTitle): ?><tr><td>Designation</td><td><?= esc($sigTitle) ?></td></tr><?php endif; ?>
        <?php if ($sigEmail): ?><tr><td>Email</td><td><?= esc($sigEmail) ?></td></tr><?php endif; ?>
        <?php if ($agreeDate): ?><tr><td>Agreement date</td><td><?= fmtDate($agreeDate) ?></td></tr><?php endif; ?>
      </tbody>
    </table>

    <!-- ──────────────── ANNEXURE A ── -->
    <div class="ann-header">
      <div class="ann-tag">Annexure A</div>
      <div class="ann-title">Scope of Work</div>
    </div>

    <?php if ($engType): ?>
      <div class="ann-eng-type"><?= esc(strtoupper($engLabel)) ?></div>
    <?php endif; ?>

    <?php if ($objective): ?>
      <div class="ann-scope-obj"><strong>Objective:</strong> <?= esc($objective) ?></div>
    <?php endif; ?>

    <?php if ($scopeItems): ?>
    <div class="ann-scope-cats">
      <?php if ($strategyScope): ?>
        <h4>Strategy</h4>
        <ul>
          <?php foreach ($strategyScope as $item): ?>
            <li><?= esc($item) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($contentScope): ?>
        <h4>Content</h4>
        <ul>
          <?php foreach ($contentScope as $item): ?>
            <li><?= esc($item) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($opsScope): ?>
        <h4>Marketing Ops</h4>
        <ul>
          <?php foreach ($opsScope as $item): ?>
            <li><?= esc($item) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($addlScope): ?>
      <div class="ann-addl"><strong>Additional notes:</strong> <?= esc($addlScope) ?></div>
    <?php endif; ?>

    <div class="gov-section">
      <div class="gov-head">Governance</div>
      <ul class="gov-list">
        <li>Weekly &ldquo;Client Promises&rdquo; meeting — async or live, to ensure delivery stays on track and commitments from both sides are honoured.</li>
        <li>Monthly meeting — to review performance, update priorities, and align on the coming month&rsquo;s plan.</li>
        <li>Quarterly meeting — to assess the engagement, revisit strategy, and adjust scope if needed.</li>
      </ul>
      <div class="gov-note">All deliverables are subject to the revision policy outlined in the main Agreement. Specific output formats and volumes are agreed at the start of each governance cycle.</div>
    </div>

    <!-- ──────────────── ANNEXURE B ── -->
    <div class="ann-header">
      <div class="ann-tag">Annexure B</div>
      <div class="ann-title">Fee &amp; Terms of Payment</div>
    </div>

    <div class="fee-terms">
      <?php if ($feeType === 'retainer'): ?>
        <p>Monthly fee (excl. GST): <strong><?= fmtMoney($monthlyFee, $currCode) ?></strong></p>
        <?php if ($retDur): ?><p>Duration: <strong><?= esc($retDur) ?></strong></p><?php endif; ?>
        <p>All invoices to be raised on the first day of each month.</p>
        <p>All invoices to be paid within <strong><?= esc($payDays) ?></strong> of the invoice date.</p>
      <?php elseif ($feeType === 'fixed'): ?>
        <p>Total fixed fee (excl. GST): <strong><?= fmtMoney($totalFee, $currCode) ?></strong></p>
        <?php if ($fixedAdv): ?><p>Advance payable: <strong><?= fmtMoney($fixedAdv, $currCode) ?></strong></p><?php endif; ?>
        <p>All invoices to be paid within <strong><?= esc($fixPayDays) ?></strong> of the invoice date.</p>
      <?php elseif ($feeType === 'milestone'): ?>
        <p>Payment is milestone-based as follows:</p>
        <p style="white-space:pre-wrap;"><?= esc($milestones) ?></p>
      <?php endif; ?>
      <p>Consultancy has the right to slow down work when payment is delayed beyond the agreed terms.</p>
      <?php if ($opeFull): ?>
        <p>Out of pocket expenses will be <?= esc($opeFull) ?>.</p>
      <?php endif; ?>
      <?php if ($gst): ?><p>Client GST: <?= esc($gst) ?></p><?php endif; ?>
      <?php if ($payNotes): ?><p><?= esc($payNotes) ?></p><?php endif; ?>
    </div>

    <table class="bank-table">
      <caption>Payment details — CoreBook Consulting Pvt Ltd</caption>
      <tbody>
        <tr><td>Beneficiary name</td><td>CoreBook Consulting Pvt Ltd</td></tr>
        <tr><td>Address</td><td>Park Vista B Block, F No. 503, Amblipura, Sarjapur Road, Bangalore, Karnataka 560037</td></tr>
        <tr><td>CIN</td><td>U74999KA2023PTC169895</td></tr>
        <tr><td>PAN</td><td>AAKCC8142N</td></tr>
        <tr><td>GST</td><td>29AAKCC8142N1Z0</td></tr>
        <tr><td>Bank account number</td><td>4090019050053</td></tr>
        <tr><td>IFSC code</td><td>RATN0000091</td></tr>
        <tr><td>Date of incorporation</td><td>04 Jan 2023</td></tr>
      </tbody>
    </table>

    <!-- ──────────────── ANNEXURE C ── -->
    <div class="ann-header">
      <div class="ann-tag">Annexure C</div>
      <div class="ann-title">Mutual Non-Disclosure Agreement</div>
    </div>

    <p style="font-size:.86rem;margin-bottom:20px;color:#4a4a6a;">
      This Mutual Non-Disclosure Agreement forms part of and is incorporated into the Marketing Services Agreement between
      <strong>Corebook Consulting Pvt Ltd (CoreVoice)</strong> and <strong><?= esc($co ?: '[Client]') ?></strong>
      dated <?= fmtDate($agreeDate) ?: '[Date]' ?>.
    </p>

    <div class="nda-section">
      <div class="nda-head">1. &nbsp; Definition of Confidential Information</div>
      <div class="nda-body">
        <p>&ldquo;Confidential Information&rdquo; means any and all non-public information or data disclosed by one Party (the &ldquo;Disclosing Party&rdquo;) to the other Party (the &ldquo;Receiving Party&rdquo;) under or in connection with this Agreement, whether disclosed in writing, orally, electronically, or by any other means, and whether or not designated as &ldquo;confidential&rdquo; at the time of disclosure. This includes, without limitation: business plans, financial data, pricing models, customer and client information, product or service roadmaps, marketing strategies, creative briefs, campaign performance data, technical specifications, proprietary methodologies, and trade secrets.</p>
      </div>
    </div>

    <div class="nda-section">
      <div class="nda-head">2. &nbsp; Obligations</div>
      <div class="nda-body">
        <p>The Receiving Party agrees to:</p>
        <ol type="a">
          <li>Hold all Confidential Information in strict confidence and not disclose it to any third party without the prior written consent of the Disclosing Party;</li>
          <li>Use the Confidential Information solely for the purpose of performing its obligations or exercising its rights under this Agreement;</li>
          <li>Limit access to the Confidential Information to those employees, contractors, and advisors who have a genuine need to know for the purposes of this Agreement, and who are bound by confidentiality obligations no less protective than those set out herein;</li>
          <li>Take at least the same degree of care to protect the Disclosing Party&rsquo;s Confidential Information as it uses to protect its own confidential information of a similar nature, and in any event no less than a reasonable degree of care.</li>
        </ol>
      </div>
    </div>

    <div class="nda-section">
      <div class="nda-head">3. &nbsp; Exclusions</div>
      <div class="nda-body">
        <p>The obligations in this Annexure shall not apply to information that:</p>
        <ol type="a">
          <li>Is or becomes publicly available through no act or omission of the Receiving Party;</li>
          <li>Was already known to the Receiving Party at the time of disclosure, without any obligation of confidentiality;</li>
          <li>Is independently developed by the Receiving Party without reference to or use of the Confidential Information;</li>
          <li>Is received from a third party who is not under any obligation of confidentiality with respect to such information; or</li>
          <li>Is required to be disclosed by applicable law, regulation, or court order, provided that the Receiving Party gives the Disclosing Party prompt prior written notice (where legally permitted) and cooperates with the Disclosing Party in seeking a protective order or other appropriate relief.</li>
        </ol>
      </div>
    </div>

    <div class="nda-section">
      <div class="nda-head">4. &nbsp; Term &amp; Return of Information</div>
      <div class="nda-body">
        <p>This Annexure shall come into effect on the Agreement Date and shall remain in force for a period of <strong>three (3) years</strong> from the date of expiry or termination of the Agreement, or such longer period as may be required by applicable law. Upon the written request of the Disclosing Party, or upon expiry or termination of the Agreement, the Receiving Party shall promptly return or permanently destroy all Confidential Information (including all copies and extracts thereof) and certify in writing that it has done so.</p>
      </div>
    </div>

    <div class="nda-section">
      <div class="nda-head">5. &nbsp; Remedies</div>
      <div class="nda-body">
        <p>The Receiving Party acknowledges that any breach or threatened breach of its obligations under this Annexure may cause irreparable harm to the Disclosing Party for which monetary damages would be an inadequate remedy. Accordingly, in addition to any other rights and remedies available under applicable law, the Disclosing Party shall be entitled to seek equitable relief, including injunction and specific performance, without the requirement to post a bond or other security and without the need to prove actual damages.</p>
      </div>
    </div>

  </div><!-- /con-body -->

<?php endif; ?>

</div><!-- /page -->
</body>
</html>
