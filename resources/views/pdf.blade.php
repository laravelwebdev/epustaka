<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>QPDF decrypt - optimized</title>
  <script type="text/javascript" src="{{ asset('js/qpdf.js') }}"></script>

  <style>
    body { font-family: Arial; background: #f2f2f2; padding: 40px; }
    #go { background: #3498db; border: none; padding: 12px 28px; font-size: 16px; color: #fff; border-radius: 4px; cursor: pointer; }
    #go:disabled { opacity: 0.6; cursor: not-allowed; }
    #progress-container { max-width: 400px; margin: 25px auto; text-align: center; }
    #progress-bar-wrapper { width: 100%; background: #ddd; border-radius: 4px; overflow: hidden; height: 20px; }
    #progress { height: 100%; width: 0%; background: linear-gradient(90deg, #4facfe, #00f2fe); transition: width 0.2s ease; }
    #progress-text { margin-top: 6px; font-size: 14px; font-weight: bold; color: #333; }
  </style>
</head>
<body>

<div style="max-width:400px; margin:auto; text-align:center;">
  <button id="go">Unduh</button>

  <div id="progress-container" style="display:none;">
    <div style="margin-bottom:6px; font-size:14px; color:#555;">Mengunduh & decrypt...</div>
    <div id="progress-bar-wrapper"><div id="progress"></div></div>
    <div id="progress-text">0%</div>
  </div>
</div>

<script>
function sendFile(arrayBuffer, filename='output.pdf') {
  const blob = new Blob([arrayBuffer], { type:'application/pdf' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
}

document.getElementById('go').addEventListener('click', async () => {
  const password = '{{ $password }}';
  const goBtn = document.getElementById('go');
  const progressContainer = document.getElementById('progress-container');
  const progressEl = document.getElementById('progress');
  const progressText = document.getElementById('progress-text');

  try {
    goBtn.disabled = true;
    progressContainer.style.display = 'block';

    const res = await fetch('{{ $fetchUrl }}');
    if (!res.ok) throw new Error('Gagal fetch: ' + res.status);

    const contentLength = res.headers.get('content-length');
    const total = contentLength ? parseInt(contentLength, 10) : null;

    let arrayBuffer;
    if (res.body && res.body.getReader) {
      const reader = res.body.getReader();
      const chunks = [];
      let received = 0;
      while(true) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
        received += value.byteLength;
        if (total) {
          const pct = Math.round((received/total)*100);
          progressEl.style.width = pct+'%';
          progressText.textContent = pct+'%';
        } else {
          progressText.textContent = Math.round(received/1024)+' KB';
        }
      }
      // Optimized: single join
      arrayBuffer = new Uint8Array(received);
      let offset=0;
      for(const chunk of chunks) {
        arrayBuffer.set(chunk, offset);
        offset += chunk.byteLength;
      }
      arrayBuffer = arrayBuffer.buffer; // final ArrayBuffer
    } else {
      arrayBuffer = await res.arrayBuffer();
    }

    //=== QPDF decrypt ===//
    QPDF.path = '{{ asset('js') }}/';
    QPDF({
      ready: function(qpdf) {
        qpdf.save('input.pdf', arrayBuffer, function(err) {
          if(err) { alert('QPDF save error: '+err.message); return; }

          qpdf.execute([
            '--decrypt',
            '--password='+password,
            '--stream-data=preserve',  // preserve stream, minimal mem
            '--',
            'input.pdf',
            'output.pdf'
          ], function(err) {
            if(err) { alert('QPDF execute error: '+err.message); return; }

            qpdf.load('output.pdf', function(err, outArrayBuffer){
              goBtn.disabled=false;
              progressContainer.style.display='none';
              if(err) alert('QPDF load error: '+err.message);
              else sendFile(outArrayBuffer, 'decrypted.pdf');
            });
          });
        });
      }
    });

  } catch(e) {
    alert('Error: '+(e.message||e));
    goBtn.disabled=false;
    progressContainer.style.display='none';
  }
});
</script>

</body>
</html>
