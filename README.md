# Cloud Server — On-Demand File Download from On-Premise Clients

## Overview

This solution enables a **cloud-hosted Laravel server** to download a large file (≈100 MB) from any **on-premise restaurant client** on demand, even when those clients sit behind a private NAT/firewall with no inbound ports open.

The core design challenge is that the cloud server cannot reach the client directly — but the client *can* reach the cloud server. This solution exploits that asymmetry:

1. The client keeps a **persistent outbound WebSocket connection** (via Laravel Reverb) open at all times.
2. The cloud server uses that pipe to **signal** the client when it wants the file.
3. The client reacts by **streaming the file back** over a regular outbound HTTP POST.

---

## Architecture

```
┌──────────────────────────────────────────────────────┐
│  ON-PREMISE CLIENT  (Private Network / Behind NAT)   │
│                                                      │
│  node client.js                                      │
│    ├── Pusher-JS  ──── persistent WSS ──────────────►│
│    └── Axios      ──── outbound HTTP POST ──────────►│
└──────────────────────────────────────────────────────┘
                                  │  (all traffic is OUTBOUND)
                                  ▼
┌──────────────────────────────────────────────────────┐
│  CLOUD SERVER  (Publicly Accessible)                 │
│                                                      │
│  Laravel 13 + Laravel Reverb (WebSocket server)      │
│                                                      │
│  API Endpoints:                                      │
│    GET  /api/check/{clientId}   ← connection status  │
│    GET  /api/trigger/{clientId} ← broadcast signal   │
│    POST /api/ack/{clientId}     ← client ack         │
│    POST /api/upload/{clientId}  ← file stream        │
│                                                      │
│  Web UI:                                             │
│    GET  /            ← Control Panel                 │
│    GET  /downloads   ← Downloaded files list         │
└──────────────────────────────────────────────────────┘
```

### Step-by-step flow

| Step | Actor | Action |
|------|-------|--------|
| 0 | Client | Starts `node client.js`. Establishes persistent WSS connection to Reverb and subscribes to `restaurant.{RESTAURANT_ID}` channel. |
| 1 | Admin | Calls `GET /api/trigger/{clientId}` (via UI or curl). |
| 2 | Cloud Server | Checks if channel is occupied (client online). If offline → returns `404`. If online → broadcasts `request.file` event. |
| 3 | Client | Receives the WebSocket event. POSTs an acknowledgement to `POST /api/ack/{clientId}`. |
| 4 | Client | Opens a read stream of `$HOME/file_to_download.txt` and POSTs the binary stream to `POST /api/upload/{clientId}`. |
| 5 | Cloud Server | Receives the stream, writes it chunk-by-chunk to `storage/app/private/downloads/{clientId}_100mbfile.txt`. |

---

## Repository Structure

```
cloud_server_nasr/          ← Laravel Cloud Server
├── app/
│   ├── Events/
│   │   └── RequestFileEvent.php     ← WebSocket broadcast event
│   └── Http/Controllers/
│       └── WebSocketController.php  ← All API logic
├── routes/
│   ├── api.php                      ← API routes
│   └── web.php                      ← Web UI routes
└── resources/views/
    ├── welcome.blade.php            ← Control panel UI
    └── downloads.blade.php          ← Downloads list UI

restaurant-pos/             ← On-Premise Client (Node.js)
├── client.js               ← Daemon: WebSocket listener + file streamer
├── .env                    ← Client configuration
└── package.json
```

---

## Prerequisites

### Cloud Server
- PHP 8.3+
- Composer
- Laravel 13
- Laravel Reverb (`laravel/reverb`)

### Client (On-Premise)
- Node.js 18+
- npm
- The file to download at: `$HOME/file_to_download.txt`

---

## Setup

### 1. Cloud Server

**Clone and install:**
```bash
git clone <repository-url> cloud_server_nasr
cd cloud_server_nasr
composer install
cp .env.example .env
php artisan key:generate
```

**Configure `.env`:**
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Start the servers** (3 separate terminals):

