<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>QPDF decrypt - callback style</title>
  <script type="text/javascript" src="{{ asset('js/qpdf.js') }}"></script>

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

  <div id="progress-container" style="display:none;">
    <div style="margin-bottom:6px; font-size:14px; color:#555;">Mengunduh...</div>

    <div id="progress-bar-wrapper">
      <div id="progress"></div>
    </div>

    <div id="progress-text">0%</div>
  </div>
</div>

<script>
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
    const password = '{{ $password }}';

    try {
      const res = await fetch('{{ $fetchUrl }}');
      if (!res.ok) throw new Error('Gagal fetch encrypt.pdf: ' + res.status);

      const goBtn = document.getElementById('go');
      const progressContainer = document.getElementById('progress-container');
      const progressEl = document.getElementById('progress');
      const progressText = document.getElementById('progress-text');

      progressContainer.style.display = 'block';
      goBtn.disabled = true;

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
          received += value.byteLength; // ✅ FIX PENTING

          if (total) {
            const percent = Math.round((received / total) * 100);
            progressEl.style.width = percent + '%';
            progressText.textContent = percent + '%';
          } else {
            progressText.textContent = (received / 1024).toFixed(1) + ' KB';
          }
        }

        const joined = new Uint8Array(received);
        let offset = 0;
        for (const chunk of chunks) {
          joined.set(chunk, offset);
          offset += chunk.byteLength; // ✅ FIX PENTING
        }

        chunks.length = 0; 
        arrayBuffer = joined.buffer;
      } else {
        arrayBuffer = await res.arrayBuffer();
      }

      //=== menjalankan QPDF ===//
      QPDF.path = '{{ asset('js') }}/';
      QPDF({
        ready: function (qpdf) {
          try {
            qpdf.save('input.pdf', arrayBuffer);

            qpdf.execute([
              '--decrypt',
              '--password=' + password,
              '--stream-data=copy',   // ✅ PERTAHANKAN STREAM ASLI TANPA RECOMPRESS
              '--',
              'input.pdf',
              'output.pdf'
            ]);

            qpdf.load('output.pdf', function (err, outArrayBuffer) {
              goBtn.disabled = false;
              progressContainer.style.display = 'none';

              if (err) {
                alert('QPDF error: ' + err.message);
              } else {
                sendFile(outArrayBuffer, 'decrypted.pdf');
              }
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
