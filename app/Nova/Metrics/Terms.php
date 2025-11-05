<?php

namespace App\Nova\Metrics;

use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

class Terms extends Table
{
    /**
     * Calculate the value of the metric.
     *
     * @return array<int, \Laravel\Nova\Metrics\MetricTableRow>
     */
    public function calculate(NovaRequest $request): array
    {
        return [
            MetricTableRow::make()
                ->icon('exclamation-triangle')
                ->iconClass('text-red-500')
                ->title('Fungsi Website E-Pustaka')
                ->subtitle('Menyediakan akses ke file PDF Ipusnas agar lebih mudah untuk dibaca.'),
            MetricTableRow::make()
                ->icon('exclamation-triangle')
                ->iconClass('text-red-500')
                ->title('Tentang Hak Cipta')
                ->subtitle('Epustaka tidak menyimpan file Buku secara langsung. Semua file yang diunduh adalah tanggung jawab pengunduh.'),
            MetricTableRow::make()
                ->icon('exclamation-triangle')
                ->iconClass('text-red-500')
                ->title('Penggunaan Buku')
                ->subtitle('Gunakan hanya untuk kepentingan pribadi, jangan disebarluaskan atau diperjualbelikan. Tanggung jawab ada pada pengguna.'),

        ];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     */
    public function cacheFor(): ?DateTimeInterface
    {
        // return now()->addMinutes(5);

        return null;
    }
}
