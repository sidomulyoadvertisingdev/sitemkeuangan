<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (!Schema::hasColumn('koperasi_members', 'account_user_id')) {
                $table->foreignId('account_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index(['user_id', 'account_user_id'], 'kop_members_user_account_idx');
            }

            // member_no harus bisa null sampai admin menyetujui
            $table->string('member_no', 40)->nullable()->change();
            // join_date juga boleh null sampai disetujui
            $table->date('join_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (Schema::hasColumn('koperasi_members', 'account_user_id')) {
                $table->dropConstrainedForeignId('account_user_id');
                $table->dropIndex('kop_members_user_account_idx');
                $table->dropColumn('account_user_id');
            }

            // kembalikan seperti semula (tidak nullable)
            $table->string('member_no', 40)->nullable(false)->change();
            $table->date('join_date')->nullable(false)->change();
        });
    }
};
