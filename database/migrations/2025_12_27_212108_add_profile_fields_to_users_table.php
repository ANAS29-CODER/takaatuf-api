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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('profile_completed'); // kr|kp|admin (admin backend فقط)
            $table->string('city_neighborhood')->nullable()->after('role');
            $table->string('wallet_type')->nullable()->after('city_neighborhood');   // ethereum|solana|bitcoin...
            $table->string('wallet_address')->nullable()->after('wallet_type');
            $table->string('paypal_account')->nullable()->after('wallet_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'city_neighborhood',
                'wallet_type',
                'wallet_address',
                'paypal_account',
            ]);
        });
    }
};
