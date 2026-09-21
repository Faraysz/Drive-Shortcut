<?php
/**
 * Konfigurasi Drive Downloader.
 *
 * Cara dapat GOOGLE_API_KEY:
 * 1. Buka https://console.cloud.google.com/
 * 2. Buat project baru (atau pakai yang sudah ada)
 * 3. Aktifkan "Google Drive API" di menu APIs & Services > Library
 * 4. Buat kredensial > API Key (tidak perlu OAuth, cukup API Key biasa)
 * 5. (Opsional tapi disarankan) Batasi API key itu supaya hanya boleh
 *    dipakai untuk Google Drive API, biar aman kalau bocor.
 * 6. Tempel key-nya di bawah ini.
 */

// Ambil dari environment var kalau ada, kalau tidak pakai default di bawah.
define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?: 'AIzaSyDiqUwT0SHM8DrISd8WxG9iOtnCpYoefhA');

// Berapa banyak koneksi paralel saat download chunk (server -> Google).
// 4-8 biasanya optimal. Terlalu banyak malah bisa kena limit dari Google.
define('PARALLEL_CHUNKS', 6);

// Ukuran minimal file (bytes) sebelum sistem pakai mode paralel.
// File kecil di bawah ini akan didownload langsung tanpa dipecah.
define('PARALLEL_THRESHOLD', 5 * 1024 * 1024); // 5 MB

// Folder sementara untuk menyimpan potongan file saat proses download.
define('TEMP_DIR', sys_get_temp_dir() . '/drive_dl_chunks');

// Timeout per request curl (detik).
define('CURL_TIMEOUT', 120);
