<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MiomiPayService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Daftar tagihan terbaru (untuk debugging/panel sederhana). */
    public function index(): JsonResponse
    {
        $payments = Payment::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Buat tagihan baru lewat MiomiPay, lalu simpan ke database lokal.
     *
     * Request JSON: external_id (wajib), amount (wajib, rupiah),
     * customer_name, customer_email, description (opsional).
     */
    public function store(Request $request, MiomiPayService $miomiPay): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $data = $miomiPay->createPayment($validated);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $payment = Payment::create([
            'reference' => $data['reference'],
            'external_id' => $data['external_id'] ?? $validated['external_id'],
            'amount' => $data['amount'] ?? $validated['amount'],
            'final_amount' => $data['final_amount'] ?? null,
            'status' => $data['status'] ?? 'PENDING',
            'checkout_url' => $data['checkout_url'] ?? null,
            'qr_image_url' => $data['qr_image_url'] ?? null,
            'qr_string' => $data['qr_string'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'description' => $validated['description'] ?? null,
            'expired_at' => $data['expired_at'] ?? ($data['expires_at'] ?? null),
            'raw' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => $payment,
        ], 201);
    }

    /**
     * Ambil satu tagihan dan sinkronkan status terbarunya dari MiomiPay.
     */
    public function show(string $reference, MiomiPayService $miomiPay): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        try {
            $data = $miomiPay->getStatus($reference);
            $payment->update($this->statusAttributes($data));
        } catch (Exception $e) {
            // MiomiPay tidak terjangkau: kembalikan data lokal terakhir.
            return response()->json([
                'success' => true,
                'message' => 'Status lokal (gagal sinkronisasi: '.$e->getMessage().')',
                'data' => $payment->fresh(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $payment->fresh(),
        ]);
    }

    /** Cek status saja (tanpa data lengkap), untuk polling dari aplikasi. */
    public function status(string $reference, MiomiPayService $miomiPay): JsonResponse
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        try {
            $data = $miomiPay->getStatus($reference);
            $payment->update($this->statusAttributes($data));
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => ['reference' => $reference, 'status' => $payment->status],
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->fresh()->status,
                'paid_at' => $payment->fresh()->paid_at,
            ],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function statusAttributes(array $data): array
    {
        $attributes = [
            'status' => $data['status'] ?? 'PENDING',
            'raw' => $data,
        ];

        if (isset($data['final_amount'])) {
            $attributes['final_amount'] = $data['final_amount'];
        }
        if (! empty($data['paid_at'])) {
            $attributes['paid_at'] = $data['paid_at'];
        }
        if (! empty($data['expired_at'])) {
            $attributes['expired_at'] = $data['expired_at'];
        }

        return $attributes;
    }
}
