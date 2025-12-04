<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\SystemEnum;
use App\Models\UserRole;
use App\Traits\BuildsPaginationMeta;

class UserController extends Controller
{
    use BuildsPaginationMeta;

    /**
     * Admin Portal API
     */
    // Handle an admin authentication attempt.
    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $rememberMe = $request->input('rememberMe', false);

        if (Auth::attempt($credentials, $rememberMe)) {
            $user = Auth::user();
            /** @var \App\Models\User $user */
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'code' => 'UserNotFound'
        ], 401);
    }

    public function register(Request $request)
    {
        Log::info('Registering user: ' . $request->email);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        Log::info('User registered successfully: ' . $request->email);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'staff_id' => 'staff_' . uniqid(),
            'role_id' => SystemEnum::getIdByName('user.role', 'admin'),
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'data' => $user
        ], 201);
    }

    public function adminProfile(Request $request)
    {
        $start = microtime(true);
        $user = Auth::user();

        Log::info('Admin profile accessed: ' . $user->email, [
            'execution_time' => microtime(true) - $start
        ]);

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    public function adminUserList(Request $request)
    {
        $start = microtime(true);
        // Page size bounds
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min(100, $perPage));

        // Basic search
        $search = trim((string) $request->input('search', ''));

        // Sorting (whitelist fields)
        $allowedSort = ['id','name','email','created_at'];
        $sort = $request->input('sort', 'id');
        if (!in_array($sort, $allowedSort, true)) { $sort = 'id'; }
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Soft delete filters
        $withDeleted = $request->boolean('with_deleted', false);
        $onlyDeleted = $request->boolean('only_deleted', false);

        // Relationship includes
        $include = collect(explode(',', (string)$request->input('include','')))->filter()->map(fn($s)=>trim($s))->values();
        $includeRoles = $include->contains('roles');

        $query = User::query();
        if ($withDeleted || $onlyDeleted) {
            $query->withTrashed();
        }
        if ($onlyDeleted) {
            $query->whereNotNull('deleted_at');
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }
        if ($includeRoles) {
            $query->with(['role.role']);
        }

        $paginator = $query->orderBy($sort, $direction)->paginate($perPage); // 'page' param auto-used

        // Transform items (add roles if included)
        $items = collect($paginator->items())->map(function ($user) use ($includeRoles) {
            $base = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->staff_id,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at,
            ];
            if ($includeRoles) {
                $base['roles'] = $user->role->map(function ($ur) {
                    return [
                        'user_role_id' => $ur->id,
                        'role_id' => $ur->role_id,
                        'ends_at' => $ur->ends_at,
                        'is_active' => $ur->is_active,
                        'role' => $ur->role ? [
                            'id' => $ur->role->id ?? null,
                            'etype' => $ur->role->etype ?? null,
                            'name' => $ur->role->name ?? null,
                        ] : null
                    ];
                });
            }
            return $base;
        });

        Log::info('Admin user list accessed', [
            'execution_time' => microtime(true) - $start,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'sort' => $sort,
            'direction' => $direction,
            'with_deleted' => $withDeleted,
            'only_deleted' => $onlyDeleted,
            'includes' => $include->all(),
        ]);

        return $this->paginatedResponse(
            $paginator,
            $items,
            [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'with_deleted' => $withDeleted,
                'only_deleted' => $onlyDeleted,
                'includes' => $include->all(),
            ]
        );
    }

    public function adminGenerateDummyUsers(Request $request)
    {
        $start = microtime(true);
        $count = (int) $request->input('count', 500);
        $count = max(1, min(500, $count));
        // Role assignment strategy
        $roleMode = $request->input('role_mode', 'random'); // random | single
        $forcedRole = $request->input('role'); // when role_mode=single

        // Resolve enum IDs once
        $enumIds = [
            'admin' => SystemEnum::getIdByName('user.role', 'admin'),
            'reviewer' => SystemEnum::getIdByName('user.role', 'reviewer'),
            'user' => SystemEnum::getIdByName('user.role', 'user'),
        ];
        // Fallback: ensure user role id exists, otherwise abort early.
        if (!$enumIds['user']) {
            return response()->json([
                'success' => false,
                'error' => 'Base user role enum missing.'
            ], 422);
        }

        // Create users via factory (cached password in factory for speed)
        $createdUsers = User::factory()->count($count)->create();

        $userRolesInsert = [];
        $stats = ['admin' => 0, 'reviewer' => 0, 'user' => 0];

        foreach ($createdUsers as $u) {
            // Decide role
            $roleKey = 'user';
            if ($roleMode === 'single' && $forcedRole && isset($enumIds[$forcedRole])) {
                $roleKey = $forcedRole;
            } elseif ($roleMode === 'random') {
                $rand = mt_rand(1, 100); // 1-100
                if ($rand <= 2) { // 2%
                    $roleKey = 'admin';
                } elseif ($rand <= 10) { // next 8%
                    $roleKey = 'reviewer';
                } else {
                    $roleKey = 'user';
                }
            }
            // Fallback if chosen role ID is missing -> use user role
            $roleId = $enumIds[$roleKey] ?: $enumIds['user'];
            $stats[$roleKey]++;
            $userRolesInsert[] = [
                'user_id' => $u->id,
                'role_id' => $roleId,
                'ends_at' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert pivot records in chunks
        foreach (array_chunk($userRolesInsert, 200) as $chunk) {
            UserRole::insert($chunk);
        }

        $duration = microtime(true) - $start;
        $sample = $createdUsers->take(5)->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'staff_id' => $u->staff_id,
        ]);

        Log::info('Dummy users generated with roles', [
            'requested' => $count,
            'duration' => $duration,
            'role_mode' => $roleMode,
            'stats' => $stats,
        ]);

        return response()->json([
            'success' => true,
            'generated' => $count,
            'duration_seconds' => $duration,
            'role_mode' => $roleMode,
            'role_counts' => $stats,
            'sample' => $sample,
            'note' => 'All users use password "password". Adjust role_mode or role param to control assignment.'
        ], 201);
    }
    /**
     * Admin Portal API Ends Here
     */
}
