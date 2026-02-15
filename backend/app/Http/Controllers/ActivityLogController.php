<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     * Only accessible by super_admin.
     */
    public function index(Request $request)
    {
        // Role check
        if ($request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = ActivityLog::with('user:id,name,role');

        // Filter by entity type
        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by user role
        if ($request->filled('user_role')) {
            $role = $request->input('user_role');
            $query->whereHas('user', function ($uq) use ($role) {
                $uq->where('role', $role);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // Search across all visible fields
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                // Entity name
                $q->where('entity_name', 'ilike', "%{$search}%");
                // User name
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'ilike', "%{$search}%");
                });
                // Action (match Russian labels)
                $actionMap = [
                    'создание' => 'created', 'создан' => 'created',
                    'изменение' => 'updated', 'изменен' => 'updated',
                    'удаление' => 'deleted', 'удален' => 'deleted',
                ];
                $searchLower = mb_strtolower($search);
                foreach ($actionMap as $label => $value) {
                    if (str_contains($label, $searchLower)) {
                        $q->orWhere('action', $value);
                    }
                }
                // Entity type (match Russian labels)
                $entityMap = [
                    'аукцион' => 'auction',
                    'участник' => 'user',
                    'модератор' => 'moderator',
                    'ставк' => 'bid',
                ];
                foreach ($entityMap as $label => $value) {
                    if (str_contains($label, $searchLower)) {
                        $q->orWhere('entity_type', $value);
                    }
                }
                // Date/time search (Moscow timezone, format DD.MM.YYYY HH:MM:SS)
                $q->orWhereRaw("TO_CHAR(activity_logs.created_at + INTERVAL '3 hours', 'DD.MM.YYYY, HH24:MI:SS') ILIKE ?", ["%{$search}%"]);
            });
        }

        // Sorting
        $sortKey = $request->input('sort_key', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['created_at', 'action', 'entity_type', 'entity_name', 'user_name'];
        if (!in_array($sortKey, $allowedSorts)) {
            $sortKey = 'created_at';
        }

        if ($sortKey === 'user_name') {
            $query->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
                  ->select('activity_logs.*')
                  ->orderBy('users.name', $sortOrder);
        } else {
            $query->orderBy($sortKey, $sortOrder);
        }

        // Secondary sort for stable ordering
        $query->orderBy('activity_logs.id', $sortOrder);

        return $query->paginate($request->input('per_page', 50));
    }
}
