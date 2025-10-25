const cliProgress = require("cli-progress");
const axios = require("axios");
const AdmZip = require("adm-zip");
const crypto = require("crypto");
const path = require("path");
const os = require("os");
const fs = require("fs");
const { URL } = require("url");
const { execSync } = require("child_process");

class IpusnasDownloader {
  constructor(bookId) {
    this.bookId = bookId;
    this.apiLogin = `https://api2-ipusnas.perpusnas.go.id/api/auth/login`;
    this.apiBookDetail = `https://api2-ipusnas.perpusnas.go.id/api/webhook/book-detail?book_id=`;
    this.apiCheckBorrowBook = `https://api2-ipusnas.perpusnas.go.id/api/webhook/check-borrow-status?book_id=`;
    this.apiReturnBook = `https://api2-ipusnas.perpusnas.go.id/api/webhook/book-return`;
    this.apiBorrowBook = `https://api2-ipusnas.perpusnas.go.id/agent/webhook/borrow`;
    this.apiPustakaId = `https://api2-ipusnas.perpusnas.go.id/api/webhook/epustaka-borrow`;
    this.apiSaveBook = `https://epustaka.office6307.my.id/api/savebook`;
    this.apiUpdateBookPath = `https://epustaka.office6307.my.id/api/updatebookpath`;
    this.apiUpdateBorrowedStatus = `https://epustaka.office6307.my.id/api/updateborrowedstatus`;

    this.headers = {
      Origin: "https://ipusnas2.perpusnas.go.id",
      Referer: "https://ipusnas2.perpusnas.go.id/",
      "User-Agent": "Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36",
      "Content-Type": "application/vnd.api+json",
    };

    this.tempDir = path.join(__dirname, "temp");
    this.booksDir = path.join(os.homedir(), "Downloads", "books");
    if (!fs.existsSync(this.booksDir)) fs.mkdirSync(this.booksDir);
    if (!fs.existsSync(this.tempDir)) fs.mkdirSync(this.tempDir);
  }

