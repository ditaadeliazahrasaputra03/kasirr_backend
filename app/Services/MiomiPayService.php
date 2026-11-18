<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP ke API MiomiPay (dokumentasi: https://pg.miomidev.com/docs).
 *
 * - Buat tagihan: POST /api/v1/payment/create
 * - Cek status:   GET  /api/v1/payment/{reference}/status
 * - Auth: header Authorization: Bearer <API_KEY>
 */
class MiomiPayService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('miomipay.base_url'), '/');
        $this->apiKey = (string) config('miomipay.api_key');
        $this->timeout = (int) config('miomipay.timeout', 30);
    }

    /**
     * Buat tagihan QRIS baru. Mengembalikan array `data` dari MiomiPay.
     *
     * @param  array{external_id: string, amount: int, customer_name?: string, customer_email?: string, description?: string}  $payload
     */
    public function createPayment(array $payload): array
    {
        $response = $this->client()->post("{$this->baseUrl}/payment/create", $payload);

        return $this->dataOrFail($response, 'membuat tagihan');
    }

    /** Cek status tagihan. Mengembalikan array `data` dari MiomiPay. */
    public function getStatus(string $reference): array
    {
        $response = $this->client()->get("{$this->baseUrl}/payment/{$reference}/status");

        return $this->dataOrFail($response, 'mengecek status');
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        if ($this->apiKey === '') {
            throw new Exception('MIOMIPAY_API_KEY belum diisi di file .env backend.');
        }

        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /** @return array<string, mixed> */
    protected function dataOrFail(Response $response, string $aksi): array
    {
        if (! $response->successful()) {
            throw new Exception(
                "MiomiPay gagal {$aksi} (HTTP {$response->status()}): {$response->body()}"
            );
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new Exception("Respons MiomiPay saat {$aksi} tidak berisi data: {$response->body()}");
        }

        return $data;
    }
}
