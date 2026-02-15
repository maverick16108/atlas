<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\InitialOffer;
use Illuminate\Http\Request;

class InitialOfferController extends Controller
{
    /**
     * List initial offers for an auction (moderator view).
     */
    public function index(string $auctionId)
    {
        $auction = Auction::findOrFail($auctionId);

        return $auction->initialOffers()
            ->with('user:id,name,inn')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Store a new initial offer (client submission).
     */
    public function store(Request $request, string $auctionId)
    {
        $auction = Auction::findOrFail($auctionId);

        // Only allow offers during collecting_offers status
        if ($auction->status !== 'collecting_offers') {
            return response()->json([
                'message' => 'Сбор предложений не активен для данного аукциона',
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'volume' => ['required', 'numeric', 'min:0.0001'],
            'price' => ['required', 'numeric', 'min:0'],
            'delivery_basis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $offer = InitialOffer::create([
            'auction_id' => $auction->id,
            'user_id' => $validated['user_id'],
            'volume' => $validated['volume'],
            'price' => $validated['price'],
            'delivery_basis' => $validated['delivery_basis'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ]);

        ActivityLog::log('created', 'bid', $offer->id, 'Предложение #' . $offer->id, null, [
            'auction_id' => $auction->id,
            'auction_title' => $auction->title,
            'user_id' => $validated['user_id'],
            'volume' => $validated['volume'],
            'price' => $validated['price'],
        ]);

        return response()->json($offer->load('user:id,name'), 201);
    }
}
