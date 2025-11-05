<?php

use App\Http\Controllers\DownloadController;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;

Route::get('/', function () {
    return redirect(route('register'));
});

Route::get('/buypoin', function () {
    return view('buypoin');
})->name('buypoin');

Route::get('/dashboard', function () {
    return redirect(config('nova.path'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware([Authenticate::class])
    ->prefix(Nova::path())
    ->group(function () {
        Route::get('/api/books/download/{filename}', [DownloadController::class, 'download'])
            ->name('download');
    });

require __DIR__.'/auth.php';
