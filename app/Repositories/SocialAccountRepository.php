<?php
// app/Repositories/SocialAccountRepository.php
namespace App\Repositories;

use App\Models\SocialAccount;

class SocialAccountRepository
{
  public function findByProvider(string $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    public function linkToUser(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $email,
        array $raw = []
    ): SocialAccount {
        return SocialAccount::updateOrCreate(
            ['provider' => $provider, 'provider_user_id' => $providerUserId],
            ['user_id' => $userId, 'email' => $email, 'raw' => $raw]
        );
    }
}
?>
