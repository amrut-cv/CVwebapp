<?php
require __DIR__ . '/../session_guard.php';
$nav_active = 'contracts';

// Inject nav into the contract builder HTML
$html = file_get_contents(__DIR__ . '/index.html');

// Fix generate.php action path (already relative, stays as-is)
// Wrap body content with layout
$nav_html = '<?php require __DIR__ . \'/../nav.php\'; ?>';

ob_start();
require __DIR__ . '/../nav.php';
$nav_output = ob_get_clean();

$layout_open  = '<div class="cv-layout">' . $nav_output;
$layout_close = '</div>';

// Inject layout wrapper inside <body>
$html = preg_replace('/(<body[^>]*>)/i', '$1' . $layout_open, $html, 1);
$html = str_replace('</body>', $layout_close . '</body>', $html);

// Make the main content aware of the sidebar offset
$html = str_replace(
    '</head>',
    '<style>.cv-main { padding: 0; } body > .cv-layout > .cv-main { min-width: 0; }</style></head>',
    $html
);

echo $html;
