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
    $filename = "downloads/{$clientId}_100mbfile.txt";
    $disk = Storage::disk('local');
    $disk->makeDirectory(dirname($filename));

    $inputStream = fopen('php://input', 'rb');
    if (!$inputStream) {
        return response()->json([
            'status' => 'error',
            'message' => 'Cannot read incoming file stream'
        ], 400);
    }

    $success = $disk->writeStream($filename, $inputStream);
    fclose($inputStream);

    if ($success === false) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save uploaded file'
        ], 500);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'File successfully received and saved',
        'path' => $filename
    ]);
});
