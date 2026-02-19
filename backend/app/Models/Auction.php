<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'gpb_started_at' => 'datetime',
        'min_step' => 'decimal:2',
        'min_price' => 'decimal:2',
        'step_time' => 'integer',
        'gpb_minutes' => 'integer',
        'bar_weight' => 'decimal:3',
        'invite_all' => 'boolean',
    ];

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'auction_participants')
            ->withPivot('status', 'invited_at')
            ->withTimestamps();
    }

    public function auctionParticipants()
    {
        return $this->hasMany(AuctionParticipant::class);
    }

    public function initialOffers()
    {
        return $this->hasMany(InitialOffer::class);
    }
}
