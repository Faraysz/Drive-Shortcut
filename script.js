const form = document.getElementById('linkForm');
const input = document.getElementById('linkInput');
const resolveBtn = document.getElementById('resolveBtn');
const logEl = document.getElementById('log');
const dot = document.getElementById('statusDot');
const fileCard = document.getElementById('fileCard');
const fileIconGlyph = document.getElementById('fileIconGlyph');
const fileThumb = document.getElementById('fileThumb');
const fileName = document.getElementById('fileName');
const fileType = document.getElementById('fileType');
const fileSize = document.getElementById('fileSize');
const downloadBtn = document.getElementById('downloadBtn');

function log(message, kind = '') {
  const line = document.createElement('div');
  if (kind) line.classList.add(kind);
  line.textContent = message;
  logEl.appendChild(line);
  logEl.scrollTop = logEl.scrollHeight;
}

function resetLog() {
  logEl.innerHTML = '';
}

function setStatus(state) {
  dot.className = '';
  if (state) dot.classList.add(state);
}

function iconForMime(mimeType) {
  if (!mimeType) return '▤';
  if (mimeType.startsWith('image/')) return '◨';
  if (mimeType.startsWith('video/')) return '▶';
  if (mimeType.startsWith('audio/')) return '♪';
  if (mimeType.includes('pdf')) return '▤';
  if (mimeType.includes('zip') || mimeType.includes('compressed')) return '▣';
  if (mimeType.includes('spreadsheet')) return '▦';
  return '▤';
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const link = input.value.trim();
  if (!link) return;

  resetLog();
  fileCard.classList.add('hidden');
  resolveBtn.disabled = true;
  resolveBtn.textContent = 'Memproses…';
  setStatus('busy');

  log('Membaca link…');

  try {
    const res = await fetch('api/resolve.php?link=' + encodeURIComponent(link));
    const data = await res.json();

    if (!res.ok || data.error) {
      throw new Error(data.error || 'Gagal memproses link.');
    }

    log('Link valid, ID ditemukan.');
    log('Mengambil metadata dari Drive API…');
    log(`Ditemukan: ${data.file.name}`, 'ok');

    fileName.textContent = data.file.name;
    fileType.textContent = data.file.mimeType;
    fileSize.textContent = data.file.sizeHuman;
    fileIconGlyph.textContent = iconForMime(data.file.mimeType);

if (data.file.thumbnailLink) {
  fileThumb.onerror = () => {
    fileThumb.classList.add('hidden');
    fileIconGlyph.classList.remove('hidden');
  };
  fileThumb.onload = () => {
    fileIconGlyph.classList.add('hidden');
    fileThumb.classList.remove('hidden');
  };
  // URL asli biasanya diakhiri "=s220", kita minta ukuran lebih besar
  fileThumb.src = 'api/thumbnail.php?id=' + encodeURIComponent(data.file.id);
} else {
  fileThumb.classList.add('hidden');
  fileIconGlyph.classList.remove('hidden');
}

downloadBtn.href = 'api/download.php?id=' + encodeURIComponent(data.file.id);
    downloadBtn.setAttribute('download', data.file.name);

    fileCard.classList.remove('hidden');
    setStatus('ready');
  } catch (err) {
    log(err.message, 'err');
    setStatus('error');
  } finally {
    resolveBtn.disabled = false;
    resolveBtn.textContent = 'Ambil';
  }
});
