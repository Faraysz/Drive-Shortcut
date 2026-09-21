<?php
require_once __DIR__ . '/config.php';

/**
 * Ambil file ID dari berbagai format link share Google Drive.
 * Mendukung:
 *  - https://drive.google.com/file/d/FILE_ID/view?usp=sharing
 *  - https://drive.google.com/open?id=FILE_ID
 *  - https://drive.google.com/uc?id=FILE_ID&export=download
 *  - Atau langsung ID mentah.
 */
function extract_file_id(string $input): ?string
{
    $input = trim($input);

    // Kalau user paste ID mentah saja (huruf/angka/-/_ minimal 15 karakter)
    if (preg_match('/^[a-zA-Z0-9_-]{15,}$/', $input)) {
        return $input;
    }

    $patterns = [
        '#/file/d/([a-zA-Z0-9_-]{15,})#',   // /file/d/ID/view
        '#[?&]id=([a-zA-Z0-9_-]{15,})#',    // ?id=ID
        '#/d/([a-zA-Z0-9_-]{15,})#',        // /d/ID
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input, $m)) {
            return $m[1];
        }
    }

    return null;
}

/**
 * Panggil Google Drive API v3 untuk ambil metadata file publik.
 * Return array (name, mimeType, size) atau null kalau gagal / file tidak publik.
 */

function fetch_file_metadata(string $fileId): ?array
{
    
    $url = 'https://www.googleapis.com/drive/v3/files/' . urlencode($fileId)
        . '?fields=' . urlencode('id,name,mimeType,size,iconLink,thumbnailLink')
        
        . '&key=' . urlencode(GOOGLE_API_KEY);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => CURL_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['id'])) {
        return null;
    }

    return [
        'id' => $data['id'],
        'name' => $data['name'] ?? 'file_unknown',
        'mimeType' => $data['mimeType'] ?? 'application/octet-stream',
        'size' => isset($data['size']) ? (int) $data['size'] : null,
        'iconLink' => $data['iconLink'] ?? null,
        'thumbnailLink' => $data['thumbnailLink'] ?? null,
    ];
}
/**
 * Cek apakah endpoint mendukung Range request dan berapa total size-nya.
 * Return [supportsRange(bool), contentLength(int|null)]
 */
function probe_range_support(string $url, array $extraHeaders = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => CURL_TIMEOUT,
        CURLOPT_HTTPHEADER => array_merge(['Range: bytes=0-0'], $extraHeaders),
    ]);
    $headerText = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $supportsRange = ($httpCode === 206);
    $totalSize = null;

    if (preg_match('/Content-Range:\s*bytes\s+\d+-\d+\/(\d+)/i', $headerText, $m)) {
        $totalSize = (int) $m[1];
    } elseif (preg_match('/Content-Length:\s*(\d+)/i', $headerText, $m) && !$supportsRange) {
        $totalSize = (int) $m[1];
    }

    return [$supportsRange, $totalSize];
}

/**
 * Metode fallback ala gdown: bypass halaman peringatan virus-scan Google
 * untuk file publik berukuran besar, dengan cara mengikuti cookie
 * "download_warning" lalu request ulang dengan token confirm.
 * Return URL final untuk didownload langsung (bukan file ID API).
 */
function resolve_uc_download_url(string $fileId, string $cookieFile): string
{
    $baseUrl = 'https://drive.google.com/uc?export=download&id=' . urlencode($fileId);

    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => CURL_TIMEOUT,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    // Cari confirm token dari cookie yang tersimpan
    $confirmToken = null;
    if (file_exists($cookieFile)) {
        $cookies = file_get_contents($cookieFile);
        if (preg_match('/download_warning\S*\s+(\S+)/', $cookies, $m)) {
            $confirmToken = $m[1];
        }
    }

    // Cara baru: cari confirm token di dalam form HTML halaman peringatan
    if (!$confirmToken && $body && preg_match('/confirm=([0-9A-Za-z_-]+)/', $body, $m)) {
        $confirmToken = $m[1];
    }

    if ($confirmToken) {
        return $baseUrl . '&confirm=' . urlencode($confirmToken);
    }

    return $baseUrl;
}

function human_filesize(?int $bytes): string
{
    if ($bytes === null) {
        return 'Tidak diketahui';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $size = $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
