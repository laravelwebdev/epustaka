<?php

use App\Http\Controllers\BookDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(route('register'));
});

Route::get('/buypoin', function () {
    return view('buypoin');
})->name('buypoin');

Route::get('/dashboard', function () {
    return redirect(config('nova.path'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/download/{filename}/{password?}', [BookDownloadController::class, 'download'])
    ->name('download')
    ->prefix(config('nova.path'))
    ->middleware(['auth', 'verified']);

require __DIR__.'/auth.php';
