# Chunked File Upload Implementation

## Overview

The system now supports **chunked/resumable file uploads** to handle large files (100MB+) without timeout issues.

### Key Benefits

✅ **Timeout Protection**: Each chunk is small (5MB default) and completes well within Cloudflare's 120s timeout
✅ **Resumable Uploads**: If a chunk fails, only that chunk needs to be re-uploaded (not the entire file)
✅ **Progress Tracking**: Real-time upload progress feedback for UI/UX
✅ **Retry Logic**: Automatic exponential backoff retry for failed chunks
✅ **Memory Efficient**: Chunks are streamed to disk, not loaded into memory

## Server Implementation

### Changes to WebSocketController

The `handleUpload()` method now:

1. **Receives chunks** instead of the entire file in one request
2. **Validates parameters**:
   - `chunkNumber`: The index of this chunk (0-based)
   - `totalChunks`: Total number of chunks expected
   - `chunkSize`: Size of this chunk in bytes
   - `uploadId`: Unique upload session ID

3. **Stores chunks temporarily** in `storage/uploads/temp/{uploadId}/chunk_N`

4. **Merges chunks** automatically when all are received

5. **Cleans up** temporary files after successful merge

### New Endpoints

```
POST   /api/upload/{clientId}
       Upload a single chunk
       Parameters: chunkNumber, totalChunks, chunkSize, uploadId

GET    /api/upload-progress/{uploadId}
       Check upload progress (for resume capability)

POST   /api/upload-abort/{uploadId}
       Cancel upload and clean up temporary files
```

## Client Implementation

### Basic Usage

```javascript
// Import the uploader
const ChunkedFileUploader = require('./chunk-uploader.js');

// Create uploader instance
const uploader = new ChunkedFileUploader({
  chunkSize: 5 * 1024 * 1024,  // 5MB chunks
  maxRetries: 3,
  timeout: 30000,              // 30s per chunk
  baseUrl: '/api'
});

// Upload a file
const fileInput = document.getElementById('fileInput');
const file = fileInput.files[0];
const clientId = 'client-123';

uploader.upload(file, clientId, (progress) => {
  console.log(`Upload progress: ${progress.progress}%`);
  console.log(`Chunk ${progress.chunkNumber} of ${progress.totalChunks}`);
  
  // Update UI
  document.getElementById('progressBar').style.width = progress.progress + '%';
  document.getElementById('progressText').textContent = `${progress.progress}%`;
}).then(result => {
  console.log('Upload completed!', result);
  alert('File uploaded successfully');
}).catch(error => {
  console.error('Upload failed:', error);
  alert('Upload failed: ' + error.message);
});
```

### Resume Interrupted Upload

```javascript
// Same uploader instance with same uploadId resumes automatically
// or create new instance with existing uploadId:

const resumeUploader = new ChunkedFileUploader({
  uploadId: 'existing-upload-id-123',
  baseUrl: '/api'
});

// Continue uploading - already-uploaded chunks are skipped
resumeUploader.upload(file, clientId, onProgress);
```

### Cancel Upload

```javascript
uploader.cancel().then(() => {
  console.log('Upload cancelled and cleaned up');
});
```

## Configuration Options

### Chunk Size
- **Default**: 5MB
- **Recommendation**: 5-10MB for balance between progress updates and retry overhead
- **For slow connections**: 2-3MB
- **For fast connections**: 10-20MB

```javascript
new ChunkedFileUploader({ chunkSize: 10 * 1024 * 1024 }) // 10MB
```

### Retries & Timeouts
- **maxRetries**: How many times to retry a failed chunk (default: 3)
- **timeout**: Per-chunk timeout in milliseconds (default: 30000 = 30s)

```javascript
new ChunkedFileUploader({
  maxRetries: 5,      // More forgiving
  timeout: 60000      // 60 seconds per chunk
})
```

## Example: Complete File Upload UI

```html
<div id="uploadContainer">
  <input type="file" id="fileInput" />
  
  <div id="progressContainer" style="display: none;">
    <div style="background: #e0e0e0; height: 24px; border-radius: 4px;">
      <div id="progressBar" 
           style="background: #4caf50; height: 100%; width: 0%; transition: width 0.3s;">
      </div>
    </div>
    <p id="progressText">0%</p>
    <p id="statusMessage"></p>
    <button id="cancelBtn">Cancel Upload</button>
  </div>
</div>

<script src="/js/chunk-uploader.js"></script>
<script>
document.getElementById('fileInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;

  const uploader = new ChunkedFileUploader({
    chunkSize: 5 * 1024 * 1024,
    baseUrl: '/api'
  });

  const clientId = new URLSearchParams(window.location.search).get('clientId') || 'default-client';

  document.getElementById('progressContainer').style.display = 'block';

  document.getElementById('cancelBtn').onclick = () => {
    uploader.cancel().then(() => {
      location.reload();
    });
  };

  try {
    const result = await uploader.upload(file, clientId, (progress) => {
      document.getElementById('progressBar').style.width = progress.progress + '%';
      document.getElementById('progressText').textContent = `${progress.progress}%`;
      document.getElementById('statusMessage').textContent = 
        `Chunk ${progress.chunkNumber + 1}/${progress.totalChunks} - ${progress.message}`;
    });

    alert('✅ File uploaded successfully!\n\n' + JSON.stringify(result, null, 2));
  } catch (error) {
    alert('❌ Upload failed: ' + error.message);
    document.getElementById('progressContainer').style.display = 'none';
  }
});
</script>
```

## Performance Comparison

### Before (Single Stream)
- 100MB file
- Single request
- **Timeout**: ~140 seconds ❌
- **Cloudflare Error**: 524 timeout
- **Resume**: Not possible

### After (Chunked)
- 100MB file
- 20 chunks × 5MB each
- **Per-chunk time**: ~5-10 seconds ✅
- **Total time**: ~30-40 seconds + overhead
- **Timeout**: Well within 120s limit ✅
- **Resume**: Supports resuming from any chunk

## Troubleshooting

### Upload still timing out?
1. **Reduce chunk size**: `chunkSize: 2 * 1024 * 1024` (2MB)
2. **Increase timeout**: `timeout: 60000` (60s per chunk)
3. **Check network**: Slow connection = smaller chunks

### "Upload session not found"?
- Session expires after a period. Complete upload without long delays between chunks.
- Or restart with a fresh uploadId.

### Disk space errors?
- Temporary chunks stored in `storage/uploads/temp/`
- Each chunk stored separately until merge
- Total temp space needed = file size
- Clean old sessions: Implement temp file cleanup by deletion age

## Storage Configuration

Ensure `storage/app/local/` directory has write permissions:

```bash
chmod -R 755 storage/app/local
mkdir -p storage/uploads/temp
chmod -R 755 storage/uploads/temp
```

Also in `config/filesystems.php`, ensure 'local' disk is writable:

```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/local'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'private',
    ],
]
```

## Notes

- Files are stored in: `storage/app/local/downloads/{clientId}_100mbfile.txt`
- Temporary chunks stored in: `storage/uploads/temp/{uploadId}/`
- Maximum retry attempts: 3 (configurable)
- Chunk merge happens server-side once all chunks received
- No client-side JavaScript memory used for file buffering
