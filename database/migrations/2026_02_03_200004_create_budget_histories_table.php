<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budget_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->decimal('previous_budget', 10, 2);
            $table->decimal('new_budget', 10, 2);
            $table->decimal('previous_pay_per_kp', 10, 2)->nullable();
            $table->decimal('new_pay_per_kp', 10, 2)->nullable();
            $table->enum('change_type', ['increase', 'decrease', 'partial_refund', 'full_refund']);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('knowledge_request_id');
            $table->index('admin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_histories');
    }
};
