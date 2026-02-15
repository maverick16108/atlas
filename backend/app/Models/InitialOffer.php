<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InitialOffer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'volume' => 'decimal:4',
        'price' => 'decimal:2',
        'delivery_basis' => 'decimal:4',
    ];

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
