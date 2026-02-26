<?php

namespace App\Console\Commands;

use App\Events\AuctionUpdated;
use App\Models\ActivityLog;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TransitionExpiredAuctions extends Command
{
    protected $signature = 'auctions:transition-expired';
    protected $description = 'Auto-transition: scheduled→active (on start_at), active→gpb_right (on end_at)';

    public function handle()
    {
        $now = Carbon::now();
        $count = 0;

        // Scheduled → Active (when start_at has passed)
        $readyToStart = Auction::where('status', 'scheduled')
            ->whereNotNull('start_at')
            ->where('start_at', '<=', $now)
            ->get();

        foreach ($readyToStart as $auction) {
            $auction->update(['status' => 'active']);

            try {
                ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                    'status' => ['old' => 'scheduled', 'new' => 'active'],
                ], ['auto' => true, 'reason' => 'Время начала торгов наступило']);
            } catch (\Throwable $e) {}

            $this->broadcastUpdate($auction);
            $this->info("Auction #{$auction->id}: scheduled → active");
            $count++;
        }

        // Active → GPB Right (when end_at has passed)
        $expiredActive = Auction::where('status', 'active')
            ->whereNotNull('end_at')
            ->where('end_at', '<=', $now)
            ->get();

        foreach ($expiredActive as $auction) {
            $auction->update([
                'status' => 'gpb_right',
                'gpb_started_at' => $now,
            ]);

            try {
                ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                    'status' => ['old' => 'active', 'new' => 'gpb_right'],
                ], ['auto' => true, 'reason' => 'Время торгов истекло']);
            } catch (\Throwable $e) {}

            $this->broadcastUpdate($auction);
            $this->info("Auction #{$auction->id}: active → gpb_right");
            $count++;
        }

        $this->info("Transitioned {$count} auction(s).");
        return 0;
    }

    private function broadcastUpdate(Auction $auction): void
    {
        try {
            $fresh = $auction->fresh();
            if ($fresh) {
                event(new AuctionUpdated($auction->id, $fresh->toArray()));
            }
        } catch (\Throwable $e) {}
    }
}
