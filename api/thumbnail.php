<?php
require_once __DIR__ . '/helpers.php';

$fileId = $_GET['id'] ?? '';
if (!$fileId || !preg_match('/^[a-zA-Z0-9_-]{15,}$/', $fileId)) {
    http_response_code(400);
    exit;
}

$meta = fetch_file_metadata($fileId);
if (!$meta || empty($meta['thumbnailLink'])) {
    http_response_code(404);
    exit;
}

// Minta ukuran lebih besar dari default (biasanya diakhiri =s220)
$thumbUrl = preg_replace('/=s\d+$/', '=s400', $meta['thumbnailLink']);

$ch = curl_init($thumbUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => CURL_TIMEOUT,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'image/jpeg';
curl_close($ch);

if ($httpCode !== 200 || !$imageData) {
    http_response_code(502);
    exit;
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=3600');
echo $imageData;