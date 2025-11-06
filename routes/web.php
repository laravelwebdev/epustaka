<?php

use App\Http\Controllers\BookDownloadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Models\Book;

Route::get('/', function () {
    return redirect(route('login'));
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
