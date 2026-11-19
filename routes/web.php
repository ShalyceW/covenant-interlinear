<?php

use App\Http\Controllers\ConcordanceController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ScriptureController;
use App\Http\Controllers\WordReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScriptureController::class, 'home'])->name('home');
Route::get('/library/{book:slug}/{chapter}', [ScriptureController::class, 'show'])->name('scripture.show');
Route::get('/library/{book:slug}/{chapter}/print', [PrintController::class, 'show'])->name('scripture.print');
Route::get('/word-study/{word}', [ScriptureController::class, 'wordStudy'])->name('word-study.show');
Route::get('/concordance', ConcordanceController::class)->name('concordance.index');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::patch('/editor/words/{word}', [WordReviewController::class, 'update'])->name('words.review');
});

require __DIR__.'/settings.php';
