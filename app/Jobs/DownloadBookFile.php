<?php

namespace App\Jobs;

use App\Helpers\IpusnasDecryptor;
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
            $path = "temp/{$filename}";

            Log::info("Downloading book file: {$url}");

            $response = Http::withHeaders($headers)->timeout(1200)->get($url);
            if ($response->failed()) {
                Log::warning("Failed to download: {$url}");

                return;
            }
            // Simpan ke temporary storage
            Storage::put($path, $response->body());

            if ($this->book->using_drm) {
                Log::warning("⚠️ Book uses DRM, additional processing may be required: {$this->book->ipusnas_book_id}");
                $decryptedKey = (new IpusnasDecryptor(Storage::path('')))->decryptKey(
                    $this->book->user_id,
                    $this->book->ipusnas_book_id,
                    $this->book->epustaka_id,
                    $this->book->borrow_key
                );
                $passwordZip = (new IpusnasDecryptor(Storage::path('')))->generatePasswordZip($decryptedKey);
                $extractedPath = (new IpusnasDecryptor(Storage::path('')))->extractZip(
                    Storage::path($path),
                    $passwordZip,
                    $this->book->ipusnas_book_id
                );
                Storage::move($extractedPath, "books/{$filename}");
                $path = "books/{$filename}";
            } else {
                Log::warning("✅ Book downloaded without DRM: {$this->book->ipusnas_book_id}");

                $finalPath = "books/{$filename}";

                if (Storage::exists($path)) {
                    try {
                        if (Storage::move($path, $finalPath)) {
                            Log::info("Moved file from {$path} to {$finalPath}");
                            // update $path so DB stores the final location
                            $path = $finalPath;
                        } else {
                            Log::warning("Failed to move {$path} to {$finalPath}; keeping original path");
                        }
                    } catch (\Exception $e) {
                        Log::error('Error moving file: '.$e->getMessage());
                    }
                } else {
                    Log::warning("Source file not found in temp: {$path}");
                }
            }

            // Update path di database
            Book::where('ipusnas_book_id', $this->book->ipusnas_book_id)->update(['path' => $path]);

            Log::info("✅ Downloaded and saved to storage: {$path}");
        } catch (\Exception $e) {
            Log::error('DownloadBookFile error: '.$e->getMessage());
        }
    }
}
