<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_installments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->after('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->after('bank_account_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('debt_installments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['bank_account_id', 'category_id']);
        });
    }
};
