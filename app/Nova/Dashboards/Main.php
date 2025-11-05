<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\Terms as CardsTerms;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    public static $name = 'Terms';

    /**
     * Get the cards for the dashboard.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(): array
    {
        return [
            CardsTerms::make()->width('full'),
        ];
    }
}
