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
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('status');
            $table->text('admin_notes')->nullable()->after('transaction_id');
            $table->foreignId('processed_by')->nullable()->after('admin_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('processed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn(['transaction_id', 'admin_notes', 'processed_by', 'processed_at']);
        });
    }
};
