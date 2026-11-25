<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_system_admin')->default(false));
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 120);
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->timestamps();
        });
        DB::table('system_settings')->insert(['id' => 1, 'app_name' => 'Cashflow Rumah Tangga Muslim', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_system_admin'));
    }
};
