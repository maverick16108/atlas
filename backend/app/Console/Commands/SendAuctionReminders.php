<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\ClientNotification;
use App\Models\User;
use Illuminate\Console\Command;

class SendAuctionReminders extends Command
{
    protected $signature = 'auctions:send-reminders';
    protected $description = 'Send notifications 15 minutes before auction start and when auction is completed';

    public function handle()
    {
        $this->sendStartReminders();
        $this->sendCompletionNotifications();

        return 0;
    }

    /**
     * Send reminders 15 minutes before auction start.
     * Target window: auctions starting between 14 and 15 minutes from now.
     */
    private function sendStartReminders()
    {
        $from = now()->addMinutes(14);
        $to = now()->addMinutes(15);

        $auctions = Auction::whereIn('status', ['scheduled', 'active', 'collecting_offers'])
            ->whereNotNull('start_at')
            ->whereBetween('start_at', [$from, $to])
            ->get();

        $count = 0;
        foreach ($auctions as $auction) {
            // Check if reminders already sent for this auction
            $alreadySent = ClientNotification::where('auction_id', $auction->id)
                ->where('type', 'auction_reminder')
                ->exists();

            if ($alreadySent) continue;

            $recipients = $this->getAuctionRecipients($auction, includeStaff: true);

            foreach ($recipients as $userId) {
                ClientNotification::create([
                    'user_id' => $userId,
                    'auction_id' => $auction->id,
                    'type' => 'auction_reminder',
                    'title' => 'Аукцион начнётся через 15 минут',
                    'message' => "Аукцион \"{$auction->title}\" начнётся через 15 минут.",
                ]);
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("Sent {$count} start reminder(s).");
        }
    }

    /**
     * Send notifications when auction is completed.
     */
    private function sendCompletionNotifications()
    {
        $auctions = Auction::where('status', 'completed')
            ->whereNotNull('start_at')
            ->get();

        $count = 0;
        foreach ($auctions as $auction) {
            // Check if completion notifications already sent
            $alreadySent = ClientNotification::where('auction_id', $auction->id)
                ->where('type', 'auction_completed')
                ->exists();

            if ($alreadySent) continue;

            $recipients = $this->getAuctionRecipients($auction, includeStaff: false);

            foreach ($recipients as $userId) {
                ClientNotification::create([
                    'user_id' => $userId,
                    'auction_id' => $auction->id,
                    'type' => 'auction_completed',
                    'title' => 'Аукцион завершён',
                    'message' => "Аукцион \"{$auction->title}\" завершён. Ознакомьтесь с итогами.",
                ]);
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("Sent {$count} completion notification(s).");
        }
    }

    /**
     * Get recipient user IDs for an auction.
     */
    private function getAuctionRecipients(Auction $auction, bool $includeStaff): array
    {
        $userIds = [];

        // Auction participants
        if ($auction->invite_all) {
            $userIds = User::where('role', 'client')->pluck('id')->toArray();
        } else {
            $userIds = $auction->participants()->pluck('users.id')->toArray();
        }

        // Add admins and moderators
        if ($includeStaff) {
            $staffIds = User::whereIn('role', ['admin', 'moderator'])->pluck('id')->toArray();
            $userIds = array_unique(array_merge($userIds, $staffIds));
        }

        return $userIds;
    }
}
