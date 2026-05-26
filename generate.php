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
$engRationaleMap = [
    'full-retainer'    => 'Based on what you\'ve shared, a full-stack retainer makes the most sense. You need consistent output across strategy, content, and execution — not a one-time project. We\'d operate as your marketing function, with clear goals, a shared calendar, and regular governance to make sure the work stays aligned with where the business is going.',
    'outcome-retainer' => 'What you\'ve described is a time-boxed problem, not a forever engagement. An outcome-focused retainer lets us define the goal together, set a window, and deploy whatever\'s needed to get there — then reassess. No lock-in beyond what the goal requires.',
    'content-retainer' => 'Your content engine isn\'t running at the level it needs to be. A content retainer gives you consistent, quality output that builds over time — compounding rather than campaign-based. Volume plus consistency is what moves the needle.',
    'new-gtm'          => 'You\'re entering the market fresh — or after a meaningful pivot. That means you need positioning, identity, and a full sales kit before anything else. We\'ll build the foundation so that every subsequent marketing activity has something real to stand on.',
    'gtm-relaunch'     => 'You already have something in the market, but it\'s not landing the way it should. A relaunch isn\'t about starting over — it\'s about updating what exists to reflect where the company actually is now.',
    'fundraising'      => 'When you\'re heading into a round, the narrative has to do the work before you even get in the room. We\'ll clean up your positioning, tighten the deck, and make sure the website backs up the story you\'re telling investors.',
    'sales-video'      => 'Video is the most efficient format for a complex product or a crowded market. A short series of well-made videos — explainers, use cases, testimonials — gives your sales team something that travels across every channel and conversation.',
    'custom'           => 'The scope here has been defined specifically for this engagement, based on what you\'ve described and what we believe will move the needle. We\'ll work from this as our starting point and adjust as we go.',
];
$engLabel     = $engLabels[$engType]         ?? ucwords(str_replace('-', ' ', $engType));
$engDesc      = $engDescs[$engType]          ?? '';
$engRationale = $engRationaleMap[$engType]   ?? '';

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

/* ─────────────────────────────── contract scope display labels ── */
$scopeContractLabels = [
    'Positioning & communication'       => 'Positioning &amp; communication — craft market-relative positioning, communication pillars and guides for each stakeholder and context',
    'CMO office — goals & reporting'    => 'CMO office — goals, budgets &amp; reporting',
    'Website design'                    => 'Website — new pages and updates (Figma + Webflow + native)',
    'Social posts — text, image, carousels' => 'Social media content — text, images, carousels',
    'Reels, shorts & social video'      => 'Short-form video — reels, shorts, social video',
    'SEO blogs & newsletters'           => 'SEO blogs, newsletters &amp; long-form articles',
    'Ad creatives — static & video'     => 'Ad creatives — static and video',
    'Social media management'           => 'Social media management — LinkedIn, Instagram, YouTube, X',
    'Paid ads — Google, Meta, LinkedIn' => 'Paid ads management — Google Ads, Meta Ads, LinkedIn Ads',
];
function scopeContractLabel(string $item): string {
    global $scopeContractLabels;
    return $scopeContractLabels[$item] ?? esc($item);
}

/* ─────────────────────────────── fee display ── */
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

/* ─────────────────────────────── payment terms ── */
$payDaysMap    = ['Net 15' => '15 days', 'Net 30' => '30 days', 'Advance' => 'advance'];
$payDays       = $payDaysMap[$payTerms]    ?? ($payTerms    ?: '15 days');
$fixPayDaysMap = ['Net 15' => '15 days', 'Net 30' => '30 days'];
$fixPayDays    = $fixPayDaysMap[$fixPayTerms] ?? ($fixPayTerms ?: '15 days');

/* ─────────────────────────────── OPE texts ── */
$opeSubMap = [
    'preapproved'     => 'Out-of-pocket expenses pre-approved and reimbursed separately',
    'casebycasetools' => 'AI/SaaS tools not reimbursed; media/travel pre-approved case-by-case',
    'none'            => 'No OPE reimbursement',
];
$opeSub = $opeSubMap[$expenses] ?? '';

// Annexure B OPE line — exact approved wording
$opeAnnexMap = [
    'preapproved'     => 'Out of pocket expenses will be pre-approved and submitted for reimbursement.',
    'casebycasetools' => 'Out of pocket expenses: AI/SaaS tools not reimbursed; media/travel to be pre-approved case-by-case.',
    'none'            => 'Out of pocket expenses will not be reimbursed.',
];
$opeAnnex = $opeAnnexMap[$expenses] ?? '';

/* ─────────────────────────────── proposal investment sub-line ── */
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

/* ─────────────────────────────── company type legal description ── */
function coTypeLegal(string $t): string {
    switch ($t) {
        case 'Private Limited': return 'a company incorporated under the Companies Act, 2013';
        case 'LLP':             return 'a limited liability partnership registered under the Limited Liability Partnership Act, 2008';
        case 'Inc (Delaware)':  return 'a corporation incorporated under the laws of the State of Delaware, USA';
        case 'Ltd (UK)':        return 'a company incorporated under the Companies Act 2006, United Kingdom';
        default:                return $t ? 'a ' . $t : '';
    }
}

