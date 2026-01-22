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
            // the user who create request
            $table->enum('category', [
                'Survey',
                'Essay',
                'Photo',
                'Video',
                'Errand'
            ]);
            $table->text('details');
            $table->decimal('pay_per_kp', 8, 2);
            $table->integer('number_of_kps');
            $table->decimal('review_fee', 8, 2)->default(5);
            $table->decimal('total_budget', 8, 2);
            $table->enum('neighborhood', [
                'All locations',
                'Gaza City',
                'Rimal',
                'Shujaiya',
                'Tal Al-Hawa',
                'Sheikh Radwan',
                'Al-Nasr',
                'Al Darraj',
                'Al-Tuffah',
                'Al-Sabra',
                'Al-Shati',
                'Al-Moghrarah',
                'Deir Al-Balah',
                'Al-Nusairat',
                'Al-Bureij',
                'Al-Maghazi',
                'Khan Younis',
                'Rafah'
            ]);
           $table->enum('status', ['available', 'active', 'completed'])->default('available');
            $table->integer('progress')->default(0);
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->nullable();
            $table->foreignId('updated_by')->constrained('users')->nullable();


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
