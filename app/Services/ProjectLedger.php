<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Project;
use App\Models\ProjectTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProjectLedger
{
    /**
     * Catat transaksi proyek + ledger umum + update saldo rekening.
     */
    public function record(array $data): ProjectTransaction
    {
        $required = ['project_id', 'bank_account_id', 'type', 'amount', 'date'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Field {$field} wajib diisi");
            }
        }

        return DB::transaction(function () use ($data) {
            $project = Project::findOrFail($data['project_id']);
            $bank    = BankAccount::findOrFail($data['bank_account_id']);

            if ($project->user_id !== $bank->user_id) {
                throw new InvalidArgumentException('Rekening tidak sesuai dengan pemilik proyek');
            }

            // Simpan project_transactions
            $projectTx = ProjectTransaction::create([
                'project_id'      => $project->id,
                'bank_account_id' => $bank->id,
                'category_id'     => $data['category_id'] ?? null,
                'type'            => $data['type'],
                'amount'          => $data['amount'],
                'date'            => $data['date'],
                'note'            => $data['note'] ?? null,
            ]);

            // Catat ke transaksi global (ledger umum)
            $txnType = in_array($data['type'], ['expense', 'allocation', 'transfer_in'])
                ? 'expense'
                : 'income';

            Transaction::create([
                'user_id'        => $project->user_id,
                'type'           => $txnType,
                'category_id'    => $data['category_id'] ?? null,
                'project_id'     => $project->id,
                'bank_account_id'=> $bank->id,
                'amount'         => $data['amount'],
                'date'           => $data['date'],
                'note'           => $data['note'] ?? null,
            ]);

            // Update saldo rekening
            $bank->balance = $txnType === 'income'
                ? $bank->balance + $data['amount']
                : $bank->balance - $data['amount'];
            $bank->save();

            return $projectTx;
        });
    }

    /**
     * Hitung ulang saldo rekening berdasarkan semua transaksi global.
     */
    public function recalculateBankBalance(int $bankAccountId): float
    {
        $bank = BankAccount::findOrFail($bankAccountId);

        $income = Transaction::where('bank_account_id', $bankAccountId)
            ->where('type', 'income')
            ->sum('amount');

        $expense = Transaction::where('bank_account_id', $bankAccountId)
            ->where('type', 'expense')
            ->sum('amount');

        $balance = $income - $expense;

        return (float) $balance;
    }
}
