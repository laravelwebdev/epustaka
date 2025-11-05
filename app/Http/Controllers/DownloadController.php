<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function download($filename): StreamedResponse
    {
        $path = "private/books/{$filename}";

        if (!Storage::exists($path)) {
            abort(404, 'File not found');
        }

        // Ambil MIME type agar sesuai (pdf, zip, dll)
        $mime = Storage::mimeType($path) ?? 'application/octet-stream';
        $fileStream = Storage::readStream($path);

        return response()->stream(function () use ($fileStream) {
            fpassthru($fileStream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
        ]);
    }
}
