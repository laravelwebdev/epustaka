<?php

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

require __DIR__.'/auth.php';
