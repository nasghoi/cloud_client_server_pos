<?php

namespace App\Http\Controllers;

use App\Events\RequestFileEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;

class WebSocketController extends Controller
{
    /**
     * Helper to get client connection status from Reverb/Pusher.
     */
    private function getClientConnectionStatus(string $clientId): array
    {
        try {
            $driver = Broadcast::driver();
            if (!$driver instanceof PusherBroadcaster) {
                return [
                    'online' => false,
                    'response' => response()->json([
                        'status' => 'error',
                        'message' => 'Broadcasting driver is not configured for Pusher/Reverb'
                    ], 500)
                ];
            }

            $pusher = $driver->getPusher();
            $info = $pusher->getChannelInfo('restaurant.' . $clientId);

            if ($info && isset($info->occupied) && $info->occupied) {
                return [
                    'online' => true,
                    'response' => response()->json([
                        'status' => 'online',
                        'message' => "Client {$clientId} is online"
                    ])
                ];
            }

            return [
                'online' => false,
                'response' => response()->json([
                    'status' => 'offline',
                    'message' => "Client {$clientId} is offline/unreachable"
                ], 404)
            ];

        } catch (\Exception $e) {
            return [
                'online' => false,
                'response' => response()->json([
                    'status' => 'error',
                    'message' => 'WebSocket server is unreachable: ' . $e->getMessage()
                ], 500)
            ];
        }
    }

    /**
     * Check if the outbound client connection is online.
     */
    public function checkConnection(string $clientId)
    {
        $status = $this->getClientConnectionStatus($clientId);
        return $status['response'];
    }

    /**
     * Trigger file download signal to the client.
     */
    public function triggerUpload(string $clientId)
    {
        $status = $this->getClientConnectionStatus($clientId);
        if (!$status['online']) {
            return $status['response'];
        }

        broadcast(new RequestFileEvent($clientId));

        return response()->json([
            'status' => 'success',
            'message' => 'Upload signal broadcasted to client: ' . $clientId
        ]);
    }

    /**
     * Handle client's websocket acknowledgment.
     */
    public function handleAcknowledgement(Request $request, string $clientId)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Client acknowledgement received',
            'clientId' => $clientId,
            'payload' => $request->all()
        ]);
    }

    /**
     * Stream download the file from client and save to local storage.
     */
    public function handleUpload(Request $request, string $clientId)
    {
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
    }

    /**
     * List all downloaded files from storage.
     */
    public function downloads()
    {
        $disk  = Storage::disk('local');
        $paths = $disk->files('downloads');

        $files = collect($paths)->map(function ($path) use ($disk) {
            $name     = basename($path);
            $bytes    = $disk->size($path);
            $modified = date('Y-m-d H:i', $disk->lastModified($path));

            // Extract client ID from filename pattern: {clientId}_100mbfile.txt
            $client = Str::before($name, '_');

            return [
                'name'     => $name,
                'client'   => $client ?: 'unknown',
                'size'     => $this->formatBytes($bytes),
                'modified' => $modified,
            ];
        })->sortByDesc('modified')->values()->all();

        return view('downloads', compact('files'));
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
