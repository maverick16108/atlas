<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource (Clients).
     */
    public function index(Request $request)
    {
        // Filter by role 'client'
        $query = User::where('role', 'client');

        if ($request->has('is_accredited')) {
            $query->where('is_accredited', filter_var($request->input('is_accredited'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search') && $request->input('search')) {
            $search = trim($request->input('search'));
            
            // Check if search looks like date/time pattern
            $isDateTimeSearch = preg_match('/^\d{1,2}[:.]\d{0,2}/', $search) || 
                                preg_match('/^\d{1,2}\.\d{1,2}/', $search);
            
            $query->where(function ($q) use ($search, $isDateTimeSearch) {
                $q->where('name', 'ilike', "%{$search}%");
                
                if (!$isDateTimeSearch) {
                    $q->orWhere('email', 'ilike', "%{$search}%");
                    $q->orWhere('phone', 'ilike', "%{$search}%");
                    $q->orWhere('auth_phone', 'ilike', "%{$search}%");
                    
                    // Also search by digits only (strip formatting from both search and stored value)
                    $digitsOnly = preg_replace('/\D/', '', $search);
                    if (strlen($digitsOnly) >= 3) {
                        $q->orWhereRaw("regexp_replace(phone, '[^0-9]', '', 'g') LIKE ?", ["%{$digitsOnly}%"]);
                        $q->orWhereRaw("regexp_replace(auth_phone, '[^0-9]', '', 'g') LIKE ?", ["%{$digitsOnly}%"]);
                    }
                }
                
                // Search by joined date (Moscow Timezone)
                $q->orWhereRaw("TO_CHAR(created_at + INTERVAL '3 hours', 'DD.MM.YYYY') ILIKE ?", ["%{$search}%"]);
            });
        }
        
        // Sorting
        $sortKey = $request->input('sort_key', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $allowedSorts = ['name', 'email', 'phone', 'is_accredited', 'is_gpb', 'created_at'];
        if (!in_array($sortKey, $allowedSorts)) {
             $sortKey = 'created_at';
        }

        $query->orderBy($sortKey, $sortOrder);

        // Secondary sort by id for stable ordering when primary sort values are equal
        if ($sortKey !== 'id') {
            $query->orderBy('id', $sortOrder);
        }

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
                'phone' => ['required', 'string', 'max:20', 'unique:users'],
                'is_accredited' => ['boolean'],
                'is_gpb' => ['boolean'],
                'delivery_basis' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'auth_phone' => [
                    'nullable', 
                    'string', 
                    'max:20', 
                    'unique:users',
                    'required_if:is_accredited,true'
                ],
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'auth_phone' => $validated['auth_phone'] ?? null,
                'password' => Hash::make(\Illuminate\Support\Str::random(32)), // Random password since we use SMS
                'role' => 'client',
                'is_accredited' => $validated['is_accredited'] ?? false,
                'is_gpb' => $validated['is_gpb'] ?? false,
                'delivery_basis' => $validated['delivery_basis'] ?? null,
            ]);

            $creationChanges = collect($user->getAttributes())
            ->except(['id', 'password', 'remember_token', 'created_at', 'updated_at'])
            ->filter(fn($v) => $v !== null)
            ->mapWithKeys(fn($v, $k) => [$k => ['old' => null, 'new' => $v]])
            ->toArray();

        ActivityLog::log('created', 'user', $user->id, $user->name, $creationChanges);

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
        return User::where('role', 'client')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::where('role', 'client')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'is_accredited' => ['boolean'],
            'is_gpb' => ['boolean'],
            'delivery_basis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'auth_phone' => [
                'nullable', 
                'string', 
                'max:20', 
                Rule::unique('users')->ignore($user->id),
                'required_if:is_accredited,true'
            ],
        ]);

        // Compute changes before update
        $changes = ActivityLog::computeChanges($user, $validated);

        $user->name = $validated['name'] ?? $user->name;
        $user->email = $validated['email'] ?? $user->email;
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->auth_phone = array_key_exists('auth_phone', $validated) ? $validated['auth_phone'] : $user->auth_phone;
        
        if (isset($validated['is_accredited'])) {
            $user->is_accredited = $validated['is_accredited'];
        }

        if (isset($validated['is_gpb'])) {
            $user->is_gpb = $validated['is_gpb'];
        }

        if (array_key_exists('delivery_basis', $validated)) {
            $user->delivery_basis = $validated['delivery_basis'];
        }
        
        $user->save();

        if ($changes) {
            ActivityLog::log('updated', 'user', $user->id, $user->name, $changes);
        }

        return $user;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::where('role', 'client')->findOrFail($id);
        $name = $user->name;

        $user->delete();

        ActivityLog::log('deleted', 'user', (int) $id, $name);

        return response()->noContent();
    }

    /**
     * Toggle accreditation status.
     */
    public function toggleAccreditation(string $id)
    {
        $user = User::where('role', 'client')->findOrFail($id);
        $oldValue = $user->is_accredited;
        $user->is_accredited = !$user->is_accredited;
        $user->save();

        ActivityLog::log('updated', 'user', $user->id, $user->name, [
            'is_accredited' => ['old' => $oldValue, 'new' => $user->is_accredited],
        ]);

        return $user;
    }
}
