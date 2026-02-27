<?php

namespace App\Models;

use App\Events\NewClientNotification;
use Illuminate\Database\Eloquent\Model;

class ClientNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (ClientNotification $notification) {
            try {
                event(new NewClientNotification($notification->user_id, [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'auction_id' => $notification->auction_id,
                    'read_at' => null,
                    'created_at' => $notification->created_at?->toISOString(),
                ]));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to broadcast notification: " . $e->getMessage());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }
}
