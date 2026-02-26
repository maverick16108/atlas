<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Models\AuctionParticipant;
use Carbon\Carbon;

class AuctionSeeder extends Seeder
{
    public function run()
    {
        $users = User::where('role', 'client')->get();
        if ($users->isEmpty()) {
            echo "No clients found.\n";
            return;
        }

        $titles = [
            'Партия мерных золотых слитков (999.9)',
            'Стандартные золотые слитки (КрАЗ)',
            'Лот золотых слитков 10-50г',
            'Золотые слитки (аффинажный завод)',
            'Крупная партия аффинированного золота'
        ];
        $descriptions = [
            'Предлагается к реализации партия высококачественных мерных золотых слитков пробы 999.9. Все слитки имеют соответствующие сертификаты и клейма аффинажного завода.',
            'Продажа стандартных золотых слитков, произведенных на аккредитованных аффинажных предприятиях. Отличное предложение для банков и институциональных инвесторов.',
            'Сборный лот небольших мерных золотых слитков массой от 10 до 50 граммов. Идеально для формирования розничного ассортимента.',
            'Оригинальные слитки с клеймом ведущего российского аффинажного завода. Безупречная история происхождения.',
            'Реализация крупного объема золота для удовлетворения промышленных и ювелирных потребноств крупных предприятий.'
        ];

        // 30 completed, 20 others
        $otherStatuses = ['draft', 'scheduled', 'active', 'collecting_offers', 'paused', 'commission', 'gpb_right', 'cancelled'];

        for ($i = 0; $i < 50; $i++) {
            $daysAgo = rand(0, 120);
            $startDate = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 5));
            $endDate = (clone $startDate)->addHours(rand(1, 4));

            if ($i < 30) {
                // Completed
                $daysAgo = 120 - ($i * 4) + rand(-2, 2);
                if ($daysAgo < 1) $daysAgo = 1;
                $startDate = Carbon::now()->subDays($daysAgo)->subHours(rand(1, 5));
                $endDate = (clone $startDate)->addHours(rand(1, 4));
                $status = 'completed';
            } else {
                $status = $otherStatuses[array_rand($otherStatuses)];
                
                if ($status == 'active' || $status == 'collecting_offers' || $status == 'commission' || $status == 'gpb_right') {
                    $startDate = Carbon::now()->subHours(rand(1, 24));
                    $endDate = Carbon::now()->addHours(rand(1, 48));
                } elseif ($status == 'scheduled') {
                    $startDate = Carbon::now()->addDays(rand(1, 5));
                    $endDate = (clone $startDate)->addHours(2);
                } elseif ($status == 'draft') {
                    $startDate = Carbon::now()->addDays(5);
                    $endDate = (clone $startDate)->addHours(2);
                }
            }

            $barCount = rand(5, 50);
            $barWeight = (rand(1, 100) / 100) + rand(1, 12);
            $basePrice = 8000 + (($i / 30) * 1500); 
            $minPrice = $basePrice + rand(-100, 100) + (rand(0, 99) / 100);

            $auction = Auction::create([
                'title' => $titles[array_rand($titles)] . ' (' . str_pad($i, 2, '0', STR_PAD_LEFT) . ')',
                'description' => $descriptions[array_rand($descriptions)],
                'status' => $status,
                'bar_count' => $barCount,
                'bar_weight' => $barWeight,
                'min_price' => $minPrice,
                'min_step' => 50,
                'step_time' => 5,
                'start_at' => $startDate,
                'end_at' => $endDate,
                'gpb_minutes' => 30,
            ]);

            // Add Participants to all except drafts
            if ($status != 'draft') {
                $numParticipants = rand(3, 10);
                $selectedUsers = $users->random($numParticipants);
                foreach ($selectedUsers as $u) {
                    AuctionParticipant::create([
                        'auction_id' => $auction->id,
                        'user_id' => $u->id,
                        'status' => 'approved',
                    ]);
                }
            }

            // Bids
            if (!in_array($status, ['draft', 'scheduled'])) {
                $bidCount = rand(1, 8);
                $currentPrice = $minPrice + rand(10, 50);
                $bidTime = clone $startDate;

                for ($b = 0; $b < $bidCount; $b++) {
                    $bidder = $users->random();
                    $bidTime->addMinutes(rand(5, 15));
                    
                    Bid::create([
                        'auction_id' => $auction->id,
                        'user_id' => $bidder->id,
                        'amount' => $currentPrice,
                        'bar_count' => rand(1, $barCount),
                        'created_at' => clone $bidTime,
                        'updated_at' => clone $bidTime,
                    ]);
                    
                    $currentPrice += (rand(2, 10) * 10);
                }
            }
        }
        echo "Seeded 50 varied auctions over the last 120 days.\n";
    }
}
