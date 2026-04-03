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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use SendGrid\Mail\Mail;

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


   // 🔹 خطوة 1: redirect to provider
    public function redirect(Request $request, string $provider)
    {
        $provider = strtolower($provider);
        if (!in_array($provider, ['google', 'facebook'])) {
            return redirect(config('app.frontend_url') . '/login?error=InvalidProvider');
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->scopes(['email', 'profile']);
        } else {
            $driver->scopes(['email', 'public_profile']);
        }

        // optional: يمكن تحدد returnUrl
        $returnUrl = $request->query('returnUrl', null);

        if ($returnUrl) {
            $driver->with(['state' => encrypt($returnUrl)]);
        }

        return $driver->stateless()->redirect();
    }


    public function callback(Request $request, string $provider)
{
    $provider = strtolower($provider);

    if (!in_array($provider, ['google', 'facebook'])) {
        return redirect(config('app.frontend_url') . '/login?error=InvalidProvider');
    }

    if ($request->get('error') === 'access_denied') {
        return redirect(config('app.frontend_url') . '/login?error=AccessDenied');
    }

    try {
        $driver = Socialite::driver($provider)->stateless();

        if ($provider === 'facebook') {
            $driver->fields(['id', 'name', 'email', 'picture']);
        } else if ($provider === 'google') {

            $driver->scopes(['openid', 'profile', 'email']);
        }

        $socialUser = $driver->user();

        $result = $this->authService->oauthLogin($provider, $socialUser);

        if ($provider === 'google' && !empty($socialUser->getEmail())) {
            $user = \App\Models\User::find($result['user']['id']);

            if ($user && !$user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
                $result['user']['email_verified'] = true;
            }
        }

        $frontendCallback = config('app.frontend_url') . "/oauth/{$provider}/callback";

        $redirectTo = $frontendCallback
            . '?token=' . urlencode($result['token'])
            . '&status=' . urlencode($result['status']);

        return redirect($redirectTo);

    } catch (\Throwable $e) {
        report($e);
        return redirect(config('app.frontend_url') . '/login?error=OAuthFailed');
    }
}

 public function user(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthenticated'
        ], 401);
    }

    $provider = $user->socialAccounts()->value('provider');

    return response()->json([
        'status' => 'success',
        'data' => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'email_verified' => !is_null($user->email_verified_at),
            'avatar' => $user->avatar,
            'profile_completed' => (bool) $user->profile_completed,
            'role'=>$user->role,
            'provider' => $provider,
            'created_at' => $user->created_at,
        ]
    ]);
}


        public function updateEmail(Request $request)
{
    $user = $request->user();

    $request->validate([
        'email' => 'required|email|unique:users,email,' . $user->id,
    ]);

    return DB::transaction(function () use ($request, $user) {

        $user->email = $request->email;
        $user->email_verified_at = null;
        $user->save();

        $this->socialRepo->updateEmailForUser($user->id, $user->email);

        // $user->sendEmailVerificationNotification();

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

    public function register(Request $request)
{
    $request->validate([
        'full_name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
    ]);

    $user = User::create([
        'full_name' => $request->full_name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    $verificationToken = sha1($user->email . time());

    $user->email_verification_token = $verificationToken;
    $user->save();

    $verificationUrl = config('app.url') . '/api/verify-email?token=' . $verificationToken;

    $email = new Mail();
    $email->setFrom("no-reply@takaatuf.org", "Takaatuf");
    $email->setSubject("Verify your email");
    $email->addTo($user->email, $user->full_name);

    $email->addContent(
        "text/html",
        "
        <h3>Hello {$user->full_name}</h3>
        <p>Please verify your email:</p>
        <a href='{$verificationUrl}'
        style='padding:10px 20px;background:#2F80ED;color:white;text-decoration:none;border-radius:5px'>
        Verify Email
        </a>
        "
    );
    $sendgrid = new \SendGrid(env('SENDGRID_API_KEY'));
    $sendgrid->send($email);

    // token للتطبيق
    $token = $user->createToken('Takaatuf App')->plainTextToken;

    return response()->json([
        'user' => new UserResource($user),
        'token' => $token,
        'message' => 'Registration successful, please verify your email.'
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



