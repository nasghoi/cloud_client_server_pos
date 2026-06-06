<?php

use App\Events\RequestFileEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// trigger the action
Route::get('/trigger/{clientId}', function ($clientId) {
    broadcast(new RequestFileEvent($clientId));
    return response()->json(['status' => 'Signal dropped down the WebSocket pipe']);
});

// handle the incoming file stream
Route::post('/upload/{clientId}', function (Request $request, $clientId) {
    $filename = "downloads/{$clientId}_received.txt";

    $inputStream = fopen('php://input', 'r');
    if (!$inputStream) {
        return response()->json(['error' => 'Cannot read input stream'], 400);
    }

    Storage::disk('local')->put($filename, $inputStream);
    fclose($inputStream);

    return response()->json(['status' => 'File successfully streamed into client storage']);
});
