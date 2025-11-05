<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>QPDF decrypt - callback style</title>
  <script type="text/javascript" src='{{ asset('js/qpdf.js') }}'></script>
  <style>
    :root{
      --bg-1: #0f172a;
      --bg-2: #0b1220;
      --card: #0b1228cc;
      --accent: #06b6d4;
      --accent-2: #7c3aed;
      --muted: #9aa4b2;
      --glass: rgba(255,255,255,0.04);
    }
    html,body{height:100%;}
    body{
      margin:0;
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: linear-gradient(180deg,var(--bg-1),var(--bg-2));
      color:#e6eef6;
      display:flex;
      align-items:center;
      justify-content:center;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      padding:24px;
    }
    .card{
      width:100%;
      max-width:720px;
      background: linear-gradient(135deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
      border:1px solid rgba(255,255,255,0.04);
      box-shadow: 0 8px 30px rgba(2,6,23,0.6);
      border-radius:12px;
      padding:28px;
      backdrop-filter: blur(6px);
    }
    h3{margin:0 0 12px 0; font-size:20px; font-weight:600; color:#f1f8ff}

    #go{
      background: linear-gradient(90deg,var(--accent), var(--accent-2));
      border:none;
      color:white;
      padding:10px 18px;
      border-radius:8px;
      font-weight:600;
      cursor:pointer;
      box-shadow: 0 6px 18px rgba(12,74,90,0.18);
      transition: transform .08s ease, box-shadow .12s ease, opacity .12s ease;
    }
    #go:active{ transform: translateY(1px) scale(.998); }
    #go[disabled]{ opacity:.6; cursor:not-allowed; box-shadow:none; }

    #progress-container{ display:flex; align-items:center; gap:12px; margin-top:16px }
    progress#progress{ appearance:none; width:320px; height:14px; border-radius:999px; overflow:hidden; background:var(--glass); border:1px solid rgba(255,255,255,0.03) }
    progress#progress::-webkit-progress-bar{ background:transparent }
    progress#progress::-webkit-progress-value{ background: linear-gradient(90deg,var(--accent),var(--accent-2)); border-radius:999px }
    progress#progress::-moz-progress-bar{ background: linear-gradient(90deg,var(--accent),var(--accent-2)); border-radius:999px }

    #progress-text{ color:var(--muted); font-size:13px; min-width:60px; text-align:left }

    /* small responsive tweaks */
    @media (max-width:420px){
      .card{ padding:18px }
      progress#progress{ width:180px }
    }
  </style>
</head>
<body>
  <h3>Decrypt encrypt.pdf (callback-style QPDF)</h3>
  <!-- Password di-hardcode, jadi input password dihapus -->
  <button id="go">Unduh</button>

  <!-- Progress UI -->
  <div id="progress-container" style="display:none; margin-top:10px;">
    <progress id="progress" value="0" max="100" style="width:300px;"></progress>
    <span id="progress-text" style="margin-left:8px">0%</span>
  </div>


  <script>
    // helper: download ArrayBuffer sebagai file (bisa set MIME)
    function sendFile(arrayBuffer, filename = 'output.pdf', mime = 'application/pdf') {
      const blob = new Blob([arrayBuffer], { type: mime });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    }

    document.getElementById('go').addEventListener('click', async () => {
  // Password di-hardcode sesuai permintaan
  const password = '{{ $password }}';

      try {
    // ambil blob dari route Laravel
    // Jika URL berakhiran .epub, nanti akan langsung didownload tanpa decrypt
    const res = await fetch('{{ $fetchUrl }}');
        if (!res.ok) throw new Error('Gagal fetch encrypt.pdf: ' + res.status);

        // Siapkan UI progress
        const progressContainer = document.getElementById('progress-container');
        const progressEl = document.getElementById('progress');
        const progressText = document.getElementById('progress-text');
        const goBtn = document.getElementById('go');
        progressContainer.style.display = 'block';
        goBtn.disabled = true;

        // Stream response supaya bisa menampilkan progress
        let arrayBuffer = null;
        const contentLength = res.headers.get('content-length');
        const total = contentLength ? parseInt(contentLength, 10) : null;

        if (res.body && res.body.getReader) {
          const reader = res.body.getReader();
          const chunks = [];
          let received = 0;
          while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            chunks.push(value);
            received += value.length;

            if (total) {
              const percent = Math.round((received / total) * 100);
              progressEl.value = percent;
              progressText.textContent = percent + '%';
            } else {
              // jika content-length tidak tersedia, tampilkan ukuran diterima
              progressText.textContent = (received / 1024).toFixed(1) + ' KB';
            }
          }

          // Gabungkan chunks menjadi satu ArrayBuffer
          const joined = new Uint8Array(received);
          let offset = 0;
          for (const chunk of chunks) {
            joined.set(chunk, offset);
            offset += chunk.length;
          }
          arrayBuffer = joined.buffer;
        } else {
          // Fallback: jika streaming tidak tersedia, ambil seluruh buffer
          arrayBuffer = await res.arrayBuffer();
        }

        // Jika file adalah EPUB (berakhiran .epub) -> langsung download tanpa decrypt
        (function () {
          // dapatkan nama file dari header atau path
          const contentDisposition = res.headers.get('content-disposition') || '';
          let filename = null;
          const cdMatch = /filename\*?=(?:UTF-8''?)?\s*"?([^";]+)/i.exec(contentDisposition);
          if (cdMatch && cdMatch[1]) {
            try {
              filename = decodeURIComponent(cdMatch[1]);
            } catch (e) {
              filename = cdMatch[1];
            }
          }

          // fallback: dari URL path
          try {
            const urlPath = new URL('{{ $fetchUrl }}', location.href).pathname;
            const seg = urlPath.split('/').pop();
            if (!filename && seg) filename = seg;
          } catch (e) {
            if (!filename) filename = '{{ $fetchUrl }}'.split('/').pop();
          }

          const ext = (filename || '').split('.').pop().toLowerCase();
          if (ext === 'epub') {
            // kirim file langsung, gunakan MIME type EPUB
            sendFile(arrayBuffer, 'decrypted.epub', 'application/epub+zip');
            // free buffer to avoid keeping large memory
            try { arrayBuffer = null; } catch (e) {}
            // reset UI
            document.getElementById('go').disabled = false;
            document.getElementById('progress-container').style.display = 'none';
            return;
          }
        })();

        // Reset UI progress
        goBtn.disabled = false;
        progressContainer.style.display = 'none';

        // PAKAI PERSIS callback-style API dari dokumentasi
        QPDF.path = '{{ asset('js') }}/'; 
        QPDF({
          ready: function (qpdf) {
            try {
              qpdf.save('input.pdf', arrayBuffer);
              qpdf.execute(['--decrypt', '--password=' + password, '--', 'input.pdf', 'output.pdf']);
              qpdf.load('output.pdf', function (err, outArrayBuffer) {
                // Always attempt to cleanup Emscripten MEMFS files and free buffers
                function _cleanup() {
                  try {
                    if (qpdf && qpdf.FS) {
                      try { qpdf.FS.unlink('/input.pdf'); } catch (e) {}
                      try { qpdf.FS.unlink('input.pdf'); } catch (e) {}
                      try { qpdf.FS.unlink('/output.pdf'); } catch (e) {}
                      try { qpdf.FS.unlink('output.pdf'); } catch (e) {}
                    }
                  } catch (e) {}
                  try { outArrayBuffer = null; } catch (e) {}
                  try { arrayBuffer = null; } catch (e) {}
                }

                if (err) {
                  alert('QPDF error: ' + err.message);
                  _cleanup();
                } else {
                  try {
                    sendFile(outArrayBuffer, 'decrypted.pdf');
                  } catch (e) {
                    // ignore send errors
                  }
                  _cleanup();
                }
              });
            } catch (e) {
              // execute/save mungkin melempar error sinkron
              alert('Error saat menjalankan QPDF: ' + (e && e.message ? e.message : e));
            }
          }
        });
      } catch (fetchErr) {
        alert('Gagal: ' + (fetchErr.message || fetchErr));
      }
    });
  </script>
</body>
</html>
