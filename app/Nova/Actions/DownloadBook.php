<?php

namespace App\Nova\Actions;

use App\Jobs\DownloadBookFile;
use App\Models\Account;
use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadBook extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public $withoutActionEvents = true;

    public $name = 'Tambah Koleksi Buku';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = Auth::user();
        $path = parse_url($fields->ipusnas_link, PHP_URL_PATH);
        $iPusnasBookId = $path === null ? '' : basename($path);
        $book = Book::where('ipusnas_book_id', $iPusnasBookId)->first();
        $book_id = optional($book)->id;
        $user_id = $user->id;
        $exist = DB::table('book_user')
            ->where('book_id', $book_id)
            ->where('user_id', $user_id)
            ->exists();
        if (optional($user)->points < 1) {
            return Action::redirect(route('buypoin'));
        }
        // cek apakah buku sdh ada di koleksi user
        if ($exist) {
            return Action::message('Buku ini sudah ada di koleksi kamu. Silakan Cek di koleksi buku kamu');
        }
        // kurangi poin user
        DB::transaction(function () use ($user) {
            $user->decrement('points', 1);
        });
        $user->refresh();
        if ($book) {
            $book->users()->attach($user_id);

            return Action::message(' Buku telah ditambahkan ke koleksi kamu.');
        }

        DownloadBookFile::dispatch($fields->account_id, $iPusnasBookId);

        return Action::message('Penambahan Buku sedang berlangsung. Cek notifikasi secara berkala.');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Link Buku iPusnas', 'ipusnas_link')
                ->rules('required', 'url', 'max:255')
                ->help('Contoh: https://ipusnas2.perpusnas.go.id/book/5af9ac34-122a-41a4-9c2f-66dd2d065f27'),
            Select::make('Akun iPusnas', 'account_id')
                ->options(function () {
                    return Account::where('user_id', Auth::user()->id)->pluck('email', 'id')->toArray();
                })
                ->rules('required')
                ->searchable()
                ->help('Pilihan Kosong? Tambahkan terlebih dulu Akun iPusnas kamu melalui menu Akun iPusnas'),
        ];
    }
}
