<?php

namespace App\Nova\Actions;

use App\Helpers\IpusnasDecryptor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadLink extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public $name = 'Download Link';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();
        $password = IpusnasDecryptor::generatePasswordPDF(
            $model->ipusnas_user_id,
            $model->ipusnas_book_id,
            $model->epustaka_id,
            $model->borrow_key
        );

        return ActionResponse::openInNewTab(route('view.book', ['pdfPass' => $password, 'filename' => basename($model->path)]));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
