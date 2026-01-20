<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\UserRepository;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileService
{
    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function updateProfile(int $userId, array $data)
    {
        return $this->userRepo->update($userId, $data);
    }


    public function getGeolocation($ip)
    {
        try {
            $reader = new Reader(storage_path('app/geoip/GeoLite2-City.mmdb'));
            // $ip = '185.34.85.10';  // مثال على IP داخل غزة
        //    $ip = '127.0.0.1';
            $record = $reader->city($ip);

            return [
                'category' => 'Match',
                'country' => $record->country->name,
                'region' => $record->mostSpecificSubdivision->name ?? 'Unknown',
                'city' => $record->city->name ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            Log::error('GeoIP Error: ' . $e->getMessage(), ['ip' => $ip]);

            return [
                'category' => 'Unknown',
                'message' => 'There was an error retrieving the location based on the IP address. Please check if the IP is valid or try again.'
            ];
        }
    }



    /**
     *compare IP-derived region with the city
     */

    public function checkLocationMatch($userCity, $ip)
    {
        $location = $this->getGeolocation($ip);

        if ($location['category'] === 'Unknown') {
            return [
                'category' => 'Unknown',
                'role' => null,
                'location' => null,
                'message' => $location['message'] ?? 'Unable to retrieve location data. Please check the IP or confirm your location manually.'
            ];
        }

        $ipRegion = strtolower($location['region']);
        $userCity = strtolower($userCity);

        if ($ipRegion !== $userCity) {
            return [
                'category' => 'Mismatch',
                'role' => null,
                'location' => $location,
                'message' => 'Your location does not match the entered city. Please confirm your location.'
            ];
        }

        $role = ($userCity === 'Gaza') ? 'Knowledge Provider' : 'Knowledge Requester';
        return [
            'category' => 'Match',
            'role' => $role,
            'location' => $location,
            'message' => 'Location matched successfully.'
        ];
    }


    public function storeAuditLog($userId, $category, $location, $userConfirmation = null)
    {
        AuditLog::create([
            'user_id' => $userId,
            'location_category' => $category,
            'location' => json_encode($location),
            'user_confirmation' => $userConfirmation,
            'created_at' => now(),
        ]);
    }
}
