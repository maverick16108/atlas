<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Http\Request;

class BidController extends Controller
{
    /**
     * Get all bids for an auction, sorted by price DESC, then created_at ASC.
     */
    public function index($auctionId)
    {
        $auction = Auction::findOrFail($auctionId);

        $bids = Bid::where('auction_id', $auctionId)
            ->with('user:id,name,inn,phone,email,is_gpb,delivery_basis')
            ->orderByDesc('amount')
            ->orderBy('created_at')
            ->get()
            ->map(function ($bid) {
                return [
                    'id' => $bid->id,
                    'user' => $bid->user ? [
                        'id' => $bid->user->id,
                        'name' => $bid->user->name,
                        'inn' => $bid->user->inn,
                        'is_gpb' => $bid->user->is_gpb,
                        'delivery_basis' => $bid->user->delivery_basis,
                    ] : null,
                    'amount' => $bid->amount,
                    'bar_count' => $bid->bar_count,
                    'is_proxy' => $bid->is_proxy,
                    'created_at' => $bid->created_at,
                ];
            });

        return response()->json([
            'bids' => $bids,
            'auction' => [
                'bar_count' => $auction->bar_count,
                'bar_weight' => $auction->bar_weight,
            ],
        ]);
    }
}
