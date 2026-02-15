<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin
        User::firstOrCreate(
            ['email' => 'admin@atlas.ru'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => 'admin_'.uniqid(),
                'is_accredited' => true,
            ]
        );

        // Super Admin
        User::firstOrCreate(
            ['email' => 'super@atlas.ru'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'phone' => 'super_'.uniqid(),
                'is_accredited' => true,
            ]
        );
    }
}
