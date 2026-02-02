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
        Schema::table('user_knowledge_request', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'awaiting_review', 'completed', 'approved', 'rejected'])
                ->default('pending')
                ->after('knowledge_request_id');
            $table->integer('progress')->default(0)->after('status');
            $table->decimal('payout_amount', 10, 2)->nullable()->after('progress');
            $table->timestamp('completed_at')->nullable()->after('payout_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_knowledge_request', function (Blueprint $table) {
            $table->dropColumn(['status', 'progress', 'payout_amount', 'completed_at']);
        });
    }
};
