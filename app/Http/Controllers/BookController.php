<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function saveBook(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|string',
            'book_title' => 'required|string',
            'book_author' => 'nullable|string',
            'book_description' => 'nullable|string',
            'category_name' => 'nullable|string',
            'publish_date' => 'nullable|string',
            'file_size_info' => 'nullable|string',
            'file_ext' => 'nullable|string',
            'cover_url' => 'nullable|string',
            'using_drm' => 'boolean',
            'borrowed' => 'boolean',
            'path' => 'nullable|string',
            'language' => 'nullable|string',
            'publisher' => 'nullable|string',
        ]);

        // Cek apakah sudah ada berdasarkan book_id
        $existing = Book::where('book_id', $validated['book_id'])->first();
        if ($existing) {
            return response()->json(['message' => 'Book already exists, skipped.'], 200);
        }

        // Simpan data baru
        $book = Book::create($validated);

        return response()->json(['message' => 'Book saved and queued for download.'], 201);
    }

    public function updateBookPathToServer(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|string',
            'path' => 'required|string',
        ]);

        $book = Book::where('book_id', $validated['book_id'])->first();
        if (! $book) {
            return response()->json(['message' => 'Book not found.'], 404);
        }

        $book->path = $validated['path'];
        $book->save();

        return response()->json(['message' => 'Book path updated successfully.'], 200);
    }

    public function updateBorrowedStatus(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|string',
            'borrowed' => 'required|boolean',
        ]);

        $book = Book::where('book_id', $validated['book_id'])->first();
        if (! $book) {
            return response()->json(['message' => 'Book not found.'], 404);
        }

        $book->borrowed = $validated['borrowed'];
        $book->save();

        return response()->json(['message' => 'Book borrowed status updated successfully.'], 200);
    }
}
