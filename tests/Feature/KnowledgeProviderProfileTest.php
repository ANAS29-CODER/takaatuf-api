<?php

namespace Tests\Feature;

use App\Models\Earning;
use App\Models\Payout;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KnowledgeProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $knowledgeProvider;
    protected User $knowledgeRequester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->knowledgeProvider = User::factory()->create([
            'full_name' => 'Test Provider',
            'role' => 'Knowledge Provider',
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f89012',
            'city_neighborhood' => 'Gaza City',
            'profile_completed' => true,
        ]);

        $this->knowledgeRequester = User::factory()->create([
            'full_name' => 'Test Requester',
            'role' => 'Knowledge Requester',
            'paypal_account' => 'test@paypal.com',
            'city_neighborhood' => 'New York',
            'profile_completed' => true,
        ]);
    }

    // Profile Access Tests

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_knowledge_provider_can_access_profile(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'city_neighborhood',
                'profile_completed',
                'role',
                'wallet_address',
                'wallet_type',
                'current_earnings',
                'can_request_payout',
                'total_historical_payouts',
            ]);
    }

    public function test_profile_shows_payout_minimum_message_when_below_threshold(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 20.00,
        ]);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'can_request_payout' => false,
            ])
            ->assertJsonStructure(['payout_minimum_message']);
    }

    public function test_profile_shows_can_request_payout_when_above_threshold(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
        ]);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'can_request_payout' => true,
            ]);
    }

    // Wallet Update Tests

    public function test_knowledge_provider_can_update_wallet_with_valid_ethereum_address(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->putJson('/api/profile/wallet', [
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x1234567890AbCdEf1234567890aBcDeF12345678',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Wallet address updated successfully.',
                'wallet_type' => 'ethereum',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->knowledgeProvider->id,
            'wallet_address' => '0x1234567890AbCdEf1234567890aBcDeF12345678',
        ]);
    }

    public function test_wallet_update_fails_with_invalid_ethereum_address(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->putJson('/api/profile/wallet', [
            'wallet_type' => 'ethereum',
            'wallet_address' => 'invalid_address',
        ]);

        $response->assertStatus(422);
    }

    public function test_wallet_update_fails_with_invalid_bitcoin_address(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->putJson('/api/profile/wallet', [
            'wallet_type' => 'bitcoin',
            'wallet_address' => '0x1234567890AbCdEf1234567890aBcDeF12345678',
        ]);

        $response->assertStatus(422);
    }

    public function test_knowledge_requester_cannot_update_wallet(): void
    {
        Sanctum::actingAs($this->knowledgeRequester);

        $response = $this->putJson('/api/profile/wallet', [
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x1234567890AbCdEf1234567890aBcDeF12345678',
        ]);

        $response->assertStatus(403);
    }

    // Working Location Update Tests

    public function test_user_can_update_working_location(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->putJson('/api/profile/location', [
            'city_neighborhood' => 'Rafah',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Working location updated successfully.',
                'city_neighborhood' => 'Rafah',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->knowledgeProvider->id,
            'city_neighborhood' => 'Rafah',
        ]);
    }

    public function test_working_location_update_fails_without_location(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->putJson('/api/profile/location', []);

        $response->assertStatus(422);
    }

    // Payout Request Tests

    public function test_knowledge_provider_can_request_payout_when_eligible(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
        ]);

        $response = $this->postJson('/api/payouts/request');

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Payout request submitted successfully.',
            ])
            ->assertJsonStructure([
                'payout' => [
                    'id',
                    'amount',
                    'payout_date',
                    'wallet_address_last_four',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('payouts', [
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);
    }

    public function test_payout_request_fails_when_below_minimum(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 20.00,
        ]);

        $response = $this->postJson('/api/payouts/request');

        $response->assertStatus(400)
            ->assertJsonStructure(['message']);
    }

    public function test_payout_request_fails_when_pending_request_exists(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 100.00,
        ]);

        Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/payouts/request');

        $response->assertStatus(400)
            ->assertJsonStructure(['message']);
    }

    public function test_knowledge_requester_cannot_request_payout(): void
    {
        Sanctum::actingAs($this->knowledgeRequester);

        $response = $this->postJson('/api/payouts/request');

        $response->assertStatus(403);
    }

    // Payout History Tests

    public function test_knowledge_provider_can_view_payout_history(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        Payout::factory()->count(3)->create([
            'user_id' => $this->knowledgeProvider->id,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/payouts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total_historical_payouts',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_payout_history_shows_empty_message_when_no_payouts(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $response = $this->getJson('/api/payouts');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'No payouts yet.',
                'data' => [],
                'total_historical_payouts' => '0.00',
            ]);
    }

    public function test_payout_history_ordered_by_most_recent_first(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $oldPayout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 30.00,
            'created_at' => now()->subDays(5),
        ]);

        $newPayout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/payouts');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($newPayout->id, $data[0]['id']);
        $this->assertEquals($oldPayout->id, $data[1]['id']);
    }

    public function test_knowledge_requester_cannot_view_payout_history(): void
    {
        Sanctum::actingAs($this->knowledgeRequester);

        $response = $this->getJson('/api/payouts');

        $response->assertStatus(403);
    }

    // Single Payout View Tests

    public function test_knowledge_provider_can_view_single_payout(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $payout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
        ]);

        $response = $this->getJson("/api/payouts/{$payout->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'amount',
                    'payout_date',
                    'wallet_address_last_four',
                    'status',
                ],
            ]);
    }

    public function test_knowledge_provider_cannot_view_other_users_payout(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $otherUser = User::factory()->create([
            'role' => 'Knowledge Provider',
        ]);

        $payout = Payout::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/payouts/{$payout->id}");

        $response->assertStatus(404);
    }

    public function test_payout_shows_wallet_last_four_characters(): void
    {
        Sanctum::actingAs($this->knowledgeProvider);

        $payout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'wallet_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f89012',
        ]);

        $response = $this->getJson("/api/payouts/{$payout->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'wallet_address_last_four' => '9012',
                ],
            ]);
    }
}
