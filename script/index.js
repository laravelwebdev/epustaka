#!/usr/bin/env node
const fs = require("fs");
const axios = require("axios");
const readline = require("readline/promises");
const IpusnasDownloader = require("./IpusnasDownloader");

const PROGRESS_FILE = "./progress.json";
const TOKEN_FILE = "./token.json";
const AUTH_FILE = "./auth.json";

let shouldStop = false;

// ==========================
// Tombol X untuk menghentikan proses
// ==========================
process.stdin.setRawMode(true);
process.stdin.resume();
process.stdin.on("data", (key) => {
  const str = key.toString().toLowerCase();
  if (str === "x") {
    console.log("\n🛑 Stop requested — will finish current download first...");
    shouldStop = true;
  } else if (str === "\u0003") {
    // Ctrl + C
    console.log("\n👋 Exiting...");
    process.exit(0);
  }
});

// ==========================
// Utility fungsi
// ==========================
function saveProgress(data) {
  fs.writeFileSync(PROGRESS_FILE, JSON.stringify(data, null, 2));
}

function loadProgress() {
  if (fs.existsSync(PROGRESS_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(PROGRESS_FILE, "utf8"));
    } catch {
      return {};
    }
  }
  return {};
}

// ==========================
// Login dan Token
// ==========================
async function getAuthCredentials() {
  // Jika auth.json sudah ada, gunakan itu
  if (fs.existsSync(AUTH_FILE)) {
    try {
      const data = JSON.parse(fs.readFileSync(AUTH_FILE, "utf8"));
      if (data.email && data.password) return data;
    } catch {
      console.log("⚠️  auth.json invalid, will recreate.");
    }
  }

  // Matikan raw mode sementara untuk readline
  process.stdin.setRawMode(false);

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });

  console.log("🔐 Please enter your iPusnas login credentials:\n");
  const email = (await rl.question("Email: ")).trim();
  const password = (await rl.question("Password: ")).trim();

  rl.close();

  // Aktifkan kembali raw mode agar tombol X berfungsi
  process.stdin.setRawMode(true);
  process.stdin.resume();

  const creds = { email, password };
  return creds;
}

async function promptLogin(downloader) {
  console.log("\n🔑 Logging In...\n");
  const { email, password } = await getAuthCredentials();
  const success = await downloader.login(email, password);
  if (!success) {
    console.log("❌ Login failed, please try again.");
    process.exit(1);
  }
  const tokenData = JSON.parse(fs.readFileSync(TOKEN_FILE, "utf8"));
  const token = tokenData?.data?.access_token;
  console.log("✅ Login successful!");
  fs.writeFileSync(AUTH_FILE, JSON.stringify({ email, password }, null, 2));
  console.log("💾 Saved to auth.json\n");
  return token;
}

async function ensureToken(downloader) {
  if (!fs.existsSync(TOKEN_FILE)) {
    return await promptLogin(downloader);
  }
  try {
    const tokenData = JSON.parse(fs.readFileSync(TOKEN_FILE, "utf8"));
    const token = tokenData?.data?.access_token;
    if (!token) throw new Error("Invalid token file");
    return token;
  } catch (err) {
    console.log("⚠️  Token file invalid or corrupted. Please login again.");
    fs.unlinkSync(TOKEN_FILE);
    return await promptLogin(downloader);
  }
}

// ==========================
// Fetch & Download Buku
// ==========================
async function fetchBookAndDownload(bookId) {
  const runDownloader = new IpusnasDownloader(bookId);
  await runDownloader.run();
}

async function fetchBookList(categoryId, offset = 0, limit = 1, token) {
  const url = `https://api2-ipusnas.perpusnas.go.id/api/webhook/book-list?limit=${limit}&offset=${offset}&sort=created_at&category_ids=${categoryId}`;

  try {
    const { data } = await axios.get(url, {
      headers: {
        Authorization: `Bearer ${token}`,
        Origin: "https://ipusnas2.perpusnas.go.id",
        Referer: "https://ipusnas2.perpusnas.go.id/",
        "User-Agent":
          "Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36",
      },
    });

    return data;
  } catch (err) {
    if (err.response) {
      throw new Error(`Failed to fetch list: ${err.response.status} ${err.response.statusText}`);
    } else {
      throw new Error(`Network error: ${err.message}`);
    }
  }
}

// ==========================
// Batch Mode
// ==========================
async function processCategory(categoryId) {
  let progress = loadProgress();
  if (!progress[categoryId]) {
    progress[categoryId] = { offset: 0 };
  }

  const downloader = new IpusnasDownloader();
  let token = await ensureToken(downloader);

  console.log(`\n📚 Starting category download: ${categoryId}`);
  console.log("🧭 Press 'x' anytime to stop after current book.\n");

  let offset = progress[categoryId].offset;
  let hasData = true;

  while (hasData) {
    if (shouldStop) {
      console.log("\n🟡 Stop signal received. Saving progress...");
      saveProgress(progress);
      console.log("💾 Progress saved. Exiting gracefully.\n");
      process.exit(0);
    }

    console.log(`🔎 Fetching book at offset ${offset}...`);
    let books;

    try {
      books = await fetchBookList(categoryId, offset, 1, token);
    } catch (err) {
      if (err.message.includes("401")) {
        console.log("🔐 Token expired. Re-logging in...");
        token = await promptLogin(downloader);
        continue; // ulang ambil daftar
      }
      console.error("❌ Error fetching book list:", err.message);
      break;
    }

    if (!books?.data?.length) {
      console.log("✅ No more books found. Done!");
      break;
    }

    const book = books.data[0];
    console.log(`📘 Downloading: ${book.book_title} (${book.id})`);
    try {
      await fetchBookAndDownload(book.id);
      console.log(`✅ Finished downloading ${book.book_title}`);
    } catch (err) {
      console.error(`❌ Failed to download ${book.book_title}:`, err.message);
    }

    offset++;
    progress[categoryId].offset = offset;
    saveProgress(progress);
  }

  console.log("\n🏁 Category processing complete.\n");
}

// ==========================
// Entry Point
// ==========================
async function main() {
  console.log("📚 iPusnas CLI Downloader");
  console.log("Downloaded books will be saved in 'Downloads/books'\n");

  const args = process.argv.slice(2);
  if (args.length === 0) {
    console.error("❌ Usage: ipusnas <book_id> or ipusnas --batch <category_id>");
    process.exit(1);
  }

  const downloader = new IpusnasDownloader();
  await ensureToken(downloader);

  if (args[0] === "--batch") {
    const categoryId = args[1];
    if (!categoryId) {
      console.error("❌ Usage: ipusnas --batch <category_id>");
      process.exit(1);
    }
    await processCategory(categoryId);
  } else {
    const bookId = args[0];
    await fetchBookAndDownload(bookId);
  }
}

main().catch((err) => {
  console.error("❌ Unexpected error:", err);
});
