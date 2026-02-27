<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koperasi_loan_installments', function (Blueprint $table) {
            if (!Schema::hasColumn('koperasi_loan_installments', 'expected_amount')) {
                $table->decimal('expected_amount', 15, 2)->default(0)->after('installment_no');
            }

            if (!Schema::hasColumn('koperasi_loan_installments', 'payment_status')) {
                $table->enum('payment_status', ['sesuai', 'kurang_bayar', 'lebih_bayar'])
                    ->default('sesuai')
                    ->after('amount_penalty');
            }

            if (!Schema::hasColumn('koperasi_loan_installments', 'shortfall_amount')) {
                $table->decimal('shortfall_amount', 15, 2)->default(0)->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('koperasi_loan_installments', function (Blueprint $table) {
            if (Schema::hasColumn('koperasi_loan_installments', 'shortfall_amount')) {
                $table->dropColumn('shortfall_amount');
            }

            if (Schema::hasColumn('koperasi_loan_installments', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('koperasi_loan_installments', 'expected_amount')) {
                $table->dropColumn('expected_amount');
            }
        });
    }
};
