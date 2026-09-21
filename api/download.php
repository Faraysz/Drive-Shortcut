<?php
require_once __DIR__ . '/helpers.php';

set_time_limit(0);

$fileId = $_GET['id'] ?? '';
if (!$fileId || !preg_match('/^[a-zA-Z0-9_-]{15,}$/', $fileId)) {
    http_response_code(400);
    die('File ID tidak valid.');
}

$meta = fetch_file_metadata($fileId);
if (!$meta) {
    http_response_code(404);
    die('File tidak ditemukan / tidak publik.');
}

$filename = $meta['name'];
$mimeType = $meta['mimeType'] ?: 'application/octet-stream';

// --- Tentukan URL sumber unduhan --------------------------------------
// Prioritas 1: Drive API v3 alt=media (mendukung Range, resmi, stabil)
$apiUrl = 'https://www.googleapis.com/drive/v3/files/' . urlencode($fileId)
    . '?alt=media&key=' . urlencode(GOOGLE_API_KEY);

[$supportsRange, $totalSize] = probe_range_support($apiUrl);
$downloadUrl = $apiUrl;
$extraHeaders = [];

// Prioritas 2 (fallback): endpoint uc?export=download ala gdown,
// dipakai kalau API key tidak punya akses alt=media untuk file ini
// (kadang terjadi tergantung setting sharing).
if ($totalSize === null) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'gdcookie_');
    $downloadUrl = resolve_uc_download_url($fileId, $cookieFile);
    [$supportsRange, $totalSize] = probe_range_support($downloadUrl);
    register_shutdown_function(function () use ($cookieFile) {
        if (file_exists($cookieFile)) {
            unlink($cookieFile);
        }
    });
}

// Kalau ukuran tetap tidak diketahui, pakai size dari metadata (kalau ada)
if ($totalSize === null && $meta['size']) {
    $totalSize = $meta['size'];
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
header('X-Content-Type-Options: nosniff');
if ($totalSize) {
    header('Content-Length: ' . $totalSize);
}

// --- Mode 1: Download paralel per-chunk (file besar + server support Range) ---
if ($supportsRange && $totalSize && $totalSize >= PARALLEL_THRESHOLD) {
    stream_via_parallel_chunks($downloadUrl, $totalSize, $extraHeaders);
    exit;
}

// --- Mode 2: Stream langsung (file kecil atau server tidak support Range) ---
stream_direct($downloadUrl, $extraHeaders);
exit;


/**
 * Download file dengan memecahnya jadi beberapa bagian dan menariknya
 * secara paralel (curl_multi), lalu menyusun & mengalirkannya ke browser
 * sesuai urutan. Ini yang membuat proses lebih cepat dibanding satu
 * koneksi biasa, terutama untuk file besar.
 */
function stream_via_parallel_chunks(string $url, int $totalSize, array $headers): void
{
    $numChunks = max(1, PARALLEL_CHUNKS);
    $chunkSize = (int) ceil($totalSize / $numChunks);

    if (!is_dir(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0700, true);
    }

    $jobs = [];
    for ($i = 0; $i < $numChunks; $i++) {
        $start = $i * $chunkSize;
        $end = min($start + $chunkSize - 1, $totalSize - 1);
        if ($start > $end) {
            break;
        }
        $tempPath = TEMP_DIR . '/' . uniqid('chunk_', true) . '.part';
        $jobs[] = ['start' => $start, 'end' => $end, 'path' => $tempPath];
    }

    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $fileHandles = [];

    foreach ($jobs as $idx => $job) {
        $fh = fopen($job['path'], 'wb');
        $fileHandles[$idx] = $fh;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => array_merge($headers, [
                "Range: bytes={$job['start']}-{$job['end']}",
            ]),
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => CURL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$idx] = $ch;
    }

    // Jalankan semua request paralel sampai selesai
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $idx => $ch) {
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
        fclose($fileHandles[$idx]);
    }
    curl_multi_close($multiHandle);

    // Susun ulang sesuai urutan lalu alirkan ke browser
    foreach ($jobs as $job) {
        $fh = fopen($job['path'], 'rb');
        fpassthru($fh);
        fclose($fh);
        unlink($job['path']);
        flush();
    }
}

/**
 * Stream langsung satu koneksi — dipakai untuk file kecil atau kalau
 * server sumber tidak mendukung Range request.
 */
function stream_direct(string $url, array $headers): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => CURL_TIMEOUT,
        CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
            echo $chunk;
            flush();
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
}
