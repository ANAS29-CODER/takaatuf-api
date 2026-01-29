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
    Schema::create('user_knowledge_request', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        // kp person who apply to the request
        $table->foreignId('knowledge_request_id')->constrained('knowledge_requests')->onDelete('cascade'); // the request it self
        $table->timestamps();
          $table->unique(['user_id', 'knowledge_request_id']);
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_knowledge_request');
    }
};
