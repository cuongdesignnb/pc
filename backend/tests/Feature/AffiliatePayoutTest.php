<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\User;
use App\Services\Affiliate\PayoutService;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class AffiliatePayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_ctv_withdraws_the_balance_and_staff_mark_it_transferred(): void
    {
        $affiliate = $this->affiliate();
        $this->commission($affiliate, 300000, 'approved');
        $this->commission($affiliate, 500000, 'pending');
        Sanctum::actingAs($affiliate->user);

        $this->postJson('/api/v1/affiliate/me/payouts')->assertCreated()->assertJsonPath('payout.amount', 300000);
        $this->postJson('/api/v1/affiliate/me/payouts')->assertUnprocessable();
        $this->getJson('/api/v1/affiliate/me')
            ->assertJsonPath('wallet.available', 0)
            ->assertJsonPath('wallet.payout_pending', 300000)
            ->assertJsonPath('payouts.0.status', 'pending');

        $payout = AffiliatePayout::firstOrFail();
        $this->assertSame('0011', $payout->bank_account_number);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/affiliates/payouts')->assertOk();
        $this->actingAs($admin)->post("/admin/affiliates/payouts/{$payout->id}/paid", ['note' => 'FT123'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame($admin->id, $payout->fresh()->paid_by);

        Sanctum::actingAs($affiliate->user);
        $this->getJson('/api/v1/affiliate/me')
            ->assertJsonPath('wallet.available', 0)
            ->assertJsonPath('wallet.withdrawn', 300000)
            ->assertJsonPath('payouts.0.status', 'paid');
    }

    public function test_cancelled_payout_returns_the_money_and_small_balances_wait(): void
    {
        $affiliate = $this->affiliate();
        $this->commission($affiliate, 50000, 'approved');
        Sanctum::actingAs($affiliate->user);
        $this->postJson('/api/v1/affiliate/me/payouts')->assertUnprocessable();

        $this->commission($affiliate, 100000, 'approved');
        $this->postJson('/api/v1/affiliate/me/payouts')->assertCreated()->assertJsonPath('payout.amount', 150000);
        $payout = AffiliatePayout::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post("/admin/affiliates/payouts/{$payout->id}/cancel", ['reason' => 'Sai số tài khoản'])
            ->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $payout->fresh()->status);
        Sanctum::actingAs($affiliate->user);
        $this->getJson('/api/v1/affiliate/me')->assertJsonPath('wallet.available', 150000);
    }

    public function test_payouts_are_created_on_the_ctv_payout_days(): void
    {
        $due = $this->affiliate(['payout_days' => [10, 25]]);
        $this->commission($due, 200000, 'approved');
        $notToday = $this->affiliate(['payout_days' => [11]]);
        $this->commission($notToday, 200000, 'approved');

        $this->assertSame(1, app(PayoutService::class)->createScheduled(now()->setDay(10)));
        $this->assertSame(1, AffiliatePayout::where('affiliate_id', $due->id)->where('source', 'schedule')->count());
        $this->assertSame(0, AffiliatePayout::where('affiliate_id', $notToday->id)->count());
        // A request still waiting is not doubled.
        $this->assertSame(0, app(PayoutService::class)->createScheduled(now()->setDay(25)));
    }

    public function test_payouts_export_to_excel(): void
    {
        $affiliate = $this->affiliate();
        $this->commission($affiliate, 400000, 'approved');
        app(PayoutService::class)->request($affiliate);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/affiliates/payouts/export?status=pending')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('Số tài khoản', $sheet);
        $this->assertStringContainsString($affiliate->code, $sheet);
        $this->assertStringContainsString('<v>400000</v>', $sheet);
        $this->assertStringContainsString('Chờ chuyển', $sheet);
    }

    public function test_xlsx_writer_escapes_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        SimpleXlsx::write($path, ['A'], [['<b>&"x"</b>'], [12]]);
        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('&lt;b&gt;&amp;&quot;x&quot;&lt;/b&gt;', $sheet);
        $this->assertStringContainsString('<c r="A3" s="2"><v>12</v></c>', $sheet);
    }

    private function affiliate(array $overrides = []): Affiliate
    {
        $user = User::factory()->create(['role' => 'customer']);

        return Affiliate::create($overrides + [
            'user_id' => $user->id, 'code' => Affiliate::nextCode(), 'phone' => '09'.random_int(10000000, 99999999),
            'bank_name' => 'VCB', 'bank_account_number' => '0011', 'bank_account_name' => 'NGUYEN VAN A',
            'status' => 'active', 'approved_at' => now(),
        ])->load('user');
    }

    private function commission(Affiliate $affiliate, int $amount, string $status): void
    {
        AffiliateCommission::create([
            'affiliate_id' => $affiliate->id, 'base_amount' => $amount * 10, 'amount' => $amount, 'status' => $status,
        ]);
    }
}
