<?php

namespace App\Services;
use App\Repositories\UserRepository;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\DB;

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


    public function getGeolocation($ip)
    {

        $reader = new Reader(storage_path('GeoLite2-City.mmdb'));
        $record = $reader->city($ip);

        return [
            'country' => $record->country->name,
            'region' => $record->mostSpecificSubdivision->name,
            'city' => $record->city->name,
        ];
    }

    public function assignRoleBasedOnLocation($ip)
{

    $location = $this->getGeolocation($ip);

    if ($location['region'] == 'Gaza') {
        return 'Knowledge Provider';
    } else {
        return 'Knowledge Requester';
}
}


    // public function assignRoleBasedOnLocation($city)
    // {
    //     return $city == "Gaza" ? 'Knowledge Provider' : 'Knowledge Requester';
    // }

    public function storeAuditLog($userId, $locationCategory, $location, $userConfirmation = null)
{
    // تخزين السجل في جدول audit_logs
    DB::table('audit_logs')->insert([
        'user_id' => $userId,
        'location_category' => $locationCategory,
        'location' => json_encode($location),
        'user_confirmation' => $userConfirmation,
        'created_at' => now(),
    ]);
}

}
?>
