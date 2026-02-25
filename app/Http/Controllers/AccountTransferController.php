<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountTransferController extends Controller
{
    public function index()
    {
        $tenantId = auth()->user()->tenantUserId();

        $organizations = $this->organizationQuery()
            ->orderBy('organization_name')
            ->orderBy('name')
            ->get(['id', 'name', 'organization_name']);

        if (!$organizations->contains('id', $tenantId)) {
            $tenantOrganization = User::query()
                ->where('id', $tenantId)
                ->first(['id', 'name', 'organization_name']);

            if ($tenantOrganization) {
                $organizations->push($tenantOrganization);
                $organizations = $organizations->sortBy([
                    ['organization_name', 'asc'],
                    ['name', 'asc'],
                ])->values();
            }
        }

        $organizationIds = $organizations->pluck('id')->all();

        $organizationAccounts = BankAccount::withoutGlobalScope('current_user')
            ->whereIn('user_id', $organizationIds)
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'bank_name', 'account_number']);

        $ownAccounts = $organizationAccounts
            ->where('user_id', $tenantId)
            ->values();

        $accountsByOrganization = $organizationAccounts
            ->groupBy('user_id')
            ->map(function ($items) {
                return $items->map(function (BankAccount $account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'bank_name' => $account->bank_name,
                        'account_number' => $account->account_number,
                    ];
                })->values();
            })
            ->toArray();

        $transfers = AccountTransfer::with([
            'senderOrganization:id,name,organization_name',
            'receiverOrganization:id,name,organization_name',
            'senderBankAccount:id,name,bank_name,account_number',
            'receiverBankAccount:id,name,bank_name,account_number',
            'requestedBy:id,name',
            'processedBy:id,name',
        ])
            ->where(function ($query) use ($tenantId) {
                $query->where('sender_user_id', $tenantId)
                    ->orWhere('receiver_user_id', $tenantId);
            })
            ->latest('id')
            ->limit(150)
            ->get();

        $incomingRequests = AccountTransfer::with([
            'receiverOrganization:id,name,organization_name',
            'receiverBankAccount:id,name,bank_name,account_number',
            'requestedBy:id,name',
        ])
            ->where('kind', AccountTransfer::KIND_PAYMENT_REQUEST)
            ->where('status', AccountTransfer::STATUS_PENDING)
            ->where('sender_user_id', $tenantId)
            ->latest('id')
            ->get();

        $outgoingRequests = AccountTransfer::with([
            'senderOrganization:id,name,organization_name',
            'receiverBankAccount:id,name,bank_name,account_number',
        ])
            ->where('kind', AccountTransfer::KIND_PAYMENT_REQUEST)
            ->where('status', AccountTransfer::STATUS_PENDING)
            ->where('receiver_user_id', $tenantId)
            ->latest('id')
            ->get();

        return view('transfers.index', compact(
            'organizations',
            'ownAccounts',
            'accountsByOrganization',
            'transfers',
            'incomingRequests',
            'outgoingRequests',
            'tenantId'
        ));
    }

    public function storeDirect(Request $request)
    {
        $validated = $request->validate([
            'sender_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'receiver_user_id' => 'required|integer|exists:users,id',
            'receiver_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenantUserId();
        $receiverOrganization = $this->findOrganization((int) $validated['receiver_user_id']);

        if (!$receiverOrganization) {
            throw ValidationException::withMessages([
                'receiver_user_id' => 'Organisasi tujuan tidak valid.',
            ]);
        }

        $senderOrganization = User::query()->findOrFail($tenantId);
        $senderAccount = $this->findBankAccountForUser(
            (int) $validated['sender_bank_account_id'],
            $tenantId
        );
        $receiverAccount = $this->findBankAccountForUser(
            (int) $validated['receiver_bank_account_id'],
            (int) $receiverOrganization->id
        );

        if (!$senderAccount) {
            throw ValidationException::withMessages([
                'sender_bank_account_id' => 'Rekening asal tidak ditemukan untuk organisasi Anda.',
            ]);
        }

        if (!$receiverAccount) {
            throw ValidationException::withMessages([
                'receiver_bank_account_id' => 'Rekening tujuan tidak valid untuk organisasi tujuan.',
            ]);
        }

        if (
            (int) $senderAccount->user_id === (int) $receiverAccount->user_id
            && (int) $senderAccount->id === (int) $receiverAccount->id
        ) {
            throw ValidationException::withMessages([
                'receiver_bank_account_id' => 'Rekening asal dan tujuan tidak boleh sama.',
            ]);
        }

        DB::transaction(function () use ($validated, $senderAccount, $receiverAccount, $senderOrganization, $receiverOrganization) {
            $senderLocked = $this->findBankAccountForUser(
                (int) $senderAccount->id,
                (int) $senderOrganization->id,
                true
            );
            $receiverLocked = $this->findBankAccountForUser(
                (int) $receiverAccount->id,
                (int) $receiverOrganization->id,
                true
            );

            if (!$senderLocked || !$receiverLocked) {
                throw ValidationException::withMessages([
                    'sender_bank_account_id' => 'Rekening tidak ditemukan saat proses transfer.',
                ]);
            }

            $amount = (float) $validated['amount'];
            $senderLocked->balance -= $amount;
            $senderLocked->save();

            $receiverLocked->balance += $amount;
            $receiverLocked->save();

            $transfer = AccountTransfer::create([
                'sender_user_id' => (int) $senderOrganization->id,
                'receiver_user_id' => (int) $receiverOrganization->id,
                'sender_bank_account_id' => (int) $senderLocked->id,
                'receiver_bank_account_id' => (int) $receiverLocked->id,
                'kind' => AccountTransfer::KIND_DIRECT_TRANSFER,
                'status' => AccountTransfer::STATUS_COMPLETED,
                'amount' => $amount,
                'transfer_date' => $validated['transfer_date'],
                'note' => $validated['note'] ?? null,
                'requested_by_user_id' => auth()->id(),
                'processed_by_user_id' => auth()->id(),
                'processed_at' => now(),
            ]);

            if ((int) $senderOrganization->id !== (int) $receiverOrganization->id) {
                $this->recordCrossOrganizationTransactions(
                    $transfer,
                    $senderLocked,
                    $receiverLocked,
                    $senderOrganization,
                    $receiverOrganization
                );
            }
        });

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Transfer dana berhasil diproses.');
    }

    public function storePaymentRequest(Request $request)
    {
        $validated = $request->validate([
            'payer_user_id' => 'required|integer|exists:users,id',
            'receiver_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $tenantId = auth()->user()->tenantUserId();
        $payerOrganization = $this->findOrganization((int) $validated['payer_user_id']);

        if (!$payerOrganization) {
            throw ValidationException::withMessages([
                'payer_user_id' => 'Organisasi yang diminta membayar tidak valid.',
            ]);
        }

        if ((int) $payerOrganization->id === (int) $tenantId) {
            throw ValidationException::withMessages([
                'payer_user_id' => 'Request pembayaran harus ditujukan ke organisasi lain.',
            ]);
        }

        $receiverAccount = $this->findBankAccountForUser(
            (int) $validated['receiver_bank_account_id'],
            $tenantId
        );

        if (!$receiverAccount) {
            throw ValidationException::withMessages([
                'receiver_bank_account_id' => 'Rekening penerima tidak valid.',
            ]);
        }

        AccountTransfer::create([
            'sender_user_id' => (int) $payerOrganization->id,
            'receiver_user_id' => $tenantId,
            'sender_bank_account_id' => null,
            'receiver_bank_account_id' => (int) $receiverAccount->id,
            'kind' => AccountTransfer::KIND_PAYMENT_REQUEST,
            'status' => AccountTransfer::STATUS_PENDING,
            'amount' => (float) $validated['amount'],
            'transfer_date' => $validated['transfer_date'],
            'note' => $validated['note'] ?? null,
            'requested_by_user_id' => auth()->id(),
            'processed_by_user_id' => null,
            'processed_at' => null,
        ]);

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Request pembayaran berhasil dikirim.');
    }

    public function payPaymentRequest(Request $request, AccountTransfer $transfer)
    {
        $tenantId = auth()->user()->tenantUserId();

        if ($transfer->kind !== AccountTransfer::KIND_PAYMENT_REQUEST) {
            abort(404);
        }

        if ((int) $transfer->sender_user_id !== (int) $tenantId) {
            abort(403, 'Anda tidak berhak memproses request ini.');
        }

        if ($transfer->status !== AccountTransfer::STATUS_PENDING) {
            return back()->withErrors([
                'request_payment' => 'Request pembayaran ini sudah diproses sebelumnya.',
            ]);
        }

        $validated = $request->validate([
            'sender_bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'transfer_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $senderOrganization = User::query()->findOrFail((int) $transfer->sender_user_id);
        $receiverOrganization = User::query()->findOrFail((int) $transfer->receiver_user_id);

        DB::transaction(function () use ($validated, $transfer, $senderOrganization, $receiverOrganization) {
            $senderAccount = $this->findBankAccountForUser(
                (int) $validated['sender_bank_account_id'],
                (int) $senderOrganization->id,
                true
            );
            $receiverAccount = $this->findBankAccountForUser(
                (int) $transfer->receiver_bank_account_id,
                (int) $receiverOrganization->id,
                true
            );

            if (!$senderAccount) {
                throw ValidationException::withMessages([
                    'sender_bank_account_id' => 'Rekening sumber pembayaran tidak valid.',
                ]);
            }

            if (!$receiverAccount) {
                throw ValidationException::withMessages([
                    'request_payment' => 'Rekening penerima tidak ditemukan. Minta pemohon memperbarui request.',
                ]);
            }

            if (
                (int) $senderAccount->user_id === (int) $receiverAccount->user_id
                && (int) $senderAccount->id === (int) $receiverAccount->id
            ) {
                throw ValidationException::withMessages([
                    'sender_bank_account_id' => 'Rekening sumber dan tujuan tidak boleh sama.',
                ]);
            }

            $amount = (float) $transfer->amount;
            $senderAccount->balance -= $amount;
            $senderAccount->save();

            $receiverAccount->balance += $amount;
            $receiverAccount->save();

            $paymentNote = trim((string) ($validated['note'] ?? ''));
            $existingNote = trim((string) ($transfer->note ?? ''));
            $combinedNote = $existingNote;
            if ($paymentNote !== '') {
                $combinedNote = $combinedNote === ''
                    ? $paymentNote
                    : $combinedNote . "\nKonfirmasi bayar: " . $paymentNote;
            }

            $transfer->update([
                'sender_bank_account_id' => (int) $senderAccount->id,
                'status' => AccountTransfer::STATUS_COMPLETED,
                'transfer_date' => $validated['transfer_date'],
                'note' => $combinedNote !== '' ? $combinedNote : null,
                'processed_by_user_id' => auth()->id(),
                'processed_at' => now(),
                'rejected_reason' => null,
            ]);

            $transfer->refresh();

            if ((int) $senderOrganization->id !== (int) $receiverOrganization->id) {
                $this->recordCrossOrganizationTransactions(
                    $transfer,
                    $senderAccount,
                    $receiverAccount,
                    $senderOrganization,
                    $receiverOrganization
                );
            }
        });

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Request pembayaran berhasil dibayar.');
    }

    public function rejectPaymentRequest(Request $request, AccountTransfer $transfer)
    {
        $tenantId = auth()->user()->tenantUserId();

        if ($transfer->kind !== AccountTransfer::KIND_PAYMENT_REQUEST) {
            abort(404);
        }

        if ((int) $transfer->sender_user_id !== (int) $tenantId) {
            abort(403, 'Anda tidak berhak menolak request ini.');
        }

        if ($transfer->status !== AccountTransfer::STATUS_PENDING) {
            return back()->withErrors([
                'request_payment' => 'Request pembayaran ini sudah diproses sebelumnya.',
            ]);
        }

        $validated = $request->validate([
            'rejected_reason' => 'nullable|string|max:1000',
        ]);

        $transfer->update([
            'status' => AccountTransfer::STATUS_REJECTED,
            'processed_by_user_id' => auth()->id(),
            'processed_at' => now(),
            'rejected_reason' => $validated['rejected_reason'] ?? null,
        ]);

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Request pembayaran ditolak.');
    }

    public function cancelPaymentRequest(AccountTransfer $transfer)
    {
        $tenantId = auth()->user()->tenantUserId();

        if ($transfer->kind !== AccountTransfer::KIND_PAYMENT_REQUEST) {
            abort(404);
        }

        if ((int) $transfer->receiver_user_id !== (int) $tenantId) {
            abort(403, 'Anda tidak berhak membatalkan request ini.');
        }

        if ($transfer->status !== AccountTransfer::STATUS_PENDING) {
            return back()->withErrors([
                'request_payment' => 'Request pembayaran ini sudah diproses sebelumnya.',
            ]);
        }

        $transfer->update([
            'status' => AccountTransfer::STATUS_CANCELLED,
            'processed_by_user_id' => auth()->id(),
            'processed_at' => now(),
        ]);

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Request pembayaran dibatalkan.');
    }

    private function organizationQuery(): Builder
    {
        return User::query()
            ->where('is_platform_admin', false)
            ->where('is_admin', true)
            ->where('account_status', User::STATUS_APPROVED)
            ->whereColumn('data_owner_user_id', 'id');
    }

    private function findOrganization(int $organizationId): ?User
    {
        return $this->organizationQuery()
            ->where('id', $organizationId)
            ->first(['id', 'name', 'organization_name']);
    }

    private function findBankAccountForUser(int $bankAccountId, int $userId, bool $forUpdate = false): ?BankAccount
    {
        $query = BankAccount::withoutGlobalScope('current_user')
            ->where('id', $bankAccountId)
            ->where('user_id', $userId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function recordCrossOrganizationTransactions(
        AccountTransfer $transfer,
        BankAccount $senderAccount,
        BankAccount $receiverAccount,
        User $senderOrganization,
        User $receiverOrganization
    ): void {
        $reference = 'TRF-' . str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT);
        $senderLabel = $this->organizationLabel($senderOrganization);
        $receiverLabel = $this->organizationLabel($receiverOrganization);
        $cleanNote = trim((string) ($transfer->note ?? ''));
        $noteSuffix = $cleanNote !== '' ? ' | Catatan: ' . $cleanNote : '';

        $senderNote = '[TRANSFER] Keluar ke ' . $receiverLabel
            . ' | Rekening tujuan: ' . $receiverAccount->name
            . ' | Ref: ' . $reference
            . $noteSuffix;

        $receiverNote = '[TRANSFER] Masuk dari ' . $senderLabel
            . ' | Rekening asal: ' . $senderAccount->name
            . ' | Ref: ' . $reference
            . $noteSuffix;

        Transaction::create([
            'user_id' => (int) $senderOrganization->id,
            'type' => 'expense',
            'category_id' => null,
            'project_id' => null,
            'bank_account_id' => (int) $senderAccount->id,
            'amount' => (float) $transfer->amount,
            'date' => $transfer->transfer_date,
            'note' => $senderNote,
        ]);

        Transaction::create([
            'user_id' => (int) $receiverOrganization->id,
            'type' => 'income',
            'category_id' => null,
            'project_id' => null,
            'bank_account_id' => (int) $receiverAccount->id,
            'amount' => (float) $transfer->amount,
            'date' => $transfer->transfer_date,
            'note' => $receiverNote,
        ]);
    }

    private function organizationLabel(User $organization): string
    {
        $org = trim((string) ($organization->organization_name ?? ''));
        $name = trim((string) ($organization->name ?? ''));

        if ($org === '') {
            return $name !== '' ? $name : 'Organisasi';
        }

        if ($name === '' || strcasecmp($org, $name) === 0) {
            return $org;
        }

        return $org . ' - ' . $name;
    }
}
