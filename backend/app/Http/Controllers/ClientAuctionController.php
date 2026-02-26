<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use App\Models\InitialOffer;
use Illuminate\Http\Request;

class ClientAuctionController extends Controller
{
    /**
     * Get auctions where the current user is a participant.
     */
    public function myAuctions(Request $request)
    {
        $user = $request->user();

        // Show auctions where user is a participant OR auctions open to all (no specific participants, not draft)
        $query = Auction::where('status', '!=', 'draft')->where(function ($q) use ($user) {
            $q->whereHas('auctionParticipants', function ($sub) use ($user) {
                $sub->where('user_id', $user->id);
            })->orWhere(function ($sub) {
                $sub->whereDoesntHave('auctionParticipants');
            });
        });

        // Filter by status group
        if ($request->has('filter') && $request->input('filter')) {
            $filter = $request->input('filter');
            switch ($filter) {
                case 'active':
                    $query->whereIn('status', ['active', 'scheduled']);
                    break;
                case 'completed':
                    $query->whereIn('status', ['completed', 'commission']);
                    break;
                case 'collecting':
                    $query->where('status', 'collecting_offers');
                    break;
                case 'gpb':
                    $query->where('status', 'gpb_right');
                    break;
            }
        }

        $auctions = $query->orderByDesc('start_at')->get();

        // Enrich with user-specific data
        $result = $auctions->map(function ($auction) use ($user) {
            $myBidsCount = Bid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->count();

            $myOffersCount = InitialOffer::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->count();

            $myBestBid = Bid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->orderByDesc('amount')
                ->first();

            // Determine if user is currently winning
            $isWinning = false;
            if ($myBestBid && in_array($auction->status, ['active', 'gpb_right', 'commission', 'completed'])) {
                $higherBidsCount = Bid::where('auction_id', $auction->id)
                    ->where('amount', '>', $myBestBid->amount)
                    ->count();

                // Simple check: if total bars can cover bids above mine + mine, I'm winning
                $totalBars = $auction->bar_count ?? 0;
                $barsAbove = Bid::where('auction_id', $auction->id)
                    ->where('amount', '>', $myBestBid->amount)
                    ->sum('bar_count');
                $isWinning = ($barsAbove + ($myBestBid->bar_count ?? 0)) <= $totalBars;
            }

            return [
                'id' => $auction->id,
                'title' => $auction->title,
                'description' => $auction->description,
                'status' => $auction->status,
                'start_at' => $auction->start_at,
                'end_at' => $auction->end_at,
                'bar_count' => $auction->bar_count,
                'bar_weight' => $auction->bar_weight,
                'min_price' => $auction->min_price,
                'min_step' => $auction->min_step,
                'step_time' => $auction->step_time,
                'gpb_started_at' => $auction->gpb_started_at,
                'gpb_minutes' => $auction->gpb_minutes,
                'my_bids_count' => $myBidsCount,
                'my_offers_count' => $myOffersCount,
                'my_best_bid' => $myBestBid ? $myBestBid->amount : null,
                'is_winning' => $isWinning,
            ];
        });

        return response()->json($result);
    }

