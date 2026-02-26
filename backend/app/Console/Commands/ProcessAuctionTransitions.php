<?php

namespace App\Console\Commands;

use App\Events\AuctionUpdated;
use App\Models\ActivityLog;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessAuctionTransitions extends Command
{
    protected $signature = 'auctions:process-transitions {--daemon : Run continuously, checking every second}';

    protected $description = 'Auto-transition auction statuses: scheduled→active (on start_at), active→gpb_right (on end_at), gpb_right→commission (on gpb timeout)';

    public function handle(): int
    {
        if ($this->option('daemon')) {
            $this->info('Running in daemon mode (checking every second)...');
            while (true) {
                $this->processTransitions();
                sleep(1);
            }
        }

        return $this->processTransitions();
    }

    private function processTransitions(): int
    {
        $now = Carbon::now();
        $transitioned = 0;

        // 0. Scheduled → Active (when start_at has passed)
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
            $this->info("Auction #{$auction->id} '{$auction->title}': scheduled → active");
            $transitioned++;
        }

        // 1. Active → Право ГПБ (when end_at has passed)
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
            $this->info("Auction #{$auction->id} '{$auction->title}': active → gpb_right");
            $transitioned++;
        }

        // 2. Право ГПБ → Работа комиссии (when gpb_minutes elapsed)
        $expiredGpb = Auction::where('status', 'gpb_right')
            ->whereNotNull('gpb_started_at')
            ->get();

        foreach ($expiredGpb as $auction) {
            $gpbMinutes = $auction->gpb_minutes ?? 30;
            $gpbEnd = Carbon::parse($auction->gpb_started_at)->addMinutes($gpbMinutes);

            if ($now->gte($gpbEnd)) {
                $auction->update(['status' => 'commission']);

                try {
                    ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                        'status' => ['old' => 'gpb_right', 'new' => 'commission'],
                    ], ['auto' => true, 'reason' => 'Время права ГПБ истекло']);
                } catch (\Throwable $e) {}

                $this->broadcastUpdate($auction);
                $this->info("Auction #{$auction->id} '{$auction->title}': gpb_right → commission");
                $transitioned++;
            }
        }

        if ($transitioned === 0) {
            $this->info('No transitions needed.');
        }

        return self::SUCCESS;
    }

    private function broadcastUpdate(Auction $auction): void
    {
        try {
            $freshAuction = $auction->fresh()?->loadCount('auctionParticipants');
            if ($freshAuction) {
                event(new AuctionUpdated($auction->id, $freshAuction->toArray()));
            }
        } catch (\Throwable $e) {
            $this->warn("Failed to broadcast update for auction #{$auction->id}: {$e->getMessage()}");
        }
    }
}
