<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Models\Payout;
use App\Models\User;
use App\Repositories\EarningRepository;
use App\Repositories\PayoutRepository;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayoutService $payoutService;
    protected User $knowledgeProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payoutService = app(PayoutService::class);

        $this->knowledgeProvider = User::factory()->create([
            'role' => 'Knowledge Provider',
            'wallet_type' => 'ethereum',
            'wallet_address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f89012',
            'profile_completed' => true,
        ]);
    }

    public function test_current_earnings_returns_zero_when_no_earnings(): void
    {
        $earnings = $this->payoutService->getCurrentEarnings($this->knowledgeProvider->id);

        $this->assertEquals(0.00, $earnings);
    }

    public function test_current_earnings_returns_sum_of_all_earnings_when_no_payouts(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 25.00,
        ]);

        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 15.50,
        ]);

        $earnings = $this->payoutService->getCurrentEarnings($this->knowledgeProvider->id);

        $this->assertEquals(40.50, $earnings);
    }

    public function test_current_earnings_only_includes_earnings_after_last_completed_payout(): void
    {
        // Old earning before payout
        $oldEarning = Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'created_at' => now()->subDays(10),
        ]);

        // Completed payout
        $payout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'status' => Payout::STATUS_COMPLETED,
            'payout_at' => now()->subDays(5),
        ]);

        // New earnings after payout
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 20.00,
            'created_at' => now()->subDays(2),
        ]);

        $earnings = $this->payoutService->getCurrentEarnings($this->knowledgeProvider->id);

        $this->assertEquals(20.00, $earnings);
    }

    public function test_can_request_payout_returns_false_when_below_minimum(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 25.00,
        ]);

        $result = $this->payoutService->canRequestPayout($this->knowledgeProvider->id);

        $this->assertFalse($result['can_request']);
        $this->assertStringContainsString('minimum of $30.00', $result['reason']);
    }

    public function test_can_request_payout_returns_true_when_at_minimum(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 30.00,
        ]);

        $result = $this->payoutService->canRequestPayout($this->knowledgeProvider->id);

        $this->assertTrue($result['can_request']);
        $this->assertNull($result['reason']);
    }

    public function test_can_request_payout_returns_false_when_pending_payout_exists(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
        ]);

        Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 30.00,
            'status' => Payout::STATUS_PENDING,
        ]);

        $result = $this->payoutService->canRequestPayout($this->knowledgeProvider->id);

        $this->assertFalse($result['can_request']);
        $this->assertStringContainsString('pending payout request', $result['reason']);
    }

    public function test_request_payout_creates_payout_record(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
        ]);

        $result = $this->payoutService->requestPayout($this->knowledgeProvider);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['payout']);
        $this->assertEquals(50.00, $result['payout']->amount);
        $this->assertEquals(Payout::STATUS_PENDING, $result['payout']->status);
        $this->assertEquals($this->knowledgeProvider->wallet_address, $result['payout']->wallet_address);
    }

    public function test_request_payout_fails_when_below_minimum(): void
    {
        Earning::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 20.00,
        ]);

        $result = $this->payoutService->requestPayout($this->knowledgeProvider);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('minimum', $result['message']);
    }

    public function test_request_payout_fails_when_no_wallet_configured(): void
    {
        $userNoWallet = User::factory()->create([
            'role' => 'Knowledge Provider',
            'wallet_type' => null,
            'wallet_address' => null,
            'profile_completed' => true,
        ]);

        Earning::factory()->create([
            'user_id' => $userNoWallet->id,
            'amount' => 50.00,
        ]);

        $result = $this->payoutService->requestPayout($userNoWallet);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('wallet address', $result['message']);
    }

    public function test_get_total_payouts_amount_returns_sum_of_completed_payouts(): void
    {
        Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
            'status' => Payout::STATUS_COMPLETED,
        ]);

        Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 75.00,
            'status' => Payout::STATUS_COMPLETED,
        ]);

        // Pending payout should not be included
        Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 100.00,
            'status' => Payout::STATUS_PENDING,
        ]);

        $total = $this->payoutService->getTotalPayoutsAmount($this->knowledgeProvider->id);

        $this->assertEquals(125.00, $total);
    }

    public function test_get_payout_for_user_returns_null_for_other_users_payout(): void
    {
        $otherUser = User::factory()->create([
            'role' => 'Knowledge Provider',
        ]);

        $payout = Payout::factory()->create([
            'user_id' => $otherUser->id,
            'amount' => 50.00,
        ]);

        $result = $this->payoutService->getPayoutForUser($payout->id, $this->knowledgeProvider->id);

        $this->assertNull($result);
    }

    public function test_get_payout_for_user_returns_payout_for_correct_user(): void
    {
        $payout = Payout::factory()->create([
            'user_id' => $this->knowledgeProvider->id,
            'amount' => 50.00,
        ]);

        $result = $this->payoutService->getPayoutForUser($payout->id, $this->knowledgeProvider->id);

        $this->assertNotNull($result);
        $this->assertEquals($payout->id, $result->id);
    }
}
