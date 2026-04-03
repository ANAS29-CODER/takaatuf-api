<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Change ENUM → TEXT (temporary)
        Schema::table('knowledge_requests', function (Blueprint $table) {
            $table->text('neighborhood')->change();
        });

        // Step 2: Convert values to JSON
        DB::statement("
        UPDATE knowledge_requests
        SET neighborhood = JSON_ARRAY(neighborhood)
    ");

        // Step 3: Change TEXT → JSON
        Schema::table('knowledge_requests', function (Blueprint $table) {
            $table->json('neighborhood')->change();
        });
    }
    public function down(): void
    {
        // Step 1: Convert JSON → string
        DB::statement("
        UPDATE knowledge_requests
        SET neighborhood = JSON_UNQUOTE(JSON_EXTRACT(neighborhood, '$[0]'))
    ");

        // Step 2: Change JSON → ENUM
        Schema::table('knowledge_requests', function (Blueprint $table) {
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
                'Rafah',
            ])->change();
        });
    }
};
