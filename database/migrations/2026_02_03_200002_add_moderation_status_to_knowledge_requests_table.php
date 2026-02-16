<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        // Add moderation fields
        Schema::table('knowledge_requests', function (Blueprint $table) {
            
            $table->foreignId('moderated_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->text('rejection_reason')->nullable()->after('moderated_at');

            $table->enum('status',['pending_moderation', 'approved', 'available', 'active', 'completed', 'rejected'])->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_requests', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['moderated_by', 'moderated_at', 'rejection_reason']);
        });

        DB::statement("ALTER TABLE knowledge_requests MODIFY COLUMN status ENUM('available', 'active', 'completed') DEFAULT 'available'");
    }
};
