<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Services\CompanyRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * طھط³ط¬ظٹظ„ ط­ط³ط§ط¨ ظ…ط³طھط£ط¬ط± ط¬ط¯ظٹط¯ (Sign Up).
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        // ط¥ظ†ط´ط§ط، ط¨ط±ظˆظپط§ظٹظ„ ط§ظ„ظ…ط³طھط£ط¬ط± ط§ظ„ظ…ط±ط¨ظˆط· ط¨ظ‡ (ط¨ط¯ظˆظ† Global Scope ظ„ط£ظ†ظ‡ ظ…ط³طھط£ط¬ط± ط¬ط¯ظٹط¯ ط¨ظ„ط§ ط´ط±ظƒط©)
        // Assign the Spatie 'tenant' role so middleware and hasRole() checks work
        app(CompanyRoleService::class)->assignTenantRole($user);

        Tenant::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'company_id' => null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ]
            ]
        ], 201);
    }

    /**
     * طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„ (Sign In).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // if ($user->role !== 'tenant') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Access denied. Only tenants are allowed.'
        //     ], 403);
        // }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ]
            ]
        ]);
    }

    /**
     * طھط³ط¬ظٹظ„ ط§ظ„ط®ط±ظˆط¬ (Logout).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tokens revoked successfully'
        ]);
    }

    /**
     * ط¬ظ„ط¨ ط¨ظٹط§ظ†ط§طھ ط§ظ„ظ…ط³طھط®ط¯ظ… ط§ظ„ط­ط§ظ„ظٹ (Me).
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
            ]
        ]);
    }
}
