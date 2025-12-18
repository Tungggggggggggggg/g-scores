<?php

use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScoreController::class, 'index'])->name('home');
Route::post('/lookup', [ScoreController::class, 'lookup'])->name('lookup');
Route::post('/lookup.json', [ScoreController::class, 'lookupJson'])->name('lookup.json');
