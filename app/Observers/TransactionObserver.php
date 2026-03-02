<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->log($transaction, 'created');
    }

    public function updated(Transaction $transaction): void
    {
        $this->log($transaction, 'updated');
    }

    public function deleted(Transaction $transaction): void
    {
        $this->log($transaction, 'deleted');
    }

    private function log(Transaction $transaction, string $event): void
    {
        if (!in_array((string) $transaction->type, ['income', 'expense'], true)) {
            return;
        }

        $actor = request()?->user();
        if (!$actor) {
            return;
        }

        $eventLabel = match ($event) {
            'created' => 'menambahkan',
            'updated' => 'mengubah',
            'deleted' => 'menghapus',
            default => 'memproses',
        };

        $typeLabel = $transaction->type === 'income' ? 'pemasukan' : 'pengeluaran';
        $amount = number_format((float) $transaction->amount, 0, ',', '.');

        $meta = [
            'type' => $transaction->type,
            'amount' => (float) $transaction->amount,
            'date' => (string) $transaction->date,
            'note' => $transaction->note,
            'category_id' => $transaction->category_id,
            'bank_account_id' => $transaction->bank_account_id,
            'project_id' => $transaction->project_id,
        ];

        if ($event === 'updated') {
            $meta['changes'] = $transaction->getChanges();
            $meta['original'] = $transaction->getOriginal();
        }

        ActivityLog::create([
            'tenant_user_id' => (int) $transaction->user_id,
            'actor_user_id' => (int) $actor->id,
            'action' => $event,
            'subject_type' => Transaction::class,
            'subject_id' => (int) $transaction->id,
            'description' => sprintf(
                '%s %s transaksi %s sebesar Rp %s',
                $actor->name,
                $eventLabel,
                $typeLabel,
                $amount
            ),
            'meta' => $meta,
        ]);
    }
}

