<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('external_id')->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('final_amount')->nullable();
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('checkout_url', 500)->nullable();
            $table->string('qr_image_url', 500)->nullable();
            $table->text('qr_string')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
