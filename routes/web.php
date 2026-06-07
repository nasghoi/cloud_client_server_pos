<?php

use App\Http\Controllers\WebSocketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/downloads', [WebSocketController::class, 'downloads'])->name('downloads');