```bash
# Terminal 1 — Laravel HTTP server
php artisan serve --port=8000

# Terminal 2 — Laravel Reverb WebSocket server
php artisan reverb:start --host=127.0.0.1 --port=8080

# (Optional) Terminal 3 — Queue worker (not required; ShouldBroadcastNow is used)
# php artisan queue:listen
```

---

### 2. On-Premise Client

**Install dependencies:**
```bash
cd restaurant-pos
npm install
```

**Configure `.env`:**
```env
REVERB_APP_KEY=your_app_key      # Must match the cloud server
REVERB_HOST=your.cloud.domain    # WebSocket server hostname
REVERB_PORT=443                  # 443 for WSS (production), 8080 for local
REVERB_SCHEME=https              # https for production, http for local

RESTAURANT_ID=rest02             # Unique ID for this client
CLOUD_API_URL=https://your.cloud.domain/api
```

**Start the daemon:**
```bash
node client.js
```

You should see:
```
Starting client daemon...
Listening on channel: restaurant.rest02
WebSocket connection established.
```

The client is now online and waiting for signals.

---

## Triggering a Download

### Option A — Web UI (Recommended)

Open `http://your-server/` in a browser.

1. Enter the **Restaurant ID** (e.g. `rest02`).
2. Click **Check Connection** to verify the client is online.
3. Click **Trigger Download** to initiate the file stream.

View downloaded files at `http://your-server/downloads`.

### Option B — API (curl)

**Check connection status:**
```bash
curl http://your-server/api/check/rest02
```
```json
{ "status": "online", "message": "Client rest02 is online" }
```

**Trigger the download:**
```bash
curl http://your-server/api/trigger/rest02
```
```json
{ "status": "success", "message": "Upload signal broadcasted to client: rest02" }
```

### Option C — Artisan CLI

```bash
php artisan tinker
>>> broadcast(new \App\Events\RequestFileEvent('rest02'));
```

---

## API Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/check/{clientId}` | Check if the client is online (WebSocket channel occupied) |
| `GET` | `/api/trigger/{clientId}` | Check online status then broadcast `request.file` event |
| `POST` | `/api/ack/{clientId}` | *(Called by client)* Acknowledge receipt of the signal |
| `POST` | `/api/upload/{clientId}` | *(Called by client)* Stream the binary file to the server |

### Response examples

**Client online:**
```json
{ "status": "online", "message": "Client rest02 is online" }
```

**Client offline:**
```json
{ "status": "offline", "message": "Client rest02 is offline/unreachable" }
```

**WebSocket server unreachable:**
```json
{ "status": "error", "message": "WebSocket server is unreachable: cURL error 7..." }
```

**Download triggered successfully:**
```json
{ "status": "success", "message": "Upload signal broadcasted to client: rest02" }
```

**File received and saved:**
```json
{
  "status": "success",
  "message": "File successfully received and saved",
  "path": "downloads/rest02_100mbfile.txt"
}
```

---

## Saved File Location

Downloaded files are saved to:
```
storage/app/private/downloads/{clientId}_100mbfile.txt
```

---

## Key Design Decisions

### Why WebSockets for signalling, not polling?
The cloud server cannot initiate an inbound TCP connection to a client behind NAT. A persistent **outbound** WebSocket from the client solves this without any firewall rule changes. The WebSocket is only used as a lightweight **signal channel** — the actual data travels over HTTP POST.

### Why HTTP POST for the file, not WebSocket frames?
Large binary transfers over WebSocket frames require careful chunking and buffering. HTTP POST with `Transfer-Encoding: chunked` is battle-tested for large payloads, plays well with reverse proxies (nginx/Caddy), and is simpler to implement memory-safely on both ends.

### Why `ShouldBroadcastNow`?
Using `ShouldBroadcastNow` instead of `ShouldBroadcast` means the event is dispatched synchronously — no queue worker is needed for the broadcast to fire. This simplifies the setup while keeping the `/api/trigger` response fast (it only broadcasts the signal, it does not wait for the upload).

### Memory-safe streaming
The server reads the upload with `php://input` and pipes it directly to disk via `Storage::writeStream()` — the file is never fully loaded into PHP memory, making it safe for files of any size.
