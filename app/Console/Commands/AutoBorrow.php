<?php

namespace App\Console\Commands;

use App\Jobs\DownloadBookFile;
use App\Models\AutoBorrow as ModelAutoBorrow;
use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoBorrow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'book:borrow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto Borrow Books from iPusnas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $borrow = ModelAutoBorrow::where('borrowed', false)->first();
        if (! $borrow) {
            $this->info('No auto borrow records found.');

            return;
        }
        $pendingBorrows = DB::table('jobs')->where('queue', 'borrow')->count();
        if ($pendingBorrows > 1) {
            $this->info('Too many pending borrow jobs. Skipping.');

            return;
        }
        $bookId = $borrow->ipusnas_book_id;
        $accountId = 1;
        $success = Book::where('ipusnas_book_id', $bookId)->exists();
        if (! $success) {
            DownloadBookFile::dispatch($accountId, $bookId, false, true)->onQueue('borrow');
            $this->info('Auto Borrow in queue.');
        } else {
            $borrow->borrowed = true;
            $borrow->save();
        }
    }
}
