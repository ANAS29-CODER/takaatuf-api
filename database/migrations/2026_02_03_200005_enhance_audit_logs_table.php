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
        Schema::table('audit_logs', function (Blueprint $table) {

            $table->string('action')->after('user_id');
            $table->string('model_type')->nullable()->after('action'); // e.g., 'knowledge_request', 'payout'
            $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
            $table->json('old_values')->nullable()->after('model_id');
            $table->json('new_values')->nullable()->after('old_values');
            $table->string('ip_address', 45)->nullable()->after('new_values');
            $table->text('user_agent')->nullable()->after('ip_address');

            

            // Add indexes
            $table->index('action');
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn([
                'action',
                'model_type',
                'model_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent'
            ]);
        });
    }
};
