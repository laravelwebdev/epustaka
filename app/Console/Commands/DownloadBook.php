<?php

namespace App\Console\Commands;

use App\Helpers\Booklist;
use App\Jobs\DownloadBookFile;
use App\Models\Account;
use App\Models\Book;
use App\Models\BulkDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DownloadBook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'book:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk Download Books from iPusnas';

    private $apiLogin = 'https://api2-ipusnas.perpusnas.go.id/api/auth/login';

    private $baseHeaders = [
        'Origin' => 'https://ipusnas2.perpusnas.go.id',
        'Referer' => 'https://ipusnas2.perpusnas.go.id/',
        'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bulk = BulkDownload::where('is_active', true)->where('is_completed', false)->first();
        if (! $bulk) {
            $this->info('No active bulk download found.');

            return;
        }
        $categoryId = optional($bulk)->category_id;
        $offset = optional($bulk)->offset ?? 0;
        $accountId = 1;
        $limit = 5;
        if ($categoryId) {
            $this->getAccessToken($accountId);
            $token = Cache::get('ipusnas_token_'.$accountId);
            $response = (new Booklist($token))->fetchBookList($categoryId, $offset, $limit);
            $books = $response['data'] ?? [];
            if (empty($books)) {
                $bulk->is_completed = true;
                $bulk->save();
                $this->info('Bulk download completed.');

                return;
            }
            foreach ($books as $book) {
                $bookId = $book['id'];
                $bookExists = Book::where('ipusnas_book_id', $bookId)->exists();
                if (! $bookExists) {
                    DownloadBookFile::dispatch($accountId, $bookId, false);
                }
            }
            $bulk->offset = $offset + $limit;
            $bulk->save();
        } else {
            $this->info('No active bulk download found.');
        }
    }

    private function getAccessToken($accountId)
    {
        if (! Cache::has('ipusnas_token_'.$accountId)) {
            $account = Account::find($accountId);
            if ($account) {
                $result = $this->login($account->email, $account->password);
                if ($result['status'] === true && isset($result['data']['data']['access_token'])) {
                    Cache::put('ipusnas_token_'.$accountId, $result['data']['data']['access_token'], 300);
                }
            }
        }

    }

    public function login(string $email, string $password)
    {
        $headers = array_merge($this->baseHeaders, [
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)
            ->post($this->apiLogin, [
                'email' => $email,
                'password' => $password,
            ]);

        return ['status' => ! $response->failed(), 'data' => $response->json()];
    }
}
