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
        //
        Schema::create('knowledge_request_attachments', function (Blueprint $table) {
    $table->id();

    $table->foreignId('knowledge_request_id')
          ->constrained()
          ->cascadeOnDelete();

    $table->enum('type', ['image', 'video']);
    $table->string('path');
    $table->unsignedBigInteger('size'); // bytes

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
