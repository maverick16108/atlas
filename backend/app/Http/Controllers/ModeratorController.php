<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModeratorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'moderator');

        if ($request->has('search') && $request->input('search')) {
            $search = trim($request->input('search'));
            
            // Check if search looks like date/time pattern (contains : or .)
            $isDateTimeSearch = preg_match('/^\d{1,2}[:.]\d{0,2}/', $search) || 
                                preg_match('/^\d{1,2}\.\d{1,2}/', $search);
            
            $query->where(function ($q) use ($search, $isDateTimeSearch) {
                // Always search by name
                $q->where('name', 'ilike', "%{$search}%");
                
                // Only search by email if not a date/time pattern
                if (!$isDateTimeSearch) {
                    $q->orWhere('email', 'ilike', "%{$search}%");
                }
                
                // Search by last_login_at formatted date (add 3 hours for Moscow timezone)
                $q->orWhereRaw("TO_CHAR(last_login_at + INTERVAL '3 hours', 'DD.MM.YYYY, HH24:MI') ILIKE ?", ["%{$search}%"]);
                
                // Search by created_at (joined date, add 3 hours for Moscow timezone)
                $q->orWhereRaw("TO_CHAR(created_at + INTERVAL '3 hours', 'DD.MM.YYYY') ILIKE ?", ["%{$search}%"]);
            });
        }
        
        // Sorting
        $sortKey = $request->input('sort_key', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // Validate sort key to prevent SQL injection or errors
        if (!in_array($sortKey, ['name', 'email', 'created_at', 'last_login_at'])) {
             $sortKey = 'created_at';
        }

        $query->orderBy($sortKey, $sortOrder);

        return $query->paginate($request->input('per_page', 50));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'moderator',
                'phone' => 'mod_'.uniqid(),
                'is_accredited' => true,
            ]);

            $creationChanges = collect($user->getAttributes())
            ->except(['id', 'password', 'remember_token', 'created_at', 'updated_at'])
            ->filter(fn($v) => $v !== null)
            ->mapWithKeys(fn($v, $k) => [$k => ['old' => null, 'new' => $v]])
            ->toArray();

        ActivityLog::log('created', 'moderator', $user->id, $user->name, $creationChanges);

            return response()->json($user, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal Error: ' . $e->getMessage()], 500); 
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::where('role', 'moderator')->findOrFail($id);
        return $user;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::where('role', 'moderator')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*(),.?":{}|<>]/'],
        ]);

        // Compute changes (exclude password from diff for security)
        $changesData = collect($validated)->except('password')->toArray();
        $changes = ActivityLog::computeChanges($user, $changesData);
        if (!empty($validated['password'])) {
            $changes = $changes ?? [];
            $changes['password'] = ['old' => '***', 'new' => '***'];
        }

        $user->name = $validated['name'] ?? $user->name;
        $user->email = $validated['email'] ?? $user->email;
        
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if ($changes) {
            ActivityLog::log('updated', 'moderator', $user->id, $user->name, $changes);
        }

        return $user;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::where('role', 'moderator')->findOrFail($id);
        $name = $user->name;

        $user->delete();

        ActivityLog::log('deleted', 'moderator', (int) $id, $name);

        return response()->noContent();
    }
}
