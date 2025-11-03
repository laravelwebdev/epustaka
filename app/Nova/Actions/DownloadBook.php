<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadBook extends Action
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Link iPusnas', 'ipusnas_link')
                ->rules('required', 'url', 'max:255')
                ->help('Contoh:https://ipusnas2.perpusnas.go.id/book/5af9ac34-122a-41a4-9c2f-66dd2d065f27'),
            Select::make('Akun iPusnas', 'account_id')
                ->options(function () {
                    return \App\Models\Account::where('user_id', Auth::user()->id)->pluck('email', 'id')->toArray();
                })
                ->rules('required')
                ->searchable()
                ->help('Pilih akun iPusnas yang akan digunakan untuk mendownload buku.'),
        ];
    }
}
