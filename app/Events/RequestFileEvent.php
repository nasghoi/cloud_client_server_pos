<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequestFileEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $clientId;

    public function __construct(string $clientId)
    {
        $this->clientId = $clientId;
    }

    public function broadcastWith(): array
    {
        return ['action' => 'PUSH_FILE'];
    }

    public function broadcastOn(): array
    {
        return [new Channel('restaurant.' . $this->clientId)];
    }

    public function broadcastAs(): string
    {
        return 'TriggerUploadEvent';
    }
}
