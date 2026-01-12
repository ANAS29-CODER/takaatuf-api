<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function findByEmail(?string $email): ?User
    {
        return $email ? User::firstWhere('email', $email) : null;
    }


    public function create(array $data): User
    {

        return User::create($data);
    }
    public function update(int $userId, array $data): ?User
    {
        $user = User::find($userId);
        if (!$user) {
            return null;
        }
        $user->update($data);
        return $user;
    }

    public function getById(int $userId): User
    {
        return User::findOrFail($userId);
    }
}

?>
