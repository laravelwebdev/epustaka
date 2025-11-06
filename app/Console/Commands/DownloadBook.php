<?php

namespace App\Console\Commands;

use App\Helpers\Booklist;
use App\Jobs\DownloadBookFile;
use App\Models\Account;
use App\Models\BulkDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bulk = BulkDownload::where('is_active', true)->first();
        $categoryId = optional($bulk)->category_id;
        $offset = optional($bulk)->offset ?? 0;
        $accountId = 1;
        if ($categoryId) {
            $this->getAccessToken($accountId);
            $token = Cache::get('ipusnas_token_'.$accountId);
            $response = (new Booklist($token))->fetchBookList($categoryId, $offset);
            $bookId = $response['data'][0]['id'];
            DownloadBookFile::dispatch($accountId, $bookId);
            $bulk->offset = $offset + 1; // Example increment
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
}
