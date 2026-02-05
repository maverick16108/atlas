<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $guarded = [];

    protected $casts = [
        'logistics_settings' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
