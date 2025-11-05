<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfController extends Controller
{
    /**
     * Serve a PDF from storage/app/books (private storage).
     *
     * Example route: Route::get('/books/{filename}', [PdfController::class, 'servePdf'])->name('serve.pdf');
     * If you prefer a fixed file, call /books/encrypt.pdf
     */
    public function servePdf(Request $request, $filename)
    {
        // sanitize filename to prevent directory traversal
        $safe = basename($filename);

        // path in storage/app/books
        $path = storage_path('app/private/books/' . $safe);

        if (! file_exists($path)) {
            abort(404, 'File PDF tidak ditemukan');
        }

        // Return as file response (Symfony BinaryFileResponse) - sets Content-Type and Content-Length
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safe . '"',
        ]);
    }

    /**
     * Show the Blade view and pass fetch URL + password
     */
    public function showView($filename)
    {
        $fetchUrl = route('serve.pdf', ['filename' => $filename]);
        $password = 'pass123'; // or fetch from config/db

        return view('pdf', compact('fetchUrl', 'password'));
    }
}
