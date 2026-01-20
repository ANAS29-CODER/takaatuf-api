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
        Schema::create('knowledge_requests', function (Blueprint $table) {
            $table->id();

    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    $table->enum('category', [
        'survey',
        'essay',
        'photos',
        'videos',
        'errand'
    ]);

    $table->text('details');

    $table->decimal('pay_per_kp', 10, 2);
    $table->unsignedInteger('number_of_providers');

    $table->decimal('total_budget', 10, 2); // (pay_per_kp * providers) + 5

    $table->string('neighborhood');

    $table->enum('status', ['pending', 'paid', 'assigned', 'completed', 'cancelled'])
          ->default('pending');

    $table->timestamps();
         
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_requests');
    }
};
