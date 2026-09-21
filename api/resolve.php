<?php
require_once __DIR__ . '/helpers.php';

header('Access-Control-Allow-Origin: *');

$link = $_GET['link'] ?? $_POST['link'] ?? '';

if (empty($link)) {
    json_response(['error' => 'Link tidak boleh kosong.'], 400);
}

$fileId = extract_file_id($link);

if (!$fileId) {
    json_response(['error' => 'Link Google Drive tidak valid atau tidak dikenali.'], 400);
}

if (GOOGLE_API_KEY === 'TEMPEL_API_KEY_KAMU_DI_SINI') {
    json_response(['error' => 'GOOGLE_API_KEY belum diatur di api/config.php. Lihat komentar di file itu untuk cara mendapatkannya.'], 500);
}

$meta = fetch_file_metadata($fileId);

if (!$meta) {
    json_response([
        'error' => 'File tidak ditemukan atau belum dibagikan sebagai "Anyone with the link". Pastikan sharing sudah diatur publik.',
    ], 404);
}

json_response([
    'success' => true,
    'file' => [
        'id' => $meta['id'],
        'name' => $meta['name'],
        'mimeType' => $meta['mimeType'],
        'size' => $meta['size'],
        'sizeHuman' => human_filesize($meta['size']),
        'iconLink' => $meta['iconLink'],
        'thumbnailLink' => $meta['thumbnailLink'],
    ],
]);
