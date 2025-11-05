<?php

namespace App\Http\Controllers;

use App\Helpers\IpusnasDecryptor;
use App\Models\Book;
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
        $path =  'books/' . $filename;
        $book = Book::where('file_path', $path)->first();
        $password = IpusnasDecryptor::generatePasswordPDF($book->ipusnas_user_id, $book->ipusnas_book_id, $book->epustaka_id, $book->borrow_key);

        return view('pdf', compact('fetchUrl', 'password'));
    }
}
