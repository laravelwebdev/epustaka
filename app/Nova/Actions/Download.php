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

class Download extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public $name = 'Download';

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

        return ActionResponse::redirect(route('download', ['password' => $password, 'filename' => basename($model->path)]));
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