  async login(email, password) {
    try {
      const { data } = await axios.post(
        this.apiLogin,
        {
          email,
          password,
        },
        {
          headers: { ...this.headers },
        }
      );

      fs.writeFileSync("token.json", JSON.stringify(data, null, 2));
      console.log(`👤 Logged in as: ${data?.data?.name || email}`);
      console.log(`🆔 User ID: ${data?.data?.id}`);
      return true;
    } catch (err) {
      console.error("❌ Login failed:", err.message);
      console.error("Please check your email/password make sure its valid!");
    }
  }

async borrow(token, user_id, book_id, organization_id, epustaka_id) {
  try {
    const payload = {
      epustaka_id,
      user_id,
      book_id,
      organization_id,
    };

    const { data } = await axios.post(
      this.apiBorrowBook,
      payload,
      {
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
          Accept: "application/json",
          Origin: "https://ipusnas2.perpusnas.go.id",
          Referer: "https://ipusnas2.perpusnas.go.id/",
          "User-Agent": "Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36",
        },
      }
    );

    return data.code === 'SUCCESS';
  } catch (err) {
    if (err.response) {
      console.error(`❌ Borrow failed (${err.response.status}):`, err.response.data);
    } else {
      console.error("❌ Borrow failed:", err.message);
    }
    return false;
  }
}

  async getPustakaId(token, book_id, organization_id) {
    try {
      const { data } = await axios.get(this.apiPustakaId, {
        params: { book_id, organization_id },
        headers: { Authorization: `Bearer ${token}`, ...this.headers },
      });
      return data.data.id;
    } catch (err) {
      console.error("❌ Get Pustaka ID failed:", err.message);
      return null;
    }
  }

  async saveBookToServer(bookData) {
    try {
      await axios.post(
        this.apiSaveBook,
        bookData,
        {
          headers: {
            "Content-Type": "application/json",
          },
        }
      );
    } catch (err) {      
      console.error("❌ Failed to save book data to server:", err.message);
    }
  }

  async updateBookPathToServer(bookData) {
    try {
      await axios.post(
        this.apiUpdateBookPath,
        bookData,
        {
          headers: {
            "Content-Type": "application/json",
          },
        }
      );
    } catch (err) {      
      console.error("❌ Failed to save bookpath  data to server:", err.message);
    }
  }

  async updateBorrowedStatusToServer(bookData) {
    try {
      await axios.post(
        this.apiUpdateBorrowedStatus,
        bookData,
        {
          headers: {
            "Content-Type": "application/json",
          },
        }
      );
    } catch (err) {      
      console.error("❌ Failed to save borrowed data to server:", err.message);
    }
  }

  async getBookDetail(token, bookId) {
    const { data } = await axios.get(this.apiBookDetail + bookId, {
      headers: { Authorization: `Bearer ${token}`, ...this.headers },
    });
    return data;
  }

  async returnBook(token, borrowBookId) {
    const { data } = await axios.put(this.apiReturnBook, {borrow_book_id : borrowBookId}, {
      headers: { Authorization: `Bearer ${token}`, ...this.headers },
    });
    return data;
  }

  async getBorrowInfo(token, bookId) {
    const { data } = await axios.get(this.apiCheckBorrowBook + bookId, {
      headers: { Authorization: `Bearer ${token}`, ...this.headers },
    });
    return data;
  }

  async downloadBook(url, name) {
    const safeName = name.trim().replace(/[^a-z0-9_\-\.]/gi, "_");
    const ext = path.extname(new URL(url).pathname) || ".pdf";
    const fileName = `${safeName}${ext}`;
    const inputPath = path.join(this.tempDir, fileName);

    if (fs.existsSync(inputPath)) {
      console.log(`📁 File already exists, skipping download: ${inputPath}`);
      return inputPath;
    }

    const response = await axios.get(url, {
      headers: { ...this.headers },
      responseType: "stream",
    });

    const totalLength = parseInt(response.headers["content-length"] || "0", 10);
    let downloaded = 0;

    const progressBar = new cliProgress.SingleBar({
      format: `↓ [{bar}] {percentage}% | {humanValue}/{humanTotal}`,
      barCompleteChar: "#",
      barIncompleteChar: ".",
      barsize: 25,
    });

    if (!totalLength) throw new Error("❌ Missing 'content-length' header. The server might not support progress tracking.");

    progressBar.start(totalLength, 0, {
      humanTotal: this.formatBytes(totalLength),
      humanValue: this.formatBytes(0),
    });

    response.data.on("data", (chunk) => {
      downloaded += chunk.length;
      progressBar.update(downloaded, {
        humanValue: this.formatBytes(downloaded),
      });
    });

    const writer = fs.createWriteStream(inputPath);
    response.data.pipe(writer);

    return new Promise((resolve, reject) => {
      writer.on("finish", () => {
        progressBar.stop();
        console.log(`\n✅ Download complete: ${inputPath}`);
        resolve(inputPath);
      });

      writer.on("error", (err) => {
        progressBar.stop();
        console.error("❌ Download failed:", err.message);
        reject(err);
      });
    });
  }

  formatBytes(bytes) {
    if (bytes === 0) return "0 Bytes";
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${sizes[i]}`;
  }

  decryptKey(userId, bookId, epustakaId, borrowKey) {
    const formatted = `${userId}${bookId}${epustakaId}`;
    const key = crypto.createHash("sha256").update(formatted).digest("hex").slice(7, 23);
    const iv = Buffer.from(borrowKey, "base64").slice(0, 16);
    const ciphertext = Buffer.from(borrowKey, "base64").slice(16);
    const decipher = crypto.createDecipheriv("aes-128-cbc", key, iv);

    let decrypted = decipher.update(ciphertext);
    decrypted = Buffer.concat([decrypted, decipher.final()]);
    return decrypted.toString("utf-8");
  }

  generatePasswordPDF(decryptedKey) {
    const hash = crypto.createHash("sha384").update(decryptedKey, "utf8").digest("hex");
    return hash.slice(9, 73);
  }
  generatePasswordZip(decryptedKey, useSha512 = false) {
    if (typeof decryptedKey !== "string") {
      throw new TypeError("Password must be a string");
    }
    const algorithm = useSha512 ? "sha512" : "sha1";
    const hash = crypto.createHash(algorithm).update(decryptedKey, "utf-8").digest("hex");
    return hash.slice(59, 105);
  }

  async decryptPDF(inputPath, password, outputPath) {
    try {
      execSync(`qpdf --password="${password}" --decrypt "${inputPath}" "${outputPath}"`); 
      console.log(`🔓 Decrypted PDF saved to: ${outputPath}`);

      // sukses, hapus file terenkripsi
      try {
        fs.unlinkSync(inputPath);
        console.log(`🧹 Removed encrypted file: ${inputPath}`);
      } catch (err) {
        console.warn(`⚠️  Could not remove encrypted file: ${err.message}`);
      }

    } catch (err) {
      console.error("❌ QPDF decryption failed:", err.message);

      try {
        fs.renameSync(inputPath, outputPath);
        console.log(`💾 Encrypted file saved as: ${outputPath}`);
        console.log(`🔑 PDF Password : ${password}`);
      } catch (moveErr) {
        console.error("⚠️  Failed to move encrypted file:", moveErr.message);
      }
    }
  }


extractZip(inputPath, passwordZip, bookId) {
  const zip = new AdmZip(inputPath);
  const entry = zip.getEntry(`${bookId}.moco`);

  // ================== CASE 1: Ada file .moco ==================
  if (entry) {
    let buffer;
    try {
      buffer = entry.getData(passwordZip);
    } catch (err) {
      console.error("❌ Failed to extract encrypted zip entry:", err.message);
      return null;
    }

    const outputPdfPath = path.join(this.tempDir, `${bookId}.pdf`);
    fs.writeFileSync(outputPdfPath, buffer);
    console.log(`📦 Extracted → ${outputPdfPath}`);

    try {
      fs.unlinkSync(inputPath);
      console.log(`🧹 Removed zip archive: ${inputPath}`);
    } catch (err) {
      console.warn(`⚠️ Could not remove zip archive: ${err.message}`);
    }

    return outputPdfPath;
  }

  // ================== CASE 2: Tidak ada .moco → Anggap EPUB ==================
  console.log("📚 No .moco found, treating ZIP as EPUB package...");

  try {
    const entries = zip.getEntries();
    if (entries.length === 0) {
      console.error("❌ ZIP is empty, nothing to convert.");
      return null;
    }

    const newZip = new AdmZip();

    // ambil semua file dari ZIP lama (pakai password kalau perlu)
    for (const e of entries) {
      try {
        const data = e.getData(passwordZip); // decrypt entry
        newZip.addFile(e.entryName, data);   // tambahkan ke ZIP baru
      } catch (err) {
        console.warn(`⚠️ Could not read ${e.entryName}: ${err.message}`);
      }
    }

    const outputEpubPath = path.join(this.tempDir, `${bookId}.epub`);
    newZip.writeZip(outputEpubPath);
    console.log(`✅ Repacked EPUB → ${outputEpubPath}`);

    try {
      fs.unlinkSync(inputPath);
      console.log(`🧹 Removed original zip archive: ${inputPath}`);
    } catch (err) {
      console.warn(`⚠️ Could not remove zip archive: ${err.message}`);
    }

    return outputEpubPath;
  } catch (err) {
    console.error("❌ Failed to rebuild EPUB:", err.message);
    return null;
  }
}


  async run() {
    try {
      //get credential
      const {
        data: { access_token, id: user_id, organization_id },
      } = JSON.parse(fs.readFileSync("token.json", "utf-8"));

      //get book detail
    const {
      data: {
        id: book_id,
        book_title,
        using_drm,
        file_size_info,
        file_ext,
        book_author,
        cover_url,
        book_description,
        category_name,
        publish_date,
        catalog_info: {
          language_name: language,
          organization_name: publisher,
        },
      },
    } = await this.getBookDetail(access_token, this.bookId);

      //save book info to server
      await this.saveBookToServer({
        book_id,
        book_title,
        book_author,
        book_description,
        category_name,
        publish_date,
        file_size_info,
        file_ext,
        cover_url,
        using_drm,
        language,
        publisher,
      });

      //get pustaka id
      const pustaka_id = await this.getPustakaId(access_token, this.bookId, organization_id);

      const borrowSuccess = await this.borrow(access_token, user_id, this.bookId, organization_id, pustaka_id);

      //update borrowed status to server
      await this.updateBorrowedStatusToServer(
        { 
          book_id,
          borrowed: borrowSuccess
        }
      );

      if (!borrowSuccess) {
        console.error("❌ Could not borrow the book. It might be already borrowed or unavailable.");
        return;
      }
      const {
        data: {
          url_file,
          borrow_key,
          id: borrow_book_id, 
          epustaka: { id: epustaka_id },
        },
      } = await this.getBorrowInfo(access_token, book_id);

      const safeName = book_title.trim().replace(/[^a-z0-9_\-\.]/gi, "_");
      const bookFolder = path.join(this.booksDir, safeName);
      const safeFileName = crypto
        .createHash("md5")
        .update(book_id)
        .digest("hex");
      const finalPath = path.join(bookFolder, `${safeFileName}_decrypted.${file_ext}`);

      if (fs.existsSync(finalPath)) {
        console.log(`✅ Book already downloaded: ${finalPath}`);
        const bookReturn = await this.returnBook(access_token, borrow_book_id);
        console.log(`🔄 Book return status: ${bookReturn.data.message}`);
        return;
      }
      let decryptedKey, passwordZip, pdfPassword;

      if (using_drm) {
        decryptedKey = this.decryptKey(user_id, book_id, epustaka_id, borrow_key);
        passwordZip = this.generatePasswordZip(decryptedKey, true);
        pdfPassword = this.generatePasswordPDF(decryptedKey);
      }

      console.log("📚 iPusnas Downloader");
      console.log(`📘 Book Title     : ${book_title}`);
      console.log(`✍️  Author         : ${book_author}`);
      console.log(`📦 File Size      : ${file_size_info}`);
      console.log(`📄 File Extension : ${file_ext}`);
      console.log(`🔒 DRM Protected  : ${using_drm ? "Yes" : "No"}`);


      process.stdout.write("");
      await new Promise((r) => setTimeout(r, 50));

      const downloadedFile = await this.downloadBook(url_file, book_title);
      const fileExt = path.extname(downloadedFile).toLowerCase();
      const bookReturn = await this.returnBook(access_token, borrow_book_id);
      console.log(`🔄 Book return status: ${bookReturn.data.message}`);

      if (!using_drm) {
        if (!fs.existsSync(bookFolder)) fs.mkdirSync(bookFolder, { recursive: true });
        const destPath = path.join(bookFolder, path.basename(downloadedFile));
        fs.renameSync(downloadedFile, destPath);
        console.log(`✅ File moved to: ${destPath}`);
      } else {
        let targetPDF = downloadedFile;

        if (fileExt === ".mdrm") {
          const extractedPDF = this.extractZip(downloadedFile, passwordZip, book_id);
          if (!extractedPDF) return;
          targetPDF = extractedPDF;
        }

        if (!fs.existsSync(bookFolder)) fs.mkdirSync(bookFolder, { recursive: true });
        if (file_ext === 'pdf') await this.decryptPDF(targetPDF, pdfPassword, finalPath);
        if (file_ext === 'epub') fs.renameSync(targetPDF, finalPath);
      }
      //save path to server      
      await this.updateBookPathToServer({
        book_id,
        path: finalPath.split('/books/')[1],
      })

    } catch (err) {
      console.error("❌ ", err.message);
    }
  }
}

module.exports = IpusnasDownloader;
