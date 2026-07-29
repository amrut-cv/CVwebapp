<?php
require_once __DIR__ . '/../session_guard.php';
require_once __DIR__ . '/../drive_secrets.php';
require_once __DIR__ . '/drive_upload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

// Renders the exact same document generate.php would for this POST data,
// but captured to a string instead of sent to the browser.
ob_start();
require __DIR__ . '/generate.php';
$html = ob_get_clean();

$tmpBase = tempnam(sys_get_temp_dir(), 'cvcontract_');
$htmlPath = $tmpBase . '.html';
$pdfPath  = $tmpBase . '.pdf';
unlink($tmpBase);
file_put_contents($htmlPath, $html);

try {
    $cmd = sprintf(
        'timeout 30 google-chrome-stable --headless --disable-gpu --no-sandbox ' .
        '--print-to-pdf=%s --no-pdf-header-footer --virtual-time-budget=10000 %s 2>&1',
        escapeshellarg($pdfPath),
        escapeshellarg('file://' . $htmlPath)
    );
    $output = shell_exec($cmd);

    if (!is_file($pdfPath) || filesize($pdfPath) === 0) {
        throw new RuntimeException('PDF rendering failed: ' . $output);
    }

    $docLabel = $isProposal ? 'Proposal' : 'Contract';
    $dateStr  = $agreeDate ? fmtDate($agreeDate) : date('j F Y');
    $nameParts = array_filter([$co ?: 'Client', $engLabel ?: null, $docLabel, $dateStr]);
    $filename = preg_replace('/\s+/', ' ', implode(' - ', $nameParts)) . '.pdf';

    $file = drive_upload_pdf($pdfPath, $filename, GOOGLE_DRIVE_FOLDER_ID);

    echo json_encode(['ok' => true, 'webViewLink' => $file['webViewLink'] ?? null, 'filename' => $filename]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (is_file($htmlPath)) unlink($htmlPath);
    if (is_file($pdfPath))  unlink($pdfPath);
}
