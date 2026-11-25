<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 16);
            $table->date('opened_on');
            $table->bigInteger('opening_balance');
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('holder_membership_id')->nullable()->constrained('household_memberships')->restrictOnDelete();
            $table->timestamps();
            $table->index(['household_id', 'opened_on']);
        });

        Schema::create('wallet_opening_balance_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_membership_id')->constrained('household_memberships')->restrictOnDelete();
            $table->bigInteger('before_amount');
            $table->bigInteger('after_amount');
            $table->dateTime('created_at');
            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 12);
            $table->string('code', 64)->nullable()->unique();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'type', 'archived_at']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->string('type', 12);
            $table->date('occurred_on');
            $table->bigInteger('amount');
            $table->bigInteger('fee_amount')->default(0);
            $table->foreignId('source_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('creator_membership_id')->constrained('household_memberships')->restrictOnDelete();
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'occurred_on', 'deleted_at']);
            $table->index(['source_wallet_id', 'occurred_on']);
            $table->index(['destination_wallet_id', 'occurred_on']);
        });

        Schema::create('transaction_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_membership_id')->constrained('household_memberships')->restrictOnDelete();
            $table->string('action', 12);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->dateTime('created_at');
            $table->index(['transaction_id', 'created_at']);
        });

        $defaults = [
            'income' => ['salary' => 'Gaji', 'freelance' => 'Usaha atau Freelance', 'bonus' => 'Bonus atau THR',
                'gift' => 'Hadiah atau Bantuan Diterima', 'other_income' => 'Pemasukan Lainnya'],
            'expense' => ['groceries' => 'Belanja Dapur', 'dining' => 'Makan di Luar', 'housing' => 'Hunian dan Tagihan',
                'communication' => 'Komunikasi dan Internet', 'transport' => 'Transportasi', 'health' => 'Kesehatan',
                'education' => 'Pendidikan', 'children' => 'Kebutuhan Anak', 'clothing' => 'Pakaian',
                'entertainment' => 'Hiburan', 'family' => 'Bantuan Keluarga', 'zakat' => 'Zakat', 'infak' => 'Infak',
                'sedekah' => 'Sedekah', 'admin_fee' => 'Biaya Admin', 'other_expense' => 'Pengeluaran Lainnya'],
        ];

        foreach ($defaults as $type => $categories) {
            foreach ($categories as $code => $name) {
                DB::table('categories')->insert(['type' => $type, 'code' => $code, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (['transaction_revisions', 'transactions', 'categories', 'wallet_opening_balance_revisions', 'wallets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
