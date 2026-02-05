<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $guarded = [];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}
