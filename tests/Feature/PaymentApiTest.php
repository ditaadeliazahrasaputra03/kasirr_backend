<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'miomipay.base_url' => 'https://pg.miomidev.com/api/v1',
            'miomipay.api_key' => 'MP-SB-test-key',
            'miomipay.webhook_secret' => 'whsec_test_secret',
        ]);
    }

    protected function fakeMiomiPayResponse(array $overrides = []): array
    {
        return [
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => array_merge([
                'reference' => 'MP-INV-20261114-TEST01',
                'external_id' => 'POS-123',
                'environment' => 'sandbox',
                'amount' => 50000,
                'final_amount' => 50000,
                'status' => 'PENDING',
                'qr_string' => '00020101021226660014ID.CO.GOPAY.WWW',
                'qr_image_url' => 'https://pg.miomidev.com/pay/snap_test/qr.png',
                'checkout_url' => 'https://pg.miomidev.com/pay/snap_test',
                'expired_at' => '2026-11-14T13:40:44+07:00',
            ], $overrides),
        ];
    }

    public function test_health_endpoint_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_create_payment_calls_miomipay_and_stores_record(): void
    {
        Http::fake([
            'pg.miomidev.com/*' => Http::response($this->fakeMiomiPayResponse(), 201),
        ]);

        $response = $this->postJson('/api/payments', [
            'external_id' => 'POS-123',
            'amount' => 50000,
            'customer_name' => 'Budi Santoso',
            'description' => 'Pembayaran POS: Nasi Goreng x2',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reference', 'MP-INV-20261114-TEST01')
            ->assertJsonPath('data.status', 'PENDING');

        $this->assertDatabaseHas('payments', [
            'reference' => 'MP-INV-20261114-TEST01',
            'external_id' => 'POS-123',
            'amount' => 50000,
            'status' => 'PENDING',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://pg.miomidev.com/api/v1/payment/create'
                && $request->header('Authorization')[0] === 'Bearer MP-SB-test-key'
                && $request->data()['amount'] === 50000;
        });
    }

    public function test_create_payment_validation_fails_without_amount(): void
    {
        $this->postJson('/api/payments', ['external_id' => 'POS-123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_show_payment_syncs_status_from_miomipay(): void
    {
        $payment = Payment::create([
            'reference' => 'MP-INV-20261114-TEST01',
            'external_id' => 'POS-123',
            'amount' => 50000,
            'status' => 'PENDING',
        ]);

        Http::fake([
            'pg.miomidev.com/*' => Http::response(
                $this->fakeMiomiPayResponse([
                    'status' => 'PAID',
                    'paid_at' => '2026-11-14T13:30:00+07:00',
                ]),
                200
            ),
        ]);

        $this->getJson('/api/payments/MP-INV-20261114-TEST01')
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_webhook_with_valid_signature_updates_status(): void
    {
        $payment = Payment::create([
            'reference' => 'MP-INV-20261114-TEST01',
            'external_id' => 'POS-123',
            'amount' => 50000,
            'status' => 'PENDING',
        ]);

        $payload = [
            'data' => [
                'reference' => 'MP-INV-20261114-TEST01',
                'status' => 'PAID',
                'paid_at' => '2026-11-14T13:30:00+07:00',
            ],
        ];
        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, 'whsec_test_secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/miomipay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MIOMIPAY_EVENT' => 'payment.success',
                'HTTP_X_MIOMIPAY_SIGNATURE' => $signature,
            ],
            $rawBody
        );

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertSame('PAID', $payment->fresh()->status);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        Payment::create([
            'reference' => 'MP-INV-20261114-TEST01',
            'external_id' => 'POS-123',
            'amount' => 50000,
            'status' => 'PENDING',
        ]);

        $rawBody = json_encode([
            'data' => ['reference' => 'MP-INV-20261114-TEST01', 'status' => 'PAID'],
        ]);

        $response = $this->call(
            'POST',
            '/api/webhooks/miomipay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_MIOMIPAY_SIGNATURE' => 'signature-palsu',
            ],
            $rawBody
        );

        $response->assertUnauthorized();
        $this->assertSame(
            'PENDING',
            Payment::where('reference', 'MP-INV-20261114-TEST01')->first()->status
        );
    }
}
