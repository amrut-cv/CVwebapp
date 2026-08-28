<?php
// guide_render.php — renders the "Guest Guide and Consent Form" as a standalone
// print-ready HTML document for one guest row. Shared by generate_guide.php
// (direct browser view) and save_guide_to_drive.php (captured for PDF export),
// mirroring the contract_builder/generate.php pattern.

function cf_guide_esc($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

function render_guest_guide(array $g): string {
    $esc     = 'cf_guide_esc';
    $name    = $g['guest_name'] ?? '';
    $company = $g['company_title'] ?? '';
    $topic   = $g['episode_topic'] ?? '';
    $recDate = !empty($g['recording_date']) ? date('j F Y', strtotime($g['recording_date'])) : '';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= $esc('Guest Guide — ' . ($name ?: 'Stories That Founders Tell')) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Georgia', 'Times New Roman', serif;
    font-size: 10.5pt;
    line-height: 1.7;
    color: #1c1c2e;
    background: #f0f2f5;
  }

  .page {
    max-width: 760px;
    margin: 32px auto 80px;
    background: #fff;
    box-shadow: 0 4px 32px rgba(0,0,0,.10);
    border-radius: 3px;
    padding: 56px 60px;
  }

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

  h1 {
    font-size: 1.5rem; font-weight: 700; line-height: 1.3;
    margin-bottom: 6px;
  }
  h1 em { font-style: italic; color: #C9972A; }
  .lead {
    font-family: 'Segoe UI', sans-serif; font-size: .82rem;
    color: #6b7280; font-style: italic; margin-bottom: 28px;
  }
  h2 {
    font-size: 1.05rem; font-weight: 700; margin: 28px 0 10px;
    padding-bottom: 6px; border-bottom: 1px solid #e8e8f0;
  }
  p { margin-bottom: 10px; }
  ul, ol { margin: 0 0 10px 22px; }
  li { margin-bottom: 6px; }
  a { color: #C9972A; }

  .guest-box {
    font-family: 'Segoe UI', sans-serif; font-size: .85rem;
    background: #fdf6e8; border: 1px solid #f0e0b8; border-radius: 8px;
    padding: 14px 18px; margin-bottom: 26px;
  }
  .guest-box div { margin-bottom: 4px; }
  .guest-box div:last-child { margin-bottom: 0; }

  .sig-block { font-size: .87rem; line-height: 2; margin-top: 24px; }
  .sig-line { display: flex; gap: 8px; margin-bottom: 22px; }
  .sig-label { font-weight: 700; white-space: nowrap; }
  .sig-fill { flex: 1; border-bottom: 1px solid #c7c9d4; min-height: 1.4em; }

  @page { size: A4; margin: 1.5cm; }
  @media print {
    body { background: #fff; }
    .page { box-shadow: none; margin: 0; padding: 0; max-width: none; }
    .toolbar { display: none !important; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn-back"  onclick="window.close()">&#8592; Back</button>
  <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

<div class="page">
  <h1>Guest Guide and Consent Form for <em>Stories That Founders Tell</em></h1>
  <p class="lead">This document is a reference document for prospective and confirmed guests of Stories That Founders Tell, produced by CoreVoice.</p>

  <?php if ($name || $topic || $recDate): ?>
  <div class="guest-box">
    <?php if ($name): ?><div><strong>Guest:</strong> <?= $esc($name) ?><?= $company ? ' — ' . $esc($company) : '' ?></div><?php endif; ?>
    <?php if ($topic): ?><div><strong>Episode topic:</strong> <?= $esc($topic) ?></div><?php endif; ?>
    <?php if ($recDate): ?><div><strong>Recording date:</strong> <?= $esc($recDate) ?></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <h2>1. Who Are We?</h2>
  <p>Stories That Founders Tell is produced by CoreVoice. We are a deep-tech focussed full-service marketing services firm based out of Bangalore.</p>
  <p><strong>Hosted by:</strong> Amrut (Amrutash Misra), promoter, CoreVoice; and ex-host of IIT Madras' Best Place to Build podcast.</p>

  <h2>2. What Is the Podcast About?</h2>
  <p>We have conceived of this podcast based on our learnings with the Best Place to Build podcast experience. We produced over 50 episodes with over 75k subscribers and a few million views. A few things worked really well, and a few things bombed completely.</p>
  <p>Stories That Founders Tell explores the narratives that founders use everyday.</p>
  <ul>
    <li><strong>Format:</strong> 1:1 Interview</li>
    <li><strong>Typical episode length:</strong> ~30 mins</li>
    <li><strong>Recording setup:</strong> Option for in-person in our studio or remote (details later)</li>
    <li><strong>Release cadence:</strong> Weekly</li>
    <li><strong>Where it airs:</strong> YouTube, Spotify, Apple Podcasts</li>
  </ul>

  <h2>3. What Type of Guests Are We Planning to Invite?</h2>
  <p>We invite guests who are building startups in the tech / deep-tech space, with a bias towards those who can speak confidently and coherently.</p>
  <p>Friends of CoreVoice are given preference.</p>

  <h2>4. How Does One Get Invited?</h2>
  <p>There are three ways to get into the invite list — (a) We invite a founder, (b) A founder gets recommended to us via someone we trust, or (c) The founder/team writes to us at amrut@corevoice.in. In the second and third case, our host will evaluate the request to verify a match.</p>
  <p><strong>There is no paid way to get into the invite list. This is a strictly curated podcast.</strong></p>

  <h2>5. How Does One Prepare Beforehand?</h2>
  <p>Once you're confirmed, here's what to expect and how to get ready:</p>
  <ul>
    <li><strong>Questions:</strong> There are only 4 questions. We expect you to be prepared for them, and be prepared for follow up questions on your answers.
      <ul>
        <li><strong>Q1: What is the story you tell your customers?</strong> Ideally you should explain who your customers are, what are their problems, what is your solution, why they should believe your solution, etc.</li>
        <li><strong>Q2: What is the story you tell your investors?</strong> Here you can speak about the overall business opportunity, why now is a good time, what investors looking into this space or your company can expect, etc. Please note this is not Shark Tank, so be real — no drama is expected from you.</li>
        <li><strong>Q3: What is the story you tell your employees?</strong> Mention why people join you, take examples, talk about growth opportunities, etc.</li>
        <li><strong>Q4: What is the story you tell yourself?</strong> This is left to you — an opportunity for you to get philosophical.</li>
      </ul>
    </li>
  </ul>
  <p>While the lines of inquiry are set, it is up to you to guide the conversation to the most interesting parts of your business. If you can hold a good conversation, then we may stretch the recording to 90 minutes. Inform us beforehand if there is anything specific you want to cover.</p>
  <ul>
    <li><strong>Preparing for the studio recording:</strong>
      <ul>
        <li>We have a mini studio in Koramangala. We expect you to come well dressed.</li>
        <li>Carry a change of clothes and personal hygiene items like comb. We will give you access to a washroom.</li>
        <li>Refreshments are available at the venue.</li>
        <li>Koramangala is in the heart of some heavy traffic. Please budget time for that.</li>
      </ul>
    </li>
    <li><strong>Preparing for the remote recording:</strong>
      <ul>
        <li>As of now remote recording is available only if you have an iPhone and a MacBook as we will use the continuity camera setup. If this option was made available to you, then we will send separate instructions to you.</li>
      </ul>
    </li>
  </ul>

  <h2>6. What Needs to Be Done After the Shoot?</h2>
  <p>In our experience over 50+ podcast shoots, our number 1 gripe has been that the guest has missed the marketing opportunity by missing out on post-shoot tasks. So, please, read this section — this is important for you.</p>
  <ul>
    <li><strong>Editorial review:</strong> We offer no review to the guest (sorry)</li>
    <li><strong>Release timeline:</strong> Typically 1–3 weeks</li>
    <li><strong>Promotion:</strong>
      <ul>
        <li>We will give you 1 pre-release promotional reel for you and your company to share on your social media. You may release this 2 days before the actual podcast release.</li>
        <li>On the podcast release date, we will release all content on social media and podcast channels. Wherever the channel allows, we will collaborate with you — you have to accept our collaborations for them to show to your audiences.</li>
        <li>After release, you may promote the podcast within your company, to your stakeholders (clients, investors, family, etc), and on social media.</li>
        <li>We will continue to release reels for a few weeks — based on how well the podcast is performing.</li>
        <li>We will also do paid promotions for the main episode.</li>
        <li>If the podcast does well, it benefits you. In Best Place to Build, the podcasts where the guests got involved and did their best to push to their audiences, we had 100K+ views. But when the guest was "too busy" to promote, that episode got only 1K+ views. It's a 100x difference.</li>
      </ul>
    </li>
    <li><strong>Engage as much as you can:</strong>
      <ul>
        <li>If we are getting comments on YouTube / other channels — please engage with the comments. It will help you.</li>
      </ul>
    </li>
    <li><strong>Marketing team:</strong>
      <ul>
        <li>Do you have a marketing team? Introduce us to them so that we can collaborate and you can get the best outcome.</li>
      </ul>
    </li>
  </ul>

  <h2>7. Is There a Payment Involved?</h2>
  <p>If you are invited, then there is no payment expected from you. Nor do we accept any payment. The guest list is entirely curated.</p>
  <p>However, we have a few additional services offered upon payment.</p>
  <ol>
    <li>We can cut a set of 1–4 videos/reels as per your requirements for your use from the main footage. This will cost Rs. 40,000. Inform us beforehand if you want this.</li>
    <li>We will be running paid promotions as per our budget. Upon payment, we can increase the budget. For example, we might spend Rs. 20,000 for promotions on ad platforms. If you wish, we can increase this to Rs. 50,000 and pass on the additional Rs. 30,000 cost to you. You can specify the amount you wish for us to spend.</li>
  </ol>

  <h2>8. Consent &amp; Release Form</h2>
  <p><strong>Stories That Founders Tell — Guest Consent &amp; Release Form</strong></p>
  <p>By signing below, I confirm that:</p>
  <ol>
    <li>I have read the Guest Guide.</li>
    <li>I consent to being recorded (audio/video) for Stories That Founders Tell, produced by CoreVoice.</li>
    <li>I grant CoreVoice a non-exclusive, worldwide, royalty-free license to edit, reproduce, publish, and distribute this recording — in full or in part — across podcast platforms, social media, CoreVoice's website, and other promotional channels, in perpetuity, unless otherwise agreed in writing.</li>
    <li>I understand the recording may be edited for length, clarity, or content, and that I will not have the opportunity to review the final cut before publication as mentioned in the Guest Guide.</li>
    <li>I retain the right to be identified by name and title as they appear in the episode, unless I request otherwise in advance.</li>
    <li>I confirm that I am participating voluntarily and have not been coerced or misled about the nature or use of this content.</li>
  </ol>

  <div class="sig-block">
    <div class="sig-line"><span class="sig-label">Guest Name:</span><span class="sig-fill"><?= $esc($name) ?></span></div>
    <div class="sig-line"><span class="sig-label">Signature:</span><span class="sig-fill"></span></div>
    <div class="sig-line"><span class="sig-label">Date:</span><span class="sig-fill"></span></div>
    <div class="sig-line"><span class="sig-label">CoreVoice Representative:</span><span class="sig-fill"></span></div>
  </div>
</div>

</body>
</html>
    <?php
    return ob_get_clean();
}
