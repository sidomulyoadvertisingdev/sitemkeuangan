<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('data_owner_user_id')
                ->nullable()
                ->after('approved_by')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::statement('UPDATE users SET data_owner_user_id = id WHERE data_owner_user_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['data_owner_user_id']);
            $table->dropColumn('data_owner_user_id');
        });
    }
};
