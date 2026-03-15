<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('knowledge_request_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('system_fee', 10, 2);
            $table->decimal('payment_fee', 10, 2);
            $table->decimal('total', 10, 2);

            $table->string('paypal_order_id')->nullable()->index();
            $table->string('paypal_capture_id')->nullable();
            $table->string('reference_id')->unique();
            $table->string('idempotency_key')->unique();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])
                ->default('pending');
            $table->text('failure_reason')->nullable();
            $table->string('payer_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
