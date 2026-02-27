<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AdminStatsUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public string $reason;

    public function __construct(string $reason = 'general')
    {
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stats.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
