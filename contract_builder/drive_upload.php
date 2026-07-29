<?php
// drive_upload.php — upload a PDF to a Google Drive folder as a service account.
// No Google client library — just a hand-rolled JWT (RS256, signed with the
// service account's private key) exchanged for an OAuth2 access token.

function drive_get_access_token(): string {
    $keyPath = GOOGLE_SERVICE_ACCOUNT_KEY_PATH;
    $key = json_decode(file_get_contents($keyPath), true);
    if (!$key || empty($key['private_key']) || empty($key['client_email'])) {
        throw new RuntimeException('Invalid service account key file at ' . $keyPath);
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];
    $b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $signingInput = $b64(json_encode($header)) . '.' . $b64(json_encode($claims));

    $signature = '';
    $ok = openssl_sign($signingInput, $signature, $key['private_key'], 'SHA256');
    if (!$ok) throw new RuntimeException('Failed to sign JWT with service account key');
    $jwt = $signingInput . '.' . $b64($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if ($status !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('Google token exchange failed: ' . $resp);
    }
    return $data['access_token'];
}

// Uploads $localPdfPath into $folderId, named $filename.
// Returns ['id' => ..., 'webViewLink' => ...].
function drive_upload_pdf(string $localPdfPath, string $filename, string $folderId): array {
    $accessToken = drive_get_access_token();

    $metadata = json_encode(['name' => $filename, 'parents' => [$folderId]]);
    $boundary = 'cvdrive' . bin2hex(random_bytes(8));
    $pdfData = file_get_contents($localPdfPath);
    if ($pdfData === false) throw new RuntimeException('Could not read generated PDF at ' . $localPdfPath);

    $body = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $metadata . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: application/pdf\r\n\r\n"
        . $pdfData . "\r\n"
        . "--$boundary--";

    $url = 'https://www.googleapis.com/upload/drive/v3/files'
        . '?uploadType=multipart&supportsAllDrives=true&fields=id,webViewLink';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$resp, true);
    if ($status !== 200 || empty($data['id'])) {
        throw new RuntimeException('Drive upload failed: ' . $resp);
    }
    return $data;
}
