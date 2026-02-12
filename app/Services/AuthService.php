<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\SocialAccountRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;


class AuthService
{
    public function __construct(
        protected UserRepository $userRepo,
        protected SocialAccountRepository $socialRepo
    ) {}

    public function oauthLogin(string $provider, SocialUser $socialUser): array
    {
        $provider   = strtolower($provider);
        $providerId = (string) $socialUser->getId();
        $name       = $socialUser->getName() ?? $socialUser->getNickname() ?? 'New User';
        $avatar     = method_exists($socialUser, 'getAvatar') ? $socialUser->getAvatar() : null;
        $email      = $socialUser->getEmail();

        // if the account exists in social account tabel
        $social = $this->socialRepo->findByProvider($provider, $providerId);

        if ($social) {
            $user = $social->user;

            if ($avatar && !$user->avatar) {
                $user->avatar = $avatar;
                $user->save();
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'status' => 'success',
                // 'need_email' => is_null($user->email),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'email_verified' => !is_null($user->email_verified_at),
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                ],
                'profile_completed' => (bool) $user->profile_completed,
                'token' => $token,
                // 'provider' => $provider,
                // 'provider_id' => $providerId,
                'message' => is_null($user->email)
                    ? 'Please enter your email to complete login'
                    : 'Login successful'
            ];
        }

        // 2 Registeration for the first time
        return DB::transaction(function () use (
            $provider,
            $providerId,
            $email,
            $name,
            $avatar,
            $socialUser
        ) {

            // 🔹if there are email , search it
            $user = $email ? $this->userRepo->findByEmail($email) : null;

            // 🔹 No email , create user
            if (!$user) {
                $user = $this->userRepo->create([
                    'full_name' => $name,
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                    'profile_completed' => false,
                    'email_verified_at' => $email ? now() : null,
                    'avatar' => $avatar,
                ]);
            }
            // link with social account table

            $this->socialRepo->linkToUser(
                $user->id,
                $provider,
                $providerId,
                $email,
                $socialUser->getRaw()
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'status' => 'success',
                // 'need_email' => is_null($user->email),
                'user' => [
                    'id' => $user->id,
                    // 'name' => $user->full_name,
                    'email' => $user->email,
                    'email_verified' => !is_null($user->email_verified_at),
                    // 'avatar' => $user->avatar,
                    // 'role' => $user->role,
                ],
                'token' => $token,
                'profile_completed' => (bool) $user->profile_completed,
                // 'provider' => $provider,
                // 'provider_id' => $providerId,
                'message' => is_null($user->email)
                    ? 'Please enter your email to complete login'
                    : 'Login successful'
            ];
        });
    }

    // login with email
    public function loginWithEmail($email, $password)
    {
        $user = $this->userRepo->findByEmail($email);
        if (!$user) {
            throw new \Exception('User not found');
        }
        if (!Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'status' => $user->profile_completed ? 'Profile complete' : 'profile_incomplete',
        ];
    }

    // Logout
    public function logout($user)
    {
        $user->tokens()->delete();
    }
}
