<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadBookFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $book;

    public function __construct(Book $book)
    {
        $this->book = $book;
    }

    public function handle(): void
    {
        try {
            $headers = [
                'Origin' => 'https://ipusnas2.perpusnas.go.id',
                'Referer' => 'https://ipusnas2.perpusnas.go.id/',
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
                'Content-Type' => 'application/vnd.api+json',
            ];
            $url = $this->book->book_url;
            $safeName = md5($this->book->ipusnas_book_id);
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
            $filename = "{$safeName}.{$extension}";
            $path = "books/{$filename}";

            Log::info("Downloading book file: {$url}");

            $response = Http::withHeaders($headers)->timeout(1200)->get($url);
            if ($response->failed()) {
                Log::warning("Failed to download: {$url}");

                return;
            }
            // Simpan ke storage/app/public/books/
            Storage::put($path, $response->body());

            // Update path di database
            Book::where('ipusnas_book_id', $this->book->ipusnas_book_id)->update(['path' => $path]);

            Log::info("✅ Downloaded and saved to storage: {$path}");
        } catch (\Exception $e) {
            Log::error('DownloadBookFile error: '.$e->getMessage());
        }
    }
}
