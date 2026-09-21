<div align="center">

# 🚀 Drive-Shortcut

**Web app sederhana untuk download file Google Drive lebih cepat, stabil, dan tanpa batas "tidak dapat memindai virus"**

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat&logo=javascript&logoColor=black)](https://javascript.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

---

## 🎯 Apa itu Drive-Shortcut?

Drive-Shortcut (drive-pull) adalah aplikasi web ringan yang dirancang untuk mengunduh file dari link berbagi Google Drive. 

Berbeda dengan download manual lewat browser yang sering macet atau terkena halaman *"Google Drive tidak dapat memindai file ini untuk mencari virus"*, sistem ini bekerja dengan cara **memecah file besar menjadi beberapa bagian (chunk)**, menariknya dari server Google secara paralel, lalu menggabungkannya sebelum dikirim ke Anda. Hasilnya? Proses download yang **jauh lebih cepat dan stabil**.

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|---------|-------------|
| ⚡ **Parallel Chunking** | Memecah file besar dan mengunduhnya secara paralel untuk kecepatan maksimal |
| 🛡️ **Bypass Virus Scan Limit** | Menghindari halaman error "Google Drive can't scan this file for viruses" |
| 📋 **Alur Simpel** | Tempel link → Cek metadata → Klik download (mirip downloader video) |
| 🌐 **Tanpa Build Step** | Murni PHP & Vanilla JS, tidak butuh Node.js atau proses kompilasi |
| 🚀 **Mudah Di-deploy** | Cukup upload ke shared hosting atau VPS, langsung jalan |

---

## 🚀 Panduan Cepat

### Prasyarat
- PHP 8.0 atau lebih baru (dengan ekstensi `curl` aktif, biasanya sudah default)
- Akun Google Cloud (untuk mendapatkan API Key)

### 1. Siapkan Google API Key
1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat project baru (atau gunakan yang sudah ada).
3. Buka **APIs & Services → Library**, cari **"Google Drive API"**, lalu klik **Enable**.
4. Buka **APIs & Services → Credentials → Create Credentials → API Key**.
5. Salin key yang muncul.
6. *(Sangat Disarankan)* Klik key tersebut → **Restrict key** → pilih hanya "Google Drive API" di bagian API restrictions agar aman jika key bocor.

### 2. Instalasi & Konfigurasi
```bash
# Clone repository ini
git clone https://github.com/Faraysz/Drive-Shortcut.git
cd Drive-Shortcut
```

## 1. Siapkan Google API Key

1. Buka https://console.cloud.google.com/
2. Buat project baru (atau pakai yang sudah ada).
3. Buka **APIs & Services → Library**, cari "Google Drive API", klik **Enable**.
4. Buka **APIs & Services → Credentials → Create Credentials → API Key**.
5. Salin key yang muncul.
6. (Disarankan) Klik key tersebut → **Restrict key** → pilih hanya
   "Google Drive API" di API restrictions, biar aman kalau key bocor.

Tempel key itu ke `api/config.php`:

```php
define('GOOGLE_API_KEY', getenv('GOOGLE_API_KEY') ?: 'API_KEY_KAMU_DI_SINI');
```

Atau, lebih aman, set sebagai environment variable saat menjalankan server
supaya key tidak ikut ter-commit ke Git:

```bash
GOOGLE_API_KEY="isi_key_kamu" php -S localhost:8000
```

## 2. Jalankan

Butuh PHP 8+ dengan ekstensi `curl` aktif (biasanya sudah default).

```bash
cd drive-downloader
php -S localhost:8000
```

Buka `http://localhost:8000` di browser.

Untuk deploy ke hosting biasa (shared hosting / VPS), cukup upload semua
file ke folder yang bisa diakses PHP — tidak butuh Node atau build step
apa pun.

## 3. Syarat file yang mau didownload

File di Drive harus dibagikan sebagai **"Anyone with the link" → Viewer**.
Kalau masih private / restricted ke akun tertentu, API key saja tidak akan
bisa membacanya (butuh OAuth atas nama pemilik file, di luar cakupan
proyek sederhana ini).

Link folder belum didukung — hanya link file tunggal.

## Cara kerja singkat

- `resolve.php` mengekstrak file ID dari berbagai format link Drive, lalu
  memanggil Drive API v3 (`files.get`) untuk ambil nama, tipe, dan ukuran.
- `download.php` mencoba Drive API v3 `alt=media` dulu (jalur resmi, support
  `Range` header). Kalau ditolak, fallback ke endpoint `uc?export=download`
  dengan logika bypass token konfirmasi ala `gdown`.
- Kalau server sumber mendukung `Range` dan file di atas 5 MB
  (`PARALLEL_THRESHOLD` di `config.php`), file dipecah jadi beberapa
  bagian dan ditarik paralel lewat `curl_multi`, disimpan sementara di
  `TEMP_DIR`, lalu digabung dan di-stream ke browser sesuai urutan.
- File kecil atau server yang tidak mendukung `Range` di-stream langsung
  satu koneksi.

## Pengaturan yang bisa diubah (`api/config.php`)

| Konstanta            | Fungsi                                              |
|-----------------------|------------------------------------------------------|
| `PARALLEL_CHUNKS`     | Jumlah koneksi paralel saat menarik file besar (default 6) |
| `PARALLEL_THRESHOLD`  | Ukuran minimum file (bytes) sebelum mode paralel dipakai (default 5 MB) |
| `CURL_TIMEOUT`        | Timeout tiap request curl, dalam detik |
| `TEMP_DIR`            | Folder penyimpanan chunk sementara |

## Batasan yang perlu disadari

- Kecepatan tetap dibatasi oleh bandwidth server tempat proyek ini
  dijalankan — kalau dijalankan di `localhost`, mode paralel hanya
  membantu sebatas kecepatan internet kamu sendiri. Manfaat paralelnya
  paling terasa kalau di-deploy ke VPS dengan koneksi besar.
- Google Drive API punya kuota harian (default cukup besar untuk
  penggunaan personal, tapi ada batas). Cek di Cloud Console kalau
  tiba-tiba error kuota.
- Jangan expose `GOOGLE_API_KEY` di frontend — di proyek ini key hanya
  dipakai di sisi server (`api/*.php`), jangan diubah jadi dipanggil dari
  `script.js`.
