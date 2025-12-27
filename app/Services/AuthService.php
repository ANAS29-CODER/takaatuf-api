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
        $providerId = (string) $socialUser->getId();
        $email      = $socialUser->getEmail(); // ممكن null
       $name       = $socialUser->getName() ?? $socialUser->getNickname() ?? 'New User';

            if (!$email) {
    throw new \Exception('Email not provided by OAuth provider. Please use a different login method.');
}


        $result = DB::transaction(function () use ($provider, $providerId, $email, $name, $socialUser) {

            // 1) لو الحساب مربوط من قبل (provider + provider_user_id)
            $social = $this->socialRepo->findByProvider($provider, $providerId);
            if ($social) {
                $user = $social->user; // لازم علاقة user في موديل SocialAccount
                $token = $user->createToken('auth_token')->plainTextToken;

                return [
                    'user' => $user,
                    'token' => $token,
                    'profile_completed' => (bool) $user->profile_completed,
                ];
            }

            // 2) لو مش مربوط: جرّبي تلاقي user بالإيميل (إذا موجود)
            $user = $this->userRepo->findByEmail($email);

            // 3) إذا ما في user: أنشئي واحد جديد
            if (!$user) {
                $user = $this->userRepo->create([
                    'name' => $name,
                    'email' => $email, // ممكن null (لازم يكون nullable بجدول users)
                    'password' => bcrypt(Str::random(32)),
                    'profile_completed' => false,
                ]);
            }

            // 4) اربطي social_account بالمستخدم
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

            // 5) token
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token,
                'profile_completed' => (bool) $user->profile_completed,
            ];
        });

        return $result;
    }

      // Login عادي
    public function loginWithEmail($email, $password) {
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
    public function logout($user) {
        $user->tokens()->delete();
    }
}
