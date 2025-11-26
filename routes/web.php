<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('video.create');
});

Route::get('/video/create', [VideoController::class, 'create'])->name('video.create');
Route::post('/video/store', [VideoController::class, 'store'])->name('video.store');
Route::get('/video/show/{id}', [VideoController::class, 'show'])->name('video.show');
Route::get('/video/progress/{id}', [VideoController::class, 'progress'])->name('video.progress');
