<?php
require_once __DIR__ . '/../session_guard.php';
require_module_access('guest_tracker');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../drive_secrets.php';
require_once __DIR__ . '/../contract_builder/drive_upload.php';
require __DIR__ . '/guide_render.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$id = (int)($_POST['guest_id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing guest_id']); exit; }

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
$stmt->execute([$id]);
$guest = $stmt->fetch();
if (!$guest) { http_response_code(404); echo json_encode(['error' => 'Guest not found']); exit; }

$html = render_guest_guide($guest);

$tmpBase = tempnam(sys_get_temp_dir(), 'cvguide_');
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

    $filename = preg_replace('/\s+/', ' ', trim('Stories That Founders Tell - Guide and Consent - ' . ($guest['guest_name'] ?: 'Guest'))) . '.pdf';

    $existingFileId = trim((string)($_POST['driveFileId'] ?? ''));
    if ($existingFileId) {
        try {
            $file = drive_update_pdf($existingFileId, $pdfPath, $filename);
        } catch (DriveFileNotFoundException $e) {
            $file = drive_upload_pdf($pdfPath, $filename, GOOGLE_DRIVE_GUEST_GUIDE_FOLDER_ID);
        }
    } else {
        $file = drive_upload_pdf($pdfPath, $filename, GOOGLE_DRIVE_GUEST_GUIDE_FOLDER_ID);
    }

    echo json_encode(['ok' => true, 'webViewLink' => $file['webViewLink'] ?? null, 'fileId' => $file['id'] ?? null, 'filename' => $filename]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (is_file($htmlPath)) unlink($htmlPath);
    if (is_file($pdfPath))  unlink($pdfPath);
}
