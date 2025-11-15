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

    protected $notify;

    protected $autoBorrow;

    public function __construct($accountId, $bookIpusnasId, $notify = true, $autoBorrow = false)
    {
        $this->iPusnasBookId = $bookIpusnasId;
        $this->accountId = $accountId;
        $this->autoBorrow = $autoBorrow;
        $this->notify = $notify;
    }

    public function handle(): void
    {
        $download = new IpusnasDownloader($this->accountId, $this->autoBorrow);
        $result = $download->getBook($this->iPusnasBookId);
        $user_id = Account::find($this->accountId)->user_id;
        $user = User::find($user_id);
        if ($result !== null) {
            DB::transaction(function () use ($user) {
                $user->increment('points', 1);
            });
            if ($this->notify) {
                $user->notify(
                    NovaNotification::make()
                        ->message('Gagal menambahkan buku ke koleksi kamu dengan alasan '.$result.'. Poin Anda Telah dikembalikan. Terima Kasih!')
                        ->icon('exclamation-triangle')
                        ->type('error')
                );
            }
        } else {
            if ($this->notify) {
                $book = Book::where('ipusnas_book_id', $this->iPusnasBookId)->first();
                $user->notify(
                    NovaNotification::make()
                        ->message('Buku '.$book->book_title.' Berhasil ditambahkan ke Koleksi buku Kamu! Silakan Cek Koleksi Buku Kamu. Terima Kasih!')
                        ->action('Lihat Buku', '/resources/books/'.$book->id)
                        ->icon('check-circle')
                        ->type('success')
                );
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        $user_id = Account::find($this->accountId)->user_id;
        $user = User::find($user_id);
        DB::transaction(function () use ($user) {
            $user->increment('points', 1);
        });
        if ($this->notify) {
            $user->notify(
                NovaNotification::make()
                    ->message('Gagal menambahkan buku ke koleksi kamu. Poin Anda Telah dikembalikan. Terima Kasih!')
                    ->icon('exclamation-triangle')
                    ->type('error')
            );
        }
    }
}
