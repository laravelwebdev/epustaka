<?php

namespace App\Jobs;

use App\Helpers\IpusnasDecryptor;
use App\Helpers\ZipExtractor;
use App\Models\Book;
use App\Models\FailedBook;
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

            $response = Http::withHeaders($headers)->timeout(1200)->get($url);
            if ($response->failed()) {
                $failed = FailedBook::firstOrNew(['ipusnas_book_id' => $this->book->ipusnas_book_id]);
                $failed->failed_url = true;
                $failed->save();
                Book::where('ipusnas_book_id', $this->book->ipusnas_book_id)->delete();

                return;
            }
            // Simpan ke temporary storage
            Storage::put($path, $response->body());

            if ($this->book->using_drm) {
                $passwordZip = IpusnasDecryptor::generatePasswordZip(
                    $this->book->ipusnas_user_id,
                    $this->book->ipusnas_book_id,
                    $this->book->epustaka_id,
                    $this->book->borrow_key
                );

            } else {
                $passwordZip = '';
            }
            $extractedPath = (new ZipExtractor)->extract(storage_path('app/private/'.$path), $passwordZip);
            Book::where('ipusnas_book_id', $this->book->ipusnas_book_id)->update(['path' => $extractedPath]);
            FailedBook::where('ipusnas_book_id', $this->book->ipusnas_book_id)->delete();
        } catch (\Exception $e) {
            Log::error('DownloadBookFile error: '.$e->getMessage());
        }
    }
}
