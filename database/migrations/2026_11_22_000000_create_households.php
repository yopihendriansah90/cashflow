<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('household_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['owner', 'partner']);
            $table->dateTime('ended_at')->nullable();
            $table->unsignedBigInteger('active_user_id')
                ->virtualAs('CASE WHEN ended_at IS NULL THEN user_id END')->unique();
            $table->string('active_role', 10)
                ->virtualAs('CASE WHEN ended_at IS NULL THEN role END');
            $table->unique(['household_id', 'active_role'], 'household_active_role_unique');
            $table->index(['household_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('household_join_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_membership_id')->constrained('household_memberships')->restrictOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->index(['household_id', 'revoked_at', 'used_at'], 'household_join_codes_pending_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_join_codes');
        Schema::dropIfExists('household_memberships');
        Schema::dropIfExists('households');
    }
};
