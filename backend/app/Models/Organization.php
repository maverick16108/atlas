<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $guarded = [];

    protected $casts = [
        'logistics_settings' => 'array',
        'delivery_basis' => 'decimal:4',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function auctions()
    {
        return $this->belongsToMany(Auction::class, 'auction_participants')
            ->withPivot('status', 'invited_at')
            ->withTimestamps();
    }
}
