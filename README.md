# Cloud File Download — On-Premise Client → Cloud Server

A solution for downloading files from restaurant clients (behind NAT/firewall) to a cloud server, using **Laravel Reverb** (WebSocket) as the signal channel and **HTTP POST** for the actual file stream.

> **Live demo:** https://cloud.nasrm.com — the cloud server is already running. You only need to run the client side yourself.

---

## How it works

```
Client (restaurant)                    Cloud Server
     │                                      │
     │── persistent WebSocket (outbound) ──►│
     │                                      │
     │        Admin triggers /api/trigger   │
     │◄─────── "request.file" event ────────│
     │                                      │
     │─── POST /api/ack/{id}  ────────────► │  (acknowledgement)
     │─── POST /api/upload/{id} ──────────► │  (streams the file)
     │                                      │
```

### Why we use each API

- **`GET /api/check/{id}`** — Checks if the client is currently online.
- **`GET /api/trigger/{id}`** — Sends a WebSocket signal to the client to start uploading.
- **`POST /api/ack/{id}`** — Client confirms it received the signal and is ready.
- **`POST /api/upload/{id}`** — Client uploads the file in multiple chunks to avoid timeouts and support large streams.

## Chunked streaming

The client does not send the entire file in one request. Instead, it streams the file in smaller chunks to `/api/upload/{id}`, with each chunk carrying metadata such as:

- `X-Upload-Id`
- `X-Chunk-Number`
- `X-Total-Chunks`
- `X-Chunk-Size`

This allows the server to receive chunks one by one, store them temporarily, and merge them into the final file once all chunks arrive.

---

## Client Setup (restaurant-pos)

> The cloud server is already live at `cloud.nasrm.com`. Just run the client.

**1. Install dependencies**

```bash
npm install pusher-js axios dotenv
```

- In `package.json`, add:  

```json
"type": "module"
```

**2. Configure `.env`**

```env
# change app key, host and api url if you use your own server
REVERB_APP_KEY=i0jxwytt7ajazg1zy8yw
REVERB_HOST="ws.nasrm.com"
CLOUD_API_URL="https://cloud.nasrm.com/api"

# based on the restaurant id
RESTAURANT_ID=restaurant02
```

**3. Place the file to be downloaded**

Make sure this file exists on the client machine:

```
$HOME/file_to_download.txt
```

**4. Start the daemon**

```bash
node client.js
```

Expected output:

```
Starting client daemon...
Listening on channel: restaurant.restaurant02
WebSocket connection established.
```

The client is now online and waiting for a signal from the cloud.

---

## Client Code (`client.js`)

```js
import { Pusher } from "pusher-js";
import axios from "axios";
import fs from "fs";
import path from "path";
import os from "os";
import dotenv from "dotenv";

dotenv.config();

console.log("Starting client daemon...");

const pusher = new Pusher(process.env.REVERB_APP_KEY, {
    wsHost: process.env.REVERB_HOST,
    // if you use your own server, change the port and wssPort and forceTLS
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
    enabledTransports: ["ws", "wss"],
    cluster: "mt1",
});
pusher.connection.bind("connected", () => {
    console.log("WebSocket connection established.");
});

const channel = pusher.subscribe(`restaurant.${process.env.RESTAURANT_ID}`);
console.log("Listening on channel: restaurant." + process.env.RESTAURANT_ID);

channel.bind("request.file", async (data) => {
    console.log("Signal received via Pusher. Initializing data stream...");

    try {
        await axios.post(
            `${process.env.CLOUD_API_URL}/ack/${process.env.RESTAURANT_ID}`,
            {
                event: "request.file",
                receivedAt: new Date().toISOString(),
            },
        );
        console.log("Acknowledgement sent to cloud server.");
    } catch (ackError) {
        console.warn(
            "Ack failed:",
            ackError.response?.data || ackError.message,
        );
    }


    await executeOutboundUpload();
});

const CHUNK_SIZE = 512 * 1024;
const MAX_RETRIES = 3;

async function executeOutboundUpload() {
    const targetFilePath = path.join(os.homedir(), "file_to_download.txt");

    if (!fs.existsSync(targetFilePath)) {
        console.log("Error: Target file missing at " + targetFilePath);
        return;
    }

    const fileStats = fs.statSync(targetFilePath);
    const uploadId = createUploadId();
    const totalChunks = Math.ceil(fileStats.size / CHUNK_SIZE);
    const uploadUrl = `${process.env.CLOUD_API_URL}/upload/${process.env.RESTAURANT_ID}`;

    console.log(
        `Starting chunked upload for file: ${targetFilePath} (${fileStats.size} bytes)`,
    );
    console.log(`Using chunk size ${CHUNK_SIZE} bytes; total chunks: ${totalChunks}`);

    for (let chunkNumber = 0; chunkNumber < totalChunks; chunkNumber++) {
        const start = chunkNumber * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, fileStats.size);
        const chunkSize = end - start;
        const headers = {
            "Content-Type": "application/octet-stream",
            "Content-Length": chunkSize,
            "X-Upload-Id": uploadId,
            "X-Chunk-Number": chunkNumber,
            "X-Total-Chunks": totalChunks,
            "X-Chunk-Size": chunkSize,
        };

        let attempt = 0;

        while (attempt < MAX_RETRIES) {
            let chunkStream = fs.createReadStream(targetFilePath, { start, end: end - 1 });

            try {
                console.log(`Uploading chunk ${chunkNumber + 1}/${totalChunks} (${chunkSize} bytes)`);

                const response = await axios({
                    method: "post",
                    url: uploadUrl,
                    data: chunkStream,
                    headers,
                    maxContentLength: Infinity,
                    maxBodyLength: Infinity,
                    timeout: 0,
                });

                console.log(
                    `Chunk ${chunkNumber + 1}/${totalChunks} uploaded:`,
                    response.status,
                    response.data,
                );
                break;
            } catch (error) {
                attempt += 1;
                const message = error.response?.data || error.message;
                console.warn(`Chunk ${chunkNumber + 1} failed (attempt ${attempt}/${MAX_RETRIES}):`, message);

                chunkStream.destroy();

                if (attempt >= MAX_RETRIES) {
                    console.error("Upload aborted after repeated failures.");
                    return;
                }

                await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
            }
        }
    }

    console.log("File upload finished successfully.");
}

function createUploadId() {
    return `upload_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}
```

---

## Triggering a Download

Once the client is online, trigger from the cloud:

**Web UI:** https://cloud.nasrm.com

- Enter the Restaurant ID → Click **Check Connection** → Click **Trigger Download**

**or via curl:**

```bash
# Check if client is online
curl https://cloud.nasrm.com/api/check/restaurant02

# Trigger the file download
curl https://cloud.nasrm.com/api/trigger/restaurant02
```

Downloaded files are listed at: https://cloud.nasrm.com/downloads

---

## Cloud Server Setup (self-hosting)

> Skip this if you're using the live server at `cloud.nasrm.com`.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set in `.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your_id
REVERB_APP_KEY=your_key
REVERB_APP_SECRET=your_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

Run:

```bash
php artisan serve            # HTTP server  → localhost:8000
php artisan reverb:start     # WebSocket    → localhost:8080
```

---

> Hi, I am Nasrul. I am still exploring WebSockets, as I have primarily worked with RESTful APIs most of the time. I am eager to learn, grow alongside experts like you all, and hope to have the opportunity to join your team! :)
