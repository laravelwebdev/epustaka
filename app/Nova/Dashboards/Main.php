<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\Terms;
use Laravel\Nova\Dashboard;

class Main extends Dashboard
{
    public $name = 'Terms';

    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            Terms::make()->width('full'),
        ];
    }
}
