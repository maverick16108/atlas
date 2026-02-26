<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuctionController extends Controller
{
    // Valid statuses for auctions
    private const VALID_STATUSES = [
        'draft',                // Черновик
        'collecting_offers',    // Сбор предложений
        'scheduled',            // Запланирован
        'active',               // Активный
        'gpb_right',            // Право ГПБ
        'commission',           // Работа комиссии
        'completed',            // Завершен
        'paused',               // Приостановлен
        'cancelled',            // Отменен
    ];

    /**
     * Display a listing of auctions.
     */
    public function index(Request $request)
    {
        $query = Auction::query();

        // Try to load participants (requires migration)
        try {
            \DB::select('SELECT 1 FROM auction_participants LIMIT 1');
            $query->withCount('auctionParticipants')
                ->withCount('initialOffers')
                ->withCount('bids')
                ->with('participants:id,name,inn');
        } catch (QueryException $e) {
            // auction_participants table not yet created
        }

        // Filter by Status
        if ($request->has('status') && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search
        if ($request->has('search') && $request->input('search')) {
            $search = trim($request->input('search'));
            
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('status', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereRaw('id::text ILIKE ?', ["%{$search}%"]);
                  
                // Search by date/time (Moscow Timezone)
                // Use HH24:MI to support querying by time "14:30"
                $q->orWhereRaw("TO_CHAR(start_at + INTERVAL '3 hours', 'DD.MM.YYYY HH24:MI') ILIKE ?", ["%{$search}%"]);
                $q->orWhereRaw("TO_CHAR(end_at + INTERVAL '3 hours', 'DD.MM.YYYY HH24:MI') ILIKE ?", ["%{$search}%"]);
            });
        }
        
        // Sorting
        $sortKey = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');
        
        $allowedSorts = ['id', 'title', 'status', 'start_at', 'end_at', 'created_at', 'min_price', 'min_step'];
        if (!in_array($sortKey, $allowedSorts)) {
            $sortKey = 'created_at';
        }

        $query->orderBy($sortKey, $sortOrder);

        return $query->paginate($request->input('per_page', 50));
    }

    /**
     * Store a newly created auction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'status' => ['string', Rule::in(self::VALID_STATUSES)],
            'min_step' => ['numeric', 'min:0'],
            'step_time' => ['integer', 'min:1', 'max:1440'],
            'timezone' => ['string', 'max:50'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:10000'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'bar_count' => ['nullable', 'integer', 'min:0'],
            'bar_weight' => ['nullable', 'numeric', 'min:0'],
            'gpb_minutes' => ['nullable', 'integer', 'min:1'],
            'invite_all' => ['nullable', 'boolean'],
        ]);

        $tomorrow = now()->addDay();
        $auction = Auction::create([
            'title' => $validated['title'] ?? '',
            'start_at' => $validated['start_at'] ?? $tomorrow->copy()->setTime(10, 0),
            'end_at' => $validated['end_at'] ?? $tomorrow->copy()->setTime(12, 0),
            'status' => $validated['status'] ?? 'draft',
            'min_step' => $validated['min_step'] ?? 50,
            'step_time' => $validated['step_time'] ?? 5,
            'timezone' => $validated['timezone'] ?? 'Europe/Moscow',
            'min_price' => $validated['min_price'] ?? 900,
            'description' => $validated['description'] ?? null,
            'bar_count' => $validated['bar_count'] ?? 10,
            'bar_weight' => $validated['bar_weight'] ?? 12.5,
            'gpb_minutes' => $validated['gpb_minutes'] ?? 30,
            'invite_all' => $validated['invite_all'] ?? false,
        ]);

        // Sync participants if provided
        try {
            if (!empty($validated['participant_ids'])) {
                $auction->participants()->sync($validated['participant_ids']);
            }

            $creationChanges = collect($auction->getAttributes())
                ->except(['id', 'created_at', 'updated_at'])
                ->filter(fn($v) => $v !== null)
                ->mapWithKeys(fn($v, $k) => [$k => ['old' => null, 'new' => $v]])
                ->toArray();

            ActivityLog::log('created', 'auction', $auction->id, $auction->title, $creationChanges);

            return response()->json($auction->load('participants')->loadCount('auctionParticipants'), 201);
        } catch (QueryException $e) {
            $creationChanges = collect($auction->getAttributes())
                ->except(['id', 'created_at', 'updated_at'])
                ->filter(fn($v) => $v !== null)
                ->mapWithKeys(fn($v, $k) => [$k => ['old' => null, 'new' => $v]])
                ->toArray();

            ActivityLog::log('created', 'auction', $auction->id, $auction->title, $creationChanges);
            return response()->json($auction, 201);
        }
    }

    /**
     * Display the specified auction.
     */
    public function show(string $id)
    {
        try {
            return Auction::with('participants')->withCount('auctionParticipants')->withCount('initialOffers')->withCount('bids')->findOrFail($id);
        } catch (QueryException $e) {
            return Auction::findOrFail($id);
        }
    }

    /**
     * Update the specified auction.
     */
    public function update(Request $request, string $id)
    {
        $auction = Auction::findOrFail($id);

        $validated = $request->validate([
            'title' => ['string', 'max:255'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'status' => ['string', Rule::in(self::VALID_STATUSES)],
            'min_step' => ['numeric', 'min:0'],
            'step_time' => ['integer', 'min:1', 'max:1440'],
            'timezone' => ['string', 'max:50'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:10000'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'bar_count' => ['nullable', 'integer', 'min:0'],
            'bar_weight' => ['nullable', 'numeric', 'min:0'],
            'gpb_minutes' => ['nullable', 'integer', 'min:1'],
            'invite_all' => ['nullable', 'boolean'],
        ]);

    // Time-based status validation
    if (isset($validated['status']) && $validated['status'] !== 'draft') {
        $now = now();
        $startAt = isset($validated['start_at']) ? \Carbon\Carbon::parse($validated['start_at']) : $auction->start_at;
        $endAt = isset($validated['end_at']) ? \Carbon\Carbon::parse($validated['end_at']) : $auction->end_at;
        $gpbStartedAt = $auction->gpb_started_at;
        $gpbMinutes = $validated['gpb_minutes'] ?? $auction->gpb_minutes ?? 30;

        // Only apply time-based checks if auction start time has already passed
        if ($startAt && $now->gte($startAt)) {
            // If auction end time has passed → can't set active, scheduled, collecting_offers
            if ($endAt && $now->gte($endAt)) {
                $blockedByEnd = ['collecting_offers', 'scheduled', 'active'];
                if (in_array($validated['status'], $blockedByEnd)) {
                    return response()->json([
                        'message' => 'Время аукциона истекло. Невозможно установить статус "' . $validated['status'] . '".',
                        'errors' => ['status' => ['Время аукциона истекло']]
                    ], 422);
                }
            }

            // If GPB time has passed → can't set gpb_right, active, scheduled, collecting_offers
            if ($gpbStartedAt && $now->gte(\Carbon\Carbon::parse($gpbStartedAt)->addMinutes($gpbMinutes))) {
                $blockedByGpb = ['collecting_offers', 'scheduled', 'active', 'gpb_right'];
                if (in_array($validated['status'], $blockedByGpb)) {
                    return response()->json([
                        'message' => 'Время права ГПБ истекло. Невозможно установить статус "' . $validated['status'] . '".',
                        'errors' => ['status' => ['Время права ГПБ истекло']]
                    ], 422);
                }
            }
        }

        // When manually setting gpb_right, reset gpb_started_at to now
        if ($validated['status'] === 'gpb_right' && $auction->status !== 'gpb_right') {
            $validated['gpb_started_at'] = $now;
        }
    }

    // Separate participant_ids from auction data
        $participantIds = $validated['participant_ids'] ?? null;
        unset($validated['participant_ids']);

        // Compute changes before update
        $changes = [];
        try {
            $changes = ActivityLog::computeChanges($auction, $validated);
        } catch (\Throwable $e) {
            // activity_logs may not be available
        }

        $auction->update($validated);

        // Log participant changes
        $participantChanges = null;
        try {
            if ($participantIds !== null) {
                $oldIds = $auction->participants()->pluck('users.id')->toArray();
                $auction->participants()->sync($participantIds);
                sort($oldIds);
                $newIds = $participantIds;
                sort($newIds);
                if ($oldIds !== $newIds) {
                    $participantChanges = ['participant_ids' => ['old' => $oldIds, 'new' => $newIds]];
                }
            }
        } catch (QueryException $e) {
            // participants table may not exist
        }

        // Merge changes
        $allChanges = array_merge($changes ?? [], $participantChanges ?? []);
        if (!empty($allChanges)) {
            try {
                ActivityLog::log('updated', 'auction', $auction->id, $auction->title, $allChanges);
            } catch (\Throwable $e) {
                // activity_logs may not be available
            }
        }

        try {
            $freshAuction = $auction->load('participants')->loadCount('auctionParticipants');
        } catch (QueryException $e) {
            $freshAuction = $auction;
        }

        // Broadcast auction update to all connected clients
        try {
            event(new \App\Events\AuctionUpdated($auction->id, $freshAuction->toArray()));
        } catch (\Throwable $e) {
            // Broadcasting may fail silently
        }

        return $freshAuction;
    }

    /**
     * Auto-transition auction from 'active' to 'gpb_right' when timer expires.
     */
    public function transitionToGpb(string $id)
    {
        $auction = Auction::findOrFail($id);

        // Only transition if currently active
        if ($auction->status !== 'active') {
            return response()->json(['message' => 'Аукцион не в статусе "Активный"'], 422);
        }

        $auction->update([
            'status' => 'gpb_right',
            'gpb_started_at' => now(),
        ]);

        try {
            ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                'status' => ['old' => 'active', 'new' => 'gpb_right'],
                'gpb_started_at' => ['old' => null, 'new' => now()->toISOString()],
            ]);
        } catch (\Throwable $e) {}

        try {
            $freshAuction = $auction->load('participants')->loadCount('auctionParticipants')->loadCount('initialOffers')->loadCount('bids');
        } catch (QueryException $e) {
            $freshAuction = $auction;
        }

        // Broadcast status change to all connected clients
        try {
            event(new \App\Events\AuctionUpdated($auction->id, $freshAuction->toArray()));
        } catch (\Throwable $e) {}

        return $freshAuction;
    }

    /**
     * Remove the specified auction.
     */
    public function destroy(string $id)
    {
        $auction = Auction::findOrFail($id);
        $title = $auction->title;

        $auction->delete();

        try {
            ActivityLog::log('deleted', 'auction', (int) $id, $title);
        } catch (\Throwable $e) {
            // activity_logs may not be available
        }

        return response()->noContent();
    }

    /**
     * Get participants for an auction.
     */
    public function participants(string $id)
    {
        $auction = Auction::findOrFail($id);
        return $auction->participants()->get();
    }

    /**
     * Sync participants for an auction.
     */
    public function syncParticipants(Request $request, string $id)
    {
        $auction = Auction::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $oldIds = $auction->participants()->pluck('users.id')->toArray();
        $auction->participants()->sync($validated['user_ids']);

        sort($oldIds);
        $newIds = $validated['user_ids'];
        sort($newIds);
        if ($oldIds !== $newIds) {
            ActivityLog::log('updated', 'auction', $auction->id, $auction->title, [
                'participant_ids' => ['old' => $oldIds, 'new' => $newIds],
            ]);
        }

        return response()->json([
            'message' => 'Участники обновлены',
            'participants' => $auction->participants()->get(),
        ]);
    }

    /**
     * Send invitations to auction participants.
     */
    public function sendInvitations(Request $request, string $id)
    {
        $auction = Auction::findOrFail($id);

        $inviteAll = $request->boolean('invite_all', $auction->invite_all);
        $participantIds = $request->input('participant_ids', []);

        // If invite_all, sync ALL accredited clients
        if ($inviteAll) {
            $allClientIds = \App\Models\User::where('role', 'client')
                ->where('is_accredited', true)
                ->pluck('id')
                ->toArray();

            $existing = AuctionParticipant::where('auction_id', $auction->id)->pluck('user_id')->toArray();
            $toAdd = array_diff($allClientIds, $existing);
            foreach ($toAdd as $userId) {
                AuctionParticipant::create([
                    'auction_id' => $auction->id,
                    'user_id' => $userId,
                    'status' => 'approved',
                ]);
            }
        } elseif (!empty($participantIds)) {
            // Sync only specified participants
            $existing = AuctionParticipant::where('auction_id', $auction->id)->pluck('user_id')->toArray();
            $toAdd = array_diff($participantIds, $existing);
            foreach ($toAdd as $userId) {
                AuctionParticipant::create([
                    'auction_id' => $auction->id,
                    'user_id' => (int)$userId,
                    'status' => 'approved',
                ]);
            }
        }

        // Update invited_at for ALL participants
        $count = AuctionParticipant::where('auction_id', $auction->id)
            ->update(['invited_at' => now()]);

        // Create client notifications for each participant
        $participantUserIds = AuctionParticipant::where('auction_id', $auction->id)
            ->pluck('user_id');

        foreach ($participantUserIds as $userId) {
            try {
                \App\Models\ClientNotification::create([
                    'user_id' => $userId,
                    'type' => 'auction_invite',
                    'title' => 'Приглашение на аукцион',
                    'message' => "Вы приглашены на аукцион: {$auction->title}",
                    'data' => ['auction_id' => $auction->id],
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to create notification for user {$userId}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Приглашения отправлены ({$count} участников)",
            'invited_count' => $count,
        ]);
    }

    /**
     * List all participants (users) for participant selection.
     */
    public function participantsList()
    {
        return \App\Models\User::where('role', 'client')
            ->where('is_accredited', true)
            ->select('id', 'name', 'email', 'phone', 'inn', 'kpp', 'auth_phone')
            ->orderBy('name')
            ->get();
    }
}
