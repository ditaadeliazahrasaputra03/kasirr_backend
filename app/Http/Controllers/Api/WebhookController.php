<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Menerima notifikasi webhook MiomiPay (event payment.success / expired).
 *
 * Mengikuti dokumentasi https://pg.miomidev.com/docs: MiomiPay mengirim
 * header X-MiomiPay-Signature berisi HMAC-SHA256 hex dari raw body JSON
 * memakai webhook secret proyek. Signature WAJIB diverifikasi sebelum
 * status pesanan diubah.
 *
 * Catatan: webhook dari server MiomiPay hanya sampai kalau URL backend
 * bisa diakses publik (bukan localhost). Untuk tugas ini, alur utama
 * aplikasi adalah polling GET /api/payments/{reference}/status; webhook
 * ini pelengkap untuk deployment yang URL-nya publik.
 */
class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-MiomiPay-Signature', '');
        $secret = (string) config('miomipay.webhook_secret');

        if ($secret !== '') {
            $expected = hash_hmac('sha256', $rawBody, $secret);
            if ($signature === '' || ! hash_equals($expected, $signature)) {
                Log::warning('MiomiPay webhook: signature tidak valid.', [
                    'event' => $request->header('X-MiomiPay-Event'),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature',
                ], 401);
            }
        } else {
            Log::warning(
                'MiomiPay webhook diterima TANPA verifikasi signature '.
                '(MIOMIPAY_WEBHOOK_SECRET belum diisi). Isi secret-nya '.
                'sebelum dipakai di lingkungan publik.'
            );
        }

        $payload = $request->json()->all();
        $data = $payload['data'] ?? $payload;
        $reference = $data['reference'] ?? null;
        $status = $data['status'] ?? null;

        if (! $reference || ! $status) {
            return response()->json([
                'success' => false,
                'message' => 'Payload webhook tidak berisi reference/status.',
            ], 422);
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            Log::warning('MiomiPay webhook untuk reference yang tidak dikenal.', [
                'reference' => $reference,
            ]);

            return response()->json(['received' => true]);
        }

        $attributes = ['status' => $status, 'raw' => $data];
        if (strtoupper((string) $status) === 'PAID') {
            $attributes['paid_at'] = $data['paid_at'] ?? now();
        }
        $payment->update($attributes);

        Log::info('MiomiPay webhook diproses.', [
            'reference' => $reference,
            'status' => $status,
            'event' => $request->header('X-MiomiPay-Event'),
        ]);

        return response()->json(['received' => true]);
    }
}
