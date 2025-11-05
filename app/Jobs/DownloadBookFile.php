<?php

namespace App\Jobs;

use App\Helpers\IpusnasDownloader;
use App\Models\Account;
use App\Models\Book;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Notifications\NovaNotification;

class DownloadBookFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $iPusnasBookId;

    protected $accountId;

    public function __construct($accountId, $bookIpusnasId)
    {
        $this->iPusnasBookId = $bookIpusnasId;
        $this->accountId = $accountId;
    }

    public function handle(): void
    {
        $download = new IpusnasDownloader($this->accountId);
        $result = $download->getBook($this->iPusnasBookId);
        $user_id = Account::find($this->accountId)->user_id;
        $user = User::find($user_id);
        if ($result !== null) {
            DB::transaction(function () use ($user) {
                $user->increment('points', 1);
            });
            $user->notify(
                NovaNotification::make()
                    ->message('Unduh Buku Gagal Dilakukan dengan alasan '.$result.'. Poin Anda Telah dikembalikan. Terima Kasih!')
                    ->icon('exclamation-triangle')
                    ->type('error')
            );
        } else {
            $bookTitle = Book::where('ipusnas_book_id', $this->iPusnasBookId)->value('book_title');
            $user->notify(
                NovaNotification::make()
                    ->message('Unduh Buku '.$bookTitle.' Berhasil! Silakan Cek Koleksi Buku Kamu. Terima Kasih!')
                    ->icon('check-circle')
                    ->type('success')
            );
        }
    }
}
