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

            // CATEGORY (required)
            $table->enum('category', [
                'Survey', 'Essay', 'Photo', 'Video', 'Errand'
            ]);

            // DETAILS (required, min 50 chars)
            $table->text('details');

            // PAY PER KP (required)
            $table->decimal('pay_per_kp', 8, 2);

            // NUMBER OF PROVIDERS (required)
            $table->integer('number_of_kps');

            // BUDGET CALCULATION
            $table->decimal('review_fee', 8, 2)->default(5);
            $table->decimal('total_budget', 8, 2);

            // NEIGHBORHOOD (required)
            $table->enum('neighborhood', [
                'All locations',
                'Gaza City', 'Rimal', 'Shujaiya', 'Tal Al-Hawa',
                'Sheikh Radwan', 'Al-Nasr', 'Al Darraj', 'Al-Tuffah',
                'Al-Sabra', 'Al-Shati', 'Al-Moghrarah',
                'Deir Al-Balah', 'Al-Nusairat', 'Al-Bureij',
                'Al-Maghazi', 'Khan Younis', 'Rafah'
            ]);

            // STATUS (pending → paid → open)
            $table->enum('status', ['pending', 'paid', 'open'])->default('pending');

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
