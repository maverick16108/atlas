<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Auction;
use Illuminate\Console\Command;

class TransitionExpiredAuctions extends Command
{
    protected $signature = 'auctions:transition-expired';
    protected $description = 'Transition expired active auctions to gpb_right status';

    public function handle()
    {
        $auctions = Auction::where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($auctions as $auction) {
            $auction->update([
                'status' => 'gpb_right',
                'gpb_started_at' => now(),
            ]);

            try {
                ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                    'status' => ['old' => 'active', 'new' => 'gpb_right'],
                    'gpb_started_at' => ['old' => null, 'new' => now()->toISOString()],
                    'auto' => true,
                ]);
            } catch (\Throwable $e) {}

            $count++;
        }

        $this->info("Transitioned {$count} auction(s) to gpb_right.");
        return 0;
    }
}
