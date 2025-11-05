<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>QPDF decrypt - callback style (streaming)</title>
  <script type="text/javascript" src='{{ asset('js/qpdf.js') }}'></script>

  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      padding: 40px;
    }

    #go {
      background: #3498db;
      border: none;
      padding: 12px 28px;
      font-size: 16px;
      color: #fff;
      border-radius: 4px;
      cursor: pointer;
      transition: 0.3s;
    }

    #go:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    #progress-container {
      max-width: 400px;
      margin: 25px auto;
      text-align: center;
    }

    #progress-bar-wrapper {
      width: 100%;
      background: #ddd;
      border-radius: 4px;
      overflow: hidden;
      height: 20px;
    }

    #progress {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #4facfe, #00f2fe);
      transition: width 0.2s ease;
    }

    #progress-text {
      margin-top: 6px;
      font-size: 14px;
      font-weight: bold;
      color: #333;
    }
  </style>
</head>
<body>

<div style="max-width:400px; margin:auto; text-align:center;">
  <button id="go">Unduh</button>

  <!-- Progress UI -->
  <div id="progress-container" style="display:none;">
    <div style="margin-bottom:6px; font-size:14px; color:#555;">Mengunduh...</div>

    <div id="progress-bar-wrapper">
      <div id="progress"></div>
    </div>

    <div id="progress-text">0%</div>
  </div>
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
    // revoke sedikit ditunda supaya browser sempat melakukan download
    setTimeout(() => URL.revokeObjectURL(url), 5000);
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

      // gunakan chunks (Uint8Array) saat baca, tapi hindari membuat 2 salinan besar:
      const reader = res.body && res.body.getReader ? res.body.getReader() : null;

      if (reader) {
        const chunks = []; // akan di-empty setelah dibuat Blob
        let received = 0;
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          // value adalah Uint8Array
          chunks.push(value);
          received += value.length;

          if (total) {
            const percent = Math.round((received / total) * 100);
            progressEl.style.width = percent + '%';
            progressText.textContent = percent + '%';
          } else {
            progressText.textContent = (received / 1024).toFixed(1) + ' KB';
          }
        }

        // Buat Blob dari chunks -> ini cenderung lebih memory-efficient daripada membentuk big Uint8Array manual
        // lalu ambil arrayBuffer dari Blob (satu salinan yang diperlukan)
        const blob = new Blob(chunks);
        // kosongkan chunks secepat mungkin agar GC bisa reclaim memory chunk buffers
        chunks.length = 0;

        arrayBuffer = await blob.arrayBuffer();

        // blob tidak lagi diperlukan
        // (browser akan meng-free memory blob ketika out of scope)
      } else {
        // fallback: jika streaming tidak tersedia, ambil seluruh buffer
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
          sendFile(arrayBuffer, 'download.epub', 'application/epub+zip');
          // reset UI
          goBtn.disabled = false;
          progressContainer.style.display = 'none';
          // release big buffer reference
          arrayBuffer = null;
          return;
        }
      })();

      // Reset UI progress (tetap tampilkan sedikit agar pengguna melihat progress berakhir)
      goBtn.disabled = false;
      progressContainer.style.display = 'none';

      // PAKAI streaming stdin/stdout API dari QPDF-WASM
      // Pastikan qpdf.wasm served with MIME application/wasm untuk streaming compile
      QPDF.path = '{{ asset('js') }}/';
      QPDF({
        ready: function (qpdf) {
          try {
            // Pastikan arrayBuffer ada
            if (!arrayBuffer) {
              throw new Error('Empty buffer — tidak ada data untuk diproses');
            }

            // QPDF.run() mengembalikan Promise dengan result.stdout (Uint8Array)
            // Kita kirim stdin sebagai Uint8Array(arrayBuffer)
            const stdinUint8 = new Uint8Array(arrayBuffer);

            // segera drop reference ke arrayBuffer agar GC bisa reclaim
            arrayBuffer = null;

            qpdf.run({
              arguments: ['--decrypt', '--password=' + password, '-', '-'],
              stdin: stdinUint8
            }).then(result => {
              try {
                // result.stdout biasanya Uint8Array
                if (!result || !result.stdout) {
                  throw new Error('QPDF tidak mengembalikan stdout');
                }

                // sendFile menerima ArrayBuffer => gunakan .buffer dari Uint8Array
                sendFile(result.stdout.buffer, 'decrypted.pdf');

                // Bebaskan referensi result untuk memudahkan GC
                // (note: beberapa browser butuh waktu untuk free WASM memory)
                result.stdout = null;
                result = null;
              } catch (e) {
                alert('Error saat menulis hasil: ' + (e && e.message ? e.message : e));
              }
            }).catch(err => {
              alert('QPDF error: ' + (err && err.message ? err.message : err));
            });
          } catch (e) {
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
