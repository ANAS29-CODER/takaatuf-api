<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
      public function findByEmail(?string $email): ?User
    {
        return $email ? User::where('email', $email)->first() : null;
    }

    public function create(array $data): User
    {
        return User::create($data);
    }
    // public function updateProvider(User $user, string $provider, string $providerId): User
    // {
    //     if (!$user->oauth_provider) {
    //         $user->oauth_provider = $provider;
    //         $user->oauth_provider_id = $providerId;
    //         $user->save();
    //     }

    //     return $user;
    // }
}

?>
