<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MiomiPay API
    |--------------------------------------------------------------------------
    |
    | Konfigurasi Payment Gateway MiomiPay. Dokumentasi resmi:
    | https://pg.miomidev.com/docs (API v1.0, terverifikasi 2026-11-14).
    |
    | API key HANYA boleh di file .env di server (jangan commit .env dan
    | jangan taruh key di kode aplikasi Flutter). Pakai key sandbox
    | (awalan MP-SB-) untuk tugas ini, bukan key live (MP-LIVE-).
    |
    */

    'base_url' => env('MIOMIPAY_BASE_URL', 'https://pg.miomidev.com/api/v1'),

    'api_key' => env('MIOMIPAY_API_KEY', ''),

    'webhook_secret' => env('MIOMIPAY_WEBHOOK_SECRET', ''),

    'timeout' => (int) env('MIOMIPAY_TIMEOUT', 30),
];
