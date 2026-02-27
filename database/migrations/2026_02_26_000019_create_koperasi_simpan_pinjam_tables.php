<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('koperasi_members')) {
            Schema::create('koperasi_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('member_no', 40);
                $table->string('name', 120);
                $table->string('nik', 30)->nullable();
                $table->enum('gender', ['L', 'P'])->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->date('join_date');
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('koperasi_savings')) {
            Schema::create('koperasi_savings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('koperasi_member_id')->constrained('koperasi_members')->cascadeOnDelete();
                $table->enum('type', ['pokok', 'wajib', 'sukarela']);
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('koperasi_loans')) {
            Schema::create('koperasi_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('koperasi_member_id')->constrained('koperasi_members')->cascadeOnDelete();
                $table->string('loan_no', 50);
                $table->decimal('principal_amount', 15, 2);
                $table->decimal('interest_percent', 5, 2)->default(0);
                $table->decimal('admin_fee', 15, 2)->default(0);
                $table->unsignedSmallInteger('tenor_months')->default(1);
                $table->date('disbursed_at');
                $table->date('due_date')->nullable();
                $table->enum('status', ['berjalan', 'lunas', 'macet'])->default('berjalan');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('koperasi_loan_installments')) {
            Schema::create('koperasi_loan_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('koperasi_loan_id')->constrained('koperasi_loans')->cascadeOnDelete();
                $table->unsignedInteger('installment_no');
                $table->decimal('amount_principal', 15, 2)->default(0);
                $table->decimal('amount_interest', 15, 2)->default(0);
                $table->decimal('amount_penalty', 15, 2)->default(0);
                $table->date('paid_at');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        $this->ensureUnique('koperasi_members', ['user_id', 'member_no'], 'kop_members_user_member_uq');
        $this->ensureIndex('koperasi_members', ['user_id', 'name'], 'kop_members_user_name_idx');
        $this->ensureIndex('koperasi_savings', ['koperasi_member_id', 'transaction_date'], 'kop_savings_member_date_idx');
        $this->ensureUnique('koperasi_loans', ['koperasi_member_id', 'loan_no'], 'kop_loans_member_loanno_uq');
        $this->ensureIndex('koperasi_loans', ['koperasi_member_id', 'status'], 'kop_loans_member_status_idx');
        $this->ensureUnique('koperasi_loan_installments', ['koperasi_loan_id', 'installment_no'], 'kop_loan_inst_loan_instno_uq');
        $this->ensureIndex('koperasi_loan_installments', ['koperasi_loan_id', 'paid_at'], 'kop_loan_inst_loan_paid_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('koperasi_loan_installments');
        Schema::dropIfExists('koperasi_loans');
        Schema::dropIfExists('koperasi_savings');
        Schema::dropIfExists('koperasi_members');
    }

    private function ensureUnique(string $table, array $columns, string $name): void
    {
        if ($this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->unique($columns, $name);
        });
    }

    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if ($this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$name]
        );

        return !empty($rows);
    }
};
