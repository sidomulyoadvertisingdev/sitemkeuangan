<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        // Gunakan statement mentah agar tidak butuh doctrine/dbal
        DB::statement('ALTER TABLE transactions MODIFY category_id BIGINT UNSIGNED NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        DB::statement('ALTER TABLE transactions MODIFY category_id BIGINT UNSIGNED NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->cascadeOnDelete();
        });
    }
};
