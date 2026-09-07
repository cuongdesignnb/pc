<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CommerceIdentityMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly CommerceIdentityMergeService $commerce)
    {
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'terms_accepted' => 'required|accepted',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => 'customer',
        ]);

        $token = $this->issueToken($user, true);
        $commerce = $this->commerce->mergeGuestCartIntoUser($user, $request->header('X-Cart-Session'));

        return $this->noStore(response()->json([
            'message' => 'Đăng ký thành công',
            'user' => $user->load('defaultAddress'),
            'token' => $token,
            'commerce' => [
                'cart_merged' => $commerce['merged'],
                'cart_warnings' => $commerce['warnings'],
            ],
        ], 201));
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'sometimes|boolean',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.'],
            ]);
        }

        $token = $this->issueToken($user, (bool) ($validated['remember'] ?? false));
        $commerce = $this->commerce->mergeGuestCartIntoUser($user, $request->header('X-Cart-Session'));

        return $this->noStore(response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user->load('defaultAddress'),
            'token' => $token,
            'commerce' => [
                'cart_merged' => $commerce['merged'],
                'cart_warnings' => $commerce['warnings'],
            ],
        ]));
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        return $this->noStore(response()->json([
            'message' => 'Đăng xuất thành công',
        ]));
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        return $this->noStore(response()->json([
            'user' => $request->user()->load('defaultAddress'),
        ]));
    }

    /**
     * Backward-compatible alias for the original /user endpoint.
     */
    public function user(Request $request): JsonResponse
    {
        return $this->me($request);
    }

    /**
     * Retry a commerce merge without issuing a second auth token.
     */
    public function mergeCommerce(Request $request): JsonResponse
    {
        $commerce = $this->commerce->mergeGuestCartIntoUser(
            $request->user(),
            $request->header('X-Cart-Session'),
        );

        return $this->noStore(response()->json([
            'cart_merged' => $commerce['merged'],
            'cart_warnings' => $commerce['warnings'],
        ]));
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user' => $user,
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không chính xác.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Đổi mật khẩu thành công',
        ]);
    }

    private function issueToken(User $user, bool $remember): string
    {
        return $user->createToken(
            'auth_token',
            ['*'],
            now()->addDays($remember ? 30 : 1),
        )->plainTextToken;
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
