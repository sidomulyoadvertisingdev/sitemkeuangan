<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->enum('account_kind', ['general', 'officer_wallet'])
                ->default('general')
                ->after('user_id');
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('account_kind')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['user_id', 'account_kind']);
            $table->index(['owner_user_id', 'account_kind']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'account_kind']);
            $table->dropIndex(['owner_user_id', 'account_kind']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('account_kind');
        });
    }
};

