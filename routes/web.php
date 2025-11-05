<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return redirect(route('register'));
});

Route::get('/buypoin', function () {
    return view('buypoin');
})->name('buypoin');

Route::get('/dashboard', function () {
    return redirect(config('nova.path'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/books/{filename}', [PdfController::class, 'servePdf'])->name('serve.pdf');

Route::get('/view-book/{pdfPass}/{filename}', [PdfController::class, 'showView'])
    ->name('view.book')
    ->prefix(config('nova.path'))
    ->middleware(['auth', 'verified']);


require __DIR__.'/auth.php';
