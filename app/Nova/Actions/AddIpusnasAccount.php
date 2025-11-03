<?php

namespace App\Nova\Actions;

use App\Helpers\IpusnasDownloader;
use App\Models\Account;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AddIpusnasAccount extends Action
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
        $login = new IpusnasDownloader;

        $result = $login->login($fields->email, $fields->password);

        if ($result['status'] === true) {
            $data = $result['data'];
            $account = Account::firstOrNew(['email' => $fields->email]);
            $account->password = $fields->password;
            $account->name = $data['data']['name'] ?? null;
            $account->ipusnas_id = $data['data']['id'] ?? null;
            $account->organization_id = $data['data']['organization_id'] ?? null;
            $account->verified = $data['data']['verified'] ?? false;
            $account->save();
            return Action::message('Akun IPusnas berhasil ditambahkan.');
        } else {
            return Action::danger($result['data']['error']['message']);
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Email::make('Email', 'email')
                ->rules('required', 'email', 'max:255'),
            Text::make('Password', 'password')
                ->rules('required', 'string', 'max:255'),
        ];
    }
}
