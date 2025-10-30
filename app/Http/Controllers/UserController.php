<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\SystemEnum;

class UserController extends Controller
{

    /**
     * Handle an admin authentication attempt.
     */
    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $rememberMe = $request->input('rememberMe', false);

        if (Auth::attempt($credentials, $rememberMe)) {
            $user = Auth::user();
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
}
