<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        // 1. User Stats
        $totalUsers = User::where('role', 'client')->count();
        $pendingUsers = User::where('role', 'client')->where('is_accredited', false)->count();

        // 2. Auction Stats
        $activeAuctions = Auction::whereIn('status', ['active', 'collecting_offers', 'gpb_right'])->count();
        $commissionAuctions = Auction::where('status', 'commission')->count();
        $completedAuctions = Auction::where('status', 'completed')->count();

        // 3. Chart Data (Last 30 days auctions)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(29);
        
        $chartData = Auction::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing dates with 0
        $formattedChartData = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');
            $record = $chartData->firstWhere('date', $dateString);
            $formattedChartData[] = [
                'date' => $currentDate->format('d.m'),
                'count' => $record ? $record->count : 0
            ];
            $currentDate->addDay();
        }

        return response()->json([
            'stats' => [
                [
                    'name' => 'Всего пользователей',
                    'value' => number_format($totalUsers),
                    'change' => 'Аккредитованных: ' . User::where('role', 'client')->where('is_accredited', true)->count(),
                    'type' => 'neutral'
                ],
                [
                    'name' => 'Ожидают аккредитации',
                    'value' => number_format($pendingUsers),
                    'change' => $pendingUsers > 0 ? 'Требует внимания' : 'Нет заявок',
                    'type' => $pendingUsers > 0 ? 'warn' : 'up'
                ],
                [
                    'name' => 'Активные аукционы',
                    'value' => number_format($activeAuctions),
                    'change' => 'Идет торг',
                    'type' => 'up'
                ],
                [
                    'name' => 'Работа комиссии',
                    'value' => number_format($commissionAuctions),
                    'change' => $commissionAuctions > 0 ? 'Требует внимания' : 'Нет аукционов',
                    'type' => $commissionAuctions > 0 ? 'warn' : 'neutral'
                ],
                [
                    'name' => 'Завершенные аукционы',
                    'value' => number_format($completedAuctions),
                    'change' => 'Всего',
                    'type' => 'neutral'
                ]
            ],
            'chart' => $formattedChartData
        ]);
    }
}
