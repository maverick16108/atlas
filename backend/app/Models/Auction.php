<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function lots()
    {
        return $this->hasMany(Lot::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}
