<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_accredited',
        'is_gpb',
        'auth_phone',
        'last_login_at',
        'inn',
        'kpp',
        'address',
        'logistics_settings',
        'delivery_basis',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_accredited' => 'boolean',
            'is_gpb' => 'boolean',
            'last_login_at' => 'datetime',
            'logistics_settings' => 'array',
            'delivery_basis' => 'decimal:4',
        ];
    }

    public function auctionParticipants()
    {
        return $this->hasMany(AuctionParticipant::class);
    }
}
