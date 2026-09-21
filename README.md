# drive-pull

Web sederhana untuk menarik file dari link share Google Drive. Alurnya
mirip downloader video: tempel link → sistem cek metadata → klik download.

Bedanya dengan download manual lewat browser: untuk file yang cukup besar,
server memecah file jadi beberapa bagian (chunk) dan menariknya dari Google
secara paralel, baru menggabungkan dan mengirimkannya ke kamu sebagai satu
file. Ini yang biasanya membuat prosesnya lebih cepat dan menghindari
halaman "Google Drive can't scan this file for viruses" yang suka bikin
download manual macet di tengah jalan.

## Struktur proyek

```
drive-downloader/
├── index.html          # UI utama
├── style.css
├── script.js
├── api/
│   ├── config.php       # API key & pengaturan (edit ini)
│   ├── helpers.php       # fungsi bantu (extract ID, panggil Drive API, dll)
│   ├── resolve.php       # endpoint: link -> metadata file
│   └── download.php      # endpoint: id -> stream file ke browser
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
