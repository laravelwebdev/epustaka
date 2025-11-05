<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class BookDownloadController extends Controller
{
    public function download($filename, $password = null)
    {
        $path = 'books/'.$filename;
        $book = Book::where('path', $path)->first();
        $authorized = DB::table('book_user')
            ->where('user_id', Auth::id())
            ->where('book_id', $book->id)
            ->exists();
        if (! $authorized) {
            abort(403, 'Anda tidak memiliki akses ke buku ini.');
        }

        $filePath = storage_path("app/private/books/{$filename}");

        if (! file_exists($filePath)) {
            abort(404, 'File PDF tidak ditemukan');
        }

        // === Jika password kosong, langsung kirim PDF ===
        if (empty($password)) {
            return response()->download($filePath, basename($filePath));
        }

        // === Kalau ada password, buat ZIP ===

        // Buat folder tmp jika belum ada
        $tmpDir = storage_path('app/private/temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // Nama file zip sementara unik
        $tmpZipPath = $tmpDir.'/'.uniqid('book_', true).'.zip';

        // Buat ZIP
        $zip = new ZipArchive;
        if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

            // Tambahkan PDF ke dalam zip
            $zip->addFile($filePath, basename($filePath));

            // Tambahkan file password.txt
            $zip->addFromString('password.txt', "{$password}");

            $zip->close();
        } else {
            abort(500, 'Gagal membuat ZIP file');
        }

        // Kirim file ZIP dan hapus setelah dikirim
        return response()
            ->download($tmpZipPath, basename($filePath, '.pdf').'.zip')
            ->deleteFileAfterSend(true);
    }
}
