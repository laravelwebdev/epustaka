<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Download Buku Aman</title>
</head>
<body>
  <button id="downloadBtn">Download Buku</button>

  <script>
    document.getElementById('downloadBtn').addEventListener('click', async () => {
      const filename = 'buku1.pdf'; // ubah sesuai nama file

      try {
        const response = await fetch(`/api/books/download/${filename}`, {
          method: 'GET',
          headers: {
            'Accept': 'application/octet-stream'
          },
          // Jika route dilindungi auth session, credentials penting
          credentials: 'same-origin'
        });

        if (!response.ok) throw new Error('Gagal mengunduh file.');

        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename; // nama file hasil download
        document.body.appendChild(a);
        a.click();
        a.remove();

        URL.revokeObjectURL(blobUrl);
      } catch (err) {
        alert(err.message);
      }
    });
  </script>
</body>
</html>
