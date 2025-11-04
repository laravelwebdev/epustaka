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

    protected $bookId;

    protected $bookUrl;

    public function __construct($bookId, $bookUrl)
    {
        $this->bookId = $bookId;
        $this->bookUrl = $bookUrl;
    }

    public function handle(): void
    {
        try {
            $url = $this->bookUrl;
            $safeName = md5($this->bookId);
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
            $filename = "{$safeName}.{$extension}";
            $path = "books/{$filename}";

            Log::info("Downloading book file: {$url}");

            $response = Http::timeout(1200)->get($url);
            if ($response->failed()) {
                Log::warning("Failed to download: {$url}");

                return;
            }
            // Simpan ke storage/app/public/books/
            Storage::put($path, $response->body());

            // Update path di database
            Book::where('ipusnas_book_id', $this->bookId)->update(['path' => $path]);

            Log::info("✅ Downloaded and saved to storage: {$path}");
        } catch (\Exception $e) {
            Log::error('DownloadBookFile error: '.$e->getMessage());
        }
    }
}
