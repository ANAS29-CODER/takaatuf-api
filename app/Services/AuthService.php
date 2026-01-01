<?php

namespace App\Services;

use App\Repositories\SocialAccountRepository;
use App\Repositories\UserRepository;
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
        $email      = $socialUser->getEmail();
        $name       = $socialUser->getName() ?? $socialUser->getNickname() ?? 'New User';

        return DB::transaction(function () use ($provider, $providerId, $email, $name, $socialUser) {


            $social = $this->socialRepo->findByProvider($provider, $providerId);
            if ($social) {
                $user = $social->user;


                if ($email && !$user->email) {
                    $exists = $this->userRepo->findByEmail($email);
                    if (!$exists) {
                        $this->userRepo->update($user->id, ['email' => $email]);
                        $user->refresh();
                    }
                }

                $token = $user->createToken('auth_token')->plainTextToken;

                return [
                    'user' => $user,
                    'token' => $token,
                    'profile_completed' => (bool) $user->profile_completed,
                ];
            }

            $user = $email ? $this->userRepo->findByEmail($email) : null;


            if (!$user) {
                $user = $this->userRepo->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                    'profile_completed' => false,
                ]);
            }


            $this->socialRepo->linkToUser(
                $user->id,
                $provider,
                $providerId,
                $email,
                [
                    'name' => $name,
                    'avatar' => $socialUser->getAvatar(),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'profile_completed' => (bool) $user->profile_completed,
            ];
        });
    }
    // Login عادي
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
            'status' => $user->profile_completed ? 'complete' : 'profile_incomplete',
        ];
    }

    // Logout
    public function logout($user)
    {
        $user->tokens()->delete();
    }
}
