<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
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
            $ip= '185.34.86.15';
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

    public function validateWalletAddress(string $type, string $address): array
    {
        $patterns = [
            'ethereum' => '/^0x[a-fA-F0-9]{40}$/',
            'solana' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
            'bitcoin' => '/^(1|3)[a-km-zA-HJ-NP-Z1-9]{25,34}$|^bc1[a-z0-9]{39,59}$/',
        ];

        if (!isset($patterns[$type])) {
            return [
                'valid' => false,
                'message' => 'Invalid wallet type. Supported types: ethereum, solana, bitcoin.',
            ];
        }

        if (!preg_match($patterns[$type], $address)) {
            $typeLabels = [
                'ethereum' => 'Ethereum (must start with 0x followed by 40 hex characters)',
                'solana' => 'Solana (32-44 base58 characters)',
                'bitcoin' => 'Bitcoin (starts with 1, 3, or bc1)',
            ];

            return [
                'valid' => false,
                'message' => sprintf('Invalid %s address format.', $typeLabels[$type]),
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }

    public function updateWallet(int $userId, string $walletType, string $walletAddress): array
    {
        $validation = $this->validateWalletAddress($walletType, $walletAddress);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        $user = $this->userRepo->update($userId, [
            'wallet_type' => $walletType,
            'wallet_address' => $walletAddress,
        ]);

        return [
            'success' => true,
            'message' => 'Wallet address updated successfully.',
            'user' => $user,
        ];
    }

    /**
     * Update user's working location
     */
    public function updateWorkingLocation(int $userId, string $cityNeighborhood): array
    {
        $user = $this->userRepo->update($userId, [
            'city_neighborhood' => $cityNeighborhood,
        ]);

        return [
            'success' => true,
            'message' => 'Working location updated successfully.',
            'user' => $user,
        ];
    }

public function isProfileCompleted(User $user): bool
{
    if (
        empty($user->full_name) ||
        empty($user->city_neighborhood)||
        empty($user->role)
    ) {
        return false;
    }
    if ($user->role === 'Knowledge Provider') {
        return $user->wallets()->exists();
    }
    if ($user->role === 'Knowledge Requester') {
        return !empty($user->paypal_account);
    }

    return false;
}


}
