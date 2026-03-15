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
        Schema::table('knowledge_requests', function (Blueprint $table) {
            $table->enum('status', [
                'pending_payment',
                'pending_moderation',
                'approved',
                'available',
                'active',
                'completed',
                'rejected',
            ])->default('pending_payment')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_requests', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'available',
                'active',
                'completed',
                'rejected',
            ])->default('pending')->change();
        });
    }
};
