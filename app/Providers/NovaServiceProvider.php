<?php

namespace App\Providers;

use App\Models\User;
use App\Nova\Dashboards\Terms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::mainMenu(function (Request $request) {
            return [
                MenuSection::dashboard(Terms::class)->icon('exclamation-triangle'),
                MenuSection::make('Admin', [
                    MenuItem::resource(\App\Nova\User::class),
                ])->icon('key'),
                MenuSection::make('iPusnas', [
                    MenuItem::resource(\App\Nova\Account::class),
                    MenuItem::resource(\App\Nova\Book::class),
                ])->icon('book-open'),
                MenuSection::make('Help', [
                    MenuItem::externalLink('Kontak Support', 'https://wa.me/6287716206091'),
                ])->icon('question-mark-circle'),

            ];
        });

        Nova::serving(function () {
            Route::post('/nova/logout', function () {
                auth()->logout();

                return redirect('/login');
            });
        });
        Nova::showUnreadCountInNotificationCenter();
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                Features::emailVerification(),
                Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes()
            // ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Terms,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
