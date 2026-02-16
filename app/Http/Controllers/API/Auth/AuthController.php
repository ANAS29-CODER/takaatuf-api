<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OAuthLoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmail;
use App\Models\User;
use App\Repositories\SocialAccountRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //
    protected $userRepo;
    protected $authService;
    protected $socialRepo;

    public function __construct(AuthService $authService, UserRepository $userRepo, SocialAccountRepository $socialRepo)
    {
        $this->authService = $authService;
        $this->userRepo = $userRepo;
        $this->socialRepo = $socialRepo;
    }

    /**
     * Redirect to provider
     */
    public function redirect(Request $request, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        // Only allow relative return URLs
        $returnUrl = $request->query('returnUrl', '/');
        if (!str_starts_with($returnUrl, '/')) {
            $returnUrl = '/';
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->scopes(['email', 'profile']);
        } else {
            $driver->scopes(['email', 'public_profile']);
        }

        return $driver
            ->with(['state' => encrypt($returnUrl)])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid provider'
            ], 400);
        }

        if ($request->get('error') === 'access_denied') {
            return response()->json([
                'status' => 'cancelled',
                'message' => 'Login was cancelled. Please try again.'
            ], 200);
        }

        try {
            $driver = Socialite::driver($provider)->stateless();

            if ($provider === 'facebook') {
                $driver->fields(['id', 'name', 'email', 'picture']);
            }

            $socialUser = $driver->user();

            $result = $this->authService->oauthLogin($provider, $socialUser);

            $result['return_url'] = empty($result['user']['email'])
                ? '/update-email'
                : '/dashboard';
                
            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'status' => 'error',
                'message' => 'OAuth failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     */
    public function updateEmail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'email' => 'required|email|unique:users,email',
        ]);

        return DB::transaction(function () use ($request) {

            $user = $this->userRepo->getById($request->user_id);
            $user->email = $request->email;

            $user->email_verified_at = null;
            $user->save();

            $this->socialRepo->updateEmailForUser($user->id, $user->email);

            $user->sendEmailVerificationNotification();

            return response()->json([
                'status' => 'success',
                'message' => 'Email updated. Please check your inbox to verify your email.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'email_verified' => false,
                    'profile_completed' => (bool) $user->profile_completed,
                ],
            ]);
        });
    }
    /**
     * Register
     *
     * @param \App\Http\Requests\RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'full_name' => $request->full_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Send verification email
        $user->sendEmailVerificationNotification();

        // Create token
        $token = $user->createToken('Takaatuf App')->plainTextToken;

        return response()->json([
            'user'    => new UserResource($user),
            'profile_completed' => (bool) $user->profile_completed,
            'token'   => $token,
            'message' => 'Registration successful, please verify your email.',
        ], 201);
    }

    // Login with email

    public function login(LoginRequest $request)
    {
        try {
            $data = $this->authService->loginWithEmail(
                $request->email,
                $request->password
            );

            return response()->json([
                'token' => $data['token'],
                'status' => $data['user']->hasVerifiedEmail()
                    ? 'verified'
                    : 'not_verified',
                'user' => new UserResource($data['user']),
                'profile_completed' => (bool) $data['user']->profile_completed,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid login credentials.'
            ], 401);
        }
    }

    // Logout
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully']);
    }
}
