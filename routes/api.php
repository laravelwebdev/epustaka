<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::post('/savebook', [BookController::class, 'saveBook'])->name('books.save');
Route::post('/updateborrowedstatus', [BookController::class, 'updateBorrowedStatus'])->name('books.updateborrowedstatus');
Route::post('/updatebookpath', [BookController::class, 'updateBookPathToServer'])->name('books.updatebookpath');
