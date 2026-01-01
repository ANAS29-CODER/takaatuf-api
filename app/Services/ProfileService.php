<?php

namespace App\Services;
use App\Repositories\UserRepository;

class ProfileService
{
    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function updateProfile(int $userId, array $data)
    {
        $user = $this->userRepo->getById($userId);
        return $this->userRepo->update($user->id, $data);
    }

    public function assignRoleBasedOnLocation($city)
    {
        return $city == "Gaza" ? 'Knowledge Provider' : 'Knowledge Requester';
    }
}
?>
