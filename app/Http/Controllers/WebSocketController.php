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
     * Handle chunked file upload from client.
     * Supports resumable uploads with progress tracking.
     */
    public function handleUpload(Request $request, string $clientId)
    {
        $chunkNumber = (int)$request->input('chunkNumber', $request->header('X-Chunk-Number', 0));
        $totalChunks = (int)$request->input('totalChunks', $request->header('X-Total-Chunks', 0));
        $chunkSize = (int)$request->input('chunkSize', $request->header('X-Chunk-Size', 0));
        $uploadId = $request->input('uploadId', $request->header('X-Upload-Id'));

        // Validate required parameters
        if (!$uploadId || $chunkNumber < 0 || $totalChunks <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing required upload parameters'
            ], 400);
        }

        $tempDir = storage_path("uploads/temp/{$uploadId}");
        $finalFilename = "downloads/{$clientId}_100mbfile.txt";
        $disk = Storage::disk('local');

        try {
            // Create temp directory for chunks
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Read and save the chunk
            $chunkPath = "{$tempDir}/chunk_{$chunkNumber}";

            if ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');
                $uploadedFile->move($tempDir, "chunk_{$chunkNumber}");
            } else {
                $inputStream = fopen('php://input', 'rb');
                if (!$inputStream) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot read incoming file stream'
                    ], 400);
                }

                $chunkFile = fopen($chunkPath, 'wb');
                if (!$chunkFile) {
                    fclose($inputStream);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot write chunk file'
                    ], 500);
                }

                // Stream chunk to disk (memory-efficient)
                $written = stream_copy_to_stream($inputStream, $chunkFile);
                fclose($inputStream);
                fclose($chunkFile);

                if ($written === 0 && $chunkSize > 0) {
                    @unlink($chunkPath);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to write chunk data'
                    ], 500);
                }
            }

            // Check if all chunks are received
            $receivedChunks = count(glob("{$tempDir}/chunk_*"));

            if ($receivedChunks === $totalChunks) {
                // All chunks received, merge them
                $success = $this->mergeChunks($tempDir, $totalChunks, $finalFilename, $disk);

                if ($success) {
                    // Clean up temp directory
                    $this->cleanupTempDir($tempDir);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'File upload completed',
                        'path' => $finalFilename,
                        'uploadId' => $uploadId,
                        'chunkNumber' => $chunkNumber,
                        'totalChunks' => $totalChunks
                    ]);
                } else {
                    $this->cleanupTempDir($tempDir);
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to merge chunks'
                    ], 500);
                }
            }

            // More chunks expected
            return response()->json([
                'status' => 'chunk_received',
                'message' => "Chunk {$chunkNumber} of {$totalChunks} received",
                'uploadId' => $uploadId,
                'chunkNumber' => $chunkNumber,
                'totalChunks' => $totalChunks,
                'progress' => round(($receivedChunks / $totalChunks) * 100, 2)
            ]);

        } catch (\Exception $e) {
            $this->cleanupTempDir($tempDir);
            return response()->json([
                'status' => 'error',
                'message' => 'Upload error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Merge all chunks into final file.
     */
    private function mergeChunks(string $tempDir, int $totalChunks, string $finalFilename, $disk): bool
    {
        try {
            $disk->makeDirectory(dirname($finalFilename));
            $finalPath = $disk->path($finalFilename);

            $finalFile = fopen($finalPath, 'wb');
            if (!$finalFile) {
                return false;
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = "{$tempDir}/chunk_{$i}";
                if (!file_exists($chunkPath)) {
                    fclose($finalFile);
                    return false;
                }

                $chunkFile = fopen($chunkPath, 'rb');
                if (!$chunkFile) {
                    fclose($finalFile);
                    return false;
                }

                stream_copy_to_stream($chunkFile, $finalFile);
                fclose($chunkFile);
            }

            fclose($finalFile);
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up temporary chunk directory.
     */
    private function cleanupTempDir(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $files = glob("{$tempDir}/*");
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }

    /**
     * Get upload progress for a resumable upload.
     */
    public function getUploadProgress(string $uploadId)
    {
        $tempDir = storage_path("uploads/temp/{$uploadId}");

        if (!is_dir($tempDir)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Upload session not found',
                'uploadId' => $uploadId
            ], 404);
        }

        $chunks = glob("{$tempDir}/chunk_*");
        return response()->json([
            'status' => 'progress',
            'uploadId' => $uploadId,
            'chunksReceived' => count($chunks),
            'chunks' => array_map(function ($path) {
                return (int)basename($path, 'chunk_');
            }, $chunks)
        ]);
    }

    /**
     * Cancel/abort an upload session and clean up temporary files.
     */
    public function abortUpload(string $uploadId)
    {
        $tempDir = storage_path("uploads/temp/{$uploadId}");
        $this->cleanupTempDir($tempDir);

        return response()->json([
            'status' => 'cancelled',
            'message' => 'Upload cancelled and temporary files cleaned up',
            'uploadId' => $uploadId
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
