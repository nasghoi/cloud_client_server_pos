<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cloud Server — Control Panel</title>
    <meta name="description" content="Manage restaurant client connections and trigger file downloads from the cloud.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --black: #000000;
            --white: #ffffff;
            --gray-50: #f9f9f9;
            --gray-100: #f0f0f0;
            --gray-200: #e0e0e0;
            --gray-400: #9e9e9e;
            --gray-600: #5a5a5a;
            --gray-800: #1a1a1a;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--white);
            color: var(--black);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── HEADER ── */
        header {
            border-bottom: 1px solid var(--black);
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-brand {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .header-nav {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .header-nav a {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.15s;
        }

        .header-nav a:hover,
        .header-nav a.active {
            color: var(--black);
        }

        /* ── MAIN LAYOUT ── */
        main {
            flex: 1;
            display: grid;
            grid-template-columns: 320px 1fr;
            min-height: calc(100vh - 57px);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            border-right: 1px solid var(--black);
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .sidebar-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-bottom: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.04em;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            border: 1px solid var(--black);
            border-radius: 0;
            background: var(--white);
            color: var(--black);
            outline: none;
            transition: background 0.15s;
        }

        input[type="text"]:focus {
            background: var(--gray-50);
        }

        input[type="text"]::placeholder {
            color: var(--gray-400);
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border: 1px solid var(--black);
            border-radius: 0;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--black);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--gray-800);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--black);
        }

        .btn-secondary:hover {
            background: var(--gray-100);
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .btn .icon {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        /* ── CONTENT AREA ── */
        .content {
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .content-header {
            padding-bottom: 24px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 32px;
        }

        .content-header h1 {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .content-header p {
            font-size: 13px;
            color: var(--gray-600);
        }

        /* ── RESPONSE BOX ── */
        .response-area {
            flex: 1;
        }

        .response-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-bottom: 12px;
        }

        .response-box {
            border: 1px solid var(--gray-200);
            min-height: 200px;
            padding: 20px;
            font-family: 'Poppins', monospace;
            font-size: 12px;
            line-height: 1.7;
            position: relative;
            background: var(--gray-50);
        }

        .response-box.empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
        }

        .response-placeholder {
            text-align: center;
        }

        .response-placeholder svg {
            margin: 0 auto 12px;
            display: block;
            opacity: 0.3;
        }

        .response-placeholder p {
            font-size: 12px;
        }

        .status-tag {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .status-tag.online {
            background: var(--black);
            color: var(--white);
        }

        .status-tag.offline {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .status-tag.success {
            background: var(--black);
            color: var(--white);
        }

        .status-tag.error {
            background: var(--black);
            color: var(--white);
            border: 1px solid var(--black);
        }

        .response-message {
            font-size: 13px;
            font-weight: 400;
            color: var(--gray-800);
            margin-bottom: 8px;
        }

        .response-meta {
            font-size: 11px;
            color: var(--gray-400);
            margin-top: 12px;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── DIVIDER ── */
        .sidebar-divider {
            height: 1px;
            background: var(--gray-200);
        }

        /* ── API REFERENCE ── */
        .api-ref {
            margin-top: auto;
        }

        .api-ref-item {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 8px 0;
            border-top: 1px solid var(--gray-100);
        }

        .api-ref-item:last-child {
            border-bottom: 1px solid var(--gray-100);
        }

        .method-tag {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 2px 5px;
            background: var(--gray-100);
            color: var(--gray-600);
            flex-shrink: 0;
        }

        .api-path {
            font-size: 11px;
            color: var(--gray-600);
            font-family: monospace;
        }

        /* ── LOADER OVERLAY ── */
        .loading .btn-text {
            display: none;
        }

        .loading .spinner {
            display: block;
        }

        @media (max-width: 768px) {
            main {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                border-bottom: 1px solid var(--black);
            }

            header {
                padding: 16px 20px;
            }

            .content {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body>

    <header>
        <span class="header-brand">Cloud Server</span>
        <nav class="header-nav">
            <a href="{{ url('/') }}" class="active">Control Panel</a>
            <a href="{{ url('/downloads') }}">Downloads</a>
        </nav>
    </header>

    <main>
        <!-- SIDEBAR: Controls -->
        <aside class="sidebar">
            <div>
                <p class="sidebar-section-label">Target Client</p>
                <div class="form-group">
                    <label for="restaurant-id">Restaurant ID</label>
                    <input type="text" id="restaurant-id" placeholder="e.g. restaurant02" autocomplete="off">
                </div>
            </div>

            <div class="sidebar-divider"></div>

            <div>
                <p class="sidebar-section-label">Actions</p>
                <div class="btn-group">
                    <button class="btn btn-secondary" id="btn-check" onclick="handleAction('check')">
                        <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="8" cy="8" r="6.5" />
                            <path d="M5.5 8l1.5 1.5L10.5 6" />
                        </svg>
                        <span class="btn-text">Check Connection</span>
                        <span class="spinner"></span>
                    </button>
                    <button class="btn btn-primary" id="btn-trigger" onclick="handleAction('trigger')">
                        <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                        <span class="btn-text">Trigger Download</span>
                        <span class="spinner"></span>
                    </button>
                </div>
            </div>

            <div class="sidebar-divider"></div>

            <div>
                <p class="sidebar-section-label">Upload File</p>
                <div class="form-group">
                    <label for="file-input">Select File</label>
                    <input type="file" id="file-input" accept="*">
                </div>
                <div id="upload-progress-container" style="display: none; gap: 12px; flex-direction: column;">
                    <div style="background: var(--gray-200); height: 20px; border-radius: 0; overflow: hidden;">
                        <div id="upload-progress-bar" 
                             style="background: var(--black); height: 100%; width: 0%; transition: width 0.3s;">
                        </div>
                    </div>
                    <div style="font-size: 11px; color: var(--gray-600);">
                        <p id="upload-progress-text" style="margin-bottom: 4px;">0%</p>
                        <p id="upload-status-message" style="margin: 0; line-height: 1.4;">Ready...</p>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-secondary" id="upload-cancel-btn" onclick="cancelUpload()">
                            <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                            <span class="btn-text">Cancel Upload</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sidebar-divider"></div>

            <!-- API Reference -->
            <div class="api-ref">
                <p class="sidebar-section-label">API Reference</p>
                <div class="api-ref-item">
                    <span class="method-tag">GET</span>
                    <span class="api-path">/api/check/{clientId}</span>
                </div>
                <div class="api-ref-item">
                    <span class="method-tag">GET</span>
                    <span class="api-path">/api/trigger/{clientId}</span>
                </div>
                <div class="api-ref-item">
                    <span class="method-tag">POST</span>
                    <span class="api-path">/api/ack/{clientId}</span>
                </div>
                <div class="api-ref-item">
                    <span class="method-tag">POST</span>
                    <span class="api-path">/api/upload/{clientId}</span>
                </div>
                <div class="api-ref-item">
                    <span class="method-tag">GET</span>
                    <span class="api-path">/api/upload-progress/{uploadId}</span>
                </div>
                <div class="api-ref-item">
                    <span class="method-tag">POST</span>
                    <span class="api-path">/api/upload-abort/{uploadId}</span>
                </div>
            </div>
        </aside>

        <!-- CONTENT: Response -->
        <section class="content">
            <div class="content-header">
                <h1>Control Panel</h1>
                <p>Enter a Restaurant ID and use the actions to check connection status or trigger a file download.</p>
            </div>

            <div class="response-area">
                <p class="response-label">Response</p>
                <div class="response-box empty" id="response-box">
                    <div class="response-placeholder">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="0" />
                            <path d="M3 9h18M9 21V9" />
                        </svg>
                        <p>No response yet.<br>Enter an ID and run an action.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="{{ asset('js/chunk-uploader.js') }}"></script>
    <script>
        let currentUploader = null;

        // File upload handler
        document.getElementById('file-input').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const clientId = document.getElementById('restaurant-id').value.trim();
            if (!clientId) {
                showError('Please enter a Restaurant ID before uploading.');
                return;
            }

            // Create uploader instance
            currentUploader = new ChunkedFileUploader({
                chunkSize: 5 * 1024 * 1024,
                maxRetries: 3,
                timeout: 30000,
                baseUrl: '/api'
            });

            const progressContainer = document.getElementById('upload-progress-container');
            progressContainer.style.display = 'flex';

            try {
                const result = await currentUploader.upload(file, clientId, (progress) => {
                    document.getElementById('upload-progress-bar').style.width = progress.progress + '%';
                    document.getElementById('upload-progress-text').textContent = `${progress.progress}%`;
                    document.getElementById('upload-status-message').textContent = 
                        `Chunk ${progress.chunkNumber + 1}/${progress.totalChunks} - ${progress.message}`;
                });

                document.getElementById('upload-status-message').textContent = '✅ Upload completed successfully!';
                document.getElementById('file-input').value = '';
                
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                }, 2000);

            } catch (error) {
                document.getElementById('upload-status-message').textContent = `❌ Upload failed: ${error.message}`;
                console.error('Upload failed:', error);
            }
        });

        function cancelUpload() {
            if (currentUploader) {
                currentUploader.cancel().then(() => {
                    document.getElementById('upload-progress-container').style.display = 'none';
                    document.getElementById('file-input').value = '';
                    console.log('Upload cancelled');
                }).catch(err => {
                    console.error('Error cancelling upload:', err);
                });
            }
        }

        async function handleAction(type) {
            const clientId = document.getElementById('restaurant-id').value.trim();

            if (!clientId) {
                showError('Please enter a Restaurant ID.');
                return;
            }

            const btn = document.getElementById('btn-' + type);
            const other = document.getElementById(type === 'check' ? 'btn-trigger' : 'btn-check');
            const box = document.getElementById('response-box');

            btn.classList.add('loading');
            btn.disabled = true;
            other.disabled = true;
            box.className = 'response-box empty';
            box.innerHTML = '<div class="response-placeholder"><p>Requesting...</p></div>';

            try {
                const res = await fetch(`/api/${type}/${encodeURIComponent(clientId)}`);
                const data = await res.json();
                renderResponse(data, res.status, type, clientId);
            } catch (err) {
                showError('Network error: ' + err.message);
            } finally {
                btn.classList.remove('loading');
                btn.disabled = false;
                other.disabled = false;
            }
        }

        function renderResponse(data, httpStatus, type, clientId) {
            const box = document.getElementById('response-box');
            box.className = 'response-box';

            const status = data.status || 'unknown';
            const tagMap = { online: 'online', offline: 'offline', success: 'success', error: 'error' };
            const tagClass = tagMap[status] || 'offline';

            const time = new Date().toLocaleTimeString('en-US', { hour12: false });

            box.innerHTML = `
                <span class="status-tag ${tagClass}">${status}</span>
                <p class="response-message">${escapeHtml(data.message || '')}</p>
                ${data.path ? `<p style="font-size:12px;color:var(--gray-600);margin-top:4px;">Saved to: <code>${escapeHtml(data.path)}</code></p>` : ''}
                <p class="response-meta">
                    HTTP ${httpStatus} &nbsp;·&nbsp; ${type === 'check' ? 'Connection Check' : 'Download Trigger'} &nbsp;·&nbsp; Client: <strong>${escapeHtml(clientId)}</strong> &nbsp;·&nbsp; ${time}
                </p>
            `;
        }

        function showError(msg) {
            const box = document.getElementById('response-box');
            box.className = 'response-box';
            box.innerHTML = `
                <span class="status-tag error">Error</span>
                <p class="response-message">${escapeHtml(msg)}</p>
            `;
        }

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        // Allow Enter key on the input to trigger check
        document.getElementById('restaurant-id').addEventListener('keydown', e => {
            if (e.key === 'Enter') handleAction('check');
        });
    </script>

</body>

</html>