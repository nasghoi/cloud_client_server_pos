<?php


use App\Events\RequestFileEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// 1. Trigger the action via WebSocket Broadcast
Route::get('/trigger/{clientId}', function ($clientId) {
    // Broadcast the event across the Reverb control plane
    broadcast(new RequestFileEvent($clientId));

    return response()->json([
        'status' => 'success',
        'message' => 'Upload signal broadcasted to client: ' . $clientId
    ]);
});

// 2. Handle the incoming heavy data stream via HTTP POST
Route::post('/upload/{clientId}', function (Request $request, $clientId) {
    // Matches your local testing file requirement target name
    $filename = "downloads/{$clientId}_100mbfile.txt";

    // Open the raw stream from the incoming request body
    $inputStream = fopen('php://input', 'r');
    if (!$inputStream) {
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot read input stream'
        ], 400);
    }

    // Stream directly to the storage/app folder chunk-by-chunk
    Storage::disk('local')->put($filename, $inputStream);
    fclose($inputStream);

    return response()->json([
        'status' => 'success',
        'message' => 'File successfully streamed into client storage',
        'path' => $filename
    ]);
});
