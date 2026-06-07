<?php

use App\Http\Controllers\WebSocketController;
use Illuminate\Support\Facades\Route;

Route::controller(WebSocketController::class)->group(function () {
    Route::get('/check/{clientId}', 'checkConnection');
    Route::get('/trigger/{clientId}', 'triggerUpload');
    Route::post('/ack/{clientId}', 'handleAcknowledgement');
    Route::post('/upload/{clientId}', 'handleUpload');
});
