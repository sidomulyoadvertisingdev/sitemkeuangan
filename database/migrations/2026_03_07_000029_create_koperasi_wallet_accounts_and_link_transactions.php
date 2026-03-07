<?php

use App\Models\KoperasiWalletAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('koperasi_wallet_accounts')) {
            Schema::create('koperasi_wallet_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('wallet_type', 30)->default('custom');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'wallet_type'], 'kop_wallet_user_type_idx');
            });
        }

        if (Schema::hasTable('koperasi_savings') && !Schema::hasColumn('koperasi_savings', 'wallet_account_id')) {
            Schema::table('koperasi_savings', function (Blueprint $table) {
                $table->foreignId('wallet_account_id')
                    ->nullable()
                    ->after('koperasi_member_id')
                    ->constrained('koperasi_wallet_accounts')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('koperasi_loans') && !Schema::hasColumn('koperasi_loans', 'wallet_account_id')) {
            Schema::table('koperasi_loans', function (Blueprint $table) {
                $table->foreignId('wallet_account_id')
                    ->nullable()
                    ->after('koperasi_member_id')
                    ->constrained('koperasi_wallet_accounts')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('koperasi_loan_installments') && !Schema::hasColumn('koperasi_loan_installments', 'principal_wallet_account_id')) {
            Schema::table('koperasi_loan_installments', function (Blueprint $table) {
                $table->foreignId('principal_wallet_account_id')
                    ->nullable()
                    ->after('koperasi_loan_id')
                    ->constrained('koperasi_wallet_accounts')
                    ->nullOnDelete();
                $table->foreignId('income_wallet_account_id')
                    ->nullable()
                    ->after('principal_wallet_account_id')
                    ->constrained('koperasi_wallet_accounts')
                    ->nullOnDelete();
            });
        }

        $this->seedDefaultWalletsAndBackfill();
    }

    public function down(): void
    {
        if (Schema::hasTable('koperasi_loan_installments')) {
            Schema::table('koperasi_loan_installments', function (Blueprint $table) {
                if (Schema::hasColumn('koperasi_loan_installments', 'income_wallet_account_id')) {
                    $table->dropConstrainedForeignId('income_wallet_account_id');
                }
                if (Schema::hasColumn('koperasi_loan_installments', 'principal_wallet_account_id')) {
                    $table->dropConstrainedForeignId('principal_wallet_account_id');
                }
            });
        }

        if (Schema::hasTable('koperasi_loans') && Schema::hasColumn('koperasi_loans', 'wallet_account_id')) {
            Schema::table('koperasi_loans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_account_id');
            });
        }

        if (Schema::hasTable('koperasi_savings') && Schema::hasColumn('koperasi_savings', 'wallet_account_id')) {
            Schema::table('koperasi_savings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_account_id');
            });
        }

        Schema::dropIfExists('koperasi_wallet_accounts');
    }

    private function seedDefaultWalletsAndBackfill(): void
    {
        if (!Schema::hasTable('koperasi_members') || !Schema::hasTable('koperasi_wallet_accounts')) {
            return;
        }

        $userIds = DB::table('koperasi_members')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $walletIds = $this->ensureDefaultWalletIds((int) $userId);

            if (Schema::hasTable('koperasi_savings') && Schema::hasColumn('koperasi_savings', 'wallet_account_id')) {
                DB::table('koperasi_savings')
                    ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_savings.koperasi_member_id')
                    ->where('koperasi_members.user_id', $userId)
                    ->whereNull('koperasi_savings.wallet_account_id')
                    ->update([
                        'koperasi_savings.wallet_account_id' => $walletIds['saving'],
                    ]);
            }

            if (Schema::hasTable('koperasi_loans') && Schema::hasColumn('koperasi_loans', 'wallet_account_id')) {
                DB::table('koperasi_loans')
                    ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                    ->where('koperasi_members.user_id', $userId)
                    ->whereNull('koperasi_loans.wallet_account_id')
                    ->update([
                        'koperasi_loans.wallet_account_id' => $walletIds['loan'],
                    ]);
            }

            if (
                Schema::hasTable('koperasi_loan_installments')
                && Schema::hasColumn('koperasi_loan_installments', 'principal_wallet_account_id')
                && Schema::hasColumn('koperasi_loan_installments', 'income_wallet_account_id')
            ) {
                DB::table('koperasi_loan_installments')
                    ->join('koperasi_loans', 'koperasi_loans.id', '=', 'koperasi_loan_installments.koperasi_loan_id')
                    ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                    ->where('koperasi_members.user_id', $userId)
                    ->whereNull('koperasi_loan_installments.principal_wallet_account_id')
                    ->update([
                        'koperasi_loan_installments.principal_wallet_account_id' => $walletIds['installment_principal'],
                    ]);

                DB::table('koperasi_loan_installments')
                    ->join('koperasi_loans', 'koperasi_loans.id', '=', 'koperasi_loan_installments.koperasi_loan_id')
                    ->join('koperasi_members', 'koperasi_members.id', '=', 'koperasi_loans.koperasi_member_id')
                    ->where('koperasi_members.user_id', $userId)
                    ->whereNull('koperasi_loan_installments.income_wallet_account_id')
                    ->update([
                        'koperasi_loan_installments.income_wallet_account_id' => $walletIds['installment_income'],
                    ]);
            }
        }
    }

    private function ensureDefaultWalletIds(int $userId): array
    {
        $wallets = collect(KoperasiWalletAccount::defaultDefinitions())
            ->map(function (array $definition) use ($userId) {
                return KoperasiWalletAccount::query()->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'name' => $definition['name'],
                    ],
                    [
                        'wallet_type' => $definition['wallet_type'],
                        'opening_balance' => $definition['opening_balance'],
                        'is_active' => true,
                        'description' => $definition['description'],
                    ]
                );
            })
            ->keyBy('wallet_type');

        return [
            'saving' => $wallets[KoperasiWalletAccount::TYPE_HOLDING]->id,
            'loan' => $wallets[KoperasiWalletAccount::TYPE_LENDING]->id,
            'installment_principal' => $wallets[KoperasiWalletAccount::TYPE_LENDING]->id,
            'installment_income' => $wallets[KoperasiWalletAccount::TYPE_INCOME]->id,
        ];
    }
};
