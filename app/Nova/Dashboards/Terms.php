<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\Terms as CardsTerms;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Terms extends Dashboard
{
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
