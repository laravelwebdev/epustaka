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
    // helper: download ArrayBuffer sebagai file
    function sendFile(arrayBuffer, filename = 'output.pdf') {
      const blob = new Blob([arrayBuffer], { type: 'application/pdf' });
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
  // ambil blob PDF dari route Laravel (asumsi: GET /decrypt-pdf menghasilkan PDF)
  // Jika route berbeda, ubah path di sini.
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
                if (err) {
                  alert('QPDF error: ' + err.message);
                } else {
                  sendFile(outArrayBuffer, 'decrypted.pdf');
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
