<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AuctionUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $auctionId;
    public array $auction;

    public function __construct(int $auctionId, array $auction)
    {
        $this->auctionId = $auctionId;
        $this->auction = $auction;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('auction.' . $this->auctionId),
            new Channel('auctions'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Send only minimal data to avoid Pusher/Reverb payload size limits.
        // Clients do a full fetchAuction() on receiving this event.
        return [
            'id' => $this->auctionId,
            'status' => $this->auction['status'] ?? null,
            'title' => $this->auction['title'] ?? null,
            'updated_at' => $this->auction['updated_at'] ?? null,
        ];
    }
}
