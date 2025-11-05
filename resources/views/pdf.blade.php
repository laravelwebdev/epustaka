<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>QPDF decrypt - callback style</title>
  <script type="text/javascript" src='{{ asset('js/qpdf.js') }}'></script>
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