/* ─── signature block helper ─── */
function renderSigBlock(string $sn, string $st, string $se,
                        string $cn, string $ct, string $co2, string $ce): string {
    $clientLine = array_filter([$ct, $co2]);
    $o  = '<div class="ann-sig-sep"></div>';
    $o .= '<p class="sig-intro">Agreed and accepted:</p>';
    $o .= '<div class="sig-grid">';
    $o .= '<div class="sig-block">';
    if ($sn) $o .= '<div class="sig-name">'   . esc($sn) . '</div>';
    if ($st) $o .= '<div class="sig-detail">'  . esc($st) . '</div>';
    $o .= '<div class="sig-detail">Corebook Consulting Pvt. Ltd.</div>';
    if ($se) $o .= '<div class="sig-detail">'  . esc($se) . '</div>';
    $o .= '</div>';
    $o .= '<div class="sig-block">';
    if ($cn) $o .= '<div class="sig-name">'   . esc($cn) . '</div>';
    if ($clientLine) $o .= '<div class="sig-detail">' . esc(implode(', ', $clientLine)) . '</div>';
    if ($ce) $o .= '<div class="sig-detail">'  . esc($ce) . '</div>';
    $o .= '</div>';
    $o .= '</div>';
    return $o;
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

    .page {
      max-width: 760px;
      margin: 32px auto 80px;
      background: #fff;
      box-shadow: 0 4px 32px rgba(0,0,0,.10);
      border-radius: 3px;
    }

    /* ── Toolbar ── */
    .toolbar {
      position: fixed; bottom: 24px; right: 24px;
      display: flex; gap: 10px; z-index: 100;
    }
    .btn-print {
      background: #1a1a2e; color: #fff; border: none;
      border-radius: 8px; padding: 12px 24px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
    }
    .btn-print:hover { background: #2d2d4e; }
    .btn-back {
      background: #fff; color: #1a1a2e; border: 1.5px solid #d1d5db;
      border-radius: 8px; padding: 12px 24px; font-size: .88rem;
      font-weight: 600; cursor: pointer; font-family: 'Segoe UI', sans-serif;
    }

    /* ════════════════════
       PROPOSAL STYLES
    ════════════════════ */
    .prop-hero { padding: 56px 56px 40px; border-bottom: 1px solid #e8e8f0; }
    .prop-tag {
      font-family: 'Segoe UI', sans-serif;
      font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .2em;
      color: #C9972A; margin-bottom: 20px;
    }
    .prop-h1 {
      font-family: 'Georgia', serif;
      font-size: 2.15rem; font-weight: 700;
      line-height: 1.18; color: #1a1a2e; margin-bottom: 18px;
    }
    .prop-h1 em { font-style: italic; color: #C9972A; }
    .prop-meta {
      font-family: 'Segoe UI', sans-serif;
      font-size: .83rem; color: #6b7280;
    }
    .prop-meta .arrow { color: #C9972A; margin: 0 4px; }
    .prop-meta .dot   { margin: 0 8px; opacity: .4; }
    .prop-hr { border: none; border-top: 1.5px solid #e8e8f0; margin: 0; }

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
    .note-card {
      border-left: 3px solid #C9972A; padding: 22px 26px;
      background: #fffdf7; border-radius: 0 4px 4px 0;
      font-size: .93rem; line-height: 1.82;
      white-space: pre-wrap; color: #2a2a3e;
    }
    .note-sender {
      margin-top: 20px; padding-top: 16px;
      border-top: 1px solid #e8e8f0;
      font-family: 'Segoe UI', sans-serif;
      font-size: .83rem; color: #6b7280;
    }
    .note-sender strong { display: block; color: #1a1a2e; font-size: .88rem; margin-bottom: 1px; }
    .heard-quote { font-style: italic; color: #3a3a5e; font-size: .96rem; line-height: 1.82; margin-bottom: 20px; }
    .trigger-list { list-style: none; display: flex; flex-wrap: wrap; gap: 8px; }
    .trigger-list li {
      background: #f4f4f8; border-radius: 20px;
      padding: 5px 14px; font-family: 'Segoe UI', sans-serif;
      font-size: .78rem; color: #1a1a2e;
    }
    .rec-card { border: 1px solid #e8e8f0; border-radius: 8px; padding: 24px 28px; }
    .rec-badge {
      display: inline-block; background: #1a1a2e; color: #fff;
      font-family: 'Segoe UI', sans-serif; font-size: .62rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      padding: 3px 10px; border-radius: 3px; margin-bottom: 14px;
    }
    .rec-name     { font-size: 1.12rem; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
    .rec-desc     { font-size: .9rem; color: #4a4a6a; line-height: 1.7; margin-bottom: 20px; }
    .rec-divider  { border: none; border-top: 1px solid #e8e8f0; margin: 0 0 20px; }
    .rec-rationale{ font-style: italic; color: #5a5a7a; font-size: .88rem; line-height: 1.78; }
    .scope-obj    { color: #3a3a5e; font-size: .93rem; margin-bottom: 24px; line-height: 1.75; }
    .scope-categories { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 24px; margin-bottom: 20px; }
    .scope-cat-head {
      font-family: 'Segoe UI', sans-serif; font-size: .63rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em;
      margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid;
    }
    .scope-cat-head.strategy { color: #1a1a2e; border-color: #1a1a2e; }
    .scope-cat-head.content  { color: #0d7a72; border-color: #0d9488; }
    .scope-cat-head.ops      { color: #b45309; border-color: #D97706; }
    .scope-cat ul { list-style: none; padding: 0; }
    .scope-cat li { font-size: .82rem; color: #3a3a5e; padding: 3px 0; border-bottom: 1px solid #f4f4f8; }
    .scope-cat li:last-child { border-bottom: none; }
    .scope-note  { font-style: italic; font-size: .84rem; color: #6b7280; margin-bottom: 8px; }
    .scope-cycle { font-size: .84rem; color: #6b7280; }
    .cases { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    .case-card { border: 1px solid #e8e8f0; border-radius: 6px; padding: 18px 18px 16px; }
    .case-name {
      font-family: 'Segoe UI', sans-serif; font-size: .78rem; font-weight: 700;
      color: #1a1a2e; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px;
    }
    .case-type { font-family: 'Segoe UI', sans-serif; font-size: .72rem; color: #C9972A; margin-bottom: 10px; }
    .case-desc { font-size: .8rem; color: #5a5a7a; line-height: 1.65; }
    .invest-big {
      font-family: 'Georgia', serif; font-size: 1.8rem; font-weight: 700;
      color: #1a1a2e; margin-bottom: 10px; line-height: 1.2;
    }
    .invest-sub  { font-family: 'Segoe UI', sans-serif; font-size: .82rem; color: #6b7280; margin-bottom: 18px; line-height: 1.6; }
    .invest-note { font-size: .86rem; color: #4a4a6a; line-height: 1.72; padding: 14px 18px; background: #f9fafb; border-radius: 4px; border: 1px solid #e8e8f0; }
    .steps-list  { list-style: none; }
    .steps-list li {
      display: flex; align-items: flex-start; gap: 18px;
      padding: 14px 0; border-bottom: 1px solid #f0f0f5;
      font-size: .9rem; color: #2a2a3e;
    }
    .steps-list li:last-child { border-bottom: none; }
    .step-num {
      font-family: 'Segoe UI', sans-serif; font-size: .7rem; font-weight: 700;
      background: #1a1a2e; color: #fff; width: 22px; height: 22px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; margin-top: 1px;
    }
    .prop-footer {
      border-top: 1px solid #e8e8f0; padding: 20px 56px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .prop-footer-logo { font-family: 'Segoe UI', sans-serif; font-size: .88rem; font-weight: 700; }
    .prop-footer-logo .cv    { color: #1a1a2e; }
    .prop-footer-logo .voice { color: #C9972A; }
    .prop-footer-contact { font-family: 'Segoe UI', sans-serif; font-size: .75rem; color: #9ca3af; }

    /* ════════════════════
       CONTRACT STYLES
    ════════════════════ */
    .con-body { padding: 52px 56px; }

    .con-logo-center {
      text-align: center; margin-bottom: 24px;
      font-family: 'Segoe UI', sans-serif; font-size: 1.3rem; font-weight: 700;
    }
    .con-logo-center .cv    { color: #1a1a2e; }
    .con-logo-center .voice { color: #C9972A; }
    .con-title {
      text-align: center; font-family: 'Georgia', serif;
      font-size: 1.05rem; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; color: #1a1a2e; margin-bottom: 8px;
    }
    .con-confidential {
      text-align: center; font-family: 'Segoe UI', sans-serif;
      font-size: .7rem; font-weight: 700; letter-spacing: .18em;
      text-transform: uppercase; color: #9ca3af; margin-bottom: 36px;
    }
    .con-intro { font-size: .9rem; margin-bottom: 24px; line-height: 1.78; }

    .con-party-block {
      background: #f9fafb; border: 1px solid #e2e8f0;
      border-radius: 6px; padding: 22px 24px; margin-bottom: 24px;
    }
    .con-party { font-size: .88rem; line-height: 1.8; margin-bottom: 14px; }
    .con-party:last-child { margin-bottom: 0; }
    .con-and {
      font-family: 'Segoe UI', sans-serif; font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .12em;
      color: #9ca3af; margin: 10px 0;
    }
    .con-effective {
      font-size: .9rem; margin-bottom: 24px; padding: 12px 18px;
      border-left: 3px solid #C9972A; background: #fffdf7; line-height: 1.78;
    }
    .con-section-head {
      font-family: 'Segoe UI', sans-serif; font-size: .7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em;
      color: #9ca3af; margin-bottom: 12px; margin-top: 28px;
    }
    .con-poa-intro { font-size: .88rem; line-height: 1.78; margin-bottom: 20px; color: #3a3a5e; }

    /* Clause numbering */
    .clause       { margin-bottom: 22px; }
    .clause > p   { font-size: .87rem; line-height: 1.78; margin-bottom: 0; }
    .cl-head      { font-size: .87rem; line-height: 1.78; margin-bottom: 6px; }
    .cl-num       { font-weight: 700; }
    .cl-sub       { font-size: .87rem; line-height: 1.78; margin-bottom: 5px; padding-left: 20px; }
    .cl-sub2      { font-size: .87rem; line-height: 1.78; margin-bottom: 4px; padding-left: 40px; }

    /* Signatures */
    .sig-intro { font-size: .87rem; color: #3a3a5e; margin: 32px 0 20px; }
    .sig-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
    .sig-block { font-size: .87rem; line-height: 1.75; }
    .sig-block .sig-name    { font-weight: 700; font-size: .9rem; }
    .sig-block .sig-detail  { color: #4a4a6a; }

    /* Annexure headers */
    .ann-header {
      text-align: center; padding: 28px 0 20px;
      border-top: 2px solid #e2e8f0; margin: 48px 0 24px;
    }
    .ann-tag   { font-family: 'Segoe UI', sans-serif; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .18em; color: #9ca3af; margin-bottom: 4px; }
    .ann-title { font-family: 'Georgia', serif; font-size: 1.05rem; font-weight: 700; color: #1a1a2e; }

    /* Annexure A */
    .ann-kv { font-size: .87rem; margin-bottom: 14px; }
    .ann-kv strong { color: #1a1a2e; }
    .ann-obj { font-size: .87rem; line-height: 1.78; margin-bottom: 20px; }
    .ann-services-head {
      font-family: 'Segoe UI', sans-serif; font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .14em; color: #9ca3af;
      margin-bottom: 16px;
    }
    .ann-cat-head {
      font-family: 'Segoe UI', sans-serif; font-weight: 700;
      font-size: .88rem; color: #1a1a2e; margin: 16px 0 6px;
    }
    .ann-cat-list { list-style: disc; padding-left: 22px; margin-bottom: 4px; }
    .ann-cat-list li { font-size: .87rem; line-height: 1.72; margin-bottom: 3px; }
    .ann-addl-head {
      font-family: 'Segoe UI', sans-serif; font-weight: 700;
      font-size: .88rem; color: #1a1a2e; margin: 20px 0 6px;
    }
    .ann-addl-text { font-size: .87rem; line-height: 1.72; margin-bottom: 16px; }
    .ann-gov-head  {
      font-family: 'Segoe UI', sans-serif; font-weight: 700;
      font-size: .88rem; color: #1a1a2e; margin: 20px 0 8px;
    }
    .ann-gov-list { list-style: disc; padding-left: 22px; margin-bottom: 10px; }
    .ann-gov-list li { font-size: .87rem; line-height: 1.72; margin-bottom: 5px; }
    .ann-gov-note { font-size: .84rem; color: #6b7280; font-style: italic; line-height: 1.7; }

    /* Annexure B */
    .ann-b-head {
      font-family: 'Segoe UI', sans-serif; font-weight: 700;
      font-size: .88rem; color: #1a1a2e; margin: 20px 0 8px;
    }
    .ann-b-terms { list-style: disc; padding-left: 22px; margin-bottom: 16px; }
    .ann-b-terms li { font-size: .87rem; line-height: 1.72; margin-bottom: 4px; }
    .bank-table { width: 100%; border-collapse: collapse; font-size: .87rem; margin-top: 10px; }
    .bank-table td { padding: 8px 12px; border: 1px solid #e2e8f0; vertical-align: top; }
    .bank-table td:first-child { width: 38%; font-weight: 600; color: #6b7280; background: #f9fafb; white-space: nowrap; }

    /* Annexure C NDA */
    .nda-preamble { font-size: .87rem; line-height: 1.78; margin-bottom: 20px; }
    .nda-section  { margin-bottom: 16px; font-size: .87rem; line-height: 1.78; }
    .nda-num      { font-weight: 700; }

    /* ─── Print header / footer (hidden on screen) ─── */
    .print-header, .print-footer { display: none; }
    .ann-sig-sep { border-top: 1px solid #e2e8f0; margin: 36px 0 20px; }

    @page { size: A4; margin: 2.5cm 1.5cm; }

    @media print {
      body  { background: #fff; font-size: 9.5pt; }
      .page { box-shadow: none; margin: 0; max-width: 100%; border-radius: 0; }
      .toolbar { display: none !important; }

      /* Repeating page header: logo top-right */
      .print-header {
        display: flex; position: fixed;
        top: 0; left: 0; right: 0; height: 1.1cm;
        align-items: center; justify-content: flex-end;
        border-bottom: 1px solid #e2e8f0; background: #fff;
      }
      /* Repeating page footer: address bottom-centre */
      .print-footer {
        display: flex; position: fixed;
        bottom: 0; left: 0; right: 0; height: 1cm;
        align-items: center; justify-content: center;
        border-top: 1px solid #e2e8f0; background: #fff;
      }
      .ph-logo        { font-family: 'Segoe UI', sans-serif; font-size: .78rem; font-weight: 700; }
      .ph-logo .cv    { color: #1a1a2e; }
      .ph-logo .voice { color: #C9972A; }
      .pf-text        { font-family: 'Segoe UI', sans-serif; font-size: .68rem; color: #6b7280; text-align: center; }

      /* Push content clear of fixed header/footer */
      .con-body    { padding-top: 1.4cm; padding-bottom: 1.3cm; padding-left: 0; padding-right: 0; }
      .prop-hero   { padding-top: 1.4cm; padding-left: 0; padding-right: 0; }
      .prop-body   { padding-left: 0; padding-right: 0; }
      .prop-footer { padding-left: 0; padding-right: 0; }

      /* Annexures always start on a new page */
      .ann-header { page-break-before: always; }

      /* Clause and NDA paragraphs may break across pages freely */
    }
  </style>
</head>
<body>

<div class="toolbar">
  <button class="btn-back"  onclick="window.close()">&#8592; Edit</button>
  <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="page">

<div class="print-header">
  <span class="ph-logo"><span class="cv">Core</span><span class="voice">Voice</span></span>
</div>
<div class="print-footer">
  <span class="pf-text">Corebook Consulting Pvt. Ltd &nbsp;&middot;&nbsp; WeWork Vaishnavi Signature, Outer Ring Road, Bellandur, Bangalore 560103 &nbsp;&middot;&nbsp; corevoice.in</span>
</div>

<?php if ($isProposal): ?>
<!-- ═══════════ PROPOSAL ═══════════ -->

  <div class="prop-hero">
    <div class="prop-tag">Proposal for <?= esc($co ?: '[Company]') ?></div>
    <h1 class="prop-h1">Here&rsquo;s how we&rsquo;d work <em>together</em></h1>
    <div class="prop-meta">
      <span>CoreVoice</span><span class="arrow">→</span><span><?= esc($co ?: '[Company]') ?></span>
      <?php if ($agreeDate): ?><span class="dot">&middot;</span><span><?= fmtDate($agreeDate) ?></span><?php endif; ?>
      <?php if ($duration):  ?><span class="dot">&middot;</span><span><?= esc($duration) ?></span><?php endif; ?>
    </div>
  </div>
  <hr class="prop-hr" />

  <div class="prop-body">

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

    <?php if ($clientSaid || $triggers): ?>
    <div class="prop-section">
      <div class="sec-label">What we heard from you</div>
      <div class="sec-title">What brought you here</div>
      <?php if ($clientSaid): ?><div class="heard-quote"><?= escNL($clientSaid) ?></div><?php endif; ?>
      <?php if ($triggers): ?>
        <ul class="trigger-list">
          <?php foreach ($triggers as $t): ?><li><?= esc($t) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($engType): ?>
    <div class="prop-section">
      <div class="sec-label">Our recommendation</div>
      <div class="sec-title">What we&rsquo;d suggest, and why</div>
      <div class="rec-card">
        <div class="rec-badge"><?= esc(strtoupper($engLabel)) ?></div>
        <div class="rec-name"><?= esc($engLabel) ?></div>
        <?php if ($engDesc):      ?><div class="rec-desc"><?= esc($engDesc) ?></div><?php endif; ?>
        <hr class="rec-divider" />
        <?php if ($engRationale): ?><div class="rec-rationale"><?= esc($engRationale) ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($scopeItems || $objective): ?>
    <div class="prop-section">
      <div class="sec-label">Scope of work</div>
      <div class="sec-title">What we&rsquo;ll do</div>
      <?php if ($objective): ?><div class="scope-obj"><?= escNL($objective) ?></div><?php endif; ?>
      <?php if ($scopeItems): ?>
      <div class="scope-categories">
        <?php if ($strategyScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head strategy">Strategy</div>
          <ul><?php foreach ($strategyScope as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($contentScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head content">Content</div>
          <ul><?php foreach ($contentScope as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($opsScope): ?>
        <div class="scope-cat">
          <div class="scope-cat-head ops">Marketing ops</div>
          <ul><?php foreach ($opsScope as $i): ?><li><?= esc($i) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($addlScope): ?><div class="scope-note"><?= esc($addlScope) ?></div><?php endif; ?>
      <div class="scope-cycle">Scope is reviewed and adjusted at each governance cycle based on what&rsquo;s working and what the business needs.</div>
    </div>
    <?php endif; ?>

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

    <?php if ($feeDisplayStr): ?>
    <div class="prop-section">
      <div class="sec-label">Investment</div>
      <div class="sec-title">Fee &amp; payment</div>
      <div class="invest-big"><?= $feeDisplayStr ?></div>
      <?php if ($investSub): ?><div class="invest-sub"><?= $investSub ?></div><?php endif; ?>
      <?php if ($payNotes):  ?><div class="invest-note"><?= esc($payNotes) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

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

  </div>

  <div class="prop-footer">
    <div class="prop-footer-logo"><span class="cv">Core</span><span class="voice">Voice</span></div>
    <div class="prop-footer-contact">corevoice.in &nbsp;&middot;&nbsp; amrut@corevoice.in &nbsp;&middot;&nbsp; Bangalore, India</div>
  </div>


<?php else: ?>
<!-- ═══════════ CONTRACT ═══════════ -->

  <div class="con-body">

    <div class="con-logo-center"><span class="cv">Core</span><span class="voice">Voice</span></div>
    <div class="con-title">Marketing Services Agreement</div>
    <div class="con-confidential">Private &amp; Confidential</div>

    <p class="con-intro">This Marketing Services Agreement (&ldquo;<strong>Agreement</strong>&rdquo;) is entered into on <strong><?= fmtDate($agreeDate) ?: '[Date]' ?></strong> (&ldquo;<strong>Agreement Date</strong>&rdquo;).</p>

    <!-- Parties -->
    <div class="con-section-head">Parties</div>
    <div class="con-party-block">
      <div class="con-party">
        <strong><?= esc($co ?: '[Client Company]') ?></strong><?php if ($coType): ?>, <?= coTypeLegal($coType) ?><?php endif; ?><?php if ($cin): ?> (CIN: <?= esc($cin) ?>)<?php endif; ?>, having its registered office at <?= esc($address ?: '[Address]') ?>, represented by <?= esc($sigName ?: '[Signatory Name]') ?> (designation: <?= esc($sigTitle ?: '[Designation]') ?>), which expression shall, unless excluded by or repugnant to the context or meaning thereof, be deemed to mean and include its successors-in-interest, designates and permitted assigns as the party of the First Part (&ldquo;<strong>Client</strong>&rdquo;)
      </div>
      <div class="con-and">AND</div>
      <div class="con-party">
        <strong>CoreVoice (CV)</strong>, a division of Corebook Consulting Pvt Ltd whose registered office is at WeWork Vaishnavi Signature, No. 78/9, Outer Ring Road, Bellandur Village, Varthur, Hobli Bangalore Karnataka 560103 (&ldquo;<strong>Consultant</strong>&rdquo;) which expression where the context so admits shall include their successors and assigns of the Other Part.
      </div>
    </div>

    <!-- Effective -->
    <div class="con-effective">
      This agreement is <strong>EFFECTIVE</strong> from Agreement Date<?php if ($duration): ?> for a period of <strong><?= esc($duration) ?></strong><?php endif; ?>.
    </div>

    <!-- Whereas -->
    <div class="con-section-head">Whereas</div>
    <div style="font-size:.87rem;line-height:1.78;margin-bottom:24px;">
      <p style="margin-bottom:6px;">1.&nbsp;&nbsp;The Client is engaged in the business of <?= esc($bizDesc ?: '[business description]') ?>.</p>
      <p>2.&nbsp;&nbsp;The Consultancy is engaged in the business of providing brand and marketing services &mdash; strategy, content and marketing ops.</p>
    </div>

    <!-- Points of Agreement -->
    <div class="con-section-head">Points of agreement</div>
    <p class="con-poa-intro">The Client is keen to hire the services of the Consultant. The points of the agreement for the engagement are as follows:</p>

    <!-- Clause 1 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">1.</span>&nbsp;&nbsp;The Scope of Work is defined in Annexure A.</p>
      <p class="cl-sub"><span class="cl-num">1.1.</span>&nbsp;&nbsp;The Consultant hereby represents and warrants that it has the necessary expertise to deliver the Scope of Work described hereinabove.</p>
      <p class="cl-sub"><span class="cl-num">1.2.</span>&nbsp;&nbsp;The Consultant also represents that it shall hire the required resources to complete the defined scope of work.</p>
    </div>

    <!-- Clause 2 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">2.</span>&nbsp;&nbsp;The Fee and Terms of Payment are defined in Annexure B.</p>
    </div>

    <!-- Clause 3 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">3.</span>&nbsp;&nbsp;<strong>Representation and Warranties:</strong> The Consultant hereby represents and warrants to the Client that</p>
      <p class="cl-sub"><span class="cl-num">3.1.</span>&nbsp;&nbsp;it has the full power and authority to enter into and perform this Agreement and to carry out the transactions contemplated by this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">3.2.</span>&nbsp;&nbsp;the execution and performance of this Agreement will not violate any applicable statute, law, rule or regulation to which the warranting party is subject, or conflict with, result in a breach of, or constitute a default under any agreement to which it is a party; and</p>
      <p class="cl-sub"><span class="cl-num">3.3.</span>&nbsp;&nbsp;it has obtained all necessary approvals to enter into this Agreement and to perform its obligations hereunder.</p>
    </div>

    <!-- Clause 4 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">4.</span>&nbsp;&nbsp;<strong>Non-Disclosure:</strong> The Consultant and the Client agree to adhere to the Non-Disclosure Agreement (NDA) signed between the parties as Annexure C.</p>
    </div>

    <!-- Clause 5 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">5.</span>&nbsp;&nbsp;<strong>Permission to share:</strong> The Client grants permission to the Consultant to share information about this engagement publicly or to relevant parties. Such sharing shall be limited to: (a) naming the Client as a client of the Consultant; (b) describing the nature of the engagement in general terms (e.g. &ldquo;brand and marketing services&rdquo;); and (c) referencing publicly available outcomes such as a launched website or published content. The Consultant shall not disclose fees, internal strategy, product roadmaps, financial information, or any other information that would constitute Confidential Information under Annexure C.</p>
    </div>

    <!-- Clause 6 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">6.</span>&nbsp;&nbsp;<strong>Termination</strong></p>
      <p class="cl-sub"><span class="cl-num">6.1.</span>&nbsp;&nbsp;<strong>With cause:</strong> The following conditions qualify as cause for termination. If any of these conditions apply, then the affected party may terminate the agreement with 10 days&rsquo; written notice if the concern is not addressed immediately.</p>
      <p class="cl-sub2"><span class="cl-num">6.1.1.</span>&nbsp;&nbsp;Wilful negligence by Consultant leading to business loss of Client.</p>
      <p class="cl-sub2"><span class="cl-num">6.1.2.</span>&nbsp;&nbsp;Breach of NDA by Consultant or Client.</p>
      <p class="cl-sub2"><span class="cl-num">6.1.3.</span>&nbsp;&nbsp;Change of senior management or change of control at the Client (defined as a change in majority ownership or acquisition by a third party).</p>
      <p class="cl-sub2"><span class="cl-num">6.1.4.</span>&nbsp;&nbsp;A material change in the Client&rsquo;s core business line.</p>
      <p class="cl-sub2"><span class="cl-num">6.1.5.</span>&nbsp;&nbsp;A hostile work environment is created by either the Consultant&rsquo;s or the Client&rsquo;s employees.</p>
      <p class="cl-sub"><span class="cl-num">6.2.</span>&nbsp;&nbsp;Either party may, by serving 30 (thirty) days advance notice in writing, terminate this Agreement without cause.</p>
      <p class="cl-sub"><span class="cl-num">6.3.</span>&nbsp;&nbsp;The Consultant will terminate the agreement on non-payment of fees and withhold delivery of services.</p>
      <p class="cl-sub"><span class="cl-num">6.4.</span>&nbsp;&nbsp;Upon termination for any reason, the Client shall pay all fees due and outstanding up to the effective date of termination. Any work product completed and delivered prior to termination shall remain the property of the Client.</p>
    </div>

    <!-- Clause 7 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">7.</span>&nbsp;&nbsp;<strong>Renewal/Extension:</strong> The contract may be extended with changes to the scope of work and payment terms as per mutual agreement.</p>
    </div>

    <!-- Clause 8 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">8.</span>&nbsp;&nbsp;<strong>Intellectual Property and Ownership:</strong></p>
      <p class="cl-sub"><span class="cl-num">8.1.</span>&nbsp;&nbsp;The Parties agree that Client shall have complete and sole ownership over any work product or Services performed by the Consultant under this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">8.2.</span>&nbsp;&nbsp;The Consultant hereby assigns and agrees to assign to Client, without royalty or any other consideration except as expressly set forth herein, all worldwide right, title and interest that the Consultant may have or acquire in and to Client, its successors, assignees, or nominees, the Receiving Party&rsquo;s right, title and interest, if any, in any patents, trade secrets, trademarks, copyrights, or other intellectual property rights or proprietary information embodied in or relating to Consultant&rsquo;s work under this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">8.3.</span>&nbsp;&nbsp;At Client&rsquo;s request, the Consultant hereby agrees to cooperate with Client and do all such actions and execute any documents necessary to give effect to the provisions of this section.</p>
      <p class="cl-sub"><span class="cl-num">8.4.</span>&nbsp;&nbsp;Notwithstanding the above, the Consultant retains ownership of its pre-existing methodologies, frameworks, tools, and general know-how that are not specific to the Client&rsquo;s business. The Consultant may reuse general approaches and learnings in engagements with other clients, provided no Confidential Information of the Client is disclosed.</p>
    </div>

    <!-- Clause 9 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">9.</span>&nbsp;&nbsp;<strong>Limitation of Liability:</strong></p>
      <p class="cl-sub"><span class="cl-num">9.1.</span>&nbsp;&nbsp;To the maximum extent permitted by applicable law, the Consultant&rsquo;s total aggregate liability to the Client under or in connection with this Agreement shall not exceed the total fees paid by the Client to the Consultant in the three (3) months immediately preceding the event giving rise to the claim.</p>
      <p class="cl-sub"><span class="cl-num">9.2.</span>&nbsp;&nbsp;In no event shall either party be liable to the other for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of revenue, loss of profits, loss of business, or loss of data, even if such party has been advised of the possibility of such damages.</p>
      <p class="cl-sub"><span class="cl-num">9.3.</span>&nbsp;&nbsp;Nothing in this clause shall limit either party&rsquo;s liability for fraud, wilful misconduct, or death or personal injury caused by negligence.</p>
    </div>

    <!-- Clause 10 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">10.</span>&nbsp;&nbsp;<strong>Non-Solicitation:</strong></p>
      <p class="cl-sub"><span class="cl-num">10.1.</span>&nbsp;&nbsp;During the term of this Agreement and for a period of twelve (12) months following its termination or expiry, the Client shall not, directly or indirectly, solicit, recruit, or hire any employee, contractor, or consultant of the Consultant who was involved in the delivery of services under this Agreement, without the prior written consent of the Consultant.</p>
      <p class="cl-sub"><span class="cl-num">10.2.</span>&nbsp;&nbsp;In the event of a breach of clause 10.1, the Client agrees to pay the Consultant a fee equivalent to six (6) months of the relevant individual&rsquo;s last applicable monthly billing rate as liquidated damages, which the Parties acknowledge is a genuine pre-estimate of loss.</p>
    </div>

    <!-- Clause 11 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">11.</span>&nbsp;&nbsp;<strong>Data Protection:</strong></p>
      <p class="cl-sub"><span class="cl-num">11.1.</span>&nbsp;&nbsp;Each party shall comply with all applicable data protection and privacy laws, including the Digital Personal Data Protection Act, 2023 (India) and any regulations made thereunder, in connection with the performance of this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">11.2.</span>&nbsp;&nbsp;The Consultant shall process personal data shared by the Client only to the extent necessary to perform the services under this Agreement, and shall not use such data for any other purpose.</p>
      <p class="cl-sub"><span class="cl-num">11.3.</span>&nbsp;&nbsp;Upon termination of this Agreement, or upon the Client&rsquo;s written request, the Consultant shall promptly delete or return all personal data provided by the Client, unless retention is required by applicable law.</p>
    </div>

    <!-- Clause 12 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">12.</span>&nbsp;&nbsp;<strong>Force Majeure:</strong> Neither party shall be liable for any delay or failure to perform its obligations under this Agreement to the extent that such delay or failure is caused by circumstances beyond that party&rsquo;s reasonable control, including but not limited to acts of God, natural disasters, pandemic, epidemic, war, civil unrest, government action, or failure of third-party infrastructure. The affected party shall notify the other party promptly and take all reasonable steps to minimise the impact of the force majeure event. If the force majeure event continues for more than thirty (30) days, either party may terminate this Agreement with immediate effect by written notice.</p>
    </div>

    <!-- Clause 13 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">13.</span>&nbsp;&nbsp;<strong>Governing Law and Dispute Resolution:</strong></p>
      <p class="cl-sub"><span class="cl-num">13.1.</span>&nbsp;&nbsp;This Agreement shall be governed by and construed in accordance with the laws of India.</p>
      <p class="cl-sub"><span class="cl-num">13.2.</span>&nbsp;&nbsp;In the event of any dispute, controversy, or claim arising out of or in connection with this Agreement, the Parties shall first attempt to resolve the matter through good-faith negotiation. Either party may initiate this process by giving written notice to the other party.</p>
      <p class="cl-sub"><span class="cl-num">13.3.</span>&nbsp;&nbsp;If the dispute is not resolved within thirty (30) days of such written notice (or such longer period as the Parties may agree), it shall be referred to and finally resolved by arbitration in accordance with the Arbitration and Conciliation Act, 1996. The arbitration shall be conducted by a sole arbitrator mutually appointed by the Parties. The seat and venue of arbitration shall be Bangalore, India. The language of arbitration shall be English.</p>
      <p class="cl-sub"><span class="cl-num">13.4.</span>&nbsp;&nbsp;Notwithstanding the above, either party may seek urgent injunctive or other equitable relief from a court of competent jurisdiction in Bangalore, India, where necessary to protect its rights pending the outcome of arbitration.</p>
    </div>

    <!-- Clause 14 -->
    <div class="clause">
      <p class="cl-head"><span class="cl-num">14.</span>&nbsp;&nbsp;<strong>Miscellaneous:</strong></p>
      <p class="cl-sub"><span class="cl-num">14.1.</span>&nbsp;&nbsp;<strong>Entire Agreement:</strong> This Agreement, and any annexures, duplicates, or copies, constitutes the entire agreement between the Parties concerning the subject matter of this Agreement and supersedes all prior negotiations, agreements, representations, and understandings of any kind, whether written or oral, between the Parties, preceding the date of this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">14.2.</span>&nbsp;&nbsp;<strong>Amendments and Assignment:</strong> This Agreement may be amended only by written agreement duly executed by an authorised representative of each party (email is acceptable). Either party shall not assign this Agreement without the express, written consent of the other party.</p>
      <p class="cl-sub"><span class="cl-num">14.3.</span>&nbsp;&nbsp;<strong>Severability:</strong> If any provision or provisions of this Agreement shall be held unenforceable for any reason, then such provision shall be modified to reflect the parties&rsquo; intention. All remaining provisions of this Agreement shall remain in full force and effect for the duration of this Agreement.</p>
      <p class="cl-sub"><span class="cl-num">14.4.</span>&nbsp;&nbsp;<strong>No Waiver:</strong> A failure or delay in exercising any right, power or privilege in respect of this Agreement will not be presumed to operate as a waiver, and a single or partial exercise of any right, power or privilege will not be presumed to preclude any subsequent or further exercise of that right, power or privilege or the exercise of any other right, power or privilege.</p>
      <p class="cl-sub"><span class="cl-num">14.5.</span>&nbsp;&nbsp;<strong>Survival:</strong> Clauses 8 (Intellectual Property), 9 (Limitation of Liability), 10 (Non-Solicitation), 11 (Data Protection), and 13 (Governing Law and Dispute Resolution) shall survive the termination or expiry of this Agreement.</p>
    </div>

    <!-- Signatures -->
    <p class="sig-intro">The agreement is electronically executed. Signatories:</p>
    <div class="sig-grid">
      <div class="sig-block">
        <?php if ($senderName):  ?><div class="sig-name"><?= esc($senderName) ?></div><?php endif; ?>
        <?php if ($senderTitle): ?><div class="sig-detail"><?= esc($senderTitle) ?></div><?php endif; ?>
        <div class="sig-detail">Corebook Consulting Pvt. Ltd.</div>
        <?php if ($senderEmail): ?><div class="sig-detail"><?= esc($senderEmail) ?></div><?php endif; ?>
      </div>
      <div class="sig-block">
        <?php if ($sigName):  ?><div class="sig-name"><?= esc($sigName) ?></div><?php endif; ?>
        <?php
          $clientSigLine = array_filter([$sigTitle, $co]);
          if ($clientSigLine): ?>
          <div class="sig-detail"><?= esc(implode(', ', $clientSigLine)) ?></div>
        <?php endif; ?>
        <?php if ($sigEmail): ?><div class="sig-detail"><?= esc($sigEmail) ?></div><?php endif; ?>
      </div>
    </div>

    <!-- ══════════ ANNEXURE A ══════════ -->
    <div class="ann-header">
      <div class="ann-tag">Annexure A</div>
      <div class="ann-title">Scope of Work</div>
    </div>

    <?php if ($engLabel): ?>
      <div class="ann-kv"><strong>Engagement type:</strong> <?= esc($engLabel) ?></div>
    <?php endif; ?>

    <?php if ($objective): ?>
      <div class="ann-kv" style="margin-top:14px;"><strong>Objective:</strong></div>
      <div class="ann-obj"><?= esc($objective) ?></div>
    <?php endif; ?>

    <?php if ($scopeItems): ?>
    <div class="ann-services-head">Activities / Services:</div>

    <?php if ($strategyScope): ?>
      <div class="ann-cat-head">Strategy</div>
      <ul class="ann-cat-list">
        <?php foreach ($strategyScope as $item): ?>
          <li><?= scopeContractLabel($item) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($contentScope): ?>
      <div class="ann-cat-head">Content</div>
      <ul class="ann-cat-list">
        <?php foreach ($contentScope as $item): ?>
          <li><?= scopeContractLabel($item) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($opsScope): ?>
      <div class="ann-cat-head">Marketing ops</div>
      <ul class="ann-cat-list">
        <?php foreach ($opsScope as $item): ?>
          <li><?= scopeContractLabel($item) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($addlScope): ?>
      <div class="ann-addl-head">Additional notes</div>
      <div class="ann-addl-text"><?= esc($addlScope) ?></div>
    <?php endif; ?>

    <div class="ann-gov-head">Governance:</div>
    <ul class="ann-gov-list">
      <li>Weekly &ldquo;Client Promises&rdquo; meeting + email for: reporting done/not-done on tasks, and setting tasks for the upcoming week</li>
      <li>Monthly meetings: review the previous period and set objectives for the upcoming period</li>
      <li>Quarterly meetings: impact review and agenda setting for the upcoming quarter</li>
    </ul>
    <div class="ann-gov-note">Note: The exact count of deliverables under each activity will be decided in governance calls based on live requirements. All items listed above are subject to change as per requirement changes noted in governance calls.</div>

    <?= renderSigBlock($senderName, $senderTitle, $senderEmail, $sigName, $sigTitle, $co, $sigEmail) ?>

    <!-- ══════════ ANNEXURE B ══════════ -->
    <div class="ann-header">
      <div class="ann-tag">Annexure B</div>
      <div class="ann-title">Fee &amp; Terms of Payment</div>
    </div>

    <div class="ann-b-head">1. Payment terms</div>
    <ul class="ann-b-terms">
      <?php if ($feeType === 'retainer'): ?>
        <?php if ($monthlyFee): ?><li>Monthly fee: <?= fmtMoney($monthlyFee, $currCode) ?> + <?= $currCode === 'INR' ? 'GST' : 'tax' ?> per month</li><?php endif; ?>
        <?php if ($retDur):     ?><li>Period: For <?= esc($retDur) ?></li><?php endif; ?>
        <li>All invoices to be raised on the first day of each month</li>
        <li>All invoices to be paid within <?= esc($payDays) ?> of the invoice being raised</li>
      <?php elseif ($feeType === 'fixed'): ?>
        <?php if ($totalFee):   ?><li>Fixed fee: <?= fmtMoney($totalFee, $currCode) ?> + <?= $currCode === 'INR' ? 'GST' : 'tax' ?></li><?php endif; ?>
        <?php if ($fixedAdv):   ?><li>Advance payable: <?= fmtMoney($fixedAdv, $currCode) ?></li><?php endif; ?>
        <li>All invoices to be paid within <?= esc($fixPayDays) ?> of the invoice being raised</li>
      <?php elseif ($feeType === 'milestone'): ?>
        <?php if ($milestones): ?><li style="white-space:pre-wrap;"><?= esc($milestones) ?></li><?php endif; ?>
      <?php endif; ?>
      <li>Consultancy has the right to slow down work when payment is delayed</li>
      <?php if ($opeAnnex): ?><li><?= esc($opeAnnex) ?></li><?php endif; ?>
      <?php if ($payNotes):  ?><li><?= esc($payNotes) ?></li><?php endif; ?>
    </ul>

    <div class="ann-b-head">2. Company and bank details</div>
    <table class="bank-table">
      <tbody>
        <tr><td>Name</td><td>CoreBook Consulting Pvt Ltd</td></tr>
        <tr><td>Registered address</td><td>Park Vista B Block, F No. 503, Amblipura, Sarjapur Road, Bangalore, Karnataka 560037</td></tr>
        <tr><td>Incorporation date</td><td>04 Jan 2023</td></tr>
        <tr><td>CIN</td><td>U74999KA2023PTC169895</td></tr>
        <tr><td>PAN</td><td>AAKCC8142N</td></tr>
        <tr><td>GST</td><td>29AAKCC8142N1Z0</td></tr>
        <tr><td>Bank account no.</td><td>4090019050053</td></tr>
        <tr><td>Bank IFSC</td><td>RATN0000091</td></tr>
      </tbody>
    </table>

    <?= renderSigBlock($senderName, $senderTitle, $senderEmail, $sigName, $sigTitle, $co, $sigEmail) ?>

    <!-- ══════════ ANNEXURE C ══════════ -->
    <div class="ann-header">
      <div class="ann-tag">Annexure C</div>
      <div class="ann-title">Mutual Non-Disclosure Agreement</div>
    </div>

    <p class="nda-preamble">This Mutual Non-Disclosure Agreement (the &ldquo;Agreement&rdquo;) is entered into on Agreement Date at Bangalore, between the Client &amp; the Consultancy &mdash; hereinafter referred to individually as &ldquo;Party&rdquo; and collectively as &ldquo;Parties&rdquo;.</p>
    <p class="nda-preamble">The Party disclosing Confidential Information to the other shall be referred to as &ldquo;Discloser&rdquo;, while the Party receiving such information shall be referred to as the &ldquo;Recipient&rdquo;, as the context may require.</p>

    <div class="nda-section">
      <span class="nda-num">1.&nbsp;&nbsp;Background.</span> The Parties intend to establish a proposed business relationship between them. In the course of such a relationship, it is anticipated that the Discloser may disclose or deliver to the Recipient certain Confidential Information as defined in Section 2 hereof, for the limited purpose of such proposed business relationship. The Parties have entered into this Agreement in order to assure the confidentiality of such confidential information in accordance with the terms of this Agreement.
    </div>

    <div class="nda-section">
      <span class="nda-num">2.&nbsp;&nbsp;Definition of Confidential Information.</span> &ldquo;Confidential Information&rdquo; as used in this Agreement shall mean any and all technical and non-technical information belonging to the Discloser including but not limited to patent, copyright, trade secret, and proprietary information, techniques, sketches, drawings, models, inventions, know-how, processes, apparatus, equipment, algorithms, software, software programs, software source documents, designs, drawings, sketches and formulae related to the current, future, and proposed products and services of each of the Parties, and includes, without limitation, their respective information concerning research, experimental work, development, design details and specifications, engineering, financial information, procurement requirements, purchasing, manufacturing, business forecasts, sales and merchandising, marketing plans and information, Client/customer/vendor related information (whether commercial or otherwise) and documentation. Such information disclosed by the Discloser shall be considered Confidential Information by the Recipient, whether communicated orally, in writing or otherwise, and which is designated as confidential or which by nature would reasonably be considered confidential.
    </div>

    <div class="nda-section">
      <span class="nda-num">3.&nbsp;&nbsp;Nondisclosure and Non Use Obligation.</span> Recipient agrees that it shall not make use of, disseminate, or in any way disclose any Confidential Information of the Discloser to any person, firm, or business, except to the extent necessary for negotiations, discussions, and consultations with personnel or authorised representatives of the Recipient, and any purpose the other Party may hereafter authorise in writing. Furthermore, the existence of any business negotiations, discussions, consultations, test results, reports or agreements in progress between the Parties shall not be released to any form of public media without the written approval of the Discloser. Recipient agrees that it shall treat all Confidential Information of the Discloser with the same degree of care as it accords to its own Confidential Information.
    </div>

    <div class="nda-section">
      <span class="nda-num">4.&nbsp;&nbsp;Exclusions.</span> Recipient&rsquo;s obligations under Section 3 shall terminate when the Recipient can document that: (i) the information was in the public domain; (ii) it was rightfully in Recipient&rsquo;s possession free of obligation of confidence; (iii) it was developed by employees or agents of Recipient independently and without reference to any information communicated to Recipient by Discloser; (iv) the communication was in response to a valid order by a court or other governmental body, was otherwise required by law, or was necessary to establish the rights of either Party under this Agreement.
    </div>

    <div class="nda-section">
      <span class="nda-num">5.&nbsp;&nbsp;Ownership of Confidential Information.</span> All Confidential Information and any Derivatives thereof, whether created by Discloser or Recipient, shall remain the property of Discloser and no license or other right to Confidential Information is granted or implied hereby. However, all intellectual property including Derivatives thereof, in and to any Deliverables delivered by the Consultancy to the Client shall be deemed to be assigned to the Client by the Consultancy.
    </div>

    <div class="nda-section">
      <span class="nda-num">6.&nbsp;&nbsp;Return or Destruction of Materials.</span> Upon termination or expiry of the Agreement, or upon written request by the Discloser, the Recipient shall promptly return or, at the Discloser&rsquo;s election, destroy all Confidential Information and any copies thereof in the Recipient&rsquo;s possession or control. The Recipient shall, upon request, certify in writing that such return or destruction has been completed. Notwithstanding the foregoing, the Recipient may retain copies of Confidential Information to the extent required by applicable law or regulation, provided that such retained information remains subject to the obligations of this Agreement.
    </div>

    <div class="nda-section">
      <span class="nda-num">7.&nbsp;&nbsp;No Warranty.</span> All Confidential Information is provided &ldquo;AS IS&rdquo; and without any warranty, express, implied, or otherwise, regarding its accuracy or performance or completeness of the Confidential Information or any warranty that the use of the Confidential Information will not infringe or violate any patent or other proprietary rights of a third party.
    </div>

    <div class="nda-section">
      <span class="nda-num">8.&nbsp;&nbsp;Term and Survival.</span> The obligations of confidentiality and non-use under this Agreement shall remain in force during the term of the Agreement and for a period of one (1) year following its termination or expiry, regardless of the reason for termination. The obligations of this Agreement shall survive termination or expiry of the main Agreement.
    </div>

    <?= renderSigBlock($senderName, $senderTitle, $senderEmail, $sigName, $sigTitle, $co, $sigEmail) ?>

  </div><!-- /con-body -->

<?php endif; ?>

</div><!-- /page -->
</body>
</html>
