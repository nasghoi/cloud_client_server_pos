/**
 * Chunked File Uploader - Handles resumable file uploads via chunks
 * Supports progress tracking, resume capability, and timeout protection
 */
class ChunkedFileUploader {
  constructor(config = {}) {
    this.chunkSize = config.chunkSize || 1 * 1024 * 1024; // 1MB default
    this.maxRetries = config.maxRetries || 3;
    this.timeout = config.timeout || 30000; // 30 seconds per chunk
    this.uploadId = config.uploadId || this.generateUploadId();
    this.baseUrl = config.baseUrl || '/api';
  }

  /**
   * Generate a unique upload ID for this session
   */
  generateUploadId() {
    return `upload_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Upload a file in chunks
   */
  async upload(file, clientId, onProgress = null) {
    const totalChunks = Math.ceil(file.size / this.chunkSize);
    const uploadId = this.uploadId;
    const uploadedChunks = new Set();

    console.log(`Starting chunked upload: ${file.name} (${this.formatBytes(file.size)})`);
    console.log(`Total chunks: ${totalChunks}, Chunk size: ${this.formatBytes(this.chunkSize)}`);

    try {
      // Check for existing upload progress
      const progress = await this.getUploadProgress(uploadId);
      if (progress.chunksReceived > 0) {
        console.log(`Resuming upload with ${progress.chunksReceived} chunks already received`);
        progress.chunks.forEach(chunk => uploadedChunks.add(chunk));
      }
    } catch (e) {
      console.log('Starting fresh upload');
    }

    // Upload chunks
    for (let chunkNumber = 0; chunkNumber < totalChunks; chunkNumber++) {
      // Skip already uploaded chunks
      if (uploadedChunks.has(chunkNumber)) {
        if (onProgress) {
          onProgress({
            uploadId,
            chunkNumber,
            totalChunks,
            progress: Math.round(((uploadedChunks.size + 1) / totalChunks) * 100),
            status: 'chunk_skipped'
          });
        }
        continue;
      }

      const start = chunkNumber * this.chunkSize;
      const end = Math.min(start + this.chunkSize, file.size);
      const chunk = file.slice(start, end);

      let retryCount = 0;
      let uploaded = false;

      while (retryCount < this.maxRetries && !uploaded) {
        try {
          const result = await this.uploadChunk(
            chunk,
            chunkNumber,
            totalChunks,
            clientId,
            uploadId
          );

          if (result.status === 'success' || result.status === 'chunk_received') {
            uploadedChunks.add(chunkNumber);
            uploaded = true;

            if (onProgress) {
              onProgress({
                uploadId,
                chunkNumber,
                totalChunks,
                progress: Math.round((uploadedChunks.size / totalChunks) * 100),
                status: result.status,
                message: result.message
              });
            }
          }
        } catch (error) {
          retryCount++;
          console.warn(`Chunk ${chunkNumber} upload failed (attempt ${retryCount}/${this.maxRetries}):`, error.message);

          if (retryCount < this.maxRetries) {
            // Exponential backoff
            const delay = Math.min(1000 * Math.pow(2, retryCount - 1), 10000);
            console.log(`Retrying in ${delay}ms...`);
            await this.sleep(delay);
          } else {
            throw new Error(`Failed to upload chunk ${chunkNumber} after ${this.maxRetries} retries`);
          }
        }
      }

      if (!uploaded) {
        throw new Error(`Failed to upload chunk ${chunkNumber}`);
      }
    }

    console.log('File upload completed successfully');
    return {
      uploadId,
      status: 'completed',
      totalChunks,
      message: `Successfully uploaded ${totalChunks} chunks`
    };
  }

  /**
   * Upload a single chunk
   */
  async uploadChunk(chunk, chunkNumber, totalChunks, clientId, uploadId) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(
        `${this.baseUrl}/upload/${clientId}`,
        {
          method: 'POST',
          body: chunk,
          signal: controller.signal,
          headers: {
            'Accept': 'application/json',
            'X-Upload-Id': uploadId,
            'X-Chunk-Number': chunkNumber,
            'X-Total-Chunks': totalChunks,
            'X-Chunk-Size': chunk.size,
            'Content-Type': 'application/octet-stream'
          }
        }
      );

      clearTimeout(timeoutId);

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || `HTTP ${response.status}`);
      }

      return await response.json();

    } catch (error) {
      clearTimeout(timeoutId);
      throw error;
    }
  }

  /**
   * Get upload progress for resumable uploads
   */
  async getUploadProgress(uploadId) {
      const response = await fetch(`${this.baseUrl}/upload-progress/${uploadId}`);
    if (!response.ok) {
      throw new Error(`Failed to get upload progress: ${response.status}`);
    }
    return await response.json();
  }

  /**
   * Cancel an upload and clean up
   */
  async cancel(uploadId = this.uploadId) {
    try {
      const response = await fetch(
        `${this.baseUrl}/upload-abort/${uploadId}`,
        { method: 'POST' }
      );
      return await response.json();
    } catch (error) {
      console.error('Failed to cancel upload:', error);
      throw error;
    }
  }

  /**
   * Format bytes to human-readable format
   */
  formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  }

  /**
   * Sleep helper for delays
   */
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ChunkedFileUploader;
}
