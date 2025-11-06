<?php

namespace App\Nova\Metrics;

use DateTimeInterface;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

class IPusnasTerms extends Table
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
                ->title('Kenapa harus dihubungkan dengan Akun iPusnas?')
                ->subtitle('Hanya buku yang dipinjam saja yang bisa diunduh.'),
            MetricTableRow::make()
                ->icon('exclamation-triangle')
                ->iconClass('text-red-500')
                ->title('Keamanan Akun iPusnas Saya?')
                ->subtitle('Epustaka tidak menyimpan password iPusnas Anda, hanya menghubungkan untuk dapat token peminjaman buku. Jika Anda Ragu silakan gunakan akun iPusnas cadangan.'),
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
