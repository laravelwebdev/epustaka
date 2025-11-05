<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $path = storage_path('app/private/books/'.$safe);

        if (! file_exists($path)) {
            abort(404, 'File PDF tidak ditemukan');
        }

        // Return as file response (Symfony BinaryFileResponse) - sets Content-Type and Content-Length
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safe.'"',
        ]);
    }

    /**
     * Show the Blade view and pass fetch URL + password
     */
    public function showView($pdfPass, $filename)
    {
        $fetchUrl = route('serve.pdf', ['filename' => $filename]);
        $password = $pdfPass;
        $path = 'books/'.$filename;
        $book = Book::where('path', $path)->first();
        $authorized = Auth::user()->books()->where('id', $book->id)->exists();
        if ($authorized)
        return view('pdf', compact('fetchUrl', 'password'));

        abort(403, 'Anda tidak memiliki akses ke buku ini.');
    }
}
