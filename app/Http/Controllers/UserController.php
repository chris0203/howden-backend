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
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'min:1'],
        ]);
        Log::info('User registered successfully: ' . $request->email);

        $roleId = (int) $request->input('role_id');
        // Validate role_id exists in SystemEnum for etype user.role
        $roleExists = SystemEnum::where('etype', 'user.role')
            ->where('id', $roleId)
            ->exists();
        if (!$roleExists) {
            return response()->json([
                'success' => false,
                'code' => 'InvalidRole',
                'message' => 'Provided role_id does not exist in user.role enums.'
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'staff_id' => 'staff_' . uniqid(),
            'password' => Hash::make($request->password),
        ]);

        // Save role assignment in UserRole pivot
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'ends_at' => null,
            'is_active' => 1,
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
        if ($user) {
            $user->load(['role.role']);
        }

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

    public function updateUser(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = User::find((int)$id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UserNotFound',
            ], 404);
        }

        // Ensure email uniqueness if changing email
        if (isset($validated['email'])) {
            $exists = User::where('email', $validated['email'])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'code' => 'EmailAlreadyExists',
                    'message' => 'Another user with the same email already exists.'
                ], 409);
            }
            $user->email = $validated['email'];
        }
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        // Update password only if provided and non-empty
        if (array_key_exists('password', $validated) && $validated['password'] !== null && $validated['password'] !== '') {
            $user->password = Hash::make($validated['password']);
        }

        // Optional role update via pivot
        if (isset($validated['role_id'])) {
            $roleId = (int)$validated['role_id'];
            $roleExists = SystemEnum::where('etype', 'user.role')
                ->where('id', $roleId)
                ->exists();
            if (!$roleExists) {
                return response()->json([
                    'success' => false,
                    'code' => 'InvalidRole',
                    'message' => 'Provided role_id does not exist in user.role enums.'
                ], 422);
            }
            // Deactivate existing active roles (if any) and assign new active role
            UserRole::where('user_id', $user->id)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'ends_at' => now()]);
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'ends_at' => null,
                'is_active' => 1,
            ]);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }

    public function softDeleteUser(Request $request, $id)
    {
        $id = (int)$id;
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UserNotFound',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User soft-deleted',
            'id' => $id,
        ], 200);
    }

    /**
     * Admin Portal API Ends Here
     */
}
