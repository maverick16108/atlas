<?php

namespace Database\Seeders;

use App\Models\ClientNotification;
use App\Models\User;
use App\Models\Auction;
use Illuminate\Database\Seeder;

class TestNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();
        if (!$admin) {
            $this->command->error('Super admin not found');
            return;
        }

        $auction = Auction::latest()->first();

        $notifications = [
            [
                'type' => 'auction_reminder',
                'title' => 'Аукцион начнётся через 15 минут',
                'message' => 'Аукцион "' . ($auction?->title ?: 'Золото 999') . '" начнётся через 15 минут.',
                'auction_id' => $auction?->id,
            ],
            [
                'type' => 'auction_completed',
                'title' => 'Аукцион завершён',
                'message' => 'Аукцион "' . ($auction?->title ?: 'Золото 999') . '" завершён. Ознакомьтесь с итогами.',
                'auction_id' => $auction?->id,
            ],
            [
                'type' => 'status_change',
                'title' => 'Статус аукциона изменён',
                'message' => 'Аукцион "' . ($auction?->title ?: 'Золото 999') . '" перешёл в статус "Активный".',
                'auction_id' => $auction?->id,
            ],
        ];

        foreach ($notifications as $n) {
            ClientNotification::create(array_merge($n, [
                'user_id' => $admin->id,
            ]));
        }

        $this->command->info("Created " . count($notifications) . " test notifications for {$admin->name} (ID: {$admin->id})");
    }
}