    /**
     * Show auction details for a participant.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $auction = Auction::findOrFail($id);

        // Verify user is a participant or auction is open to all
        $isParticipant = AuctionParticipant::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->exists();

        // Auction is open to all if it has no specific participants and is not a draft
        $isOpenToAll = !AuctionParticipant::where('auction_id', $auction->id)->exists()
            && $auction->status !== 'draft';

        if (!$isParticipant && !$isOpenToAll) {
            return response()->json(['message' => 'Вы не являетесь участником этого аукциона'], 403);
        }

        // My offers
        $myOffers = InitialOffer::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        // My bids
        $myBids = Bid::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($bid) => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'bar_count' => $bid->bar_count,
                'is_proxy' => $bid->is_proxy,
                'created_at' => $bid->created_at,
            ]);

        // All bids (anonymized for participants, sorted by price DESC)
        $allBids = [];
        $highestBid = null;
        if (in_array($auction->status, ['active', 'gpb_right', 'commission', 'completed'])) {
            $bidsRaw = Bid::where('auction_id', $auction->id)
                ->orderByDesc('amount')
                ->orderBy('created_at')
                ->get();

            // Build stable participant labels by user_id
            $userIds = $bidsRaw->pluck('user_id')->unique()->values();
            $userLabelMap = [];
            $labelCounter = 1;
            foreach ($userIds as $uid) {
                if ($uid === $user->id) {
                    $userLabelMap[$uid] = 'Вы';
                } else {
                    $userLabelMap[$uid] = 'Участник ' . $labelCounter;
                    $labelCounter++;
                }
            }

            // Allocate bids to lot (winning/losing)
            $totalBarsForAlloc = $auction->bar_count ?? 0;
            $remainingBars = $totalBarsForAlloc;
            $allocatedBids = [];
            foreach ($bidsRaw as $bid) {
                $fulfilled = 0;
                $bidStatus = 'losing';
                $partial = false;
                $isRemainder = false;

                if ($remainingBars > 0) {
                    $fulfilled = min($bid->bar_count, $remainingBars);
                    if ($fulfilled === $bid->bar_count) {
                        $bidStatus = 'winning';
                    } else {
                        $bidStatus = 'partial';
                        $partial = true;
                        $isRemainder = true;
                    }
                    $remainingBars -= $fulfilled;
                }

                $allocatedBids[] = [
                    'id' => $bid->id,
                    'is_mine' => $bid->user_id === $user->id,
                    'participant_label' => $userLabelMap[$bid->user_id] ?? 'Участник',
                    'amount' => $bid->amount,
                    'bar_count' => $bid->bar_count,
                    'fulfilled' => $fulfilled,
                    'status' => $bidStatus,
                    'partial' => $partial,
                    'is_remainder' => $isRemainder,
                    'created_at' => $bid->created_at,
                ];
            }

            $allBids = $allocatedBids;

            // Highest bid amount for min_step hint
            $highestBid = $bidsRaw->first()?->amount;
        }

        // Determine winning status
        $totalBars = $auction->bar_count ?? 0;
        $barWeight = $auction->bar_weight ?? 0;
        $myWinningBars = 0;
        $myStatus = 'none'; // none, winning, losing, partial

        if (count($allBids) > 0) {
            $remaining = $totalBars;
            foreach ($allBids as $bid) {
                if ($remaining <= 0) {
                    break;
                }
                $bars = min($bid['bar_count'], $remaining);
                if ($bid['is_mine']) {
                    $myWinningBars += $bars;
                }
                $remaining -= $bars;
            }

            $myTotalBidBars = Bid::where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->sum('bar_count');

            if ($myWinningBars > 0 && $myWinningBars >= $myTotalBidBars) {
                $myStatus = 'winning';
            } elseif ($myWinningBars > 0) {
                $myStatus = 'partial';
            } elseif ($myTotalBidBars > 0) {
                $myStatus = 'losing';
            }
        }

        return response()->json([
            'auction' => [
                'id' => $auction->id,
                'title' => $auction->title,
                'status' => $auction->status,
                'start_at' => $auction->start_at,
                'end_at' => $auction->end_at,
                'bar_count' => $auction->bar_count,
                'bar_weight' => $auction->bar_weight,
                'min_price' => $auction->min_price,
                'min_step' => $auction->min_step,
                'step_time' => $auction->step_time,
                'description' => $auction->description,
                'gpb_started_at' => $auction->gpb_started_at,
                'gpb_minutes' => $auction->gpb_minutes,
            ],
            'my_offers' => $myOffers,
            'my_bids' => $myBids,
            'all_bids' => $allBids,
            'my_status' => $myStatus,
            'my_winning_bars' => $myWinningBars,
            'highest_bid' => $highestBid,
            'is_gpb' => (bool) $user->is_gpb,
        ]);
    }

    /**
     * Submit an initial offer (collecting_offers status only).
     */
    public function submitOffer(Request $request, string $id)
    {
        $user = $request->user();
        $auction = Auction::findOrFail($id);

        // Verify participant or open auction
        $isParticipant = AuctionParticipant::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->exists();

        $isOpenToAll = !AuctionParticipant::where('auction_id', $auction->id)->exists()
            && $auction->status !== 'draft';

        if (!$isParticipant && !$isOpenToAll) {
            return response()->json(['message' => 'Вы не являетесь участником этого аукциона'], 403);
        }

        if ($auction->status !== 'collecting_offers') {
            return response()->json(['message' => 'Сбор предложений не активен'], 422);
        }

        $validated = $request->validate([
            'volume' => ['required', 'numeric', 'min:1'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $offer = InitialOffer::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'volume' => $validated['volume'],
            'price' => $validated['price'],
            'comment' => $validated['comment'] ?? null,
        ]);

        // Broadcast to admin via WebSocket
        try {
            event(new \App\Events\OfferPlaced($auction->id, [
                'id' => $offer->id,
                'user_id' => $offer->user_id,
                'volume' => $offer->volume,
                'price' => $offer->price,
                'created_at' => $offer->created_at->toISOString(),
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OfferPlaced broadcast failed: ' . $e->getMessage());
        }

        return response()->json($offer, 201);
    }

    /**
     * Place a bid (active status only).
     */
    public function placeBid(Request $request, string $id)
    {
        $user = $request->user();
        $auction = Auction::findOrFail($id);

        // Verify participant or open auction
        $isParticipant = AuctionParticipant::where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->exists();

        $isOpenToAll = !AuctionParticipant::where('auction_id', $auction->id)->exists()
            && $auction->status !== 'draft';

        if (!$isParticipant && !$isOpenToAll) {
            return response()->json(['message' => 'Вы не являетесь участником этого аукциона'], 403);
        }

        if ($auction->status === 'active') {
            // Обычные торги — GPB участники НЕ могут ставить
            if ($user->is_gpb) {
                return response()->json(['message' => 'ГПБ участник не может ставить в обычных торгах'], 422);
            }
            // Check if auction time has expired
            if ($auction->end_at && now()->gte($auction->end_at)) {
                return response()->json(['message' => 'Время торгов истекло'], 422);
            }
        } elseif ($auction->status === 'gpb_right') {
            // Фаза Право ГПБ — только GPB участники могут ставить
            if (!$user->is_gpb) {
                return response()->json(['message' => 'Торги не активны'], 422);
            }
            // Проверка: не истекло ли время ГПБ
            if ($auction->gpb_started_at) {
                $gpbEnd = \Carbon\Carbon::parse($auction->gpb_started_at)->addMinutes($auction->gpb_minutes ?? 30);
                if (now()->gte($gpbEnd)) {
                    return response()->json(['message' => 'Время права ГПБ истекло'], 422);
                }
            }
        } else {
            return response()->json(['message' => 'Торги не активны'], 422);
        }

        $validated = $request->validate([
            'bar_count' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        // Validate amount >= min_price per kg
        if ($auction->min_price) {
            $barCount = $auction->bar_count ?: 1;
            $barWeight = $auction->bar_weight ?: 1;
            $minPricePerKg = round((float)$auction->min_price / $barCount / $barWeight, 2);
            if ((float)$validated['amount'] < $minPricePerKg) {
                return response()->json([
                    'message' => 'Цена должна быть не менее ' . number_format($minPricePerKg, 2) . ' ₽/кг',
                    'errors' => ['amount' => ['Минимальная цена: ' . number_format($minPricePerKg, 2) . ' ₽/кг']]
                ], 422);
            }
        }

        // Note: min_step is used for display/auction rules but not enforced per-bid.
        // In multi-bar auctions, participants can bid at any price >= min_price.
        // The allocation algorithm sorts all bids by price DESC and fills bars.

        // Validate bar_count doesn't exceed auction's bar_count
        if ($auction->bar_count && $validated['bar_count'] > $auction->bar_count) {
            return response()->json([
                'message' => 'Количество слитков не может превышать ' . $auction->bar_count,
                'errors' => ['bar_count' => ['Максимум: ' . $auction->bar_count]]
            ], 422);
        }

        $bid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'bar_count' => $validated['bar_count'],
            'is_proxy' => false,
        ]);

        // Log bid in Activity Log
        ActivityLog::log('created', 'bid', $auction->id, $auction->title, null, [
            'bid_id' => $bid->id,
            'user_name' => $user->name,
            'amount' => $bid->amount,
            'bar_count' => $bid->bar_count,
        ]);

        // Broadcast to all listeners on this auction channel (non-critical)
        try {
            event(new \App\Events\BidPlaced($auction->id, [
                'id' => $bid->id,
                'user_id' => $bid->user_id,
                'amount' => $bid->amount,
                'bar_count' => $bid->bar_count,
                'created_at' => $bid->created_at->toISOString(),
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('BidPlaced broadcast failed: ' . $e->getMessage());
        }

        // If GPB bid during gpb_right → auto-transition to commission
        if ($auction->status === 'gpb_right' && $user->is_gpb) {
            $auction->update(['status' => 'commission']);
            ActivityLog::log('updated', 'auction', $auction->id, $auction->title, null, [
                'status' => ['old' => 'gpb_right', 'new' => 'commission'],
                'reason' => 'GPB exercised priority purchase right',
            ]);
        }

        return response()->json([
            'bid' => [
                'id' => $bid->id,
                'amount' => $bid->amount,
                'bar_count' => $bid->bar_count,
                'created_at' => $bid->created_at,
            ],
            'message' => $auction->status === 'commission' ? 'Слитки забраны! Аукцион передан комиссии.' : 'Ставка принята',
        ], 201);
    }

    /**
     * Dashboard stats for the current client.
     */
    public function myStats(Request $request)
    {
        $user = $request->user();

        // Base query for auctions available to the user (same as in myAuctions)
        $baseQuery = Auction::where('status', '!=', 'draft')->where(function ($q) use ($user) {
            $q->whereHas('auctionParticipants', function ($sub) use ($user) {
                $sub->where('user_id', $user->id);
            })->orWhere(function ($sub) {
                $sub->whereDoesntHave('auctionParticipants');
            });
        });

        $totalAuctions = (clone $baseQuery)->count();
        $activeAuctions = (clone $baseQuery)->whereIn('status', ['active', 'scheduled'])->count();
        $collectingAuctions = (clone $baseQuery)->where('status', 'collecting_offers')->count();

        $totalBids = Bid::where('user_id', $user->id)->count();
        $totalOffers = InitialOffer::where('user_id', $user->id)->count();

        // Won auctions: auctions where user has winning bids in completed status
        $completedAuctionIds = (clone $baseQuery)
            ->where('status', 'completed')
            ->pluck('id');

        $wonCount = 0;
        foreach ($completedAuctionIds as $auctionId) {
            $auction = Auction::find($auctionId);
            if (!$auction) continue;

            $totalBars = $auction->bar_count ?? 0;
            $bids = Bid::where('auction_id', $auctionId)
                ->orderByDesc('amount')
                ->orderBy('created_at')
                ->get();

            $remaining = $totalBars;
            foreach ($bids as $bid) {
                if ($remaining <= 0) break;
                $bars = min($bid->bar_count, $remaining);
                if ($bid->user_id === $user->id && $bars > 0) {
                    $wonCount++;
                    break; // Count auction once
                }
                $remaining -= $bars;
            }
        }

                // Calculate Price History
        $quarterAgo = now()->subMonths(3);
        $completedAuctions = \App\Models\Auction::whereIn('status', ['completed', 'commission'])
            ->where('end_at', '>=', $quarterAgo)
            ->with(['bids' => function($q) {
                $q->orderBy('amount', 'desc');
            }])
            ->orderBy('end_at', 'asc')
            ->get();

        $priceHistory = [];
        foreach ($completedAuctions as $auction) {
            $highestBid = $auction->bids->first();
            if ($highestBid) {
                $priceHistory[] = [
                    'date' => $auction->end_at->format('Y-m-d'),
                    'price' => (float) $highestBid->amount,
                    'title' => $auction->title
                ];
            }
        }

        return response()->json([
            'total_auctions' => $totalAuctions,
            'active_auctions' => $activeAuctions,
            'collecting_auctions' => $collectingAuctions,
            'total_bids' => $totalBids,
            'total_offers' => $totalOffers,
            'won_auctions' => $wonCount,
            'price_history' => $priceHistory,
        ]);
    }

    /**
     * Upload user avatar.
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar file if exists
        if ($user->avatar) {
            $oldPath = str_replace('/storage/', '', $user->avatar);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = '/storage/' . $path;
        $user->save();

        return response()->json([
            'avatar' => $user->avatar,
            'user' => $user,
        ]);
    }
}
