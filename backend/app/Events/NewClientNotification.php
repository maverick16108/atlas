<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NewClientNotification implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $userId;
    public array $notification;

    public function __construct(int $userId, array $notification)
    {
        $this->userId = $userId;
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('notifications.user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new.notification';
    }

    public function broadcastWith(): array
    {
        return $this->notification;
    }
}
